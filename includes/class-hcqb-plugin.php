<?php
/**
 * HCQB_Plugin
 *
 * Singleton loader and central hook registration orchestrator.
 * Every add_action() and add_filter() call for the plugin passes through
 * this class. Each stage of development adds its hooks here.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HCQB_Plugin {

	private static ?HCQB_Plugin $instance = null;

	// -------------------------------------------------------------------------
	// Singleton
	// -------------------------------------------------------------------------

	public static function get_instance(): HCQB_Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->load_dependencies();
		$this->register_hooks();
	}

	// -------------------------------------------------------------------------
	// Dependencies
	// Require additional class files as each stage is built.
	// -------------------------------------------------------------------------

	private function load_dependencies(): void {
		// Stage 2 — Settings
		require_once HCQB_PLUGIN_DIR . 'includes/class-hcqb-settings.php';

		// Stage 3–4 — Admin classes (loaded only in admin context)
		if ( is_admin() ) {
			require_once HCQB_PLUGIN_DIR . 'admin/class-hcqb-admin-assets.php';
			require_once HCQB_PLUGIN_DIR . 'admin/class-hcqb-metabox-container.php';
			// require_once HCQB_PLUGIN_DIR . 'admin/class-hcqb-metabox-config.php';
		}

		// Stage 6–7 — Shortcodes
		// require_once HCQB_PLUGIN_DIR . 'includes/class-hcqb-shortcodes.php';

		// Stage 9 — Submission + Email
		// require_once HCQB_PLUGIN_DIR . 'includes/class-hcqb-ajax.php';
		// require_once HCQB_PLUGIN_DIR . 'includes/class-hcqb-submission.php';
		// require_once HCQB_PLUGIN_DIR . 'includes/class-hcqb-email.php';

		// Stage 10 — Submissions admin view
		if ( is_admin() ) {
			// require_once HCQB_PLUGIN_DIR . 'admin/class-hcqb-metabox-submission.php';
			// require_once HCQB_PLUGIN_DIR . 'admin/class-hcqb-list-table-submissions.php';
		}
	}

	// -------------------------------------------------------------------------
	// Hook Registration
	// Add hooks here as each stage is built. Group by stage for clarity.
	// -------------------------------------------------------------------------

	private function register_hooks(): void {

		// --- Stage 1 — CPT Registration ---
		add_action( 'init', [ 'HCQB_Post_Types', 'register' ], 10 );

		// --- Stage 2 — Settings ---
		add_action( 'admin_menu',            [ HCQB_Settings::class, 'register_settings_page' ] );
		add_action( 'admin_init',            [ HCQB_Settings::class, 'register_setting'       ] );
		add_action( 'admin_enqueue_scripts', [ HCQB_Settings::class, 'enqueue_assets'         ] );

		// --- Stage 3 — Container Meta Box ---
		add_action( 'add_meta_boxes',        [ HCQB_Metabox_Container::class, 'register'              ] );
		add_action( 'save_post',             [ HCQB_Metabox_Container::class, 'save'                  ] );
		add_action( 'admin_enqueue_scripts', [ HCQB_Admin_Assets::class,      'enqueue_for_container' ] );

		// --- Stage 4 — Config Admin Screen ---
		// add_action( 'add_meta_boxes',        [ HCQB_Metabox_Config::class, 'register'         ] );
		// add_action( 'save_post',             [ HCQB_Metabox_Config::class, 'save'              ] );
		// add_action( 'admin_action_hcqb_duplicate_config', [ HCQB_Metabox_Config::class, 'duplicate' ] );
		// add_action( 'admin_enqueue_scripts', [ HCQB_Admin_Assets::class,   'enqueue_for_config' ] );

		// --- Stage 5 — Product Page Template ---
		// add_filter( 'template_include', [ $this, 'override_container_template' ] );

		// --- Stage 6 — Shortcodes ---
		// add_action( 'init', [ HCQB_Shortcodes::class, 'register' ], 20 );

		// --- Stage 8 — Admin Assets (settings screen) ---
		// add_action( 'admin_enqueue_scripts', [ HCQB_Admin_Assets::class, 'enqueue_for_settings' ] );

		// --- Stage 9 — AJAX Submission ---
		// add_action( 'wp_ajax_hcqb_submit_quote',        [ HCQB_Ajax::class, 'handle_submission'   ] );
		// add_action( 'wp_ajax_nopriv_hcqb_submit_quote', [ HCQB_Ajax::class, 'handle_submission'   ] );

		// --- Stage 10 — Submissions Admin ---
		// add_action( 'add_meta_boxes',        [ HCQB_Metabox_Submission::class, 'register'           ] );
		// add_action( 'admin_enqueue_scripts', [ HCQB_Admin_Assets::class,       'enqueue_for_submissions' ] );
		// add_action( 'wp_ajax_hcqb_update_submission_status', [ HCQB_Ajax::class, 'handle_update_status' ] );
		// add_filter( 'manage_hc-quote-submissions_posts_columns',        [ HCQB_List_Table_Submissions::class, 'columns'        ] );
		// add_action( 'manage_hc-quote-submissions_posts_custom_column',  [ HCQB_List_Table_Submissions::class, 'column_content' ], 10, 2 );
		// add_action( 'restrict_manage_posts', [ HCQB_List_Table_Submissions::class, 'status_filter'  ] );
		// add_action( 'pre_get_posts',         [ HCQB_List_Table_Submissions::class, 'apply_filter'   ] );
	}

	// -------------------------------------------------------------------------
	// Template override (uncommented in Stage 5)
	// -------------------------------------------------------------------------

	// public function override_container_template( string $template ): string {
	// 	if ( is_singular( 'hc-containers' ) ) {
	// 		return HCQB_PLUGIN_DIR . 'templates/single-hc-containers.php';
	// 	}
	// 	return $template;
	// }
}
