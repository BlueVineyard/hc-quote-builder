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

### [Stage 4] — Pending
**hc-quote-configs Admin Screen**
- [ ] `admin/class-hcqb-metabox-config.php`
- [ ] `assets/js/admin/hcqb-admin-repeater.js`
- [ ] `assets/js/admin/hcqb-admin-config.js`
- [ ] `assets/css/admin/hcqb-admin-config.css`

---

### [Stage 5] — Pending
**Product Page Template**
- [ ] `templates/single-hc-containers.php`
- [ ] `assets/css/frontend/hcqb-product-page.css`

---

### [Stage 6] — Pending
**Product Grid + Lease Grid Shortcodes**
- [ ] `includes/class-hcqb-shortcodes.php`
- [ ] `templates/shortcodes/product-grid.php`
- [ ] `templates/shortcodes/lease-grid.php`
- [ ] `templates/shortcodes/product-card.php`
- [ ] `assets/css/frontend/hcqb-grids.css`

---

### [Stage 7] — Pending
**Quote Builder — Frame 1**
- [ ] `templates/quote-builder/frame-1-questions.php`
- [ ] `templates/quote-builder/frame-1-preview.php`
- [ ] `templates/quote-builder/partials/question-radio.php`
- [ ] `templates/quote-builder/partials/question-dropdown.php`
- [ ] `templates/quote-builder/partials/question-checkbox.php`
- [ ] `assets/js/frontend/hcqb-quote-builder.js`
- [ ] `assets/js/frontend/hcqb-pricing.js`
- [ ] `assets/js/frontend/hcqb-image-switcher.js`
- [ ] `assets/js/frontend/hcqb-conditionals.js`
- [ ] `assets/js/frontend/hcqb-feature-pills.js`
- [ ] `assets/css/frontend/hcqb-quote-builder.css`

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
