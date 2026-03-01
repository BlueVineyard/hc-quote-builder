<?php
/**
 * HCQB_Ajax
 *
 * Handles the hcqb_submit_quote AJAX action — the strict 10-step processing
 * pipeline. Registered for both authenticated (wp_ajax_) and guest
 * (wp_ajax_nopriv_) requests.
 *
 * Pipeline:
 *   1. Nonce verification (403 on failure)
 *   2. Honeypot check (silent fake success on match)
 *   3. Product ID validation
 *   4. Active config check
 *   5. Required field validation (server-side)
 *   6. Input sanitisation
 *   7. Post creation
 *   8. Meta persistence (all §15.3 keys)
 *   9. Email dispatch (failures logged, never block save)
 *  10. Success response
 *
 * Delegates data concerns to HCQB_Submission (validate, sanitise, save_meta).
 * Delegates email concerns to HCQB_Email (send_admin_notification, send_customer_copy).
 *
 * @package HC_Quote_Builder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HCQB_Ajax {

	// -------------------------------------------------------------------------
	// AJAX entry point
	// -------------------------------------------------------------------------

	public static function handle_submission(): void {

		// Step 1 — Nonce verification.
		// Passing false as the third argument prevents check_ajax_referer() from
		// calling wp_die() so we can return a JSON error response instead.
		if ( ! check_ajax_referer( 'hcqb_submit_quote', 'hcqb_nonce', false ) ) {
			wp_send_json_error( [ 'message' => 'Security check failed.' ], 403 );
		}

		// Step 2 — Honeypot: silently accept so bots get no signal.
		if ( ! empty( $_POST['hcqb_hp'] ) ) {
			wp_send_json_success( [ 'message' => 'Submission received.' ] );
		}

		// Step 3 — Product ID.
		$product_id = absint( $_POST['product_id'] ?? 0 );
		if ( ! $product_id || get_post_type( $product_id ) !== 'hc-containers' ) {
			wp_send_json_error( [ 'message' => 'Invalid product.' ] );
		}

		// Step 4 — Active config.
		$config = hcqb_get_active_config_for_product( $product_id );
		if ( ! $config ) {
			wp_send_json_error( [ 'message' => 'No active quote configuration found.' ] );
		}

		// Step 5 — Required field validation (mirrors client-side).
		$errors = HCQB_Submission::validate( $_POST );
		if ( ! empty( $errors ) ) {
			wp_send_json_error( [
				'message' => 'Please check the form and try again.',
				'errors'  => $errors,
			] );
		}

		// Step 6 — Sanitise all inputs.
		$data = HCQB_Submission::sanitise_data( $_POST, $product_id );

		// Step 7 — Create submission post.
		$post_id = wp_insert_post(
			[
				'post_type'   => 'hc-quote-submissions',
				'post_status' => 'publish',
				'post_title'  => trim(
					                 $data['prefix'] . ' ' .
					                 $data['first_name'] . ' ' .
					                 $data['last_name']
				                 ) . ' — ' . get_the_title( $product_id ),
			],
			true
		);
		if ( is_wp_error( $post_id ) ) {
			wp_send_json_error( [ 'message' => 'Failed to save submission. Please try again.' ] );
		}

		// Step 8 — Persist all §15.3 meta keys.
		HCQB_Submission::save_meta( $post_id, $data, $product_id );

		// Step 9 — Send emails. Log any failures but never block the save.
		$admin_sent    = HCQB_Email::send_admin_notification( $post_id, $data, $product_id );
		$customer_sent = HCQB_Email::send_customer_copy( $post_id, $data, $product_id );

		if ( ! $admin_sent || ! $customer_sent ) {
			update_option( 'hcqb_last_email_error', [
				'post_id'       => $post_id,
				'admin_sent'    => $admin_sent,
				'customer_sent' => $customer_sent,
				'time'          => current_time( 'mysql' ),
			] );
		}

		// Step 10 — Success.
		wp_send_json_success( [
			'message' => 'Thank you! Your estimate request has been sent. One of our team members will be in touch with you shortly.',
		] );
	}

	// -------------------------------------------------------------------------
	// Stage 10 — Submission status update
	// -------------------------------------------------------------------------

	public static function handle_update_status(): void {
		check_ajax_referer( 'hcqb_update_status', 'nonce' );

		$post_id    = absint( $_POST['post_id']    ?? 0 );
		$status_key = sanitize_key( $_POST['status_key'] ?? '' );

		if ( ! $post_id || get_post_type( $post_id ) !== 'hc-quote-submissions' ) {
			wp_send_json_error( [ 'message' => 'Invalid submission.' ] );
		}

		// Validate: key must exist in the configured status labels.
		$valid_keys = array_column(
			(array) hcqb_get_setting( 'submission_status_labels', [] ),
			'key'
		);
		if ( ! in_array( $status_key, $valid_keys, true ) ) {
			wp_send_json_error( [ 'message' => 'Invalid status key.' ] );
		}

		update_post_meta( $post_id, 'hcqb_submission_status', $status_key );
		wp_send_json_success();
	}
}
