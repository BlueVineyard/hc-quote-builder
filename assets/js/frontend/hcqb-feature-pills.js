/**
 * hcqb-feature-pills.js
 *
 * Updates feature pill labels whenever the user changes a selection.
 * Only questions configured with show_in_pill=true (max 4) have pills.
 *
 * Each pill has data-question-key matching the question key. On every
 * selection change, the pill's .hcqb-pill__value is updated with the
 * currently selected option label, or "—" if nothing is selected.
 *
 * Pills inside hidden conditional questions are treated as unselected.
 *
 * Exposes: window.HCQBFeaturePills
 * Depends on: (none — reads DOM only)
 */

( function () {
	'use strict';

	var HCQBFeaturePills = {

		init: function () {
			document.addEventListener( 'hcqb:selection-changed', this.update.bind( this ) );
		},

		update: function () {
			document.querySelectorAll( '.hcqb-pill[data-question-key]' ).forEach( function ( pill ) {
				var questionKey = pill.dataset.questionKey;
				var valueEl     = pill.querySelector( '.hcqb-pill__value' );
				if ( ! valueEl ) { return; }

				var selectedLabel = '';

				// Selector scoped to visible (non-hidden) question wrappers.
				var questionSelector = '.hcqb-question:not([aria-hidden="true"])[data-question-key="' + questionKey + '"]';

				// Radio / checkbox — find checked input, read parent label text.
				var checked = document.querySelector( questionSelector + ' input:checked' );
				if ( checked ) {
					var label = checked.closest( 'label' );
					if ( label ) {
						var clone     = label.cloneNode( true );
						var priceSpan = clone.querySelector( '.hcqb-option-price' );
						if ( priceSpan ) { priceSpan.remove(); }
						selectedLabel = clone.textContent.trim();
					}
				}

				// Select / dropdown — use selected option text (strip price annotation).
				if ( ! selectedLabel ) {
					var select = document.querySelector( questionSelector + ' select' );
					if ( select && select.value ) {
						var opt = select.options[ select.selectedIndex ];
						if ( opt ) {
							// Strip anything in parentheses (price annotation) at end.
							selectedLabel = opt.textContent.trim().replace( /\s*\([^)]*\)\s*$/, '' );
						}
					}
				}

				valueEl.textContent = selectedLabel || '—';
				pill.classList.toggle( 'hcqb-pill--active', Boolean( selectedLabel ) );
			} );
		},
	};

	window.HCQBFeaturePills = HCQBFeaturePills;
}() );
