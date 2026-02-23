# HC Quote Builder — Development Document

**Plugin Name:** HC Quote Builder
**Code Prefix:** `hcqb_`
**Version:** 1.0
**Architecture Reference:** See `ARCHITECTURE.md` (v1.2) for full product specification
**Status:** In Development

> **How to use this document:** Work through stages in order. Each stage has an objective, a complete file list, implementation tasks, critical code patterns, and acceptance criteria. Do not start a stage until all its dependencies are marked complete. Update the status tracker below as you progress.

---

## Quick Reference

| Item | Value |
| ---- | ----- |
| Meta / option prefix | `hcqb_` |
| PHP minimum | 8.x |
| WordPress APIs | Native only — no ACF, no third-party field plugins |
| JavaScript | Vanilla ES6+ — no jQuery, no build toolchain |
| Database | Post meta only — no custom tables |
| Asset versioning | `HCQB_VERSION` constant throughout |
| Slug generation | `sanitize_title()` — generated on **first save only**, then immutable |
| AJAX security | Nonce verified **before** any processing on every endpoint |
| Shortcode output | Always `ob_start()` / `ob_get_clean()` — never direct `echo` |

---

## Stage Status Tracker

| # | Stage | Status |
| - | ----- | ------ |
| 1 | Plugin Bootstrap + CPT Registration | ✅ Complete |
| 2 | Plugin Settings Page | ✅ Complete |
| 3 | hc-containers Admin Meta Box | ✅ Complete |
| 4 | hc-quote-configs Admin Screen | ✅ Complete |
| 5 | Product Page Template | ✅ Complete |
| 6 | Product Grid + Lease Grid Shortcodes | 🔲 Not Started |
| 7 | Quote Builder — Frame 1 | 🔲 Not Started |
| 8 | Frame 2 Contact Form + Google APIs | 🔲 Not Started |
| 9 | Submission Processing + Emails | 🔲 Not Started |
| 10 | Submissions Admin View | 🔲 Not Started |
| 11 | QA + Edge Case Testing | 🔲 Not Started |

> Update status to `🔄 In Progress` when starting a stage. Update to `✅ Complete` only when all acceptance criteria are verified.

---

## Development Principles

These rules apply to every line of code written for this plugin. No exceptions.

- **Native WordPress APIs only.** No ACF, no jQuery, no page builder dependencies.
- **All meta keys prefixed `hcqb_`.** No bare key names in `get_post_meta()` / `update_post_meta()`.
- **Slugs and keys are immutable after first save.** Generated from label using `sanitize_title()` on first save only. PHP save handler must detect and preserve existing values.
- **All user input sanitised server-side.** `sanitize_text_field()` for text, `sanitize_email()` for email, `absint()` for integers, `esc_url_raw()` for URLs, `floatval()` for prices. Never pass `$_POST` data raw to any WP function.
- **All AJAX endpoints verify a nonce first.** Reject with `wp_die()` or `wp_send_json_error()` before any other processing if nonce fails.
- **All shortcode output via `ob_start()` / `ob_get_clean()`.** Never `echo` directly from a shortcode callback.
- **No custom database tables.** All data stored as post meta or options.
- **Asset versioning via `HCQB_VERSION`.** Pass as the `$ver` argument to every `wp_enqueue_style()` and `wp_enqueue_script()` call.
- **Admin assets loaded conditionally.** Enqueue admin scripts/styles only on the relevant screen — never globally across all admin pages.

---

## Plugin File / Folder Structure

```
hc-quote-builder/
│
├── hc-quote-builder.php                        ← Main plugin bootstrap file
├── uninstall.php                               ← Cleanup on plugin deletion
│
├── includes/                                   ← Core server-side logic
│   ├── class-hcqb-plugin.php                   ← Singleton loader + hook registration
│   ├── class-hcqb-post-types.php               ← CPT and taxonomy registration
│   ├── class-hcqb-settings.php                 ← Settings page: tabs, save, retrieve
│   ├── class-hcqb-ajax.php                     ← wp_ajax_ handler registration
│   ├── class-hcqb-submission.php               ← Form processing: validate, sanitise, save
│   ├── class-hcqb-email.php                    ← Email assembly and dispatch
│   ├── class-hcqb-shortcodes.php               ← All shortcode registrations + callbacks
│   └── class-hcqb-helpers.php                  ← Shared utilities
│
├── admin/                                      ← Admin-only UI (loaded on is_admin() only)
│   ├── class-hcqb-metabox-container.php        ← hc-containers tabbed meta box
│   ├── class-hcqb-metabox-config.php           ← hc-quote-configs edit screen (4 sections)
│   ├── class-hcqb-metabox-submission.php       ← Submission detail view + status editor
│   ├── class-hcqb-list-table-submissions.php   ← Custom WP_List_Table for submissions
│   └── class-hcqb-admin-assets.php             ← Admin asset enqueue (conditional per screen)
│
├── templates/
│   ├── single-hc-containers.php                ← Product page (purchase + lease views)
│   ├── quote-builder/
│   │   ├── frame-1-questions.php               ← Left panel: product selector + questions
│   │   ├── frame-1-preview.php                 ← Right panel: image, pills, live price
│   │   ├── frame-2-contact.php                 ← Contact form fields
│   │   └── partials/
│   │       ├── question-radio.php
│   │       ├── question-dropdown.php
│   │       └── question-checkbox.php
│   ├── shortcodes/
│   │   ├── product-grid.php
│   │   ├── lease-grid.php
│   │   └── product-card.php                    ← Shared card partial (both grids)
│   └── emails/
│       ├── admin-notification.php
│       └── customer-copy.php
│
└── assets/
    ├── css/
    │   ├── admin/
    │   │   ├── hcqb-admin-global.css           ← Tabs, repeaters, toggles (all admin screens)
    │   │   ├── hcqb-admin-config.css           ← Config screen specific styles
    │   │   └── hcqb-admin-submissions.css      ← Submissions list + detail view
    │   └── frontend/
    │       ├── hcqb-quote-builder.css          ← Quote builder page (Frame 1 + Frame 2)
    │       ├── hcqb-product-page.css           ← single-hc-containers.php styles
    │       └── hcqb-grids.css                  ← Product grid + lease grid shortcodes
    │
    └── js/
        ├── admin/
        │   ├── hcqb-admin-repeater.js          ← Generic drag-and-drop repeater (reused across admin)
        │   ├── hcqb-admin-config.js            ← Config screen: slug gen, image tags, role enforcement
        │   ├── hcqb-admin-container.js         ← Container screen: tabs, media uploader, gallery
        │   ├── hcqb-admin-settings.js          ← Settings: tab switching, Places for warehouse
        │   └── hcqb-admin-submissions.js       ← Submission detail: status AJAX save
        └── frontend/
            ├── hcqb-quote-builder.js           ← Orchestrator: HCQBState, event bus init
            ├── hcqb-pricing.js                 ← Live price calculation engine
            ├── hcqb-image-switcher.js          ← Image rule matching + front/back toggle
            ├── hcqb-conditionals.js            ← Conditional question reveal/hide
            ├── hcqb-feature-pills.js           ← Feature pill label updates
            ├── hcqb-google-maps.js             ← Places autocomplete, Distance Matrix, Maps Embed
            └── hcqb-form-submit.js             ← Client-side validation + AJAX submission
```

---

## Stage 1 — Plugin Bootstrap + CPT Registration

**Status:** ✅ Complete — 2026-02-22
**Depends on:** Nothing — start here
**Architecture ref:** §2 Technology Stack, §3 Custom Post Types

### Objective

Establish the plugin's foundation: the main entry file, singleton loader class, all three custom post types, the product taxonomy, and the shared helpers class. Nothing visible to end users at this stage — just a working plugin scaffold that activates cleanly and registers all post types.

### Files to Create

```
hc-quote-builder.php
uninstall.php
includes/class-hcqb-plugin.php
includes/class-hcqb-post-types.php
includes/class-hcqb-helpers.php
```

### Implementation Tasks

- [ ] **`hc-quote-builder.php`** — Define constants (`HCQB_VERSION`, `HCQB_PLUGIN_DIR`, `HCQB_PLUGIN_URL`). Require the plugin class. Call `HCQB_Plugin::get_instance()`. Register activation hook calling `flush_rewrite_rules()`.
- [ ] **`class-hcqb-plugin.php`** — Singleton. On `init` at priority 10: call post types registration. On `is_admin()`: load admin classes. Centralise all `add_action()` / `add_filter()` calls.
- [ ] **`class-hcqb-post-types.php`** — Register `hc-containers` (public, rewrite slug `portables`), `hc-quote-configs` (private, show in menu), `hc-quote-submissions` (private, show in menu). Register `hc-container-category` taxonomy attached to `hc-containers`.
- [ ] **`class-hcqb-helpers.php`** — Implement the four shared utility functions (see Critical Patterns below).
- [ ] **`uninstall.php`** — Delete `hcqb_settings` option and all post meta for the three CPTs on uninstall.

### Critical Patterns

**Activation hook — correct placement:**
```php
// In hc-quote-builder.php
register_activation_hook( __FILE__, function() {
    HCQB_Post_Types::register(); // register CPTs directly
    flush_rewrite_rules();
});
// Never call flush_rewrite_rules() on the init hook
```

**Helpers class functions:**
```php
// Retrieve a setting value (with optional default)
function hcqb_get_setting( string $key, $default = '' ) {
    $settings = get_option( 'hcqb_settings', [] );
    return $settings[ $key ] ?? $default;
}

// Generate a slug from a string (for first-save-only slug creation)
function hcqb_generate_slug( string $string ): string {
    return sanitize_title( $string );
}

// Format a price for display
function hcqb_format_price( float $price ): string {
    return '$' . number_format( $price, 2 );
}

// Get the active quote config for a given product ID (returns post or null)
function hcqb_get_active_config_for_product( int $product_id ): ?WP_Post {
    $configs = get_posts([
        'post_type'   => 'hc-quote-configs',
        'numberposts' => 1,
        'meta_query'  => [
            [ 'key' => 'hcqb_linked_product', 'value' => $product_id ],
            [ 'key' => 'hcqb_config_status',  'value' => 'active'    ],
        ],
    ]);
    return $configs[0] ?? null;
}
```

### Acceptance Criteria

- [ ] Plugin activates and deactivates without PHP errors
- [ ] All three CPTs appear in WordPress admin sidebar
- [ ] `hc-container-category` taxonomy appears on the `hc-containers` edit screen
- [ ] `hc-containers` single post URLs use the `/portables/` slug
- [ ] `hcqb_generate_slug('Do you require Air Conditioning?')` returns `do-you-require-air-conditioning`

---

## Stage 2 — Plugin Settings Page

**Status:** ✅ Complete — 2026-02-22
**Depends on:** Stage 1
**Architecture ref:** §4 Plugin Settings Page, §15.6 Plugin Settings Structure

### Objective

Build the Settings → HC Quote Builder page with all four tabs. Implement save and retrieve. This stage establishes the settings store that feeds the API key, warehouse coordinates, email configuration, prefix options, and status labels to every downstream stage.

### Files to Create

```
includes/class-hcqb-settings.php
assets/js/admin/hcqb-admin-settings.js
assets/css/admin/hcqb-admin-global.css
```

### Implementation Tasks

- [ ] Register settings page under `Settings` menu via `admin_menu`
- [ ] Register `hcqb_settings` option via `register_setting()` with a single sanitisation callback covering all four tabs
- [ ] Build Tab 1 — General: Warehouse Address (text + Places autocomplete), Warehouse Lat/Lng (read-only, auto-filled), Google Maps API Key (password-masked input), Supported Countries (multi-select checkboxes)
- [ ] Build Tab 2 — Email: all email configuration fields
- [ ] Build Tab 3 — Quote Builder: page selector dropdown, alert text, submit button label, consent text, privacy fine print
- [ ] Build Tab 4 — Form Options: Prefix options repeater (text rows, add/remove/reorder), Submission status labels repeater (key + label rows — see Critical Patterns)
- [ ] Populate default values on first save (prefix options: Mr/Mrs/Ms/Dr; status labels: New/Contacted/Closed)
- [ ] JS tab switching in `hcqb-admin-settings.js` (no jQuery)
- [ ] Wire Google Places Autocomplete to warehouse address field; on place selection, auto-fill lat/lng fields

### Critical Patterns

**Status labels — stable key generation:**

Each status row has a hidden `key` input and a visible `label` input. The key is generated ONCE when the row is first created by JS and stored in the hidden input. On subsequent saves, the existing key is passed through unchanged. The PHP sanitisation callback reads the key from the submitted hidden input — it never generates or modifies keys.

```js
// In hcqb-admin-settings.js — when admin clicks "Add Status"
function addStatusRow() {
    const key   = 'status_' + Date.now(); // generated once, never regenerated
    const row   = document.createElement('div');
    row.innerHTML = `
        <input type="hidden" name="hcqb_settings[submission_status_labels][][key]" value="${key}">
        <input type="text"   name="hcqb_settings[submission_status_labels][][label]" value="">
        <button type="button" class="hcqb-remove-row">Remove</button>
    `;
    document.querySelector('.hcqb-status-list').appendChild(row);
}
```

```php
// In sanitisation callback — keys come from submitted data, never regenerated
foreach ( $raw['submission_status_labels'] ?? [] as $row ) {
    $key   = sanitize_key( $row['key'] );   // preserve the submitted key
    $label = sanitize_text_field( $row['label'] );
    if ( $key && $label ) {
        $clean['submission_status_labels'][] = compact( 'key', 'label' );
    }
}
```

**API key — never in frontend HTML:**
```php
// Correct — pass via wp_localize_script only
wp_localize_script( 'hcqb-quote-builder', 'HCQBLocale', [
    'apiKey' => hcqb_get_setting( 'google_maps_api_key' ),
    // ...
]);

// Wrong — never do this in a template
// echo '<script src="...key=' . $api_key . '..."></script>';
```

**Chicken-and-egg notice for warehouse autocomplete:**
```php
if ( empty( hcqb_get_setting( 'google_maps_api_key' ) ) ) {
    echo '<p class="description">Save a Google Maps API Key first to enable address autocomplete.</p>';
}
```

### Acceptance Criteria

- [ ] All four tabs save and retrieve correctly
- [ ] Saving Tab 1 stores warehouse lat/lng when address is selected via autocomplete
- [ ] Status label keys survive re-saves unchanged when labels are renamed
- [ ] API key field renders as password-masked input
- [ ] Adding and removing prefix options and status label rows works
- [ ] Drag-and-drop reorder on both repeater types works

---

## Stage 3 — hc-containers Admin Meta Box

**Status:** ✅ Complete — 2026-02-22
**Depends on:** Stage 1
**Architecture ref:** §5.1 hc-containers Admin Edit Screen

### Objective

Build the tabbed meta box on the `hc-containers` post edit screen. Tab 1: Product Info (gallery, features repeater, all product fields). Tab 2: Lease Info (lease pricing, extras repeater, layout fields). This establishes the product data structure that the product page template (Stage 5), shortcodes (Stage 6), and quote builder (Stage 7) all read from.

### Files to Create

```
admin/class-hcqb-metabox-container.php
admin/class-hcqb-admin-assets.php
assets/js/admin/hcqb-admin-container.js
assets/css/admin/hcqb-admin-global.css   (expand as needed)
```

### Implementation Tasks

- [ ] Register meta box via `add_meta_boxes` action, only on `hc-containers` screen
- [ ] Build tab switching UI (Tab 1: Product Info, Tab 2: Lease Info) — CSS + JS class toggle on `data-tab` attributes
- [ ] **Tab 1 fields:** Short Description, Product Price, Star Rating, Review Count, Product Images (gallery repeater), Features (repeater: icon upload + label), Product Description (wp_editor), Additional Notes, Plan Document (file upload), Shipping Details Link
- [ ] **Tab 2 fields:** Enable Lease (toggle), Lease Price, Lease Price Label, Lease Terms (wp_editor), Standard Layout Title, Standard Layout Description (wp_editor), Lease Optional Extras (repeater: label + weekly price), Enquiry Button Label
- [ ] Save handler via `save_post` — verify nonce, check `current_user_can('edit_post')`, sanitise and save all meta
- [ ] Call `wp_enqueue_media()` on the `hc-containers` edit screen for the media uploader
- [ ] Load admin assets conditionally (only on `hc-containers` edit screen) via `class-hcqb-admin-assets.php`

### Critical Patterns

**Conditional asset loading:**
```php
// In class-hcqb-admin-assets.php
public function enqueue_for_container( string $hook ): void {
    if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) return;
    if ( 'hc-containers' !== get_post_type() ) return;

    wp_enqueue_media(); // required for wp.media gallery/icon uploader
    wp_enqueue_script( 'hcqb-admin-container', HCQB_PLUGIN_URL . 'assets/js/admin/hcqb-admin-container.js', [], HCQB_VERSION, true );
    wp_enqueue_style( 'hcqb-admin-global', HCQB_PLUGIN_URL . 'assets/css/admin/hcqb-admin-global.css', [], HCQB_VERSION );
}
```

**Gallery repeater — save:**
```php
// Always sanitise attachment IDs as integers before saving
$images = array_map( 'absint', $_POST['hcqb_product_images'] ?? [] );
$images = array_filter( $images ); // remove any zeros
update_post_meta( $post_id, 'hcqb_product_images', $images );
```

**Features repeater — save:**
```php
$features = [];
foreach ( $_POST['hcqb_features'] ?? [] as $row ) {
    $icon_id = absint( $row['icon_id'] ?? 0 );
    $label   = sanitize_text_field( $row['label'] ?? '' );
    if ( $label ) {
        $features[] = [ 'icon_id' => $icon_id, 'label' => $label ];
    }
}
update_post_meta( $post_id, 'hcqb_features', $features );
```

### Acceptance Criteria

- [ ] Both tabs render and switch correctly
- [ ] All Tab 1 fields save and repopulate on reload
- [ ] All Tab 2 fields save and repopulate on reload
- [ ] Gallery repeater: images can be added, removed, and reordered; IDs saved as integer array
- [ ] Features repeater: rows can be added, removed, reordered; icon upload opens WP media library
- [ ] Lease extras repeater: rows can be added, removed, reordered
- [ ] Admin assets do NOT load on other admin screens (verify in network tab)

---

## Stage 4 — hc-quote-configs Admin Screen

**Status:** 🔲 Not Started
**Depends on:** Stages 1, 2, 3
**Architecture ref:** §5.2 hc-quote-configs Admin Edit Screen

### Objective

Build the most complex admin screen in the plugin. Four sections: (1) Config Info with 1:1 product linking, (2) Questions Builder with drag-and-drop, nested options, slug immutability via `_uid`, conditional dropdowns, feature pill toggle, (3) Image Rules with match tag multi-select and tie-break ordering, (4) Duplication. This stage must be fully correct before the frontend quote builder can be built.

### Files to Create

```
admin/class-hcqb-metabox-config.php
assets/js/admin/hcqb-admin-repeater.js
assets/js/admin/hcqb-admin-config.js
assets/css/admin/hcqb-admin-config.css
```

### Implementation Tasks

- [ ] **Section 1 — Config Info:** Title (native WP post title), Linked Product dropdown, Status (Active/Inactive select)
- [ ] **Linked Product dropdown:** Query all `hc-containers` posts; mark as `disabled` any already linked to another active config (excluding current); enforce 1:1 at save time with a meta query check
- [ ] **Section 2 — Questions Builder:** Drag-and-drop repeater; each question row has all Question Fields; nested Options repeater inside each question with all Option Fields
- [ ] **Slug/key immutability via `_uid`:** See Critical Patterns — this is the most important implementation detail in the entire plugin
- [ ] **Conditional dropdowns:** "Show When — Question" populates from other questions in the config; "Show When — Option" populates from the selected trigger question's options; both update dynamically via JS
- [ ] **Assembly role enforcement:** When one option's role select is set to `assembly`, JS disables (not removes) the `assembly` value from all other options' role selects
- [ ] **Section 3 — Image Rules:** Drag-and-drop repeater; match tags multi-select populated from image-affecting options (slugs stored, labels displayed); front/back image upload per rule; default fallback images outside repeater
- [ ] **Match tag display:** Multi-select always regenerated from current questions data on page load — never from cached option values
- [ ] **Section 4 — Duplication:** `admin_action_hcqb_duplicate_config` handler; copies all meta; clears linked product; redirects to new post edit screen
- [ ] Add "Duplicate" action link to the `hc-quote-configs` list table rows

### Critical Patterns

**`_uid` pattern — slug immutability:**

This is the mechanism that makes slugs immutable. Every question and option row gets a `_uid` (unique identifier) when it is first created by JavaScript. The `_uid` is stored in a hidden input and submitted with the form. On re-save, PHP uses the `_uid` to find the previously saved row and preserve its existing slug/key — it never generates a new slug for a row that already has one.

```js
// In hcqb-admin-config.js — when a new question row is added
function addQuestionRow() {
    const uid = 'q_' + Date.now() + '_' + Math.random().toString(36).substr(2, 5);
    // uid stored in hidden input, never changes for this row's lifetime
    const row = buildQuestionRowHTML( uid );
    questionsContainer.appendChild( row );
}

// Slug generation — fires on question label blur, only if slug field is empty
questionLabel.addEventListener( 'blur', function() {
    if ( slugField.value === '' ) {
        slugField.value = slugify( this.value ); // slugify = s => s.toLowerCase().replace(/[^a-z0-9]+/g, '_')
    }
    // If slug already has a value: do nothing. It is locked.
});
```

```php
// In class-hcqb-metabox-config.php save handler
private function save_questions( int $post_id, array $incoming ): void {
    // Load existing saved questions (keyed by _uid)
    $existing = get_post_meta( $post_id, 'hcqb_questions', true ) ?: [];
    $existing_by_uid = [];
    foreach ( $existing as $q ) {
        $existing_by_uid[ $q['_uid'] ] = $q;
    }

    $clean = [];
    foreach ( $incoming as $q ) {
        $uid = sanitize_key( $q['_uid'] ?? '' );

        // Preserve existing slug if this row was previously saved
        $existing_key = $existing_by_uid[ $uid ]['key'] ?? '';
        $incoming_key = hcqb_generate_slug( $q['label'] ?? '' );
        $final_key    = $existing_key ?: $incoming_key; // existing wins

        $clean[] = [
            '_uid'               => $uid,
            'label'              => sanitize_text_field( $q['label'] ?? '' ),
            'key'                => $final_key,
            'input_type'         => in_array( $q['input_type'] ?? '', ['radio','dropdown','checkbox'] ) ? $q['input_type'] : 'radio',
            'required'           => (bool) ( $q['required'] ?? false ),
            'helper_text'        => sanitize_text_field( $q['helper_text'] ?? '' ),
            'show_in_pill'       => (bool) ( $q['show_in_pill'] ?? false ),
            'is_conditional'     => (bool) ( $q['is_conditional'] ?? false ),
            'show_when_question' => sanitize_key( $q['show_when_question'] ?? '' ),
            'show_when_option'   => sanitize_key( $q['show_when_option'] ?? '' ),
            'options'            => $this->save_options( $q['options'] ?? [], $existing_by_uid[ $uid ]['options'] ?? [] ),
        ];
    }

    update_post_meta( $post_id, 'hcqb_questions', $clean );
}
```

The same `_uid` pattern applies to options nested inside each question.

**Image tag multi-select — always regenerated:**
```php
// In the Image Rules render, always build match tag options from live questions
$questions = get_post_meta( $post_id, 'hcqb_questions', true ) ?: [];
$image_tags = []; // slug => label

foreach ( $questions as $q ) {
    foreach ( $q['options'] as $opt ) {
        if ( ! empty( $opt['affects_image'] ) ) {
            $image_tags[ $opt['slug'] ] = $opt['label']; // always human-readable
        }
    }
}
// $image_tags is used to render the multi-select options
// Stored match_tags in existing rules are slugs; display is resolved here at render time
```

**1:1 enforcement at save time:**
```php
private function check_linked_product_available( int $product_id, int $current_config_id ): bool {
    $existing = get_posts([
        'post_type'      => 'hc-quote-configs',
        'numberposts'    => 1,
        'exclude'        => [ $current_config_id ],
        'meta_query'     => [[ 'key' => 'hcqb_linked_product', 'value' => $product_id ]],
    ]);
    return empty( $existing );
}
```

### Acceptance Criteria

- [ ] Questions can be added, reordered (drag-and-drop), and removed
- [ ] Options can be added, reordered, and removed within each question
- [ ] Renaming a question label does NOT change its `key` after first save
- [ ] Renaming an option label does NOT change its `slug` after first save
- [ ] Slug field renders as read-only after first save with "Slug is locked" note
- [ ] Assembly role: selecting `assembly` on one option disables it on all others
- [ ] Only one option per config can hold the `assembly` role
- [ ] Conditional "Show When — Option" dropdown updates when trigger question changes
- [ ] Image rules match tags multi-select shows human-readable option labels
- [ ] Renaming an option label updates its label in Image Rules on next page load
- [ ] Duplication creates new post, copies all meta, clears linked product
- [ ] Linking a product already linked to another config is blocked at save time
- [ ] Default fallback front/back images save and repopulate

---

## Stage 5 — Product Page Template

**Status:** 🔲 Not Started
**Depends on:** Stages 1, 3, 4
**Architecture ref:** §10 Product Page Logic, §11.4 Assembled Price

### Objective

Build the `single-hc-containers.php` template that handles both purchase and lease views via the `?view=` URL parameter. The assembled price on the purchase view is the first real integration point between the container CPT and the quote config CPT.

### Files to Create

```
templates/single-hc-containers.php
assets/css/frontend/hcqb-product-page.css
```

### Implementation Tasks

- [ ] Hook template via `template_include` filter: `is_singular('hc-containers')` → return plugin template path
- [ ] Read `?view=` parameter; default to `product`; fall back to `product` if `?view=lease` and lease is disabled
- [ ] **Purchase view:** product image gallery, star rating, review count, flatpack price, assembled price (see Critical Patterns), description, features list, additional notes, plan document link, shipping details link, "Get a Custom Quote" button
- [ ] **Lease view:** product title with "– LEASE" appended, weekly price + label, lease terms, standard layout title + description, optional extras list, "Enquire Now" button
- [ ] "Get a Custom Quote" button: link to `{quote_builder_page_url}?product={$post_id}`; add CSS class `hcqb-inactive` (which sets `display:none`) if linked config status is `inactive`
- [ ] Enqueue `hcqb-product-page.css` on `hc-containers` singular templates only

### Critical Patterns

**Template override via filter:**
```php
// In class-hcqb-plugin.php
add_filter( 'template_include', [ $this, 'override_container_template' ] );

public function override_container_template( string $template ): string {
    if ( is_singular( 'hc-containers' ) ) {
        return HCQB_PLUGIN_DIR . 'templates/single-hc-containers.php';
    }
    return $template;
}
```

**Assembled price calculation:**
```php
// In single-hc-containers.php (purchase view)
$base_price     = (float) get_post_meta( get_the_ID(), 'hcqb_product_price', true );
$config         = hcqb_get_active_config_for_product( get_the_ID() );
$assembled_price = null;

if ( $config ) {
    $questions = get_post_meta( $config->ID, 'hcqb_questions', true ) ?: [];
    foreach ( $questions as $q ) {
        foreach ( $q['options'] as $opt ) {
            if ( ( $opt['option_role'] ?? '' ) === 'assembly' ) {
                $price_delta     = (float) $opt['price'];
                $assembled_price = ( $opt['price_type'] === 'deduction' )
                    ? $base_price - $price_delta
                    : $base_price + $price_delta;
                break 2;
            }
        }
    }
}
// If $assembled_price is null, assembled price section is not displayed
```

**Quote button hidden when config inactive — CSS only:**
```php
$config          = hcqb_get_active_config_for_product( get_the_ID() );
$button_class    = $config ? '' : 'hcqb-btn--hidden';
$quote_page_url  = get_permalink( hcqb_get_setting( 'quote_builder_page_id' ) );
?>
<a href="<?php echo esc_url( $quote_page_url . '?product=' . get_the_ID() ); ?>"
   class="hcqb-btn hcqb-btn--quote <?php echo esc_attr( $button_class ); ?>">
    Get a Custom Quote ↗
</a>
```
```css
/* In hcqb-product-page.css */
.hcqb-btn--hidden { display: none; }
```

### Acceptance Criteria

- [ ] `?view=product` (or no parameter) renders purchase view correctly
- [ ] `?view=lease` renders lease view correctly
- [ ] `?view=lease` on a product with lease disabled falls back to purchase view
- [ ] Assembled price displays when a config with an `assembly` role option exists
- [ ] Assembled price does not display when no `assembly` role option exists in the config
- [ ] "Get a Custom Quote" button links to correct URL with `?product={ID}`
- [ ] "Get a Custom Quote" button is hidden (CSS) when linked config is inactive
- [ ] All product meta fields render correctly in both views

---

## Stage 6 — Product Grid + Lease Grid Shortcodes

**Status:** 🔲 Not Started
**Depends on:** Stages 1, 3
**Architecture ref:** §13 Shortcodes

### Objective

Implement the `[hc_product_grid]` and `[hc_lease_grid]` shortcodes with all supported attributes. This gives the client working landing pages and establishes the `class-hcqb-shortcodes.php` file that will also register `[hc_quote_builder]` in Stage 7.

### Files to Create

```
includes/class-hcqb-shortcodes.php
templates/shortcodes/product-grid.php
templates/shortcodes/lease-grid.php
templates/shortcodes/product-card.php
assets/css/frontend/hcqb-grids.css
```

### Implementation Tasks

- [ ] Register both shortcodes in `class-hcqb-shortcodes.php` via `add_shortcode()` on `init`
- [ ] `[hc_product_grid]`: supports `columns`, `limit`, `category`, `orderby`, `order`; queries all `hc-containers` posts; each card links to `?view=product`
- [ ] `[hc_lease_grid]`: same attributes; queries only containers where `hcqb_lease_enabled = 1`; each card links to `?view=lease`
- [ ] Shared `product-card.php` partial used by both grids
- [ ] Enqueue `hcqb-grids.css` only when a grid shortcode is present on the page

### Critical Patterns

**Shortcode output — always via output buffer:**
```php
public function render_product_grid( array $atts ): string {
    $atts = shortcode_atts([
        'columns'  => 3,
        'limit'    => -1,
        'category' => '',
        'orderby'  => 'date',
        'order'    => 'DESC',
    ], $atts, 'hc_product_grid' );

    $query_args = $this->build_grid_query( $atts, 'product' );
    $products   = new WP_Query( $query_args );

    ob_start();
    include HCQB_PLUGIN_DIR . 'templates/shortcodes/product-grid.php';
    return ob_get_clean();
}
```

**`orderby` whitelist — never pass raw user input:**
```php
private function build_grid_query( array $atts, string $view ): array {
    $allowed_orderby = [ 'date', 'title', 'meta_value_num' ];
    $orderby         = $atts['orderby'] === 'price' ? 'meta_value_num' : $atts['orderby'];
    $orderby         = in_array( $orderby, $allowed_orderby ) ? $orderby : 'date';

    $args = [
        'post_type'      => 'hc-containers',
        'posts_per_page' => (int) $atts['limit'],
        'orderby'        => $orderby,
        'order'          => strtoupper( $atts['order'] ) === 'ASC' ? 'ASC' : 'DESC',
    ];

    if ( $orderby === 'meta_value_num' ) {
        $args['meta_key'] = 'hcqb_product_price';
    }

    if ( $view === 'lease' ) {
        $args['meta_query'][] = [ 'key' => 'hcqb_lease_enabled', 'value' => '1' ];
    }

    if ( $atts['category'] ) {
        $args['tax_query'][] = [
            'taxonomy' => 'hc-container-category',
            'field'    => 'slug',
            'terms'    => sanitize_text_field( $atts['category'] ),
        ];
    }

    return $args;
}
```

### Acceptance Criteria

- [ ] `[hc_product_grid]` renders all containers by default
- [ ] `[hc_lease_grid]` renders only containers with lease enabled
- [ ] All attributes (`columns`, `limit`, `category`, `orderby`, `order`) work correctly on both shortcodes
- [ ] Product cards link to `?view=product` (product grid) and `?view=lease` (lease grid)
- [ ] Grid CSS does not load on pages without a grid shortcode
- [ ] `orderby=price` sorts correctly

---

## Stage 7 — Quote Builder Frame 1

**Status:** 🔲 Not Started
**Depends on:** Stages 1, 2, 4
**Architecture ref:** §6 Quote Builder Page, §11 Pricing Logic, §12 Image Switching Logic

### Objective

Build the `[hc_quote_builder]` shortcode and all of Frame 1: the product selector, all questions rendering, the live price display, the image preview with front/back toggle, feature pills, and all vanilla JS modules. This is the core user-facing feature of the plugin.

### Files to Create

```
includes/class-hcqb-shortcodes.php  (add quote builder shortcode)
templates/quote-builder/frame-1-questions.php
templates/quote-builder/frame-1-preview.php
templates/quote-builder/partials/question-radio.php
templates/quote-builder/partials/question-dropdown.php
templates/quote-builder/partials/question-checkbox.php
assets/js/frontend/hcqb-quote-builder.js
assets/js/frontend/hcqb-pricing.js
assets/js/frontend/hcqb-image-switcher.js
assets/js/frontend/hcqb-conditionals.js
assets/js/frontend/hcqb-feature-pills.js
assets/css/frontend/hcqb-quote-builder.css
```

### Implementation Tasks

- [ ] Register `[hc_quote_builder]` shortcode; read `?product=` via `absint( $_GET['product'] ?? 0 )` — reject if 0, no matching post, or config inactive
- [ ] Define four distinct error states: no param, invalid ID, product not found, config inactive — styled error card with back link for each
- [ ] PHP renders entire config as `window.HCQBConfig = {...}` JSON block (see Critical Patterns)
- [ ] Render product selector field (pre-filled + locked); implement "Change" button flow in JS (confirmation alert → unlock → dropdown → URL update → reload)
- [ ] Render all questions in admin-defined order; conditional questions get `data-conditional`, `data-show-when-question`, `data-show-when-option` attributes and are hidden by default
- [ ] Render right panel: image element, front/back toggle buttons, feature pill slots, price label + live price display
- [ ] **`hcqb-quote-builder.js`** — Orchestrator. Initialise `window.HCQBState`. Call `init()` on all modules in order: Conditionals → Pricing → ImageSwitcher → FeaturePills.
- [ ] **`hcqb-pricing.js`** — On every `hcqb:selection-changed` event, sum all checked inputs not inside `[aria-hidden="true"]` elements, update DOM price display
- [ ] **`hcqb-image-switcher.js`** — Maintain active tags; on `hcqb:selection-changed`, run rule matching algorithm; update image src; handle front/back toggle
- [ ] **`hcqb-conditionals.js`** — On `hcqb:selection-changed`, evaluate all `[data-conditional]` questions; set/remove `aria-hidden` + `display:none`; reset hidden inputs; guard loop
- [ ] **`hcqb-feature-pills.js`** — On `hcqb:selection-changed`, update each pill with the selected option label for its assigned question

### Critical Patterns

**JS module architecture — shared state + event bus:**
```js
// hcqb-quote-builder.js (orchestrator)
window.HCQBState = {
    activeTags:   [],
    currentPrice: 0,
    currentView:  'front',
};

// All input changes dispatch this event — modules listen, never call each other
function dispatchSelectionChanged( questionKey, optionSlug, isSelected ) {
    document.dispatchEvent( new CustomEvent( 'hcqb:selection-changed', {
        detail: { questionKey, optionSlug, isSelected }
    }));
}

document.addEventListener( 'DOMContentLoaded', () => {
    HCQBConditionals.init();
    HCQBPricing.init();
    HCQBImageSwitcher.init();
    HCQBFeaturePills.init();
    // Trigger initial state calculation
    document.dispatchEvent( new CustomEvent( 'hcqb:selection-changed', { detail: { initial: true } } ) );
});
```

**PHP → JS data handoff — single JSON block:**
```php
// In the shortcode callback, before any HTML output
$config_data = [
    'basePrice'     => (float) get_post_meta( $product->ID, 'hcqb_product_price', true ),
    'productName'   => get_the_title( $product->ID ),
    'questions'     => $questions, // full array from get_post_meta
    'imageRules'    => $this->prepare_image_rules_for_js( $config ),
    'defaultImages' => [
        'front' => wp_get_attachment_url( get_post_meta( $config->ID, 'hcqb_default_front_image', true ) ),
        'back'  => wp_get_attachment_url( get_post_meta( $config->ID, 'hcqb_default_back_image', true ) ),
    ],
];
echo '<script>window.HCQBConfig = ' . wp_json_encode( $config_data ) . ';</script>';
```

**Image rules — pre-sorted for JS (most match tags first, preserving order for tie-break):**
```php
private function prepare_image_rules_for_js( WP_Post $config ): array {
    $rules = get_post_meta( $config->ID, 'hcqb_image_rules', true ) ?: [];

    // Attach resolved image URLs
    foreach ( $rules as &$rule ) {
        $rule['front_image_url'] = wp_get_attachment_url( $rule['front_image'] );
        $rule['back_image_url']  = wp_get_attachment_url( $rule['back_image'] );
    }

    // Sort by match_tags count DESC (most specific first) — ties preserve admin order
    usort( $rules, fn( $a, $b ) => count( $b['match_tags'] ) <=> count( $a['match_tags'] ) );

    return $rules;
}
```

**Image matching algorithm in JS:**
```js
// hcqb-image-switcher.js
function findMatchingRule( activeTags, rules ) {
    let bestFullMatch  = null;
    let bestPartial    = null;
    let bestPartialCnt = 0;

    for ( const rule of rules ) {
        const matchCount = rule.match_tags.filter( t => activeTags.includes( t ) ).length;
        const isFullMatch = matchCount === rule.match_tags.length && matchCount > 0;

        if ( isFullMatch ) {
            if ( ! bestFullMatch ) bestFullMatch = rule; // first full match wins (rules pre-sorted)
        } else if ( matchCount > bestPartialCnt ) {
            bestPartial    = rule;
            bestPartialCnt = matchCount;
        }
    }

    return bestFullMatch ?? bestPartial ?? null; // null = use default fallback
}
```

**Pricing engine — excludes hidden conditionals:**
```js
// hcqb-pricing.js
function recalculate() {
    let total = HCQBConfig.basePrice;

    // :not([aria-hidden="true"]) excludes hidden conditional question inputs automatically
    document.querySelectorAll(
        '.hcqb-question:not([aria-hidden="true"]) input:checked, ' +
        '.hcqb-question:not([aria-hidden="true"]) select'
    ).forEach( input => {
        const price = parseFloat( input.dataset.price ) || 0;
        if ( input.dataset.priceType === 'addition'  ) total += price;
        if ( input.dataset.priceType === 'deduction' ) total -= price;
    });

    HCQBState.currentPrice = Math.round( total * 100 ) / 100;
    document.querySelectorAll( '.hcqb-live-price' ).forEach( el => {
        el.textContent = '$' + HCQBState.currentPrice.toLocaleString( 'en-AU', { minimumFractionDigits: 2 } );
    });
}
```

**Conditional reveal — loop guard:**
```js
// hcqb-conditionals.js
document.addEventListener( 'hcqb:selection-changed', () => {
    document.querySelectorAll( '[data-conditional="true"]' ).forEach( wrapper => {
        const triggerKey    = wrapper.dataset.showWhenQuestion;
        const triggerOption = wrapper.dataset.showWhenOption;
        const triggerInput  = document.querySelector(
            `[data-question-key="${triggerKey}"] input[value="${triggerOption}"]:checked`
        );

        const shouldShow    = Boolean( triggerInput );
        const isCurrentlyHidden = wrapper.getAttribute( 'aria-hidden' ) === 'true';

        if ( shouldShow && isCurrentlyHidden ) {
            wrapper.removeAttribute( 'aria-hidden' );
            wrapper.style.display = '';
        } else if ( ! shouldShow && ! isCurrentlyHidden ) {
            wrapper.setAttribute( 'aria-hidden', 'true' );
            wrapper.style.display = 'none';
            // Reset inputs — but only if state actually changed (loop guard)
            wrapper.querySelectorAll( 'input' ).forEach( i => { i.checked = false; } );
            wrapper.querySelectorAll( 'select' ).forEach( s => { s.selectedIndex = 0; } );
            // Re-dispatch to recalculate price/images — note: hcqb:selection-changed will re-run
            // conditionals, but they will now no-op (isCurrentlyHidden is now true) — loop safe
        }
    });
});
```

### Acceptance Criteria

- [ ] `[hc_quote_builder]` with no `?product=` param shows a styled error message
- [ ] Invalid/missing product ID shows a styled error message
- [ ] Config with status inactive shows a styled error message
- [ ] All questions render in the order defined in admin
- [ ] Required questions are marked with asterisk
- [ ] Helper text renders below question label
- [ ] Conditional questions are hidden on load and revealed when trigger condition is met
- [ ] Revealing a conditional question does not affect pricing/images of sibling questions
- [ ] Hiding a conditional question resets its inputs and removes its contribution to pricing
- [ ] Live price updates on every input change, including checkbox multi-select
- [ ] Image updates when image-affecting options change
- [ ] Front/Back toggle switches image without re-evaluating rules
- [ ] Feature pills show selected option labels for pill-enabled questions (max 4)
- [ ] "Change" button shows confirmation alert; Cancel locks field; Confirm unlocks dropdown → URL updates → page reloads with new config

---

## Stage 8 — Frame 2 Contact Form + Google APIs

**Status:** 🔲 Not Started
**Depends on:** Stages 2, 7
**Architecture ref:** §7 Contact Information, §7.4 Address Autocomplete Flow

### Objective

Build Frame 2: the contact information form including Google Places Autocomplete for address, Distance Matrix for shipping calculation, Maps Embed for the map pin, phone prefix auto-set, client-side validation, honeypot field, and AJAX form submission handling.

### Files to Create

```
templates/quote-builder/frame-2-contact.php
assets/js/frontend/hcqb-google-maps.js
assets/js/frontend/hcqb-form-submit.js
```

### Implementation Tasks

- [ ] Render all Frame 2 form fields in the order defined in §7.2
- [ ] Include honeypot field (CSS-hidden, see Critical Patterns)
- [ ] Include nonce hidden field via `wp_nonce_field('hcqb_submit_quote', 'hcqb_nonce')`
- [ ] Include `Total Cost` read-only display (value carried from `HCQBState.currentPrice`)
- [ ] Include consent checkbox and collapsible privacy fine print
- [ ] Include submit button with label from settings
- [ ] **`hcqb-google-maps.js`** — Initialise Places Autocomplete on address searcher; on `place_changed`: populate all address fields (read-only), extract lat/lng, call Distance Matrix, update map, set phone prefix
- [ ] **`hcqb-form-submit.js`** — Client-side validation on submit; `fetch()` AJAX to `admin-ajax.php`; handle success (clear form, show message) and failure (show error, preserve data)
- [ ] Load Google Maps JS API via `wp_enqueue_script()` with `callback=hcqbMapsInit`; pass `HCQBLocale` via `wp_localize_script()`
- [ ] Prefix dropdown options populated from `hcqb_get_setting('prefix_options')`

### Critical Patterns

**Google Maps API loading:**
```php
// In class-hcqb-shortcodes.php (when rendering quote builder)
$api_key = hcqb_get_setting( 'google_maps_api_key' );
wp_enqueue_script(
    'google-maps-api',
    'https://maps.googleapis.com/maps/api/js?key=' . esc_attr( $api_key ) . '&libraries=places&callback=hcqbMapsInit',
    [],
    null,
    true // in footer
);
wp_localize_script( 'hcqb-quote-builder', 'HCQBLocale', [
    'apiKey'             => $api_key, // also needed for Distance Matrix service object
    'warehouseLat'       => (float) hcqb_get_setting( 'warehouse_lat' ),
    'warehouseLng'       => (float) hcqb_get_setting( 'warehouse_lng' ),
    'supportedCountries' => hcqb_get_setting( 'supported_countries', ['AU'] ),
    'ajaxUrl'            => admin_url( 'admin-ajax.php' ),
    'nonce'              => wp_create_nonce( 'hcqb_submit_quote' ),
]);
```

**Honeypot field — CSS hidden only:**
```php
// In frame-2-contact.php
?>
<div class="hcqb-honeypot" aria-hidden="true">
    <input type="text"
           name="hcqb_honeypot"
           value=""
           tabindex="-1"
           autocomplete="off">
</div>
```
```css
/* In hcqb-quote-builder.css */
.hcqb-honeypot {
    position: absolute;
    left: -9999px;
    /* Never display:none or visibility:hidden — bots check those */
}
```

**Places → address field population:**
```js
// hcqb-google-maps.js
window.hcqbMapsInit = function() {
    const input      = document.getElementById( 'hcqb-address-searcher' );
    const autocomplete = new google.maps.places.Autocomplete( input, {
        types: ['geocode'],
        componentRestrictions: { country: HCQBLocale.supportedCountries },
    });

    autocomplete.addListener( 'place_changed', () => {
        const place = autocomplete.getPlace();
        if ( ! place.geometry ) return;

        populateAddressFields( place.address_components );
        callDistanceMatrix( place.geometry.location );
        updateMap( place.geometry.location );
        setPhonePrefix( getCountryCode( place.address_components ) );
    });
};
```

**AJAX submission:**
```js
// hcqb-form-submit.js
async function submitForm( form ) {
    const formData = new FormData( form );
    formData.append( 'action', 'hcqb_submit_quote' );
    formData.append( 'product_id', new URLSearchParams( window.location.search ).get( 'product' ) );
    formData.append( 'total_price', HCQBState.currentPrice );

    const response = await fetch( HCQBLocale.ajaxUrl, { method: 'POST', body: formData } );
    const result   = await response.json();

    if ( result.success ) {
        showSuccessMessage();
        form.reset();
    } else {
        showErrorMessage( result.data?.message ?? 'An error occurred. Please try again.' );
    }
}
```

### Acceptance Criteria

- [ ] Address Searcher autocomplete is restricted to configured countries
- [ ] Selecting an address populates all address fields (read-only)
- [ ] Shipping distance calculates and populates after address selection
- [ ] Map updates with customer address pin; warehouse pin visible as origin
- [ ] Phone prefix auto-sets from resolved country code
- [ ] Phone prefix defaults to first supported country if no address entered
- [ ] Honeypot field is not visible to users (verify in rendered HTML + browser)
- [ ] First Name, Last Name, Email, Confirm Email, Consent Checkbox — all validated client-side before submission
- [ ] Confirm Email mismatch shows inline error
- [ ] Invalid email format shows inline error
- [ ] Total Cost in Frame 2 matches live price from Frame 1
- [ ] Successful submission shows success message and clears form
- [ ] Failed submission shows error message and preserves all form data

---

## Stage 9 — Submission Processing + Emails

**Status:** 🔲 Not Started
**Depends on:** Stages 2, 8
**Architecture ref:** §8 Submission Flow, §15.3 Submissions Meta Keys, §17 Email Templates

### Objective

Implement the complete server-side submission handler: nonce verification, honeypot check, input sanitisation, WP post creation, all meta saves, and both email dispatches. This closes the full end-to-end flow from customer form submission to database record and email notifications.

### Files to Create

```
includes/class-hcqb-ajax.php
includes/class-hcqb-submission.php
includes/class-hcqb-email.php
templates/emails/admin-notification.php
templates/emails/customer-copy.php
```

### Implementation Tasks

- [ ] Register AJAX action as both `wp_ajax_hcqb_submit_quote` and `wp_ajax_nopriv_hcqb_submit_quote`
- [ ] Implement the 10-step processing pipeline in the strict order below (see Critical Patterns)
- [ ] Save all meta keys from §15.3, including `hcqb_selected_options` as a label snapshot array
- [ ] Set `hcqb_submission_status` to the first status KEY from settings (e.g. `status_1`), never a label
- [ ] Set `post_title` to `"{Prefix} {First} {Last} — {Product Name}"`
- [ ] Dispatch admin notification email and customer copy email via `wp_mail()`
- [ ] Log email failures to `update_option('hcqb_last_email_error', ...)` — do NOT let failure block the submission save
- [ ] Return `wp_send_json_success()` on success, `wp_send_json_error()` on failure

### Critical Patterns

**Strict processing order — non-negotiable:**
```php
public function handle_submission(): void {
    // Step 1: Verify nonce
    if ( ! check_ajax_referer( 'hcqb_submit_quote', 'hcqb_nonce', false ) ) {
        wp_send_json_error( [ 'message' => 'Security check failed.' ], 403 );
    }

    // Step 2: Honeypot check
    if ( ! empty( $_POST['hcqb_honeypot'] ) ) {
        wp_send_json_success(); // silent rejection — return fake success
    }

    // Step 3: Sanitise and validate product ID
    $product_id = absint( $_POST['product_id'] ?? 0 );
    if ( ! $product_id || get_post_type( $product_id ) !== 'hc-containers' ) {
        wp_send_json_error( [ 'message' => 'Invalid product.' ] );
    }

    // Step 4: Validate config is active
    $config = hcqb_get_active_config_for_product( $product_id );
    if ( ! $config ) {
        wp_send_json_error( [ 'message' => 'No active quote configuration found.' ] );
    }

    // Step 5: Required field validation
    $errors = $this->validate_required_fields( $_POST );
    if ( ! empty( $errors ) ) {
        wp_send_json_error( [ 'message' => 'Validation failed.', 'errors' => $errors ] );
    }

    // Step 6: Sanitise all inputs
    $data = $this->sanitise_submission_data( $_POST );

    // Step 7: Create post
    $post_id = wp_insert_post([
        'post_type'   => 'hc-quote-submissions',
        'post_status' => 'publish',
        'post_title'  => trim( $data['prefix'] . ' ' . $data['first_name'] . ' ' . $data['last_name'] )
                         . ' — ' . get_the_title( $product_id ),
    ]);
    if ( is_wp_error( $post_id ) ) {
        wp_send_json_error( [ 'message' => 'Failed to save submission.' ] );
    }

    // Step 8: Save all meta
    $this->save_submission_meta( $post_id, $data, $product_id, $config );

    // Step 9: Send emails (log failures, don't block)
    $this->send_emails( $post_id, $data, $product_id );

    // Step 10: Return success
    wp_send_json_success( [ 'message' => 'Submission received.' ] );
}
```

**Status key — first key from settings:**
```php
$status_labels = hcqb_get_setting( 'submission_status_labels', [] );
$first_key     = $status_labels[0]['key'] ?? 'status_1';
update_post_meta( $post_id, 'hcqb_submission_status', $first_key );
```

**Selected options — label snapshot:**
```php
// Store human-readable snapshots, NOT slugs or keys
// This ensures old submissions remain readable if options are later renamed/deleted
$selected_options = [];
foreach ( $data['selected_options'] as $opt ) {
    $selected_options[] = [
        'question_label' => sanitize_text_field( $opt['question_label'] ),
        'option_label'   => sanitize_text_field( $opt['option_label'] ),
        'price'          => floatval( $opt['price'] ),
        'price_type'     => in_array( $opt['price_type'], ['addition','deduction'] ) ? $opt['price_type'] : 'addition',
    ];
}
update_post_meta( $post_id, 'hcqb_selected_options', $selected_options );
```

**Email dispatch — failure isolation:**
```php
private function send_emails( int $post_id, array $data, int $product_id ): void {
    $admin_sent    = $this->send_admin_notification( $post_id, $data, $product_id );
    $customer_sent = $this->send_customer_copy( $post_id, $data, $product_id );

    if ( ! $admin_sent || ! $customer_sent ) {
        update_option( 'hcqb_last_email_error', [
            'post_id'       => $post_id,
            'admin_sent'    => $admin_sent,
            'customer_sent' => $customer_sent,
            'time'          => current_time( 'mysql' ),
        ]);
        // Do NOT return or throw — submission is already saved
    }
}
```

### Acceptance Criteria

- [ ] Valid submission creates a `hc-quote-submissions` post with correct `post_title`
- [ ] All meta keys from §15.3 are saved with correct values
- [ ] `hcqb_submission_status` stores the status KEY, not a label
- [ ] `hcqb_selected_options` stores label snapshots
- [ ] `hcqb_submitted_at` stores ISO 8601 datetime
- [ ] Admin notification email is received at configured address
- [ ] Customer copy email is received at submitted email address
- [ ] Email subjects have tokens (`{product_name}`, `{customer_name}`) replaced
- [ ] Nonce failure returns a 403 response; submission is NOT saved
- [ ] Honeypot-populated submission returns fake success; submission is NOT saved
- [ ] Invalid `product_id` returns error; submission is NOT saved
- [ ] Email failure does NOT prevent submission from being saved

---

## Stage 10 — Submissions Admin View

**Status:** 🔲 Not Started
**Depends on:** Stage 9
**Architecture ref:** §9 Quote Submissions Admin View

### Objective

Build the submissions admin interface: a custom list table with all columns, status filtering, and colour-coded badges; and a clean read-only detail view with four panels and an editable status field. This completes the admin CRM loop.

### Files to Create

```
admin/class-hcqb-list-table-submissions.php
admin/class-hcqb-metabox-submission.php
assets/js/admin/hcqb-admin-submissions.js
assets/css/admin/hcqb-admin-submissions.css
```

### Implementation Tasks

- [ ] **List table:** Customise columns via `manage_hc-quote-submissions_posts_columns` and `manage_hc-quote-submissions_posts_custom_column` hooks — columns: Customer Name, Email (mailto link), Phone, Product, Total Estimate, Shipping Distance, Status (badge), Submitted
- [ ] Default sort newest first (`pre_get_posts` for the CPT)
- [ ] Status filter via `restrict_manage_posts` (renders `<select>`); `pre_get_posts` applies meta query when filter is active
- [ ] Make Customer Name column the row title link to the detail view
- [ ] **Detail view:** Hook `add_meta_boxes` to add one custom meta box (suppress all default WP meta boxes via `remove_meta_box()`); render four panels inside it (Customer Details, Quote Summary, Shipping, Submission Info + Status)
- [ ] Status dropdown in detail view: populated from `hcqb_settings.submission_status_labels`; "Save Status" button triggers AJAX save
- [ ] Register `wp_ajax_hcqb_update_submission_status` handler; validate nonce; confirm key exists in settings; update meta

### Critical Patterns

**Status badge — colour via CSS attribute selector, not PHP:**
```php
// In the list table column renderer — output data-status attribute
$status_key   = get_post_meta( $post->ID, 'hcqb_submission_status', true );
$status_labels = hcqb_get_setting( 'submission_status_labels', [] );
$status_label  = 'Unknown';
foreach ( $status_labels as $s ) {
    if ( $s['key'] === $status_key ) { $status_label = $s['label']; break; }
}
echo '<span class="hcqb-status-badge" data-status="' . esc_attr( $status_key ) . '">'
     . esc_html( $status_label ) . '</span>';
```
```css
/* In hcqb-admin-submissions.css — colour by key, not by label text */
.hcqb-status-badge { padding: 3px 8px; border-radius: 3px; font-size: 12px; }
.hcqb-status-badge[data-status="status_1"] { background: #d4e6ff; color: #004a99; } /* New    */
.hcqb-status-badge[data-status="status_2"] { background: #fff0d4; color: #9a5500; } /* Contacted */
.hcqb-status-badge[data-status="status_3"] { background: #d4f0d4; color: #1a6b1a; } /* Closed */
```

**Status AJAX save — validate key before updating:**
```php
public function handle_update_status(): void {
    check_ajax_referer( 'hcqb_update_status', 'nonce' );

    $post_id    = absint( $_POST['post_id'] ?? 0 );
    $status_key = sanitize_key( $_POST['status_key'] ?? '' );

    // Validate: key must exist in configured status labels
    $valid_keys = array_column( hcqb_get_setting( 'submission_status_labels', [] ), 'key' );
    if ( ! in_array( $status_key, $valid_keys, true ) ) {
        wp_send_json_error( [ 'message' => 'Invalid status key.' ] );
    }

    update_post_meta( $post_id, 'hcqb_submission_status', $status_key );
    wp_send_json_success();
}
```

**Suppress default meta boxes on submission detail:**
```php
add_action( 'add_meta_boxes', function() {
    remove_meta_box( 'submitdiv',      'hc-quote-submissions', 'side' );
    remove_meta_box( 'slugdiv',        'hc-quote-submissions', 'normal' );
    remove_meta_box( 'authordiv',      'hc-quote-submissions', 'normal' );
    // Add only the one custom panels meta box
    add_meta_box( 'hcqb-submission-detail', 'Submission Details',
                  [ HCQB_Metabox_Submission::class, 'render' ],
                  'hc-quote-submissions', 'normal', 'high' );
});
```

### Acceptance Criteria

- [ ] List table shows all columns with correct data
- [ ] Default sort is newest first
- [ ] Status filter dropdown works: selecting a status filters the list
- [ ] Customer Name links to the detail view
- [ ] Status badges are colour-coded; colours do not break if a label is renamed
- [ ] Detail view suppresses all default WP meta boxes
- [ ] All four panels (Customer Details, Quote Summary, Shipping, Submission Info) render correctly
- [ ] Selected options in Quote Summary display correct labels and prices
- [ ] Status dropdown on detail view shows all configured statuses
- [ ] Saving status updates the stored key and refreshes the badge
- [ ] Quote Summary calculates and displays total correctly from stored meta

---

## Stage 11 — QA + Edge Case Testing

**Status:** 🔲 Not Started
**Depends on:** All previous stages
**Architecture ref:** §18 Open Items, §12 Image Switching Logic

### Objective

Systematic verification of every documented rule, constraint, and edge case. This is not a coding stage — it is a testing checklist to be worked through in full before the plugin is considered ready for client handoff.

### Test Checklist

**Data integrity:**
- [ ] Rename a question label → re-save config → verify question `key` is unchanged in the database
- [ ] Rename an option label → re-save config → verify option `slug` is unchanged in the database
- [ ] Verify image rules still match correctly after option label rename
- [ ] Verify "Show When — Option" dropdown in a conditional question still references the correct option after label rename

**1:1 config enforcement:**
- [ ] Try to link the same product to a second config (at render time: product should be disabled in dropdown)
- [ ] Simulate race condition: manually set the same linked product on two configs in the database; verify save handler rejects the second one

**Image rule matching:**
- [ ] Exact match: select combination that matches a specific rule → correct front/back images shown
- [ ] No match: select combination with no matching rule → default fallback images shown
- [ ] Partial match: select combination where no exact rule matches → best partial match rule images shown
- [ ] Tie-break: two rules with equal match tag count both match → rule higher in admin order wins
- [ ] Front/Back toggle switches images without changing matched rule

**Conditional questions:**
- [ ] Conditional question is hidden on page load
- [ ] Trigger condition met → question reveals
- [ ] Trigger condition removed → question hides again; its inputs are reset
- [ ] Hiding a conditional question removes its price contribution immediately
- [ ] Default-selected options in a conditional question do not contribute to price while hidden

**Quote builder product change flow:**
- [ ] Click "Change" → confirmation alert appears with correct text from settings
- [ ] Click "Cancel" in alert → field stays locked; no change
- [ ] Click "Yes, Change Product" → field unlocks; dropdown shows only products with active configs
- [ ] Select a different product → URL updates to `?product=456` → page reloads with new config

**Security:**
- [ ] Submit form with honeypot field populated (use browser devtools to set value) → submission silently rejected; no WP post created
- [ ] Tamper with nonce field before submission → 403 response; no WP post created
- [ ] Submit with `?product=0` → error response; no WP post created
- [ ] Submit with `?product=abc` → `absint()` results in 0 → error response; no WP post created

**Error states on quote builder page:**
- [ ] No `?product=` parameter → styled error card with back link
- [ ] `?product=99999` (non-existent ID) → styled error card with back link
- [ ] Product exists but linked config is inactive → styled error card with back link

**Lease logic:**
- [ ] `?view=lease` on a product with lease disabled → falls back to purchase view (no error)
- [ ] `[hc_lease_grid]` does not include products with lease disabled

**Settings → display integrity:**
- [ ] Rename status label "New" to "Pending" → all existing submissions showing `status_1` now display "Pending"
- [ ] Status badge colour for `status_1` still applies correctly after label rename

**Feature pills:**
- [ ] Enable "Show in Feature Pill" on 5 questions → only first 4 display in the right panel
- [ ] Feature pill displays correct selected option label on each selection change

**Checkbox pricing:**
- [ ] Check multiple options in a checkbox question → all prices are summed (not just the first)
- [ ] Check + uncheck options in a checkbox question → price updates correctly in both directions

**Email and submission:**
- [ ] Mock `wp_mail()` failure (temporary filter returning false) → submission WP post still created; email error logged to option
- [ ] Admin notification email received with correct content, subject, and all tokens replaced
- [ ] Customer copy email received with correct content, subject, and all tokens replaced
- [ ] `hcqb_submission_status` meta stores a key (e.g. `status_1`) — not a label string

---

_Development document version 1.0 — covers all 11 build stages, file structure, implementation patterns, and QA checklist. Update stage status indicators in the tracker as work progresses. Cross-reference ARCHITECTURE.md (v1.2) for full product specification at any stage._
