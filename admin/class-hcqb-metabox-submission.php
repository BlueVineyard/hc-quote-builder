<?php
/**
 * HCQB_Metabox_Submission
 *
 * Read-only detail view for individual hc-quote-submissions posts.
 *
 * Removes all default WordPress meta boxes and replaces them with
 * a single custom meta box containing four panels:
 *   1. Customer Details   — name, email, phone
 *   2. Quote Summary      — product, selected options (with price deltas), total
 *   3. Shipping           — delivery address, estimated distance
 *   4. Submission Info    — timestamp, post ID, editable status with AJAX save
 *
 * Registered via add_meta_boxes at priority 11 (after WP adds its defaults at 10).
 *
 * @package HC_Quote_Builder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HCQB_Metabox_Submission {

	// -------------------------------------------------------------------------
	// Register — remove default boxes, add our single custom box
	// -------------------------------------------------------------------------

	public static function register(): void {
		$screen = 'hc-quote-submissions';

		// Remove default WP meta boxes that are irrelevant for a read-only CPT.
		remove_meta_box( 'submitdiv',    $screen, 'side'   );
		remove_meta_box( 'slugdiv',      $screen, 'normal' );
		remove_meta_box( 'authordiv',    $screen, 'normal' );
		remove_meta_box( 'revisionsdiv', $screen, 'normal' );

		add_meta_box(
			'hcqb-submission-detail',
			'Submission Details',
			[ self::class, 'render' ],
			$screen,
			'normal',
			'high'
		);
	}

	// -------------------------------------------------------------------------
	// Render — four read-only panels + editable status
	// -------------------------------------------------------------------------

	public static function render( WP_Post $post ): void {
		$post_id = $post->ID;

		// Retrieve all stored meta.
		$prefix       = get_post_meta( $post_id, 'hcqb_prefix',               true );
		$first        = get_post_meta( $post_id, 'hcqb_first_name',           true );
		$last         = get_post_meta( $post_id, 'hcqb_last_name',            true );
		$email        = get_post_meta( $post_id, 'hcqb_email',                true );
		$phone        = get_post_meta( $post_id, 'hcqb_phone',                true );
		$product_name = get_post_meta( $post_id, 'hcqb_product_name',         true );
		$product_id   = (int) get_post_meta( $post_id, 'hcqb_linked_product_id', true );
		$base_price   = (float) get_post_meta( $post_id, 'hcqb_base_price',   true );
		$total_price  = (float) get_post_meta( $post_id, 'hcqb_total_price',  true );
		$options      = (array) get_post_meta( $post_id, 'hcqb_selected_options', true );
		$street       = get_post_meta( $post_id, 'hcqb_address_street',       true );
		$suburb       = get_post_meta( $post_id, 'hcqb_address_city',         true );
		$state        = get_post_meta( $post_id, 'hcqb_address_state',        true );
		$postcode     = get_post_meta( $post_id, 'hcqb_address_postcode',     true );
		$distance_km  = (float) get_post_meta( $post_id, 'hcqb_shipping_distance_km', true );
		$status_key   = get_post_meta( $post_id, 'hcqb_submission_status',   true );
		$submitted_at = get_post_meta( $post_id, 'hcqb_submitted_at',         true );

		$status_labels     = (array) hcqb_get_setting( 'submission_status_labels', [] );
		$full_name         = trim( $prefix . ' ' . $first . ' ' . $last );
		$full_address      = implode( ', ', array_filter( [ $street, $suburb, $state, $postcode ] ) );
		$submitted_display = '';
		if ( $submitted_at ) {
			$ts                = strtotime( $submitted_at );
			$submitted_display = gmdate( 'F j, Y \a\t g:i a', $ts ) . ' UTC';
		}
		?>

		<div class="hcqb-submission-panels">

			<?php /* ---------------------------------------------------------- */ ?>
			<?php /* Panel 1 — Customer Details                                  */ ?>
			<?php /* ---------------------------------------------------------- */ ?>
			<div class="hcqb-sub-panel">
				<h3 class="hcqb-sub-panel__title">Customer Details</h3>
				<table class="hcqb-sub-table">
					<tr>
						<th>Name</th>
						<td><?php echo esc_html( $full_name ); ?></td>
					</tr>
					<tr>
						<th>Email</th>
						<td>
							<?php if ( $email ) : ?>
								<a href="mailto:<?php echo esc_attr( $email ); ?>"><?php echo esc_html( $email ); ?></a>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th>Phone</th>
						<td><?php echo esc_html( $phone ); ?></td>
					</tr>
				</table>
			</div>

			<?php /* ---------------------------------------------------------- */ ?>
			<?php /* Panel 2 — Quote Summary                                     */ ?>
			<?php /* ---------------------------------------------------------- */ ?>
			<div class="hcqb-sub-panel">
				<h3 class="hcqb-sub-panel__title">Quote Summary</h3>
				<table class="hcqb-sub-table">
					<tr>
						<th>Product</th>
						<td>
							<?php if ( $product_id ) : ?>
								<a href="<?php echo esc_url( get_edit_post_link( $product_id ) ); ?>"><?php echo esc_html( $product_name ); ?></a>
							<?php else : ?>
								<?php echo esc_html( $product_name ); ?>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th>Base Price</th>
						<td><?php echo esc_html( hcqb_format_price( $base_price ) ); ?></td>
					</tr>
				</table>

				<?php if ( ! empty( $options ) ) : ?>
					<h4 class="hcqb-sub-panel__subtitle">Selected Options</h4>
					<table class="hcqb-sub-table hcqb-sub-table--options">
						<?php foreach ( $options as $opt ) :
							$sign      = 'deduction' === ( $opt['price_type'] ?? '' ) ? '−' : '+';
							$price_str = ( isset( $opt['price'] ) && $opt['price'] > 0 )
								? $sign . hcqb_format_price( $opt['price'] )
								: '';
						?>
							<tr>
								<th><?php echo esc_html( $opt['question_label'] ?? '' ); ?></th>
								<td>
									<?php echo esc_html( $opt['option_label'] ?? '' ); ?>
									<?php if ( $price_str ) : ?>
										<span class="hcqb-sub-price"><?php echo esc_html( $price_str ); ?></span>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</table>
				<?php endif; ?>

				<div class="hcqb-sub-total">
					<strong>Total Estimate:</strong>
					<span class="hcqb-sub-total__price"><?php echo esc_html( hcqb_format_price( $total_price ) ); ?></span>
				</div>
			</div>

			<?php /* ---------------------------------------------------------- */ ?>
			<?php /* Panel 3 — Shipping                                          */ ?>
			<?php /* ---------------------------------------------------------- */ ?>
			<div class="hcqb-sub-panel">
				<h3 class="hcqb-sub-panel__title">Shipping</h3>
				<table class="hcqb-sub-table">
					<tr>
						<th>Delivery Address</th>
						<td><?php echo esc_html( $full_address ?: '—' ); ?></td>
					</tr>
					<?php if ( $distance_km > 0 ) : ?>
						<tr>
							<th>Est. Distance</th>
							<td><?php echo esc_html( number_format( $distance_km, 0 ) . ' km' ); ?></td>
						</tr>
					<?php endif; ?>
				</table>
			</div>

			<?php /* ---------------------------------------------------------- */ ?>
			<?php /* Panel 4 — Submission Info + Status                          */ ?>
			<?php /* ---------------------------------------------------------- */ ?>
			<div class="hcqb-sub-panel">
				<h3 class="hcqb-sub-panel__title">Submission Info</h3>
				<table class="hcqb-sub-table">
					<tr>
						<th>Submitted</th>
						<td><?php echo esc_html( $submitted_display ?: '—' ); ?></td>
					</tr>
					<tr>
						<th>Submission ID</th>
						<td>#<?php echo esc_html( $post_id ); ?></td>
					</tr>
				</table>

				<?php if ( ! empty( $status_labels ) ) :
					// Resolve current status label for the badge.
					$current_label = 'Unknown';
					foreach ( $status_labels as $s ) {
						if ( isset( $s['key'] ) && $s['key'] === $status_key ) {
							$current_label = $s['label'];
							break;
						}
					}
				?>
					<div class="hcqb-sub-status">
						<div class="hcqb-sub-status__label">
							<strong>Status</strong>
							<span class="hcqb-status-badge"
								data-status="<?php echo esc_attr( $status_key ); ?>"
								id="hcqb-status-badge">
								<?php echo esc_html( $current_label ); ?>
							</span>
						</div>
						<div class="hcqb-sub-status__controls">
							<select id="hcqb-status-select" class="hcqb-sub-status__select">
								<?php foreach ( $status_labels as $s ) :
									if ( empty( $s['key'] ) ) { continue; }
								?>
									<option value="<?php echo esc_attr( $s['key'] ); ?>"
										<?php selected( $status_key, $s['key'] ); ?>>
										<?php echo esc_html( $s['label'] ?? $s['key'] ); ?>
									</option>
								<?php endforeach; ?>
							</select>
							<button type="button"
								id="hcqb-save-status"
								class="button button-primary"
								data-post-id="<?php echo esc_attr( $post_id ); ?>">
								Save Status
							</button>
							<span id="hcqb-status-msg" class="hcqb-status-msg" hidden></span>
						</div>
						<?php wp_nonce_field( 'hcqb_update_status', 'hcqb_status_nonce' ); ?>
					</div>
				<?php endif; ?>
			</div>

		</div>
		<?php
	}
}
