<?php
/**
 * HCQB_Post_Types
 *
 * Registers all three custom post types and the product taxonomy.
 * Called on the 'init' hook (priority 10) and directly on plugin activation
 * so rewrite rules flush correctly.
 *
 * CPTs registered:
 *   hc-containers        — Products (public, rewrite: /portables/)
 *   hc-quote-configs     — Quote configurations (private, admin menu)
 *   hc-quote-submissions — Customer submissions (private, admin menu)
 *
 * Taxonomy registered:
 *   hc-container-category — Hierarchical, attached to hc-containers
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HCQB_Post_Types {

	public static function register(): void {
		self::register_containers();
		self::register_quote_configs();
		self::register_quote_submissions();
		self::register_container_category();
	}

	// -------------------------------------------------------------------------
	// hc-containers — Products
	// Architecture ref: §3.1
	// -------------------------------------------------------------------------

	private static function register_containers(): void {
		$labels = [
			'name'               => 'Containers',
			'singular_name'      => 'Container',
			'add_new'            => 'Add New',
			'add_new_item'       => 'Add New Container',
			'edit_item'          => 'Edit Container',
			'new_item'           => 'New Container',
			'view_item'          => 'View Container',
			'search_items'       => 'Search Containers',
			'not_found'          => 'No containers found',
			'not_found_in_trash' => 'No containers found in trash',
			'all_items'          => 'All Containers',
			'menu_name'          => 'Containers',
		];

		register_post_type( 'hc-containers', [
			'labels'       => $labels,
			'public'       => true,
			'has_archive'  => false,
			'show_ui'      => true,
			'show_in_menu' => true,
			'menu_icon'    => 'dashicons-building',
			'supports'     => [ 'title', 'thumbnail', 'editor' ],
			'rewrite'      => [ 'slug' => 'portables', 'with_front' => false ],
			'show_in_rest' => false,
		] );
	}

	// -------------------------------------------------------------------------
	// hc-quote-configs — Quote Configurations
	// Architecture ref: §3.2
	// -------------------------------------------------------------------------

	private static function register_quote_configs(): void {
		$labels = [
			'name'               => 'Quote Builder',
			'singular_name'      => 'Quote Config',
			'add_new'            => 'Add New',
			'add_new_item'       => 'Add New Quote Config',
			'edit_item'          => 'Edit Quote Config',
			'new_item'           => 'New Quote Config',
			'search_items'       => 'Search Quote Configs',
			'not_found'          => 'No quote configs found',
			'not_found_in_trash' => 'No quote configs found in trash',
			'all_items'          => 'All Quote Configs',
			'menu_name'          => 'Quote Builder',
		];

		register_post_type( 'hc-quote-configs', [
			'labels'       => $labels,
			'public'       => false,
			'has_archive'  => false,
			'show_ui'      => true,
			'show_in_menu' => true,
			'menu_icon'    => 'dashicons-calculator',
			'supports'     => [ 'title' ],
			'rewrite'      => false,
			'show_in_rest' => false,
		] );
	}

	// -------------------------------------------------------------------------
	// hc-quote-submissions — Quote Submissions
	// Architecture ref: §3.3
	// -------------------------------------------------------------------------

	private static function register_quote_submissions(): void {
		$labels = [
			'name'               => 'Quote Submissions',
			'singular_name'      => 'Quote Submission',
			'edit_item'          => 'View Submission',
			'new_item'           => 'New Submission',
			'search_items'       => 'Search Submissions',
			'not_found'          => 'No submissions found',
			'not_found_in_trash' => 'No submissions found in trash',
			'all_items'          => 'All Submissions',
			'menu_name'          => 'Quote Submissions',
		];

		register_post_type( 'hc-quote-submissions', [
			'labels'              => $labels,
			'public'              => false,
			'has_archive'         => false,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'menu_icon'           => 'dashicons-email-alt',
			'supports'            => [ 'title' ],
			'rewrite'             => false,
			'show_in_rest'        => false,
			'capabilities'        => [
				'create_posts' => 'do_not_allow', // submissions created only via frontend form
			],
			'map_meta_cap'        => true,
		] );
	}

	// -------------------------------------------------------------------------
	// hc-container-category — Product Taxonomy
	// Architecture ref: §5.1 Tab 1 (Categories field)
	// -------------------------------------------------------------------------

	private static function register_container_category(): void {
		$labels = [
			'name'              => 'Categories',
			'singular_name'     => 'Category',
			'search_items'      => 'Search Categories',
			'all_items'         => 'All Categories',
			'parent_item'       => 'Parent Category',
			'parent_item_colon' => 'Parent Category:',
			'edit_item'         => 'Edit Category',
			'update_item'       => 'Update Category',
			'add_new_item'      => 'Add New Category',
			'new_item_name'     => 'New Category Name',
			'menu_name'         => 'Categories',
		];

		register_taxonomy( 'hc-container-category', [ 'hc-containers' ], [
			'labels'            => $labels,
			'hierarchical'      => true,
			'public'            => true,
			'show_ui'           => true,
			'show_admin_column' => true,
			'show_in_rest'      => false,
			'rewrite'           => [ 'slug' => 'container-category' ],
		] );
	}
}
