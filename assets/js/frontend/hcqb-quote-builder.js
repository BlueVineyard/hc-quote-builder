/**
 * hcqb-quote-builder.js
 *
 * Orchestrator for Frame 1 of the HC Quote Builder.
 *
 * Responsibilities:
 *   - Initialise window.HCQBState (shared mutable state)
 *   - Define window.HCQBDispatch (event bus dispatch function)
 *   - Wire all input changes to dispatch hcqb:selection-changed
 *   - Manage the view-toggle buttons (dispatches hcqb:view-changed)
 *   - Manage the "Change Product" confirmation + redirect flow
 *   - Boot all sub-modules in dependency order on DOMContentLoaded
 *   - Fire an initial hcqb:selection-changed so all modules compute
 *     their starting state from default-selected inputs
 *
 * Load order: pricing.js, image-switcher.js, conditionals.js,
 *             feature-pills.js must all be loaded before this file.
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

		// Fire initial event — all modules calculate from the page's starting
		// state (e.g. any radio pre-selected by the browser from history).
		dispatchSelectionChanged( { initial: true } );
	} );

}() );
