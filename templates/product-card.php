<?php
/**
 * product-card.php
 *
 * Shared card partial — used by both [hc_product_grid] and [hc_lease_grid].
 * Included from HCQB_Shortcodes::render_grid() inside a WP_Query loop.
 *
 * Variables injected by render_grid():
 *   $card_type  string  'product' or 'lease'
 *
 * The WordPress loop is active when this file is included, so all standard
 * template tags (get_the_ID, get_the_title, get_permalink, etc.) are available.
 *
 * @package HC_Quote_Builder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$_post_id = get_the_ID();

// =========================================================================
// Image
// =========================================================================

$_image_ids = array_filter( array_map( 'absint', (array) ( get_post_meta( $_post_id, 'hcqb_product_images', true ) ?: [] ) ) );
$_thumb_id  = ! empty( $_image_ids ) ? reset( $_image_ids ) : (int) get_post_thumbnail_id( $_post_id );

// =========================================================================
// Price + link — differ by card type
// =========================================================================

if ( 'lease' === $card_type ) {
	$_price       = (float) get_post_meta( $_post_id, 'hcqb_lease_price', true );
	$_price_label = get_post_meta( $_post_id, 'hcqb_lease_price_label', true ) ?: 'per week';
	$_link        = add_query_arg( 'view', 'lease', get_permalink() );
	$_cta_label   = get_post_meta( $_post_id, 'hcqb_enquiry_button_label', true ) ?: 'View Lease';
} else {
	$_price       = (float) get_post_meta( $_post_id, 'hcqb_product_price', true );
	$_price_label = '';
	$_link        = get_permalink();
	$_cta_label   = 'View Product';
}

// =========================================================================
// Supporting fields
// =========================================================================

$_short_desc = get_post_meta( $_post_id, 'hcqb_short_description', true );
$_rating     = (float) get_post_meta( $_post_id, 'hcqb_star_rating', true );
?>
<article class="hcqb-card">

	<a class="hcqb-card__image-wrap"
	   href="<?php echo esc_url( $_link ); ?>"
	   tabindex="-1"
	   aria-hidden="true">
		<div class="hcqb-card__image">
			<?php if ( $_thumb_id ) : ?>
				<?php echo wp_get_attachment_image(
					$_thumb_id,
					'medium',
					false,
					[
						'class' => 'hcqb-card__img',
						'alt'   => esc_attr( get_the_title() ),
					]
				); ?>
			<?php else : ?>
				<div class="hcqb-card__img-placeholder"></div>
			<?php endif; ?>
		</div>
	</a>

	<div class="hcqb-card__body">

		<?php if ( $_rating > 0 ) : ?>
		<div class="hcqb-card__rating"
		     aria-label="<?php echo esc_attr( number_format( $_rating, 1 ) . ' out of 5 stars' ); ?>">
			<?php echo hcqb_render_stars( $_rating ); ?>
		</div>
		<?php endif; ?>

		<h3 class="hcqb-card__title">
			<a href="<?php echo esc_url( $_link ); ?>"><?php echo esc_html( get_the_title() ); ?></a>
		</h3>

		<?php if ( $_short_desc ) : ?>
		<p class="hcqb-card__short-desc"><?php echo esc_html( $_short_desc ); ?></p>
		<?php endif; ?>

		<?php if ( $_price > 0 ) : ?>
		<div class="hcqb-card__price">
			<span class="hcqb-card__price-value"><?php echo esc_html( hcqb_format_price( $_price ) ); ?></span>
			<?php if ( $_price_label ) : ?>
			<span class="hcqb-card__price-label"><?php echo esc_html( $_price_label ); ?></span>
			<?php endif; ?>
		</div>
		<?php endif; ?>

		<a href="<?php echo esc_url( $_link ); ?>" class="hcqb-btn hcqb-btn--card">
			<?php echo esc_html( $_cta_label ); ?>
		</a>

	</div><!-- .hcqb-card__body -->

</article>
