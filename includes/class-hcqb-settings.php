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
			'styles'        => 'Styles',
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
					self::render_tab_styles( $settings, $active_tab );
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
		$default_product_id  = absint( $settings['default_product_id'] ?? 0 );
		$all_containers      = get_posts( [
			'post_type'      => 'hc-containers',
			'post_status'    => 'publish',
			'numberposts'    => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'no_found_rows'  => true,
		] );
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
				<tr>
					<th scope="row">
						<label for="hcqb_default_product_id">Default Product</label>
					</th>
					<td>
						<select id="hcqb_default_product_id"
						        name="<?php echo esc_attr( self::OPTION_KEY ); ?>[default_product_id]">
							<option value="0">— None (show error when no ?product= set) —</option>
							<?php foreach ( $all_containers as $container ) : ?>
								<option value="<?php echo esc_attr( $container->ID ); ?>"
									<?php selected( $default_product_id, $container->ID ); ?>>
									<?php echo esc_html( $container->post_title ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<p class="description">When the quote builder page is visited without a <code>?product=</code> parameter, this product's questionnaire loads automatically.</p>
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
		$visible             = $active_tab === 'quote-builder';
		$pages               = get_pages( [ 'sort_column' => 'post_title', 'sort_order' => 'ASC' ] );
		$selected_page       = absint( $settings['quote_builder_page_id'] ?? 0 );
		$quote_form_layout   = $settings['quote_form_layout'] ?? 'multistep';
		$default_config_id   = absint( $settings['default_config_id'] ?? 0 );
		$all_configs         = get_posts( [
			'post_type'      => 'hc-quote-configs',
			'post_status'    => 'publish',
			'numberposts'    => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'no_found_rows'  => true,
		] );
		?>
		<div class="hcqb-tab-panel<?php echo $visible ? ' hcqb-tab-panel--active' : ''; ?>" id="hcqb-tab-quote-builder">
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">
						<label for="hcqb_default_config_id">Default Config</label>
					</th>
					<td>
						<select id="hcqb_default_config_id"
						        name="<?php echo esc_attr( self::OPTION_KEY ); ?>[default_config_id]">
							<option value="0">— No default —</option>
							<?php foreach ( $all_configs as $cfg ) : ?>
								<option value="<?php echo esc_attr( $cfg->ID ); ?>"
								        <?php selected( $default_config_id, $cfg->ID ); ?>>
									<?php echo esc_html( $cfg->post_title ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<p class="description">Fallback config used for any product that does not have its own linked config. The selected config must have its status set to <strong>Active</strong>.</p>
					</td>
				</tr>
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
					<th scope="row">Form Layout</th>
					<td>
						<fieldset>
							<legend class="screen-reader-text">Form Layout</legend>
							<label>
								<input type="radio"
								       name="<?php echo esc_attr( self::OPTION_KEY ); ?>[quote_form_layout]"
								       value="multistep"
								       <?php checked( $quote_form_layout, 'multistep' ); ?>>
								Multi-step &mdash; show the contact form only after the customer clicks Continue
							</label><br>
							<label>
								<input type="radio"
								       name="<?php echo esc_attr( self::OPTION_KEY ); ?>[quote_form_layout]"
								       value="always_show"
								       <?php checked( $quote_form_layout, 'always_show' ); ?>>
								Always visible &mdash; display the contact form alongside the configuration questions
							</label>
						</fieldset>
						<p class="description">Controls whether the contact form is shown as a second step or always visible.</p>
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
				<tr>
					<th scope="row">View Angles</th>
					<td>
						<label>
							<input type="hidden" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[show_view_angles]" value="0">
							<input type="checkbox"
							       name="<?php echo esc_attr( self::OPTION_KEY ); ?>[show_view_angles]"
							       value="1"
							       <?php checked( $settings['show_view_angles'] ?? 0, 1 ); ?>>
							Show Front / Side / Back / Interior view toggle in the quote builder
						</label>
						<p class="description">When disabled, the image preview is fixed to the Front view and the view switcher buttons are hidden.</p>
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
	// Tab 5 — Styles
	// Exposes CSS custom properties for the product page as editable fields.
	// Values are stored in hcqb_settings and output via wp_add_inline_style().
	// -------------------------------------------------------------------------

	private static function render_tab_styles( array $settings, string $active_tab ): void {
		$visible = $active_tab === 'styles';

		// Resolve values — settings already merged with defaults via wp_parse_args.
		$brand       = $settings['style_pp_brand']      ?? '#ED1C23';
		$brand_dark  = $settings['style_pp_brand_dark'] ?? '#c4151a';
		$border      = $settings['style_pp_border']     ?? '#e2e2e2';
		$text        = $settings['style_pp_text']        ?? '#1d2327';
		$muted       = $settings['style_pp_muted']       ?? '#6b7280';
		$bg          = $settings['style_pp_bg']          ?? '#ffffff';
		$bg_alt      = $settings['style_pp_bg_alt']      ?? '#f6f7f7';
		$star_full   = $settings['style_pp_star_full']   ?? '#f0a500';
		$star_empty  = $settings['style_pp_star_empty']  ?? '#dcdcdc';
		$max         = $settings['style_pp_max']         ?? '1100px';
		$gap         = $settings['style_pp_gap']         ?? '40px';
		$gallery     = $settings['style_pp_gallery']     ?? '50%';
		$radius      = $settings['style_pp_radius']      ?? '8px';
		?>
		<div class="hcqb-tab-panel<?php echo $visible ? ' hcqb-tab-panel--active' : ''; ?>" id="hcqb-tab-styles">
			<p>These values override the CSS custom properties on the product page. Changes take effect immediately after saving.</p>

			<h2>Colours</h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="hcqb_style_pp_brand">Brand Colour</label></th>
					<td>
						<input type="color"
						       id="hcqb_style_pp_brand"
						       name="<?php echo esc_attr( self::OPTION_KEY ); ?>[style_pp_brand]"
						       value="<?php echo esc_attr( $brand ); ?>">
						<p class="description">Primary brand colour — buttons, icons, accents. CSS variable: <code>--hcqb-pp-brand</code></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="hcqb_style_pp_brand_dark">Brand Colour (Dark)</label></th>
					<td>
						<input type="color"
						       id="hcqb_style_pp_brand_dark"
						       name="<?php echo esc_attr( self::OPTION_KEY ); ?>[style_pp_brand_dark]"
						       value="<?php echo esc_attr( $brand_dark ); ?>">
						<p class="description">Darker variant — hover states on brand elements. CSS variable: <code>--hcqb-pp-brand-dark</code></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="hcqb_style_pp_text">Text Colour</label></th>
					<td>
						<input type="color"
						       id="hcqb_style_pp_text"
						       name="<?php echo esc_attr( self::OPTION_KEY ); ?>[style_pp_text]"
						       value="<?php echo esc_attr( $text ); ?>">
						<p class="description">Primary body text colour. CSS variable: <code>--hcqb-pp-text</code></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="hcqb_style_pp_muted">Muted Text Colour</label></th>
					<td>
						<input type="color"
						       id="hcqb_style_pp_muted"
						       name="<?php echo esc_attr( self::OPTION_KEY ); ?>[style_pp_muted]"
						       value="<?php echo esc_attr( $muted ); ?>">
						<p class="description">Secondary / helper text. CSS variable: <code>--hcqb-pp-muted</code></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="hcqb_style_pp_border">Border Colour</label></th>
					<td>
						<input type="color"
						       id="hcqb_style_pp_border"
						       name="<?php echo esc_attr( self::OPTION_KEY ); ?>[style_pp_border]"
						       value="<?php echo esc_attr( $border ); ?>">
						<p class="description">Dividers and border lines. CSS variable: <code>--hcqb-pp-border</code></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="hcqb_style_pp_bg">Background</label></th>
					<td>
						<input type="color"
						       id="hcqb_style_pp_bg"
						       name="<?php echo esc_attr( self::OPTION_KEY ); ?>[style_pp_bg]"
						       value="<?php echo esc_attr( $bg ); ?>">
						<p class="description">Main page background. CSS variable: <code>--hcqb-pp-bg</code></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="hcqb_style_pp_bg_alt">Background (Alt)</label></th>
					<td>
						<input type="color"
						       id="hcqb_style_pp_bg_alt"
						       name="<?php echo esc_attr( self::OPTION_KEY ); ?>[style_pp_bg_alt]"
						       value="<?php echo esc_attr( $bg_alt ); ?>">
						<p class="description">Alternate background — cards, tab fills. CSS variable: <code>--hcqb-pp-bg-alt</code></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="hcqb_style_pp_star_full">Star Colour (Filled)</label></th>
					<td>
						<input type="color"
						       id="hcqb_style_pp_star_full"
						       name="<?php echo esc_attr( self::OPTION_KEY ); ?>[style_pp_star_full]"
						       value="<?php echo esc_attr( $star_full ); ?>">
						<p class="description">Filled star in the rating display. CSS variable: <code>--hcqb-pp-star-full</code></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="hcqb_style_pp_star_empty">Star Colour (Empty)</label></th>
					<td>
						<input type="color"
						       id="hcqb_style_pp_star_empty"
						       name="<?php echo esc_attr( self::OPTION_KEY ); ?>[style_pp_star_empty]"
						       value="<?php echo esc_attr( $star_empty ); ?>">
						<p class="description">Empty star in the rating display. CSS variable: <code>--hcqb-pp-star-empty</code></p>
					</td>
				</tr>
			</table>

			<h2>Layout</h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="hcqb_style_pp_max">Max Content Width</label></th>
					<td>
						<input type="text"
						       id="hcqb_style_pp_max"
						       name="<?php echo esc_attr( self::OPTION_KEY ); ?>[style_pp_max]"
						       value="<?php echo esc_attr( $max ); ?>"
						       class="small-text"
						       placeholder="1100px">
						<p class="description">Maximum width of the product page content area. CSS variable: <code>--hcqb-pp-max</code></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="hcqb_style_pp_gap">Column Gap</label></th>
					<td>
						<input type="text"
						       id="hcqb_style_pp_gap"
						       name="<?php echo esc_attr( self::OPTION_KEY ); ?>[style_pp_gap]"
						       value="<?php echo esc_attr( $gap ); ?>"
						       class="small-text"
						       placeholder="40px">
						<p class="description">Gap between the gallery and details columns. CSS variable: <code>--hcqb-pp-gap</code></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="hcqb_style_pp_gallery">Gallery Column Width</label></th>
					<td>
						<input type="text"
						       id="hcqb_style_pp_gallery"
						       name="<?php echo esc_attr( self::OPTION_KEY ); ?>[style_pp_gallery]"
						       value="<?php echo esc_attr( $gallery ); ?>"
						       class="small-text"
						       placeholder="50%">
						<p class="description">Width of the gallery column (e.g. <code>50%</code>, <code>420px</code>). CSS variable: <code>--hcqb-pp-gallery</code></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="hcqb_style_pp_radius">Border Radius</label></th>
					<td>
						<input type="text"
						       id="hcqb_style_pp_radius"
						       name="<?php echo esc_attr( self::OPTION_KEY ); ?>[style_pp_radius]"
						       value="<?php echo esc_attr( $radius ); ?>"
						       class="small-text"
						       placeholder="8px">
						<p class="description">Border radius for cards and buttons. CSS variable: <code>--hcqb-pp-radius</code></p>
					</td>
				</tr>
			</table>
		</div>
		<?php
	}

	// -------------------------------------------------------------------------
	// Tab 6 — Instructions (read-only reference panel — rendered outside <form>)
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
					<p class="description">Use these in theme templates, child theme files, or custom PHP. Replace <code>$post_id</code> with the relevant integer ID (e.g. <code>get_the_ID()</code> inside a loop). Fields that return arrays (gallery, features, extras) need a <code>foreach</code> loop — see the dedicated examples below.</p>

					<h3>Scalar Fields — Simple Values</h3>
					<pre class="hcqb-code-block"><code><?php echo esc_html(
						"// Post title — plain text\n" .
						"get_the_title( \$post_id );\n\n" .
						"// Base (flat-pack) price — stored as float\n" .
						"\$price = (float) get_post_meta( \$post_id, 'hcqb_product_price', true );\n" .
						"echo '\$' . number_format( \$price, 2 );  // e.g. \$7,500.00\n\n" .
						"// Star rating — float 0.0–5.0 (half-star supported, e.g. 4.5)\n" .
						"\$rating = (float) get_post_meta( \$post_id, 'hcqb_star_rating', true );\n\n" .
						"// Review count — integer\n" .
						"\$count = absint( get_post_meta( \$post_id, 'hcqb_review_count', true ) );\n\n" .
						"// Short description — plain text\n" .
						"\$short = get_post_meta( \$post_id, 'hcqb_short_description', true );\n" .
						"echo esc_html( \$short );\n\n" .
						"// Full description — rich HTML; always sanitise before output\n" .
						"\$desc = get_post_meta( \$post_id, 'hcqb_product_description', true );\n" .
						"echo wp_kses_post( \$desc );\n\n" .
						"// Additional notes — plain text\n" .
						"\$notes = get_post_meta( \$post_id, 'hcqb_additional_notes', true );\n\n" .
						"// Plan document — attachment ID (0 when none is set)\n" .
						"\$doc_id  = absint( get_post_meta( \$post_id, 'hcqb_plan_document', true ) );\n" .
						"\$doc_url = \$doc_id ? wp_get_attachment_url( \$doc_id ) : '';\n\n" .
						"// Shipping details link — URL string (empty when unset)\n" .
						"\$shipping_url = get_post_meta( \$post_id, 'hcqb_shipping_details_link', true );"
					); ?></code></pre>

					<h3>Gallery Images — Loop Example</h3>
					<p class="description">The gallery is stored as a plain indexed array of WordPress attachment IDs, in the display order set via drag-and-drop in the admin.</p>
					<pre class="hcqb-code-block"><code><?php echo esc_html(
						"// Fetch the gallery — returns [] when no images are saved.\n" .
						"\$ids = (array) ( get_post_meta( \$post_id, 'hcqb_product_images', true ) ?: [] );\n\n" .
						"// Primary / hero image — always the first item in the array\n" .
						"\$primary_id  = \$ids[0] ?? 0;\n" .
						"\$primary_url = \$primary_id ? wp_get_attachment_image_url( \$primary_id, 'full' ) : '';\n\n" .
						"// Loop — render every image\n" .
						"foreach ( \$ids as \$img_id ) {\n" .
						"    // wp_get_attachment_image() returns a complete <img> tag\n" .
						"    // with srcset, sizes, and the alt text stored in the Media Library.\n" .
						"    echo wp_get_attachment_image( \$img_id, 'large' );\n" .
						"}\n\n" .
						"// Alternatively, fetch just the URL (e.g. for a CSS background-image):\n" .
						"foreach ( \$ids as \$img_id ) {\n" .
						"    \$url = wp_get_attachment_image_url( \$img_id, 'large' );\n" .
						"    if ( \$url ) {\n" .
						"        echo '<img src=\"' . esc_url( \$url ) . '\" alt=\"\">';\n" .
						"    }\n" .
						"}"
					); ?></code></pre>

					<h3>Product Features — Loop Example</h3>
					<p class="description">Features are stored as a sequential array of associative arrays. Each row has exactly two keys: <code>icon_id</code> (attachment ID, 0 when unset) and <code>label</code> (string).</p>
					<pre class="hcqb-code-block"><code><?php echo esc_html(
						"\$features = (array) ( get_post_meta( \$post_id, 'hcqb_features', true ) ?: [] );\n\n" .
						"// Structure of \$features:\n" .
						"// [\n" .
						"//   0 => [ 'icon_id' => 45, 'label' => 'Fully insulated' ],\n" .
						"//   1 => [ 'icon_id' => 0,  'label' => 'Lockable door'   ],\n" .
						"//   ...\n" .
						"// ]\n\n" .
						"if ( \$features ) {\n" .
						"    echo '<ul class=\"hcqb-features-list\">';\n\n" .
						"    foreach ( \$features as \$feat ) {\n" .
						"        \$label   = \$feat['label']   ?? '';\n" .
						"        \$icon_id = absint( \$feat['icon_id'] ?? 0 );\n\n" .
						"        echo '<li class=\"hcqb-feature\">';\n\n" .
						"        // Icon — optional; wrap in brand-coloured circular badge\n" .
						"        if ( \$icon_id ) {\n" .
						"            \$icon_url = wp_get_attachment_image_url( \$icon_id, 'thumbnail' );\n" .
						"            if ( \$icon_url ) {\n" .
						"                echo '<span class=\"hcqb-feature__icon-wrap\">';\n" .
						"                echo '<img src=\"' . esc_url( \$icon_url ) . '\"';\n" .
						"                echo ' class=\"hcqb-feature__icon\" alt=\"\" width=\"20\" height=\"20\">';\n" .
						"                echo '</span>';\n" .
						"            }\n" .
						"        }\n\n" .
						"        echo '<span class=\"hcqb-feature__label\">' . esc_html( \$label ) . '</span>';\n" .
						"        echo '</li>';\n" .
						"    }\n\n" .
						"    echo '</ul>';\n" .
						"}"
					); ?></code></pre>

					<h3>Lease Scalar Fields</h3>
					<pre class="hcqb-code-block"><code><?php echo esc_html(
						"// Always check lease is enabled before rendering lease content\n" .
						"\$lease_on = (bool) get_post_meta( \$post_id, 'hcqb_lease_enabled', true );\n\n" .
						"if ( \$lease_on ) {\n" .
						"    // Lease price — float\n" .
						"    \$lease_price = (float) get_post_meta( \$post_id, 'hcqb_lease_price', true );\n" .
						"    echo '\$' . number_format( \$lease_price, 2 );  // e.g. \$299.00\n\n" .
						"    // Lease price label — plain text (defaults to 'per week')\n" .
						"    \$lease_label = get_post_meta( \$post_id, 'hcqb_lease_price_label', true ) ?: 'per week';\n\n" .
						"    // Lease terms — rich HTML\n" .
						"    \$terms = get_post_meta( \$post_id, 'hcqb_lease_terms', true );\n" .
						"    echo wp_kses_post( \$terms );\n\n" .
						"    // Standard layout title — plain text\n" .
						"    \$layout_title = get_post_meta( \$post_id, 'hcqb_lease_layout_title', true );\n\n" .
						"    // Standard layout description — rich HTML\n" .
						"    \$layout_desc = get_post_meta( \$post_id, 'hcqb_lease_layout_description', true );\n" .
						"    echo wp_kses_post( \$layout_desc );\n\n" .
						"    // Enquiry button label — plain text\n" .
						"    \$btn = get_post_meta( \$post_id, 'hcqb_enquiry_button_label', true ) ?: 'Enquire Now';\n" .
						"}"
					); ?></code></pre>

					<h3>Optional Extras — Loop Example</h3>
					<p class="description">Extras are stored as a sequential array of associative arrays. Each row has <code>label</code> (string) and <code>weekly_price</code> (float).</p>
					<pre class="hcqb-code-block"><code><?php echo esc_html(
						"\$extras = (array) ( get_post_meta( \$post_id, 'hcqb_lease_extras', true ) ?: [] );\n\n" .
						"// Structure of \$extras:\n" .
						"// [\n" .
						"//   0 => [ 'label' => 'Climate control pack', 'weekly_price' => 12.50 ],\n" .
						"//   1 => [ 'label' => 'Security upgrade',     'weekly_price' => 8.00  ],\n" .
						"//   ...\n" .
						"// ]\n\n" .
						"if ( \$extras ) {\n" .
						"    echo '<ul>';\n\n" .
						"    foreach ( \$extras as \$extra ) {\n" .
						"        \$label = esc_html( \$extra['label']        ?? '' );\n" .
						"        \$price = number_format( (float) ( \$extra['weekly_price'] ?? 0 ), 2 );\n\n" .
						"        echo '<li>' . \$label . ' — \$' . \$price . ' / week</li>';\n" .
						"    }\n\n" .
						"    echo '</ul>';\n" .
						"}"
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

		<?php /* ----- Panel 4: Taxonomies ----- */ ?>
		<details class="hcqb-accordion">
			<summary class="hcqb-accordion__trigger">Taxonomies — Container Categories</summary>
			<div class="hcqb-accordion__body">
				<p class="description">Container products use the <code>hc-container-category</code> taxonomy. The examples below use standard WordPress functions — no plugin-specific helpers are needed.</p>

				<h3>Get Categories for a Single Product</h3>
				<p class="description"><code>get_the_terms()</code> returns an array of <code>WP_Term</code> objects, <code>false</code> if none are assigned, or a <code>WP_Error</code>. Always check the return value before looping.</p>
				<pre class="hcqb-code-block"><code><?php echo esc_html(
					"// Fetch all categories assigned to this product\n" .
					"\$terms = get_the_terms( \$post_id, 'hc-container-category' );\n\n" .
					"if ( \$terms && ! is_wp_error( \$terms ) ) {\n" .
					"    foreach ( \$terms as \$term ) {\n" .
					"        echo esc_html( \$term->name );            // e.g. 'Office Containers'\n" .
					"        echo esc_html( \$term->slug );            // e.g. 'office-containers'\n" .
					"        echo absint( \$term->term_id );           // e.g. 12\n" .
					"        echo esc_url( get_term_link( \$term ) );  // category archive URL\n" .
					"    }\n" .
					"}\n\n" .
					"// Shortcut — render a comma-separated list of linked category names:\n" .
					"\$links = get_the_term_list( \$post_id, 'hc-container-category', '', ', ' );\n" .
					"if ( \$links && ! is_wp_error( \$links ) ) {\n" .
					"    echo wp_kses_post( \$links );\n" .
					"    // Outputs: <a href=\"...\">Office</a>, <a href=\"...\">Storage</a>\n" .
					"}"
				); ?></code></pre>

				<h3>Get All Categories (for Filters / Navigation)</h3>
				<p class="description"><code>get_terms()</code> retrieves every term in the taxonomy regardless of which post is being viewed. Set <code>hide_empty</code> to <code>true</code> to skip categories with no published products.</p>
				<pre class="hcqb-code-block"><code><?php echo esc_html(
					"\$all_terms = get_terms( [\n" .
					"    'taxonomy'   => 'hc-container-category',\n" .
					"    'hide_empty' => true,   // omit categories with no published products\n" .
					"    'orderby'    => 'name',\n" .
					"    'order'      => 'ASC',\n" .
					"] );\n\n" .
					"if ( \$all_terms && ! is_wp_error( \$all_terms ) ) {\n" .
					"    foreach ( \$all_terms as \$term ) {\n" .
					"        echo esc_html( \$term->name );            // term name\n" .
					"        echo absint( \$term->count );             // number of products\n" .
					"        echo esc_url( get_term_link( \$term ) );  // archive page URL\n" .
					"    }\n" .
					"}"
				); ?></code></pre>

				<h3>Query Products by Category</h3>
				<p class="description">Use <code>WP_Query</code> with a <code>tax_query</code> argument to fetch products belonging to a specific category. Remember to call <code>wp_reset_postdata()</code> after the loop.</p>
				<pre class="hcqb-code-block"><code><?php echo esc_html(
					"// --- By category slug ---\n" .
					"\$query = new WP_Query( [\n" .
					"    'post_type'      => 'hc-containers',\n" .
					"    'post_status'    => 'publish',\n" .
					"    'posts_per_page' => 12,\n" .
					"    'tax_query'      => [\n" .
					"        [\n" .
					"            'taxonomy' => 'hc-container-category',\n" .
					"            'field'    => 'slug',\n" .
					"            'terms'    => 'office-containers',  // single slug\n" .
					"        ],\n" .
					"    ],\n" .
					"] );\n\n" .
					"while ( \$query->have_posts() ) {\n" .
					"    \$query->the_post();\n" .
					"    \$post_id = get_the_ID();\n" .
					"    // ... use meta helpers above\n" .
					"}\n" .
					"wp_reset_postdata();\n\n" .
					"// --- By term ID (match any of several categories) ---\n" .
					"\$query = new WP_Query( [\n" .
					"    'post_type'  => 'hc-containers',\n" .
					"    'tax_query'  => [\n" .
					"        [\n" .
					"            'taxonomy' => 'hc-container-category',\n" .
					"            'field'    => 'term_id',\n" .
					"            'terms'    => [ 12, 15 ],  // products in either category\n" .
					"            'operator' => 'IN',        // 'IN' | 'NOT IN' | 'AND'\n" .
					"        ],\n" .
					"    ],\n" .
					"] );"
				); ?></code></pre>

				<h3>Check Whether a Product Belongs to a Category</h3>
				<pre class="hcqb-code-block"><code><?php echo esc_html(
					"// Returns true if the product is in the given category (slug, ID, or name)\n" .
					"if ( has_term( 'office-containers', 'hc-container-category', \$post_id ) ) {\n" .
					"    // product is in the 'Office Containers' category\n" .
					"}"
				); ?></code></pre>
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
		$clean['default_product_id']  = absint( $raw['default_product_id']               ?? 0 );

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
		$clean['default_config_id']     = absint( $raw['default_config_id']     ?? 0 );
		$clean['quote_builder_page_id'] = absint( $raw['quote_builder_page_id'] ?? 0 );
		$allowed_layouts                = [ 'multistep', 'always_show' ];
		$clean['quote_form_layout']     = in_array( $raw['quote_form_layout'] ?? '', $allowed_layouts, true )
			? $raw['quote_form_layout']
			: 'multistep';
		$clean['product_change_alert']  = sanitize_text_field( $raw['product_change_alert'] ?? '' );
		$clean['submit_button_label']   = sanitize_text_field( $raw['submit_button_label']  ?? '' );
		$clean['consent_text']          = sanitize_text_field( $raw['consent_text']          ?? '' );
		$clean['privacy_fine_print']    = wp_kses_post( $raw['privacy_fine_print']          ?? '' );
		$clean['show_view_angles']      = absint( $raw['show_view_angles'] ?? 0 ) ? 1 : 0;

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

		// ---- Tab 5 — Styles ----
		$color_keys = [
			'style_pp_brand', 'style_pp_brand_dark', 'style_pp_border',
			'style_pp_text',  'style_pp_muted',       'style_pp_bg',
			'style_pp_bg_alt', 'style_pp_star_full',  'style_pp_star_empty',
		];
		foreach ( $color_keys as $key ) {
			// sanitize_hex_color returns null on invalid value; fall back to default.
			$sanitized        = sanitize_hex_color( $raw[ $key ] ?? '' );
			$clean[ $key ]    = $sanitized ?? self::defaults()[ $key ];
		}

		$dim_keys = [ 'style_pp_max', 'style_pp_gap', 'style_pp_gallery', 'style_pp_radius' ];
		foreach ( $dim_keys as $key ) {
			$val           = sanitize_text_field( $raw[ $key ] ?? '' );
			// Allow only safe CSS dimension values: digits + optional decimal + unit.
			$clean[ $key ] = preg_match( '/^[\d.]+(px|%|em|rem|vw|vh)$/', $val ) ? $val : self::defaults()[ $key ];
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
			'default_product_id'  => 0,
			// Email
			'admin_email'            => get_option( 'admin_email', '' ),
			'from_name'              => get_option( 'blogname', '' ),
			'from_email'             => get_option( 'admin_email', '' ),
			'admin_email_subject'    => 'New Quote Request — {product_name}',
			'customer_email_subject' => 'Your Quote Request — {product_name}',
			// Quote Builder
			'default_config_id'     => 0,
			'quote_builder_page_id' => 0,
			'quote_form_layout'     => 'multistep',
			'product_change_alert'  => 'Changing the product will reset your current selections. Continue?',
			'submit_button_label'   => 'Submit Quote Request',
			'consent_text'          => 'I agree to be contacted regarding this quote request.',
			'privacy_fine_print'    => '',
			'show_view_angles'      => 0,
			// Form Options
			'prefix_options'           => [ 'Mr', 'Mrs', 'Ms', 'Dr' ],
			'submission_status_labels' => [
				[ 'key' => 'status_1', 'label' => 'New'       ],
				[ 'key' => 'status_2', 'label' => 'Contacted' ],
				[ 'key' => 'status_3', 'label' => 'Closed'    ],
			],
			// Styles — CSS custom property defaults matching hcqb-product-page.css
			'style_pp_brand'      => '#ED1C23',
			'style_pp_brand_dark' => '#c4151a',
			'style_pp_border'     => '#e2e2e2',
			'style_pp_text'       => '#1d2327',
			'style_pp_muted'      => '#6b7280',
			'style_pp_bg'         => '#ffffff',
			'style_pp_bg_alt'     => '#f6f7f7',
			'style_pp_star_full'  => '#f0a500',
			'style_pp_star_empty' => '#dcdcdc',
			'style_pp_max'        => '1100px',
			'style_pp_gap'        => '40px',
			'style_pp_gallery'    => '50%',
			'style_pp_radius'     => '8px',
		];
	}
}
