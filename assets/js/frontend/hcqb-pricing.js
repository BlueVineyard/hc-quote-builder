/**
 * hcqb-pricing.js
 *
 * Recalculates the total estimated price on every hcqb:selection-changed
 * event and updates every .hcqb-live-price element in the DOM.
 *
 * Inputs inside a hidden conditional question (aria-hidden="true") are
 * automatically excluded via the CSS selector filter.
 *
 * Exposes: window.HCQBPricing
 * Depends on: window.HCQBConfig (PHP-injected), window.HCQBState
 */

( function () {
	'use strict';

	var HCQBPricing = {

		init: function () {
			document.addEventListener( 'hcqb:selection-changed', this.recalculate.bind( this ) );
		},

		recalculate: function () {
			// Pass 1: look for a selected option with data-option-role="base_price".
			// If found, its price replaces HCQBConfig.basePrice as the starting total.
			var total = HCQBConfig.basePrice;

			document.querySelectorAll(
				'.hcqb-question:not([aria-hidden="true"]) input[data-option-role="base_price"]:checked'
			).forEach( function ( input ) {
				total = parseFloat( input.dataset.price ) || 0;
			} );

			document.querySelectorAll(
				'.hcqb-question:not([aria-hidden="true"]) select'
			).forEach( function ( select ) {
				var opt = select.options[ select.selectedIndex ];
				if ( opt && opt.dataset.optionRole === 'base_price' && opt.value ) {
					total = parseFloat( opt.dataset.price ) || 0;
				}
			} );

			// Pass 2: sum all other checked/selected options (skip base_price role).
			document.querySelectorAll(
				'.hcqb-question:not([aria-hidden="true"]) input:checked'
			).forEach( function ( input ) {
				if ( input.dataset.optionRole === 'base_price' ) { return; }
				var price     = parseFloat( input.dataset.price ) || 0;
				var priceType = input.dataset.priceType;
				if ( priceType === 'addition'  ) { total += price; }
				if ( priceType === 'deduction' ) { total -= price; }
			} );

			// Select / dropdown inputs — read from the selected <option>.
			document.querySelectorAll(
				'.hcqb-question:not([aria-hidden="true"]) select'
			).forEach( function ( select ) {
				var opt = select.options[ select.selectedIndex ];
				if ( ! opt || ! opt.value ) { return; }
				if ( opt.dataset.optionRole === 'base_price' ) { return; }
				var price     = parseFloat( opt.dataset.price ) || 0;
				var priceType = opt.dataset.priceType;
				if ( priceType === 'addition'  ) { total += price; }
				if ( priceType === 'deduction' ) { total -= price; }
			} );

			HCQBState.currentPrice = Math.round( total * 100 ) / 100;

			var formatted = '$' + HCQBState.currentPrice.toLocaleString( 'en-AU', {
				minimumFractionDigits: 2,
				maximumFractionDigits: 2,
			} );

			document.querySelectorAll( '.hcqb-live-price' ).forEach( function ( el ) {
				el.textContent = formatted;
			} );
		},
	};

	window.HCQBPricing = HCQBPricing;
}() );
