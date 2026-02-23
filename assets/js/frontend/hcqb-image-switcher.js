/**
 * hcqb-image-switcher.js
 *
 * Updates the product preview image based on which option tags are currently
 * active and which view (front/back/side/interior) is selected.
 *
 * Rules are pre-sorted by PHP (most match_tags first) so the first full match
 * always wins. If no full match exists, the best partial match is used.
 * If no rule matches at all, the default image is shown.
 *
 * Listens to:
 *   hcqb:selection-changed  — rebuilds activeTags, then updates image
 *   hcqb:view-changed       — re-runs image update with new view
 *
 * Exposes: window.HCQBImageSwitcher
 * Depends on: window.HCQBConfig, window.HCQBState
 */

( function () {
	'use strict';

	var HCQBImageSwitcher = {

		imgEl: null,

		init: function () {
			this.imgEl = document.getElementById( 'hcqb-preview-img' );
			document.addEventListener( 'hcqb:selection-changed', this.onSelectionChanged.bind( this ) );
			document.addEventListener( 'hcqb:view-changed',      this.updateImage.bind( this ) );
		},

		// Rebuild activeTags from the current DOM state, then update the image.
		onSelectionChanged: function () {
			var tags = [];

			// Checked radio + checkbox inputs in visible questions.
			document.querySelectorAll(
				'.hcqb-question:not([aria-hidden="true"]) input:checked'
			).forEach( function ( input ) {
				if ( input.dataset.affectsImage === '1' ) {
					tags.push( input.value ); // value = option slug
				}
			} );

			// Selected <option> in visible dropdown questions.
			document.querySelectorAll(
				'.hcqb-question:not([aria-hidden="true"]) select'
			).forEach( function ( select ) {
				var opt = select.options[ select.selectedIndex ];
				if ( opt && opt.value && opt.dataset.affectsImage === '1' ) {
					tags.push( opt.value );
				}
			} );

			HCQBState.activeTags = tags;
			this.updateImage();
		},

		// Re-run rule matching for the current view and update the <img> src.
		updateImage: function () {
			if ( ! this.imgEl || this.imgEl.tagName !== 'IMG' ) { return; }

			var rules      = HCQBConfig.imageRules    || [];
			var activeTags = HCQBState.activeTags     || [];
			var view       = HCQBState.currentView    || 'front';

			// Only consider rules for the currently active view.
			var viewRules = rules.filter( function ( r ) { return r.view === view; } );

			var matched = this.findMatchingRule( activeTags, viewRules );
			var newSrc  = matched ? matched.image_url : ( HCQBConfig.defaultImageUrl || '' );

			if ( newSrc && this.imgEl.src !== newSrc ) {
				this.imgEl.src = newSrc;
			}
		},

		// Return the best-matching rule or null.
		// Expects rules pre-sorted by match_tags.length DESC (from PHP).
		findMatchingRule: function ( activeTags, rules ) {
			var bestFullMatch  = null;
			var bestPartial    = null;
			var bestPartialCnt = 0;

			for ( var i = 0; i < rules.length; i++ ) {
				var rule       = rules[ i ];
				var matchCount = 0;

				for ( var j = 0; j < rule.match_tags.length; j++ ) {
					if ( activeTags.indexOf( rule.match_tags[ j ] ) !== -1 ) {
						matchCount++;
					}
				}

				var isFullMatch = matchCount > 0 && matchCount === rule.match_tags.length;

				if ( isFullMatch ) {
					// Rules are pre-sorted — first full match is most specific, wins.
					if ( ! bestFullMatch ) { bestFullMatch = rule; }
				} else if ( matchCount > bestPartialCnt ) {
					bestPartial    = rule;
					bestPartialCnt = matchCount;
				}
			}

			return bestFullMatch || bestPartial || null;
		},
	};

	window.HCQBImageSwitcher = HCQBImageSwitcher;
}() );
