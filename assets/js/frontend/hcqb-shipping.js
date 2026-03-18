/**
 * hcqb-shipping.js
 *
 * Calculates the estimated shipping cost using combination-based Shipping Rules.
 *
 * Rules are defined in admin (like Image Rules): each rule has match_tags[],
 * shipping_rate ($/km), and shipping_min ($). The most specific matching rule
 * wins (most match_tags). Formula: max( rate * distance_km, min ).
 *
 * Depends on: window.HCQBConfig.shippingRules, window.HCQBState,
 *             hcqb:selection-changed, hcqb:distance-changed
 *
 * @package HC_Quote_Builder
 */

( function () {
	'use strict';

	var activeRate = 0;
	var activeMin  = 0;
	var distanceKm = 0;

	var HCQBShipping = {

		init: function () {
			document.addEventListener( 'hcqb:selection-changed', this.onSelectionChanged.bind( this ) );
			document.addEventListener( 'hcqb:distance-changed',  this.onDistanceChanged.bind( this ) );
		},

		/**
		 * Collect all selected option slugs, then find the best matching rule.
		 */
		onSelectionChanged: function () {
			var activeSlugs = this.collectActiveSlugs();
			var rule        = this.findMatchingRule( activeSlugs );

			activeRate = rule ? ( rule.shipping_rate || 0 ) : 0;
			activeMin  = rule ? ( rule.shipping_min  || 0 ) : 0;
			this.recalculate();
		},

		/**
		 * Collect value (slug) from every checked/selected input in visible questions.
		 */
		collectActiveSlugs: function () {
			var slugs = [];

			// Radio + checkbox inputs.
			document.querySelectorAll(
				'.hcqb-question:not([aria-hidden="true"]) input:checked'
			).forEach( function ( inp ) {
				if ( inp.value ) { slugs.push( inp.value ); }
			} );

			// Dropdown selects.
			document.querySelectorAll(
				'.hcqb-question:not([aria-hidden="true"]) select'
			).forEach( function ( sel ) {
				var opt = sel.options[ sel.selectedIndex ];
				if ( opt && opt.value ) { slugs.push( opt.value ); }
			} );

			return slugs;
		},

		/**
		 * Find the first shipping rule where ALL match_tags are in the active slugs.
		 * Rules are pre-sorted most-specific-first (most tags) by PHP.
		 */
		findMatchingRule: function ( activeSlugs ) {
			var rules = ( window.HCQBConfig && HCQBConfig.shippingRules ) || [];

			for ( var i = 0; i < rules.length; i++ ) {
				var tags = rules[ i ].match_tags || [];
				if ( tags.length === 0 ) { continue; }

				var allMatch = true;
				for ( var t = 0; t < tags.length; t++ ) {
					if ( activeSlugs.indexOf( tags[ t ] ) === -1 ) {
						allMatch = false;
						break;
					}
				}
				if ( allMatch ) { return rules[ i ]; }
			}

			return null;
		},

		onDistanceChanged: function ( e ) {
			distanceKm = ( e.detail && e.detail.distanceKm ) || 0;
			this.recalculate();
		},

		formatPrice: function ( val ) {
			return '$' + val.toLocaleString( 'en-AU', {
				minimumFractionDigits: 2,
				maximumFractionDigits: 2,
			} );
		},

		recalculate: function () {
			var container   = document.getElementById( 'hcqb-shipping-estimate' );
			var hiddenInput = document.getElementById( 'hcqb-estimated-shipping-cost' );

			if ( activeRate <= 0 || distanceKm <= 0 ) {
				if ( window.HCQBState ) { HCQBState.estimatedShipping = 0; }
				if ( container )   { container.hidden = true; }
				if ( hiddenInput ) { hiddenInput.value = '0'; }
				return;
			}

			var raw  = Math.round( activeRate * distanceKm * 100 ) / 100;
			var cost = Math.max( raw, activeMin );
			cost = Math.round( cost * 100 ) / 100;

			if ( window.HCQBState ) { HCQBState.estimatedShipping = cost; }

			var distStr = Math.round( distanceKm ) + ' km x ' + this.formatPrice( activeRate ) + '/km';
			var breakdown;
			if ( activeMin > 0 && raw < activeMin ) {
				breakdown = distStr + ' = ' + this.formatPrice( raw ) + ' (min ' + this.formatPrice( activeMin ) + ' applies)';
			} else {
				breakdown = distStr;
			}

			if ( container ) {
				container.hidden = false;
				var priceEl     = container.querySelector( '.hcqb-shipping-estimate__price' );
				var breakdownEl = container.querySelector( '.hcqb-shipping-estimate__breakdown' );
				if ( priceEl )     { priceEl.textContent = this.formatPrice( cost ); }
				if ( breakdownEl ) { breakdownEl.textContent = breakdown; }
			}

			if ( hiddenInput ) { hiddenInput.value = cost; }
		},
	};

	window.HCQBShipping = HCQBShipping;
}() );
