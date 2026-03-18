<?php
/**
 * HCQB_Shortcodes
 *
 * All plugin shortcodes — individual field shortcodes for custom template
 * building plus grid shortcode stubs (full implementation in Stage 6).
 *
 * Individual shortcodes accept an optional post_id="" attribute. When omitted
 * they default to the current post (get_the_ID()). All return empty string
 * rather than an error when data is missing.
 *
 * Registered shortcodes:
 *   [hcqb_title]              — Post title
 *   [hcqb_price]              — Base price (formatted)
 *   [hcqb_assembled_price]    — Assembled price (formatted)
 *   [hcqb_rating]             — Star rating HTML + review count
 *   [hcqb_review_count]       — Review count number only
 *   [hcqb_short_desc]         — Short description (plain text)
 *   [hcqb_description]        — Full product description (rich text)
 *   [hcqb_additional_notes]   — Additional notes block
 *   [hcqb_gallery]            — All product images (thumbnail strip)
 *   [hcqb_main_image]         — Primary product image only
 *   [hcqb_features]           — Features list (icon + label)
 *   [hcqb_plan_document]      — Plan document download link
 *   [hcqb_shipping_link]      — Shipping details link
 *   [hcqb_lease_price]        — Lease price (formatted)
 *   [hcqb_lease_price_label]  — Lease price label (e.g. "per week")
 *   [hcqb_lease_terms]        — Lease terms (rich text)
 *   [hcqb_lease_layout_title] — Standard layout section title
 *   [hcqb_lease_layout_desc]  — Standard layout section description
 *   [hcqb_lease_extras]       — Lease optional extras list
 *   [hcqb_quote_button]       — Get a Custom Quote button
 *   [hcqb_enquire_button]     — Enquire Now button (lease)
 *   [hc_product_grid]         — Product grid (stub — Stage 6)
 *   [hc_lease_grid]           — Lease grid (stub — Stage 6)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HCQB_Shortcodes {

	/** Whether the enquire-modal inline JS has already been output. */
	private static bool $enquire_modal_js_output = false;

	// -------------------------------------------------------------------------
	// Registration
	// -------------------------------------------------------------------------

	public static function register(): void {
		// Individual field shortcodes
		add_shortcode( 'hcqb_title',              [ __CLASS__, 'sc_title'              ] );
		add_shortcode( 'hcqb_price',              [ __CLASS__, 'sc_price'              ] );
		add_shortcode( 'hcqb_assembled_price',    [ __CLASS__, 'sc_assembled_price'    ] );
		add_shortcode( 'hcqb_rating',             [ __CLASS__, 'sc_rating'             ] );
		add_shortcode( 'hcqb_review_count',       [ __CLASS__, 'sc_review_count'       ] );
		add_shortcode( 'hcqb_short_desc',         [ __CLASS__, 'sc_short_desc'         ] );
		add_shortcode( 'hcqb_description',        [ __CLASS__, 'sc_description'        ] );
		add_shortcode( 'hcqb_additional_notes',   [ __CLASS__, 'sc_additional_notes'   ] );
		add_shortcode( 'hcqb_gallery',            [ __CLASS__, 'sc_gallery'            ] );
		add_shortcode( 'hcqb_image_list',         [ __CLASS__, 'sc_image_list'         ] );
		add_shortcode( 'hcqb_main_image',         [ __CLASS__, 'sc_main_image'         ] );
		add_shortcode( 'hcqb_features',           [ __CLASS__, 'sc_features'           ] );
		add_shortcode( 'hcqb_plan_document',      [ __CLASS__, 'sc_plan_document'      ] );
		add_shortcode( 'hcqb_shipping_link',      [ __CLASS__, 'sc_shipping_link'      ] );
		add_shortcode( 'hcqb_lease_price',        [ __CLASS__, 'sc_lease_price'        ] );
		add_shortcode( 'hcqb_lease_price_label',  [ __CLASS__, 'sc_lease_price_label'  ] );
		add_shortcode( 'hcqb_lease_terms',        [ __CLASS__, 'sc_lease_terms'        ] );
		add_shortcode( 'hcqb_lease_layout_title', [ __CLASS__, 'sc_lease_layout_title' ] );
		add_shortcode( 'hcqb_lease_layout_desc',  [ __CLASS__, 'sc_lease_layout_desc'  ] );
		add_shortcode( 'hcqb_lease_extras',       [ __CLASS__, 'sc_lease_extras'       ] );
		add_shortcode( 'hcqb_quote_button',       [ __CLASS__, 'sc_quote_button'       ] );
		add_shortcode( 'hcqb_enquire_button',     [ __CLASS__, 'sc_enquire_button'     ] );

		// Grid shortcodes — full implementation in Stage 6
		add_shortcode( 'hc_product_grid', [ __CLASS__, 'sc_product_grid' ] );
		add_shortcode( 'hc_lease_grid',   [ __CLASS__, 'sc_lease_grid'   ] );

		// Quote builder — Stage 7
		add_shortcode( 'hc_quote_builder', [ __CLASS__, 'sc_quote_builder' ] );
	}

	// -------------------------------------------------------------------------
	// Shared helper — resolve post ID from atts or current context
	// -------------------------------------------------------------------------

	private static function get_post_id( array $atts ): int {
		if ( ! empty( $atts['post_id'] ) ) {
			return absint( $atts['post_id'] );
		}
		return (int) get_the_ID();
	}

	// -------------------------------------------------------------------------
	// [hcqb_title]
	// -------------------------------------------------------------------------

	/**
	 * Output the container post title.
	 *
	 * @param array|string $atts Shortcode attributes. Accepts: post_id.
	 * @return string
	 */
	public static function sc_title( array|string $atts ): string {
		$atts    = shortcode_atts( [ 'post_id' => 0 ], (array) $atts );
		$post_id = self::get_post_id( $atts );
		if ( ! $post_id ) {
			return '';
		}
		$post = get_post( $post_id );
		return $post ? esc_html( $post->post_title ) : '';
	}

	// -------------------------------------------------------------------------
	// [hcqb_price]
	// -------------------------------------------------------------------------

	/**
	 * Output the base (flat-pack) price.
	 *
	 * @param array|string $atts Shortcode attributes. Accepts: post_id.
	 * @return string  Formatted price string, e.g. "$7,500.00".
	 */
	public static function sc_price( array|string $atts ): string {
		$atts    = shortcode_atts( [ 'post_id' => 0 ], (array) $atts );
		$post_id = self::get_post_id( $atts );
		if ( ! $post_id ) {
			return '';
		}
		$price = (float) get_post_meta( $post_id, 'hcqb_product_price', true );
		return esc_html( hcqb_format_price( $price ) );
	}

	// -------------------------------------------------------------------------
	// [hcqb_assembled_price]
	// -------------------------------------------------------------------------

	/**
	 * Output the assembled (fully installed) price.
	 * Calculated from the base price plus/minus the assembly-role option delta.
	 * Falls back to the base price if no active config or no assembly option exists.
	 *
	 * @param array|string $atts Shortcode attributes. Accepts: post_id.
	 * @return string  Formatted price string.
	 */
	public static function sc_assembled_price( array|string $atts ): string {
		$atts       = shortcode_atts( [ 'post_id' => 0 ], (array) $atts );
		$post_id    = self::get_post_id( $atts );
		if ( ! $post_id ) {
			return '';
		}
		$base_price = (float) get_post_meta( $post_id, 'hcqb_product_price', true );
		$config     = hcqb_get_active_config_for_product( $post_id );
		if ( $config ) {
			$questions = get_post_meta( $config->ID, 'hcqb_questions', true ) ?: [];
			foreach ( $questions as $q ) {
				foreach ( $q['options'] ?? [] as $opt ) {
					if ( ( $opt['option_role'] ?? '' ) === 'assembly' ) {
						$delta     = (float) $opt['price'];
						$assembled = ( 'deduction' === ( $opt['price_type'] ?? '' ) )
							? $base_price - $delta
							: $base_price + $delta;
						return esc_html( hcqb_format_price( $assembled ) );
					}
				}
			}
		}
		return esc_html( hcqb_format_price( $base_price ) );
	}

	// -------------------------------------------------------------------------
	// [hcqb_rating]
	// -------------------------------------------------------------------------

	/**
	 * Output the star rating block (stars + review count).
	 *
	 * @param array|string $atts Shortcode attributes. Accepts: post_id.
	 * @return string  HTML rating block, or empty if rating is 0.
	 */
	public static function sc_rating( array|string $atts ): string {
		$atts    = shortcode_atts( [ 'post_id' => 0 ], (array) $atts );
		$post_id = self::get_post_id( $atts );
		if ( ! $post_id ) {
			return '';
		}
		$rating = (float) get_post_meta( $post_id, 'hcqb_star_rating', true );
		if ( $rating <= 0 ) {
			return '';
		}
		$count = absint( get_post_meta( $post_id, 'hcqb_review_count', true ) );
		$label = number_format( $rating, 1 ) . ' out of 5 stars';
		ob_start();
		?>
		<div class="hcqb-rating" aria-label="<?php echo esc_attr( $label ); ?>">
			<span class="hcqb-stars"><?php echo hcqb_render_stars( $rating ); ?></span>
			<?php if ( $count > 0 ) : ?>
			<span class="hcqb-review-count">(<?php echo esc_html( number_format( $count ) ); ?> review<?php echo 1 !== $count ? 's' : ''; ?>)</span>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	// -------------------------------------------------------------------------
	// [hcqb_review_count]
	// -------------------------------------------------------------------------

	/**
	 * Output the raw review count number only.
	 *
	 * @param array|string $atts Shortcode attributes. Accepts: post_id.
	 * @return string  Numeric count, e.g. "42", or empty if 0.
	 */
	public static function sc_review_count( array|string $atts ): string {
		$atts    = shortcode_atts( [ 'post_id' => 0 ], (array) $atts );
		$post_id = self::get_post_id( $atts );
		if ( ! $post_id ) {
			return '';
		}
		$count = absint( get_post_meta( $post_id, 'hcqb_review_count', true ) );
		return $count > 0 ? esc_html( number_format( $count ) ) : '';
	}

	// -------------------------------------------------------------------------
	// [hcqb_short_desc]
	// -------------------------------------------------------------------------

	/**
	 * Output the short description (plain text).
	 *
	 * @param array|string $atts Shortcode attributes. Accepts: post_id.
	 * @return string
	 */
	public static function sc_short_desc( array|string $atts ): string {
		$atts    = shortcode_atts( [ 'post_id' => 0 ], (array) $atts );
		$post_id = self::get_post_id( $atts );
		if ( ! $post_id ) {
			return '';
		}
		return esc_html( get_post_meta( $post_id, 'hcqb_short_description', true ) );
	}

	// -------------------------------------------------------------------------
	// [hcqb_description]
	// -------------------------------------------------------------------------

	/**
	 * Output the full product description (rich text / HTML).
	 *
	 * @param array|string $atts Shortcode attributes. Accepts: post_id.
	 * @return string  Filtered HTML string.
	 */
	public static function sc_description( array|string $atts ): string {
		$atts    = shortcode_atts( [ 'post_id' => 0 ], (array) $atts );
		$post_id = self::get_post_id( $atts );
		if ( ! $post_id ) {
			return '';
		}
		return wp_kses_post( wpautop( get_post_meta( $post_id, 'hcqb_product_description', true ) ) );
	}

	// -------------------------------------------------------------------------
	// [hcqb_additional_notes]
	// -------------------------------------------------------------------------

	/**
	 * Output the additional notes in a styled block.
	 *
	 * @param array|string $atts Shortcode attributes. Accepts: post_id.
	 * @return string  HTML block or empty string if no notes saved.
	 */
	public static function sc_additional_notes( array|string $atts ): string {
		$atts    = shortcode_atts( [ 'post_id' => 0 ], (array) $atts );
		$post_id = self::get_post_id( $atts );
		if ( ! $post_id ) {
			return '';
		}
		$notes = get_post_meta( $post_id, 'hcqb_additional_notes', true );
		if ( ! $notes ) {
			return '';
		}
		return wp_kses_post( wpautop( $notes ) );
	}

	// -------------------------------------------------------------------------
	// [hcqb_gallery]
	// -------------------------------------------------------------------------

	/**
	 * Output a WooCommerce-style product gallery (main image + clickable thumbnails).
	 *
	 * @param array|string $atts  Shortcode attributes. Accepts: post_id, size, thumb_size.
	 * @return string  Full gallery HTML.
	 */
	public static function sc_gallery( array|string $atts ): string {
		$atts    = shortcode_atts( [
			'post_id'    => 0,
			'size'       => 'large',
			'thumb_size' => 'thumbnail',
		], (array) $atts );

		$post_id = self::get_post_id( $atts );
		if ( ! $post_id ) {
			return '';
		}

		$ids = array_filter( array_map( 'absint', (array) ( get_post_meta( $post_id, 'hcqb_product_images', true ) ?: [] ) ) );
		if ( empty( $ids ) ) {
			$thumb = (int) get_post_thumbnail_id( $post_id );
			if ( $thumb ) {
				$ids = [ $thumb ];
			}
		}
		if ( empty( $ids ) ) {
			return '';
		}

		// Ensure gallery assets are loaded.
		wp_enqueue_style( 'hcqb-product-page' );
		wp_enqueue_script( 'hcqb-gallery' );

		$main_id   = $ids[0];
		$main_url  = wp_get_attachment_image_url( $main_id, $atts['size'] );
		$main_alt  = trim( (string) get_post_meta( $main_id, '_wp_attachment_image_alt', true ) );
		$has_thumbs = count( $ids ) > 1;
		$title      = get_the_title( $post_id );

		ob_start();
		?>
		<div class="hcqb-product-gallery">
			<div class="hcqb-gallery-layout<?php echo $has_thumbs ? ' hcqb-gallery-layout--with-thumbs' : ''; ?>">

				<?php if ( $has_thumbs ) : ?>
				<div class="hcqb-gallery-thumbs swiper">
					<div class="swiper-wrapper">
						<?php foreach ( $ids as $img_id ) :
							$thumb_url = wp_get_attachment_image_url( $img_id, $atts['thumb_size'] );
							$full_url  = wp_get_attachment_image_url( $img_id, $atts['size'] );
							$alt       = trim( (string) get_post_meta( $img_id, '_wp_attachment_image_alt', true ) );
						?>
						<button type="button"
						        class="hcqb-gallery-thumb swiper-slide<?php echo $img_id === $main_id ? ' hcqb-gallery-thumb--active' : ''; ?>"
						        data-full="<?php echo esc_url( $full_url ); ?>"
						        aria-label="View image">
							<img src="<?php echo esc_url( $thumb_url ); ?>"
							     alt="<?php echo esc_attr( $alt ?: $title ); ?>">
						</button>
						<?php endforeach; ?>
					</div>
					<div class="hcqb-gallery-nav hcqb-gallery-nav--prev"></div>
					<div class="hcqb-gallery-nav hcqb-gallery-nav--next"></div>
				</div>
				<?php endif; ?>

				<div class="hcqb-gallery-main">
					<img src="<?php echo esc_url( $main_url ); ?>"
					     alt="<?php echo esc_attr( $main_alt ?: $title ); ?>"
					     class="hcqb-gallery-main__img">
				</div>

			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	// -------------------------------------------------------------------------
	// [hcqb_image_list]
	// -------------------------------------------------------------------------

	/**
	 * Output all gallery images as a simple list of img tags.
	 *
	 * @param array|string $atts  Shortcode attributes. Accepts: post_id, size (default "large").
	 * @return string  Sequence of img tags or empty string.
	 */
	public static function sc_image_list( array|string $atts ): string {
		$atts    = shortcode_atts( [ 'post_id' => 0, 'size' => 'large' ], (array) $atts );
		$post_id = self::get_post_id( $atts );
		if ( ! $post_id ) {
			return '';
		}

		$ids = array_filter( array_map( 'absint', (array) ( get_post_meta( $post_id, 'hcqb_product_images', true ) ?: [] ) ) );
		if ( empty( $ids ) ) {
			$thumb = (int) get_post_thumbnail_id( $post_id );
			if ( $thumb ) {
				$ids = [ $thumb ];
			}
		}
		if ( empty( $ids ) ) {
			return '';
		}

		$title = get_the_title( $post_id );
		$out   = '';
		foreach ( $ids as $img_id ) {
			$url = wp_get_attachment_image_url( $img_id, $atts['size'] );
			$alt = trim( (string) get_post_meta( $img_id, '_wp_attachment_image_alt', true ) );
			if ( ! $url ) {
				continue;
			}
			$out .= '<img class="hcqb-image-list__img" src="' . esc_url( $url ) . '" alt="' . esc_attr( $alt ?: $title ) . '">' . "\n";
		}
		return $out;
	}

	// -------------------------------------------------------------------------
	// [hcqb_main_image]
	// -------------------------------------------------------------------------

	/**
	 * Output the primary product image only (first gallery image or featured image).
	 *
	 * @param array|string $atts  Shortcode attributes. Accepts: post_id, size (default "large").
	 * @return string  img tag or empty string.
	 */
	public static function sc_main_image( array|string $atts ): string {
		$atts    = shortcode_atts( [ 'post_id' => 0, 'size' => 'large' ], (array) $atts );
		$post_id = self::get_post_id( $atts );
		if ( ! $post_id ) {
			return '';
		}
		$ids   = (array) ( get_post_meta( $post_id, 'hcqb_product_images', true ) ?: [] );
		$first = ! empty( $ids ) ? absint( $ids[0] ) : (int) get_post_thumbnail_id( $post_id );
		if ( ! $first ) {
			return '';
		}
		return wp_get_attachment_image( $first, $atts['size'], false, [ 'class' => 'hcqb-gallery-main__img' ] );
	}

	// -------------------------------------------------------------------------
	// [hcqb_features]
	// -------------------------------------------------------------------------

	/**
	 * Output the product features list (icon + label rows).
	 *
	 * @param array|string $atts Shortcode attributes. Accepts: post_id.
	 * @return string  HTML unordered list or empty string.
	 */
	public static function sc_features( array|string $atts ): string {
		$atts    = shortcode_atts( [ 'post_id' => 0 ], (array) $atts );
		$post_id = self::get_post_id( $atts );
		if ( ! $post_id ) {
			return '';
		}
		$features = (array) ( get_post_meta( $post_id, 'hcqb_features', true ) ?: [] );
		if ( empty( $features ) ) {
			return '';
		}
		ob_start();
		echo '<ul class="hcqb-features-list">';
		foreach ( $features as $f ) {
			$label   = sanitize_text_field( $f['label'] ?? '' );
			$icon_id = absint( $f['icon_id'] ?? 0 );
			if ( ! $label ) {
				continue;
			}
			echo '<li class="hcqb-feature">';
			if ( $icon_id ) {
				echo wp_get_attachment_image( $icon_id, [ 28, 28 ], false, [ 'class' => 'hcqb-feature__icon', 'alt' => '' ] );
			}
			echo '<span class="hcqb-feature__label">' . esc_html( $label ) . '</span>';
			echo '</li>';
		}
		echo '</ul>';
		return ob_get_clean();
	}

	// -------------------------------------------------------------------------
	// [hcqb_plan_document]
	// -------------------------------------------------------------------------

	/**
	 * Output a download link for the plan document.
	 *
	 * @param array|string $atts  Shortcode attributes. Accepts: post_id, label (default "Download Floor Plan").
	 * @return string  Anchor tag or empty string.
	 */
	public static function sc_plan_document( array|string $atts ): string {
		$atts    = shortcode_atts( [ 'post_id' => 0, 'label' => 'Download Floor Plan' ], (array) $atts );
		$post_id = self::get_post_id( $atts );
		if ( ! $post_id ) {
			return '';
		}
		$doc_id = absint( get_post_meta( $post_id, 'hcqb_plan_document', true ) );
		if ( ! $doc_id ) {
			return '';
		}
		$url = wp_get_attachment_url( $doc_id );
		if ( ! $url ) {
			return '';
		}
		return '<a href="' . esc_url( $url ) . '" class="hcqb-product-link" target="_blank" rel="noopener noreferrer">'
			. esc_html( $atts['label'] ) . '</a>';
	}

	// -------------------------------------------------------------------------
	// [hcqb_shipping_link]
	// -------------------------------------------------------------------------

	/**
	 * Output the shipping details link.
	 *
	 * @param array|string $atts  Shortcode attributes. Accepts: post_id, label (default "Shipping Details").
	 * @return string  Anchor tag or empty string.
	 */
	public static function sc_shipping_link( array|string $atts ): string {
		$atts    = shortcode_atts( [ 'post_id' => 0, 'label' => 'Shipping Details' ], (array) $atts );
		$post_id = self::get_post_id( $atts );
		if ( ! $post_id ) {
			return '';
		}
		$url = get_post_meta( $post_id, 'hcqb_shipping_details_link', true );
		if ( ! $url ) {
			return '';
		}
		return '<a href="' . esc_url( $url ) . '" class="hcqb-product-link" target="_blank" rel="noopener noreferrer">'
			. esc_html( $atts['label'] ) . '</a>';
	}

	// -------------------------------------------------------------------------
	// [hcqb_lease_price]
	// -------------------------------------------------------------------------

	/**
	 * Output the lease price (formatted). Returns empty string if lease is disabled.
	 *
	 * @param array|string $atts Shortcode attributes. Accepts: post_id.
	 * @return string  Formatted price string or empty.
	 */
	public static function sc_lease_price( array|string $atts ): string {
		$atts    = shortcode_atts( [ 'post_id' => 0 ], (array) $atts );
		$post_id = self::get_post_id( $atts );
		if ( ! $post_id || ! get_post_meta( $post_id, 'hcqb_lease_enabled', true ) ) {
			return '';
		}
		$price = (float) get_post_meta( $post_id, 'hcqb_lease_price', true );
		return esc_html( hcqb_format_price( $price ) );
	}

	// -------------------------------------------------------------------------
	// [hcqb_lease_price_label]
	// -------------------------------------------------------------------------

	/**
	 * Output the lease price label (e.g. "per week"). Returns empty if lease is disabled.
	 *
	 * @param array|string $atts Shortcode attributes. Accepts: post_id.
	 * @return string  Plain text label or empty.
	 */
	public static function sc_lease_price_label( array|string $atts ): string {
		$atts    = shortcode_atts( [ 'post_id' => 0 ], (array) $atts );
		$post_id = self::get_post_id( $atts );
		if ( ! $post_id || ! get_post_meta( $post_id, 'hcqb_lease_enabled', true ) ) {
			return '';
		}
		$label = get_post_meta( $post_id, 'hcqb_lease_price_label', true ) ?: 'per week';
		return esc_html( $label );
	}

	// -------------------------------------------------------------------------
	// [hcqb_lease_terms]
	// -------------------------------------------------------------------------

	/**
	 * Output the lease terms (rich text). Returns empty if lease is disabled.
	 *
	 * @param array|string $atts Shortcode attributes. Accepts: post_id.
	 * @return string  Filtered HTML or empty.
	 */
	public static function sc_lease_terms( array|string $atts ): string {
		$atts    = shortcode_atts( [ 'post_id' => 0 ], (array) $atts );
		$post_id = self::get_post_id( $atts );
		if ( ! $post_id || ! get_post_meta( $post_id, 'hcqb_lease_enabled', true ) ) {
			return '';
		}
		return wp_kses_post( wpautop( get_post_meta( $post_id, 'hcqb_lease_terms', true ) ) );
	}

	// -------------------------------------------------------------------------
	// [hcqb_lease_layout_title]
	// -------------------------------------------------------------------------

	/**
	 * Output the standard layout section title. Returns empty if lease is disabled.
	 *
	 * @param array|string $atts Shortcode attributes. Accepts: post_id.
	 * @return string  Plain text title or empty.
	 */
	public static function sc_lease_layout_title( array|string $atts ): string {
		$atts    = shortcode_atts( [ 'post_id' => 0 ], (array) $atts );
		$post_id = self::get_post_id( $atts );
		if ( ! $post_id || ! get_post_meta( $post_id, 'hcqb_lease_enabled', true ) ) {
			return '';
		}
		return esc_html( get_post_meta( $post_id, 'hcqb_lease_layout_title', true ) );
	}

	// -------------------------------------------------------------------------
	// [hcqb_lease_layout_desc]
	// -------------------------------------------------------------------------

	/**
	 * Output the standard layout section description (rich text). Returns empty if lease is disabled.
	 *
	 * @param array|string $atts Shortcode attributes. Accepts: post_id.
	 * @return string  Filtered HTML or empty.
	 */
	public static function sc_lease_layout_desc( array|string $atts ): string {
		$atts    = shortcode_atts( [ 'post_id' => 0 ], (array) $atts );
		$post_id = self::get_post_id( $atts );
		if ( ! $post_id || ! get_post_meta( $post_id, 'hcqb_lease_enabled', true ) ) {
			return '';
		}
		return wp_kses_post( wpautop( get_post_meta( $post_id, 'hcqb_lease_layout_description', true ) ) );
	}

	// -------------------------------------------------------------------------
	// [hcqb_lease_extras]
	// -------------------------------------------------------------------------

	/**
	 * Output the lease optional extras list. Returns empty if lease is disabled.
	 *
	 * @param array|string $atts Shortcode attributes. Accepts: post_id.
	 * @return string  HTML list or empty.
	 */
	public static function sc_lease_extras( array|string $atts ): string {
		$atts    = shortcode_atts( [ 'post_id' => 0 ], (array) $atts );
		$post_id = self::get_post_id( $atts );
		if ( ! $post_id || ! get_post_meta( $post_id, 'hcqb_lease_enabled', true ) ) {
			return '';
		}
		$extras = (array) ( get_post_meta( $post_id, 'hcqb_lease_extras', true ) ?: [] );
		if ( empty( $extras ) ) {
			return '';
		}
		ob_start();
		?>
		<div class="hcqb-lease-extras">
			<h4 class="hcqb-lease-extras__heading">Optional Extras</h4>
			<ul class="hcqb-lease-extras__list">
				<?php foreach ( $extras as $extra ) :
					$label = sanitize_text_field( $extra['label'] ?? '' );
					$price = (float) ( $extra['weekly_price'] ?? 0 );
					if ( ! $label ) continue;
				?>
				<li class="hcqb-lease-extra">
					<span class="hcqb-lease-extra__label"><?php echo esc_html( $label ); ?></span>
					<span class="hcqb-lease-extra__price"><?php echo esc_html( hcqb_format_price( $price ) ); ?> / week</span>
				</li>
				<?php endforeach; ?>
			</ul>
		</div>
		<?php
		return ob_get_clean();
	}

	// -------------------------------------------------------------------------
	// [hcqb_quote_button]
	// -------------------------------------------------------------------------

	/**
	 * Output the "Get a Custom Quote" button.
	 * Hidden via CSS if no active config exists (same pattern as the product page template).
	 *
	 * @param array|string $atts  Shortcode attributes. Accepts: post_id, label (default "Get a Custom Quote ↗").
	 * @return string  Anchor tag, or empty if Quote Builder page is not configured.
	 */
	public static function sc_quote_button( array|string $atts ): string {
		$atts    = shortcode_atts( [ 'post_id' => 0, 'label' => 'Get a Custom Quote ↗' ], (array) $atts );
		$post_id = self::get_post_id( $atts );
		if ( ! $post_id ) {
			return '';
		}
		$page_id  = (int) hcqb_get_setting( 'quote_builder_page_id' );
		$page_url = $page_id ? get_permalink( $page_id ) : '';
		if ( ! $page_url ) {
			return '';
		}
		$config = hcqb_get_active_config_for_product( $post_id );
		$hidden = $config ? '' : ' hcqb-btn--hidden';
		return '<a href="' . esc_url( $page_url ) . '" class="hcqb-btn hcqb-btn--quote' . esc_attr( $hidden ) . '">'
			. esc_html( $atts['label'] ) . '</a>';
	}

	// -------------------------------------------------------------------------
	// [hcqb_enquire_button]
	// -------------------------------------------------------------------------

	/**
	 * Output the "Enquire Now" button (lease view).
	 * Returns empty string if lease is not enabled on the product.
	 * The label defaults to the saved enquiry button label meta value.
	 *
	 * @param array|string $atts  Shortcode attributes. Accepts: post_id, label (default from saved meta or "Enquire Now").
	 * @return string  Anchor tag or empty.
	 */
	public static function sc_enquire_button( array|string $atts ): string {
		$atts    = shortcode_atts( [ 'post_id' => 0, 'label' => '' ], (array) $atts );
		$post_id = self::get_post_id( $atts );
		if ( ! $post_id || ! get_post_meta( $post_id, 'hcqb_lease_enabled', true ) ) {
			return '';
		}

		$label   = $atts['label'] ?: ( get_post_meta( $post_id, 'hcqb_enquiry_button_label', true ) ?: 'Enquire Now' );
		$form_id = (int) hcqb_get_setting( 'lease_enquiry_form_id' );

		// If a Fluent Form ID is set, render a modal button + modal.
		if ( $form_id > 0 ) {
			$modal_id   = 'hcqb-enquire-modal-' . $post_id;
			$form_html  = do_shortcode( '[fluentform id="' . $form_id . '"]' );

			$out  = '<button type="button" class="hcqb-btn hcqb-btn--enquire" data-hcqb-modal="' . esc_attr( $modal_id ) . '">'
				. esc_html( $label ) . '</button>';

			$out .= '<div id="' . esc_attr( $modal_id ) . '" class="hcqb-modal" hidden>';
			$out .= '<div class="hcqb-modal__backdrop"></div>';
			$out .= '<div class="hcqb-modal__dialog">';
			$out .= '<button type="button" class="hcqb-modal__close" aria-label="Close">&times;</button>';
			$out .= '<div class="hcqb-modal__body">' . $form_html . '</div>';
			$out .= '</div>';
			$out .= '</div>';

			// Inline script — only output once per page.
			if ( ! self::$enquire_modal_js_output ) {
				self::$enquire_modal_js_output = true;
				$out .= '<script>'
					. '(function(){'
					. 'function open(m){m.hidden=false;document.body.style.overflow="hidden";}'
					. 'function close(m){m.hidden=true;document.body.style.overflow="";}'
					. 'document.addEventListener("click",function(e){'
					. 'var btn=e.target.closest("[data-hcqb-modal]");'
					. 'if(btn){e.preventDefault();var m=document.getElementById(btn.dataset.hcqbModal);if(m)open(m);return;}'
					. 'if(e.target.closest(".hcqb-modal__close")||e.target.classList.contains("hcqb-modal__backdrop")){'
					. 'var m=e.target.closest(".hcqb-modal");if(m)close(m);}'
					. '});'
					. 'document.addEventListener("keydown",function(e){'
					. 'if(e.key==="Escape"){var m=document.querySelector(".hcqb-modal:not([hidden])");if(m)close(m);}'
					. '});'
					. '}());'
					. '</script>';
			}

			return $out;
		}

		// Fallback: link to the quote builder page.
		$page_id  = (int) hcqb_get_setting( 'quote_builder_page_id' );
		$page_url = $page_id ? get_permalink( $page_id ) : '';
		if ( ! $page_url ) {
			return '';
		}
		$config = hcqb_get_active_config_for_product( $post_id );
		$hidden = $config ? '' : ' hcqb-btn--hidden';
		return '<a href="' . esc_url( $page_url ) . '" class="hcqb-btn hcqb-btn--enquire' . esc_attr( $hidden ) . '">'
			. esc_html( $label ) . '</a>';
	}

	// -------------------------------------------------------------------------
	// [hc_product_grid]
	// -------------------------------------------------------------------------

	/**
	 * Render a responsive grid of published hc-containers products.
	 *
	 * Supported attributes:
	 *   columns  int     Number of columns (1–6). Default: 3.
	 *   limit    int     Maximum posts to display (1–100). Default: 12.
	 *   category string  container-type taxonomy slug to filter by. Default: all.
	 *   orderby  string  date | title | price. Default: date.
	 *   order    string  ASC | DESC. Default: DESC.
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string  HTML grid or empty string if no posts found.
	 */
	public static function sc_product_grid( array|string $atts ): string {
		$atts = shortcode_atts(
			[
				'columns'  => '3',
				'limit'    => '12',
				'category' => '',
				'orderby'  => 'date',
				'order'    => 'DESC',
			],
			(array) $atts
		);
		return self::render_grid( $atts, 'product' );
	}

	// -------------------------------------------------------------------------
	// [hc_lease_grid]
	// -------------------------------------------------------------------------

	/**
	 * Render a responsive grid of hc-containers products with lease enabled.
	 * Accepts the same attributes as [hc_product_grid].
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string  HTML grid or empty string if no posts found.
	 */
	public static function sc_lease_grid( array|string $atts ): string {
		$atts = shortcode_atts(
			[
				'columns'  => '3',
				'limit'    => '12',
				'category' => '',
				'orderby'  => 'date',
				'order'    => 'DESC',
			],
			(array) $atts
		);
		return self::render_grid( $atts, 'lease' );
	}

	// =========================================================================
	// [hc_quote_builder] — Stage 7
	// =========================================================================

	/**
	 * Render the quote builder.
	 *
	 * Loads the global default config from Settings → Quote Builder and renders
	 * the builder with no product context. The config holds all questions,
	 * image rules, and pricing — fully independent of hc-containers products.
	 *
	 * @param array|string $atts Shortcode attributes (none currently used).
	 * @return string  HTML output.
	 */
	public static function sc_quote_builder( array|string $atts ): string {
		$config_id = absint( hcqb_get_setting( 'default_config_id' ) );
		if ( ! $config_id ) {
			return self::render_builder_error();
		}
		$config = get_post( $config_id );
		if ( ! $config || 'hc-quote-configs' !== $config->post_type || 'publish' !== $config->post_status ) {
			return self::render_builder_error();
		}
		return self::render_builder( $config );
	}

	// -------------------------------------------------------------------------
	// Builder renderer
	// -------------------------------------------------------------------------

	private static function render_builder( WP_Post $config ): string {
		$questions   = get_post_meta( $config->ID, 'hcqb_questions',        true ) ?: [];
		$image_rules = get_post_meta( $config->ID, 'hcqb_image_rules',      true ) ?: [];
		$base_price  = (float) get_post_meta( $config->ID, 'hcqb_base_price', true );

		// Default image from config meta.
		$default_image_id  = absint( get_post_meta( $config->ID, 'hcqb_default_image_id', true ) );
		$default_image_url = $default_image_id
			? ( wp_get_attachment_image_url( $default_image_id, 'large' ) ?: '' )
			: '';

		// Process image rules — attach URLs, sort most-specific-first.
		foreach ( $image_rules as &$rule ) {
			$rule['image_url'] = wp_get_attachment_url( $rule['attachment_id'] ?? 0 ) ?: '';
		}
		unset( $rule );
		usort( $image_rules, fn( $a, $b ) => count( $b['match_tags'] ) <=> count( $a['match_tags'] ) );

		// Shipping rules — sort most-specific-first.
		$shipping_rules = get_post_meta( $config->ID, 'hcqb_shipping_rules', true ) ?: [];
		usort( $shipping_rules, fn( $a, $b ) => count( $b['match_tags'] ?? [] ) <=> count( $a['match_tags'] ?? [] ) );

		// Feature pill questions — max 4, in admin order.
		$pill_questions = array_map( function ( $q ) {
			$icon_id = absint( $q['pill_icon_id'] ?? 0 );
			$q['pill_icon_url'] = $icon_id ? ( wp_get_attachment_url( $icon_id ) ?: '' ) : '';
			return $q;
		}, array_slice(
			array_values( array_filter( $questions, fn( $q ) => ! empty( $q['show_in_pill'] ) ) ),
			0, 4
		) );

		// Frame 2 — variables passed into frame-2-contact.php.
		$prefix_options      = (array) ( hcqb_get_setting( 'prefix_options' )      ?: [ 'Mr', 'Mrs', 'Ms', 'Dr' ] );
		$supported_countries = (array) ( hcqb_get_setting( 'supported_countries' ) ?: [ 'AU' ] );
		$submit_button_label = hcqb_get_setting( 'submit_button_label' ) ?: 'Submit Quote Request';
		$consent_text        = hcqb_get_setting( 'consent_text' )        ?: 'I agree to be contacted regarding this quote request.';
		$privacy_fine_print  = hcqb_get_setting( 'privacy_fine_print' )  ?: '';
		$form_layout         = hcqb_get_setting( 'quote_form_layout' )   ?: 'multistep';

		// JSON config block for JS modules.
		$config_data = [
			'basePrice'       => $base_price,
			'questions'       => $questions,
			'imageRules'      => $image_rules,
			'shippingRules'   => $shipping_rules,
			'defaultImageUrl' => $default_image_url,
			'pillQuestions'   => array_map( fn( $q ) => $q['key'], $pill_questions ),
			'formLayout'      => $form_layout,
		];

		ob_start();
		echo '<script>window.HCQBConfig = ' . wp_json_encode( $config_data ) . ';</script>' . "\n";
		?>
		<div class="hcqb-quote-builder" id="hcqb-builder"
		     data-form-layout="<?php echo esc_attr( $form_layout ); ?>">
			<div class="hcqb-builder-layout" id="hcqb-frame-1">
				<?php
				include HCQB_PLUGIN_DIR . 'templates/quote-builder/frame-1-questions.php';
				include HCQB_PLUGIN_DIR . 'templates/quote-builder/frame-1-preview.php';
				?>
			</div><!-- .hcqb-builder-layout #hcqb-frame-1 -->
			<?php include HCQB_PLUGIN_DIR . 'templates/quote-builder/frame-2-contact.php'; ?>
		</div><!-- .hcqb-quote-builder -->
		<?php
		return ob_get_clean();
	}

	// -------------------------------------------------------------------------
	// Builder — error card (no config configured in settings)
	// -------------------------------------------------------------------------

	private static function render_builder_error(): string {
		ob_start();
		?>
		<div class="hcqb-builder-error">
			<h2 class="hcqb-builder-error__heading">Quote Not Configured</h2>
			<p  class="hcqb-builder-error__message">The quote builder has not been set up yet. Please check Settings &rarr; Quote Builder.</p>
			<a  class="hcqb-btn hcqb-btn--secondary" href="<?php echo esc_url( home_url( '/' ) ); ?>">
				&larr; Back to Home
			</a>
		</div>
		<?php
		return ob_get_clean();
	}

	// -------------------------------------------------------------------------
	// Grid renderer — shared by both grid shortcodes
	// -------------------------------------------------------------------------

	/**
	 * Build the WP_Query, loop, and render product cards.
	 *
	 * @param array  $atts      Sanitised shortcode attributes.
	 * @param string $card_type 'product' or 'lease' — passed into the card partial.
	 * @return string
	 */
	private static function render_grid( array $atts, string $card_type ): string {
		// Sanitise and clamp values.
		$columns  = max( 1, min( 6, absint( $atts['columns'] ) ) ) ?: 3;
		$limit    = max( 1, min( 100, absint( $atts['limit'] )  ) ) ?: 12;
		$category = sanitize_text_field( $atts['category'] );
		$order    = 'ASC' === strtoupper( $atts['order'] ) ? 'ASC' : 'DESC';

		// Orderby whitelist — 'price' maps to meta_value_num.
		$allowed_orderby = [ 'date', 'title', 'price' ];
		$orderby_raw     = in_array( $atts['orderby'], $allowed_orderby, true ) ? $atts['orderby'] : 'date';

		// Base query args.
		$query_args = [
			'post_type'      => 'hc-containers',
			'post_status'    => 'publish',
			'posts_per_page' => $limit,
			'no_found_rows'  => true,
			'order'          => $order,
		];

		if ( 'price' === $orderby_raw ) {
			$query_args['orderby']  = 'meta_value_num';
			$query_args['meta_key'] = 'hcqb_product_price';
		} else {
			$query_args['orderby'] = $orderby_raw;
		}

		// Lease grid: restrict to products with lease enabled.
		if ( 'lease' === $card_type ) {
			$query_args['meta_query'] = [
				[
					'key'   => 'hcqb_lease_enabled',
					'value' => '1',
				],
			];
		}

		// Optional taxonomy filter.
		if ( $category ) {
			$query_args['tax_query'] = [
				[
					'taxonomy' => 'container-type',
					'field'    => 'slug',
					'terms'    => $category,
				],
			];
		}

		$query = new WP_Query( $query_args );

		if ( ! $query->have_posts() ) {
			return '';
		}

		ob_start();
		?>
		<div class="hcqb-grid" style="--hcqb-grid-cols: <?php echo esc_attr( $columns ); ?>;">
			<?php
			while ( $query->have_posts() ) {
				$query->the_post();
				include HCQB_PLUGIN_DIR . 'templates/product-card.php';
			}
			wp_reset_postdata();
			?>
		</div>
		<?php
		return ob_get_clean();
	}
}
