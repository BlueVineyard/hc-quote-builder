/**
 * hcqb-admin-container.js
 *
 * Handles the hc-containers edit screen meta box:
 *   - Tab switching (Product Info ↔ Lease Info)
 *   - Product Images gallery (wp.media multi-select, drag reorder, remove)
 *   - Product Features repeater (add / remove / drag reorder / icon picker)
 *   - Plan Document picker (wp.media single file, remove)
 *   - Lease Optional Extras repeater (add / remove / drag reorder)
 *
 * Depends on: wp.media (loaded via wp_enqueue_media())
 * No jQuery — vanilla ES6+.
 */

( function () {
	'use strict';

	// =========================================================================
	// Tab switching
	// =========================================================================

	function initTabs() {
		const tabButtons = document.querySelectorAll( '.hcqb-metabox-tab-btn' );
		if ( ! tabButtons.length ) return;

		tabButtons.forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				const targetId = this.dataset.target;

				// Update button states.
				tabButtons.forEach( function ( b ) {
					b.classList.remove( 'hcqb-metabox-tab-btn--active' );
				} );
				this.classList.add( 'hcqb-metabox-tab-btn--active' );

				// Show target panel, hide others.
				document.querySelectorAll( '.hcqb-metabox-panel' ).forEach( function ( panel ) {
					panel.classList.remove( 'hcqb-metabox-panel--active' );
				} );
				const target = document.getElementById( targetId );
				if ( target ) {
					target.classList.add( 'hcqb-metabox-panel--active' );
				}
			} );
		} );
	}

	// =========================================================================
	// Shared drag-and-drop reorder
	// Operates on a list element; rows must have a draggable handle inside them.
	// =========================================================================

	function initDragRow( row ) {
		const handle = row.querySelector( '.hcqb-repeater__handle' );
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

			const dragging = document.querySelector( '.hcqb-row--dragging' );
			if ( ! dragging || dragging === row ) return;
			if ( ! dragging.parentElement || dragging.parentElement !== row.parentElement ) return;

			const rows    = Array.from( row.parentElement.children );
			const dragIdx = rows.indexOf( dragging );
			const overIdx = rows.indexOf( row );

			if ( dragIdx < overIdx ) {
				row.parentElement.insertBefore( dragging, row.nextSibling );
			} else {
				row.parentElement.insertBefore( dragging, row );
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

	// =========================================================================
	// Product Images gallery
	// =========================================================================

	function initGallery() {
		const addBtn  = document.getElementById( 'hcqb-add-images' );
		const list    = document.getElementById( 'hcqb-gallery-list' );
		if ( ! addBtn || ! list ) return;

		// Attach drag to pre-existing gallery items.
		list.querySelectorAll( '.hcqb-gallery__item' ).forEach( initGalleryItemDrag );

		// Attach remove listeners to pre-existing gallery items.
		list.querySelectorAll( '.hcqb-gallery__remove' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				this.closest( '.hcqb-gallery__item' ).remove();
			} );
		} );

		// Open wp.media for multi-image selection.
		addBtn.addEventListener( 'click', function () {
			const frame = wp.media( {
				title:   'Select Product Images',
				button:  { text: 'Add to Gallery' },
				multiple: true,
				library: { type: 'image' },
			} );

			frame.on( 'select', function () {
				frame.state().get( 'selection' ).each( function ( attachment ) {
					const data      = attachment.toJSON();
					const thumbUrl  = ( data.sizes && data.sizes.thumbnail )
						? data.sizes.thumbnail.url
						: data.url;

					const item = buildGalleryItem( data.id, thumbUrl );
					list.appendChild( item );
					initGalleryItemDrag( item );
					item.querySelector( '.hcqb-gallery__remove' ).addEventListener( 'click', function () {
						item.remove();
					} );
				} );
			} );

			frame.open();
		} );
	}

	function buildGalleryItem( id, thumbUrl ) {
		const item = document.createElement( 'div' );
		item.className = 'hcqb-gallery__item';
		item.setAttribute( 'draggable', 'true' );
		item.dataset.id = id;
		item.innerHTML =
			'<img src="' + thumbUrl + '" alt="">' +
			'<input type="hidden" name="hcqb_product_images[]" value="' + id + '">' +
			'<button type="button" class="hcqb-gallery__remove" title="Remove image">&times;</button>';
		return item;
	}

	// Gallery items use a simpler drag approach — dragging the item itself.
	function initGalleryItemDrag( item ) {
		item.setAttribute( 'draggable', 'true' );

		item.addEventListener( 'dragstart', function ( e ) {
			item.classList.add( 'hcqb-gallery__item--dragging' );
			e.dataTransfer.effectAllowed = 'move';
			e.dataTransfer.setData( 'text/plain', '' );
		} );

		item.addEventListener( 'dragend', function () {
			item.classList.remove( 'hcqb-gallery__item--dragging' );
		} );

		item.addEventListener( 'dragover', function ( e ) {
			e.preventDefault();
			e.dataTransfer.dropEffect = 'move';

			const dragging = document.querySelector( '.hcqb-gallery__item--dragging' );
			if ( ! dragging || dragging === item ) return;
			if ( dragging.parentElement !== item.parentElement ) return;

			const items   = Array.from( item.parentElement.children );
			const dragIdx = items.indexOf( dragging );
			const overIdx = items.indexOf( item );

			if ( dragIdx < overIdx ) {
				item.parentElement.insertBefore( dragging, item.nextSibling );
			} else {
				item.parentElement.insertBefore( dragging, item );
			}
		} );
	}

	// =========================================================================
	// Features repeater
	// =========================================================================

	function initFeatures() {
		const addBtn = document.getElementById( 'hcqb-add-feature' );
		const list   = document.getElementById( 'hcqb-features-list' );
		if ( ! addBtn || ! list ) return;

		// Init existing rows.
		list.querySelectorAll( '.hcqb-feature-row' ).forEach( initFeatureRow );

		addBtn.addEventListener( 'click', function () {
			const row = buildFeatureRow( 0, '', '' );
			list.appendChild( row );
			initFeatureRow( row );
		} );
	}

	function initFeatureRow( row ) {
		// Remove button.
		const removeBtn = row.querySelector( '.hcqb-repeater__remove' );
		if ( removeBtn ) {
			removeBtn.addEventListener( 'click', function () {
				row.remove();
			} );
		}

		// Icon picker button.
		const iconBtn = row.querySelector( '.hcqb-choose-icon' );
		if ( iconBtn ) {
			iconBtn.addEventListener( 'click', function () {
				openIconPicker( row );
			} );
		}

		// Drag handle.
		initDragRow( row );
	}

	function buildFeatureRow( iconId, iconUrl, label ) {
		const row = document.createElement( 'div' );
		row.className = 'hcqb-repeater__row hcqb-feature-row';

		const iconMarkup = iconUrl
			? '<img class="hcqb-icon-preview" src="' + iconUrl + '" alt="" width="36" height="36">'
			: '<span class="hcqb-icon-preview hcqb-icon-preview--empty"></span>';

		row.innerHTML =
			'<span class="hcqb-repeater__handle dashicons dashicons-menu" title="Drag to reorder"></span>' +
			'<div class="hcqb-icon-picker">' +
				'<input type="hidden" name="hcqb_features[][icon_id]" value="' + ( iconId || 0 ) + '">' +
				iconMarkup +
				'<button type="button" class="button hcqb-choose-icon">Choose Icon</button>' +
			'</div>' +
			'<input type="text" name="hcqb_features[][label]" value="' + escAttr( label ) + '" class="regular-text" placeholder="Feature label">' +
			'<button type="button" class="button hcqb-repeater__remove">Remove</button>';

		return row;
	}

	function openIconPicker( row ) {
		const frame = wp.media( {
			title:    'Choose Feature Icon',
			button:   { text: 'Use This Image' },
			multiple:  false,
			library:  { type: 'image' },
		} );

		frame.on( 'select', function () {
			const attachment = frame.state().get( 'selection' ).first().toJSON();
			const thumbUrl   = ( attachment.sizes && attachment.sizes.thumbnail )
				? attachment.sizes.thumbnail.url
				: attachment.url;

			// Update hidden input.
			const hiddenInput = row.querySelector( 'input[name="hcqb_features[][icon_id]"]' );
			if ( hiddenInput ) hiddenInput.value = attachment.id;

			// Update or create preview image.
			const iconPicker = row.querySelector( '.hcqb-icon-picker' );
			let preview = iconPicker.querySelector( '.hcqb-icon-preview' );

			if ( preview && preview.tagName === 'SPAN' ) {
				// Replace placeholder span with img.
				const img = document.createElement( 'img' );
				img.className = 'hcqb-icon-preview';
				img.width  = 36;
				img.height = 36;
				img.alt    = '';
				iconPicker.replaceChild( img, preview );
				preview = img;
			}
			if ( preview ) preview.src = thumbUrl;
		} );

		frame.open();
	}

	// =========================================================================
	// Plan document picker
	// =========================================================================

	function initPlanDocument() {
		const chooseBtn = document.getElementById( 'hcqb-choose-plan-doc' );
		const hiddenInput = document.getElementById( 'hcqb_plan_document' );
		const nameDisplay = document.getElementById( 'hcqb-plan-doc-name' );
		if ( ! chooseBtn || ! hiddenInput || ! nameDisplay ) return;

		chooseBtn.addEventListener( 'click', function () {
			const frame = wp.media( {
				title:    'Choose Plan Document',
				button:   { text: 'Use This File' },
				multiple:  false,
			} );

			frame.on( 'select', function () {
				const attachment = frame.state().get( 'selection' ).first().toJSON();
				hiddenInput.value = attachment.id;

				// Show filename.
				const filename = attachment.filename || attachment.url.split( '/' ).pop();
				nameDisplay.textContent = filename;

				// Show remove button if not already present.
				let removeBtn = document.getElementById( 'hcqb-remove-plan-doc' );
				if ( ! removeBtn ) {
					removeBtn = document.createElement( 'button' );
					removeBtn.type      = 'button';
					removeBtn.className = 'button hcqb-remove-file';
					removeBtn.id        = 'hcqb-remove-plan-doc';
					removeBtn.textContent = 'Remove';
					chooseBtn.parentElement.appendChild( removeBtn );
					initPlanDocRemove( removeBtn, hiddenInput, nameDisplay );
				}
			} );

			frame.open();
		} );

		// Handle pre-existing remove button.
		const existingRemove = document.getElementById( 'hcqb-remove-plan-doc' );
		if ( existingRemove ) {
			initPlanDocRemove( existingRemove, hiddenInput, nameDisplay );
		}
	}

	function initPlanDocRemove( removeBtn, hiddenInput, nameDisplay ) {
		removeBtn.addEventListener( 'click', function () {
			hiddenInput.value  = 0;
			nameDisplay.innerHTML = '<em>No file selected</em>';
			removeBtn.remove();
		} );
	}

	// =========================================================================
	// Lease Optional Extras repeater
	// =========================================================================

	function initExtras() {
		const addBtn = document.getElementById( 'hcqb-add-extra' );
		const list   = document.getElementById( 'hcqb-extras-list' );
		if ( ! addBtn || ! list ) return;

		// Init existing rows.
		list.querySelectorAll( '.hcqb-extra-row' ).forEach( initExtraRow );

		addBtn.addEventListener( 'click', function () {
			const row = buildExtraRow( '', '' );
			list.appendChild( row );
			initExtraRow( row );
		} );
	}

	function initExtraRow( row ) {
		const removeBtn = row.querySelector( '.hcqb-repeater__remove' );
		if ( removeBtn ) {
			removeBtn.addEventListener( 'click', function () {
				row.remove();
			} );
		}
		initDragRow( row );
	}

	function buildExtraRow( label, price ) {
		const row = document.createElement( 'div' );
		row.className = 'hcqb-repeater__row hcqb-extra-row';
		row.innerHTML =
			'<span class="hcqb-repeater__handle dashicons dashicons-menu" title="Drag to reorder"></span>' +
			'<input type="text" name="hcqb_lease_extras[][label]" value="' + escAttr( label ) + '" class="regular-text" placeholder="Extra label">' +
			'<input type="number" name="hcqb_lease_extras[][weekly_price]" value="' + escAttr( price ) + '" class="small-text" step="0.01" min="0" placeholder="0.00">' +
			'<span class="hcqb-extras-unit">/ week</span>' +
			'<button type="button" class="button hcqb-repeater__remove">Remove</button>';
		return row;
	}

	// =========================================================================
	// Utility — escape attribute value for inline HTML
	// =========================================================================

	function escAttr( str ) {
		return String( str )
			.replace( /&/g, '&amp;' )
			.replace( /"/g, '&quot;' )
			.replace( /'/g, '&#039;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' );
	}

	// =========================================================================
	// Boot
	// =========================================================================

	document.addEventListener( 'DOMContentLoaded', function () {
		initTabs();
		initGallery();
		initFeatures();
		initPlanDocument();
		initExtras();
	} );

}() );
