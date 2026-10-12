/**
 * hcqb-google-maps.js
 *
 * Google Maps integration for HC Quote Builder Frame 2.
 *
 * Registers window.hcqbMapsInit as the async callback for the Google Maps API
 * script. When the API is ready it:
 *   - Adds a custom suggestion dropdown to #hcqb-address-search backed by the
 *     Places API (New) AutocompleteSuggestion service; falls back to the
 *     legacy Places Autocomplete if that service is unavailable
 *   - On place selection: parses address components → fills the address fields,
 *     hidden lat/lng inputs, auto-selects the phone prefix, calls Distance Matrix
 *   - Address fields are also editable by hand (fallback when the search box
 *     fails). A manual edit clears the now-stale coordinates/marker/distance and
 *     re-runs Distance Matrix against the typed address string. This listener is
 *     bound on DOMContentLoaded, independent of the Maps API loading at all.
 *   - Initialises a Google Map centred on the warehouse location
 *   - Listens to hcqb:frame-2-shown to trigger a map resize (layout flush)
 *
 * Requires: HCQBLocale (warehouseLat, warehouseLng, supportedCountries)
 * — injected via wp_localize_script before hcqb-quote-builder.js.
 *
 * @package HC_Quote_Builder
 */

( function () {
	'use strict';

	// Dial code lookup — must mirror $phone_prefix_map in frame-2-contact.php.
	var PHONE_PREFIXES = { AU: '+61', NZ: '+64' };

	// Country names appended to manually typed addresses for Distance Matrix.
	var COUNTRY_NAMES = { AU: 'Australia', NZ: 'New Zealand' };

	// Editable address fields — order matters for building the address string.
	var ADDRESS_FIELD_IDS = [ 'hcqb-street', 'hcqb-suburb', 'hcqb-state', 'hcqb-postcode' ];

	// Internal references set once the Maps API is ready.
	var map            = null;
	var customerMarker = null;

	// Incremented per request/edit so late Distance Matrix responses are ignored.
	var distanceRequestId = 0;

	// -------------------------------------------------------------------------
	// hcqbMapsInit — called by the Google Maps API as its async callback.
	// -------------------------------------------------------------------------

	window.hcqbMapsInit = function () {
		initAutocomplete();
		initMap();

		// Trigger a resize whenever Frame 2 becomes visible so the map tiles
		// render correctly (the div was hidden during map initialisation).
		document.addEventListener( 'hcqb:frame-2-shown', function () {
			if ( map ) {
				google.maps.event.trigger( map, 'resize' );
				// Re-centre after resize so the warehouse pin stays visible.
				if ( ! customerMarker ) {
					var locale       = window.HCQBLocale || {};
					var warehouseLat = parseFloat( locale.warehouseLat ) || 0;
					var warehouseLng = parseFloat( locale.warehouseLng ) || 0;
					if ( warehouseLat && warehouseLng ) {
						map.setCenter( { lat: warehouseLat, lng: warehouseLng } );
					}
				}
			}
		} );
	};

	// -------------------------------------------------------------------------
	// Places Autocomplete
	// -------------------------------------------------------------------------

	function initAutocomplete() {
		var searchInput = document.getElementById( 'hcqb-address-search' );
		if ( ! searchInput ) { return; }

		var locale    = window.HCQBLocale || {};
		var countries = ( locale.supportedCountries || [ 'AU' ] ).map( function ( c ) {
			return c.toLowerCase();
		} );

		if ( typeof google.maps.importLibrary !== 'function' ) {
			initLegacyAutocomplete( searchInput, countries );
			return;
		}

		google.maps.importLibrary( 'places' ).then( function ( places ) {
			if ( ! places.AutocompleteSuggestion ) {
				initLegacyAutocomplete( searchInput, countries );
				return;
			}
			initSuggestionAutocomplete( places, searchInput, countries );
		} ).catch( function () {
			initLegacyAutocomplete( searchInput, countries );
		} );
	}

	// Places API (New) — data-only AutocompleteSuggestion service rendered into
	// our own dropdown, so the search box keeps the form's native input styling
	// (the PlaceAutocompleteElement web component can't be themed to match).
	function initSuggestionAutocomplete( places, input, countries ) {
		var wrap = input.closest( '.hcqb-field-wrap' ) || input.parentElement;

		var list = document.createElement( 'ul' );
		list.id        = 'hcqb-address-suggestions';
		list.className = 'hcqb-ac-list';
		list.setAttribute( 'role', 'listbox' );
		list.hidden = true;
		wrap.appendChild( list );

		input.setAttribute( 'role', 'combobox' );
		input.setAttribute( 'aria-autocomplete', 'list' );
		input.setAttribute( 'aria-expanded', 'false' );
		input.setAttribute( 'aria-controls', list.id );

		var predictions  = [];
		var activeIndex  = -1;
		var sessionToken = null;
		var debounce     = null;
		var requestId    = 0;

		input.addEventListener( 'input', function () {
			clearTimeout( debounce );
			var query = input.value.trim();
			if ( query.length < 3 ) { close(); return; }
			debounce = setTimeout( function () { fetchSuggestions( query ); }, 200 );
		} );

		input.addEventListener( 'keydown', function ( e ) {
			if ( list.hidden ) { return; }
			if ( 'ArrowDown' === e.key ) {
				e.preventDefault();
				setActive( ( activeIndex + 1 ) % predictions.length );
			} else if ( 'ArrowUp' === e.key ) {
				e.preventDefault();
				setActive( activeIndex <= 0 ? predictions.length - 1 : activeIndex - 1 );
			} else if ( 'Enter' === e.key ) {
				// Keep Enter from submitting the quote form while choosing.
				e.preventDefault();
				select( activeIndex >= 0 ? activeIndex : 0 );
			} else if ( 'Escape' === e.key ) {
				close();
			}
		} );

		input.addEventListener( 'blur', function () {
			// Delay so a click on a suggestion lands before the list closes.
			setTimeout( close, 150 );
		} );

		// mousedown (not click) fires before blur and keeps focus in the input.
		list.addEventListener( 'mousedown', function ( e ) {
			var item = e.target.closest( '.hcqb-ac-item' );
			if ( ! item ) { return; }
			e.preventDefault();
			select( Number( item.dataset.index ) );
		} );

		function fetchSuggestions( query ) {
			if ( ! sessionToken ) {
				sessionToken = new places.AutocompleteSessionToken();
			}
			var thisRequest = ++requestId;

			places.AutocompleteSuggestion.fetchAutocompleteSuggestions( {
				input:               query,
				includedRegionCodes: countries,
				sessionToken:        sessionToken,
			} ).then( function ( result ) {
				if ( thisRequest !== requestId ) { return; }
				predictions = ( result.suggestions || [] )
					.map( function ( s ) { return s.placePrediction; } )
					.filter( Boolean )
					.slice( 0, 5 );
				render();
			} ).catch( function () {
				if ( thisRequest === requestId ) { close(); }
			} );
		}

		function render() {
			list.textContent = '';
			activeIndex = -1;
			input.removeAttribute( 'aria-activedescendant' );

			if ( ! predictions.length ) { close(); return; }

			predictions.forEach( function ( p, i ) {
				var item = document.createElement( 'li' );
				item.id            = 'hcqb-ac-item-' + i;
				item.className     = 'hcqb-ac-item';
				item.dataset.index = i;
				item.setAttribute( 'role', 'option' );
				item.setAttribute( 'aria-selected', 'false' );

				var main = document.createElement( 'span' );
				main.className   = 'hcqb-ac-item__main';
				main.textContent = p.mainText ? p.mainText.text : p.text.text;
				item.appendChild( main );

				if ( p.secondaryText && p.secondaryText.text ) {
					var secondary = document.createElement( 'span' );
					secondary.className   = 'hcqb-ac-item__secondary';
					secondary.textContent = p.secondaryText.text;
					item.appendChild( secondary );
				}

				list.appendChild( item );
			} );

			// Google attribution — required when showing Places predictions.
			var footer = document.createElement( 'li' );
			footer.className   = 'hcqb-ac-attribution';
			footer.textContent = 'Powered by Google';
			footer.setAttribute( 'aria-hidden', 'true' );
			list.appendChild( footer );

			list.hidden = false;
			input.setAttribute( 'aria-expanded', 'true' );
		}

		function setActive( index ) {
			var items = list.querySelectorAll( '.hcqb-ac-item' );
			items.forEach( function ( item, i ) {
				var on = i === index;
				item.classList.toggle( 'hcqb-ac-item--active', on );
				item.setAttribute( 'aria-selected', on ? 'true' : 'false' );
			} );
			activeIndex = index;
			if ( items[ index ] ) {
				input.setAttribute( 'aria-activedescendant', items[ index ].id );
			}
		}

		function close() {
			list.hidden = true;
			list.textContent = '';
			activeIndex = -1;
			input.setAttribute( 'aria-expanded', 'false' );
			input.removeAttribute( 'aria-activedescendant' );
		}

		function select( index ) {
			var prediction = predictions[ index ];
			if ( ! prediction ) { return; }

			input.value = prediction.text.text;
			close();
			requestId++; // Drop any in-flight suggestion request.

			var place = prediction.toPlace();
			// fetchFields() ends the billing session; start a new one next time.
			sessionToken = null;

			place.fetchFields( { fields: [ 'addressComponents', 'location' ] } ).then( function () {
				if ( ! place.location ) { return; }
				var components = ( place.addressComponents || [] ).map( function ( c ) {
					return { types: c.types, long: c.longText, short: c.shortText };
				} );
				onPlaceSelected( components, place.location );
			} ).catch( function () {} );
		}
	}

	// Legacy Places Autocomplete (deprecated March 2025) — fallback only.
	function initLegacyAutocomplete( searchInput, countries ) {
		if ( ! google.maps.places || ! google.maps.places.Autocomplete ) { return; }

		var autocomplete = new google.maps.places.Autocomplete( searchInput, {
			fields:               [ 'address_components', 'geometry' ],
			componentRestrictions: { country: countries },
		} );

		autocomplete.addListener( 'place_changed', function () {
			var place = autocomplete.getPlace();
			if ( ! place || ! place.geometry ) { return; }
			var components = ( place.address_components || [] ).map( function ( c ) {
				return { types: c.types, long: c.long_name, short: c.short_name };
			} );
			onPlaceSelected( components, place.geometry.location );
		} );
	}

	// components: normalised [ { types, long, short } ]; location: LatLng.
	function onPlaceSelected( components, location ) {
		parseAndFillAddress( components, location );
		updateMap( location );
		calculateDistance( location );
	}

	// -------------------------------------------------------------------------
	// Manual address entry
	// Bound on DOMContentLoaded so it works even if the Maps API never loads.
	// setField() assigns .value directly, which fires no input/change events,
	// so Places auto-fill never triggers these handlers.
	// -------------------------------------------------------------------------

	document.addEventListener( 'DOMContentLoaded', function () {
		ADDRESS_FIELD_IDS.forEach( function ( id ) {
			var el = document.getElementById( id );
			if ( ! el ) { return; }
			el.addEventListener( 'input',  onManualAddressInput );
			el.addEventListener( 'change', onManualAddressChange );
		} );
	} );

	// Any keystroke invalidates the Places result the fields were filled from.
	function onManualAddressInput() {
		setField( 'hcqb-lat', '' );
		setField( 'hcqb-lng', '' );
		if ( customerMarker ) {
			customerMarker.setMap( null );
			customerMarker = null;
		}
		clearDistance();
	}

	// On commit (blur after edit), estimate distance from the typed address.
	function onManualAddressChange() {
		var suburb   = fieldValue( 'hcqb-suburb' );
		var postcode = fieldValue( 'hcqb-postcode' );

		// Not enough to locate the address yet.
		if ( ! suburb && ! postcode ) { return; }
		if ( ! window.google || ! google.maps || ! google.maps.DistanceMatrixService ) { return; }

		var parts = ADDRESS_FIELD_IDS.map( fieldValue ).filter( Boolean );

		var countries = ( ( window.HCQBLocale || {} ).supportedCountries || [ 'AU' ] );
		if ( 1 === countries.length && COUNTRY_NAMES[ countries[ 0 ] ] ) {
			parts.push( COUNTRY_NAMES[ countries[ 0 ] ] );
		}

		calculateDistance( parts.join( ', ' ) );
	}

	// -------------------------------------------------------------------------
	// Address parsing + field population
	// -------------------------------------------------------------------------

	function parseAndFillAddress( components, location ) {
		var streetNumber = '';
		var route        = '';
		var locality     = '';
		var adminLevel   = '';
		var postalCode   = '';
		var country      = '';

		components.forEach( function ( comp ) {
			var types = comp.types || [];
			if ( types.indexOf( 'street_number' )               >= 0 ) { streetNumber = comp.long;  }
			if ( types.indexOf( 'route' )                       >= 0 ) { route        = comp.long;  }
			if ( types.indexOf( 'locality' )                    >= 0 ) { locality     = comp.long;  }
			if ( types.indexOf( 'administrative_area_level_1' ) >= 0 ) { adminLevel   = comp.short; }
			if ( types.indexOf( 'postal_code' )                 >= 0 ) { postalCode   = comp.long;  }
			if ( types.indexOf( 'country' )                     >= 0 ) { country      = comp.short; }
		} );

		var street = streetNumber ? ( streetNumber + ' ' + route ).trim() : route;

		setField( 'hcqb-street',   street     );
		setField( 'hcqb-suburb',   locality   );
		setField( 'hcqb-state',    adminLevel );
		setField( 'hcqb-postcode', postalCode );
		setField( 'hcqb-lat',      location.lat() );
		setField( 'hcqb-lng',      location.lng() );

		// Auto-select the matching phone prefix for the detected country.
		if ( country && PHONE_PREFIXES[ country ] ) {
			var dial      = PHONE_PREFIXES[ country ];
			var prefixSel = document.getElementById( 'hcqb-phone-prefix' );
			if ( prefixSel ) {
				Array.from( prefixSel.options ).forEach( function ( opt ) {
					opt.selected = ( opt.value === dial );
				} );
			}
		}
	}

	// -------------------------------------------------------------------------
	// Map initialisation
	// -------------------------------------------------------------------------

	function initMap() {
		var mapEl  = document.getElementById( 'hcqb-map' );
		var locale = window.HCQBLocale || {};

		// Fallback to Sydney CBD if warehouse coords not yet configured.
		var warehouseLat = parseFloat( locale.warehouseLat ) || -33.8688;
		var warehouseLng = parseFloat( locale.warehouseLng ) || 151.2093;
		var warehousePos = { lat: warehouseLat, lng: warehouseLng };

		if ( ! mapEl ) { return; }

		map = new google.maps.Map( mapEl, {
			center:              warehousePos,
			zoom:                8,
			mapTypeControl:      false,
			fullscreenControl:   false,
			streetViewControl:   false,
		} );

		// Warehouse marker (blue dot).
		new google.maps.Marker( {
			map:      map,
			position: warehousePos,
			title:    'Warehouse',
			icon:     { url: 'https://maps.google.com/mapfiles/ms/icons/blue-dot.png' },
		} );
	}

	// -------------------------------------------------------------------------
	// Map update after address selection
	// -------------------------------------------------------------------------

	function updateMap( location ) {
		var mapEl = document.getElementById( 'hcqb-map' );
		if ( ! mapEl || ! map ) { return; }

		map.setCenter( location );
		map.setZoom( 10 );

		if ( customerMarker ) {
			customerMarker.setPosition( location );
		} else {
			customerMarker = new google.maps.Marker( {
				map:      map,
				position: location,
				title:    'Delivery Location',
			} );
		}

		google.maps.event.trigger( map, 'resize' );
	}

	// -------------------------------------------------------------------------
	// Distance Matrix
	// destination: a LatLng (Places result) or an address string (manual entry).
	// Any failure clears the distance so a stale value is never submitted.
	// -------------------------------------------------------------------------

	function calculateDistance( destination ) {
		var locale = window.HCQBLocale || {};

		var warehouseLat = parseFloat( locale.warehouseLat );
		var warehouseLng = parseFloat( locale.warehouseLng );
		if ( ! warehouseLat || ! warehouseLng ) { return; }

		var requestId = ++distanceRequestId;
		var service   = new google.maps.DistanceMatrixService();
		service.getDistanceMatrix(
			{
				origins:      [ new google.maps.LatLng( warehouseLat, warehouseLng ) ],
				destinations: [ destination ],
				travelMode:   google.maps.TravelMode.DRIVING,
				unitSystem:   google.maps.UnitSystem.METRIC,
			},
			function ( response, status ) {
				if ( requestId !== distanceRequestId ) { return; }
				if ( status !== 'OK' ) { clearDistance(); return; }
				var element = response.rows[ 0 ].elements[ 0 ];
				if ( element.status !== 'OK' ) { clearDistance(); return; }
				setField( 'hcqb-distance', element.distance.text );

				// Dispatch numeric distance for shipping cost calculation.
				var distanceKm = element.distance.value / 1000;
				document.dispatchEvent( new CustomEvent( 'hcqb:distance-changed', {
					detail: { distanceKm: distanceKm, distanceText: element.distance.text },
				} ) );
			}
		);
	}

	// Reset distance + shipping estimate (hcqb-shipping.js hides at 0 km).
	// Also invalidates any in-flight Distance Matrix request.
	function clearDistance() {
		distanceRequestId++;
		setField( 'hcqb-distance', '' );
		document.dispatchEvent( new CustomEvent( 'hcqb:distance-changed', {
			detail: { distanceKm: 0, distanceText: '' },
		} ) );
	}

	// -------------------------------------------------------------------------
	// Utility
	// -------------------------------------------------------------------------

	function setField( id, value ) {
		var el = document.getElementById( id );
		if ( el ) { el.value = value; }
	}

	function fieldValue( id ) {
		var el = document.getElementById( id );
		return el ? el.value.trim() : '';
	}

}() );
