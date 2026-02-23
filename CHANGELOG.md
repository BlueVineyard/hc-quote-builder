# HC Quote Builder — Changelog

All notable changes to this project are documented here.
Format: `[Version] — YYYY-MM-DD` | Categories: **Added**, **Changed**, **Fixed**, **Removed**
Versioning: `HCQB_VERSION` minor-bumped on each stage completion — Stage 1 → `1.0.0`, Stage 2 → `1.1.0`, Stage N → `1.(N−1).0`

---

## Pre-Development

### [Docs 1.2] — 2026-02-22

**Added**
- `DEVELOPMENT.md` v1.0 — Full 11-stage development document with file structure, implementation tasks, critical code patterns, acceptance criteria checklists, and QA test suite
- `CHANGELOG.md` — This file. Tracks all development progress and document updates

**Changed**
- `ARCHITECTURE.md` updated from v1.1 → v1.2 incorporating developer review feedback:
  - Option slugs and question keys now explicitly immutable after first save (never regenerate on label rename)
  - Submission status decoupled from label text — stores stable internal key (`status_1` etc.); label resolved at render time
  - `Option Role` field added to quote config options — `assembly` role identifies the option used for assembled price calculation on product page
  - Single-level conditional constraint documented as explicit v1.0 constraint with forward-compatibility note
  - Image rule tie-breaking via admin drag-and-drop order documented in §5.2 and §12.3
  - `Show in Feature Pill` toggle added to question fields; feature pill behaviour in §6.6 updated accordingly
  - Google Maps API key restriction documented as a required deployment step (not optional) in Tab 1 field description
  - WordPress nonce verification added as Step 1 of server-side processing pipeline in §8.2
  - Honeypot spam protection field added to Frame 2 form fields table in §7.2
  - `absint()` sanitisation requirement documented for `?product=` URL parameter in §8.2 and §14
  - Questions Array JSON example in §15.4 and Plugin Settings structure in §15.6 updated to reflect new fields
  - Version footer updated to reflect all changes

---

## Development

### [Stage 1] — 2026-02-22
**Plugin Bootstrap + CPT Registration**

**Added**
- [x] `hc-quote-builder.php` — Main plugin bootstrap; defines `HCQB_VERSION`, `HCQB_PLUGIN_DIR`, `HCQB_PLUGIN_URL`; registers activation/deactivation hooks; boots singleton
- [x] `uninstall.php` — Deletes `hcqb_settings`, `hcqb_last_email_error`, and all posts + meta for all three CPTs on plugin deletion
- [x] `includes/class-hcqb-plugin.php` — Singleton loader; centralises all hook registration; future stage hooks pre-listed as commented stubs ready to uncomment
- [x] `includes/class-hcqb-post-types.php` — Registers `hc-containers` (public, `/portables/`), `hc-quote-configs` (private, admin menu), `hc-quote-submissions` (private, admin menu, create_posts blocked), and `hc-container-category` taxonomy
- [x] `includes/class-hcqb-helpers.php` — Implements `hcqb_get_setting()`, `hcqb_generate_slug()` (underscore-separated via `sanitize_title()`), `hcqb_format_price()`, `hcqb_get_active_config_for_product()`

---

### [Stage 2] — 2026-02-22
**Plugin Settings Page**

**Added**
- [x] `includes/class-hcqb-settings.php` — Settings page class; registers `Settings → HC Quote Builder` submenu via `add_options_page()`; stores all plugin config in a single `hcqb_settings` option with a full sanitisation callback; renders a four-tab form (General, Email, Quote Builder, Form Options); enqueues assets conditionally on the settings screen only
- [x] `assets/js/admin/hcqb-admin-settings.js` — Prefix options and status labels repeaters (add / remove / drag-and-drop reorder); status keys generated once via `Date.now()` and stored in hidden inputs — never regenerated on label rename; Google Places Autocomplete for warehouse address field (auto-fills lat/lng on place selection) via `hcqbSettingsMapsInit` callback
- [x] `assets/css/admin/hcqb-admin-global.css` — Full shared admin stylesheet: tab navigation (settings + meta box variants), tab panel show/hide, repeater rows, drag handle, drag-and-drop states, remove button, status key badge, toggle switch component, inline notice, slug lock label, section headings

**Changed**
- [x] `includes/class-hcqb-plugin.php` — Stage 2 hooks activated: `admin_menu` → `register_settings_page`, `admin_init` → `register_setting`, `admin_enqueue_scripts` → `enqueue_assets`; Stage 2 `require_once` uncommented

---

### [Stage 3] — 2026-02-22
**hc-containers Admin Meta Box** | `HCQB_VERSION` → `1.2.0`

**Added**
- [x] `admin/class-hcqb-metabox-container.php` — Tabbed meta box on the `hc-containers` edit screen; Tab 1 (Product Info): short description, base price, star rating, review count, gallery repeater, features repeater (icon + label), `wp_editor` product description, additional notes, plan document file picker, shipping details link; Tab 2 (Lease Info): enable lease toggle, lease price + label, `wp_editor` lease terms, standard layout title + `wp_editor` layout description, optional extras repeater (label + weekly price), enquiry button label; `save_post` handler with nonce, capability, autosave, and revision guards; full sanitisation (`absint`, `sanitize_text_field`, `sanitize_textarea_field`, `wp_kses_post`, `esc_url_raw`, `floatval`)
- [x] `admin/class-hcqb-admin-assets.php` — Conditional asset enqueue class; `enqueue_for_container()` guards on `post.php`/`post-new.php` + `hc-containers` post type; calls `wp_enqueue_media()`; commented stubs for Stage 4 config screen and Stage 10 submissions view
- [x] `assets/js/admin/hcqb-admin-container.js` — Tab switching; gallery (wp.media multi-select, drag reorder, remove); features repeater (add/remove/drag/icon picker via wp.media single-image); plan document picker (wp.media any file, remove); lease extras repeater (add/remove/drag); shared HTML5 drag-and-drop reorder utility

**Changed**
- [x] `assets/css/admin/hcqb-admin-global.css` — Metabox panel hiding switched from `display:none` to off-screen positioning so TinyMCE editors inside inactive panels can initialise on page load; added gallery grid, gallery item drag state, icon picker, file picker, extras unit label, and meta table spacing styles
- [x] `includes/class-hcqb-plugin.php` — Stage 3 hooks activated: `add_meta_boxes` → `HCQB_Metabox_Container::register`, `save_post` → `HCQB_Metabox_Container::save`, `admin_enqueue_scripts` → `HCQB_Admin_Assets::enqueue_for_container`; Stage 3 `require_once` statements uncommented

---

### [Stage 4] — 2026-02-22
**hc-quote-configs Admin Screen** | `HCQB_VERSION` → `1.3.0`

**Added**
- [x] `admin/class-hcqb-metabox-config.php` — Three meta boxes on the `hc-quote-configs` edit screen: **Config Info** (sidebar — status select + linked product dropdown with render-time and save-time 1:1 enforcement); **Questions Builder** (main — question/option repeaters rendered from saved meta, each with _uid hidden input for slug immutability; option fields: label, slug display, price + type, option role, affects-image checkbox); **Image Rules** (main — rows with match-tag multi-select built from live affects-image options, wp.media image picker, view dropdown); `save()` handler with full guards; `save_questions()` implementing _uid lookup — preserves existing key/slug, generates from label on first save only; `save_image_rules()`; `duplicate()` handler for `admin_action_hcqb_duplicate_config` — clones all hcqb_ meta, clears linked product, forces inactive; `post_row_actions()` filter adds "Duplicate" link; `get_image_tag_options()` and `get_taken_product_ids()` helpers
- [x] `assets/js/admin/hcqb-admin-repeater.js` — Exports `window.HCQBRepeater.initDrag(row)` — generic HTML5 drag-and-drop row reorder utility; prevents cross-list moves; applies `.hcqb-row--dragging` and `.hcqb-row--over` CSS states
- [x] `assets/js/admin/hcqb-admin-config.js` — Full config screen controller: question/option add/remove/drag via HCQBRepeater; slug auto-fill on label blur (never overwrites existing); assembly role enforcement (disables 'assembly' in other role selects when one holds it); conditional fields show/hide + `refreshConditionalDropdowns()` / `refreshConditionalOptions()` to populate selects from live DOM + `restoreConditionalValues()` from PHP-rendered hidden inputs; `rebuildImageTagSelects()` rebuilds match-tags selects from affects-image DOM state on every relevant change; image rule add/remove/drag; wp.media image picker for rule rows; `reindexAll()` called on form submit — normalises all question/option/rule indices to sequential DOM order before PHP receives them
- [x] `assets/css/admin/hcqb-admin-config.css` — Config screen layout: question card rows (header always visible, collapsible body); options repeater with compact field sizing; assembly role and affects-image label styles; image rule rows with three labelled sections (match tags, image picker, view); rule image preview and empty placeholder

**Changed**
- [x] `admin/class-hcqb-admin-assets.php` — `enqueue_for_config()` implemented: guards on `post.php`/`post-new.php` + `hc-quote-configs` post type; calls `wp_enqueue_media()`; enqueues `hcqb-admin-global.css`, `hcqb-admin-config.css`, `hcqb-admin-repeater.js`, `hcqb-admin-config.js` (config depends on repeater)
- [x] `includes/class-hcqb-plugin.php` — Stage 4 hooks activated: `add_meta_boxes` → `HCQB_Metabox_Config::register`, `save_post` → `HCQB_Metabox_Config::save`, `admin_action_hcqb_duplicate_config` → `HCQB_Metabox_Config::duplicate`, `admin_enqueue_scripts` → `HCQB_Admin_Assets::enqueue_for_config`, `post_row_actions` → `HCQB_Metabox_Config::post_row_actions`; Stage 4 `require_once` uncommented

---

### [Stage 5] — 2026-02-22
**Product Page Template** | `HCQB_VERSION` → `1.4.0`

**Added**
- [x] `templates/single-hc-containers.php` — Full singular template for `hc-containers`; determines view from `?view=` parameter (defaults to `product`; falls back to `product` if `?view=lease` and `hcqb_lease_enabled = 0`); **purchase view**: gallery with main image + thumbnail strip (click-to-switch via inline script), star rating, review count, short description, pricing block (shows separate flatpack / assembled rows when an assembly-role option exists, or a single "From" price otherwise), "Get a Custom Quote" button (hidden via `.hcqb-btn--hidden` CSS class when config is inactive), features list with optional icons, product description, additional notes, plan document download link, shipping details link; **lease view**: title with "— Lease" suffix, weekly price + label, lease terms, standard layout title + description, optional extras list, "Enquire Now" button; view-switcher nav rendered only when lease is enabled
- [x] `assets/css/frontend/hcqb-product-page.css` — Responsive product page styles (mobile-first, two-column layout at ≥768px): custom properties, view switcher tabs, two-column grid layout, gallery main image (4:3 aspect ratio) + thumbnail strip with active state, product title and "— Lease" tag, star rating (full/half/empty via colour), short description, purchase pricing (flatpack row + assembled row highlighted in accent colour), lease pricing (large price + label), buttons (quote + enquire + hidden modifier), features list with icons, lease terms/layout/extras, expanded content sections (description, additional notes, plan + shipping links)

**Changed**
- [x] `includes/class-hcqb-helpers.php` — Added `hcqb_render_stars( float $rating ): string` — returns 5 HTML `<span>` elements with BEM modifier classes `.hcqb-star--full`, `.hcqb-star--half`, `.hcqb-star--empty`; all spans are `aria-hidden="true"` (parent element carries the accessible label)
- [x] `includes/class-hcqb-plugin.php` — Stage 5 hooks activated: `template_include` → `override_container_template()`, `wp_enqueue_scripts` → `enqueue_product_page_assets()`; both methods uncommented/added as public instance methods; `enqueue_product_page_assets()` guards on `is_singular('hc-containers')` before enqueueing `hcqb-product-page.css`

---

### [Stage 5.1] — 2026-02-22
**Individual Field Shortcodes + Settings Instructions Tab**

**Added**
- [x] `includes/class-hcqb-shortcodes.php` — Registers 21 individual field shortcodes + 2 grid shortcode stubs (activated for Stage 6); all shortcodes accept an optional `post_id=""` attribute defaulting to `get_the_ID()`; shortcode groups: product info (`[hcqb_title]`, `[hcqb_short_desc]`, `[hcqb_price]`, `[hcqb_star_rating]`, `[hcqb_review_count]`, `[hcqb_gallery]`, `[hcqb_features]`, `[hcqb_product_desc]`, `[hcqb_additional_notes]`, `[hcqb_plan_document]`, `[hcqb_shipping_link]`), lease info (`[hcqb_lease_price]`, `[hcqb_lease_terms]`, `[hcqb_lease_layout_title]`, `[hcqb_lease_layout_desc]`, `[hcqb_lease_extras]`), quote (`[hcqb_quote_button]`, `[hcqb_assembled_price]`), grids (`[hc_product_grid]`, `[hc_lease_grid]`)

**Changed**
- [x] `includes/class-hcqb-settings.php` — Added fifth tab `Instructions` (read-only, rendered outside the `<form>` tag to avoid a confusing Save button); three `<details>/<summary>` accordion panels with no JS required: (1) Shortcodes reference table listing all 21 field shortcodes with meta key and output description, (2) PHP Snippets with pre-formatted dark code blocks for common helper usage, (3) General Shortcodes docs for `[hc_product_grid]` and `[hc_lease_grid]` with attribute reference; Save button conditionally hidden when the Instructions tab is active
- [x] `assets/css/admin/hcqb-admin-global.css` — Added accordion styles (`.hcqb-accordion`, `.hcqb-accordion__trigger` with rotating `▶` chevron via `::before` pseudo-element), instructions reference table (`.hcqb-instructions-table`, `.hcqb-instructions-divider`), and dark code block (`.hcqb-code-block` — `#1e1e2e` background, `#cdd6f4` text, monospace, `white-space: pre`)
- [x] `includes/class-hcqb-plugin.php` — Stage 5.1 `require_once` for `class-hcqb-shortcodes.php` uncommented; `add_action('init', [HCQB_Shortcodes::class, 'register'], 20)` hook activated

---

### [Stage 6] — 2026-02-22
**Product Grid + Lease Grid Shortcodes** | `HCQB_VERSION` → `1.5.0`

**Added**
- [x] `templates/product-card.php` — Shared card partial included inside the `WP_Query` loop by `render_grid()`; receives `$card_type` ('product' or 'lease') from the enclosing function scope; product card: base price, permalink, CTA "View Product"; lease card: lease price + label, `?view=lease` permalink, CTA from saved `hcqb_enquiry_button_label` (falls back to "View Lease"); image sourced from `hcqb_product_images[0]` → featured image → empty placeholder div; all card variables prefixed `$_` to prevent scope pollution
- [x] `assets/css/frontend/hcqb-grids.css` — Mobile-first grid and card styles: custom properties scoped to `.hcqb-grid`; layout: 1 col default → `repeat(2, 1fr)` at ≥480px → `repeat(var(--hcqb-grid-cols, 3), 1fr)` at ≥768px; card hover lift (`translateY(-2px)` + shadow depth); image hover scale (`scale(1.03)`); short description clamped to 2 lines via `-webkit-line-clamp`; full-width CTA button (`.hcqb-btn--card`)

**Changed**
- [x] `includes/class-hcqb-shortcodes.php` — `[hc_product_grid]` and `[hc_lease_grid]` stubs replaced with real implementation; shared `render_grid( array $atts, string $card_type )` private static method handles both shortcodes; supported attributes: `columns` (1–6, default 3), `limit` (1–100, default 12), `category` (taxonomy slug), `orderby` (whitelist: `date`, `title`, `price`; `price` maps to `meta_value_num` + `meta_key = hcqb_product_price`), `order` (`ASC`/`DESC`); `[hc_lease_grid]` adds `meta_query` for `hcqb_lease_enabled = 1`; column count injected as inline CSS custom property `--hcqb-grid-cols`; all output via `ob_start()/ob_get_clean()`
- [x] `includes/class-hcqb-plugin.php` — `enqueue_grid_assets()` public method added; uses `has_shortcode()` to conditionally load `hcqb-grids.css` only on pages containing either grid shortcode; `add_action('wp_enqueue_scripts', [$this, 'enqueue_grid_assets'])` hook activated
- [x] `hc-quote-builder.php` — `HCQB_VERSION` bumped `1.4.0` → `1.5.0`

---

### [Stage 7] — 2026-02-22
**Quote Builder — Frame 1** | `HCQB_VERSION` → `1.6.0`

**Added**
- [x] `templates/quote-builder/frame-1-questions.php` — Left panel: product selector (locked name + "Change" button → confirmation → hidden `<select>` → URL update + reload); questions list loop rendering each question as a `.hcqb-question` wrapper with `data-question-key`; conditional questions rendered with `data-conditional="true"`, `data-show-when-question`, `data-show-when-option`, `aria-hidden="true"`, `style="display:none"` so JS manages visibility; includes correct question partial based on `input_type`
- [x] `templates/quote-builder/frame-1-preview.php` — Right panel (sticky on desktop): view toggle buttons (Front/Back/Side/Interior with `data-view` + `aria-pressed`); product preview `<img id="hcqb-preview-img">` initialised to first gallery image; feature pill slots (up to 4, one per `show_in_pill` question) with `data-question-key`; live price (`.hcqb-live-price`) initialised to base price; step indicator + disabled "Continue" CTA (wired in Stage 8)
- [x] `templates/quote-builder/partials/question-radio.php` — Radio input group; each `<label>` wraps `<input type="radio">` with `data-price`, `data-price-type`, `data-affects-image`; price delta shown when non-zero
- [x] `templates/quote-builder/partials/question-dropdown.php` — `<select>` with blank default option; price annotation appended to option text; `data-price`, `data-price-type`, `data-affects-image` on each `<option>`
- [x] `templates/quote-builder/partials/question-checkbox.php` — Checkbox input group; same data-attribute pattern as radio; native multi-select
- [x] `assets/js/frontend/hcqb-pricing.js` — `window.HCQBPricing`; sums price deltas from all visible checked inputs + selected dropdowns; updates `HCQBState.currentPrice` and `.hcqb-live-price` formatted as `$X,XXX.XX` via `toLocaleString('en-AU')`
- [x] `assets/js/frontend/hcqb-image-switcher.js` — `window.HCQBImageSwitcher`; listens to `hcqb:selection-changed` + `hcqb:view-changed`; rebuilds `HCQBState.activeTags` from visible `data-affects-image="1"` inputs; filters rules by current view; `findMatchingRule()` — full match wins (pre-sorted by PHP ensures most-specific-first); falls back to `HCQBConfig.defaultImageUrl`; updates `<img#hcqb-preview-img>.src`
- [x] `assets/js/frontend/hcqb-conditionals.js` — `window.HCQBConditionals`; evaluates every `[data-conditional="true"]` wrapper on `hcqb:selection-changed`; checks trigger via `:checked` selector and select `.value`; reveals by removing `aria-hidden`; hides + resets all inputs/selects; re-dispatches after any state change; loop-safe (re-reads `aria-hidden` from DOM each run)
- [x] `assets/js/frontend/hcqb-feature-pills.js` — `window.HCQBFeaturePills`; updates `.hcqb-pill__value` for each pill from the selected option label of its question (radio/checkbox: clone label, strip price span; select: strip price annotation via regex); toggles `.hcqb-pill--active`
- [x] `assets/css/frontend/hcqb-quote-builder.css` — Full layout + component styles; mobile-first single column → two-column (1fr | 360px) at ≥900px; preview panel sticky on desktop; error card; product selector; question cards with conditional accent + reveal animation; radio/checkbox option cards with `:has(:checked)` highlight; dropdown; view toggle tab bar; 4:3 preview image; feature pills; live price; button variants (primary, secondary, ghost, full-width, hidden)

**Changed**
- [x] `includes/class-hcqb-shortcodes.php` — `[hc_quote_builder]` registered; `sc_quote_builder()` validates `?product=` via `absint()`, returns styled error card for four error states; `render_builder_frame_1()` gathers data, outputs `window.HCQBConfig` JSON inline script, includes frame templates; `render_builder_error()` produces context-appropriate error card with back link
- [x] `includes/class-hcqb-plugin.php` — `enqueue_quote_builder_assets()` added; guards with `has_shortcode()`; enqueues CSS + 5 JS modules in footer with dependency chain; Stage 7 hook activated
- [x] `hc-quote-builder.php` — `HCQB_VERSION` bumped `1.5.0` → `1.6.0`

---

### [Stage 8] — Pending
**Frame 2 Contact Form + Google APIs**
- [ ] `templates/quote-builder/frame-2-contact.php`
- [ ] `assets/js/frontend/hcqb-google-maps.js`
- [ ] `assets/js/frontend/hcqb-form-submit.js`

---

### [Stage 9] — Pending
**Submission Processing + Emails**
- [ ] `includes/class-hcqb-ajax.php`
- [ ] `includes/class-hcqb-submission.php`
- [ ] `includes/class-hcqb-email.php`
- [ ] `templates/emails/admin-notification.php`
- [ ] `templates/emails/customer-copy.php`

---

### [Stage 10] — Pending
**Submissions Admin View**
- [ ] `admin/class-hcqb-list-table-submissions.php`
- [ ] `admin/class-hcqb-metabox-submission.php`
- [ ] `assets/js/admin/hcqb-admin-submissions.js`
- [ ] `assets/css/admin/hcqb-admin-submissions.css`

---

### [Stage 11] — Pending
**QA + Edge Case Testing**
- [ ] All acceptance criteria from Stages 1–10 verified
- [ ] All 30+ edge case scenarios from DEVELOPMENT.md §Stage 11 tested and passed
- [ ] Plugin ready for client handoff

---

_This changelog is updated at the completion of each stage and whenever architectural or document changes are made. For full implementation detail at each stage, refer to `DEVELOPMENT.md`. For product specification, refer to `ARCHITECTURE.md`._
