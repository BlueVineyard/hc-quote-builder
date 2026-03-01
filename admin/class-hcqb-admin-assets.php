<?php
/**
 * HCQB_Admin_Assets
 *
 * Conditional asset enqueueing for all HCQB admin screens.
 * Each enqueue_for_* method guards on the specific hook and post type —
 * scripts and styles are never loaded globally across all admin pages.
 *
 * Methods activated per stage:
 *   enqueue_for_container()   — Stage 3 (hc-containers edit screen)
 *   enqueue_for_config()      — Stage 4 (hc-quote-configs edit screen)    [stub]
 *   enqueue_for_submissions() — Stage 10 (submissions list + detail view) [stub]
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HCQB_Admin_Assets {

	// -------------------------------------------------------------------------
	// Stage 3 — hc-containers edit screen
	// -------------------------------------------------------------------------

	public static function enqueue_for_container( string $hook ): void {
		if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) {
			return;
		}
		if ( 'hc-containers' !== get_post_type() ) {
			return;
		}

		// wp_enqueue_media() is required for the wp.media gallery and icon uploader.
		wp_enqueue_media();

		wp_enqueue_style(
			'hcqb-admin-global',
			HCQB_PLUGIN_URL . 'assets/css/admin/hcqb-admin-global.css',
			[],
			HCQB_VERSION
		);

		wp_enqueue_script(
			'hcqb-admin-container',
			HCQB_PLUGIN_URL . 'assets/js/admin/hcqb-admin-container.js',
			[],
			HCQB_VERSION,
			true
		);
	}

	// -------------------------------------------------------------------------
	// Stage 4 — hc-quote-configs edit screen
	// -------------------------------------------------------------------------

	public static function enqueue_for_config( string $hook ): void {
		if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) {
			return;
		}
		if ( 'hc-quote-configs' !== get_post_type() ) {
			return;
		}

		// wp.media is needed for the image rule image picker.
		wp_enqueue_media();

		wp_enqueue_style(
			'hcqb-admin-global',
			HCQB_PLUGIN_URL . 'assets/css/admin/hcqb-admin-global.css',
			[],
			HCQB_VERSION
		);

		wp_enqueue_style(
			'hcqb-admin-config',
			HCQB_PLUGIN_URL . 'assets/css/admin/hcqb-admin-config.css',
			[ 'hcqb-admin-global' ],
			HCQB_VERSION
		);

		wp_enqueue_script(
			'hcqb-admin-repeater',
			HCQB_PLUGIN_URL . 'assets/js/admin/hcqb-admin-repeater.js',
			[],
			HCQB_VERSION,
			true
		);

		wp_enqueue_script(
			'hcqb-admin-config',
			HCQB_PLUGIN_URL . 'assets/js/admin/hcqb-admin-config.js',
			[ 'hcqb-admin-repeater' ],
			HCQB_VERSION,
			true
		);
	}

	// -------------------------------------------------------------------------
	// Stage 10 — Submissions list + detail view
	// -------------------------------------------------------------------------

	public static function enqueue_for_submissions( string $hook ): void {
		$is_list   = 'edit.php' === $hook;
		$is_detail = ( 'post.php' === $hook || 'post-new.php' === $hook );

		if ( ! $is_list && ! $is_detail ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || 'hc-quote-submissions' !== $screen->post_type ) {
			return;
		}

		wp_enqueue_style(
			'hcqb-admin-global',
			HCQB_PLUGIN_URL . 'assets/css/admin/hcqb-admin-global.css',
			[],
			HCQB_VERSION
		);

		wp_enqueue_style(
			'hcqb-admin-submissions',
			HCQB_PLUGIN_URL . 'assets/css/admin/hcqb-admin-submissions.css',
			[ 'hcqb-admin-global' ],
			HCQB_VERSION
		);

		// JS + localized data for the status AJAX save — detail view only.
		if ( $is_detail ) {
			wp_enqueue_script(
				'hcqb-admin-submissions',
				HCQB_PLUGIN_URL . 'assets/js/admin/hcqb-admin-submissions.js',
				[],
				HCQB_VERSION,
				true
			);
			wp_localize_script( 'hcqb-admin-submissions', 'HCQBSubmissions', [
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'hcqb_update_status' ),
			] );
		}
	}
}
