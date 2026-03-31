/**
 * hcqb-gallery.js
 *
 * Product gallery — main image + Swiper thumbnail slider + lightbox with zoom.
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

	// =========================================================================
	// Lightbox
	// =========================================================================

	var lightbox     = null;
	var lbImg        = null;
	var lbClose      = null;
	var lbPrev       = null;
	var lbNext       = null;
	var lbImages     = [];  // Array of { src, alt } for the active gallery.
	var lbIndex      = 0;
	var lbZoomed     = false;
	var lbDragging   = false;
	var lbDragStart  = { x: 0, y: 0 };
	var lbTranslate  = { x: 0, y: 0 };
	var lbStartTrans = { x: 0, y: 0 };

	function buildLightbox() {
		if ( lightbox ) return;

		lightbox = document.createElement( 'div' );
		lightbox.className = 'hcqb-lightbox';
		lightbox.setAttribute( 'aria-hidden', 'true' );
		lightbox.innerHTML =
			'<div class="hcqb-lightbox__backdrop"></div>' +
			'<button type="button" class="hcqb-lightbox__close" aria-label="Close">&times;</button>' +
			'<button type="button" class="hcqb-lightbox__arrow hcqb-lightbox__arrow--prev" aria-label="Previous">&#8249;</button>' +
			'<button type="button" class="hcqb-lightbox__arrow hcqb-lightbox__arrow--next" aria-label="Next">&#8250;</button>' +
			'<div class="hcqb-lightbox__img-wrap">' +
				'<img class="hcqb-lightbox__img" src="" alt="" draggable="false">' +
			'</div>';

		document.body.appendChild( lightbox );

		lbImg   = lightbox.querySelector( '.hcqb-lightbox__img' );
		lbClose = lightbox.querySelector( '.hcqb-lightbox__close' );
		lbPrev  = lightbox.querySelector( '.hcqb-lightbox__arrow--prev' );
		lbNext  = lightbox.querySelector( '.hcqb-lightbox__arrow--next' );

		// Close on backdrop click.
		lightbox.querySelector( '.hcqb-lightbox__backdrop' ).addEventListener( 'click', closeLightbox );
		lbClose.addEventListener( 'click', closeLightbox );

		// Escape key.
		document.addEventListener( 'keydown', function ( e ) {
			if ( lightbox.getAttribute( 'aria-hidden' ) === 'true' ) return;
			if ( e.key === 'Escape' )      closeLightbox();
			if ( e.key === 'ArrowLeft' )    lbNavigate( -1 );
			if ( e.key === 'ArrowRight' )   lbNavigate( 1 );
		} );

		// Arrow navigation.
		lbPrev.addEventListener( 'click', function ( e ) { e.stopPropagation(); lbNavigate( -1 ); } );
		lbNext.addEventListener( 'click', function ( e ) { e.stopPropagation(); lbNavigate( 1 ); } );

		// Click image to toggle zoom.
		lbImg.addEventListener( 'click', function ( e ) {
			if ( lbDragging ) return; // Don't toggle if we just finished dragging.
			toggleZoom( e );
		} );

		// Drag to pan when zoomed.
		var imgWrap = lightbox.querySelector( '.hcqb-lightbox__img-wrap' );
		imgWrap.addEventListener( 'mousedown', startDrag );
		imgWrap.addEventListener( 'touchstart', startDrag, { passive: false } );
		window.addEventListener( 'mousemove', onDrag );
		window.addEventListener( 'touchmove', onDrag, { passive: false } );
		window.addEventListener( 'mouseup', endDrag );
		window.addEventListener( 'touchend', endDrag );
	}

	function openLightbox( images, startIndex ) {
		buildLightbox();
		lbImages = images;
		lbIndex  = startIndex || 0;
		lbZoomed = false;
		resetZoom();
		showLbImage();
		lightbox.setAttribute( 'aria-hidden', 'false' );
		document.body.style.overflow = 'hidden';

		// Show/hide arrows.
		var multi = lbImages.length > 1;
		lbPrev.style.display = multi ? '' : 'none';
		lbNext.style.display = multi ? '' : 'none';
	}

	function closeLightbox() {
		if ( ! lightbox ) return;
		lightbox.setAttribute( 'aria-hidden', 'true' );
		document.body.style.overflow = '';
		lbZoomed = false;
		resetZoom();
	}

	function showLbImage() {
		var item = lbImages[ lbIndex ];
		if ( ! item ) return;
		lbImg.src = item.src;
		lbImg.alt = item.alt;
		resetZoom();
	}

	function lbNavigate( dir ) {
		lbIndex = ( lbIndex + dir + lbImages.length ) % lbImages.length;
		lbZoomed = false;
		showLbImage();
	}

	function toggleZoom( e ) {
		lbZoomed = ! lbZoomed;
		if ( lbZoomed ) {
			lbImg.classList.add( 'hcqb-lightbox__img--zoomed' );
			lbImg.style.cursor = 'grab';
			// Center zoom on click position.
			var rect = lbImg.getBoundingClientRect();
			var cx   = e.clientX - rect.left;
			var cy   = e.clientY - rect.top;
			var pcX  = cx / rect.width;
			var pcY  = cy / rect.height;
			lbImg.style.transformOrigin = ( pcX * 100 ) + '% ' + ( pcY * 100 ) + '%';
		} else {
			resetZoom();
		}
	}

	function resetZoom() {
		lbZoomed = false;
		lbTranslate = { x: 0, y: 0 };
		if ( lbImg ) {
			lbImg.classList.remove( 'hcqb-lightbox__img--zoomed' );
			lbImg.style.cursor = 'zoom-in';
			lbImg.style.transformOrigin = '';
			lbImg.style.transform = '';
		}
	}

	// --- Drag to pan ---

	function startDrag( e ) {
		if ( ! lbZoomed ) return;
		lbDragging = true;
		var point = e.touches ? e.touches[0] : e;
		lbDragStart  = { x: point.clientX, y: point.clientY };
		lbStartTrans = { x: lbTranslate.x, y: lbTranslate.y };
		lbImg.style.cursor = 'grabbing';
		if ( e.cancelable ) e.preventDefault();
	}

	function onDrag( e ) {
		if ( ! lbDragging ) return;
		var point = e.touches ? e.touches[0] : e;
		var dx = point.clientX - lbDragStart.x;
		var dy = point.clientY - lbDragStart.y;
		lbTranslate.x = lbStartTrans.x + dx;
		lbTranslate.y = lbStartTrans.y + dy;
		lbImg.style.transform = 'scale(2) translate(' + lbTranslate.x + 'px, ' + lbTranslate.y + 'px)';
		if ( e.cancelable ) e.preventDefault();
	}

	function endDrag() {
		if ( ! lbDragging ) return;
		lbImg.style.cursor = 'grab';
		// Small threshold to distinguish drag from click.
		var moved = Math.abs( lbTranslate.x - lbStartTrans.x ) + Math.abs( lbTranslate.y - lbStartTrans.y );
		if ( moved < 5 ) {
			lbDragging = false; // Let the click handler fire.
			return;
		}
		// Use setTimeout so the click event that fires right after mouseup is ignored.
		setTimeout( function () { lbDragging = false; }, 0 );
	}

	// =========================================================================
	// Gallery init
	// =========================================================================

	/**
	 * Initialise gallery: Swiper thumbnails + main image click swap + lightbox.
	 */
	function initGalleries() {
		document.querySelectorAll( '.hcqb-product-gallery' ).forEach( function ( gallery ) {
			var mainImg    = gallery.querySelector( '.hcqb-gallery-main__img' );
			var mainWrap   = gallery.querySelector( '.hcqb-gallery-main' );
			var thumbsWrap = gallery.querySelector( '.hcqb-gallery-thumbs' );
			var thumbs     = gallery.querySelectorAll( '.hcqb-gallery-thumb' );

			if ( ! mainImg ) return;

			// Build lightbox image list from thumbnails (or single main image).
			var galleryImages = [];
			if ( thumbs.length > 1 ) {
				thumbs.forEach( function ( btn ) {
					galleryImages.push( {
						src: btn.dataset.zoom || btn.dataset.full,
						alt: btn.querySelector( 'img' ).alt || '',
					} );
				} );
			} else {
				galleryImages.push( {
					src: mainImg.dataset.zoom || mainImg.src,
					alt: mainImg.alt || '',
				} );
			}

			// Store images on the gallery element for reference.
			gallery._lbImages = galleryImages;

			// Init Swiper on the thumbnails.
			if ( thumbs.length >= 2 && thumbsWrap ) {
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
						mainImg.dataset.zoom = this.dataset.zoom || this.dataset.full;
						thumbs.forEach( function ( b ) {
							b.classList.remove( 'hcqb-gallery-thumb--active' );
						} );
						this.classList.add( 'hcqb-gallery-thumb--active' );
						swiper.slideTo( index );
					} );
				} );
			}

			// Click main image → open lightbox.
			if ( mainWrap ) {
				mainWrap.addEventListener( 'click', function () {
					// Find which image is currently shown.
					var currentZoom = mainImg.dataset.zoom || mainImg.src;
					var startIdx    = 0;
					for ( var i = 0; i < galleryImages.length; i++ ) {
						if ( galleryImages[ i ].src === currentZoom ) {
							startIdx = i;
							break;
						}
					}
					openLightbox( galleryImages, startIdx );
				} );

				mainWrap.addEventListener( 'keydown', function ( e ) {
					if ( e.key === 'Enter' || e.key === ' ' ) {
						e.preventDefault();
						mainWrap.click();
					}
				} );
			}
		} );
	}

	// Boot.
	ensureSwiper( initGalleries );
}() );
