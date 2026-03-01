/**
 * hcqb-form-submit.js
 *
 * Client-side validation and AJAX submission for HC Quote Builder Frame 2.
 *
 * Validates:
 *   - First name (required)
 *   - Last name  (required)
 *   - Email      (required + format)
 *   - Confirm email (required + must match email)
 *   - Consent checkbox (must be checked)
 *
 * On valid submission: POST FormData to admin-ajax.php via fetch().
 * On success: show success message + reset form.
 * On failure: show error message + restore submit button (data preserved).
 *
 * Requires: HCQBLocale.ajaxUrl (injected by wp_localize_script)
 *           HCQBState.currentPrice (set by hcqb-pricing.js)
 *
 * @package HC_Quote_Builder
 */

( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		initFormSubmit();
	} );

	// -------------------------------------------------------------------------
	// Form submit handler
	// -------------------------------------------------------------------------

	function initFormSubmit() {
		var form = document.getElementById( 'hcqb-quote-form' );
		if ( ! form ) { return; }

		form.addEventListener( 'submit', function ( e ) {
			e.preventDefault();
			if ( ! validateForm() ) { return; }
			submitForm( form );
		} );

		// Clear individual field errors on input (live feedback).
		form.addEventListener( 'input', function ( e ) {
			var id    = e.target.id;
			var errId = getErrorId( id );
			if ( errId ) { clearError( errId ); }
		} );

		form.addEventListener( 'change', function ( e ) {
			if ( e.target.id === 'hcqb-consent' ) {
				clearError( 'hcqb-err-consent' );
			}
		} );
	}

	// -------------------------------------------------------------------------
	// Validation
	// -------------------------------------------------------------------------

	function validateForm() {
		var valid = true;

		valid = requireField( 'hcqb-first-name',    'hcqb-err-first-name',    'First name is required.'           ) && valid;
		valid = requireField( 'hcqb-last-name',     'hcqb-err-last-name',     'Last name is required.'            ) && valid;
		valid = requireEmail( 'hcqb-email',         'hcqb-err-email'                                              ) && valid;
		valid = matchEmail(   'hcqb-email-confirm', 'hcqb-err-email-confirm'                                      ) && valid;
		valid = requireCheck( 'hcqb-consent',       'hcqb-err-consent',       'Please tick the consent box to continue.' ) && valid;

		// Scroll to first visible error.
		if ( ! valid ) {
			var firstErr = document.querySelector( '.hcqb-field-error:not([hidden])' );
			if ( firstErr ) {
				firstErr.scrollIntoView( { behavior: 'smooth', block: 'center' } );
			}
		}

		return valid;
	}

	function requireField( fieldId, errId, message ) {
		if ( ! fieldValue( fieldId ) ) {
			setError( errId, message );
			return false;
		}
		clearError( errId );
		return true;
	}

	function requireEmail( fieldId, errId ) {
		var val = fieldValue( fieldId );
		if ( ! val ) {
			setError( errId, 'Email address is required.' );
			return false;
		}
		if ( ! /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test( val ) ) {
			setError( errId, 'Please enter a valid email address.' );
			return false;
		}
		clearError( errId );
		return true;
	}

	function matchEmail( confirmId, errId ) {
		var email   = fieldValue( 'hcqb-email' );
		var confirm = fieldValue( confirmId );
		if ( ! confirm ) {
			setError( errId, 'Please confirm your email address.' );
			return false;
		}
		if ( confirm !== email ) {
			setError( errId, 'Email addresses do not match.' );
			return false;
		}
		clearError( errId );
		return true;
	}

	function requireCheck( fieldId, errId, message ) {
		var el = document.getElementById( fieldId );
		if ( ! el || ! el.checked ) {
			setError( errId, message );
			return false;
		}
		clearError( errId );
		return true;
	}

	// -------------------------------------------------------------------------
	// AJAX submission
	// -------------------------------------------------------------------------

	function submitForm( form ) {
		var submitBtn = form.querySelector( '[type="submit"]' );
		var messageEl = document.getElementById( 'hcqb-form-message' );
		var ajaxUrl   = ( window.HCQBLocale && HCQBLocale.ajaxUrl ) || '/wp-admin/admin-ajax.php';

		// Disable button + show loading state.
		if ( submitBtn ) {
			submitBtn.disabled    = true;
			submitBtn.textContent = 'Submitting…';
		}
		if ( messageEl ) { messageEl.hidden = true; }

		var data = new FormData( form );
		// Append current live price from the pricing module.
		if ( window.HCQBState && HCQBState.currentPrice !== undefined ) {
			data.set( 'total_price', HCQBState.currentPrice );
		}

		fetch( ajaxUrl, { method: 'POST', body: data } )
			.then( function ( res ) {
				if ( ! res.ok ) { throw new Error( 'HTTP ' + res.status ); }
				return res.json();
			} )
			.then( function ( json ) {
				if ( json.success ) {
					showMessage(
						messageEl,
						( json.data && json.data.message ) ||
							'Your quote request has been submitted. We will be in touch shortly.',
						'success'
					);
					form.reset();
					clearAllErrors( form );
					// Scroll message into view.
					if ( messageEl ) {
						messageEl.scrollIntoView( { behavior: 'smooth', block: 'nearest' } );
					}
				} else {
					showMessage(
						messageEl,
						( json.data && json.data.message ) ||
							'Something went wrong. Please try again.',
						'error'
					);
				}
			} )
			.catch( function () {
				showMessage(
					messageEl,
					'A network error occurred. Please check your connection and try again.',
					'error'
				);
			} )
			.finally( function () {
				if ( submitBtn ) {
					submitBtn.disabled    = false;
					submitBtn.textContent = submitBtn.dataset.originalLabel || 'Submit Quote Request';
				}
			} );
	}

	// -------------------------------------------------------------------------
	// Utilities
	// -------------------------------------------------------------------------

	function fieldValue( id ) {
		var el = document.getElementById( id );
		return el ? el.value.trim() : '';
	}

	function setError( errId, message ) {
		var el = document.getElementById( errId );
		if ( el ) {
			el.textContent = message;
			el.hidden      = false;
		}
	}

	function clearError( errId ) {
		var el = document.getElementById( errId );
		if ( el ) {
			el.textContent = '';
			el.hidden      = true;
		}
	}

	function clearAllErrors( form ) {
		form.querySelectorAll( '.hcqb-field-error' ).forEach( function ( el ) {
			el.textContent = '';
			el.hidden      = true;
		} );
	}

	function showMessage( el, text, type ) {
		if ( ! el ) { return; }
		el.textContent = text;
		el.className   = 'hcqb-form-message hcqb-form-message--' + type;
		el.hidden      = false;
	}

	// Map a form field ID to its corresponding error span ID.
	function getErrorId( fieldId ) {
		var map = {
			'hcqb-first-name':    'hcqb-err-first-name',
			'hcqb-last-name':     'hcqb-err-last-name',
			'hcqb-email':         'hcqb-err-email',
			'hcqb-email-confirm': 'hcqb-err-email-confirm',
		};
		return map[ fieldId ] || null;
	}

}() );
