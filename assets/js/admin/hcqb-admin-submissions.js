/**
 * hcqb-admin-submissions.js
 *
 * Handles the status AJAX save on the hc-quote-submissions detail view.
 *
 * Depends on: HCQBSubmissions (wp_localize_script) — { ajaxUrl, nonce }
 *
 * DOM elements expected:
 *   #hcqb-save-status     — Save button (data-post-id attribute)
 *   #hcqb-status-select   — Status <select>
 *   #hcqb-status-badge    — Inline badge (data-status attribute + text)
 *   #hcqb-status-msg      — Inline message span
 *   #hcqb_status_nonce    — Hidden nonce input (wp_nonce_field output)
 */

( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		var saveBtn  = document.getElementById( 'hcqb-save-status' );
		var selectEl = document.getElementById( 'hcqb-status-select' );
		var badge    = document.getElementById( 'hcqb-status-badge' );
		var msgEl    = document.getElementById( 'hcqb-status-msg' );
		var nonceEl  = document.getElementById( 'hcqb_status_nonce' );

		if ( ! saveBtn || ! selectEl || ! badge ) { return; }

		saveBtn.addEventListener( 'click', function () {
			var postId    = saveBtn.dataset.postId;
			var statusKey = selectEl.value;
			var nonce     = nonceEl ? nonceEl.value : '';

			saveBtn.disabled    = true;
			saveBtn.textContent = 'Saving\u2026';

			var data = new FormData();
			data.append( 'action',     'hcqb_update_submission_status' );
			data.append( 'nonce',      nonce );
			data.append( 'post_id',    postId );
			data.append( 'status_key', statusKey );

			fetch( HCQBSubmissions.ajaxUrl, { method: 'POST', body: data } )
				.then( function ( r ) { return r.json(); } )
				.then( function ( res ) {
					if ( res.success ) {
						// Update badge text and data-status colour attribute.
						var selectedOpt       = selectEl.options[ selectEl.selectedIndex ];
						badge.textContent     = selectedOpt ? selectedOpt.textContent.trim() : statusKey;
						badge.dataset.status  = statusKey;
						showMsg( 'Status updated.', 'success' );
					} else {
						showMsg( ( res.data && res.data.message ) || 'Update failed.', 'error' );
					}
				} )
				.catch( function () {
					showMsg( 'Network error. Please try again.', 'error' );
				} )
				.finally( function () {
					saveBtn.disabled    = false;
					saveBtn.textContent = 'Save Status';
				} );
		} );

		function showMsg( text, type ) {
			if ( ! msgEl ) { return; }
			msgEl.textContent = text;
			msgEl.className   = 'hcqb-status-msg hcqb-status-msg--' + type;
			msgEl.hidden      = false;
			setTimeout( function () { msgEl.hidden = true; }, 4000 );
		}
	} );

}() );
