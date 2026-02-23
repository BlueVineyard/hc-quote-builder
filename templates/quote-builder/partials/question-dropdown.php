<?php
/**
 * question-dropdown.php
 *
 * Select / dropdown input for a quote builder question.
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
?>
<select class="hcqb-dropdown"
        name="hcqb_q_<?php echo esc_attr( $q['key'] ); ?>"
        data-price="0"
        data-price-type="addition"
        data-affects-image="0">
	<option value="">— Select an option —</option>
	<?php foreach ( $q['options'] ?? [] as $opt ) :
		$o_slug       = $opt['slug']         ?? '';
		$o_label      = $opt['label']        ?? '';
		$o_price      = (float) ( $opt['price']      ?? 0 );
		$o_price_type = $opt['price_type']   ?? 'addition';
		$o_affects    = ! empty( $opt['affects_image'] ) ? '1' : '0';

		if ( ! $o_slug || ! $o_label ) {
			continue;
		}

		// Build price annotation for the label (shown inside the option text).
		$price_annotation = '';
		if ( $o_price > 0 ) {
			$price_annotation = ' (' . ( 'deduction' === $o_price_type ? '−' : '+' ) . hcqb_format_price( $o_price ) . ')';
		}
	?>
	<option value="<?php echo esc_attr( $o_slug ); ?>"
	        data-price="<?php echo esc_attr( $o_price ); ?>"
	        data-price-type="<?php echo esc_attr( $o_price_type ); ?>"
	        data-affects-image="<?php echo esc_attr( $o_affects ); ?>">
		<?php echo esc_html( $o_label . $price_annotation ); ?>
	</option>
	<?php endforeach; ?>
</select>
