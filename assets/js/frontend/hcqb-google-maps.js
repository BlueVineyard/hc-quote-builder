/**
 * hcqb-google-maps.js
 *
 * Google Maps integration for HC Quote Builder Frame 2.
 *
 * Registers window.hcqbMapsInit as the async callback for the Google Maps API
 * script. When the API is ready it:
 *   - Attaches Places Autocomplete to #hcqb-address-search
 *   - On place_changed: parses address components → fills read-only fields,
 *     hidden lat/lng inputs, auto-selects the phone prefix, calls Distance Matrix
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

	// Internal references set once the Maps API is ready.
	var map            = null;
	var customerMarker = null;

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
	// -------------------------------------------------------------------------

	function calculateDistance( destination ) {
		var locale = window.HCQBLocale || {};

		var warehouseLat = parseFloat( locale.warehouseLat );
		var warehouseLng = parseFloat( locale.warehouseLng );
		if ( ! warehouseLat || ! warehouseLng ) { return; }

		var service = new google.maps.DistanceMatrixService();
		service.getDistanceMatrix(
			{
				origins:      [ new google.maps.LatLng( warehouseLat, warehouseLng ) ],
				destinations: [ destination ],
				travelMode:   google.maps.TravelMode.DRIVING,
				unitSystem:   google.maps.UnitSystem.METRIC,
			},
			function ( response, status ) {
				if ( status !== 'OK' ) { return; }
				var element = response.rows[ 0 ].elements[ 0 ];
				if ( element.status !== 'OK' ) { return; }
				setField( 'hcqb-distance', element.distance.text );
			}
		);
	}

	// -------------------------------------------------------------------------
	// Utility
	// -------------------------------------------------------------------------

	function setField( id, value ) {
		var el = document.getElementById( id );
		if ( el ) { el.value = value; }
	}

}() );
