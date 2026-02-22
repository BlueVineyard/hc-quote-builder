/**
 * hcqb-admin-settings.js
 *
 * Handles:
 *   - Prefix options repeater  (add / remove / drag-and-drop reorder)
 *   - Status labels repeater   (add / remove / drag-and-drop reorder)
 *     Keys are generated ONCE on row creation — never regenerated on rename.
 *   - Google Places Autocomplete for warehouse address field
 *     (loaded via hcqbSettingsMapsInit callback when Maps JS API is ready)
 */

( function () {
	'use strict';

	// -------------------------------------------------------------------------
	// Repeater — initialise a single repeater container
	// -------------------------------------------------------------------------

	function initRepeater( repeater ) {
		const list   = repeater.querySelector( '.hcqb-repeater__list' );
		const addBtn = repeater.querySelector( '.hcqb-repeater__add' );

		if ( ! list ) return;

		// Attach listeners to pre-existing rows.
		list.querySelectorAll( '.hcqb-repeater__row' ).forEach( function ( row ) {
			initRowListeners( row );
		} );

		// Add-row button.
		if ( addBtn ) {
			addBtn.addEventListener( 'click', function () {
				const type = this.dataset.type;
				const row  = type === 'status' ? buildStatusRow() : buildPrefixRow();
				list.appendChild( row );
				initRowListeners( row );
				// Focus the first text input in the new row for convenience.
				const firstInput = row.querySelector( 'input[type="text"]' );
				if ( firstInput ) firstInput.focus();
			} );
		}
	}

	// -------------------------------------------------------------------------
	// Row — attach remove and drag listeners
	// -------------------------------------------------------------------------

	function initRowListeners( row ) {
		const removeBtn = row.querySelector( '.hcqb-repeater__remove' );
		if ( removeBtn ) {
			removeBtn.addEventListener( 'click', function () {
				row.remove();
			} );
		}
		initDragRow( row );
	}

	// -------------------------------------------------------------------------
	// Prefix row builder
	// -------------------------------------------------------------------------

	function buildPrefixRow() {
		const row = document.createElement( 'div' );
		row.className = 'hcqb-repeater__row';
		row.dataset.type = 'prefix';
		row.innerHTML =
			'<span class="hcqb-repeater__handle dashicons dashicons-menu" title="Drag to reorder"></span>' +
			'<input type="text"' +
			'       name="hcqb_settings[prefix_options][]"' +
			'       value=""' +
			'       class="regular-text"' +
			'       placeholder="e.g. Mr">' +
			'<button type="button" class="button hcqb-repeater__remove">Remove</button>';
		return row;
	}

	// -------------------------------------------------------------------------
	// Status row builder
	// Key is generated once here via Date.now() — the hidden input carries it
	// through every subsequent save unchanged.
	// -------------------------------------------------------------------------

	function buildStatusRow() {
		const key = 'status_' + Date.now();
		const row = document.createElement( 'div' );
		row.className = 'hcqb-repeater__row';
		row.dataset.type = 'status';
		row.innerHTML =
			'<span class="hcqb-repeater__handle dashicons dashicons-menu" title="Drag to reorder"></span>' +
			'<input type="hidden"' +
			'       name="hcqb_settings[submission_status_labels][][key]"' +
			'       value="' + key + '">' +
			'<span class="hcqb-status-key-display">' + key + '</span>' +
			'<input type="text"' +
			'       name="hcqb_settings[submission_status_labels][][label]"' +
			'       value=""' +
			'       class="regular-text"' +
			'       placeholder="Status label">' +
			'<button type="button" class="button hcqb-repeater__remove">Remove</button>';
		return row;
	}

	// -------------------------------------------------------------------------
	// Drag-and-drop row reorder
	// Uses the HTML5 Drag and Drop API on the row handle.
	// -------------------------------------------------------------------------

	function initDragRow( row ) {
		const handle = row.querySelector( '.hcqb-repeater__handle' );
		if ( ! handle ) return;

		handle.setAttribute( 'draggable', 'true' );

		handle.addEventListener( 'dragstart', function ( e ) {
			row.classList.add( 'hcqb-row--dragging' );
			e.dataTransfer.effectAllowed = 'move';
			// Store a reference on the dataTransfer so we can identify the dragged row.
			e.dataTransfer.setData( 'text/plain', '' );
		} );

		handle.addEventListener( 'dragend', function () {
			row.classList.remove( 'hcqb-row--dragging' );
			document.querySelectorAll( '.hcqb-row--over' ).forEach( function ( r ) {
				r.classList.remove( 'hcqb-row--over' );
			} );
		} );

		row.addEventListener( 'dragover', function ( e ) {
			e.preventDefault();
			e.dataTransfer.dropEffect = 'move';

			const dragging = document.querySelector( '.hcqb-row--dragging' );
			if ( ! dragging || dragging === row ) return;

			const list = row.parentElement;
			// Prevent dragging between different repeater lists.
			if ( ! dragging.parentElement || dragging.parentElement !== list ) return;

			const rows    = Array.from( list.children );
			const dragIdx = rows.indexOf( dragging );
			const overIdx = rows.indexOf( row );

			if ( dragIdx < overIdx ) {
				list.insertBefore( dragging, row.nextSibling );
			} else {
				list.insertBefore( dragging, row );
			}
		} );

		row.addEventListener( 'dragenter', function () {
			const dragging = document.querySelector( '.hcqb-row--dragging' );
			if ( dragging && dragging !== row ) {
				row.classList.add( 'hcqb-row--over' );
			}
		} );

		row.addEventListener( 'dragleave', function () {
			row.classList.remove( 'hcqb-row--over' );
		} );
	}

	// -------------------------------------------------------------------------
	// Google Places Autocomplete — warehouse address
	// Registered as window.hcqbSettingsMapsInit so the Maps JS API can call it
	// as the async callback once the script has loaded.
	// -------------------------------------------------------------------------

	window.hcqbSettingsMapsInit = function () {
		const addressInput = document.getElementById( 'hcqb_warehouse_address' );
		if ( ! addressInput ) return;

		// The field renders as readonly when no API key was saved.
		// Once the Maps API loads we know a key exists, so unlock it.
		addressInput.removeAttribute( 'readonly' );

		const autocomplete = new google.maps.places.Autocomplete( addressInput, {
			types: [ 'geocode' ],
		} );

		autocomplete.addListener( 'place_changed', function () {
			const place = autocomplete.getPlace();
			if ( ! place || ! place.geometry ) return;

			const lat = place.geometry.location.lat();
			const lng = place.geometry.location.lng();

			const latField = document.getElementById( 'hcqb_warehouse_lat' );
			const lngField = document.getElementById( 'hcqb_warehouse_lng' );

			if ( latField ) latField.value = lat;
			if ( lngField ) lngField.value = lng;
		} );
	};

	// -------------------------------------------------------------------------
	// Boot
	// -------------------------------------------------------------------------

	document.addEventListener( 'DOMContentLoaded', function () {
		document.querySelectorAll( '.hcqb-repeater' ).forEach( initRepeater );
	} );

}() );
