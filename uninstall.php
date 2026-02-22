<?php
/**
 * HC Quote Builder — Uninstall
 *
 * Runs when the plugin is deleted (not just deactivated) from the
 * WordPress admin. Removes all plugin data: options, post meta, and
 * all posts belonging to the three plugin CPTs.
 *
 * WordPress calls this file automatically when the plugin is deleted.
 * The WP_UNINSTALL_PLUGIN constant is defined by WordPress before calling
 * this file — if it is not set, we exit immediately as a safety guard.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// -------------------------------------------------------------------------
// Delete plugin option
// -------------------------------------------------------------------------

delete_option( 'hcqb_settings' );
delete_option( 'hcqb_last_email_error' );

// -------------------------------------------------------------------------
// Delete all posts and their meta for each plugin CPT
// -------------------------------------------------------------------------

$post_types = [
	'hc-containers',
	'hc-quote-configs',
	'hc-quote-submissions',
];

foreach ( $post_types as $post_type ) {
	$posts = get_posts( [
		'post_type'      => $post_type,
		'post_status'    => 'any',
		'numberposts'    => -1,
		'fields'         => 'ids',
		'no_found_rows'  => true,
	] );

	foreach ( $posts as $post_id ) {
		// wp_delete_post with true forces deletion (bypasses trash)
		// and removes all associated post meta automatically.
		wp_delete_post( $post_id, true );
	}
}

// -------------------------------------------------------------------------
// Flush rewrite rules so /portables/ slug is removed
// -------------------------------------------------------------------------

flush_rewrite_rules();
