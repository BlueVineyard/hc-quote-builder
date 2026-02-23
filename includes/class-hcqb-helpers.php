<?php
/**
 * HCQB_Helpers
 *
 * Shared utility functions used across the plugin.
 * All functions are prefixed hcqb_ and available globally after this file loads.
 *
 * Functions:
 *   hcqb_get_setting()                   — Retrieve a value from hcqb_settings
 *   hcqb_generate_slug()                 — Convert a string to an internal slug
 *   hcqb_format_price()                  — Format a float as a display price string
 *   hcqb_render_stars()                  — Render 5-star rating HTML
 *   hcqb_get_active_config_for_product() — Fetch the active quote config for a product
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// -------------------------------------------------------------------------
// Settings retrieval
// -------------------------------------------------------------------------

/**
 * Retrieve a single value from the hcqb_settings option.
 *
 * @param string $key     The setting key.
 * @param mixed  $default Fallback value if the key does not exist.
 * @return mixed
 */
function hcqb_get_setting( string $key, mixed $default = '' ): mixed {
	static $settings = null;

	if ( null === $settings ) {
		$settings = get_option( 'hcqb_settings', [] );
		if ( ! is_array( $settings ) ) {
			$settings = [];
		}
	}

	return $settings[ $key ] ?? $default;
}

// -------------------------------------------------------------------------
// Slug generation
// -------------------------------------------------------------------------

/**
 * Convert a human-readable string to an internal slug.
 *
 * Uses sanitize_title() which handles Unicode, punctuation, and whitespace.
 * Underscores are used as separators (not hyphens) to match the architecture
 * convention for question keys and option slugs.
 *
 * Example:
 *   'Do you require Air Conditioning?' → 'do_you_require_air_conditioning'
 *
 * @param string $string The source string (e.g. a question or option label).
 * @return string
 */
function hcqb_generate_slug( string $string ): string {
	// sanitize_title produces hyphen-separated slugs; swap to underscores.
	return str_replace( '-', '_', sanitize_title( $string ) );
}

// -------------------------------------------------------------------------
// Price formatting
// -------------------------------------------------------------------------

/**
 * Format a float as a human-readable price string.
 *
 * Examples:
 *   7500.0  → '$7,500.00'
 *   850.5   → '$850.50'
 *   0.0     → '$0.00'
 *
 * @param float $price The raw price value.
 * @return string
 */
function hcqb_format_price( float $price ): string {
	return '$' . number_format( $price, 2 );
}

// -------------------------------------------------------------------------
// Star rating
// -------------------------------------------------------------------------

/**
 * Render an accessible 5-star rating as a string of HTML spans.
 *
 * Each <span> carries a BEM modifier class:
 *   .hcqb-star--full  — whole star   (rating ≥ i)
 *   .hcqb-star--half  — half star    (rating ≥ i − 0.5)
 *   .hcqb-star--empty — empty star
 *
 * The CSS uses colour to distinguish the states; aria-hidden keeps the
 * decorative stars out of the accessibility tree (the parent element
 * should carry the label, e.g. aria-label="4.5 out of 5 stars").
 *
 * @param float $rating  A value from 0.0 to 5.0, supports 0.5 steps.
 * @return string  HTML string — safe to echo directly.
 */
function hcqb_render_stars( float $rating ): string {
	$rating = max( 0.0, min( 5.0, $rating ) );
	$html   = '';

	for ( $i = 1; $i <= 5; $i++ ) {
		if ( $rating >= $i ) {
			$modifier = 'full';
		} elseif ( $rating >= $i - 0.5 ) {
			$modifier = 'half';
		} else {
			$modifier = 'empty';
		}
		$html .= '<span class="hcqb-star hcqb-star--' . $modifier . '" aria-hidden="true">★</span>';
	}

	return $html;
}

// -------------------------------------------------------------------------
// Config lookup
// -------------------------------------------------------------------------

/**
 * Fetch the active hc-quote-configs post linked to a given product ID.
 *
 * Returns null if no active config exists for the product — callers should
 * always check for null before using the return value.
 *
 * @param int $product_id The hc-containers post ID.
 * @return WP_Post|null
 */
function hcqb_get_active_config_for_product( int $product_id ): ?WP_Post {
	if ( ! $product_id ) {
		return null;
	}

	$configs = get_posts( [
		'post_type'      => 'hc-quote-configs',
		'post_status'    => 'publish',
		'numberposts'    => 1,
		'fields'         => 'all',
		'no_found_rows'  => true,
		'meta_query'     => [
			'relation' => 'AND',
			[
				'key'   => 'hcqb_linked_product',
				'value' => $product_id,
			],
			[
				'key'   => 'hcqb_config_status',
				'value' => 'active',
			],
		],
	] );

	return $configs[0] ?? null;
}
