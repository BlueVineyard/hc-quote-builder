<?php
/**
 * HCQB_Submission
 *
 * Submission data handling: required field validation, input sanitisation,
 * and post meta persistence for all §15.3 meta keys.
 *
 * All public methods are static — called directly by HCQB_Ajax::handle_submission().
 *
 * @package HC_Quote_Builder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HCQB_Submission {

	// -------------------------------------------------------------------------
	// Validation — server-side required field checks
	// Mirrors the client-side checks in hcqb-form-submit.js.
	// -------------------------------------------------------------------------

	public static function validate( array $post ): array {
		$errors = [];

		if ( empty( trim( $post['first_name'] ?? '' ) ) ) {
			$errors['first_name'] = 'First name is required.';
		}

		if ( empty( trim( $post['last_name'] ?? '' ) ) ) {
			$errors['last_name'] = 'Last name is required.';
		}

		$email = trim( $post['email'] ?? '' );
		if ( ! $email ) {
			$errors['email'] = 'Email address is required.';
		} elseif ( ! is_email( $email ) ) {
			$errors['email'] = 'Please enter a valid email address.';
		}

		if ( empty( $post['consent'] ) || '1' !== (string) ( $post['consent'] ) ) {
			$errors['consent'] = 'Please tick the consent box to continue.';
		}

		return $errors;
	}

	// -------------------------------------------------------------------------
	// Sanitisation — clean and structure all submitted POST data
	// -------------------------------------------------------------------------

	public static function sanitise_data( array $post ): array {

		// Phone — combine prefix + local number.
		$phone_prefix = sanitize_text_field( $post['phone_prefix'] ?? '' );
		$phone_number = sanitize_text_field( $post['phone']        ?? '' );
		$phone_full   = trim( $phone_prefix . ' ' . $phone_number );

		// Parse hcqb_selections JSON (serialised by collectSelections() in JS).
		$selections_raw = isset( $post['hcqb_selections'] ) ? wp_unslash( $post['hcqb_selections'] ) : '';
		$selections     = [];
		if ( $selections_raw ) {
			$decoded = json_decode( $selections_raw, true );
			if ( is_array( $decoded ) ) {
				$selections = $decoded;
			}
		}

		// Build question-key → question-label lookup from the global default config
		// so we can store human-readable labels rather than internal keys.
		$config_id = absint( hcqb_get_setting( 'default_config_id' ) );
		$config    = $config_id ? get_post( $config_id ) : null;
		$questions = ( $config && 'hc-quote-configs' === $config->post_type )
			? ( get_post_meta( $config->ID, 'hcqb_questions', true ) ?: [] )
			: [];
		$q_labels  = [];
		foreach ( $questions as $q ) {
			$q_labels[ $q['key'] ] = $q['label'] ?? $q['key'];
		}

		// Normalise selections to label snapshots (DEVELOPMENT.md §8 + §15.3).
		// Radio/dropdown arrive as single { slug, label, price, priceType }.
		// Checkbox arrives as an indexed array of the same objects.
		$selected_options = [];
		foreach ( $selections as $key => $selection ) {
			$q_label = sanitize_text_field( $q_labels[ $key ] ?? $key );
			$entries = isset( $selection['slug'] )
				? [ $selection ]
				: array_values( (array) $selection );

			foreach ( $entries as $entry ) {
				if ( empty( $entry['slug'] ) ) {
					continue;
				}
				$price_type = in_array(
					$entry['priceType'] ?? 'addition',
					[ 'addition', 'deduction' ],
					true
				) ? $entry['priceType'] : 'addition';

				$selected_options[] = [
					'question_label' => $q_label,
					'option_label'   => sanitize_text_field( $entry['label'] ?? $entry['slug'] ),
					'price'          => (float) ( $entry['price'] ?? 0 ),
					'price_type'     => $price_type,
				];
			}
		}

		// Find question labels whose options carry option_role "base_price" (e.g. Size).
		// These become the starting total — not line items — so remove them from the list.
		$base_price_q_labels = [];
		foreach ( $questions as $q ) {
			foreach ( $q['options'] ?? [] as $opt ) {
				if ( ( $opt['option_role'] ?? '' ) === 'base_price' ) {
					$base_price_q_labels[] = sanitize_text_field( $q['label'] ?? $q['key'] );
					break;
				}
			}
		}

		// Extract base_price from the matching question — keep ALL entries in the list
		// so every question answer is captured in the submission record.
		$base_price   = 0.0;
		$product_name = '';
		foreach ( $selected_options as $entry ) {
			if ( in_array( $entry['question_label'], $base_price_q_labels, true ) ) {
				$base_price   = (float) $entry['price'];
				$product_name = $entry['option_label'];
			}
		}

		// Shipping distance — strip any non-numeric chars (e.g. "342 km" → 342.0).
		$distance_raw = sanitize_text_field( $post['shipping_distance'] ?? '' );
		$distance_km  = (float) preg_replace( '/[^\d.]/', '', $distance_raw );

		return [
			'prefix'               => sanitize_text_field( $post['prefix']           ?? '' ),
			'first_name'           => sanitize_text_field( $post['first_name']       ?? '' ),
			'last_name'            => sanitize_text_field( $post['last_name']        ?? '' ),
			'email'                => sanitize_email(      $post['email']            ?? '' ),
			'phone'                => $phone_full,
			'street'               => sanitize_text_field( $post['street']           ?? '' ),
			'suburb'               => sanitize_text_field( $post['suburb']           ?? '' ),
			'state'                => sanitize_text_field( $post['state']            ?? '' ),
			'postcode'             => sanitize_text_field( $post['postcode']         ?? '' ),
			'lat'                  => sanitize_text_field( $post['lat']              ?? '' ),
			'lng'                  => sanitize_text_field( $post['lng']              ?? '' ),
			'shipping_distance_km' => $distance_km,
			'total_price'          => (float) ( $post['total_price']                ?? 0 ),
			'base_price'           => $base_price,
			'product_name'         => $product_name,
			'selected_options'     => $selected_options,
		];
	}

	// -------------------------------------------------------------------------
	// Meta persistence — all §15.3 keys
	// -------------------------------------------------------------------------

	public static function save_meta( int $post_id, array $data ): void {
		$base_price = $data['base_price'] ?? 0.0;

		// First status key from settings (stable internal key, never a label).
		$status_labels = (array) ( hcqb_get_setting( 'submission_status_labels' ) ?: [] );
		$first_key     = ! empty( $status_labels[0]['key'] ) ? $status_labels[0]['key'] : 'status_1';

		update_post_meta( $post_id, 'hcqb_product_name',          $data['product_name'] ?? '' );
		update_post_meta( $post_id, 'hcqb_base_price',           $base_price );
		update_post_meta( $post_id, 'hcqb_selected_options',     $data['selected_options'] );
		update_post_meta( $post_id, 'hcqb_total_price',          $data['total_price'] );
		update_post_meta( $post_id, 'hcqb_prefix',               $data['prefix'] );
		update_post_meta( $post_id, 'hcqb_first_name',           $data['first_name'] );
		update_post_meta( $post_id, 'hcqb_last_name',            $data['last_name'] );
		update_post_meta( $post_id, 'hcqb_email',                $data['email'] );
		update_post_meta( $post_id, 'hcqb_phone',                $data['phone'] );
		update_post_meta( $post_id, 'hcqb_address_street',       $data['street'] );
		update_post_meta( $post_id, 'hcqb_address_city',         $data['suburb'] );
		update_post_meta( $post_id, 'hcqb_address_state',        $data['state'] );
		update_post_meta( $post_id, 'hcqb_address_postcode',     $data['postcode'] );
		update_post_meta( $post_id, 'hcqb_shipping_distance_km', $data['shipping_distance_km'] );
		update_post_meta( $post_id, 'hcqb_submission_status',    $first_key );
		// ISO 8601 UTC timestamp — gmdate() always returns UTC.
		update_post_meta( $post_id, 'hcqb_submitted_at',         gmdate( 'Y-m-d\TH:i:s\Z' ) );
	}

	// -------------------------------------------------------------------------
	// Helper — single-line address from submission data
	// Used by email templates and anywhere a formatted address is needed.
	// -------------------------------------------------------------------------

	public static function format_address( array $data ): string {
		return implode( ', ', array_filter( [
			$data['street'],
			$data['suburb'],
			$data['state'],
			$data['postcode'],
		] ) );
	}
}
