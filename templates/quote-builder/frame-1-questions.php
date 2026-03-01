<?php
/**
 * frame-1-questions.php
 *
 * Left panel of Frame 1 — product selector + questions list.
 * Included from HCQB_Shortcodes::render_builder_frame_1().
 *
 * Variables in scope (from calling function):
 *   $product            WP_Post   The hc-containers post
 *   $config             WP_Post   The active hc-quote-configs post
 *   $questions          array     Full questions array from post meta
 *   $available_products array     [ ['id' => int, 'name' => string], ... ]
 *   $product_id         int
 *   $base_price         float
 *
 * @package HC_Quote_Builder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Map input_type → partial filename.
$partial_map = [
	'radio'    => 'question-radio',
	'dropdown' => 'question-dropdown',
	'checkbox' => 'question-checkbox',
];
?>
<div class="hcqb-builder-questions">

	<?php if ( ! ( $standalone ?? false ) ) : ?>
	<?php // ----------------------------------------------------------------
	// Product selector — hidden in standalone mode (no pre-selected product)
	// --------------------------------------------------------------- ?>
	<div class="hcqb-product-selector">
		<div class="hcqb-product-selector__current">
			<div class="hcqb-product-selector__info">
				<span class="hcqb-product-selector__label">Configuring</span>
				<span class="hcqb-product-selector__name"><?php echo esc_html( $product->post_title ); ?></span>
			</div>
			<button type="button" class="hcqb-btn hcqb-btn--ghost hcqb-change-product">
				Change
			</button>
		</div>

		<div class="hcqb-product-selector__dropdown" id="hcqb-product-dropdown" hidden>
			<label for="hcqb-product-select" class="screen-reader-text">Select a different product</label>
			<select id="hcqb-product-select" class="hcqb-product-select">
				<?php foreach ( $available_products as $p ) : ?>
				<option value="<?php echo esc_attr( $p['id'] ); ?>"
				        <?php selected( $p['id'], $product_id ); ?>>
					<?php echo esc_html( $p['name'] ); ?>
				</option>
				<?php endforeach; ?>
			</select>
		</div>
	</div><!-- .hcqb-product-selector -->
	<?php endif; ?>

	<?php // ----------------------------------------------------------------
	// Questions list
	// --------------------------------------------------------------- ?>
	<div class="hcqb-questions-list">

		<?php
		// Track rendered keys so duplicate slugs in the config don't share a
		// radio group on the frontend. Duplicates get a numeric suffix appended.
		$seen_keys = [];
		foreach ( $questions as $q ) :
			$q_key      = $q['key']        ?? '';
			$q_label    = $q['label']       ?? '';
			$input_type = $q['input_type']  ?? 'radio';
			$is_cond    = ! empty( $q['is_conditional'] );

			if ( ! $q_key || ! $q_label ) {
				continue;
			}

			// Deduplicate: if two questions share the same key, append _2, _3, etc.
			if ( isset( $seen_keys[ $q_key ] ) ) {
				$suffix = 2;
				while ( isset( $seen_keys[ $q_key . '_' . $suffix ] ) ) {
					$suffix++;
				}
				$q_key    = $q_key . '_' . $suffix;
				$q['key'] = $q_key; // partials read $q['key'] directly
			}
			$seen_keys[ $q_key ] = true;

			$partial = $partial_map[ $input_type ] ?? 'question-radio';
		?>
		<div class="hcqb-question<?php echo $is_cond ? ' hcqb-question--conditional' : ''; ?>"
		     data-question-key="<?php echo esc_attr( $q_key ); ?>"
		     <?php if ( $is_cond ) : ?>
		     data-conditional="true"
		     data-show-when-question="<?php echo esc_attr( $q['show_when_question'] ?? '' ); ?>"
		     data-show-when-option="<?php echo esc_attr( $q['show_when_option']   ?? '' ); ?>"
		     aria-hidden="true"
		     style="display:none"
		     <?php endif; ?>>

			<div class="hcqb-question__header">
				<p class="hcqb-question__label">
					<?php echo esc_html( $q_label ); ?>
					<?php if ( ! empty( $q['required'] ) ) : ?>
					<span class="hcqb-required" aria-hidden="true"> *</span>
					<?php endif; ?>
				</p>
				<?php if ( ! empty( $q['helper_text'] ) ) : ?>
				<p class="hcqb-question__helper"><?php echo esc_html( $q['helper_text'] ); ?></p>
				<?php endif; ?>
			</div>

			<div class="hcqb-question__options">
				<?php include HCQB_PLUGIN_DIR . 'templates/quote-builder/partials/' . $partial . '.php'; ?>
			</div>

		</div><!-- .hcqb-question -->
		<?php endforeach; ?>

	</div><!-- .hcqb-questions-list -->

</div><!-- .hcqb-builder-questions -->
