<?php
/**
 * HCQB_Settings
 *
 * Plugin Settings Page — Settings → HC Quote Builder
 * Four tabs: General, Email, Quote Builder, Form Options.
 *
 * Methods:
 *   register_settings_page()   — adds submenu page under Settings
 *   register_setting()         — registers hcqb_settings option with sanitisation callback
 *   enqueue_assets()           — loads CSS/JS on the settings screen only
 *   render_settings_page()     — outputs the five-tab form
 *   render_tab_instructions()  — read-only reference panel (shortcodes, PHP snippets, general docs)
 *   sanitise()                 — full sanitisation for all four data tabs
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HCQB_Settings {

	const OPTION_KEY   = 'hcqb_settings';
	const OPTION_GROUP = 'hcqb_settings_group';
	const PAGE_SLUG    = 'hcqb-settings';

	// -------------------------------------------------------------------------
	// Menu registration
	// -------------------------------------------------------------------------

	public static function register_settings_page(): void {
		add_options_page(
			'HC Quote Builder Settings',
			'HC Quote Builder',
			'manage_options',
			self::PAGE_SLUG,
			[ __CLASS__, 'render_settings_page' ]
		);
	}

	// -------------------------------------------------------------------------
	// Option registration
	// -------------------------------------------------------------------------

	public static function register_setting(): void {
		register_setting(
			self::OPTION_GROUP,
			self::OPTION_KEY,
			[
				'sanitize_callback' => [ __CLASS__, 'sanitise' ],
				'default'           => self::defaults(),
			]
		);
	}

	// -------------------------------------------------------------------------
	// Asset enqueueing — settings screen only
	// -------------------------------------------------------------------------

	public static function enqueue_assets( string $hook ): void {
		if ( 'settings_page_' . self::PAGE_SLUG !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'hcqb-admin-global',
			HCQB_PLUGIN_URL . 'assets/css/admin/hcqb-admin-global.css',
			[],
			HCQB_VERSION
		);

		wp_enqueue_script(
			'hcqb-admin-settings',
			HCQB_PLUGIN_URL . 'assets/js/admin/hcqb-admin-settings.js',
			[],
			HCQB_VERSION,
			true
		);

		// Load Google Maps Places for warehouse address autocomplete —
		// only if an API key is already saved (chicken-and-egg guard).
		$api_key = hcqb_get_setting( 'google_maps_api_key' );
		if ( $api_key ) {
			wp_enqueue_script(
				'google-maps-api',
				'https://maps.googleapis.com/maps/api/js?key=' . esc_attr( $api_key ) . '&libraries=places&callback=hcqbSettingsMapsInit',
				[ 'hcqb-admin-settings' ],
				null,
				true
			);
		}
	}

	// -------------------------------------------------------------------------
	// Settings page renderer
	// -------------------------------------------------------------------------

	public static function render_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings   = get_option( self::OPTION_KEY, [] );
		$settings   = wp_parse_args( $settings, self::defaults() );
		$active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'general';

		$tabs = [
			'general'       => 'General',
			'email'         => 'Email',
			'quote-builder' => 'Quote Builder',
			'form-options'  => 'Form Options',
			'instructions'  => 'Instructions',
		];

		// Normalise tab to a known value.
		if ( ! array_key_exists( $active_tab, $tabs ) ) {
			$active_tab = 'general';
		}
		?>
		<div class="wrap hcqb-settings-wrap">
			<h1>HC Quote Builder — Settings</h1>

			<nav class="hcqb-tabs" role="tablist">
				<?php foreach ( $tabs as $slug => $label ) : ?>
					<a href="<?php echo esc_url( admin_url( 'options-general.php?page=' . self::PAGE_SLUG . '&tab=' . $slug ) ); ?>"
					   class="hcqb-tab<?php echo $active_tab === $slug ? ' hcqb-tab--active' : ''; ?>"
					   role="tab"
					   aria-selected="<?php echo $active_tab === $slug ? 'true' : 'false'; ?>">
						<?php echo esc_html( $label ); ?>
					</a>
				<?php endforeach; ?>
			</nav>

			<form method="post" action="options.php">
				<?php settings_fields( self::OPTION_GROUP ); ?>

				<div class="hcqb-tab-panels">
					<?php
					self::render_tab_general( $settings, $active_tab );
					self::render_tab_email( $settings, $active_tab );
					self::render_tab_quote_builder( $settings, $active_tab );
					self::render_tab_form_options( $settings, $active_tab );
					?>
				</div>

				<?php if ( 'instructions' !== $active_tab ) : ?>
					<?php submit_button( 'Save Settings' ); ?>
				<?php endif; ?>
			</form>

		<?php self::render_tab_instructions( $active_tab ); ?>
		</div>
		<?php
	}

	// -------------------------------------------------------------------------
	// Tab 1 — General
	// -------------------------------------------------------------------------

	private static function render_tab_general( array $settings, string $active_tab ): void {
		$visible             = $active_tab === 'general';
		$api_key             = $settings['google_maps_api_key'] ?? '';
		$supported_countries = $settings['supported_countries'] ?? [ 'AU' ];
		$available_countries = [
			'AU' => 'Australia',
			'NZ' => 'New Zealand',
		];
		?>
		<div class="hcqb-tab-panel<?php echo $visible ? ' hcqb-tab-panel--active' : ''; ?>" id="hcqb-tab-general">
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">
						<label for="hcqb_google_maps_api_key">Google Maps API Key</label>
					</th>
					<td>
						<input type="password"
						       id="hcqb_google_maps_api_key"
						       name="<?php echo esc_attr( self::OPTION_KEY ); ?>[google_maps_api_key]"
						       value="<?php echo esc_attr( $api_key ); ?>"
						       class="regular-text"
						       autocomplete="off">
						<p class="description">Required. Restrict this key to your domain in the Google Cloud Console.</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="hcqb_warehouse_address">Warehouse Address</label>
					</th>
					<td>
						<?php if ( empty( $api_key ) ) : ?>
							<p class="description hcqb-notice-inline">Save a Google Maps API Key first to enable address autocomplete.</p>
						<?php endif; ?>
						<input type="text"
						       id="hcqb_warehouse_address"
						       name="<?php echo esc_attr( self::OPTION_KEY ); ?>[warehouse_address]"
						       value="<?php echo esc_attr( $settings['warehouse_address'] ?? '' ); ?>"
						       class="large-text"
						       <?php echo empty( $api_key ) ? 'readonly' : ''; ?>>
						<p class="description">Start typing to search. Selecting a result auto-fills the coordinates below.</p>
					</td>
				</tr>
				<tr>
					<th scope="row">Warehouse Coordinates</th>
					<td>
						<label for="hcqb_warehouse_lat">Lat</label>
						<input type="text"
						       id="hcqb_warehouse_lat"
						       name="<?php echo esc_attr( self::OPTION_KEY ); ?>[warehouse_lat]"
						       value="<?php echo esc_attr( $settings['warehouse_lat'] ?? '' ); ?>"
						       class="small-text"
						       readonly>
						&nbsp;
						<label for="hcqb_warehouse_lng">Lng</label>
						<input type="text"
						       id="hcqb_warehouse_lng"
						       name="<?php echo esc_attr( self::OPTION_KEY ); ?>[warehouse_lng]"
						       value="<?php echo esc_attr( $settings['warehouse_lng'] ?? '' ); ?>"
						       class="small-text"
						       readonly>
						<p class="description">Auto-filled when an address is selected via autocomplete.</p>
					</td>
				</tr>
				<tr>
					<th scope="row">Supported Countries</th>
					<td>
						<fieldset>
							<legend class="screen-reader-text">Supported Countries</legend>
							<?php foreach ( $available_countries as $code => $name ) : ?>
								<label>
									<input type="checkbox"
									       name="<?php echo esc_attr( self::OPTION_KEY ); ?>[supported_countries][]"
									       value="<?php echo esc_attr( $code ); ?>"
									       <?php checked( in_array( $code, $supported_countries, true ) ); ?>>
									<?php echo esc_html( $name ); ?>
								</label><br>
							<?php endforeach; ?>
						</fieldset>
						<p class="description">Restricts address autocomplete and the phone prefix selector on the quote form.</p>
					</td>
				</tr>
			</table>
		</div>
		<?php
	}

	// -------------------------------------------------------------------------
	// Tab 2 — Email
	// -------------------------------------------------------------------------

	private static function render_tab_email( array $settings, string $active_tab ): void {
		$visible = $active_tab === 'email';
		?>
		<div class="hcqb-tab-panel<?php echo $visible ? ' hcqb-tab-panel--active' : ''; ?>" id="hcqb-tab-email">
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">
						<label for="hcqb_admin_email">Admin Notification Email</label>
					</th>
					<td>
						<input type="email"
						       id="hcqb_admin_email"
						       name="<?php echo esc_attr( self::OPTION_KEY ); ?>[admin_email]"
						       value="<?php echo esc_attr( $settings['admin_email'] ?? '' ); ?>"
						       class="regular-text">
						<p class="description">Quote submission notifications are sent to this address.</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="hcqb_from_name">From Name</label>
					</th>
					<td>
						<input type="text"
						       id="hcqb_from_name"
						       name="<?php echo esc_attr( self::OPTION_KEY ); ?>[from_name]"
						       value="<?php echo esc_attr( $settings['from_name'] ?? '' ); ?>"
						       class="regular-text">
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="hcqb_from_email">From Email</label>
					</th>
					<td>
						<input type="email"
						       id="hcqb_from_email"
						       name="<?php echo esc_attr( self::OPTION_KEY ); ?>[from_email]"
						       value="<?php echo esc_attr( $settings['from_email'] ?? '' ); ?>"
						       class="regular-text">
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="hcqb_admin_email_subject">Admin Email Subject</label>
					</th>
					<td>
						<input type="text"
						       id="hcqb_admin_email_subject"
						       name="<?php echo esc_attr( self::OPTION_KEY ); ?>[admin_email_subject]"
						       value="<?php echo esc_attr( $settings['admin_email_subject'] ?? '' ); ?>"
						       class="regular-text">
						<p class="description">Available tokens: <code>{product_name}</code>, <code>{customer_name}</code></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="hcqb_customer_email_subject">Customer Email Subject</label>
					</th>
					<td>
						<input type="text"
						       id="hcqb_customer_email_subject"
						       name="<?php echo esc_attr( self::OPTION_KEY ); ?>[customer_email_subject]"
						       value="<?php echo esc_attr( $settings['customer_email_subject'] ?? '' ); ?>"
						       class="regular-text">
						<p class="description">Available tokens: <code>{product_name}</code>, <code>{customer_name}</code></p>
					</td>
				</tr>
			</table>
		</div>
		<?php
	}

	// -------------------------------------------------------------------------
	// Tab 3 — Quote Builder
	// -------------------------------------------------------------------------

	private static function render_tab_quote_builder( array $settings, string $active_tab ): void {
		$visible       = $active_tab === 'quote-builder';
		$pages         = get_pages( [ 'sort_column' => 'post_title', 'sort_order' => 'ASC' ] );
		$selected_page = absint( $settings['quote_builder_page_id'] ?? 0 );
		?>
		<div class="hcqb-tab-panel<?php echo $visible ? ' hcqb-tab-panel--active' : ''; ?>" id="hcqb-tab-quote-builder">
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">
						<label for="hcqb_quote_builder_page_id">Quote Builder Page</label>
					</th>
					<td>
						<select id="hcqb_quote_builder_page_id"
						        name="<?php echo esc_attr( self::OPTION_KEY ); ?>[quote_builder_page_id]">
							<option value="0">— Select a page —</option>
							<?php foreach ( $pages as $page ) : ?>
								<option value="<?php echo esc_attr( $page->ID ); ?>"
								        <?php selected( $selected_page, $page->ID ); ?>>
									<?php echo esc_html( $page->post_title ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<p class="description">The page containing the <code>[hc_quote_builder]</code> shortcode.</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="hcqb_product_change_alert">Product Change Alert Text</label>
					</th>
					<td>
						<input type="text"
						       id="hcqb_product_change_alert"
						       name="<?php echo esc_attr( self::OPTION_KEY ); ?>[product_change_alert]"
						       value="<?php echo esc_attr( $settings['product_change_alert'] ?? '' ); ?>"
						       class="large-text">
						<p class="description">Confirmation message shown when the customer clicks "Change Product" on the quote builder.</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="hcqb_submit_button_label">Submit Button Label</label>
					</th>
					<td>
						<input type="text"
						       id="hcqb_submit_button_label"
						       name="<?php echo esc_attr( self::OPTION_KEY ); ?>[submit_button_label]"
						       value="<?php echo esc_attr( $settings['submit_button_label'] ?? '' ); ?>"
						       class="regular-text">
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="hcqb_consent_text">Consent Checkbox Text</label>
					</th>
					<td>
						<input type="text"
						       id="hcqb_consent_text"
						       name="<?php echo esc_attr( self::OPTION_KEY ); ?>[consent_text]"
						       value="<?php echo esc_attr( $settings['consent_text'] ?? '' ); ?>"
						       class="large-text">
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="hcqb_privacy_fine_print">Privacy Fine Print</label>
					</th>
					<td>
						<textarea id="hcqb_privacy_fine_print"
						          name="<?php echo esc_attr( self::OPTION_KEY ); ?>[privacy_fine_print]"
						          rows="5"
						          class="large-text"><?php echo esc_textarea( $settings['privacy_fine_print'] ?? '' ); ?></textarea>
						<p class="description">Displayed below the consent checkbox as collapsible fine print. Basic HTML allowed.</p>
					</td>
				</tr>
			</table>
		</div>
		<?php
	}

	// -------------------------------------------------------------------------
	// Tab 4 — Form Options
	// -------------------------------------------------------------------------

	private static function render_tab_form_options( array $settings, string $active_tab ): void {
		$visible        = $active_tab === 'form-options';
		$prefix_options = $settings['prefix_options'] ?? self::defaults()['prefix_options'];
		$status_labels  = $settings['submission_status_labels'] ?? self::defaults()['submission_status_labels'];
		?>
		<div class="hcqb-tab-panel<?php echo $visible ? ' hcqb-tab-panel--active' : ''; ?>" id="hcqb-tab-form-options">

			<?php /* ---- Prefix Options ---- */ ?>
			<h2>Name Prefix Options</h2>
			<p>Options available in the title prefix dropdown on the quote request form (Mr, Mrs, Ms, Dr, etc.).</p>

			<div class="hcqb-repeater" id="hcqb-prefix-repeater">
				<div class="hcqb-repeater__list" id="hcqb-prefix-list">
					<?php foreach ( $prefix_options as $prefix ) : ?>
						<div class="hcqb-repeater__row" data-type="prefix">
							<span class="hcqb-repeater__handle dashicons dashicons-menu" title="Drag to reorder"></span>
							<input type="text"
							       name="<?php echo esc_attr( self::OPTION_KEY ); ?>[prefix_options][]"
							       value="<?php echo esc_attr( $prefix ); ?>"
							       class="regular-text"
							       placeholder="e.g. Mr">
							<button type="button" class="button hcqb-repeater__remove">Remove</button>
						</div>
					<?php endforeach; ?>
				</div>
				<button type="button"
				        class="button hcqb-repeater__add"
				        data-target="hcqb-prefix-list"
				        data-type="prefix">
					+ Add Prefix
				</button>
			</div>

			<?php /* ---- Submission Status Labels ---- */ ?>
			<h2>Submission Status Labels</h2>
			<p>Statuses available on quote submissions. The <strong>key</strong> is generated once and locked — only the <strong>label</strong> can be renamed.</p>

			<div class="hcqb-repeater" id="hcqb-status-repeater">
				<div class="hcqb-repeater__list" id="hcqb-status-list">
					<?php foreach ( $status_labels as $status ) : ?>
						<div class="hcqb-repeater__row" data-type="status">
							<span class="hcqb-repeater__handle dashicons dashicons-menu" title="Drag to reorder"></span>
							<input type="hidden"
							       name="<?php echo esc_attr( self::OPTION_KEY ); ?>[submission_status_labels][][key]"
							       value="<?php echo esc_attr( $status['key'] ); ?>">
							<span class="hcqb-status-key-display"><?php echo esc_html( $status['key'] ); ?></span>
							<input type="text"
							       name="<?php echo esc_attr( self::OPTION_KEY ); ?>[submission_status_labels][][label]"
							       value="<?php echo esc_attr( $status['label'] ); ?>"
							       class="regular-text"
							       placeholder="Status label">
							<button type="button" class="button hcqb-repeater__remove">Remove</button>
						</div>
					<?php endforeach; ?>
				</div>
				<button type="button"
				        class="button hcqb-repeater__add"
				        data-target="hcqb-status-list"
				        data-type="status">
					+ Add Status
				</button>
			</div>

		</div>
		<?php
	}

	// -------------------------------------------------------------------------
	// Tab 5 — Instructions (read-only reference panel — rendered outside <form>)
	// -------------------------------------------------------------------------

	private static function render_tab_instructions( string $active_tab ): void {
		$visible = $active_tab === 'instructions';
		?>
		<div class="hcqb-tab-panel hcqb-instructions-panel<?php echo $visible ? ' hcqb-tab-panel--active' : ''; ?>" id="hcqb-tab-instructions">

			<p>Use the shortcodes and PHP snippets below to build custom product templates with your page builder, or to query container data programmatically.</p>
			<p><strong>All individual shortcodes accept an optional <code>post_id=""</code> attribute.</strong> When placed on an <code>hc-containers</code> singular page, <code>post_id</code> can be omitted — the shortcode reads from the current post automatically. When used elsewhere (e.g. a custom template or page builder widget), pass the product's post ID explicitly.</p>

			<?php /* ----- Panel 1: Shortcodes ----- */ ?>
			<details class="hcqb-accordion" open>
				<summary class="hcqb-accordion__trigger">Shortcodes — Individual Field Output</summary>
				<div class="hcqb-accordion__body">
					<p class="description">Place these shortcodes anywhere on an <code>hc-containers</code> page, or in any template using a page builder.</p>
					<table class="widefat striped hcqb-instructions-table">
						<thead>
							<tr>
								<th>Shortcode</th>
								<th>Output</th>
								<th>Notes</th>
							</tr>
						</thead>
						<tbody>
							<tr>
								<td><code>[hcqb_title]</code></td>
								<td>Product title</td>
								<td>Plain text</td>
							</tr>
							<tr>
								<td><code>[hcqb_price]</code></td>
								<td>Base (flat-pack) price</td>
								<td>Formatted — e.g. <em>$7,500.00</em></td>
							</tr>
							<tr>
								<td><code>[hcqb_assembled_price]</code></td>
								<td>Fully assembled price</td>
								<td>Base price ± assembly option delta. Falls back to base price if no active config.</td>
							</tr>
							<tr>
								<td><code>[hcqb_rating]</code></td>
								<td>Star rating + review count</td>
								<td>Styled HTML block. Empty if rating is 0.</td>
							</tr>
							<tr>
								<td><code>[hcqb_review_count]</code></td>
								<td>Review count number</td>
								<td>Plain number only — e.g. <em>42</em></td>
							</tr>
							<tr>
								<td><code>[hcqb_short_desc]</code></td>
								<td>Short description</td>
								<td>Plain text</td>
							</tr>
							<tr>
								<td><code>[hcqb_description]</code></td>
								<td>Full product description</td>
								<td>Rich text / HTML</td>
							</tr>
							<tr>
								<td><code>[hcqb_additional_notes]</code></td>
								<td>Additional notes block</td>
								<td>Wrapped in <code>.hcqb-additional-notes</code></td>
							</tr>
							<tr>
								<td><code>[hcqb_gallery]</code></td>
								<td>All product images</td>
								<td>Thumbnail strip. Attr: <code>size=""</code> (default: <em>thumbnail</em>)</td>
							</tr>
							<tr>
								<td><code>[hcqb_main_image]</code></td>
								<td>Primary product image only</td>
								<td>First gallery image. Attr: <code>size=""</code> (default: <em>large</em>)</td>
							</tr>
							<tr>
								<td><code>[hcqb_features]</code></td>
								<td>Features list</td>
								<td>Icon + label rows in <code>.hcqb-features-list</code></td>
							</tr>
							<tr>
								<td><code>[hcqb_plan_document]</code></td>
								<td>Floor plan download link</td>
								<td>Attr: <code>label=""</code> (default: <em>Download Floor Plan</em>)</td>
							</tr>
							<tr>
								<td><code>[hcqb_shipping_link]</code></td>
								<td>Shipping details link</td>
								<td>Attr: <code>label=""</code> (default: <em>Shipping Details</em>)</td>
							</tr>
							<tr class="hcqb-instructions-divider">
								<td colspan="3"><strong>Lease Fields</strong> — return empty if lease is disabled on the product</td>
							</tr>
							<tr>
								<td><code>[hcqb_lease_price]</code></td>
								<td>Lease price</td>
								<td>Formatted — e.g. <em>$299.00</em></td>
							</tr>
							<tr>
								<td><code>[hcqb_lease_price_label]</code></td>
								<td>Lease price label</td>
								<td>Plain text — e.g. <em>per week</em></td>
							</tr>
							<tr>
								<td><code>[hcqb_lease_terms]</code></td>
								<td>Lease terms</td>
								<td>Rich text / HTML</td>
							</tr>
							<tr>
								<td><code>[hcqb_lease_layout_title]</code></td>
								<td>Standard layout section title</td>
								<td>Plain text</td>
							</tr>
							<tr>
								<td><code>[hcqb_lease_layout_desc]</code></td>
								<td>Standard layout section description</td>
								<td>Rich text / HTML</td>
							</tr>
							<tr>
								<td><code>[hcqb_lease_extras]</code></td>
								<td>Optional extras list</td>
								<td>Styled list with weekly prices</td>
							</tr>
							<tr class="hcqb-instructions-divider">
								<td colspan="3"><strong>Buttons</strong></td>
							</tr>
							<tr>
								<td><code>[hcqb_quote_button]</code></td>
								<td>Get a Custom Quote button</td>
								<td>Hidden via CSS if no active config is linked. Attr: <code>label=""</code></td>
							</tr>
							<tr>
								<td><code>[hcqb_enquire_button]</code></td>
								<td>Enquire Now button (lease)</td>
								<td>Empty if lease is disabled. Attr: <code>label=""</code></td>
							</tr>
						</tbody>
					</table>

					<h3>Usage example</h3>
					<pre class="hcqb-code-block"><code>[hcqb_main_image size="full"]
[hcqb_title]
[hcqb_price]
[hcqb_assembled_price]
[hcqb_rating]
[hcqb_short_desc]
[hcqb_features]
[hcqb_quote_button label="Request a Quote"]</code></pre>

					<p class="description">Using a specific product on any page: <code>[hcqb_title post_id="42"]</code></p>
				</div>
			</details>

			<?php /* ----- Panel 2: PHP Snippets ----- */ ?>
			<details class="hcqb-accordion">
				<summary class="hcqb-accordion__trigger">PHP Snippets — Direct Meta Access</summary>
				<div class="hcqb-accordion__body">
					<p class="description">Use these in theme templates, child theme files, or custom PHP. Replace <code>$post_id</code> with the relevant integer ID (e.g. <code>get_the_ID()</code> inside a loop).</p>

					<h3>Product Info</h3>
					<pre class="hcqb-code-block"><code><?php echo esc_html(
						"// Post title\n" .
						"get_the_title( \$post_id );\n\n" .
						"// Base (flat-pack) price — stored as float\n" .
						"\$price = (float) get_post_meta( \$post_id, 'hcqb_product_price', true );\n\n" .
						"// Star rating — float 0.0–5.0 (supports half-star)\n" .
						"\$rating = (float) get_post_meta( \$post_id, 'hcqb_star_rating', true );\n\n" .
						"// Review count — integer\n" .
						"\$count = absint( get_post_meta( \$post_id, 'hcqb_review_count', true ) );\n\n" .
						"// Short description — plain text\n" .
						"\$short = get_post_meta( \$post_id, 'hcqb_short_description', true );\n\n" .
						"// Full product description — rich text / HTML (pass through wp_kses_post before output)\n" .
						"\$desc = get_post_meta( \$post_id, 'hcqb_product_description', true );\n\n" .
						"// Additional notes — plain text\n" .
						"\$notes = get_post_meta( \$post_id, 'hcqb_additional_notes', true );\n\n" .
						"// Gallery — ordered array of attachment IDs\n" .
						"\$ids = (array) ( get_post_meta( \$post_id, 'hcqb_product_images', true ) ?: [] );\n\n" .
						"// Features — array of [ 'icon_id' => int, 'label' => string ]\n" .
						"\$features = (array) ( get_post_meta( \$post_id, 'hcqb_features', true ) ?: [] );\n\n" .
						"// Plan document — attachment ID; use wp_get_attachment_url() for the download link\n" .
						"\$doc_id  = absint( get_post_meta( \$post_id, 'hcqb_plan_document', true ) );\n" .
						"\$doc_url = \$doc_id ? wp_get_attachment_url( \$doc_id ) : '';\n\n" .
						"// Shipping details link — URL string\n" .
						"\$shipping = get_post_meta( \$post_id, 'hcqb_shipping_details_link', true );"
					); ?></code></pre>

					<h3>Lease Fields</h3>
					<pre class="hcqb-code-block"><code><?php echo esc_html(
						"// Lease enabled — 1 or 0\n" .
						"\$lease_on = (bool) get_post_meta( \$post_id, 'hcqb_lease_enabled', true );\n\n" .
						"// Lease price — float\n" .
						"\$lease_price = (float) get_post_meta( \$post_id, 'hcqb_lease_price', true );\n\n" .
						"// Lease price label — plain text, e.g. \"per week\"\n" .
						"\$label = get_post_meta( \$post_id, 'hcqb_lease_price_label', true ) ?: 'per week';\n\n" .
						"// Lease terms — rich text / HTML\n" .
						"\$terms = get_post_meta( \$post_id, 'hcqb_lease_terms', true );\n\n" .
						"// Standard layout title — plain text\n" .
						"\$layout_title = get_post_meta( \$post_id, 'hcqb_lease_layout_title', true );\n\n" .
						"// Standard layout description — rich text / HTML\n" .
						"\$layout_desc  = get_post_meta( \$post_id, 'hcqb_lease_layout_description', true );\n\n" .
						"// Optional extras — array of [ 'label' => string, 'weekly_price' => float ]\n" .
						"\$extras = (array) ( get_post_meta( \$post_id, 'hcqb_lease_extras', true ) ?: [] );\n\n" .
						"// Enquiry button label — plain text\n" .
						"\$btn = get_post_meta( \$post_id, 'hcqb_enquiry_button_label', true ) ?: 'Enquire Now';"
					); ?></code></pre>

					<h3>Assembled Price (computed — not stored)</h3>
					<pre class="hcqb-code-block"><code><?php echo esc_html(
						"// The assembled price is calculated at runtime from the active config.\n" .
						"\$base_price = (float) get_post_meta( \$post_id, 'hcqb_product_price', true );\n" .
						"\$config     = hcqb_get_active_config_for_product( \$post_id );\n\n" .
						"if ( \$config ) {\n" .
						"    \$questions = get_post_meta( \$config->ID, 'hcqb_questions', true ) ?: [];\n" .
						"    foreach ( \$questions as \$q ) {\n" .
						"        foreach ( \$q['options'] ?? [] as \$opt ) {\n" .
						"            if ( ( \$opt['option_role'] ?? '' ) === 'assembly' ) {\n" .
						"                \$delta     = (float) \$opt['price'];\n" .
						"                \$assembled = ( 'deduction' === ( \$opt['price_type'] ?? '' ) )\n" .
						"                    ? \$base_price - \$delta\n" .
						"                    : \$base_price + \$delta;\n" .
						"                break 2;\n" .
						"            }\n" .
						"        }\n" .
						"    }\n" .
						"}"
					); ?></code></pre>
				</div>
			</details>

			<?php /* ----- Panel 3: General Shortcodes ----- */ ?>
			<details class="hcqb-accordion">
				<summary class="hcqb-accordion__trigger">General Shortcodes — Grids &amp; Quote Builder</summary>
				<div class="hcqb-accordion__body">

					<h3><code>[hc_product_grid]</code></h3>
					<p>Displays a responsive grid of published <code>hc-containers</code> products. Full implementation available in Stage 6.</p>
					<table class="widefat striped hcqb-instructions-table">
						<thead>
							<tr><th>Attribute</th><th>Default</th><th>Description</th></tr>
						</thead>
						<tbody>
							<tr><td><code>columns</code></td><td>3</td><td>Number of grid columns.</td></tr>
							<tr><td><code>limit</code></td><td>12</td><td>Maximum number of products to display.</td></tr>
							<tr><td><code>category</code></td><td><em>all</em></td><td>Filter by container category slug.</td></tr>
							<tr><td><code>orderby</code></td><td>date</td><td><code>date</code> | <code>title</code> | <code>price</code></td></tr>
							<tr><td><code>order</code></td><td>DESC</td><td><code>ASC</code> | <code>DESC</code></td></tr>
						</tbody>
					</table>
					<pre class="hcqb-code-block"><code>[hc_product_grid columns="3" limit="6" orderby="price" order="ASC"]</code></pre>

					<h3><code>[hc_lease_grid]</code></h3>
					<p>Same as <code>[hc_product_grid]</code> but shows only products with lease enabled. Accepts the same attributes. Full implementation available in Stage 6.</p>
					<pre class="hcqb-code-block"><code>[hc_lease_grid columns="2" limit="4"]</code></pre>

					<h3><code>[hc_quote_builder]</code></h3>
					<p>Embeds the two-frame interactive quote builder (product configuration + contact form). Place this shortcode on the page configured in <strong>Settings → HC Quote Builder → Quote Builder → Quote Builder Page</strong>. Available in Stage 7.</p>
					<pre class="hcqb-code-block"><code>[hc_quote_builder]</code></pre>
					<p class="description">The quote builder reads the <code>?product=</code> URL parameter to load the correct container configuration. Place this shortcode on exactly one page — use the Quote Builder Page setting to register it.</p>

				</div>
			</details>

		</div>
		<?php
	}

	// -------------------------------------------------------------------------
	// Sanitisation callback
	// Called by WordPress before storing the hcqb_settings option.
	// Keys in submission_status_labels come from hidden inputs — never regenerated here.
	// -------------------------------------------------------------------------

	public static function sanitise( mixed $raw ): array {
		if ( ! is_array( $raw ) ) {
			return self::defaults();
		}

		$clean = [];

		// ---- Tab 1 — General ----
		$clean['google_maps_api_key'] = sanitize_text_field( $raw['google_maps_api_key'] ?? '' );
		$clean['warehouse_address']   = sanitize_text_field( $raw['warehouse_address']   ?? '' );
		$clean['warehouse_lat']       = sanitize_text_field( $raw['warehouse_lat']       ?? '' );
		$clean['warehouse_lng']       = sanitize_text_field( $raw['warehouse_lng']       ?? '' );

		$allowed_countries       = [ 'AU', 'NZ' ];
		$submitted_countries     = (array) ( $raw['supported_countries'] ?? [] );
		$clean['supported_countries'] = array_values(
			array_filter( $submitted_countries, fn( $c ) => in_array( $c, $allowed_countries, true ) )
		);
		if ( empty( $clean['supported_countries'] ) ) {
			$clean['supported_countries'] = [ 'AU' ];
		}

		// ---- Tab 2 — Email ----
		$clean['admin_email']            = sanitize_email( $raw['admin_email']             ?? '' );
		$clean['from_name']              = sanitize_text_field( $raw['from_name']           ?? '' );
		$clean['from_email']             = sanitize_email( $raw['from_email']               ?? '' );
		$clean['admin_email_subject']    = sanitize_text_field( $raw['admin_email_subject'] ?? '' );
		$clean['customer_email_subject'] = sanitize_text_field( $raw['customer_email_subject'] ?? '' );

		// ---- Tab 3 — Quote Builder ----
		$clean['quote_builder_page_id'] = absint( $raw['quote_builder_page_id'] ?? 0 );
		$clean['product_change_alert']  = sanitize_text_field( $raw['product_change_alert'] ?? '' );
		$clean['submit_button_label']   = sanitize_text_field( $raw['submit_button_label']  ?? '' );
		$clean['consent_text']          = sanitize_text_field( $raw['consent_text']          ?? '' );
		$clean['privacy_fine_print']    = wp_kses_post( $raw['privacy_fine_print']          ?? '' );

		// ---- Tab 4 — Prefix options ----
		$clean['prefix_options'] = [];
		foreach ( (array) ( $raw['prefix_options'] ?? [] ) as $prefix ) {
			$sanitised = sanitize_text_field( $prefix );
			if ( '' !== $sanitised ) {
				$clean['prefix_options'][] = $sanitised;
			}
		}
		if ( empty( $clean['prefix_options'] ) ) {
			$clean['prefix_options'] = self::defaults()['prefix_options'];
		}

		// ---- Tab 4 — Status labels ----
		// Keys are read from the submitted hidden inputs — never generated or modified here.
		$clean['submission_status_labels'] = [];
		foreach ( (array) ( $raw['submission_status_labels'] ?? [] ) as $row ) {
			$key   = sanitize_key( $row['key']   ?? '' );
			$label = sanitize_text_field( $row['label'] ?? '' );
			if ( $key && $label ) {
				$clean['submission_status_labels'][] = compact( 'key', 'label' );
			}
		}
		if ( empty( $clean['submission_status_labels'] ) ) {
			$clean['submission_status_labels'] = self::defaults()['submission_status_labels'];
		}

		return $clean;
	}

	// -------------------------------------------------------------------------
	// Default values — used on first-ever save and as fallback via wp_parse_args
	// -------------------------------------------------------------------------

	private static function defaults(): array {
		return [
			// General
			'google_maps_api_key' => '',
			'warehouse_address'   => '',
			'warehouse_lat'       => '',
			'warehouse_lng'       => '',
			'supported_countries' => [ 'AU' ],
			// Email
			'admin_email'            => get_option( 'admin_email', '' ),
			'from_name'              => get_option( 'blogname', '' ),
			'from_email'             => get_option( 'admin_email', '' ),
			'admin_email_subject'    => 'New Quote Request — {product_name}',
			'customer_email_subject' => 'Your Quote Request — {product_name}',
			// Quote Builder
			'quote_builder_page_id' => 0,
			'product_change_alert'  => 'Changing the product will reset your current selections. Continue?',
			'submit_button_label'   => 'Submit Quote Request',
			'consent_text'          => 'I agree to be contacted regarding this quote request.',
			'privacy_fine_print'    => '',
			// Form Options
			'prefix_options'           => [ 'Mr', 'Mrs', 'Ms', 'Dr' ],
			'submission_status_labels' => [
				[ 'key' => 'status_1', 'label' => 'New'       ],
				[ 'key' => 'status_2', 'label' => 'Contacted' ],
				[ 'key' => 'status_3', 'label' => 'Closed'    ],
			],
		];
	}
}
