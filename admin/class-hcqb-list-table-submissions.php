<?php
/**
 * HCQB_List_Table_Submissions
 *
 * Customises the WordPress posts list table for the hc-quote-submissions CPT.
 *
 * Hooks (registered in HCQB_Plugin):
 *   manage_hc-quote-submissions_posts_columns        → columns()
 *   manage_hc-quote-submissions_posts_custom_column  → column_content()
 *   restrict_manage_posts                            → status_filter()
 *   pre_get_posts                                    → apply_filter()
 *
 * Customer Name is the primary column (first non-cb column) — WordPress
 * automatically attaches row actions to it.
 *
 * @package HC_Quote_Builder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HCQB_List_Table_Submissions {

	// -------------------------------------------------------------------------
	// Columns — replace default WP columns with custom set
	// -------------------------------------------------------------------------

	public static function columns( array $columns ): array {
		return [
			'cb'             => '<input type="checkbox">',
			'hcqb_customer'  => 'Customer',
			'hcqb_email'     => 'Email',
			'hcqb_phone'     => 'Phone',
			'hcqb_product'   => 'Product',
			'hcqb_total'     => 'Total Estimate',
			'hcqb_distance'  => 'Shipping Dist.',
			'hcqb_status'    => 'Status',
			'hcqb_submitted' => 'Submitted',
		];
	}

	// -------------------------------------------------------------------------
	// Column content — renders each custom column cell
	// -------------------------------------------------------------------------

	public static function column_content( string $column, int $post_id ): void {

		switch ( $column ) {

			case 'hcqb_customer':
				$prefix    = get_post_meta( $post_id, 'hcqb_prefix',     true );
				$first     = get_post_meta( $post_id, 'hcqb_first_name', true );
				$last      = get_post_meta( $post_id, 'hcqb_last_name',  true );
				$name      = trim( $prefix . ' ' . $first . ' ' . $last );
				$edit_link = get_edit_post_link( $post_id );
				echo '<strong><a href="' . esc_url( $edit_link ) . '">'
					. esc_html( $name ) . '</a></strong>';
				break;

			case 'hcqb_email':
				$email = get_post_meta( $post_id, 'hcqb_email', true );
				if ( $email ) {
					echo '<a href="mailto:' . esc_attr( $email ) . '">'
						. esc_html( $email ) . '</a>';
				}
				break;

			case 'hcqb_phone':
				echo esc_html( get_post_meta( $post_id, 'hcqb_phone', true ) );
				break;

			case 'hcqb_product':
				$product_name = get_post_meta( $post_id, 'hcqb_product_name',       true );
				$product_id   = (int) get_post_meta( $post_id, 'hcqb_linked_product_id', true );
				if ( $product_id ) {
					echo '<a href="' . esc_url( get_edit_post_link( $product_id ) ) . '">'
						. esc_html( $product_name ) . '</a>';
				} else {
					echo esc_html( $product_name );
				}
				break;

			case 'hcqb_total':
				$total = (float) get_post_meta( $post_id, 'hcqb_total_price', true );
				echo esc_html( hcqb_format_price( $total ) );
				break;

			case 'hcqb_distance':
				$km = (float) get_post_meta( $post_id, 'hcqb_shipping_distance_km', true );
				if ( $km > 0 ) {
					echo esc_html( number_format( $km, 0 ) . ' km' );
				} else {
					echo '&mdash;';
				}
				break;

			case 'hcqb_status':
				$status_key    = get_post_meta( $post_id, 'hcqb_submission_status', true );
				$status_labels = (array) hcqb_get_setting( 'submission_status_labels', [] );
				$status_label  = 'Unknown';
				foreach ( $status_labels as $s ) {
					if ( isset( $s['key'] ) && $s['key'] === $status_key ) {
						$status_label = $s['label'];
						break;
					}
				}
				echo '<span class="hcqb-status-badge" data-status="' . esc_attr( $status_key ) . '">'
					. esc_html( $status_label ) . '</span>';
				break;

			case 'hcqb_submitted':
				$iso = get_post_meta( $post_id, 'hcqb_submitted_at', true );
				if ( $iso ) {
					$ts = strtotime( $iso );
					echo esc_html( gmdate( 'j M Y', $ts ) );
					echo '<br><span class="hcqb-time">'
						. esc_html( gmdate( 'g:i a', $ts ) ) . ' UTC</span>';
				}
				break;
		}
	}

	// -------------------------------------------------------------------------
	// Status filter — renders a <select> in the list table action area
	// -------------------------------------------------------------------------

	public static function status_filter(): void {
		$screen = get_current_screen();
		if ( ! $screen || 'hc-quote-submissions' !== $screen->post_type ) {
			return;
		}

		$status_labels = (array) hcqb_get_setting( 'submission_status_labels', [] );
		if ( empty( $status_labels ) ) {
			return;
		}

		$current = sanitize_key( $_GET['hcqb_status'] ?? '' );
		echo '<select name="hcqb_status" id="hcqb-status-filter">';
		echo '<option value="">' . esc_html( 'All Statuses' ) . '</option>';
		foreach ( $status_labels as $s ) {
			if ( empty( $s['key'] ) ) {
				continue;
			}
			printf(
				'<option value="%s"%s>%s</option>',
				esc_attr( $s['key'] ),
				selected( $current, $s['key'], false ),
				esc_html( $s['label'] ?? $s['key'] )
			);
		}
		echo '</select>';
	}

	// -------------------------------------------------------------------------
	// Apply filter + default sort (newest first)
	// -------------------------------------------------------------------------

	public static function apply_filter( WP_Query $query ): void {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}
		if ( 'hc-quote-submissions' !== $query->get( 'post_type' ) ) {
			return;
		}

		// Default sort: newest first (unless an explicit orderby is set).
		if ( ! $query->get( 'orderby' ) ) {
			$query->set( 'orderby', 'date' );
			$query->set( 'order',   'DESC' );
		}

		// Status filter — applies meta_query when a status key is selected.
		$status_key = sanitize_key( $_GET['hcqb_status'] ?? '' );
		if ( $status_key ) {
			$query->set( 'meta_query', [
				[
					'key'   => 'hcqb_submission_status',
					'value' => $status_key,
				],
			] );
		}
	}
}
