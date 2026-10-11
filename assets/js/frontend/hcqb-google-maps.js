/**
 * hcqb-google-maps.js
 *
 * Google Maps integration for HC Quote Builder Frame 2.
 *
 * Registers window.hcqbMapsInit as the async callback for the Google Maps API
 * script. When the API is ready it:
 *   - Attaches Places Autocomplete to #hcqb-address-search
 *   - On place_changed: parses address components → fills the address fields,
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

		var locale   = window.HCQBLocale || {};
		var countries = ( locale.supportedCountries || [ 'AU' ] ).map( function ( c ) {
			return c.toLowerCase();
		} );

		var autocomplete = new google.maps.places.Autocomplete( searchInput, {
			fields:               [ 'address_components', 'geometry', 'name' ],
			componentRestrictions: { country: countries },
		} );

		autocomplete.addListener( 'place_changed', function () {
			var place = autocomplete.getPlace();
			if ( ! place || ! place.geometry ) { return; }

			parseAndFillAddress( place );
			updateMap( place.geometry.location );
			calculateDistance( place.geometry.location );
		} );
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

	function parseAndFillAddress( place ) {
		var streetNumber = '';
		var route        = '';
		var locality     = '';
		var adminLevel   = '';
		var postalCode   = '';
		var country      = '';

		( place.address_components || [] ).forEach( function ( comp ) {
			var types = comp.types;
			if ( types.indexOf( 'street_number' )               >= 0 ) { streetNumber = comp.long_name;  }
			if ( types.indexOf( 'route' )                       >= 0 ) { route        = comp.long_name;  }
			if ( types.indexOf( 'locality' )                    >= 0 ) { locality     = comp.long_name;  }
			if ( types.indexOf( 'administrative_area_level_1' ) >= 0 ) { adminLevel   = comp.short_name; }
			if ( types.indexOf( 'postal_code' )                 >= 0 ) { postalCode   = comp.long_name;  }
			if ( types.indexOf( 'country' )                     >= 0 ) { country      = comp.short_name; }
		} );

		var street = streetNumber ? ( streetNumber + ' ' + route ).trim() : route;

		setField( 'hcqb-street',   street     );
		setField( 'hcqb-suburb',   locality   );
		setField( 'hcqb-state',    adminLevel );
		setField( 'hcqb-postcode', postalCode );
		setField( 'hcqb-lat',      place.geometry.location.lat() );
		setField( 'hcqb-lng',      place.geometry.location.lng() );

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
