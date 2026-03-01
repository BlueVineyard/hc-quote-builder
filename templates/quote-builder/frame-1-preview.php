<?php
/**
 * frame-1-preview.php
 *
 * Right panel of Frame 1 — live image preview, view toggle, feature pills,
 * live price display, and the step CTA button.
 * Included from HCQB_Shortcodes::render_builder_frame_1().
 *
 * Variables in scope (from calling function):
 *   $product           WP_Post  The hc-containers post
 *   $product_id        int
 *   $base_price        float
 *   $pill_questions    array    Max-4 questions with show_in_pill=1 (full question rows)
 *   $default_image_url string   URL of fallback image
 *
 * @package HC_Quote_Builder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="hcqb-builder-preview">

	<?php // ----------------------------------------------------------------
	// Image + view toggle
	// --------------------------------------------------------------- ?>
	<div class="hcqb-preview-image-wrap">

		<?php if ( hcqb_get_setting( 'show_view_angles' ) ) : ?>
		<div class="hcqb-preview-view-toggle" role="group" aria-label="Product view">
			<button type="button"
			        class="hcqb-view-btn hcqb-view-btn--active"
			        data-view="front"
			        aria-pressed="true">
				Front
			</button>
			<button type="button"
			        class="hcqb-view-btn"
			        data-view="back"
			        aria-pressed="false">
				Back
			</button>
			<button type="button"
			        class="hcqb-view-btn"
			        data-view="side"
			        aria-pressed="false">
				Side
			</button>
			<button type="button"
			        class="hcqb-view-btn"
			        data-view="interior"
			        aria-pressed="false">
				Interior
			</button>
		</div>
		<?php endif; ?>

		<div class="hcqb-preview-image">
			<?php if ( $default_image_url ) : ?>
			<img src="<?php echo esc_url( $default_image_url ); ?>"
			     alt="<?php echo esc_attr( $product_title ?? '' ); ?>"
			     class="hcqb-preview-img"
			     id="hcqb-preview-img">
			<?php else : ?>
			<div class="hcqb-preview-img-placeholder" id="hcqb-preview-img" aria-hidden="true"></div>
			<?php endif; ?>
		</div>

	</div><!-- .hcqb-preview-image-wrap -->

	<?php // ----------------------------------------------------------------
	// Feature pills
	// --------------------------------------------------------------- ?>
	<?php if ( ! empty( $pill_questions ) ) : ?>
	<div class="hcqb-feature-pills">
		<?php foreach ( $pill_questions as $pill_q ) : ?>
		<div class="hcqb-pill"
		     data-question-key="<?php echo esc_attr( $pill_q['key'] ); ?>">
			<span class="hcqb-pill__label"><?php echo esc_html( $pill_q['label'] ); ?></span>
			<span class="hcqb-pill__value">—</span>
		</div>
		<?php endforeach; ?>
	</div>
	<?php endif; ?>

	<?php // ----------------------------------------------------------------
	// Live price
	// --------------------------------------------------------------- ?>
	<div class="hcqb-preview-price">
		<span class="hcqb-preview-price__label">Estimated Price</span>
		<span class="hcqb-live-price"><?php echo esc_html( hcqb_format_price( $base_price ) ); ?></span>
		<span class="hcqb-preview-price__note">Subject to confirmation</span>
	</div>

	<?php // ----------------------------------------------------------------
	// Step indicator + CTA — wired up in Stage 8
	// --------------------------------------------------------------- ?>
	<div class="hcqb-preview-actions">
		<p class="hcqb-preview-step">Step 1 of 2 — Configure your container</p>
		<button type="button"
		        class="hcqb-btn hcqb-btn--primary hcqb-btn--full"
		        id="hcqb-next-step"
		        disabled>
			Continue to Contact Details →
		</button>
	</div>

</div><!-- .hcqb-builder-preview -->
