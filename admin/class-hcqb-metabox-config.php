<?php
/**
 * HCQB_Metabox_Config
 *
 * Registers and handles all meta boxes on the hc-quote-configs edit screen:
 *   - Config Info   (sidebar) : config status + linked product (1:1 enforcement)
 *   - Questions Builder (main): question/option repeaters, _uid slug-immutability
 *   - Image Rules   (main)    : per-view image assignment with option-slug match tags
 *
 * Slug immutability — the _uid pattern:
 *   JS assigns a unique _uid to each question/option row on creation.
 *   That _uid is stored in a hidden input and persists across saves.
 *   The PHP save handler looks up the existing slug/key by _uid and reuses it —
 *   a new slug is only generated when the _uid has never been seen before.
 *
 * Also registers:
 *   admin_action_hcqb_duplicate_config — clone post, clear linked product
 *   post_row_actions filter             — adds "Duplicate" link to list table
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HCQB_Metabox_Config {

	// =========================================================================
	// Meta box registration
	// =========================================================================

	public static function register(): void {
		add_meta_box(
			'hcqb-config-info',
			'Config Info',
			[ self::class, 'render_meta_box_info' ],
			'hc-quote-configs',
			'side',
			'high'
		);

		add_meta_box(
			'hcqb-config-questions',
			'Questions Builder',
			[ self::class, 'render_meta_box_questions' ],
			'hc-quote-configs',
			'normal',
			'high'
		);

		add_meta_box(
			'hcqb-config-image-rules',
			'Image Rules',
			[ self::class, 'render_meta_box_image_rules' ],
			'hc-quote-configs',
			'normal',
			'default'
		);
	}

	// =========================================================================
	// Render — Config Info (sidebar)
	// =========================================================================

	public static function render_meta_box_info( WP_Post $post ): void {
		wp_nonce_field( 'hcqb_save_config_' . $post->ID, 'hcqb_config_nonce' );

		$status            = get_post_meta( $post->ID, 'hcqb_config_status',    true ) ?: 'inactive';
		$linked_product    = (int)   get_post_meta( $post->ID, 'hcqb_linked_product',   true );
		$base_price        = (float) get_post_meta( $post->ID, 'hcqb_base_price',        true );
		$default_image_id  = absint(  get_post_meta( $post->ID, 'hcqb_default_image_id', true ) );
		$default_image_url = $default_image_id
			? ( wp_get_attachment_image_url( $default_image_id, 'thumbnail' ) ?: '' )
			: '';
		$taken_ids         = self::get_taken_product_ids( $post->ID );

		$containers = get_posts( [
			'post_type'      => 'hc-containers',
			'post_status'    => 'publish',
			'numberposts'    => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'no_found_rows'  => true,
		] );
		?>
		<table class="form-table hcqb-meta-table">
			<tr>
				<th><label for="hcqb_config_status">Status</label></th>
				<td>
					<select name="hcqb_config_status" id="hcqb_config_status">
						<option value="inactive" <?php selected( $status, 'inactive' ); ?>>Inactive</option>
						<option value="active"   <?php selected( $status, 'active'   ); ?>>Active</option>
					</select>
				</td>
			</tr>
			<tr>
				<th><label for="hcqb_linked_product">Linked Product</label></th>
				<td>
					<select name="hcqb_linked_product" id="hcqb_linked_product">
						<option value="0">— None —</option>
						<?php foreach ( $containers as $container ) :
							$is_taken    = in_array( $container->ID, $taken_ids, true );
							$is_selected = ( $linked_product === $container->ID );
						?>
						<option
							value="<?php echo esc_attr( $container->ID ); ?>"
							<?php selected( $is_selected ); ?>
							<?php disabled( $is_taken && ! $is_selected ); ?>
						><?php
							echo esc_html( $container->post_title );
							if ( $is_taken && ! $is_selected ) {
								echo esc_html( ' (already linked)' );
							}
						?></option>
						<?php endforeach; ?>
					</select>
					<p class="description">One product may be linked to only one config.</p>
				</td>
			</tr>
			<tr>
				<th><label for="hcqb_base_price">Base Price</label></th>
				<td>
					<span style="line-height:30px;">$&nbsp;</span><input type="number"
					       id="hcqb_base_price" name="hcqb_base_price"
					       value="<?php echo esc_attr( $base_price ); ?>"
					       min="0" step="0.01" style="width:110px;">
					<p class="description">Starting price shown in the builder when used in standalone mode (no <code>?product=</code>).</p>
				</td>
			</tr>
			<tr>
				<th>Default Image</th>
				<td>
					<div id="hcqb-default-image-wrap" style="margin-bottom:8px;">
						<?php if ( $default_image_url ) : ?>
						<img src="<?php echo esc_url( $default_image_url ); ?>"
						     width="60" height="60"
						     style="object-fit:cover;border-radius:4px;display:block;">
						<?php else : ?>
						<span style="color:#999;font-size:12px;">No image selected</span>
						<?php endif; ?>
					</div>
					<input type="hidden" name="hcqb_default_image_id" id="hcqb_default_image_id"
					       value="<?php echo esc_attr( $default_image_id ?: 0 ); ?>">
					<button type="button" class="button" id="hcqb-choose-default-image">Choose Image</button>
					<button type="button" class="button" id="hcqb-remove-default-image"
					        <?php echo $default_image_id ? '' : 'style="display:none;"'; ?>>Remove</button>
					<p class="description">Preview image shown before any option is selected (standalone mode only).</p>
				</td>
			</tr>
		</table>
		<?php
	}

	// =========================================================================
	// Render — Questions Builder (main column)
	// =========================================================================

	public static function render_meta_box_questions( WP_Post $post ): void {
		$questions = get_post_meta( $post->ID, 'hcqb_questions', true );
		if ( ! is_array( $questions ) ) {
			$questions = [];
		}
		?>
		<div class="hcqb-questions-wrap">
			<div class="hcqb-questions-list" id="hcqb-questions-list">
				<?php foreach ( $questions as $idx => $q ) : ?>
					<?php self::render_question_row( $q, $idx ); ?>
				<?php endforeach; ?>
			</div>
			<button type="button" class="button button-primary hcqb-add-question" id="hcqb-add-question">
				+ Add Question
			</button>
		</div>
		<?php
	}

	// =========================================================================
	// Render — single question row
	// =========================================================================

	private static function render_question_row( array $q, int $idx ): void {
		$uid        = esc_attr( $q['_uid']           ?? '' );
		$key        = esc_attr( $q['key']             ?? '' );
		$label      = esc_attr( $q['label']           ?? '' );
		$input_type = $q['input_type']                 ?? 'radio';
		$required   = ! empty( $q['required']         ) ? '1' : '0';
		$helper     = esc_attr( $q['helper_text']     ?? '' );
		$show_pill  = ! empty( $q['show_in_pill']     ) ? '1' : '0';
		$is_cond    = ! empty( $q['is_conditional']   ) ? '1' : '0';
		$sw_q       = esc_attr( $q['show_when_question'] ?? '' );
		$sw_o       = esc_attr( $q['show_when_option']   ?? '' );
		$options    = is_array( $q['options'] ?? null ) ? $q['options'] : [];
		$base       = "hcqb_questions[{$idx}]";
		?>
		<div class="hcqb-question-row" data-uid="<?php echo $uid; ?>">

			<!-- Header — always visible -->
			<div class="hcqb-question-header">
				<span class="hcqb-repeater__handle dashicons dashicons-menu" title="Drag to reorder"></span>
				<span class="hcqb-question-label-preview"><?php
					echo $label ? esc_html( $q['label'] ) : '<em>New Question</em>';
				?></span>
				<span class="hcqb-slug-locked" title="Question key — immutable after first save"><?php
					echo $key ? esc_html( $q['key'] ) : '<em>auto</em>';
				?></span>
				<button type="button" class="button hcqb-toggle-question">Expand</button>
				<button type="button" class="button hcqb-repeater__remove">Remove</button>
			</div>

			<!-- Body — shown/hidden by JS -->
			<div class="hcqb-question-body hcqb-hidden">

				<input type="hidden" name="<?php echo esc_attr( $base ); ?>[_uid]" value="<?php echo $uid; ?>">
				<input type="hidden" name="<?php echo esc_attr( $base ); ?>[key]"  value="<?php echo $key; ?>" class="hcqb-key-hidden">

				<table class="form-table hcqb-meta-table hcqb-question-table">
					<tr>
						<th><label>Label</label></th>
						<td>
							<input type="text"
								name="<?php echo esc_attr( $base ); ?>[label]"
								value="<?php echo $label; ?>"
								class="regular-text hcqb-question-label-input"
								placeholder="Question label">
						</td>
					</tr>
					<tr>
						<th><label>Input Type</label></th>
						<td>
							<select name="<?php echo esc_attr( $base ); ?>[input_type]">
								<option value="radio"    <?php selected( $input_type, 'radio'    ); ?>>Radio buttons</option>
								<option value="dropdown" <?php selected( $input_type, 'dropdown' ); ?>>Dropdown</option>
								<option value="checkbox" <?php selected( $input_type, 'checkbox' ); ?>>Checkboxes (multi-select)</option>
							</select>
						</td>
					</tr>
					<tr>
						<th>Required</th>
						<td>
							<label>
								<input type="checkbox"
									name="<?php echo esc_attr( $base ); ?>[required]"
									value="1"
									<?php checked( $required, '1' ); ?>>
								Customer must answer this question
							</label>
						</td>
					</tr>
					<tr>
						<th><label>Helper Text</label></th>
						<td>
							<input type="text"
								name="<?php echo esc_attr( $base ); ?>[helper_text]"
								value="<?php echo $helper; ?>"
								class="regular-text"
								placeholder="Optional hint shown below the question">
						</td>
					</tr>
					<tr>
						<th>Show in Pill</th>
						<td>
							<label>
								<input type="checkbox"
									name="<?php echo esc_attr( $base ); ?>[show_in_pill]"
									value="1"
									<?php checked( $show_pill, '1' ); ?>>
								Display selection as a feature pill (max 4 pills total)
							</label>
						</td>
					</tr>
					<tr>
						<th>Conditional</th>
						<td>
							<label>
								<input type="checkbox"
									name="<?php echo esc_attr( $base ); ?>[is_conditional]"
									value="1"
									<?php checked( $is_cond, '1' ); ?>
									class="hcqb-is-conditional-check">
								Only show when another question's answer is…
							</label>
							<div class="hcqb-conditional-fields<?php echo $is_cond ? '' : ' hcqb-hidden'; ?>">
								<select name="<?php echo esc_attr( $base ); ?>[show_when_question]"
									class="hcqb-show-when-question">
									<option value="">— Select question —</option>
									<?php /* Options populated by JS on page load */ ?>
								</select>
								<select name="<?php echo esc_attr( $base ); ?>[show_when_option]"
									class="hcqb-show-when-option">
									<option value="">— Select option —</option>
									<?php /* Options populated by JS after question select */ ?>
								</select>
								<?php if ( $sw_q ) : ?>
									<input type="hidden" class="hcqb-init-sw-q" value="<?php echo $sw_q; ?>">
								<?php endif; ?>
								<?php if ( $sw_o ) : ?>
									<input type="hidden" class="hcqb-init-sw-o" value="<?php echo $sw_o; ?>">
								<?php endif; ?>
							</div>
						</td>
					</tr>
				</table>

				<!-- Options repeater -->
				<div class="hcqb-options-wrap">
					<h4 class="hcqb-section-heading">Options</h4>
					<div class="hcqb-options-list">
						<?php foreach ( $options as $o_idx => $opt ) : ?>
							<?php self::render_option_row( $opt, $idx, $o_idx ); ?>
						<?php endforeach; ?>
					</div>
					<button type="button" class="button hcqb-add-option">+ Add Option</button>
				</div>

			</div><!-- .hcqb-question-body -->
		</div><!-- .hcqb-question-row -->
		<?php
	}

	// =========================================================================
	// Render — single option row
	// =========================================================================

	private static function render_option_row( array $opt, int $q_idx, int $o_idx ): void {
		$uid         = esc_attr( $opt['_uid']         ?? '' );
		$slug        = esc_attr( $opt['slug']          ?? '' );
		$label       = esc_attr( $opt['label']         ?? '' );
		$price       = isset( $opt['price'] ) ? floatval( $opt['price'] ) : 0.0;
		$price_type  = $opt['price_type']              ?? 'addition';
		$role        = $opt['option_role']             ?? 'standard';
		$affects_img = ! empty( $opt['affects_image'] ) ? '1' : '0';
		$base        = "hcqb_questions[{$q_idx}][options][{$o_idx}]";
		?>
		<div class="hcqb-repeater__row hcqb-option-row" data-uid="<?php echo $uid; ?>">
			<span class="hcqb-repeater__handle dashicons dashicons-menu" title="Drag to reorder"></span>
			<input type="hidden" name="<?php echo esc_attr( $base ); ?>[_uid]" value="<?php echo $uid;  ?>">
			<input type="hidden" name="<?php echo esc_attr( $base ); ?>[slug]" value="<?php echo $slug; ?>" class="hcqb-option-slug-hidden">
			<input type="text"
				name="<?php echo esc_attr( $base ); ?>[label]"
				value="<?php echo $label; ?>"
				class="regular-text hcqb-option-label-input"
				placeholder="Option label">
			<span class="hcqb-slug-locked hcqb-option-slug-display" title="Option slug — immutable after first save"><?php
				echo $slug ? esc_html( $opt['slug'] ) : '<em>auto</em>';
			?></span>
			<span class="hcqb-extras-unit">$</span>
			<input type="number"
				name="<?php echo esc_attr( $base ); ?>[price]"
				value="<?php echo esc_attr( $price ); ?>"
				class="small-text"
				step="0.01"
				min="0"
				placeholder="0.00">
			<select name="<?php echo esc_attr( $base ); ?>[price_type]" class="hcqb-price-type">
				<option value="addition"  <?php selected( $price_type, 'addition'  ); ?>>+ add</option>
				<option value="deduction" <?php selected( $price_type, 'deduction' ); ?>>− deduct</option>
			</select>
			<select name="<?php echo esc_attr( $base ); ?>[option_role]" class="hcqb-option-role">
				<option value="standard" <?php selected( $role, 'standard' ); ?>>Standard</option>
				<option value="assembly" <?php selected( $role, 'assembly' ); ?>>Assembly</option>
			</select>
			<label class="hcqb-affects-image-label" title="Affects product image display">
				<input type="checkbox"
					name="<?php echo esc_attr( $base ); ?>[affects_image]"
					value="1"
					<?php checked( $affects_img, '1' ); ?>
					class="hcqb-affects-image-check">
				Image
			</label>
			<button type="button" class="button hcqb-repeater__remove">Remove</button>
		</div>
		<?php
	}

	// =========================================================================
	// Render — Image Rules (main column)
	// =========================================================================

	public static function render_meta_box_image_rules( WP_Post $post ): void {
		$rules = get_post_meta( $post->ID, 'hcqb_image_rules', true );
		if ( ! is_array( $rules ) ) {
			$rules = [];
		}

		// Build slug → label map from current questions (affects_image options only).
		$questions   = get_post_meta( $post->ID, 'hcqb_questions', true );
		$tag_options = self::get_image_tag_options( is_array( $questions ) ? $questions : [] );
		?>
		<p class="description">
			Each rule assigns an image to a product view when a specific set of option tags is active.
			Rules are evaluated in order — the first matching rule wins. Drag to reorder.
		</p>
		<?php if ( ! hcqb_get_setting( 'show_view_angles' ) ) : ?>
		<div class="notice notice-warning inline" style="margin:8px 0;">
			<p>
				<strong>View angles are currently disabled.</strong>
				The Front / Side / Back / Interior toggle will not appear on the quote builder.
				You can enable it under <a href="<?php echo esc_url( admin_url( 'admin.php?page=hc-quote-builder&tab=quote-builder' ) ); ?>">Settings &rarr; Quote Builder &rarr; View Angles</a>.
			</p>
		</div>
		<?php endif; ?>
		<div class="hcqb-image-rules-wrap">
			<div class="hcqb-image-rules-list" id="hcqb-image-rules-list">
				<?php foreach ( $rules as $idx => $rule ) : ?>
					<?php self::render_image_rule_row( $rule, $idx, $tag_options ); ?>
				<?php endforeach; ?>
			</div>
			<button type="button" class="button button-primary" id="hcqb-add-image-rule">
				+ Add Image Rule
			</button>
			<button type="button" class="button" id="hcqb-generate-image-rules">
				&#x21ba; Generate Combinations
			</button>
		</div>
		<script>window.hcqbTagOptions = <?php echo wp_json_encode( $tag_options ); ?>;</script>
		<?php
	}

	// =========================================================================
	// Render — single image rule row
	// =========================================================================

	private static function render_image_rule_row( array $rule, int $idx, array $tag_options ): void {
		$saved_tags    = is_array( $rule['match_tags'] ?? null ) ? $rule['match_tags'] : [];
		$attachment_id = (int) ( $rule['attachment_id'] ?? 0 );
		$view          = $rule['view'] ?? 'front';
		$thumb_url     = $attachment_id ? wp_get_attachment_image_url( $attachment_id, 'thumbnail' ) : '';
		$base          = "hcqb_image_rules[{$idx}]";
		?>
		<div class="hcqb-repeater__row hcqb-image-rule-row">
			<span class="hcqb-repeater__handle dashicons dashicons-menu" title="Drag to reorder"></span>

			<!-- Match tags -->
			<div class="hcqb-rule-tags-wrap">
				<span class="hcqb-rule-label">Match tags</span>
				<select name="<?php echo esc_attr( $base ); ?>[match_tags][]"
					multiple
					class="hcqb-match-tags-select"
					size="4">
					<?php foreach ( $tag_options as $slug => $display_label ) : ?>
						<option value="<?php echo esc_attr( $slug ); ?>"
							<?php selected( in_array( $slug, $saved_tags, true ) ); ?>>
							<?php echo esc_html( $display_label ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<p class="description">Hold Ctrl / Cmd to select multiple. Leave empty for default fallback.</p>
			</div>

			<!-- Image picker -->
			<div class="hcqb-rule-image-wrap">
				<span class="hcqb-rule-label">Image</span>
				<input type="hidden"
					name="<?php echo esc_attr( $base ); ?>[attachment_id]"
					value="<?php echo esc_attr( $attachment_id ); ?>"
					class="hcqb-rule-attachment-id">
				<?php if ( $thumb_url ) : ?>
					<img src="<?php echo esc_url( $thumb_url ); ?>"
						class="hcqb-rule-image-preview"
						width="60" height="60" alt="">
				<?php else : ?>
					<span class="hcqb-rule-image-preview hcqb-rule-image-empty"></span>
				<?php endif; ?>
				<button type="button" class="button hcqb-choose-rule-image">Choose</button>
				<?php if ( $attachment_id ) : ?>
					<button type="button" class="button hcqb-remove-rule-image">Remove</button>
				<?php endif; ?>
			</div>

			<!-- View -->
			<div class="hcqb-rule-view-wrap">
				<span class="hcqb-rule-label">View</span>
				<select name="<?php echo esc_attr( $base ); ?>[view]">
					<option value="front"    <?php selected( $view, 'front'    ); ?>>Front</option>
					<option value="side"     <?php selected( $view, 'side'     ); ?>>Side</option>
					<option value="back"     <?php selected( $view, 'back'     ); ?>>Back</option>
					<option value="interior" <?php selected( $view, 'interior' ); ?>>Interior</option>
				</select>
			</div>

			<button type="button" class="button hcqb-repeater__remove">Remove</button>
		</div>
		<?php
	}

	// =========================================================================
	// Save
	// =========================================================================

	public static function save( int $post_id ): void {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}
		if ( 'hc-quote-configs' !== get_post_type( $post_id ) ) {
			return;
		}
		if ( empty( $_POST['hcqb_config_nonce'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_key( $_POST['hcqb_config_nonce'] ), 'hcqb_save_config_' . $post_id ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		self::save_info( $post_id );

		$raw_questions = $_POST['hcqb_questions'] ?? [];
		self::save_questions( $post_id, is_array( $raw_questions ) ? $raw_questions : [] );

		$raw_rules = $_POST['hcqb_image_rules'] ?? [];
		self::save_image_rules( $post_id, is_array( $raw_rules ) ? $raw_rules : [] );
	}

	// -------------------------------------------------------------------------

	private static function save_info( int $post_id ): void {
		$status = sanitize_key( $_POST['hcqb_config_status'] ?? 'inactive' );
		if ( ! in_array( $status, [ 'active', 'inactive' ], true ) ) {
			$status = 'inactive';
		}
		update_post_meta( $post_id, 'hcqb_config_status', $status );

		// 1:1 enforcement: reject product already claimed by another config.
		$product_id = absint( $_POST['hcqb_linked_product'] ?? 0 );
		if ( $product_id && in_array( $product_id, self::get_taken_product_ids( $post_id ), true ) ) {
			$product_id = 0;
		}
		update_post_meta( $post_id, 'hcqb_linked_product', $product_id );

		// Standalone mode fields.
		$base_price = max( 0.0, (float) ( $_POST['hcqb_base_price'] ?? 0 ) );
		update_post_meta( $post_id, 'hcqb_base_price', $base_price );

		$default_image_id = absint( $_POST['hcqb_default_image_id'] ?? 0 );
		update_post_meta( $post_id, 'hcqb_default_image_id', $default_image_id );
	}

	// -------------------------------------------------------------------------

	/**
	 * Save questions array using _uid to preserve existing keys/slugs.
	 *
	 * For every submitted row:
	 *   - If its _uid exists in the current post meta → reuse the stored key.
	 *   - If _uid is new → generate key from label (first save only).
	 * Same logic applied independently to each option within each question.
	 */
	private static function save_questions( int $post_id, array $incoming ): void {
		// Index existing questions and options by _uid for O(1) lookup.
		$existing = get_post_meta( $post_id, 'hcqb_questions', true );
		if ( ! is_array( $existing ) ) {
			$existing = [];
		}

		$existing_q_by_uid = [];
		foreach ( $existing as $eq ) {
			$uid = $eq['_uid'] ?? '';
			if ( $uid ) {
				$existing_q_by_uid[ $uid ] = $eq;
			}
		}

		$clean = [];

		foreach ( $incoming as $q ) {
			if ( ! is_array( $q ) ) {
				continue;
			}

			$q_uid   = sanitize_key( $q['_uid']   ?? '' );
			$q_label = sanitize_text_field( $q['label'] ?? '' );
			if ( ! $q_label ) {
				continue; // Discard questions with no label.
			}

			// Preserve existing key; generate only for new questions.
			$q_key = $q_uid ? ( $existing_q_by_uid[ $q_uid ]['key'] ?? '' ) : '';
			if ( ! $q_key ) {
				$q_key = hcqb_generate_slug( $q_label );
			}

			// Build option lookup for this question.
			$existing_opts_by_uid = [];
			foreach ( $existing_q_by_uid[ $q_uid ]['options'] ?? [] as $eo ) {
				$ouid = $eo['_uid'] ?? '';
				if ( $ouid ) {
					$existing_opts_by_uid[ $ouid ] = $eo;
				}
			}

			// Process options.
			$raw_options   = is_array( $q['options'] ?? null ) ? $q['options'] : [];
			$clean_options = [];

			foreach ( $raw_options as $opt ) {
				if ( ! is_array( $opt ) ) {
					continue;
				}

				$o_uid   = sanitize_key( $opt['_uid']   ?? '' );
				$o_label = sanitize_text_field( $opt['label'] ?? '' );
				if ( ! $o_label ) {
					continue; // Discard options with no label.
				}

				$o_slug = $o_uid ? ( $existing_opts_by_uid[ $o_uid ]['slug'] ?? '' ) : '';
				if ( ! $o_slug ) {
					$o_slug = hcqb_generate_slug( $o_label );
				}

				$price_type = sanitize_key( $opt['price_type'] ?? 'addition' );
				if ( ! in_array( $price_type, [ 'addition', 'deduction' ], true ) ) {
					$price_type = 'addition';
				}

				$option_role = sanitize_key( $opt['option_role'] ?? 'standard' );
				if ( ! in_array( $option_role, [ 'standard', 'assembly' ], true ) ) {
					$option_role = 'standard';
				}

				$clean_options[] = [
					'_uid'          => $o_uid,
					'slug'          => $o_slug,
					'label'         => $o_label,
					'price'         => round( floatval( $opt['price'] ?? 0 ), 2 ),
					'price_type'    => $price_type,
					'option_role'   => $option_role,
					'affects_image' => ! empty( $opt['affects_image'] ) ? 1 : 0,
				];
			}

			$input_type = sanitize_key( $q['input_type'] ?? 'radio' );
			if ( ! in_array( $input_type, [ 'radio', 'dropdown', 'checkbox' ], true ) ) {
				$input_type = 'radio';
			}

			$clean[] = [
				'_uid'               => $q_uid,
				'key'                => $q_key,
				'label'              => $q_label,
				'input_type'         => $input_type,
				'required'           => ! empty( $q['required'] ) ? 1 : 0,
				'helper_text'        => sanitize_text_field( $q['helper_text'] ?? '' ),
				'show_in_pill'       => ! empty( $q['show_in_pill'] ) ? 1 : 0,
				'is_conditional'     => ! empty( $q['is_conditional'] ) ? 1 : 0,
				'show_when_question' => sanitize_key( $q['show_when_question'] ?? '' ),
				'show_when_option'   => sanitize_key( $q['show_when_option']   ?? '' ),
				'options'            => $clean_options,
			];
		}

		update_post_meta( $post_id, 'hcqb_questions', $clean );
	}

	// -------------------------------------------------------------------------

	private static function save_image_rules( int $post_id, array $incoming ): void {
		$clean = [];

		foreach ( $incoming as $rule ) {
			if ( ! is_array( $rule ) ) {
				continue;
			}

			$attachment_id = absint( $rule['attachment_id'] ?? 0 );
			if ( ! $attachment_id ) {
				continue; // Require an image to save the rule.
			}

			$raw_tags   = is_array( $rule['match_tags'] ?? null ) ? $rule['match_tags'] : [];
			$clean_tags = array_values( array_filter( array_map( 'sanitize_key', $raw_tags ) ) );

			$view = sanitize_key( $rule['view'] ?? 'front' );
			if ( ! in_array( $view, [ 'front', 'side', 'back', 'interior' ], true ) ) {
				$view = 'front';
			}

			$clean[] = [
				'match_tags'    => $clean_tags,
				'attachment_id' => $attachment_id,
				'view'          => $view,
			];
		}

		update_post_meta( $post_id, 'hcqb_image_rules', $clean );
	}

	// =========================================================================
	// Duplication
	// =========================================================================

	/**
	 * Handles admin_action_hcqb_duplicate_config.
	 * Clones the post + all hcqb_ meta; clears linked product; forces inactive;
	 * redirects to the new config's edit screen.
	 */
	public static function duplicate(): void {
		if ( empty( $_GET['post'] ) || empty( $_GET['_wpnonce'] ) ) {
			wp_die( esc_html__( 'Invalid request.', 'hc-quote-builder' ) );
		}

		$original_id = absint( $_GET['post'] );

		if ( ! wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ), 'hcqb_duplicate_config_' . $original_id ) ) {
			wp_die( esc_html__( 'Security check failed.', 'hc-quote-builder' ) );
		}
		if ( ! current_user_can( 'edit_post', $original_id ) ) {
			wp_die( esc_html__( 'You do not have permission to duplicate this config.', 'hc-quote-builder' ) );
		}

		$original = get_post( $original_id );
		if ( ! $original || 'hc-quote-configs' !== $original->post_type ) {
			wp_die( esc_html__( 'Config not found.', 'hc-quote-builder' ) );
		}

		$new_id = wp_insert_post( [
			'post_title'  => $original->post_title . ' (Copy)',
			'post_type'   => 'hc-quote-configs',
			'post_status' => 'draft',
		] );

		if ( is_wp_error( $new_id ) ) {
			wp_die( esc_html__( 'Could not create duplicate.', 'hc-quote-builder' ) );
		}

		// Copy all hcqb_ meta — override specific fields.
		foreach ( get_post_meta( $original_id ) as $key => $values ) {
			if ( strpos( $key, 'hcqb_' ) !== 0 ) {
				continue;
			}
			if ( 'hcqb_linked_product' === $key ) {
				update_post_meta( $new_id, 'hcqb_linked_product', 0 );
				continue;
			}
			if ( 'hcqb_config_status' === $key ) {
				update_post_meta( $new_id, 'hcqb_config_status', 'inactive' );
				continue;
			}
			foreach ( $values as $value ) {
				update_post_meta( $new_id, $key, maybe_unserialize( $value ) );
			}
		}

		wp_safe_redirect( admin_url( 'post.php?action=edit&post=' . $new_id ) );
		exit;
	}

	/**
	 * Adds a "Duplicate" row action link on the Quote Builder list table.
	 */
	public static function post_row_actions( array $actions, WP_Post $post ): array {
		if ( 'hc-quote-configs' !== $post->post_type ) {
			return $actions;
		}
		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			return $actions;
		}

		$url = wp_nonce_url(
			admin_url( 'admin.php?action=hcqb_duplicate_config&post=' . $post->ID ),
			'hcqb_duplicate_config_' . $post->ID
		);

		$actions['hcqb_duplicate'] = sprintf(
			'<a href="%s">%s</a>',
			esc_url( $url ),
			esc_html__( 'Duplicate', 'hc-quote-builder' )
		);

		return $actions;
	}

	// =========================================================================
	// Helpers
	// =========================================================================

	/**
	 * Build a slug → "Question — Option" map from options that have affects_image = 1.
	 * Used to populate the match-tags multi-select in image rule rows.
	 *
	 * @param  array $questions  hcqb_questions post meta array.
	 * @return array<string,string>  option_slug => "Q Label — O Label"
	 */
	private static function get_image_tag_options( array $questions ): array {
		$map = [];
		foreach ( $questions as $q ) {
			if ( empty( $q['options'] ) || ! is_array( $q['options'] ) ) {
				continue;
			}
			$q_label = $q['label'] ?? '';
			foreach ( $q['options'] as $opt ) {
				if ( empty( $opt['affects_image'] ) ) {
					continue;
				}
				$slug = $opt['slug'] ?? '';
				if ( ! $slug ) {
					continue;
				}
				$map[ $slug ] = $q_label . ' — ' . ( $opt['label'] ?? $slug );
			}
		}
		return $map;
	}

	/**
	 * Return all hc-containers IDs already linked to a config other than $exclude_id.
	 *
	 * @param  int   $exclude_id  Post ID to skip (current config being edited).
	 * @return int[]
	 */
	private static function get_taken_product_ids( int $exclude_id ): array {
		$configs = get_posts( [
			'post_type'      => 'hc-quote-configs',
			'post_status'    => [ 'publish', 'draft', 'pending', 'private' ],
			'numberposts'    => -1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'exclude'        => $exclude_id ? [ $exclude_id ] : [],
			'meta_query'     => [
				[
					'key'     => 'hcqb_linked_product',
					'value'   => '0',
					'compare' => '!=',
				],
			],
		] );

		$taken = [];
		foreach ( $configs as $config_id ) {
			$pid = (int) get_post_meta( $config_id, 'hcqb_linked_product', true );
			if ( $pid ) {
				$taken[] = $pid;
			}
		}
		return $taken;
	}
}
