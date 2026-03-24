<?php
/**
 * admin-notification.php
 *
 * Plain-text email body for the admin quote submission notification.
 * Included via ob_start() inside HCQB_Email::build_admin_body().
 *
 * Variables in scope:
 *   $post_id       int     The hc-quote-submissions post ID
 *   $data          array   Sanitised submission data from HCQB_Submission::sanitise_data()
 *   $customer_name string  Full name (prefix + first + last)
 *
 * @package HC_Quote_Builder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$base_price   = (float) get_post_meta( $post_id, 'hcqb_base_price', true );
$full_address = HCQB_Submission::format_address( $data );
$admin_url    = admin_url( 'post.php?post=' . $post_id . '&action=edit' );
$submitted_at = current_time( 'F j, Y \a\t g:i a' );

?>
NEW QUOTE SUBMISSION
<?php echo str_repeat( '-', 52 ) . "\n"; ?>

CUSTOMER DETAILS
Name:     <?php echo $customer_name . "\n"; ?>
Email:    <?php echo $data['email'] . "\n"; ?>
Phone:    <?php echo $data['phone'] . "\n"; ?>
Address:  <?php echo $full_address . "\n"; ?>

<?php echo str_repeat( '-', 52 ) . "\n"; ?>

QUOTE SUMMARY

Product: <?php echo ( $data['product_name'] ?? '' ) ?: 'N/A'; echo "\n"; ?>
Base Price: <?php echo hcqb_format_price( $base_price ) . "\n"; ?>

Selected Options:
<?php foreach ( $data['selected_options'] as $opt ) :
	if ( 'base_price' === ( $opt['option_role'] ?? '' ) ) { continue; }
	$sign      = 'deduction' === $opt['price_type'] ? '-' : '+';
	$price_str = $opt['price'] > 0 ? '  ' . $sign . hcqb_format_price( $opt['price'] ) : '';
?>
  <?php echo $opt['question_label'] . ': ' . $opt['option_label'] . $price_str . "\n"; ?>
<?php endforeach; ?>

<?php echo str_repeat( '-', 52 ) . "\n"; ?>
TOTAL ESTIMATE:   <?php echo hcqb_format_price( $data['total_price'] ) . "\n"; ?>
<?php echo str_repeat( '-', 52 ) . "\n"; ?>

SHIPPING
Delivery Address:  <?php echo $full_address . "\n"; ?>
<?php if ( $data['shipping_distance_km'] > 0 ) : ?>
Shipping Distance: approximately <?php echo number_format( $data['shipping_distance_km'], 0 ) . " km from warehouse\n"; ?>
<?php endif; ?>
<?php if ( ( $data['estimated_shipping_cost'] ?? 0 ) > 0 ) : ?>
Est. Shipping Cost: <?php echo hcqb_format_price( $data['estimated_shipping_cost'] ) . " (estimate only)\n"; ?>
<?php endif; ?>

Submitted: <?php echo $submitted_at . "\n"; ?>

<?php echo str_repeat( '-', 52 ) . "\n"; ?>
View full submission in WordPress:
<?php echo $admin_url . "\n"; ?>
