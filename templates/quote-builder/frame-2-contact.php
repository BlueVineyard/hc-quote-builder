<?php
/**
 * frame-2-contact.php
 *
 * Frame 2 of the HC Quote Builder — contact details and delivery address.
 * Included from HCQB_Shortcodes::render_builder_frame_1().
 * Hidden by default; revealed by JS when the user clicks Continue on Frame 1.
 *
 * Variables in scope (from calling function):
 *   $product              WP_Post   The hc-containers post
 *   $product_id           int
 *   $base_price           float
 *   $prefix_options       array     Title prefixes (Mr, Mrs, etc.)
 *   $supported_countries  array     ISO country codes (e.g. ['AU', 'NZ'])
 *   $submit_button_label  string    Label for the submit button
 *   $consent_text         string    Consent checkbox label text
 *   $privacy_fine_print   string    HTML for collapsible privacy fine print
 *
 * @package HC_Quote_Builder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Phone prefix mapping — must match PHONE_PREFIXES in hcqb-google-maps.js.
$phone_prefix_map = [ 'AU' => '+61', 'NZ' => '+64' ];
?>
<div id="hcqb-frame-2" class="hcqb-frame-2" hidden>

	<div class="hcqb-frame-2-header">
		<div>
			<p class="hcqb-frame-2-step">Step 2 of 2 — Your Details</p>
			<h2 class="hcqb-frame-2-title"><?php echo esc_html( $product->post_title ); ?></h2>
		</div>
	</div>

	<form id="hcqb-quote-form" class="hcqb-contact-form" novalidate>

		<?php // Hidden: action identifier, product ID, serialised Frame 1 selections ?>
		<input type="hidden" name="action"          value="hcqb_submit_quote">
		<input type="hidden" name="product_id"      value="<?php echo esc_attr( $product_id ); ?>">
		<input type="hidden" id="hcqb-selections"   name="hcqb_selections" value="">

		<?php // Security nonce — verified by PHP in Stage 9 ?>
		<?php wp_nonce_field( 'hcqb_submit_quote', 'hcqb_nonce' ); ?>

		<?php // Honeypot — CSS-hidden only (never display:none or visibility:hidden) ?>
		<div class="hcqb-hp" aria-hidden="true">
			<label for="hcqb_hp_field">Leave this field blank</label>
			<input type="text"
			       id="hcqb_hp_field"
			       name="hcqb_hp"
			       tabindex="-1"
			       autocomplete="off">
		</div>

		<?php // ----------------------------------------------------------------
		// Section 1 — Personal Details
		// --------------------------------------------------------------- ?>
		<div class="hcqb-form-section">
			<p class="hcqb-form-section__title">Your Details</p>

			<?php // Title prefix + First Name + Last Name ?>
			<div class="hcqb-form-row hcqb-form-row--prefix">
				<div class="hcqb-form-group">
					<label class="hcqb-form-label" for="hcqb-prefix">Title</label>
					<select id="hcqb-prefix" name="prefix" class="hcqb-form-select">
						<option value="">—</option>
						<?php foreach ( $prefix_options as $pf ) : ?>
						<option value="<?php echo esc_attr( $pf ); ?>"><?php echo esc_html( $pf ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>

				<div class="hcqb-form-group">
					<label class="hcqb-form-label" for="hcqb-first-name">
						First Name <span class="hcqb-required" aria-hidden="true">*</span>
					</label>
					<input type="text"
					       id="hcqb-first-name"
					       name="first_name"
					       class="hcqb-form-input"
					       autocomplete="given-name">
					<span class="hcqb-field-error" id="hcqb-err-first-name" hidden></span>
				</div>

				<div class="hcqb-form-group">
					<label class="hcqb-form-label" for="hcqb-last-name">
						Last Name <span class="hcqb-required" aria-hidden="true">*</span>
					</label>
					<input type="text"
					       id="hcqb-last-name"
					       name="last_name"
					       class="hcqb-form-input"
					       autocomplete="family-name">
					<span class="hcqb-field-error" id="hcqb-err-last-name" hidden></span>
				</div>
			</div>

			<?php // Email + Confirm Email ?>
			<div class="hcqb-form-row hcqb-form-row--2col">
				<div class="hcqb-form-group">
					<label class="hcqb-form-label" for="hcqb-email">
						Email <span class="hcqb-required" aria-hidden="true">*</span>
					</label>
					<input type="email"
					       id="hcqb-email"
					       name="email"
					       class="hcqb-form-input"
					       autocomplete="email">
					<span class="hcqb-field-error" id="hcqb-err-email" hidden></span>
				</div>

				<div class="hcqb-form-group">
					<label class="hcqb-form-label" for="hcqb-email-confirm">
						Confirm Email <span class="hcqb-required" aria-hidden="true">*</span>
					</label>
					<input type="email"
					       id="hcqb-email-confirm"
					       name="email_confirm"
					       class="hcqb-form-input">
					<span class="hcqb-field-error" id="hcqb-err-email-confirm" hidden></span>
				</div>
			</div>

			<?php // Phone prefix + number ?>
			<div class="hcqb-form-row hcqb-form-row--phone">
				<div class="hcqb-form-group">
					<label class="hcqb-form-label" for="hcqb-phone-prefix">Prefix</label>
					<select id="hcqb-phone-prefix" name="phone_prefix" class="hcqb-form-select">
						<?php foreach ( $supported_countries as $code ) :
							$dial = $phone_prefix_map[ $code ] ?? '';
							if ( ! $dial ) { continue; }
						?>
						<option value="<?php echo esc_attr( $dial ); ?>">
							<?php echo esc_html( $code . ' ' . $dial ); ?>
						</option>
						<?php endforeach; ?>
					</select>
				</div>

				<div class="hcqb-form-group">
					<label class="hcqb-form-label" for="hcqb-phone">Phone Number</label>
					<input type="tel"
					       id="hcqb-phone"
					       name="phone"
					       class="hcqb-form-input"
					       autocomplete="tel-national">
				</div>
			</div>
		</div><!-- .hcqb-form-section -->

		<?php // ----------------------------------------------------------------
		// Section 2 — Delivery Address
		// --------------------------------------------------------------- ?>
		<div class="hcqb-form-section">
			<p class="hcqb-form-section__title">Delivery Address</p>

			<?php // Address search — Google Places Autocomplete target ?>
			<div class="hcqb-form-row">
				<div class="hcqb-form-group">
					<label class="hcqb-form-label" for="hcqb-address-search">Search Address</label>
					<input type="text"
					       id="hcqb-address-search"
					       class="hcqb-form-input"
					       autocomplete="off"
					       placeholder="Start typing to search your delivery address…">
					<p class="hcqb-form-hint">Select a result to auto-fill the fields below.</p>
				</div>
			</div>

			<?php // Street — read-only, populated by Places result ?>
			<div class="hcqb-form-row">
				<div class="hcqb-form-group">
					<label class="hcqb-form-label" for="hcqb-street">Street Address</label>
					<input type="text"
					       id="hcqb-street"
					       name="street"
					       class="hcqb-form-input"
					       readonly>
				</div>
			</div>

			<?php // Suburb + State + Postcode ?>
			<div class="hcqb-form-row hcqb-form-row--3col">
				<div class="hcqb-form-group">
					<label class="hcqb-form-label" for="hcqb-suburb">Suburb / City</label>
					<input type="text"
					       id="hcqb-suburb"
					       name="suburb"
					       class="hcqb-form-input"
					       readonly>
				</div>
				<div class="hcqb-form-group">
					<label class="hcqb-form-label" for="hcqb-state">State</label>
					<input type="text"
					       id="hcqb-state"
					       name="state"
					       class="hcqb-form-input"
					       readonly>
				</div>
				<div class="hcqb-form-group">
					<label class="hcqb-form-label" for="hcqb-postcode">Postcode</label>
					<input type="text"
					       id="hcqb-postcode"
					       name="postcode"
					       class="hcqb-form-input"
					       readonly>
				</div>
			</div>

			<?php // Hidden coordinates — populated by Places result ?>
			<input type="hidden" id="hcqb-lat" name="lat">
			<input type="hidden" id="hcqb-lng" name="lng">

			<?php // Estimated shipping distance — populated by Distance Matrix ?>
			<div class="hcqb-form-row">
				<div class="hcqb-form-group">
					<label class="hcqb-form-label" for="hcqb-distance">Estimated Shipping Distance</label>
					<input type="text"
					       id="hcqb-distance"
					       name="shipping_distance"
					       class="hcqb-form-input"
					       readonly
					       placeholder="—">
				</div>
			</div>

			<?php // Map — revealed once an address is selected ?>
			<div id="hcqb-map" class="hcqb-map" hidden></div>

		</div><!-- .hcqb-form-section -->

		<?php // ----------------------------------------------------------------
		// Quote price summary — mirrors the Frame 1 live price
		// --------------------------------------------------------------- ?>
		<div class="hcqb-contact-price">
			<span class="hcqb-preview-price__label">Total Estimated Price</span>
			<span class="hcqb-live-price"><?php echo esc_html( hcqb_format_price( $base_price ) ); ?></span>
			<span class="hcqb-preview-price__note">Subject to confirmation</span>
		</div>

		<?php // ----------------------------------------------------------------
		// Consent + privacy fine print
		// --------------------------------------------------------------- ?>
		<div class="hcqb-form-section hcqb-form-section--consent">
			<label class="hcqb-consent-label">
				<input type="checkbox"
				       id="hcqb-consent"
				       name="consent"
				       value="1"
				       class="hcqb-consent-checkbox">
				<span><?php echo esc_html( $consent_text ); ?></span>
			</label>
			<span class="hcqb-field-error" id="hcqb-err-consent" hidden></span>

			<?php if ( $privacy_fine_print ) : ?>
			<details class="hcqb-fine-print">
				<summary class="hcqb-fine-print__toggle">Privacy information</summary>
				<div class="hcqb-fine-print__body">
					<?php echo wp_kses_post( $privacy_fine_print ); ?>
				</div>
			</details>
			<?php endif; ?>
		</div><!-- .hcqb-form-section -->

		<?php // ----------------------------------------------------------------
		// Submission feedback (success / error)
		// --------------------------------------------------------------- ?>
		<div id="hcqb-form-message" class="hcqb-form-message" hidden></div>

		<?php // ----------------------------------------------------------------
		// Form actions — Back + Submit
		// --------------------------------------------------------------- ?>
		<div class="hcqb-form-actions">
			<button type="button" id="hcqb-back-step" class="hcqb-btn hcqb-btn--secondary">
				← Back to Configuration
			</button>
			<button type="submit"
			        class="hcqb-btn hcqb-btn--primary"
			        data-original-label="<?php echo esc_attr( $submit_button_label ); ?>">
				<?php echo esc_html( $submit_button_label ); ?>
			</button>
		</div>

	</form>
</div><!-- #hcqb-frame-2 -->
