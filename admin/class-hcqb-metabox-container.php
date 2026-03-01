<?php
/**
 * HCQB_Metabox_Container
 *
 * Tabbed meta box on the hc-containers edit screen.
 * Tab 1 — Product Info: gallery, features repeater, pricing, description, plan doc.
 * Tab 2 — Lease Info: enable toggle, lease pricing, terms, extras repeater.
 *
 * All meta keys are prefixed hcqb_.
 * Save handler verifies nonce, capability, and sanitises all input before storing.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HCQB_Metabox_Container {

	// -------------------------------------------------------------------------
	// Registration
	// -------------------------------------------------------------------------

	public static function register(): void {
		add_meta_box(
			'hcqb-container-settings',
			'Container Settings',
			[ __CLASS__, 'render' ],
			'hc-containers',
			'normal',
			'high'
		);
	}

	// -------------------------------------------------------------------------
	// Render
	// -------------------------------------------------------------------------

	public static function render( WP_Post $post ): void {
		wp_nonce_field( 'hcqb_container_save', 'hcqb_container_nonce' );
		?>
		<div class="hcqb-metabox-tabs">
			<button type="button"
			        class="hcqb-metabox-tab-btn hcqb-metabox-tab-btn--active"
			        data-target="hcqb-panel-product">
				Product Info
			</button>
			<button type="button"
			        class="hcqb-metabox-tab-btn"
			        data-target="hcqb-panel-lease">
				Lease Info
			</button>
		</div>

		<div id="hcqb-panel-product" class="hcqb-metabox-panel hcqb-metabox-panel--active">
			<?php self::render_tab_product( $post ); ?>
		</div>

		<div id="hcqb-panel-lease" class="hcqb-metabox-panel">
			<?php self::render_tab_lease( $post ); ?>
		</div>
		<?php
	}

	// -------------------------------------------------------------------------
	// Tab 1 — Product Info
	// -------------------------------------------------------------------------

	private static function render_tab_product( WP_Post $post ): void {
		$short_desc      = get_post_meta( $post->ID, 'hcqb_short_description',  true );
		$price           = get_post_meta( $post->ID, 'hcqb_product_price',       true );
		$rating          = get_post_meta( $post->ID, 'hcqb_star_rating',         true );
		$review_count    = get_post_meta( $post->ID, 'hcqb_review_count',        true );
		$images          = get_post_meta( $post->ID, 'hcqb_product_images',      true ) ?: [];
		$features        = get_post_meta( $post->ID, 'hcqb_features',            true ) ?: [];
		$description     = get_post_meta( $post->ID, 'hcqb_product_description', true );
		$additional_notes = get_post_meta( $post->ID, 'hcqb_additional_notes',  true );
		$plan_doc_id     = (int) get_post_meta( $post->ID, 'hcqb_plan_document', true );
		$shipping_link   = get_post_meta( $post->ID, 'hcqb_shipping_details_link', true );
		?>

		<table class="form-table hcqb-meta-table" role="presentation">
			<tr>
				<th scope="row"><label for="hcqb_short_description">Short Description</label></th>
				<td>
					<textarea id="hcqb_short_description"
					          name="hcqb_short_description"
					          rows="2"
					          class="large-text"><?php echo esc_textarea( $short_desc ); ?></textarea>
					<p class="description">Displayed as the product sub-heading on the product page and in grid cards.</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="hcqb_product_price">Base Price ($)</label></th>
				<td>
					<input type="number"
					       id="hcqb_product_price"
					       name="hcqb_product_price"
					       value="<?php echo esc_attr( $price ); ?>"
					       class="regular-text"
					       step="0.01"
					       min="0">
					<p class="description">Flat-pack base price. Used as the starting price in the quote builder.</p>
				</td>
			</tr>
			<tr>
				<th scope="row">Rating</th>
				<td>
					<label for="hcqb_star_rating">Stars</label>
					<input type="number"
					       id="hcqb_star_rating"
					       name="hcqb_star_rating"
					       value="<?php echo esc_attr( $rating ); ?>"
					       class="small-text"
					       step="0.1"
					       min="0"
					       max="5">
					&nbsp;&nbsp;
					<label for="hcqb_review_count">Reviews</label>
					<input type="number"
					       id="hcqb_review_count"
					       name="hcqb_review_count"
					       value="<?php echo esc_attr( $review_count ); ?>"
					       class="small-text"
					       min="0">
				</td>
			</tr>
		</table>

		<?php /* ---- Product Images Gallery ---- */ ?>
		<div class="hcqb-section-heading">Product Images</div>

		<div class="hcqb-gallery" id="hcqb-product-gallery">
			<div class="hcqb-gallery__list" id="hcqb-gallery-list">
				<?php foreach ( $images as $img_id ) :
					$thumb_url = wp_get_attachment_image_url( $img_id, 'thumbnail' );
					if ( ! $thumb_url ) continue;
					?>
					<div class="hcqb-gallery__item" draggable="true" data-id="<?php echo esc_attr( $img_id ); ?>">
						<img src="<?php echo esc_url( $thumb_url ); ?>" alt="">
						<input type="hidden" name="hcqb_product_images[]" value="<?php echo esc_attr( $img_id ); ?>">
						<button type="button" class="hcqb-gallery__remove" title="Remove image">&times;</button>
					</div>
				<?php endforeach; ?>
			</div>
			<button type="button" class="button" id="hcqb-add-images">+ Add Images</button>
			<p class="description">Drag thumbnails to reorder. The first image is the primary product image.</p>
		</div>

		<?php /* ---- Features Repeater ---- */ ?>
		<div class="hcqb-section-heading">Product Features</div>

		<div class="hcqb-repeater" id="hcqb-features-repeater">
			<div class="hcqb-repeater__list" id="hcqb-features-list">
				<?php foreach ( $features as $feat_index => $feature ) :
					$icon_id  = (int) ( $feature['icon_id'] ?? 0 );
					$label    = $feature['label'] ?? '';
					$icon_url = $icon_id ? wp_get_attachment_image_url( $icon_id, 'thumbnail' ) : '';
					?>
					<div class="hcqb-repeater__row hcqb-feature-row">
						<span class="hcqb-repeater__handle dashicons dashicons-menu" title="Drag to reorder"></span>
						<div class="hcqb-icon-picker">
							<input type="hidden" name="hcqb_features[<?php echo $feat_index; ?>][icon_id]" value="<?php echo esc_attr( $icon_id ); ?>">
							<?php if ( $icon_url ) : ?>
								<img class="hcqb-icon-preview" src="<?php echo esc_url( $icon_url ); ?>" alt="" width="36" height="36">
							<?php else : ?>
								<span class="hcqb-icon-preview hcqb-icon-preview--empty"></span>
							<?php endif; ?>
							<button type="button" class="button hcqb-choose-icon">Choose Icon</button>
						</div>
						<input type="text"
						       name="hcqb_features[<?php echo $feat_index; ?>][label]"
						       value="<?php echo esc_attr( $label ); ?>"
						       class="regular-text"
						       placeholder="Feature label">
						<button type="button" class="button hcqb-repeater__remove">Remove</button>
					</div>
				<?php endforeach; ?>
			</div>
			<button type="button" class="button" id="hcqb-add-feature">+ Add Feature</button>
		</div>

		<?php /* ---- Product Description ---- */ ?>
		<div class="hcqb-section-heading">Product Description</div>
		<?php
		wp_editor(
			$description ?? '',
			'hcqb_product_description',
			[
				'textarea_name' => 'hcqb_product_description',
				'media_buttons' => false,
				'teeny'         => false,
				'textarea_rows' => 8,
			]
		);
		?>

		<?php /* ---- Additional Notes ---- */ ?>
		<div class="hcqb-section-heading">Additional Notes</div>
		<?php
		wp_editor(
			$additional_notes ?? '',
			'hcqb_additional_notes',
			[
				'textarea_name' => 'hcqb_additional_notes',
				'media_buttons' => false,
				'teeny'         => false,
				'textarea_rows' => 6,
			]
		);
		?>

		<table class="form-table hcqb-meta-table" role="presentation" style="margin-top:16px;">
			<tr>
				<th scope="row">Plan Document</th>
				<td>
					<div class="hcqb-file-picker" id="hcqb-plan-doc-picker">
						<input type="hidden" id="hcqb_plan_document" name="hcqb_plan_document" value="<?php echo esc_attr( $plan_doc_id ); ?>">
						<span class="hcqb-file-name" id="hcqb-plan-doc-name">
							<?php
							if ( $plan_doc_id ) {
								$file = get_attached_file( $plan_doc_id );
								echo esc_html( $file ? basename( $file ) : __( 'File attached', 'hc-quote-builder' ) );
							} else {
								echo '<em>No file selected</em>';
							}
							?>
						</span>
						<button type="button" class="button" id="hcqb-choose-plan-doc">Choose File</button>
						<?php if ( $plan_doc_id ) : ?>
							<button type="button" class="button hcqb-remove-file" id="hcqb-remove-plan-doc">Remove</button>
						<?php endif; ?>
					</div>
					<p class="description">PDF or document file for customer download.</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="hcqb_shipping_details_link">Shipping Details Link</label></th>
				<td>
					<input type="url"
					       id="hcqb_shipping_details_link"
					       name="hcqb_shipping_details_link"
					       value="<?php echo esc_url( $shipping_link ); ?>"
					       class="large-text"
					       placeholder="https://">
				</td>
			</tr>
		</table>
		<?php
	}

	// -------------------------------------------------------------------------
	// Tab 2 — Lease Info
	// -------------------------------------------------------------------------

	private static function render_tab_lease( WP_Post $post ): void {
		$lease_enabled      = (int) get_post_meta( $post->ID, 'hcqb_lease_enabled',             true );
		$lease_price        = get_post_meta( $post->ID, 'hcqb_lease_price',                     true );
		$lease_price_label  = get_post_meta( $post->ID, 'hcqb_lease_price_label',               true );
		$lease_terms        = get_post_meta( $post->ID, 'hcqb_lease_terms',                     true );
		$layout_title       = get_post_meta( $post->ID, 'hcqb_lease_layout_title',              true );
		$layout_description = get_post_meta( $post->ID, 'hcqb_lease_layout_description',        true );
		$extras             = get_post_meta( $post->ID, 'hcqb_lease_extras',                    true ) ?: [];
		$enquiry_label      = get_post_meta( $post->ID, 'hcqb_enquiry_button_label',             true );
		?>

		<table class="form-table hcqb-meta-table" role="presentation">
			<tr>
				<th scope="row">Enable Lease</th>
				<td>
					<label class="hcqb-toggle">
						<input type="hidden" name="hcqb_lease_enabled" value="0">
						<input type="checkbox"
						       name="hcqb_lease_enabled"
						       value="1"
						       <?php checked( $lease_enabled, 1 ); ?>>
						<span class="hcqb-toggle__track"></span>
						Enable lease pricing for this product
					</label>
					<p class="description">When enabled, this product appears in the Lease Grid and the product page shows a Lease view.</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="hcqb_lease_price">Lease Price ($)</label></th>
				<td>
					<input type="number"
					       id="hcqb_lease_price"
					       name="hcqb_lease_price"
					       value="<?php echo esc_attr( $lease_price ); ?>"
					       class="regular-text"
					       step="0.01"
					       min="0">
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="hcqb_lease_price_label">Lease Price Label</label></th>
				<td>
					<input type="text"
					       id="hcqb_lease_price_label"
					       name="hcqb_lease_price_label"
					       value="<?php echo esc_attr( $lease_price_label ?: 'per week' ); ?>"
					       class="regular-text"
					       placeholder="per week">
					<p class="description">Displayed alongside the lease price (e.g. "per week").</p>
				</td>
			</tr>
		</table>

		<?php /* ---- Lease Terms ---- */ ?>
		<div class="hcqb-section-heading">Lease Terms</div>
		<?php
		wp_editor(
			$lease_terms ?? '',
			'hcqb_lease_terms',
			[
				'textarea_name' => 'hcqb_lease_terms',
				'media_buttons' => false,
				'teeny'         => false,
				'textarea_rows' => 6,
			]
		);
		?>

		<table class="form-table hcqb-meta-table" role="presentation" style="margin-top:16px;">
			<tr>
				<th scope="row"><label for="hcqb_lease_layout_title">Standard Layout Title</label></th>
				<td>
					<input type="text"
					       id="hcqb_lease_layout_title"
					       name="hcqb_lease_layout_title"
					       value="<?php echo esc_attr( $layout_title ); ?>"
					       class="regular-text">
				</td>
			</tr>
		</table>

		<?php /* ---- Standard Layout Description ---- */ ?>
		<div class="hcqb-section-heading">Standard Layout Description</div>
		<?php
		wp_editor(
			$layout_description ?? '',
			'hcqb_lease_layout_description',
			[
				'textarea_name' => 'hcqb_lease_layout_description',
				'media_buttons' => false,
				'teeny'         => false,
				'textarea_rows' => 6,
			]
		);
		?>

		<?php /* ---- Lease Optional Extras ---- */ ?>
		<div class="hcqb-section-heading">Optional Extras</div>
		<p>Additional items the customer can add to their lease. Prices shown as weekly.</p>

		<div class="hcqb-repeater" id="hcqb-extras-repeater">
			<div class="hcqb-repeater__list" id="hcqb-extras-list">
				<?php foreach ( $extras as $extra_index => $extra ) : ?>
					<div class="hcqb-repeater__row hcqb-extra-row">
						<span class="hcqb-repeater__handle dashicons dashicons-menu" title="Drag to reorder"></span>
						<input type="text"
						       name="hcqb_lease_extras[<?php echo $extra_index; ?>][label]"
						       value="<?php echo esc_attr( $extra['label'] ?? '' ); ?>"
						       class="regular-text"
						       placeholder="Extra label">
						<input type="number"
						       name="hcqb_lease_extras[<?php echo $extra_index; ?>][weekly_price]"
						       value="<?php echo esc_attr( $extra['weekly_price'] ?? '' ); ?>"
						       class="small-text"
						       step="0.01"
						       min="0"
						       placeholder="0.00">
						<span class="hcqb-extras-unit">/ week</span>
						<button type="button" class="button hcqb-repeater__remove">Remove</button>
					</div>
				<?php endforeach; ?>
			</div>
			<button type="button" class="button" id="hcqb-add-extra">+ Add Extra</button>
		</div>

		<table class="form-table hcqb-meta-table" role="presentation">
			<tr>
				<th scope="row"><label for="hcqb_enquiry_button_label">Enquiry Button Label</label></th>
				<td>
					<input type="text"
					       id="hcqb_enquiry_button_label"
					       name="hcqb_enquiry_button_label"
					       value="<?php echo esc_attr( $enquiry_label ?: 'Enquire Now' ); ?>"
					       class="regular-text"
					       placeholder="Enquire Now">
				</td>
			</tr>
		</table>
		<?php
	}

	// -------------------------------------------------------------------------
	// Save handler
	// -------------------------------------------------------------------------

	public static function save( int $post_id ): void {
		// Skip autosave requests.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Skip revisions.
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		// Only process hc-containers posts.
		if ( 'hc-containers' !== get_post_type( $post_id ) ) {
			return;
		}

		// Nonce must be present and valid.
		if ( ! isset( $_POST['hcqb_container_nonce'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( $_POST['hcqb_container_nonce'], 'hcqb_container_save' ) ) {
			return;
		}

		// Current user must be able to edit this post.
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		self::save_tab_product( $post_id );
		self::save_tab_lease( $post_id );
	}

	// -------------------------------------------------------------------------
	// Tab 1 save — Product Info
	// -------------------------------------------------------------------------

	private static function save_tab_product( int $post_id ): void {
		// Simple text / numeric fields.
		update_post_meta( $post_id, 'hcqb_short_description', sanitize_textarea_field( $_POST['hcqb_short_description'] ?? '' ) );
		update_post_meta( $post_id, 'hcqb_product_price',     (float) ( $_POST['hcqb_product_price'] ?? 0 ) );
		update_post_meta( $post_id, 'hcqb_star_rating',       (float) ( $_POST['hcqb_star_rating'] ?? 0 ) );
		update_post_meta( $post_id, 'hcqb_review_count',      absint( $_POST['hcqb_review_count'] ?? 0 ) );
		update_post_meta( $post_id, 'hcqb_additional_notes',  wp_kses_post( $_POST['hcqb_additional_notes'] ?? '' ) );
		update_post_meta( $post_id, 'hcqb_plan_document',     absint( $_POST['hcqb_plan_document'] ?? 0 ) );
		update_post_meta( $post_id, 'hcqb_shipping_details_link', esc_url_raw( $_POST['hcqb_shipping_details_link'] ?? '' ) );

		// Rich text — wp_kses_post allows formatting tags.
		update_post_meta( $post_id, 'hcqb_product_description', wp_kses_post( $_POST['hcqb_product_description'] ?? '' ) );

		// Gallery — sanitise each ID as an integer; discard zeros.
		$images = array_map( 'absint', (array) ( $_POST['hcqb_product_images'] ?? [] ) );
		$images = array_values( array_filter( $images ) );
		update_post_meta( $post_id, 'hcqb_product_images', $images );

		// Features repeater — discard rows with no label.
		$features = [];
		foreach ( (array) ( $_POST['hcqb_features'] ?? [] ) as $row ) {
			$icon_id = absint( $row['icon_id'] ?? 0 );
			$label   = sanitize_text_field( $row['label'] ?? '' );
			if ( '' !== $label ) {
				$features[] = [ 'icon_id' => $icon_id, 'label' => $label ];
			}
		}
		update_post_meta( $post_id, 'hcqb_features', $features );
	}

	// -------------------------------------------------------------------------
	// Tab 2 save — Lease Info
	// -------------------------------------------------------------------------

	private static function save_tab_lease( int $post_id ): void {
		// Toggle — hidden input ensures 0 is submitted when unchecked.
		update_post_meta( $post_id, 'hcqb_lease_enabled',      absint( $_POST['hcqb_lease_enabled'] ?? 0 ) );
		update_post_meta( $post_id, 'hcqb_lease_price',        (float) ( $_POST['hcqb_lease_price'] ?? 0 ) );
		update_post_meta( $post_id, 'hcqb_lease_price_label',  sanitize_text_field( $_POST['hcqb_lease_price_label'] ?? '' ) );
		update_post_meta( $post_id, 'hcqb_lease_layout_title', sanitize_text_field( $_POST['hcqb_lease_layout_title'] ?? '' ) );
		update_post_meta( $post_id, 'hcqb_enquiry_button_label', sanitize_text_field( $_POST['hcqb_enquiry_button_label'] ?? '' ) );

		// Rich text editors.
		update_post_meta( $post_id, 'hcqb_lease_terms',             wp_kses_post( $_POST['hcqb_lease_terms'] ?? '' ) );
		update_post_meta( $post_id, 'hcqb_lease_layout_description', wp_kses_post( $_POST['hcqb_lease_layout_description'] ?? '' ) );

		// Extras repeater — discard rows with no label.
		$extras = [];
		foreach ( (array) ( $_POST['hcqb_lease_extras'] ?? [] ) as $row ) {
			$label       = sanitize_text_field( $row['label'] ?? '' );
			$weekly_price = (float) ( $row['weekly_price'] ?? 0 );
			if ( '' !== $label ) {
				$extras[] = [ 'label' => $label, 'weekly_price' => $weekly_price ];
			}
		}
		update_post_meta( $post_id, 'hcqb_lease_extras', $extras );
	}
}
