/**
 * hcqb-admin-repeater.js
 *
 * Generic drag-and-drop row reorder for HCQB repeater lists.
 * Exposes window.HCQBRepeater so screen-specific scripts can call it
 * without duplicating the drag logic.
 *
 * Usage:
 *   HCQBRepeater.initDrag( rowElement );
 *
 * The row element must contain a child with class .hcqb-repeater__handle.
 * Dragging only works within the same parent list — cross-list moves are blocked.
 *
 * Drag states applied:
 *   .hcqb-row--dragging  — the row being moved (CSS: opacity 0.45)
 *   .hcqb-row--over      — the row currently being dragged over
 */

( function () {
	'use strict';

	window.HCQBRepeater = {

		/**
		 * Attach HTML5 drag-and-drop reorder behaviour to a repeater row.
		 *
		 * @param {HTMLElement} row  The row element to make draggable.
		 */
		initDrag: function ( row ) {
			var handle = row.querySelector( '.hcqb-repeater__handle' );
			if ( ! handle ) return;

			handle.setAttribute( 'draggable', 'true' );

			handle.addEventListener( 'dragstart', function ( e ) {
				row.classList.add( 'hcqb-row--dragging' );
				e.dataTransfer.effectAllowed = 'move';
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

				var dragging = document.querySelector( '.hcqb-row--dragging' );
				if ( ! dragging || dragging === row ) return;
				// Block cross-list drops.
				if ( ! dragging.parentElement || dragging.parentElement !== row.parentElement ) return;

				var rows    = Array.from( row.parentElement.children );
				var dragIdx = rows.indexOf( dragging );
				var overIdx = rows.indexOf( row );

				if ( dragIdx < overIdx ) {
					row.parentElement.insertBefore( dragging, row.nextSibling );
				} else {
					row.parentElement.insertBefore( dragging, row );
				}
			} );

			row.addEventListener( 'dragenter', function () {
				var dragging = document.querySelector( '.hcqb-row--dragging' );
				if ( dragging && dragging !== row ) {
					row.classList.add( 'hcqb-row--over' );
				}
			} );

			row.addEventListener( 'dragleave', function () {
				row.classList.remove( 'hcqb-row--over' );
			} );
		},

	};

}() );
