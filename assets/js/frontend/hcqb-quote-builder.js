/**
 * hcqb-quote-builder.js
 *
 * Orchestrator for the HC Quote Builder (Frame 1 + Frame 2 navigation).
 *
 * Responsibilities:
 *   - Initialise window.HCQBState (shared mutable state)
 *   - Define window.HCQBDispatch (event bus dispatch function)
 *   - Wire all input changes to dispatch hcqb:selection-changed
 *   - Manage the view-toggle buttons (dispatches hcqb:view-changed)
 *   - Manage the "Change Product" confirmation + redirect flow
 *   - Manage Frame 1 ↔ Frame 2 navigation (Continue / Back buttons)
 *   - Serialize Frame 1 selections to hidden JSON input before moving to Frame 2
 *   - Boot all sub-modules in dependency order on DOMContentLoaded
 *   - Fire an initial hcqb:selection-changed so all modules compute
 *     their starting state from default-selected inputs
 *
 * Load order: pricing.js, image-switcher.js, conditionals.js,
 *             feature-pills.js, google-maps.js, form-submit.js must all
 *             be loaded before this file.
 *
 * Depends on: window.HCQBConfig (PHP-injected inline script)
 */

( function () {
	'use strict';

	// -------------------------------------------------------------------------
	// Shared mutable state
	// -------------------------------------------------------------------------

	window.HCQBState = {
		activeTags:   [],
		currentPrice: HCQBConfig.basePrice,
		currentView:  'front',
	};

	// -------------------------------------------------------------------------
	// Event bus — all input changes go through this single function.
	// Sub-modules listen via document.addEventListener('hcqb:selection-changed').
	// -------------------------------------------------------------------------

	function dispatchSelectionChanged( detail ) {
		document.dispatchEvent( new CustomEvent( 'hcqb:selection-changed', {
			detail: detail || {},
		} ) );
	}

	window.HCQBDispatch = dispatchSelectionChanged;

	// -------------------------------------------------------------------------
	// Input change listener — single delegated handler on the builder root
	// -------------------------------------------------------------------------

	function attachInputListeners() {
		var builder = document.getElementById( 'hcqb-builder' );
		if ( ! builder ) { return; }

		builder.addEventListener( 'change', function ( e ) {
			var t = e.target;
			var isInput  = t.tagName === 'INPUT'  && ( t.type === 'radio' || t.type === 'checkbox' );
			var isSelect = t.tagName === 'SELECT';

			if ( isInput || isSelect ) {
				dispatchSelectionChanged( { source: t } );
			}
		} );
	}

	// -------------------------------------------------------------------------
	// View-toggle buttons
	// -------------------------------------------------------------------------

	function initViewToggle() {
		document.querySelectorAll( '.hcqb-view-btn' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				document.querySelectorAll( '.hcqb-view-btn' ).forEach( function ( b ) {
					b.classList.remove( 'hcqb-view-btn--active' );
					b.setAttribute( 'aria-pressed', 'false' );
				} );
				this.classList.add( 'hcqb-view-btn--active' );
				this.setAttribute( 'aria-pressed', 'true' );

				HCQBState.currentView = this.dataset.view || 'front';
				document.dispatchEvent( new CustomEvent( 'hcqb:view-changed' ) );
			} );
		} );
	}

	// -------------------------------------------------------------------------
	// Change product flow — confirmation → dropdown → URL update → reload
	// -------------------------------------------------------------------------

	function initProductChange() {
		var changeBtn    = document.querySelector( '.hcqb-change-product' );
		var dropdownWrap = document.getElementById( 'hcqb-product-dropdown' );
		var select       = document.getElementById( 'hcqb-product-select' );

		if ( ! changeBtn || ! dropdownWrap || ! select ) { return; }

		changeBtn.addEventListener( 'click', function () {
			var confirmed = window.confirm(
				'Changing the product will reset all your current selections. Continue?'
			);
			if ( ! confirmed ) { return; }

			changeBtn.hidden      = true;
			dropdownWrap.hidden   = false;
			select.focus();
		} );

		select.addEventListener( 'change', function () {
			var newId = parseInt( this.value, 10 );
			if ( ! newId ) { return; }

			var url = new URL( window.location.href );
			url.searchParams.set( 'product', newId );
			window.location.href = url.toString();
		} );
	}

	// -------------------------------------------------------------------------
	// Frame navigation — Continue (Frame 1 → 2) and Back (Frame 2 → 1)
	//
	// Behaviour is controlled by HCQBConfig.formLayout:
	//   'multistep'   — Frame 2 hidden until Continue is clicked (default)
	//   'always_show' — Frame 2 visible on load; selections kept live
	// -------------------------------------------------------------------------

	function initFrameNav() {
		var frame1          = document.getElementById( 'hcqb-frame-1' );
		var frame2          = document.getElementById( 'hcqb-frame-2' );
		var nextBtn         = document.getElementById( 'hcqb-next-step' );
		var backBtn         = document.getElementById( 'hcqb-back-step' );
		var selectionsInput = document.getElementById( 'hcqb-selections' );

		if ( ! frame1 || ! frame2 ) { return; }

		// --- Always-show mode ---
		if ( HCQBConfig.formLayout === 'always_show' ) {
			// Frame 2 is already visible (no hidden attribute from PHP).
			// Keep #hcqb-selections up to date on every selection change so
			// it is current at submit time without a Continue-click step.
			document.addEventListener( 'hcqb:selection-changed', function () {
				if ( selectionsInput ) {
					selectionsInput.value = JSON.stringify( collectSelections() );
				}
			} );

			// Notify the Maps module that Frame 2 is already visible.
			document.dispatchEvent( new CustomEvent( 'hcqb:frame-2-shown' ) );
			return;
		}

		// --- Multi-step mode (default) ---
		if ( ! nextBtn ) { return; }

		// Enable the Continue button after the first user-initiated selection.
		// The initial { initial: true } event (fired on load) is ignored.
		document.addEventListener( 'hcqb:selection-changed', function ( e ) {
			if ( e.detail && e.detail.initial ) { return; }
			nextBtn.disabled = false;
		} );

		// Continue — serialize selections, swap frames, notify Maps module.
		nextBtn.addEventListener( 'click', function () {
			if ( selectionsInput ) {
				selectionsInput.value = JSON.stringify( collectSelections() );
			}

			frame1.hidden = true;
			frame2.hidden = false;

			document.dispatchEvent( new CustomEvent( 'hcqb:frame-2-shown' ) );

			// Scroll the builder into view so the form is visible immediately.
			var builder = document.getElementById( 'hcqb-builder' );
			if ( builder ) {
				builder.scrollIntoView( { behavior: 'smooth', block: 'start' } );
			}
		} );

		// Back — return to Frame 1.
		if ( backBtn ) {
			backBtn.addEventListener( 'click', function () {
				frame2.hidden = true;
				frame1.hidden = false;

				var builder = document.getElementById( 'hcqb-builder' );
				if ( builder ) {
					builder.scrollIntoView( { behavior: 'smooth', block: 'start' } );
				}
			} );
		}
	}

	// -------------------------------------------------------------------------
	// Collect all visible Frame 1 selections into a plain object.
	// Called once when the user clicks Continue, before Frame 2 is shown.
	// -------------------------------------------------------------------------

	function collectSelections() {
		var builder = document.getElementById( 'hcqb-builder' );
		if ( ! builder ) { return {}; }

		var result = {};

		// Radio and checkbox inputs in visible (non-hidden) questions.
		builder.querySelectorAll(
			'.hcqb-question:not([aria-hidden="true"]) input:checked'
		).forEach( function ( inp ) {
			var key     = inp.name.replace( /^hcqb_q_/, '' );
			var labelEl = inp.closest( 'label' );
			var label   = '';

			if ( labelEl ) {
				var clone     = labelEl.cloneNode( true );
				var priceSpan = clone.querySelector( '.hcqb-option-price' );
				if ( priceSpan ) { priceSpan.remove(); }
				label = clone.textContent.trim();
			}

			var entry = {
				slug:      inp.value,
				label:     label,
				price:     parseFloat( inp.dataset.price || 0 ),
				priceType: inp.dataset.priceType || 'addition',
			};

			if ( inp.type === 'checkbox' ) {
				if ( ! result[ key ] ) { result[ key ] = []; }
				result[ key ].push( entry );
			} else {
				result[ key ] = entry;
			}
		} );

		// Select / dropdown inputs.
		builder.querySelectorAll(
			'.hcqb-question:not([aria-hidden="true"]) select'
		).forEach( function ( sel ) {
			if ( ! sel.value ) { return; }
			var key = sel.name.replace( /^hcqb_q_/, '' );
			var opt = sel.options[ sel.selectedIndex ];
			var label = opt
				? opt.textContent.trim().replace( /\s*\([^)]*\)\s*$/, '' )
				: '';

			result[ key ] = {
				slug:      sel.value,
				label:     label,
				price:     parseFloat( ( opt && opt.dataset.price ) || 0 ),
				priceType: ( opt && opt.dataset.priceType ) || 'addition',
			};
		} );

		return result;
	}

	// -------------------------------------------------------------------------
	// Option highlight sync — keeps .hcqb-option--selected in step with the
	// actual checked state after every selection change (including conditional
	// resets that call inp.checked = false without firing a native change event).
	// Belt-and-suspenders alongside the CSS :has(input:checked) rule.
	// -------------------------------------------------------------------------

	function syncOptionHighlights() {
		document.querySelectorAll( '.hcqb-radio-option input, .hcqb-checkbox-option input' ).forEach( function ( inp ) {
			var label = inp.closest( '.hcqb-radio-option, .hcqb-checkbox-option' );
			if ( label ) {
				label.classList.toggle( 'hcqb-option--selected', inp.checked );
			}
		} );
	}

	// -------------------------------------------------------------------------
	// Consent toggle — expands/collapses the consent detail text
	// -------------------------------------------------------------------------

	function initConsentToggle() {
		var btn  = document.querySelector( '.hcqb-consent-toggle' );
		var body = document.getElementById( 'hcqb-consent-body' );
		if ( ! btn || ! body ) { return; }

		btn.addEventListener( 'click', function () {
			var isOpen = btn.getAttribute( 'aria-expanded' ) === 'true';
			btn.setAttribute( 'aria-expanded', isOpen ? 'false' : 'true' );
			body.hidden = isOpen;
		} );
	}

	// -------------------------------------------------------------------------
	// Boot
	// -------------------------------------------------------------------------

	document.addEventListener( 'DOMContentLoaded', function () {
		// Initialise sub-modules in dependency order.
		// Conditionals must run first so hidden questions are set before
		// Pricing and ImageSwitcher calculate from them.
		if ( window.HCQBConditionals  ) { HCQBConditionals.init();  }
		if ( window.HCQBPricing       ) { HCQBPricing.init();       }
		if ( window.HCQBImageSwitcher ) { HCQBImageSwitcher.init(); }
		if ( window.HCQBFeaturePills  ) { HCQBFeaturePills.init();  }

		attachInputListeners();
		initViewToggle();
		initProductChange();
		initFrameNav();
		initConsentToggle();

		// Sync option highlight class on every selection change so the visual
		// state is always correct, including after conditional resets.
		document.addEventListener( 'hcqb:selection-changed', syncOptionHighlights );

		// Fire initial event — all modules calculate from the page's starting
		// state (e.g. any radio pre-selected by the browser from history).
		dispatchSelectionChanged( { initial: true } );
	} );

}() );
