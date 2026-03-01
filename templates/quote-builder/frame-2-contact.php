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
<div id="hcqb-frame-2" class="hcqb-frame-2"<?php echo ( ( $form_layout ?? 'multistep' ) === 'multistep' ) ? ' hidden' : ''; ?>>

	<div class="hcqb-frame-2-header">
		<p class="hcqb-frame-2-step">Step 2 of 2 — Your Details</p>
		<h2 class="hcqb-frame-2-title">CONTACT INFORMATION</h2>
		<p class="hcqb-frame-2-subtitle">This information is optional and based upon your submission and request for further assistance with your purchase. If you do not wish to be contacted at this time, you do not need to complete this section OR submit this quotation request.</p>
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

			<?php // Row 1: First Name + Last Name ?>
			<div class="hcqb-form-row hcqb-form-row--2col">
				<div class="hcqb-form-group">
					<label class="hcqb-form-label" for="hcqb-first-name">
						First Name <span class="hcqb-required" aria-hidden="true">*</span>
					</label>
					<div class="hcqb-field-wrap">
						<span class="hcqb-field-icon"><svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true"><circle cx="8" cy="5" r="3" stroke="currentColor" stroke-width="1.5"/><path d="M2 14c0-3.314 2.686-6 6-6s6 2.686 6 6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg></span>
						<input type="text"
						       id="hcqb-first-name"
						       name="first_name"
						       class="hcqb-form-input"
						       autocomplete="given-name">
					</div>
					<span class="hcqb-field-error" id="hcqb-err-first-name" hidden></span>
				</div>

				<div class="hcqb-form-group">
					<label class="hcqb-form-label" for="hcqb-last-name">
						Last Name <span class="hcqb-required" aria-hidden="true">*</span>
					</label>
					<div class="hcqb-field-wrap">
						<span class="hcqb-field-icon"><svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true"><circle cx="8" cy="5" r="3" stroke="currentColor" stroke-width="1.5"/><path d="M2 14c0-3.314 2.686-6 6-6s6 2.686 6 6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg></span>
						<input type="text"
						       id="hcqb-last-name"
						       name="last_name"
						       class="hcqb-form-input"
						       autocomplete="family-name">
					</div>
					<span class="hcqb-field-error" id="hcqb-err-last-name" hidden></span>
				</div>
			</div>

			<?php // Row 2: Email + Confirm Email + Title prefix ?>
			<div class="hcqb-form-row hcqb-form-row--3col">
				<div class="hcqb-form-group">
					<label class="hcqb-form-label" for="hcqb-email">
						Email <span class="hcqb-required" aria-hidden="true">*</span>
					</label>
					<div class="hcqb-field-wrap">
						<span class="hcqb-field-icon"><svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true"><rect x="1" y="3" width="14" height="10" rx="1.5" stroke="currentColor" stroke-width="1.5"/><path d="M1 5l7 5 7-5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
						<input type="email"
						       id="hcqb-email"
						       name="email"
						       class="hcqb-form-input"
						       autocomplete="email">
					</div>
					<span class="hcqb-field-error" id="hcqb-err-email" hidden></span>
				</div>

				<div class="hcqb-form-group">
					<label class="hcqb-form-label" for="hcqb-email-confirm">
						Confirm Email <span class="hcqb-required" aria-hidden="true">*</span>
					</label>
					<div class="hcqb-field-wrap">
						<span class="hcqb-field-icon"><svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true"><rect x="1" y="3" width="14" height="10" rx="1.5" stroke="currentColor" stroke-width="1.5"/><path d="M1 5l7 5 7-5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
						<input type="email"
						       id="hcqb-email-confirm"
						       name="email_confirm"
						       class="hcqb-form-input">
					</div>
					<span class="hcqb-field-error" id="hcqb-err-email-confirm" hidden></span>
				</div>

				<div class="hcqb-form-group">
					<label class="hcqb-form-label" for="hcqb-prefix">Title</label>
					<div class="hcqb-field-wrap hcqb-field-wrap--select">
						<span class="hcqb-field-icon"><svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true"><circle cx="8" cy="5" r="3" stroke="currentColor" stroke-width="1.5"/><path d="M2 14c0-3.314 2.686-6 6-6s6 2.686 6 6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg></span>
						<select id="hcqb-prefix" name="prefix" class="hcqb-form-select">
							<option value="">—</option>
							<?php foreach ( $prefix_options as $pf ) : ?>
							<option value="<?php echo esc_attr( $pf ); ?>"><?php echo esc_html( $pf ); ?></option>
							<?php endforeach; ?>
						</select>
						<span class="hcqb-field-chevron"><svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true"><path d="M3 5l4 4 4-4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
					</div>
				</div>
			</div>

			<?php // Row 3: Phone (prefix + number) + Address Search ?>
			<div class="hcqb-form-row hcqb-form-row--phone-search">
				<div class="hcqb-form-group">
					<label class="hcqb-form-label">Phone Number</label>
					<div class="hcqb-phone-fields">
						<div class="hcqb-field-wrap hcqb-field-wrap--select">
							<span class="hcqb-field-icon"><svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M3 2h3l1.5 3.5-1.75 1.25a9 9 0 004.5 4.5L11.5 9.5 15 11v3a1 1 0 01-1 1C6.268 15 1 9.732 1 3a1 1 0 011-1z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
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
							<span class="hcqb-field-chevron"><svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true"><path d="M3 5l4 4 4-4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
						</div>
						<div class="hcqb-field-wrap">
							<span class="hcqb-field-icon"><svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M3 2h3l1.5 3.5-1.75 1.25a9 9 0 004.5 4.5L11.5 9.5 15 11v3a1 1 0 01-1 1C6.268 15 1 9.732 1 3a1 1 0 011-1z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
							<input type="tel"
							       id="hcqb-phone"
							       name="phone"
							       class="hcqb-form-input"
							       autocomplete="tel-national">
						</div>
					</div>
				</div>

				<div class="hcqb-form-group">
					<label class="hcqb-form-label" for="hcqb-address-search">Search Address</label>
					<div class="hcqb-field-wrap">
						<span class="hcqb-field-icon"><svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true"><circle cx="7" cy="7" r="4.5" stroke="currentColor" stroke-width="1.5"/><path d="M10.5 10.5l3 3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg></span>
						<input type="text"
						       id="hcqb-address-search"
						       class="hcqb-form-input"
						       autocomplete="off"
						       placeholder="Start typing to search your delivery address…">
					</div>
					<p class="hcqb-form-hint">Select a result to auto-fill the fields below.</p>
				</div>
			</div>
		</div><!-- .hcqb-form-section -->

		<?php // ----------------------------------------------------------------
		// Section 2 — Delivery Address
		// --------------------------------------------------------------- ?>
		<div class="hcqb-form-section">
			<p class="hcqb-form-section__title">Delivery Address</p>

			<?php // Hidden coordinates — populated by Places result ?>
			<input type="hidden" id="hcqb-lat" name="lat">
			<input type="hidden" id="hcqb-lng" name="lng">

			<?php // Address fields box — Street, Suburb, State, Postcode ?>
			<div class="hcqb-address-box">

			<?php // Street + Suburb + State — 3 columns ?>
			<div class="hcqb-form-row hcqb-form-row--3col">
				<div class="hcqb-form-group">
					<label class="hcqb-form-label" for="hcqb-street">Street Address</label>
					<div class="hcqb-field-wrap">
						<span class="hcqb-field-icon"><svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M8 1.5A4.5 4.5 0 0112.5 6c0 3-4.5 8.5-4.5 8.5S3.5 9 3.5 6A4.5 4.5 0 018 1.5z" stroke="currentColor" stroke-width="1.5"/><circle cx="8" cy="6" r="1.5" stroke="currentColor" stroke-width="1.5"/></svg></span>
						<input type="text"
						       id="hcqb-street"
						       name="street"
						       class="hcqb-form-input"
						       readonly>
					</div>
				</div>
				<div class="hcqb-form-group">
					<label class="hcqb-form-label" for="hcqb-suburb">Suburb / City</label>
					<div class="hcqb-field-wrap">
						<span class="hcqb-field-icon"><svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M8 1.5A4.5 4.5 0 0112.5 6c0 3-4.5 8.5-4.5 8.5S3.5 9 3.5 6A4.5 4.5 0 018 1.5z" stroke="currentColor" stroke-width="1.5"/><circle cx="8" cy="6" r="1.5" stroke="currentColor" stroke-width="1.5"/></svg></span>
						<input type="text"
						       id="hcqb-suburb"
						       name="suburb"
						       class="hcqb-form-input"
						       readonly>
					</div>
				</div>
				<div class="hcqb-form-group">
					<label class="hcqb-form-label" for="hcqb-state">State</label>
					<div class="hcqb-field-wrap">
						<span class="hcqb-field-icon"><svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M8 1.5A4.5 4.5 0 0112.5 6c0 3-4.5 8.5-4.5 8.5S3.5 9 3.5 6A4.5 4.5 0 018 1.5z" stroke="currentColor" stroke-width="1.5"/><circle cx="8" cy="6" r="1.5" stroke="currentColor" stroke-width="1.5"/></svg></span>
						<input type="text"
						       id="hcqb-state"
						       name="state"
						       class="hcqb-form-input"
						       readonly>
					</div>
				</div>
			</div>

			<?php // Postcode — full width ?>
			<div class="hcqb-form-row">
				<div class="hcqb-form-group">
					<label class="hcqb-form-label" for="hcqb-postcode">Postcode</label>
					<div class="hcqb-field-wrap">
						<span class="hcqb-field-icon"><svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M8 1.5A4.5 4.5 0 0112.5 6c0 3-4.5 8.5-4.5 8.5S3.5 9 3.5 6A4.5 4.5 0 018 1.5z" stroke="currentColor" stroke-width="1.5"/><circle cx="8" cy="6" r="1.5" stroke="currentColor" stroke-width="1.5"/></svg></span>
						<input type="text"
						       id="hcqb-postcode"
						       name="postcode"
						       class="hcqb-form-input"
						       readonly>
					</div>
				</div>
			</div>

			</div><!-- .hcqb-address-box -->

			<?php // Map — always visible, centred on warehouse until address is selected ?>
			<div id="hcqb-map" class="hcqb-map"></div>

			<?php // Estimated shipping distance — populated by Distance Matrix ?>
			<div class="hcqb-form-row">
				<div class="hcqb-form-group">
					<label class="hcqb-form-label" for="hcqb-distance">Estimated Shipping Distance</label>
					<div class="hcqb-field-wrap">
						<span class="hcqb-field-icon"><svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true"><rect x="1" y="4" width="10" height="8" rx="1" stroke="currentColor" stroke-width="1.5"/><path d="M11 7h2.5l1.5 2v3h-4V7z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/><circle cx="4" cy="12.5" r="1.5" stroke="currentColor" stroke-width="1.5"/><circle cx="12.5" cy="12.5" r="1.5" stroke="currentColor" stroke-width="1.5"/></svg></span>
						<input type="text"
						       id="hcqb-distance"
						       name="shipping_distance"
						       class="hcqb-form-input"
						       readonly
						       placeholder="—">
					</div>
					<p class="hcqb-form-hint">We have calculated the shipping distance from our warehouse to your location to be this number of kilometres. We will use this information to assist in calculating your approximate cost for shipping and send the cost to you in a formal quote.</p>
				</div>
			</div>

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
			<div class="hcqb-consent-header">
				<label class="hcqb-consent-label">
					<input type="checkbox"
					       id="hcqb-consent"
					       name="consent"
					       value="1"
					       class="hcqb-consent-checkbox">
					<span><?php echo esc_html( $consent_text ); ?></span>
				</label>
				<button type="button"
				        class="hcqb-consent-toggle"
				        aria-expanded="false"
				        aria-controls="hcqb-consent-body"
				        aria-label="Show consent details">
					<svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
						<path d="M3 6l5 5 5-5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
					</svg>
				</button>
			</div>
			<span class="hcqb-field-error" id="hcqb-err-consent" hidden></span>
			<div id="hcqb-consent-body" class="hcqb-consent-body" hidden>
				<p>By submitting this form, you hereby agree to our Privacy Policy and agree to being contacted by one of our sales team members to discuss your estimate and enquiry. I understand that this is OBLIGATION FREE and I am in no way obliged to move forward with any purchase.</p>
			</div>
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
			        class="hcqb-btn hcqb-btn--primary hcqb-btn--full"
			        data-original-label="<?php echo esc_attr( $submit_button_label ); ?>">
				<?php echo esc_html( $submit_button_label ); ?>
				<svg width="18" height="18" viewBox="0 0 18 18" fill="none" aria-hidden="true" class="hcqb-btn-arrow">
					<path d="M4 14L14 4M14 4H7M14 4v7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
				</svg>
			</button>
		</div>

	</form>
</div><!-- #hcqb-frame-2 -->
