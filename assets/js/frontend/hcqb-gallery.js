/**
 * hcqb-gallery.js
 *
 * Product gallery — main image + Swiper thumbnail slider.
 * Dynamically loads Swiper from CDN if not already available on the page.
 *
 * @package HC_Quote_Builder
 */

( function () {
	'use strict';

	var SWIPER_VERSION = '11';
	var SWIPER_CDN_JS  = 'https://cdn.jsdelivr.net/npm/swiper@' + SWIPER_VERSION + '/swiper-bundle.min.js';
	var SWIPER_CDN_CSS = 'https://cdn.jsdelivr.net/npm/swiper@' + SWIPER_VERSION + '/swiper-bundle.min.css';

	/**
	 * Ensure Swiper is available, then run callback.
	 */
	function ensureSwiper( callback ) {
		if ( window.Swiper ) {
			callback();
			return;
		}

		// Load CSS if not already present.
		if ( ! document.querySelector( 'link[href*="swiper"]' ) ) {
			var link  = document.createElement( 'link' );
			link.rel  = 'stylesheet';
			link.href = SWIPER_CDN_CSS;
			document.head.appendChild( link );
		}

		// Load JS.
		var script  = document.createElement( 'script' );
		script.src  = SWIPER_CDN_JS;
		script.onload = callback;
		document.head.appendChild( script );
	}

	/**
	 * Initialise gallery: Swiper thumbnails + main image click swap.
	 */
	function initGalleries() {
		document.querySelectorAll( '.hcqb-product-gallery' ).forEach( function ( gallery ) {
			var mainImg    = gallery.querySelector( '.hcqb-gallery-main__img' );
			var thumbsWrap = gallery.querySelector( '.hcqb-gallery-thumbs' );
			var thumbs     = gallery.querySelectorAll( '.hcqb-gallery-thumb' );

			if ( ! mainImg || thumbs.length < 2 || ! thumbsWrap ) {
				return;
			}

			// Init Swiper on the thumbnails.
			var swiper = new Swiper( thumbsWrap, {
				slidesPerView: 'auto',
				spaceBetween: 8,
				watchOverflow: true,
				navigation: {
					prevEl: thumbsWrap.querySelector( '.hcqb-gallery-nav--prev' ),
					nextEl: thumbsWrap.querySelector( '.hcqb-gallery-nav--next' ),
				},
			} );

			// Click handler — swap main image + active state.
			thumbs.forEach( function ( btn, index ) {
				btn.addEventListener( 'click', function () {
					mainImg.src = this.dataset.full;
					thumbs.forEach( function ( b ) {
						b.classList.remove( 'hcqb-gallery-thumb--active' );
					} );
					this.classList.add( 'hcqb-gallery-thumb--active' );
					swiper.slideTo( index );
				} );
			} );
		} );
	}

	// Boot.
	ensureSwiper( initGalleries );
}() );
