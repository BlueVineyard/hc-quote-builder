<?php
/**
 * single-hc-containers.php
 *
 * Plugin template for the hc-containers singular post type.
 * Overrides the active theme template via the template_include filter.
 *
 * Handles two views controlled by the ?view= URL parameter:
 *   product (default) — purchase view: gallery, pricing, quote button, features, description
 *   lease             — lease view: weekly price, terms, layout, extras, enquire button
 *
 * Falls back to 'product' view if ?view=lease is requested on a product with
 * hcqb_lease_enabled = 0.
 *
 * @package HC_Quote_Builder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// =========================================================================
// Determine view
// =========================================================================

$post_id       = get_the_ID();
$lease_enabled = (bool) get_post_meta( $post_id, 'hcqb_lease_enabled', true );
$raw_view      = sanitize_key( $_GET['view'] ?? 'product' );

// Lease view only available when lease is enabled on this product.
$view = ( 'lease' === $raw_view && $lease_enabled ) ? 'lease' : 'product';

// =========================================================================
// Gather meta — product fields
// =========================================================================

$base_price    = (float)  get_post_meta( $post_id, 'hcqb_product_price',     true );
$star_rating   = (float)  get_post_meta( $post_id, 'hcqb_star_rating',       true );
$review_count  = (int)    get_post_meta( $post_id, 'hcqb_review_count',      true );
$short_desc    =          get_post_meta( $post_id, 'hcqb_short_description',  true );
$gallery_ids   =          get_post_meta( $post_id, 'hcqb_product_images',     true );
$features      =          get_post_meta( $post_id, 'hcqb_features',           true );
$description   =          get_post_meta( $post_id, 'hcqb_product_description',true );
$add_notes     =          get_post_meta( $post_id, 'hcqb_additional_notes',   true );
$plan_doc_id   = (int)    get_post_meta( $post_id, 'hcqb_plan_document',      true );
$shipping_link =          get_post_meta( $post_id, 'hcqb_shipping_details_link', true );

// =========================================================================
// Gather meta — lease fields
// =========================================================================

$lease_price        = (float) get_post_meta( $post_id, 'hcqb_lease_price',              true );
$lease_price_label  =         get_post_meta( $post_id, 'hcqb_lease_price_label',        true ) ?: 'per week';
$lease_terms        =         get_post_meta( $post_id, 'hcqb_lease_terms',              true );
$lease_layout_title =         get_post_meta( $post_id, 'hcqb_lease_layout_title',       true );
$lease_layout_desc  =         get_post_meta( $post_id, 'hcqb_lease_layout_description', true );
$lease_extras       =         get_post_meta( $post_id, 'hcqb_lease_extras',             true );
$enquiry_btn_label  =         get_post_meta( $post_id, 'hcqb_enquiry_button_label',     true ) ?: 'Enquire Now';

// =========================================================================
// Assembled price calculation
// =========================================================================

$config          = hcqb_get_active_config_for_product( $post_id );
$assembled_price = null;

if ( $config ) {
	$questions = get_post_meta( $config->ID, 'hcqb_questions', true ) ?: [];
	foreach ( $questions as $q ) {
		foreach ( $q['options'] ?? [] as $opt ) {
			if ( ( $opt['option_role'] ?? '' ) === 'assembly' ) {
				$delta           = (float) $opt['price'];
				$assembled_price = ( 'deduction' === ( $opt['price_type'] ?? '' ) )
					? $base_price - $delta
					: $base_price + $delta;
				break 2;
			}
		}
	}
}

// =========================================================================
// Quote / enquire button
// Button is hidden via CSS (not removed from DOM) when config is inactive.
// =========================================================================

$btn_class      = $config ? '' : 'hcqb-btn--hidden';
$quote_page_id  = (int) hcqb_get_setting( 'quote_builder_page_id' );
$quote_page_url = $quote_page_id ? get_permalink( $quote_page_id ) : '';

// =========================================================================
// Gallery — normalise to array; fall back to featured image if empty
// =========================================================================

if ( ! is_array( $gallery_ids ) || empty( $gallery_ids ) ) {
	$thumb_id    = get_post_thumbnail_id( $post_id );
	$gallery_ids = $thumb_id ? [ (int) $thumb_id ] : [];
} else {
	$gallery_ids = array_map( 'absint', $gallery_ids );
}

// =========================================================================
// Theme header
// =========================================================================

get_header();
?>

<main class="hcqb-product-page" data-view="<?php echo esc_attr( $view ); ?>">
<div class="hcqb-product-container">

	<?php // ----------------------------------------------------------------
	// View switcher — only shown when lease is enabled
	// --------------------------------------------------------------- ?>
	<?php if ( $lease_enabled ) : ?>
	<nav class="hcqb-view-switcher" aria-label="Product views">
		<a href="<?php echo esc_url( add_query_arg( 'view', 'product', get_permalink() ) ); ?>"
		   class="hcqb-view-tab<?php echo 'product' === $view ? ' hcqb-view-tab--active' : ''; ?>">
			Purchase
		</a>
		<a href="<?php echo esc_url( add_query_arg( 'view', 'lease', get_permalink() ) ); ?>"
		   class="hcqb-view-tab<?php echo 'lease' === $view ? ' hcqb-view-tab--active' : ''; ?>">
			Lease
		</a>
	</nav>
	<?php endif; ?>

	<div class="hcqb-product-layout">

		<?php // ------------------------------------------------------------
		// Gallery column — vertical thumbnail strip + main image
		// --------------------------------------------------------- ?>
		<?php if ( $gallery_ids ) :
			$main_id    = $gallery_ids[0];
			$main_url   = wp_get_attachment_image_url( $main_id, 'large' );
			$main_alt   = trim( (string) get_post_meta( $main_id, '_wp_attachment_image_alt', true ) );
			$has_thumbs = count( $gallery_ids ) > 1;
		?>
		<div class="hcqb-product-gallery">

			<div class="hcqb-gallery-layout<?php echo $has_thumbs ? ' hcqb-gallery-layout--with-thumbs' : ''; ?>">

				<?php if ( $has_thumbs ) : ?>
				<div class="hcqb-gallery-thumbs" role="list">
					<?php foreach ( $gallery_ids as $img_id ) :
						$thumb_url = wp_get_attachment_image_url( $img_id, 'thumbnail' );
						$full_url  = wp_get_attachment_image_url( $img_id, 'large' );
						$alt       = trim( (string) get_post_meta( $img_id, '_wp_attachment_image_alt', true ) );
					?>
					<button type="button"
					        class="hcqb-gallery-thumb<?php echo $img_id === $main_id ? ' hcqb-gallery-thumb--active' : ''; ?>"
					        data-full="<?php echo esc_url( $full_url ); ?>"
					        aria-label="View image"
					        role="listitem">
						<img src="<?php echo esc_url( $thumb_url ); ?>"
						     alt="<?php echo esc_attr( $alt ?: get_the_title() ); ?>">
					</button>
					<?php endforeach; ?>
				</div>
				<?php endif; ?>

				<div class="hcqb-gallery-main">
					<img src="<?php echo esc_url( $main_url ); ?>"
					     alt="<?php echo esc_attr( $main_alt ?: get_the_title() ); ?>"
					     class="hcqb-gallery-main__img"
					     id="hcqb-main-img">
				</div>

			</div><!-- .hcqb-gallery-layout -->

			<?php // Categories taxonomy links ?>
			<?php
			$terms = get_the_terms( $post_id, 'hc-container-category' );
			if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) :
				$term_links = [];
				foreach ( $terms as $term ) {
					$term_url     = get_term_link( $term );
					$term_links[] = '<a href="' . esc_url( $term_url ) . '" class="hcqb-product-cat-link">'
					                . esc_html( $term->name )
					                . '</a>';
				}
			?>
			<p class="hcqb-product-cats">
				<strong>Categories:</strong>
				<?php echo implode( ', ', $term_links ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			</p>
			<?php endif; ?>

			<?php // Review prompt + dummy button ?>
			<p class="hcqb-review-prompt">Purchased this unit? Please leave us a review.</p>
			<button type="button" class="hcqb-btn hcqb-btn--review">
				<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
					<path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
					<path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
				</svg>
				Write a Review
			</button>

		</div><!-- .hcqb-product-gallery -->
		<?php endif; ?>

		<?php // ------------------------------------------------------------
		// Details column
		// --------------------------------------------------------- ?>
		<div class="hcqb-product-details">

			<?php // Rating — above the title ?>
			<?php if ( $star_rating > 0 ) : ?>
			<div class="hcqb-rating"
			     aria-label="<?php echo esc_attr( $star_rating . ' out of 5 stars' ); ?>">
				<span class="hcqb-stars">
					<?php echo hcqb_render_stars( $star_rating ); ?>
				</span>
				<?php if ( $review_count > 0 ) : ?>
				<span class="hcqb-review-count">
					<?php echo absint( $review_count ); ?> Review<?php echo $review_count !== 1 ? 's' : ''; ?>
				</span>
				<?php endif; ?>
			</div>
			<?php endif; ?>

			<h1 class="hcqb-product-title"><?php
				echo esc_html( get_the_title() );
				if ( 'lease' === $view ) {
					echo ' <span class="hcqb-title-tag">— Lease</span>';
				}
			?></h1>

			<?php if ( $short_desc ) : ?>
			<p class="hcqb-short-desc"><?php echo esc_html( $short_desc ); ?></p>
			<?php endif; ?>

			<?php // ============================================================
			// PURCHASE VIEW
			// ============================================================ ?>
			<?php if ( 'product' === $view ) : ?>

			<?php // Large base price — shown above the description card ?>
			<?php if ( $base_price > 0 ) : ?>
			<div class="hcqb-base-price"><?php echo esc_html( hcqb_format_price( $base_price ) ); ?></div>
			<?php endif; ?>

			<?php // Product Description card — collapsible ?>
			<details class="hcqb-desc-card" open>
				<summary class="hcqb-desc-card__header">
					<span class="hcqb-desc-card__title">Product Description</span>
					<span class="hcqb-desc-card__toggle" aria-hidden="true"></span>
				</summary>

				<div class="hcqb-desc-card__body">

					<?php if ( $description ) : ?>
					<div class="hcqb-product-description">
						<?php echo wp_kses_post( $description ); ?>
					</div>
					<?php endif; ?>

					<?php if ( is_array( $features ) && $features ) : ?>
					<ul class="hcqb-features-list">
						<?php foreach ( $features as $feat ) :
							$feat_label = sanitize_text_field( $feat['label'] ?? '' );
							$icon_id    = absint( $feat['icon_id'] ?? 0 );
							if ( ! $feat_label ) { continue; }
						?>
						<li class="hcqb-feature">
							<span class="hcqb-feature__icon-wrap" aria-hidden="true">
								<?php if ( $icon_id ) : ?>
								<img src="<?php echo esc_url( wp_get_attachment_image_url( $icon_id, 'thumbnail' ) ); ?>"
								     alt=""
								     class="hcqb-feature__icon"
								     width="20" height="20">
								<?php endif; ?>
							</span>
							<span class="hcqb-feature__label"><?php echo esc_html( $feat_label ); ?></span>
						</li>
						<?php endforeach; ?>
					</ul>
					<?php endif; ?>

					<?php if ( $add_notes ) : ?>
					<div class="hcqb-additional-notes"><?php echo wp_kses_post( $add_notes ); ?></div>
					<?php endif; ?>

					<?php // Flatpack + assembled pricing as red text ?>
					<?php if ( $base_price > 0 ) : ?>
					<p class="hcqb-price-text">
						Priced at <?php echo esc_html( hcqb_format_price( $base_price ) ); ?> for flatpack
					</p>
					<?php endif; ?>

					<?php if ( null !== $assembled_price ) : ?>
					<p class="hcqb-price-text">
						<?php echo esc_html( hcqb_format_price( $assembled_price ) ); ?> for Assembled Building
					</p>
					<?php endif; ?>

					<?php // Plan document link ?>
					<?php if ( $plan_doc_id ) :
						$plan_url = wp_get_attachment_url( $plan_doc_id );
					?>
					<a href="<?php echo esc_url( $plan_url ); ?>"
					   class="hcqb-plan-link"
					   target="_blank"
					   rel="noopener noreferrer">
						View full plan here
					</a>
					<?php endif; ?>

				</div><!-- .hcqb-desc-card__body -->
			</details><!-- .hcqb-desc-card -->

			<?php // Shipping details button ?>
			<?php if ( $shipping_link ) : ?>
			<a href="<?php echo esc_url( $shipping_link ); ?>"
			   class="hcqb-btn hcqb-btn--shipping"
			   target="_blank"
			   rel="noopener noreferrer">
				Click here for shipping details ↗
			</a>
			<?php endif; ?>

			<?php // Get a Custom Quote button ?>
			<?php if ( $quote_page_url ) : ?>
			<a href="<?php echo esc_url( add_query_arg( 'product', $post_id, $quote_page_url ) ); ?>"
			   class="hcqb-btn hcqb-btn--quote <?php echo esc_attr( $btn_class ); ?>">
				Get a Custom Quote ↗
			</a>
			<?php endif; ?>

			<?php endif; // end purchase view — details column ?>

			<?php // ============================================================
			// LEASE VIEW
			// ============================================================ ?>
			<?php if ( 'lease' === $view ) : ?>

			<?php if ( $lease_price > 0 ) : ?>
			<div class="hcqb-pricing">
				<div class="hcqb-price-row hcqb-price-row--lease">
					<span class="hcqb-price-value hcqb-price-value--large"><?php echo esc_html( hcqb_format_price( $lease_price ) ); ?></span>
					<span class="hcqb-price-label"><?php echo esc_html( $lease_price_label ); ?></span>
				</div>
			</div>
			<?php endif; ?>

			<?php if ( $lease_terms ) : ?>
			<div class="hcqb-lease-terms">
				<?php echo wp_kses_post( $lease_terms ); ?>
			</div>
			<?php endif; ?>

			<?php if ( $lease_layout_title || $lease_layout_desc ) : ?>
			<div class="hcqb-lease-layout">
				<?php if ( $lease_layout_title ) : ?>
				<h3 class="hcqb-lease-layout__title"><?php echo esc_html( $lease_layout_title ); ?></h3>
				<?php endif; ?>
				<?php if ( $lease_layout_desc ) : ?>
				<div class="hcqb-lease-layout__desc"><?php echo wp_kses_post( $lease_layout_desc ); ?></div>
				<?php endif; ?>
			</div>
			<?php endif; ?>

			<?php if ( is_array( $lease_extras ) && $lease_extras ) : ?>
			<div class="hcqb-lease-extras">
				<h4 class="hcqb-lease-extras__heading">Optional Extras</h4>
				<ul class="hcqb-lease-extras__list">
					<?php foreach ( $lease_extras as $extra ) :
						$extra_label = sanitize_text_field( $extra['label']        ?? '' );
						$extra_price = (float)             ( $extra['weekly_price'] ?? 0 );
						if ( ! $extra_label ) { continue; }
					?>
					<li class="hcqb-lease-extra">
						<span class="hcqb-lease-extra__label"><?php echo esc_html( $extra_label ); ?></span>
						<span class="hcqb-lease-extra__price"><?php echo esc_html( hcqb_format_price( $extra_price ) ); ?> / week</span>
					</li>
					<?php endforeach; ?>
				</ul>
			</div>
			<?php endif; ?>

			<?php if ( $quote_page_url ) : ?>
			<a href="<?php echo esc_url( add_query_arg( [ 'product' => $post_id, 'view' => 'lease' ], $quote_page_url ) ); ?>"
			   class="hcqb-btn hcqb-btn--enquire">
				<?php echo esc_html( $enquiry_btn_label ); ?>
			</a>
			<?php endif; ?>

			<?php endif; // end lease view — details column ?>

		</div><!-- .hcqb-product-details -->
	</div><!-- .hcqb-product-layout -->

</div><!-- .hcqb-product-container -->
</main>

<?php // =========================================================================
// Gallery thumbnail switcher — minimal inline script
// ========================================================================= ?>
<?php if ( count( $gallery_ids ) > 1 ) : ?>
<script>
( function () {
	var mainImg = document.getElementById( 'hcqb-main-img' );
	document.querySelectorAll( '.hcqb-gallery-thumb' ).forEach( function ( btn ) {
		btn.addEventListener( 'click', function () {
			mainImg.src = this.dataset.full;
			document.querySelectorAll( '.hcqb-gallery-thumb' ).forEach( function ( b ) {
				b.classList.remove( 'hcqb-gallery-thumb--active' );
			} );
			this.classList.add( 'hcqb-gallery-thumb--active' );
		} );
	} );
}() );
</script>
<?php endif; ?>

<?php get_footer(); ?>
