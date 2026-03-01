<?php
/**
 * customer-copy.php
 *
 * Plain-text email body for the customer copy of their quote submission.
 * Included via ob_start() inside HCQB_Email::build_customer_body().
 *
 * Variables in scope:
 *   $post_id       int     The hc-quote-submissions post ID
 *   $data          array   Sanitised submission data from HCQB_Submission::sanitise_data()
 *   $product_id    int     The hc-containers post ID
 *   $product_name  string  Product title at submission time
 *   $customer_name string  Full name (prefix + first + last)
 *
 * @package HC_Quote_Builder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$base_price    = (float) get_post_meta( $product_id, 'hcqb_product_price', true );
$full_address  = HCQB_Submission::format_address( $data );
$company_name  = hcqb_get_setting( 'from_name' ) ?: get_option( 'blogname', '' );
$site_url      = home_url( '/' );
$salutation    = trim( $data['prefix'] . ' ' . $data['last_name'] );

?>
Hi <?php echo $salutation . ",\n"; ?>

Thank you for your enquiry. Here is a summary of your estimate:

<?php echo str_repeat( '-', 52 ) . "\n"; ?>
QUOTE SUMMARY
<?php echo str_repeat( '-', 52 ) . "\n"; ?>
Product: <?php echo $product_name . "\n"; ?>

Selected Options:
<?php foreach ( $data['selected_options'] as $opt ) :
	$sign      = 'deduction' === $opt['price_type'] ? '-' : '+';
	$price_str = $opt['price'] > 0 ? '  ' . $sign . hcqb_format_price( $opt['price'] ) : '';
?>
  <?php echo $opt['question_label'] . "\n"; ?>
  <?php echo $opt['option_label'] . $price_str . "\n"; ?>
<?php endforeach; ?>

Base Price:   <?php echo hcqb_format_price( $base_price ) . "\n"; ?>

<?php echo str_repeat( '-', 52 ) . "\n"; ?>
TOTAL ESTIMATE:   <?php echo hcqb_format_price( $data['total_price'] ) . "\n"; ?>
<?php echo str_repeat( '-', 52 ) . "\n"; ?>

SHIPPING
Delivery Address: <?php echo $full_address . "\n"; ?>
<?php if ( $data['shipping_distance_km'] > 0 ) : ?>
Estimated Distance: approximately <?php echo number_format( $data['shipping_distance_km'], 0 ) . " km\n"; ?>
<?php endif; ?>
Final shipping cost will be confirmed in your formal quote.

<?php echo str_repeat( '-', 52 ) . "\n"; ?>
This estimate is OBLIGATION FREE.
One of our team members will be in touch shortly
to discuss your enquiry. You are under no obligation
to proceed with any purchase.
<?php echo str_repeat( '-', 52 ) . "\n"; ?>

<?php echo $company_name . "\n"; ?>
<?php echo $site_url . "\n"; ?>
