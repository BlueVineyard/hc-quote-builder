<?php
/**
 * question-checkbox.php
 *
 * Checkbox (multi-select) options for a quote builder question.
 * Included inside the questions loop in frame-1-questions.php.
 *
 * Variables in scope:
 *   $q   array  The current question row from hcqb_questions meta.
 *
 * @package HC_Quote_Builder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

foreach ( $q['options'] ?? [] as $opt ) :
	$o_slug       = $opt['slug']         ?? '';
	$o_label      = $opt['label']        ?? '';
	$o_price      = (float) ( $opt['price']      ?? 0 );
	$o_price_type = $opt['price_type']   ?? 'addition';
	$o_affects    = ! empty( $opt['affects_image'] ) ? '1' : '0';

	if ( ! $o_slug || ! $o_label ) {
		continue;
	}
?>
<label class="hcqb-checkbox-option">
	<input type="checkbox"
	       name="hcqb_q_<?php echo esc_attr( $q['key'] ); ?>[]"
	       value="<?php echo esc_attr( $o_slug ); ?>"
	       data-price="<?php echo esc_attr( $o_price ); ?>"
	       data-price-type="<?php echo esc_attr( $o_price_type ); ?>"
	       data-affects-image="<?php echo esc_attr( $o_affects ); ?>">
	<span class="hcqb-checkbox-option__label"><?php echo esc_html( $o_label ); ?></span>
	<?php if ( $o_price > 0 ) : ?>
	<span class="hcqb-option-price">
		<?php echo 'deduction' === $o_price_type ? '−' : '+'; ?>
		<?php echo esc_html( hcqb_format_price( $o_price ) ); ?>
	</span>
	<?php endif; ?>
</label>
<?php endforeach; ?>
