/**
 * hcqb-product-switcher.js
 *
 * Handles product size tile selection in standalone mode.
 * When a .hcqb-size-tile is clicked:
 *   1. Marks the tile as active.
 *   2. Fetches the selected product's questions HTML + config data via AJAX.
 *   3. Replaces the .hcqb-questions-list element with fresh HTML.
 *   4. Updates window.HCQBConfig and HCQBState with the new product's data.
 *   5. Updates the builder's data-product-id attribute (used by form submission).
 *   6. Dispatches hcqb:selection-changed so all sub-modules recalculate.
 *
 * No-ops when HCQBConfig.standalone is falsy (product-specific mode).
 *
 * Exposes: window.HCQBProductSwitcher
 * Depends on: window.HCQBConfig, window.HCQBState, window.HCQBLocale
 * Init: called from hcqb-quote-builder.js boot
 */

( function () {
	'use strict';

	var HCQBProductSwitcher = {

		init: function () {
			if ( ! HCQBConfig.standalone ) { return; }
			// Delegated listener on document so it works after DOM swaps.
			document.addEventListener( 'click', this.onTileClick.bind( this ) );
		},

		onTileClick: function ( e ) {
			var tile = e.target.closest( '.hcqb-size-tile' );
			if ( ! tile ) { return; }

			var productId = parseInt( tile.dataset.productId, 10 );
			if ( ! productId || productId < 1 ) { return; }

			// Already active — nothing to do.
			if ( tile.classList.contains( 'hcqb-size-tile--active' ) ) { return; }

			this.markTileActive( tile );
			this.loadQuestions( productId );
		},

		markTileActive: function ( activeTile ) {
			document.querySelectorAll( '.hcqb-size-tile' ).forEach( function ( t ) {
				t.classList.remove( 'hcqb-size-tile--active' );
				t.setAttribute( 'aria-pressed', 'false' );
			} );
			activeTile.classList.add( 'hcqb-size-tile--active' );
			activeTile.setAttribute( 'aria-pressed', 'true' );
		},

		loadQuestions: function ( productId ) {
			var listEl    = document.getElementById( 'hcqb-questions-list' );
			var builderEl = document.getElementById( 'hcqb-builder' );

			// Show loading state.
			if ( listEl ) {
				listEl.classList.add( 'hcqb-questions-list--loading' );
			}

			var formData = new FormData();
			formData.append( 'action',     'hcqb_get_product_questions' );
			formData.append( 'nonce',      HCQBLocale.productQuestionsNonce );
			formData.append( 'product_id', productId );

			fetch( HCQBLocale.ajaxUrl, { method: 'POST', body: formData } )
				.then( function ( res ) { return res.json(); } )
				.then( function ( data ) {
					if ( ! data.success ) {
						if ( listEl ) { listEl.classList.remove( 'hcqb-questions-list--loading' ); }
						return;
					}

					// Swap questions list HTML (outerHTML so the ID is preserved).
					if ( listEl ) {
						listEl.outerHTML = data.data.questions_html;
					}

					var cfg = data.data.config;

					// Update the global config so all sub-modules read fresh data.
					HCQBConfig.productId       = cfg.productId;
					HCQBConfig.productName     = cfg.productName;
					HCQBConfig.basePrice       = cfg.basePrice;
					HCQBConfig.questions       = cfg.questions;
					HCQBConfig.imageRules      = cfg.imageRules;
					HCQBConfig.defaultImageUrl = cfg.defaultImageUrl;

					// Update builder element attribute — used by the form submit handler.
					if ( builderEl ) {
						builderEl.dataset.productId = cfg.productId;
					}

					// Reset mutable state for the new product.
					HCQBState.activeTags   = [];
					HCQBState.currentPrice = cfg.basePrice;
					HCQBState.currentView  = 'front';

					// Reset preview image to the new product's default.
					var imgEl = document.getElementById( 'hcqb-preview-img' );
					if ( imgEl && imgEl.tagName === 'IMG' ) {
						imgEl.src = cfg.defaultImageUrl || '';
					}

					// Trigger full recalculation across all listening sub-modules
					// (conditionals, pricing, image-switcher, feature-pills, highlights).
					document.dispatchEvent( new CustomEvent( 'hcqb:selection-changed', {
						bubbles: true,
						detail:  { source: 'product-switch' },
					} ) );
				} )
				.catch( function () {
					var el = document.getElementById( 'hcqb-questions-list' );
					if ( el ) { el.classList.remove( 'hcqb-questions-list--loading' ); }
				} );
		},
	};

	window.HCQBProductSwitcher = HCQBProductSwitcher;
}() );
