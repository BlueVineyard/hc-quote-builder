<?php
/**
 * HCQB_Email
 *
 * Email dispatch for quote submissions: admin notification and customer copy.
 *
 * Both emails are plain text (Content-Type: text/plain).
 * Subject tokens {product_name} and {customer_name} are replaced before dispatch.
 * From address and name are read from plugin settings (Tab 2 — Email).
 *
 * Failures are signalled by returning false — the caller (HCQB_Ajax) logs them
 * via update_option('hcqb_last_email_error') and proceeds without blocking.
 *
 * @package HC_Quote_Builder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HCQB_Email {

	// -------------------------------------------------------------------------
	// Admin notification
	// -------------------------------------------------------------------------

	public static function send_admin_notification( int $post_id, array $data, int $product_id ): bool {
		$to           = hcqb_get_setting( 'admin_email' ) ?: get_option( 'admin_email', '' );
		$from_name    = hcqb_get_setting( 'from_name' )   ?: get_option( 'blogname', '' );
		$from_email   = hcqb_get_setting( 'from_email' )  ?: get_option( 'admin_email', '' );
		$product_name  = get_the_title( $product_id );
		$customer_name = self::customer_name( $data );

		$raw_subject = hcqb_get_setting( 'admin_email_subject' ) ?: 'New Quote Request — {product_name}';
		$subject     = self::replace_tokens( $raw_subject, $product_name, $customer_name );

		$headers = self::build_headers( $from_name, $from_email );
		$body    = self::build_admin_body( $post_id, $data, $product_id, $product_name, $customer_name );

		return (bool) wp_mail( $to, $subject, $body, $headers );
	}

	// -------------------------------------------------------------------------
	// Customer copy
	// -------------------------------------------------------------------------

	public static function send_customer_copy( int $post_id, array $data, int $product_id ): bool {
		$to           = $data['email'];
		$from_name    = hcqb_get_setting( 'from_name' )  ?: get_option( 'blogname', '' );
		$from_email   = hcqb_get_setting( 'from_email' ) ?: get_option( 'admin_email', '' );
		$product_name  = get_the_title( $product_id );
		$customer_name = self::customer_name( $data );

		$raw_subject = hcqb_get_setting( 'customer_email_subject' ) ?: 'Your Quote Request — {product_name}';
		$subject     = self::replace_tokens( $raw_subject, $product_name, $customer_name );

		$headers = self::build_headers( $from_name, $from_email );
		$body    = self::build_customer_body( $post_id, $data, $product_id, $product_name, $customer_name );

		return (bool) wp_mail( $to, $subject, $body, $headers );
	}

	// -------------------------------------------------------------------------
	// Internal helpers
	// -------------------------------------------------------------------------

	private static function customer_name( array $data ): string {
		return trim( $data['prefix'] . ' ' . $data['first_name'] . ' ' . $data['last_name'] );
	}

	private static function replace_tokens( string $subject, string $product_name, string $customer_name ): string {
		return str_replace(
			[ '{product_name}', '{customer_name}' ],
			[ $product_name,    $customer_name    ],
			$subject
		);
	}

	private static function build_headers( string $from_name, string $from_email ): array {
		return [
			'Content-Type: text/plain; charset=UTF-8',
			'From: ' . $from_name . ' <' . $from_email . '>',
		];
	}

	private static function build_admin_body(
		int $post_id, array $data, int $product_id, string $product_name, string $customer_name
	): string {
		ob_start();
		include HCQB_PLUGIN_DIR . 'templates/emails/admin-notification.php';
		return ob_get_clean();
	}

	private static function build_customer_body(
		int $post_id, array $data, int $product_id, string $product_name, string $customer_name
	): string {
		ob_start();
		include HCQB_PLUGIN_DIR . 'templates/emails/customer-copy.php';
		return ob_get_clean();
	}
}
