<?php
/**
 * Plugin Name:  HC Quote Builder
 * Description:  Custom quote builder for HC Containers — portable building product configuration, live pricing, and quote submission.
 * Version:      1.6.0
 * Author:       Blue Vineyard
 * License:      GPL-2.0-or-later
 * Text Domain:  hc-quote-builder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// -------------------------------------------------------------------------
// Constants
// -------------------------------------------------------------------------

define( 'HCQB_VERSION',    '1.6.0' );
define( 'HCQB_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'HCQB_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// -------------------------------------------------------------------------
// Autoload core classes
// -------------------------------------------------------------------------

require_once HCQB_PLUGIN_DIR . 'includes/class-hcqb-helpers.php';
require_once HCQB_PLUGIN_DIR . 'includes/class-hcqb-post-types.php';
require_once HCQB_PLUGIN_DIR . 'includes/class-hcqb-plugin.php';

// -------------------------------------------------------------------------
// Activation / Deactivation hooks
// -------------------------------------------------------------------------

register_activation_hook( __FILE__, function () {
	HCQB_Post_Types::register();
	flush_rewrite_rules();
} );

register_deactivation_hook( __FILE__, function () {
	flush_rewrite_rules();
} );

// -------------------------------------------------------------------------
// Boot
// -------------------------------------------------------------------------

HCQB_Plugin::get_instance();
