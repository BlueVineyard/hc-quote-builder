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

if (! defined('ABSPATH')) {
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

	<?php // ----------------------------------------------------------------
	// Questions list
	// --------------------------------------------------------------- 
	?>
	<div class="hcqb-questions-list">
		<h3 class="hcqb-questions-list__heading">Portable Buildings Custom Options</h3>

		<?php
		// Track rendered keys so duplicate slugs in the config don't share a
		// radio group on the frontend. Duplicates get a numeric suffix appended.
		$seen_keys = [];
		foreach ($questions as $q) :
			$q_key      = $q['key']        ?? '';
			$q_label    = $q['label']       ?? '';
			$input_type = $q['input_type']  ?? 'radio';
			$is_cond    = ! empty($q['is_conditional']);

			if (! $q_key || ! $q_label) {
				continue;
			}

			// Deduplicate: if two questions share the same key, append _2, _3, etc.
			if (isset($seen_keys[$q_key])) {
				$suffix = 2;
				while (isset($seen_keys[$q_key . '_' . $suffix])) {
					$suffix++;
				}
				$q_key    = $q_key . '_' . $suffix;
				$q['key'] = $q_key; // partials read $q['key'] directly
			}
			$seen_keys[$q_key] = true;

			$partial = $partial_map[$input_type] ?? 'question-radio';
		?>
			<div class="hcqb-question<?php echo $is_cond ? ' hcqb-question--conditional' : ''; ?>"
				data-question-key="<?php echo esc_attr($q_key); ?>" <?php if ($is_cond) :
																			$multi_conds = ! empty($q['show_when_conditions']) && is_array($q['show_when_conditions'])
																				? $q['show_when_conditions'] : [];
																		?> data-conditional="true"
				data-condition-logic="<?php echo esc_attr($q['condition_logic'] ?? 'and'); ?>" <?php if ($multi_conds) : ?>
				data-show-when-conditions="<?php echo esc_attr(wp_json_encode($multi_conds)); ?>" <?php else : ?>
				data-show-when-question="<?php echo esc_attr($q['show_when_question'] ?? ''); ?>"
				data-show-when-option="<?php echo esc_attr($q['show_when_option']   ?? ''); ?>" <?php endif; ?>
				aria-hidden="true" style="display:none" <?php endif; ?>>

				<div class="hcqb-question__header">
					<p class="hcqb-question__label">
						<?php echo esc_html($q_label); ?>
						<?php if (! empty($q['required'])) : ?>
							<span class="hcqb-required" aria-hidden="true"> *</span>
						<?php endif; ?>
					</p>
					<?php if (! empty($q['helper_text'])) : ?>
						<p class="hcqb-question__helper"><?php echo esc_html($q['helper_text']); ?></p>
					<?php endif; ?>
				</div>

				<div class="hcqb-question__options">
					<?php include HCQB_PLUGIN_DIR . 'templates/quote-builder/partials/' . $partial . '.php'; ?>
				</div>

			</div><!-- .hcqb-question -->
		<?php endforeach; ?>

	</div><!-- .hcqb-questions-list -->

</div><!-- .hcqb-builder-questions -->