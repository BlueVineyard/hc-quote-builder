/**
 * hcqb-conditionals.js
 *
 * Shows and hides conditional questions based on the current selection state.
 *
 * Single-condition questions have:
 *   data-conditional="true"
 *   data-show-when-question="question_key"
 *   data-show-when-option="option_slug"
 *
 * Multi-condition (AND) questions have:
 *   data-conditional="true"
 *   data-show-when-conditions='[{"question":"key","option":"slug"},...]'
 *   All conditions must be satisfied for the question to show.
 *
 * Auto-check: when a conditional question is revealed and it contains exactly
 * one radio input (e.g. assembly upgrade questions), that input is auto-checked
 * so its price is applied without user interaction.
 *
 * When a conditional is hidden, its inputs are reset so they no longer
 * contribute to pricing or image matching. A re-dispatch is fired after
 * resetting so Pricing and ImageSwitcher recalculate immediately.
 *
 * Loop guard: state is read from aria-hidden on every run. After hiding a
 * conditional, aria-hidden is "true", so the next run finds isHidden===true
 * and skips — no infinite loop.
 *
 * Exposes: window.HCQBConditionals
 * Depends on: window.HCQBDispatch
 */

( function () {
	'use strict';

	/**
	 * Returns true if the given question key has the given option slug
	 * currently selected (radio/checkbox checked or select value matches).
	 */
	function isOptionSelected( questionKey, optionSlug ) {
		var checkedInput = document.querySelector(
			'.hcqb-question[data-question-key="' + questionKey + '"] input[value="' + optionSlug + '"]:checked'
		);
		if ( checkedInput ) {
			return true;
		}
		var sel = document.querySelector(
			'.hcqb-question[data-question-key="' + questionKey + '"] select'
		);
		return !! ( sel && sel.value === optionSlug );
	}

	var HCQBConditionals = {

		init: function () {
			document.addEventListener( 'hcqb:selection-changed', this.evaluate.bind( this ) );
		},

		evaluate: function () {
			var changed = false;

			document.querySelectorAll( '[data-conditional="true"]' ).forEach( function ( wrapper ) {
				var isHidden  = wrapper.getAttribute( 'aria-hidden' ) === 'true';
				var shouldShow = false;

				// Multi-condition (AND logic) — data-show-when-conditions JSON array.
				var multiAttr = wrapper.dataset.showWhenConditions;
				if ( multiAttr ) {
					try {
						var conditions = JSON.parse( multiAttr );
						shouldShow = conditions.length > 0 && conditions.every( function ( cond ) {
							return isOptionSelected( cond.question, cond.option );
						} );
					} catch ( e ) {
						shouldShow = false;
					}
				} else {
					// Single-condition — data-show-when-question / data-show-when-option.
					var triggerKey    = wrapper.dataset.showWhenQuestion;
					var triggerOption = wrapper.dataset.showWhenOption;
					shouldShow = isOptionSelected( triggerKey, triggerOption );
				}

				if ( shouldShow && isHidden ) {
					// Reveal.
					wrapper.removeAttribute( 'aria-hidden' );
					wrapper.style.display = '';

					// Auto-check single-option radio questions (e.g. assembly upgrade).
					var radios = wrapper.querySelectorAll( 'input[type="radio"]' );
					if ( radios.length === 1 && ! radios[ 0 ].checked ) {
						radios[ 0 ].checked = true;
					}

					changed = true;
				} else if ( ! shouldShow && ! isHidden ) {
					// Hide + reset inputs.
					wrapper.setAttribute( 'aria-hidden', 'true' );
					wrapper.style.display = 'none';
					wrapper.querySelectorAll( 'input' ).forEach( function ( inp ) {
						inp.checked = false;
					} );
					wrapper.querySelectorAll( 'select' ).forEach( function ( sel ) {
						sel.selectedIndex = 0;
					} );
					changed = true;
				}
			} );

			// Re-dispatch once after all conditionals are evaluated so Pricing
			// and ImageSwitcher recalculate from the updated DOM state.
			// The re-dispatch will re-run evaluate() but all elements are now in
			// their correct state so no further changes will occur (loop safe).
			if ( changed && window.HCQBDispatch ) {
				HCQBDispatch( { source: 'conditionals' } );
			}
		},
	};

	window.HCQBConditionals = HCQBConditionals;
}() );
