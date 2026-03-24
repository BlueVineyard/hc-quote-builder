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
	 *
	 * Special slugs:
	 *   __any__   — true when any non-empty value is selected.
	 *   __empty__ — true when no value is selected.
	 */
	function isOptionSelected( questionKey, optionSlug ) {
		var wrapper = document.querySelector(
			'.hcqb-question[data-question-key="' + questionKey + '"]'
		);
		if ( ! wrapper ) return false;

		// Meta-conditions: __any__ / __empty__.
		if ( optionSlug === '__any__' || optionSlug === '__empty__' ) {
			var hasValue = false;
			var checked = wrapper.querySelector( 'input:checked' );
			if ( checked && checked.value ) {
				hasValue = true;
			} else {
				var sel = wrapper.querySelector( 'select' );
				if ( sel && sel.value ) {
					hasValue = true;
				}
			}
			return optionSlug === '__any__' ? hasValue : ! hasValue;
		}

		// Standard: exact option match.
		var checkedInput = wrapper.querySelector( 'input[value="' + optionSlug + '"]:checked' );
		if ( checkedInput ) {
			return true;
		}
		var sel = wrapper.querySelector( 'select' );
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

				// Multi-condition — data-show-when-conditions JSON array.
				// Supports AND (all must match, default) and OR (any must match) via data-condition-logic.
				var multiAttr = wrapper.dataset.showWhenConditions;
				if ( multiAttr ) {
					try {
						var conditions = JSON.parse( multiAttr );
						var logic      = wrapper.dataset.conditionLogic || 'and';
						var matcher    = logic === 'or' ? 'some' : 'every';
						shouldShow = conditions.length > 0 && conditions[ matcher ]( function ( cond ) {
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

			// --- Option-level conditionals (radio & checkbox labels) ---
			document.querySelectorAll( 'label[data-option-conditional="true"]' ).forEach( function ( label ) {
				var isHidden   = label.getAttribute( 'aria-hidden' ) === 'true';
				var shouldShow = false;
				try {
					var conds = JSON.parse( label.dataset.optionShowWhen || '[]' );
					shouldShow = conds.length > 0 && conds.every( function ( c ) {
						return isOptionSelected( c.question, c.option );
					} );
				} catch ( e ) {
					shouldShow = false;
				}

				if ( shouldShow && isHidden ) {
					label.removeAttribute( 'aria-hidden' );
					label.style.display = '';
					changed = true;
				} else if ( ! shouldShow && ! isHidden ) {
					label.setAttribute( 'aria-hidden', 'true' );
					label.style.display = 'none';
					var inp = label.querySelector( 'input' );
					if ( inp && inp.checked ) {
						inp.checked = false;
						changed = true;
					}
				}
			} );

			// --- Option-level conditionals (dropdown <option> elements) ---
			document.querySelectorAll( '.hcqb-dropdown' ).forEach( function ( select ) {
				// Snapshot all original options on first encounter.
				if ( ! select._hcqbAllOptions ) {
					select._hcqbAllOptions = Array.from( select.querySelectorAll( 'option' ) );
				}

				// Skip dropdowns that have no conditional options — no rebuild needed.
				var hasConditional = select._hcqbAllOptions.some( function ( opt ) {
					return !! opt.dataset.optionConditional;
				} );
				if ( ! hasConditional ) return;

				var currentVal       = select.value;
				var valStillVisible  = false;

				// Rebuild: keep placeholder + visible options.
				while ( select.firstChild ) {
					select.removeChild( select.firstChild );
				}

				select._hcqbAllOptions.forEach( function ( opt ) {
					var condAttr = opt.dataset.optionShowWhen;
					if ( ! opt.dataset.optionConditional || ! condAttr ) {
						// Non-conditional — always visible.
						select.appendChild( opt );
						if ( opt.value === currentVal ) { valStillVisible = true; }
						return;
					}
					var shouldShow = false;
					try {
						var conds = JSON.parse( condAttr );
						shouldShow = conds.length > 0 && conds.every( function ( c ) {
							return isOptionSelected( c.question, c.option );
						} );
					} catch ( e ) {
						shouldShow = false;
					}
					if ( shouldShow ) {
						select.appendChild( opt );
						if ( opt.value === currentVal ) { valStillVisible = true; }
					}
				} );

				// Restore selection or reset if selected value was removed.
				if ( valStillVisible ) {
					select.value = currentVal;
				} else if ( currentVal ) {
					select.selectedIndex = 0;
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
