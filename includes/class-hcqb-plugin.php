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
			require_once HCQB_PLUGIN_DIR . 'admin/class-hcqb-metabox-config.php';
		}

		// Stage 5.1 / 7 — Shortcodes (field shortcodes + quote builder)
		require_once HCQB_PLUGIN_DIR . 'includes/class-hcqb-shortcodes.php';

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
		// --- Stage 4 — Config Admin Screen ---
		// Admin classes are only loaded in admin context (is_admin()); REST API
		// requests from the block editor are NOT admin context, so all hooks that
		// reference these classes must also be guarded here to prevent fatal errors
		// when save_post fires during a REST API save.
		if ( is_admin() ) {
			add_action( 'add_meta_boxes',        [ HCQB_Metabox_Container::class, 'register'              ] );
			add_action( 'save_post',             [ HCQB_Metabox_Container::class, 'save'                  ] );
			add_action( 'admin_enqueue_scripts', [ HCQB_Admin_Assets::class,      'enqueue_for_container' ] );

			add_action( 'add_meta_boxes',        [ HCQB_Metabox_Config::class, 'register'          ] );
			add_action( 'save_post',             [ HCQB_Metabox_Config::class, 'save'               ] );
			add_action( 'admin_action_hcqb_duplicate_config', [ HCQB_Metabox_Config::class, 'duplicate' ] );
			add_action( 'admin_enqueue_scripts', [ HCQB_Admin_Assets::class,   'enqueue_for_config' ] );
			add_filter( 'post_row_actions',      [ HCQB_Metabox_Config::class, 'post_row_actions'  ], 10, 2 );
		}

		// --- Stage 5 — Product Page Template ---
		add_filter( 'template_include',   [ $this, 'override_container_template' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_product_page_assets' ] );

		// --- Stage 5.1 / 6 — Shortcodes + Grid CSS ---
		add_action( 'init',               [ HCQB_Shortcodes::class, 'register' ], 20 );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_grid_assets' ] );

		// --- Stage 7 — Quote Builder Frame 1 ---
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_quote_builder_assets' ] );

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
	// Stage 6 — Grid shortcode CSS
	// Enqueued only on pages that contain either grid shortcode.
	// -------------------------------------------------------------------------

	public function enqueue_grid_assets(): void {
		global $post;
		if ( ! $post instanceof WP_Post ) {
			return;
		}
		if (
			! has_shortcode( $post->post_content, 'hc_product_grid' ) &&
			! has_shortcode( $post->post_content, 'hc_lease_grid' )
		) {
			return;
		}
		wp_enqueue_style(
			'hcqb-grids',
			HCQB_PLUGIN_URL . 'assets/css/frontend/hcqb-grids.css',
			[],
			HCQB_VERSION
		);
	}

	// -------------------------------------------------------------------------
	// Stage 7 — Quote Builder assets
	// Enqueued only on pages that contain the [hc_quote_builder] shortcode.
	// Scripts loaded in footer; HCQBConfig inline script is output in the
	// page body (shortcode), which executes before footer scripts.
	// -------------------------------------------------------------------------

	public function enqueue_quote_builder_assets(): void {
		global $post;
		if ( ! $post instanceof WP_Post ) {
			return;
		}

		// Primary: match the page explicitly configured in plugin settings.
		// Fallback: scan post content for the shortcode tag.
		$page_id          = (int) hcqb_get_setting( 'quote_builder_page_id' );
		$on_builder_page  = ( $page_id && is_page( $page_id ) )
			|| has_shortcode( $post->post_content, 'hc_quote_builder' );

		if ( ! $on_builder_page ) {
			return;
		}

		wp_enqueue_style(
			'hcqb-quote-builder',
			HCQB_PLUGIN_URL . 'assets/css/frontend/hcqb-quote-builder.css',
			[],
			HCQB_VERSION
		);

		// Sub-modules registered first so quote-builder.js can declare them as deps.
		wp_enqueue_script(
			'hcqb-pricing',
			HCQB_PLUGIN_URL . 'assets/js/frontend/hcqb-pricing.js',
			[],
			HCQB_VERSION,
			true
		);
		wp_enqueue_script(
			'hcqb-image-switcher',
			HCQB_PLUGIN_URL . 'assets/js/frontend/hcqb-image-switcher.js',
			[],
			HCQB_VERSION,
			true
		);
		wp_enqueue_script(
			'hcqb-conditionals',
			HCQB_PLUGIN_URL . 'assets/js/frontend/hcqb-conditionals.js',
			[],
			HCQB_VERSION,
			true
		);
		wp_enqueue_script(
			'hcqb-feature-pills',
			HCQB_PLUGIN_URL . 'assets/js/frontend/hcqb-feature-pills.js',
			[],
			HCQB_VERSION,
			true
		);
		wp_enqueue_script(
			'hcqb-quote-builder',
			HCQB_PLUGIN_URL . 'assets/js/frontend/hcqb-quote-builder.js',
			[ 'hcqb-pricing', 'hcqb-image-switcher', 'hcqb-conditionals', 'hcqb-feature-pills' ],
			HCQB_VERSION,
			true
		);
	}

	// -------------------------------------------------------------------------
	// Stage 5 — Product page template + CSS
	// -------------------------------------------------------------------------

	public function override_container_template( string $template ): string {
		if ( is_singular( 'hc-containers' ) ) {
			return HCQB_PLUGIN_DIR . 'templates/single-hc-containers.php';
		}
		return $template;
	}

	public function enqueue_product_page_assets(): void {
		if ( ! is_singular( 'hc-containers' ) ) {
			return;
		}
		wp_enqueue_style(
			'hcqb-product-page',
			HCQB_PLUGIN_URL . 'assets/css/frontend/hcqb-product-page.css',
			[],
			HCQB_VERSION
		);
	}
}
