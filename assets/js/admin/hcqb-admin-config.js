/**
 * hcqb-admin-config.js
 *
 * Drives the hc-quote-configs edit screen:
 *   - Questions Builder: add / remove / drag-reorder questions and nested options
 *   - Slug auto-generation on first label blur (never overwrites an existing slug)
 *   - Assembly role enforcement: disables the 'assembly' option in all other role
 *     selects within the same question when one option claims it
 *   - Conditional fields: shows/hides show_when_question + show_when_option selects,
 *     populates them from the live question DOM, restores saved values on page load
 *   - Image tag rebuilding: rescans the DOM for affects_image options and updates
 *     all match-tags selects whenever option state changes
 *   - Image rules: add / remove / drag-reorder rule rows; wp.media image picker
 *   - Form reindexing: all name indices updated to sequential DOM order before submit
 *     so PHP receives a clean 0-based array regardless of add/remove/reorder history
 *
 * Depends on: HCQBRepeater (hcqb-admin-repeater.js), wp.media
 * No jQuery — vanilla ES6+.
 */

( function () {
	'use strict';

	// =========================================================================
	// Counter — used for temp array indices in newly-created rows.
	// Any value above the highest PHP-rendered index works; 1000 is ample.
	// The reindexAll() call before submit normalises everything.
	// =========================================================================

	var tempIdx = 1000;

	function nextTempIdx() {
		return tempIdx++;
	}

	// =========================================================================
	// Utilities
	// =========================================================================

	function generateUid( prefix ) {
		return prefix + '_' + Date.now() + '_' + Math.random().toString( 36 ).substring( 2, 7 );
	}

	function slugify( str ) {
		return str
			.toLowerCase()
			.replace( /[^a-z0-9]+/g, '_' )
			.replace( /^_+|_+$/g, '' );
	}

	function escAttr( str ) {
		return String( str )
			.replace( /&/g, '&amp;' )
			.replace( /"/g, '&quot;' )
			.replace( /'/g, '&#039;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' );
	}

	// =========================================================================
	// Questions — init existing rows on page load
	// =========================================================================

	function initAllQuestions() {
		document.querySelectorAll( '#hcqb-questions-list > .hcqb-question-row' ).forEach( function ( row ) {
			initQuestionRow( row, false );
		} );
	}

	// =========================================================================
	// Question row — init
	// =========================================================================

	function initQuestionRow( row, expanded ) {
		// Toggle expand / collapse.
		var toggleBtn = row.querySelector( '.hcqb-toggle-question' );
		if ( toggleBtn ) {
			toggleBtn.addEventListener( 'click', function () {
				toggleQuestion( row, toggleBtn );
			} );
		}

		// Remove button.
		var removeBtn = row.querySelector( ':scope > .hcqb-question-header > .hcqb-repeater__remove' );
		if ( removeBtn ) {
			removeBtn.addEventListener( 'click', function () {
				row.remove();
				refreshConditionalDropdowns();
				rebuildImageTagSelects();
			} );
		}

		// Label → live preview update + slug auto-fill on first blur.
		var labelInput = row.querySelector( '.hcqb-question-label-input' );
		var keyHidden  = row.querySelector( '.hcqb-key-hidden' );
		var preview    = row.querySelector( '.hcqb-question-label-preview' );
		var slugDisplay = row.querySelector( ':scope > .hcqb-question-header > .hcqb-slug-locked' );

		if ( labelInput ) {
			labelInput.addEventListener( 'input', function () {
				if ( preview ) preview.textContent = this.value || 'New Question';
				refreshConditionalDropdowns();
			} );
			labelInput.addEventListener( 'blur', function () {
				if ( keyHidden && ! keyHidden.value && this.value ) {
					var generated = slugify( this.value );
					keyHidden.value = generated;
					if ( slugDisplay ) slugDisplay.textContent = generated;
				}
			} );
		}

		// Conditional checkbox.
		var condCheck = row.querySelector( '.hcqb-is-conditional-check' );
		if ( condCheck ) {
			condCheck.addEventListener( 'change', function () {
				var fields = row.querySelector( '.hcqb-conditional-fields' );
				if ( fields ) {
					fields.classList.toggle( 'hcqb-hidden', ! this.checked );
				}
			} );
		}

		// show_when_question change → rebuild show_when_option.
		var swqSelect = row.querySelector( '.hcqb-show-when-question' );
		if ( swqSelect ) {
			swqSelect.addEventListener( 'change', function () {
				refreshConditionalOptions( row );
			} );
		}

		// Init existing option rows.
		row.querySelectorAll( '.hcqb-option-row' ).forEach( function ( oRow ) {
			initOptionRow( oRow, row );
		} );

		// "Add Option" button.
		var addOptionBtn = row.querySelector( '.hcqb-add-option' );
		if ( addOptionBtn ) {
			addOptionBtn.addEventListener( 'click', function () {
				addOption( row );
			} );
		}

		// Drag reorder via HCQBRepeater.
		HCQBRepeater.initDrag( row );

		// Expand if requested (newly added questions open immediately).
		if ( expanded ) {
			var body = row.querySelector( '.hcqb-question-body' );
			if ( body ) {
				body.classList.remove( 'hcqb-hidden' );
				if ( toggleBtn ) toggleBtn.textContent = 'Collapse';
			}
		}
	}

	function toggleQuestion( row, btn ) {
		var body = row.querySelector( '.hcqb-question-body' );
		if ( ! body ) return;
		var hidden = body.classList.toggle( 'hcqb-hidden' );
		btn.textContent = hidden ? 'Expand' : 'Collapse';
	}

	// =========================================================================
	// Question row — build new
	// =========================================================================

	function addQuestion() {
		var list = document.getElementById( 'hcqb-questions-list' );
		if ( ! list ) return;

		var uid    = generateUid( 'q' );
		var qIdx   = nextTempIdx();
		var row    = buildQuestionRow( uid, qIdx );
		list.appendChild( row );
		initQuestionRow( row, true );
		refreshConditionalDropdowns();
	}

	function buildQuestionRow( uid, qIdx ) {
		var b    = 'hcqb_questions[' + qIdx + ']';
		var row  = document.createElement( 'div' );
		row.className  = 'hcqb-question-row';
		row.dataset.uid = uid;

		row.innerHTML =
			'<div class="hcqb-question-header">' +
				'<span class="hcqb-repeater__handle dashicons dashicons-menu" title="Drag to reorder"></span>' +
				'<span class="hcqb-question-label-preview"><em>New Question</em></span>' +
				'<span class="hcqb-slug-locked"><em>auto</em></span>' +
				'<button type="button" class="button hcqb-toggle-question">Collapse</button>' +
				'<button type="button" class="button hcqb-repeater__remove">Remove</button>' +
			'</div>' +
			'<div class="hcqb-question-body">' +
				'<input type="hidden" name="' + escAttr( b ) + '[_uid]" value="' + escAttr( uid ) + '">' +
				'<input type="hidden" name="' + escAttr( b ) + '[key]"  value="" class="hcqb-key-hidden">' +
				'<table class="form-table hcqb-meta-table hcqb-question-table">' +
					'<tr><th><label>Label</label></th>' +
					'<td><input type="text" name="' + escAttr( b ) + '[label]" value="" class="regular-text hcqb-question-label-input" placeholder="Question label"></td></tr>' +
					'<tr><th><label>Input Type</label></th>' +
					'<td><select name="' + escAttr( b ) + '[input_type]">' +
						'<option value="radio">Radio buttons</option>' +
						'<option value="dropdown">Dropdown</option>' +
						'<option value="checkbox">Checkboxes (multi-select)</option>' +
					'</select></td></tr>' +
					'<tr><th>Required</th>' +
					'<td><label><input type="checkbox" name="' + escAttr( b ) + '[required]" value="1"> Customer must answer this question</label></td></tr>' +
					'<tr><th><label>Helper Text</label></th>' +
					'<td><input type="text" name="' + escAttr( b ) + '[helper_text]" value="" class="regular-text" placeholder="Optional hint shown below the question"></td></tr>' +
					'<tr><th>Show in Pill</th>' +
					'<td><label><input type="checkbox" name="' + escAttr( b ) + '[show_in_pill]" value="1"> Display selection as a feature pill (max 4 pills total)</label></td></tr>' +
					'<tr><th>Conditional</th>' +
					'<td>' +
						'<label><input type="checkbox" name="' + escAttr( b ) + '[is_conditional]" value="1" class="hcqb-is-conditional-check"> Only show when another question\'s answer is…</label>' +
						'<div class="hcqb-conditional-fields hcqb-hidden">' +
							'<select name="' + escAttr( b ) + '[show_when_question]" class="hcqb-show-when-question"><option value="">— Select question —</option></select>' +
							'<select name="' + escAttr( b ) + '[show_when_option]"   class="hcqb-show-when-option"><option value="">— Select option —</option></select>' +
						'</div>' +
					'</td></tr>' +
				'</table>' +
				'<div class="hcqb-options-wrap">' +
					'<h4 class="hcqb-section-heading">Options</h4>' +
					'<div class="hcqb-options-list"></div>' +
					'<button type="button" class="button hcqb-add-option">+ Add Option</button>' +
				'</div>' +
			'</div>';

		return row;
	}

	// =========================================================================
	// Option row — init
	// =========================================================================

	function initOptionRow( oRow, qRow ) {
		// Remove button.
		var removeBtn = oRow.querySelector( '.hcqb-repeater__remove' );
		if ( removeBtn ) {
			removeBtn.addEventListener( 'click', function () {
				oRow.remove();
				refreshAssemblyStates( qRow );
				rebuildImageTagSelects();
			} );
		}

		// Label input → slug auto-fill on first blur + slug display update.
		var labelInput  = oRow.querySelector( '.hcqb-option-label-input' );
		var slugHidden  = oRow.querySelector( '.hcqb-option-slug-hidden' );
		var slugDisplay = oRow.querySelector( '.hcqb-option-slug-display' );

		if ( labelInput ) {
			labelInput.addEventListener( 'blur', function () {
				if ( slugHidden && ! slugHidden.value && this.value ) {
					var generated = slugify( this.value );
					slugHidden.value = generated;
					if ( slugDisplay ) slugDisplay.textContent = generated;
				}
				rebuildImageTagSelects();
			} );
		}

		// Assembly role enforcement.
		var roleSelect = oRow.querySelector( '.hcqb-option-role' );
		if ( roleSelect ) {
			roleSelect.addEventListener( 'change', function () {
				refreshAssemblyStates( qRow );
			} );
		}

		// affects_image toggle → rebuild image tag selects.
		var affectsCheck = oRow.querySelector( '.hcqb-affects-image-check' );
		if ( affectsCheck ) {
			affectsCheck.addEventListener( 'change', function () {
				rebuildImageTagSelects();
			} );
		}

		// Drag reorder.
		HCQBRepeater.initDrag( oRow );

		// Set initial assembly state.
		refreshAssemblyStates( qRow );
	}

	// =========================================================================
	// Option row — build new
	// =========================================================================

	function addOption( qRow ) {
		var list = qRow.querySelector( '.hcqb-options-list' );
		if ( ! list ) return;

		// Use current DOM position of qRow as temp question index.
		var qRows = document.querySelectorAll( '#hcqb-questions-list > .hcqb-question-row' );
		var qIdx  = Array.from( qRows ).indexOf( qRow );
		if ( qIdx < 0 ) qIdx = nextTempIdx();

		var oIdx  = list.children.length; // safe since we append after building
		var uid   = generateUid( 'o' );
		var oRow  = buildOptionRow( uid, qIdx, oIdx );
		list.appendChild( oRow );
		initOptionRow( oRow, qRow );
		rebuildImageTagSelects();
	}

	function buildOptionRow( uid, qIdx, oIdx ) {
		var b   = 'hcqb_questions[' + qIdx + '][options][' + oIdx + ']';
		var row = document.createElement( 'div' );
		row.className   = 'hcqb-repeater__row hcqb-option-row';
		row.dataset.uid = uid;

		row.innerHTML =
			'<span class="hcqb-repeater__handle dashicons dashicons-menu" title="Drag to reorder"></span>' +
			'<input type="hidden" name="' + escAttr( b ) + '[_uid]" value="' + escAttr( uid ) + '">' +
			'<input type="hidden" name="' + escAttr( b ) + '[slug]" value="" class="hcqb-option-slug-hidden">' +
			'<input type="text" name="' + escAttr( b ) + '[label]" value="" class="regular-text hcqb-option-label-input" placeholder="Option label">' +
			'<span class="hcqb-slug-locked hcqb-option-slug-display" title="Option slug — immutable after first save"><em>auto</em></span>' +
			'<span class="hcqb-extras-unit">$</span>' +
			'<input type="number" name="' + escAttr( b ) + '[price]" value="0" class="small-text" step="0.01" min="0" placeholder="0.00">' +
			'<select name="' + escAttr( b ) + '[price_type]" class="hcqb-price-type">' +
				'<option value="addition">+ add</option>' +
				'<option value="deduction">− deduct</option>' +
			'</select>' +
			'<select name="' + escAttr( b ) + '[option_role]" class="hcqb-option-role">' +
				'<option value="standard">Standard</option>' +
				'<option value="assembly">Assembly</option>' +
			'</select>' +
			'<label class="hcqb-affects-image-label" title="Affects product image display">' +
				'<input type="checkbox" name="' + escAttr( b ) + '[affects_image]" value="1" class="hcqb-affects-image-check"> Image' +
			'</label>' +
			'<button type="button" class="button hcqb-repeater__remove">Remove</button>';

		return row;
	}

	// =========================================================================
	// Assembly role enforcement
	// Only one option per question may hold the 'assembly' role.
	// When one select holds 'assembly', disable that option in all other selects
	// within the same question. Re-enable when the 'assembly' holder changes.
	// =========================================================================

	function refreshAssemblyStates( qRow ) {
		var roleSelects   = Array.from( qRow.querySelectorAll( '.hcqb-option-role' ) );
		var assemblyOwner = null;

		roleSelects.forEach( function ( sel ) {
			if ( sel.value === 'assembly' ) {
				assemblyOwner = sel;
			}
		} );

		roleSelects.forEach( function ( sel ) {
			var assemblyOpt = sel.querySelector( 'option[value="assembly"]' );
			if ( ! assemblyOpt ) return;
			// Disable 'assembly' option on all selects that don't currently hold it.
			assemblyOpt.disabled = ( assemblyOwner !== null && sel !== assemblyOwner );
		} );
	}

	// =========================================================================
	// Conditional dropdowns
	// =========================================================================

	/**
	 * Rebuild the show_when_question select for every conditional question row.
	 * Each select is populated with all OTHER questions' keys and labels.
	 * Previously selected values are preserved where possible.
	 */
	function refreshConditionalDropdowns() {
		// Snapshot all question key → label pairs.
		var questionData = [];
		document.querySelectorAll( '#hcqb-questions-list > .hcqb-question-row' ).forEach( function ( qRow ) {
			var keyInput   = qRow.querySelector( '.hcqb-key-hidden' );
			var labelInput = qRow.querySelector( '.hcqb-question-label-input' );
			if ( keyInput && labelInput ) {
				questionData.push( {
					uid:   qRow.dataset.uid || '',
					key:   keyInput.value,
					label: labelInput.value || '(unnamed)',
				} );
			}
		} );

		// For each question, rebuild its show_when_question select.
		document.querySelectorAll( '#hcqb-questions-list > .hcqb-question-row' ).forEach( function ( qRow ) {
			var swqSelect = qRow.querySelector( '.hcqb-show-when-question' );
			if ( ! swqSelect ) return;

			var savedVal = swqSelect.value;
			swqSelect.innerHTML = '<option value="">— Select question —</option>';

			questionData.forEach( function ( qd ) {
				if ( qd.uid === qRow.dataset.uid ) return; // exclude self
				var opt       = document.createElement( 'option' );
				opt.value     = qd.key;
				opt.textContent = qd.label;
				if ( qd.key && qd.key === savedVal ) opt.selected = true;
				swqSelect.appendChild( opt );
			} );

			// Refresh the option select based on current question selection.
			if ( swqSelect.value ) {
				refreshConditionalOptions( qRow );
			}
		} );
	}

	/**
	 * Populate the show_when_option select based on the currently selected
	 * show_when_question value within a conditional question row.
	 */
	function refreshConditionalOptions( condQRow ) {
		var swqSelect = condQRow.querySelector( '.hcqb-show-when-question' );
		var swoSelect = condQRow.querySelector( '.hcqb-show-when-option' );
		if ( ! swqSelect || ! swoSelect ) return;

		var targetKey    = swqSelect.value;
		var savedOptVal  = swoSelect.value;
		swoSelect.innerHTML = '<option value="">— Select option —</option>';
		if ( ! targetKey ) return;

		// Find the target question row by its key.
		var targetQRow = null;
		document.querySelectorAll( '#hcqb-questions-list > .hcqb-question-row' ).forEach( function ( qRow ) {
			var ki = qRow.querySelector( '.hcqb-key-hidden' );
			if ( ki && ki.value === targetKey ) targetQRow = qRow;
		} );
		if ( ! targetQRow ) return;

		targetQRow.querySelectorAll( '.hcqb-option-row' ).forEach( function ( oRow ) {
			var slugInput  = oRow.querySelector( '.hcqb-option-slug-hidden' );
			var labelInput = oRow.querySelector( '.hcqb-option-label-input' );
			if ( ! slugInput || ! labelInput ) return;

			var slug  = slugInput.value;
			var label = labelInput.value || slug;
			if ( ! slug && ! label ) return;

			var displayVal = slug || slugify( label );
			var opt        = document.createElement( 'option' );
			opt.value      = displayVal;
			opt.textContent = label || displayVal;
			if ( displayVal === savedOptVal ) opt.selected = true;
			swoSelect.appendChild( opt );
		} );
	}

	/**
	 * After refreshConditionalDropdowns() has populated the selects,
	 * restore saved question/option values from the PHP-rendered hidden inputs.
	 */
	function restoreConditionalValues() {
		document.querySelectorAll( '#hcqb-questions-list > .hcqb-question-row' ).forEach( function ( qRow ) {
			var initSwQ = qRow.querySelector( '.hcqb-init-sw-q' );
			var initSwO = qRow.querySelector( '.hcqb-init-sw-o' );
			var swqSelect = qRow.querySelector( '.hcqb-show-when-question' );
			var swoSelect = qRow.querySelector( '.hcqb-show-when-option' );

			if ( initSwQ && swqSelect && initSwQ.value ) {
				swqSelect.value = initSwQ.value;
				refreshConditionalOptions( qRow );
			}
			if ( initSwO && swoSelect && initSwO.value ) {
				swoSelect.value = initSwO.value;
			}
		} );
	}

	// =========================================================================
	// Image tag selects
	// Rescans the DOM for options with .hcqb-affects-image-check checked
	// and rebuilds all .hcqb-match-tags-select elements in image rule rows.
	// =========================================================================

	function rebuildImageTagSelects() {
		var tagOptions = buildTagOptionsFromDom();
		window.hcqbTagOptions = tagOptions;

		document.querySelectorAll( '.hcqb-match-tags-select' ).forEach( function ( select ) {
			// Preserve currently selected values.
			var saved = Array.from( select.selectedOptions ).map( function ( opt ) { return opt.value; } );

			select.innerHTML = '';

			Object.keys( tagOptions ).forEach( function ( slug ) {
				var opt       = document.createElement( 'option' );
				opt.value     = slug;
				opt.textContent = tagOptions[ slug ];
				if ( saved.indexOf( slug ) !== -1 ) opt.selected = true;
				select.appendChild( opt );
			} );
		} );
	}

	function buildTagOptionsFromDom() {
		var map = {};
		document.querySelectorAll( '#hcqb-questions-list > .hcqb-question-row' ).forEach( function ( qRow ) {
			var qLabelInput = qRow.querySelector( '.hcqb-question-label-input' );
			var qLabel      = qLabelInput ? ( qLabelInput.value || '' ) : '';

			qRow.querySelectorAll( '.hcqb-option-row' ).forEach( function ( oRow ) {
				var check  = oRow.querySelector( '.hcqb-affects-image-check' );
				if ( ! check || ! check.checked ) return;

				var slugInput  = oRow.querySelector( '.hcqb-option-slug-hidden' );
				var labelInput = oRow.querySelector( '.hcqb-option-label-input' );

				var slug  = slugInput  ? slugInput.value  : '';
				var label = labelInput ? labelInput.value : '';

				// If slug isn't saved yet, derive from label (for display only —
				// the actual slug will be locked in on first PHP save).
				if ( ! slug && label ) slug = slugify( label );
				if ( ! slug ) return;

				map[ slug ] = ( qLabel ? qLabel + ' — ' : '' ) + ( label || slug );
			} );
		} );
		return map;
	}

	// =========================================================================
	// Image rules
	// =========================================================================

	function initAllImageRules() {
		document.querySelectorAll( '#hcqb-image-rules-list > .hcqb-image-rule-row' ).forEach( function ( row ) {
			initImageRuleRow( row );
		} );
	}

	function initImageRuleRow( row ) {
		// Remove button.
		var removeBtn = row.querySelector( '.hcqb-repeater__remove' );
		if ( removeBtn ) {
			removeBtn.addEventListener( 'click', function () {
				row.remove();
			} );
		}

		// Choose image button.
		var chooseBtn = row.querySelector( '.hcqb-choose-rule-image' );
		if ( chooseBtn ) {
			chooseBtn.addEventListener( 'click', function () {
				openRuleImagePicker( row );
			} );
		}

		// Remove image button (may not exist on new rows).
		initRuleImageRemove( row );

		// Drag reorder.
		HCQBRepeater.initDrag( row );
	}

	function initRuleImageRemove( row ) {
		var removeImgBtn = row.querySelector( '.hcqb-remove-rule-image' );
		if ( ! removeImgBtn ) return;
		removeImgBtn.addEventListener( 'click', function () {
			var hidden  = row.querySelector( '.hcqb-rule-attachment-id' );
			var preview = row.querySelector( '.hcqb-rule-image-preview' );
			if ( hidden  ) hidden.value = '0';
			if ( preview ) {
				var empty    = document.createElement( 'span' );
				empty.className = 'hcqb-rule-image-preview hcqb-rule-image-empty';
				preview.parentNode.replaceChild( empty, preview );
			}
			removeImgBtn.remove();
		} );
	}

	function openRuleImagePicker( row ) {
		var frame = wp.media( {
			title:    'Choose Rule Image',
			button:   { text: 'Use This Image' },
			multiple:  false,
			library:  { type: 'image' },
		} );

		frame.on( 'select', function () {
			var attachment = frame.state().get( 'selection' ).first().toJSON();
			var thumbUrl   = ( attachment.sizes && attachment.sizes.thumbnail )
				? attachment.sizes.thumbnail.url
				: attachment.url;

			var hidden  = row.querySelector( '.hcqb-rule-attachment-id' );
			var preview = row.querySelector( '.hcqb-rule-image-preview' );

			if ( hidden ) hidden.value = attachment.id;

			// Replace placeholder span or update existing img.
			if ( preview && preview.tagName === 'SPAN' ) {
				var img    = document.createElement( 'img' );
				img.className = 'hcqb-rule-image-preview';
				img.width  = 60;
				img.height = 60;
				img.alt    = '';
				img.src    = thumbUrl;
				preview.parentNode.replaceChild( img, preview );
			} else if ( preview ) {
				preview.src = thumbUrl;
			}

			// Ensure a remove button exists.
			if ( ! row.querySelector( '.hcqb-remove-rule-image' ) ) {
				var chooseBtn = row.querySelector( '.hcqb-choose-rule-image' );
				var btn       = document.createElement( 'button' );
				btn.type      = 'button';
				btn.className = 'button hcqb-remove-rule-image';
				btn.textContent = 'Remove';
				if ( chooseBtn && chooseBtn.parentNode ) {
					chooseBtn.parentNode.insertBefore( btn, chooseBtn.nextSibling );
				}
				initRuleImageRemove( row );
			}
		} );

		frame.open();
	}

	function addImageRule() {
		var list = document.getElementById( 'hcqb-image-rules-list' );
		if ( ! list ) return;

		var rIdx = nextTempIdx();
		var row  = buildImageRuleRow( rIdx );
		list.appendChild( row );
		initImageRuleRow( row );
	}

	function buildImageRuleRow( rIdx ) {
		var b   = 'hcqb_image_rules[' + rIdx + ']';
		var row = document.createElement( 'div' );
		row.className = 'hcqb-repeater__row hcqb-image-rule-row';

		// Build match-tags select from current tag options.
		var tagOptions = window.hcqbTagOptions || {};
		var optionsHtml = '';
		Object.keys( tagOptions ).forEach( function ( slug ) {
			optionsHtml += '<option value="' + escAttr( slug ) + '">' + escAttr( tagOptions[ slug ] ) + '</option>';
		} );

		row.innerHTML =
			'<span class="hcqb-repeater__handle dashicons dashicons-menu" title="Drag to reorder"></span>' +
			'<div class="hcqb-rule-tags-wrap">' +
				'<span class="hcqb-rule-label">Match tags</span>' +
				'<select name="' + escAttr( b ) + '[match_tags][]" multiple class="hcqb-match-tags-select" size="4">' +
					optionsHtml +
				'</select>' +
				'<p class="description">Hold Ctrl / Cmd to select multiple. Leave empty for default fallback.</p>' +
			'</div>' +
			'<div class="hcqb-rule-image-wrap">' +
				'<span class="hcqb-rule-label">Image</span>' +
				'<input type="hidden" name="' + escAttr( b ) + '[attachment_id]" value="0" class="hcqb-rule-attachment-id">' +
				'<span class="hcqb-rule-image-preview hcqb-rule-image-empty"></span>' +
				'<button type="button" class="button hcqb-choose-rule-image">Choose</button>' +
			'</div>' +
			'<div class="hcqb-rule-view-wrap">' +
				'<span class="hcqb-rule-label">View</span>' +
				'<select name="' + escAttr( b ) + '[view]">' +
					'<option value="front">Front</option>' +
					'<option value="side">Side</option>' +
					'<option value="back">Back</option>' +
					'<option value="interior">Interior</option>' +
				'</select>' +
			'</div>' +
			'<button type="button" class="button hcqb-repeater__remove">Remove</button>';

		return row;
	}

	// =========================================================================
	// Combination generator
	// =========================================================================

	/**
	 * Build an array of question groups from the DOM.
	 * Only questions that have at least one affects_image option are included.
	 * Shape: [ { label: 'Colour', options: [ { slug, label }, … ] }, … ]
	 */
	function buildTagGroupsFromDom() {
		var groups = [];
		document.querySelectorAll( '#hcqb-questions-list > .hcqb-question-row' ).forEach( function ( qRow ) {
			var qLabelInput = qRow.querySelector( '.hcqb-question-label-input' );
			var qLabel      = qLabelInput ? ( qLabelInput.value || 'Unnamed Question' ) : 'Unnamed Question';
			var options     = [];

			qRow.querySelectorAll( '.hcqb-option-row' ).forEach( function ( oRow ) {
				var check = oRow.querySelector( '.hcqb-affects-image-check' );
				if ( ! check || ! check.checked ) return;

				var slugInput  = oRow.querySelector( '.hcqb-option-slug-hidden' );
				var labelInput = oRow.querySelector( '.hcqb-option-label-input' );
				var slug       = slugInput  ? slugInput.value  : '';
				var label      = labelInput ? labelInput.value : '';

				if ( ! slug && label ) slug = slugify( label );
				if ( ! slug ) return;

				options.push( { slug: slug, label: label || slug } );
			} );

			if ( options.length ) {
				groups.push( { label: qLabel, options: options } );
			}
		} );
		return groups;
	}

	/**
	 * Compute the Cartesian product of an array of arrays.
	 * cartesian([[a,b],[x,y]]) → [[a,x],[a,y],[b,x],[b,y]]
	 */
	function cartesian( arrays ) {
		return arrays.reduce( function ( acc, arr ) {
			var result = [];
			acc.forEach( function ( existing ) {
				arr.forEach( function ( item ) {
					result.push( existing.concat( [ item ] ) );
				} );
			} );
			return result;
		}, [ [] ] );
	}

	/**
	 * Return true if a rule with exactly this set of slugs already exists in the DOM.
	 */
	function ruleTagsExist( slugs ) {
		var rows = document.querySelectorAll( '#hcqb-image-rules-list > .hcqb-image-rule-row' );
		return Array.from( rows ).some( function ( row ) {
			var selected = Array.from( row.querySelectorAll( '.hcqb-match-tags-select option' ) )
				.filter( function ( opt ) { return opt.selected; } )
				.map( function ( opt ) { return opt.value; } );
			if ( selected.length !== slugs.length ) return false;
			return slugs.every( function ( s ) { return selected.indexOf( s ) !== -1; } );
		} );
	}

	/**
	 * Build a rule row with specific tags pre-selected.
	 */
	function buildImageRuleRowWithTags( rIdx, selectedSlugs ) {
		var b           = 'hcqb_image_rules[' + rIdx + ']';
		var row         = document.createElement( 'div' );
		row.className   = 'hcqb-repeater__row hcqb-image-rule-row';

		var tagOptions  = window.hcqbTagOptions || {};
		var optionsHtml = '';
		Object.keys( tagOptions ).forEach( function ( slug ) {
			var sel = selectedSlugs.indexOf( slug ) !== -1 ? ' selected' : '';
			optionsHtml += '<option value="' + escAttr( slug ) + '"' + sel + '>' + escAttr( tagOptions[ slug ] ) + '</option>';
		} );

		row.innerHTML =
			'<span class="hcqb-repeater__handle dashicons dashicons-menu" title="Drag to reorder"></span>' +
			'<div class="hcqb-rule-tags-wrap">' +
				'<span class="hcqb-rule-label">Match tags</span>' +
				'<select name="' + escAttr( b ) + '[match_tags][]" multiple class="hcqb-match-tags-select" size="4">' +
					optionsHtml +
				'</select>' +
				'<p class="description">Hold Ctrl / Cmd to select multiple. Leave empty for default fallback.</p>' +
			'</div>' +
			'<div class="hcqb-rule-image-wrap">' +
				'<span class="hcqb-rule-label">Image</span>' +
				'<input type="hidden" name="' + escAttr( b ) + '[attachment_id]" value="0" class="hcqb-rule-attachment-id">' +
				'<span class="hcqb-rule-image-preview hcqb-rule-image-empty"></span>' +
				'<button type="button" class="button hcqb-choose-rule-image">Choose</button>' +
			'</div>' +
			'<div class="hcqb-rule-view-wrap">' +
				'<span class="hcqb-rule-label">View</span>' +
				'<select name="' + escAttr( b ) + '[view]">' +
					'<option value="front">Front</option>' +
					'<option value="side">Side</option>' +
					'<option value="back">Back</option>' +
					'<option value="interior">Interior</option>' +
				'</select>' +
			'</div>' +
			'<button type="button" class="button hcqb-repeater__remove">Remove</button>';

		return row;
	}

	/**
	 * Open the Generate Combinations dialog below the rules list.
	 * Shows one checkbox per qualifying question with a live combination count.
	 */
	function openGenerateDialog() {
		if ( document.getElementById( 'hcqb-generate-dialog' ) ) return;

		var groups = buildTagGroupsFromDom();
		if ( ! groups.length ) {
			// eslint-disable-next-line no-alert
			alert( 'No options have "Affects Image" checked. Tick the Image checkbox on at least one option per question before generating.' );
			return;
		}

		var wrap = document.querySelector( '.hcqb-image-rules-wrap' );
		if ( ! wrap ) return;

		var checkboxesHtml = '';
		groups.forEach( function ( group, i ) {
			var count = group.options.length;
			checkboxesHtml +=
				'<label class="hcqb-gen-q-label">' +
					'<input type="checkbox" class="hcqb-gen-q-check" data-index="' + i + '" checked> ' +
					escAttr( group.label ) +
					' <em>(' + count + ' option' + ( count !== 1 ? 's' : '' ) + ')</em>' +
				'</label>';
		} );

		var dialog = document.createElement( 'div' );
		dialog.id        = 'hcqb-generate-dialog';
		dialog.className = 'hcqb-generate-dialog';
		dialog.innerHTML =
			'<h3>Generate Combinations</h3>' +
			'<p>Select which questions to include:</p>' +
			'<div class="hcqb-gen-questions">' + checkboxesHtml + '</div>' +
			'<p class="hcqb-gen-count"></p>' +
			'<div class="hcqb-gen-actions">' +
				'<button type="button" class="button button-primary" id="hcqb-do-generate">Generate</button> ' +
				'<button type="button" class="button" id="hcqb-cancel-generate">Cancel</button>' +
			'</div>';

		wrap.appendChild( dialog );

		// Live count.
		updateGenerateCount( dialog, groups );
		dialog.querySelectorAll( '.hcqb-gen-q-check' ).forEach( function ( cb ) {
			cb.addEventListener( 'change', function () {
				updateGenerateCount( dialog, groups );
			} );
		} );

		dialog.querySelector( '#hcqb-cancel-generate' ).addEventListener( 'click', function () {
			dialog.remove();
		} );

		dialog.querySelector( '#hcqb-do-generate' ).addEventListener( 'click', function () {
			var selectedIndices = [];
			dialog.querySelectorAll( '.hcqb-gen-q-check:checked' ).forEach( function ( cb ) {
				selectedIndices.push( parseInt( cb.dataset.index, 10 ) );
			} );
			if ( ! selectedIndices.length ) {
				// eslint-disable-next-line no-alert
				alert( 'Please select at least one question.' );
				return;
			}
			doGenerate( groups, selectedIndices );
			dialog.remove();
		} );
	}

	/**
	 * Recalculate and display the combination count based on checked questions.
	 */
	function updateGenerateCount( dialog, groups ) {
		var selected = [];
		dialog.querySelectorAll( '.hcqb-gen-q-check:checked' ).forEach( function ( cb ) {
			selected.push( groups[ parseInt( cb.dataset.index, 10 ) ] );
		} );

		var count = selected.length
			? selected.reduce( function ( acc, g ) { return acc * g.options.length; }, 1 )
			: 0;

		var countEl = dialog.querySelector( '.hcqb-gen-count' );
		if ( countEl ) {
			var noun = count === 1 ? 'rule' : 'rules';
			countEl.innerHTML = selected.length < 2
				? 'This will add <strong>' + count + '</strong> ' + noun + '. Select 2+ questions for combinations.'
				: 'This will add up to <strong>' + count + '</strong> ' + noun + ' (duplicates will be skipped).';
		}
	}

	/**
	 * Build and append rule rows for every combination in the Cartesian product
	 * of the selected question groups. Skips any that already exist.
	 */
	function doGenerate( groups, selectedIndices ) {
		var list = document.getElementById( 'hcqb-image-rules-list' );
		if ( ! list ) return;

		var selectedGroups = selectedIndices.map( function ( i ) { return groups[ i ].options; } );
		var combinations   = cartesian( selectedGroups );
		var added   = 0;
		var skipped = 0;

		combinations.forEach( function ( combo ) {
			var slugs = combo.map( function ( opt ) { return opt.slug; } );
			if ( ruleTagsExist( slugs ) ) {
				skipped++;
				return;
			}
			var row = buildImageRuleRowWithTags( nextTempIdx(), slugs );
			list.appendChild( row );
			initImageRuleRow( row );
			added++;
		} );

		// Brief feedback notice.
		var msg    = added + ' rule' + ( added !== 1 ? 's' : '' ) + ' added.';
		if ( skipped ) msg += ' ' + skipped + ' duplicate' + ( skipped !== 1 ? 's' : '' ) + ' skipped.';
		var notice = document.createElement( 'p' );
		notice.className   = 'hcqb-gen-notice';
		notice.textContent = msg;
		list.parentNode.insertBefore( notice, list.nextSibling );
		setTimeout( function () { if ( notice.parentNode ) notice.remove(); }, 4000 );
	}

	// =========================================================================
	// Form reindexing — called right before submit
	//
	// Normalises all array indices in name attributes to sequential DOM order
	// so PHP receives a clean 0-based array regardless of add/remove/reorder.
	//
	// Two-pass per question:
	//   1. Replace hcqb_questions[N] → hcqb_questions[qIdx]  (all descendants)
	//   2. Replace [options][N]      → [options][oIdx]        (option descendants only)
	// =========================================================================

	function reindexAll() {
		// Questions.
		var qRows = document.querySelectorAll( '#hcqb-questions-list > .hcqb-question-row' );
		qRows.forEach( function ( qRow, qIdx ) {
			// Pass 1: reindex the question index portion of ALL names in this row.
			qRow.querySelectorAll( '[name]' ).forEach( function ( el ) {
				el.name = el.name.replace( /^hcqb_questions\[\d+\]/, 'hcqb_questions[' + qIdx + ']' );
			} );

			// Pass 2: reindex the option index within this question.
			var oRows = qRow.querySelectorAll( '.hcqb-options-list > .hcqb-option-row' );
			oRows.forEach( function ( oRow, oIdx ) {
				oRow.querySelectorAll( '[name]' ).forEach( function ( el ) {
					el.name = el.name.replace( /\[options\]\[\d+\]/, '[options][' + oIdx + ']' );
				} );
			} );
		} );

		// Image rules.
		var rRows = document.querySelectorAll( '#hcqb-image-rules-list > .hcqb-image-rule-row' );
		rRows.forEach( function ( rRow, rIdx ) {
			rRow.querySelectorAll( '[name]' ).forEach( function ( el ) {
				el.name = el.name.replace( /^hcqb_image_rules\[\d+\]/, 'hcqb_image_rules[' + rIdx + ']' );
			} );
		} );
	}

	// =========================================================================
	// Boot
	// =========================================================================

	document.addEventListener( 'DOMContentLoaded', function () {
		// Questions.
		initAllQuestions();
		var addQBtn = document.getElementById( 'hcqb-add-question' );
		if ( addQBtn ) {
			addQBtn.addEventListener( 'click', addQuestion );
		}

		// Image rules.
		initAllImageRules();
		var addRuleBtn = document.getElementById( 'hcqb-add-image-rule' );
		if ( addRuleBtn ) {
			addRuleBtn.addEventListener( 'click', addImageRule );
		}
		var genBtn = document.getElementById( 'hcqb-generate-image-rules' );
		if ( genBtn ) {
			genBtn.addEventListener( 'click', openGenerateDialog );
		}

		// Populate conditional dropdowns from current question data,
		// then restore saved values from PHP-rendered hidden inputs.
		refreshConditionalDropdowns();
		restoreConditionalValues();

		// Default image picker — used by standalone mode configs.
		var chooseDefaultImg = document.getElementById( 'hcqb-choose-default-image' );
		var removeDefaultImg = document.getElementById( 'hcqb-remove-default-image' );

		if ( chooseDefaultImg ) {
			chooseDefaultImg.addEventListener( 'click', function () {
				var frame = wp.media( {
					title:    'Choose Default Image',
					button:   { text: 'Use This Image' },
					multiple:  false,
					library:  { type: 'image' },
				} );
				frame.on( 'select', function () {
					var attachment = frame.state().get( 'selection' ).first().toJSON();
					var thumbUrl   = ( attachment.sizes && attachment.sizes.thumbnail )
						? attachment.sizes.thumbnail.url
						: attachment.url;
					var hidden = document.getElementById( 'hcqb_default_image_id' );
					var wrap   = document.getElementById( 'hcqb-default-image-wrap' );
					if ( hidden ) { hidden.value = attachment.id; }
					if ( wrap ) {
						wrap.innerHTML = '<img src="' + thumbUrl + '" width="60" height="60" style="object-fit:cover;border-radius:4px;display:block;">';
					}
					if ( removeDefaultImg ) { removeDefaultImg.style.display = ''; }
				} );
				frame.open();
			} );
		}

		if ( removeDefaultImg ) {
			removeDefaultImg.addEventListener( 'click', function () {
				var hidden = document.getElementById( 'hcqb_default_image_id' );
				var wrap   = document.getElementById( 'hcqb-default-image-wrap' );
				if ( hidden ) { hidden.value = '0'; }
				if ( wrap ) {
					wrap.innerHTML = '<span style="color:#999;font-size:12px;">No image selected</span>';
				}
				removeDefaultImg.style.display = 'none';
			} );
		}

		// Reindex all name attributes before the post form submits.
		// Also warn the user if any two questions share the same key.
		var postForm = document.getElementById( 'post' );
		if ( postForm ) {
			postForm.addEventListener( 'submit', function ( e ) {
				var seenKeys = {};
				var dupes    = [];
				document.querySelectorAll( '#hcqb-questions-list > .hcqb-question-row' ).forEach( function ( row ) {
					var keyInp   = row.querySelector( '.hcqb-key-hidden' );
					var labelInp = row.querySelector( '.hcqb-question-label-input' );
					if ( ! keyInp || ! keyInp.value ) { return; }
					var k = keyInp.value;
					var l = labelInp ? ( labelInp.value || k ) : k;
					if ( seenKeys[ k ] ) {
						dupes.push( '\u201c' + l + '\u201d  (key: ' + k + ')' );
					}
					seenKeys[ k ] = true;
				} );

				if ( dupes.length ) {
					var ok = window.confirm(
						'Duplicate question keys detected!\n\n' +
						dupes.join( '\n' ) + '\n\n' +
						'Questions with the same key share a radio group on the frontend, ' +
						'so selecting one option deselects the other question\u2019s selection.\n\n' +
						'Fix: rename the duplicate questions so each generates a unique key.\n\n' +
						'Save anyway?'
					);
					if ( ! ok ) {
						e.preventDefault();
						return;
					}
				}

				reindexAll();
			} );
		}
	} );

}() );
