/**
 * hcqb-conditionals.js
 *
 * Shows and hides conditional questions based on the current selection state.
 *
 * Each conditional question has:
 *   data-conditional="true"
 *   data-show-when-question="question_key"
 *   data-show-when-option="option_slug"
 *
 * When a conditional is hidden, its inputs are reset so they no longer
 * contribute to pricing or image matching. A re-dispatch is fired after
 * resetting so Pricing and ImageSwitcher recalculate immediately.
 *
 * Loop guard: state is read from aria-hidden on every run. After hiding a
 * conditional, aria-hidden is "true", so the next run finds isHidden===true
 * and skips — no infinite loop.
 *
 * Constraint: v1.0 only supports single-level conditionals (a conditional
 * question may depend on a non-conditional question only).
 *
 * Exposes: window.HCQBConditionals
 * Depends on: window.HCQBDispatch
 */

( function () {
	'use strict';

	var HCQBConditionals = {

		init: function () {
			document.addEventListener( 'hcqb:selection-changed', this.evaluate.bind( this ) );
		},

		evaluate: function () {
			var changed = false;

			document.querySelectorAll( '[data-conditional="true"]' ).forEach( function ( wrapper ) {
				var triggerKey    = wrapper.dataset.showWhenQuestion;
				var triggerOption = wrapper.dataset.showWhenOption;
				var isHidden      = wrapper.getAttribute( 'aria-hidden' ) === 'true';

				var shouldShow = false;

				// Check radio / checkbox inputs.
				var checkedInput = document.querySelector(
					'.hcqb-question[data-question-key="' + triggerKey + '"] input[value="' + triggerOption + '"]:checked'
				);
				if ( checkedInput ) {
					shouldShow = true;
				}

				// Check select / dropdown.
				if ( ! shouldShow ) {
					var triggerSelect = document.querySelector(
						'.hcqb-question[data-question-key="' + triggerKey + '"] select'
					);
					if ( triggerSelect && triggerSelect.value === triggerOption ) {
						shouldShow = true;
					}
				}

				if ( shouldShow && isHidden ) {
					// Reveal.
					wrapper.removeAttribute( 'aria-hidden' );
					wrapper.style.display = '';
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
