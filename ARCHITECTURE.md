# HC Quote Builder — Product Architecture Document

**Plugin Name:** HC Quote Builder  
**Code Prefix:** `hcqb_`  
**Version:** 1.2 (Draft)  
**Project:** HC Containers Website  
**Prepared by:** Blue Vineyard  
**Status:** Pre-Development

---

## Table of Contents

1. [Plugin Overview](#1-plugin-overview)
2. [Technology Stack](#2-technology-stack)
3. [Custom Post Types](#3-custom-post-types)
4. [Plugin Settings Page](#4-plugin-settings-page)
5. [Admin UI Structure](#5-admin-ui-structure)
6. [Quote Builder Page — Frame 1](#6-quote-builder-page--frame-1)
7. [Contact Information — Frame 2](#7-contact-information--frame-2)
8. [Submission Flow](#8-submission-flow)
9. [Quote Submissions Admin View](#9-quote-submissions-admin-view)
10. [Product Page Logic](#10-product-page-logic)
11. [Pricing Logic](#11-pricing-logic)
12. [Image Switching Logic](#12-image-switching-logic)
13. [Shortcodes](#13-shortcodes)
14. [URL Parameter Reference](#14-url-parameter-reference)
15. [Database & Data Structure](#15-database--data-structure)
16. [User Flows](#16-user-flows)
17. [Email Templates](#17-email-templates)
18. [Open Items & Pending Confirmations](#18-open-items--pending-confirmations)

---

## 1. Plugin Overview

HC Quote Builder is a custom WordPress plugin built exclusively for HC Containers. It provides:

- A **custom post type** for managing portable building products (`hc-containers`)
- A **custom post type** for managing quote configurations (`hc-quote-configs`)
- A **custom post type** for storing quote submissions (`hc-quote-submissions`)
- A **dynamic quote builder page** where customers configure a portable building and receive a live itemised cost estimate
- A **contact information form** where customers submit their estimate request
- A **product page template** that renders either purchase or lease information based on URL parameter
- A **plugin settings page** for warehouse address, Google API key, supported countries, email configuration, and more
- **Shortcodes** for embedding product grids and lease grids on any WordPress page

The plugin is fully self-contained — no third-party field plugins are used. All meta boxes and admin UI are built using native WordPress APIs only. All questions, options, prices, and image rules are managed through the WordPress admin with no code changes required.

---

## 2. Technology Stack

| Layer                | Technology                                                         |
| -------------------- | ------------------------------------------------------------------ |
| Platform             | WordPress (latest stable)                                          |
| Backend              | PHP 8.x                                                            |
| Custom Fields        | Native WordPress meta boxes (no ACF or any third-party dependency) |
| Frontend             | Vanilla JS + CSS (no jQuery dependency)                            |
| Maps & Geocoding     | Google Places Autocomplete API                                     |
| Distance Calculation | Google Distance Matrix API                                         |
| Map Embed            | Google Maps Embed API                                              |
| Data Storage         | WordPress Custom Post Types + Post Meta                            |
| Shortcodes           | Native WordPress shortcode API                                     |
| Admin UI             | Native WordPress admin + custom meta box UI                        |

---

## 3. Custom Post Types

### 3.1 `hc-containers` — Products

Represents a single portable building product. Each entry can be displayed as a purchase product, a lease product, or both depending on configuration.

| Setting          | Value                    |
| ---------------- | ------------------------ |
| Post Type Key    | `hc-containers`          |
| Label (Singular) | Container                |
| Label (Plural)   | Containers               |
| Public           | `true`                   |
| Has Archive      | `false`                  |
| Supports         | title, thumbnail, editor |
| Menu Icon        | `dashicons-building`     |
| Rewrite Slug     | `portables`              |

---

### 3.2 `hc-quote-configs` — Quote Configurations

Represents the quote builder configuration for a single product. Each entry is linked 1:1 to an `hc-containers` entry.

| Setting          | Value                               |
| ---------------- | ----------------------------------- |
| Post Type Key    | `hc-quote-configs`                  |
| Label (Singular) | Quote Config                        |
| Label (Plural)   | Quote Builder                       |
| Public           | `false`                             |
| Has Archive      | `false`                             |
| Show in Menu     | `true` (standalone admin menu item) |
| Menu Icon        | `dashicons-calculator`              |
| Supports         | title                               |

---

### 3.3 `hc-quote-submissions` — Quote Submissions

Stores every customer quote submission. Each entry is a read-only record accessible from the WordPress admin with a clean detail view.

| Setting          | Value                               |
| ---------------- | ----------------------------------- |
| Post Type Key    | `hc-quote-submissions`              |
| Label (Singular) | Quote Submission                    |
| Label (Plural)   | Quote Submissions                   |
| Public           | `false`                             |
| Has Archive      | `false`                             |
| Show in Menu     | `true` (standalone admin menu item) |
| Menu Icon        | `dashicons-email-alt`               |
| Supports         | title                               |

---

## 4. Plugin Settings Page

Accessible via **Settings → HC Quote Builder** in the WordPress admin. Organised into four tabs.

---

### Tab 1 — General

| Field                           | Type                          | Description                                                                                                                                                                                                                                                                                                                                                                              |
| ------------------------------- | ----------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Warehouse Address               | Text + autocomplete           | Origin point for shipping distance calculation. Uses Google Places to resolve to coordinates                                                                                                                                                                                                                                                                                             |
| Warehouse Coordinates (Lat/Lng) | Text (auto-filled, read-only) | Resolved automatically from warehouse address entry. Stored for Distance Matrix API calls                                                                                                                                                                                                                                                                                                |
| Google Maps API Key             | Password-masked text input    | Single key used for Places Autocomplete, Maps Embed, and Distance Matrix API. **This key is exposed to the frontend for Places Autocomplete.** It must be restricted in the Google Cloud Console to: (1) the specific APIs in use — Places, Maps Embed, Distance Matrix — and (2) HTTP referrers limited to the production domain only. This is a required deployment step, not optional |
| Supported Countries             | Multi-select                  | Restricts Google Places Autocomplete to selected countries only. Also determines which country phone prefixes are available. e.g. Australia (+61), New Zealand (+64)                                                                                                                                                                                                                     |

---

### Tab 2 — Email

| Field                     | Type        | Description                                                                                         |
| ------------------------- | ----------- | --------------------------------------------------------------------------------------------------- |
| Admin Notification Email  | Email input | Where submission notifications are sent (HC Containers team inbox)                                  |
| Email From Name           | Text        | e.g. "HC Containers Quote Builder"                                                                  |
| Email From Address        | Email input | The from address for all outgoing emails                                                            |
| Admin Email Subject       | Text        | Subject line for the admin notification email. Supports tokens: `{product_name}`, `{customer_name}` |
| Customer Email Subject    | Text        | Subject line for the customer copy email. Supports tokens: `{product_name}`                         |
| Customer Email Intro Text | Textarea    | Intro paragraph shown at the top of the customer copy email                                         |
| Company Name              | Text        | Used in customer email footer                                                                       |
| Company Phone             | Text        | Used in customer email footer                                                                       |
| Company Website           | URL         | Used in customer email footer                                                                       |

---

### Tab 3 — Quote Builder

| Field                     | Type                     | Description                                                                                                                                                                                                              |
| ------------------------- | ------------------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| Quote Builder Page        | Page selector (dropdown) | The WordPress page hosting `[hc_quote_builder]`. Used to build the redirect URL from product pages                                                                                                                       |
| Change Product Alert Text | Textarea                 | Message shown in the confirmation alert when user clicks "Change" on the product field. Default: "Changing the product will reload the quote builder and your current selections will be lost. Do you want to continue?" |
| Submit Button Label       | Text                     | Default: "Send my Estimate and Contact Request!"                                                                                                                                                                         |
| Consent Checkbox Text     | Textarea                 | Legal consent label shown alongside the checkbox                                                                                                                                                                         |
| Privacy Policy Fine Print | Textarea                 | Fine print shown on expand below the consent checkbox                                                                                                                                                                    |

---

### Tab 4 — Form Options

| Field                    | Type                        | Description                                                                                                                                                                                                                                                                                                           |
| ------------------------ | --------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Prefix Options           | Repeatable text rows        | Admin can add, edit, remove, and reorder prefix options shown in the quote form Prefix dropdown. Default: Mr, Mrs, Ms, Dr                                                                                                                                                                                             |
| Submission Status Labels | Repeatable key + label rows | Each status has a **stable internal key** (auto-generated on creation, immutable) and an **editable display label**. Admin can rename labels freely without affecting existing submission records. Admin can add and reorder statuses. Default entries: `status_1` → New, `status_2` → Contacted, `status_3` → Closed |

> **Status key stability:** Submissions store the internal key (e.g. `status_1`), never the label text. If the admin renames "New" to "Pending", all existing submissions automatically display "Pending" — no data migration required. Keys are generated once on row creation and never regenerate.

---

## 5. Admin UI Structure

### 5.1 `hc-containers` — Admin Edit Screen

Each product edit screen contains a **tabbed meta box** built with native WordPress meta box API.

---

#### Tab 1 — Product Info

| Field                 | Type                                | Description                                                                                                         |
| --------------------- | ----------------------------------- | ------------------------------------------------------------------------------------------------------------------- |
| Short Description     | Textarea                            | Brief tagline shown in product grids                                                                                |
| Product Price         | Number                              | Base/flatpack price in AUD. This is the base price used in the quote builder — never duplicated in the quote config |
| Star Rating           | Number (1–5, step 0.1)              | Displayed on product page                                                                                           |
| Review Count          | Number                              | Displayed next to star rating                                                                                       |
| Product Images        | Gallery (repeater of image uploads) | Multiple images shown in thumbnail gallery on product page                                                          |
| Features              | Repeater                            | Each row: Icon upload + Feature Label (e.g. "Open Room", "1x Std PA Door")                                          |
| Product Description   | Textarea (rich text)                | Full description shown in collapsible panel on product page                                                         |
| Additional Notes      | Textarea                            | Shown below features (e.g. tilt tray/crane info, planning regulations disclaimer)                                   |
| Plan Document         | File upload                         | PDF linked as "View full plan here"                                                                                 |
| Shipping Details Link | URL input                           | Linked as "Click here for shipping details"                                                                         |
| Categories            | Native WordPress taxonomy           | e.g. Portable Building, Shipping Container, Site Shed                                                               |

---

#### Tab 2 — Lease Info

| Field                       | Type                 | Description                                                                                   |
| --------------------------- | -------------------- | --------------------------------------------------------------------------------------------- |
| Enable Lease                | Toggle (Yes/No)      | If disabled, lease view is inaccessible and this product will not appear in `[hc_lease_grid]` |
| Lease Price                 | Number               | Weekly price in AUD                                                                           |
| Lease Price Label           | Text                 | e.g. "/Week"                                                                                  |
| Lease Terms                 | Textarea (rich text) | Minimum weeks, fixed/ongoing terms, T&Cs                                                      |
| Standard Layout Title       | Text                 | e.g. "6×3m Portable Site Office"                                                              |
| Standard Layout Description | Textarea (rich text) | What is included in the standard lease layout                                                 |
| Lease Optional Extras       | Repeater             | Each row: Label + Weekly Add-on Price (e.g. "Sliding Glass Door" — $15/week)                  |
| Enquiry Button Label        | Text                 | Default: "Enquire Now"                                                                        |

> ⚠️ Lease optional extras are completely separate from quote builder options. They are managed here only and appear only on the lease product page view.

---

### 5.2 `hc-quote-configs` — Admin Edit Screen

Accessible via the **"Quote Builder"** standalone menu item in the WordPress admin sidebar. The edit screen is divided into four sections.

---

#### Section 1 — Configuration Info

| Field          | Type                     | Description                                                                                                                                         |
| -------------- | ------------------------ | --------------------------------------------------------------------------------------------------------------------------------------------------- |
| Config Title   | Text (WP native title)   | e.g. "3×3m Portable Building — Quote Config"                                                                                                        |
| Linked Product | Dropdown (post selector) | Lists all `hc-containers` entries. Products already linked to another active config are shown as disabled, enforcing the 1:1 relationship           |
| Status         | Select                   | Active / Inactive. Inactive configs will not load in the quote builder and will suppress the "Get a Custom Quote" button on the linked product page |

---

#### Section 2 — Questions Builder

A repeater where the admin defines all questions for this product's quote. Questions can be reordered via drag-and-drop.

**Question Fields:**

| Field                | Type                                                        | Description                                                                                                                                                                                                                                   |
| -------------------- | ----------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Question Label       | Text                                                        | e.g. "Do you require Air Conditioning?"                                                                                                                                                                                                       |
| Question Key         | Auto-generated slug (read-only, immutable after first save) | e.g. `do_you_require_air_conditioning` — generated from the label on first save. Never regenerates. Used internally only, never shown to the customer                                                                                         |
| Input Type           | Select                                                      | Radio / Dropdown / Checkbox                                                                                                                                                                                                                   |
| Required             | Toggle                                                      | Whether the customer must answer this question before submitting                                                                                                                                                                              |
| Helper Text          | Text                                                        | Optional small note shown below the question label (e.g. "This allows power to be supplied by extension cord.")                                                                                                                               |
| Show in Feature Pill | Toggle                                                      | If enabled, this question's selected option label is shown as one of the feature overlay chips in the right panel preview. Maximum 4 questions can have this enabled — if more than 4 are toggled on, only the first 4 in order are displayed |
| Is Conditional       | Toggle                                                      | If enabled, this question is hidden until its trigger condition is met                                                                                                                                                                        |
| Show When — Question | Dropdown                                                    | Select which other question in this config triggers this one to appear                                                                                                                                                                        |
| Show When — Option   | Dropdown                                                    | Select which option within that question must be selected for this question to reveal                                                                                                                                                         |
| Options              | Nested repeater                                             | See Option Fields below                                                                                                                                                                                                                       |

> **v1.0 Constraint — Single-level conditionals only:** A conditional question can depend on any other question, but only one level deep. A question that is itself conditional cannot be used as a trigger for another conditional question. This covers all known client requirements for v1.0. If nested conditionals are required in future, the condition system will need to be extended.

**Option Fields (nested inside each Question):**

| Field            | Type                                                   | Description                                                                                                                                                                                                                                                |
| ---------------- | ------------------------------------------------------ | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Option Label     | Text                                                   | e.g. "Please install 1.6kW A/C Cooling Only"                                                                                                                                                                                                               |
| Option Slug      | Auto-generated (read-only, immutable after first save) | e.g. `please_install_16kw_ac_cooling_only` — generated from label on first save. **Never regenerates after creation.** Used internally only — never shown to the customer                                                                                  |
| Price            | Number                                                 | The dollar amount this option adds to or deducts from the total                                                                                                                                                                                            |
| Price Type       | Radio                                                  | ➕ Addition / ➖ Deduction                                                                                                                                                                                                                                 |
| Option Role      | Select                                                 | Optional. Reserved roles: `assembly` (marks this option as the assembly cost, used to calculate the assembled price on the product page). Only one option per config may hold the `assembly` role — the UI enforces this. Leave blank for standard options |
| Affects Image    | Toggle                                                 | If enabled, this option participates in image rule matching                                                                                                                                                                                                |
| Default Selected | Toggle                                                 | Whether this option is pre-selected when the quote form first loads                                                                                                                                                                                        |

> **Slug immutability:** Option slugs are generated from the label on first save and then frozen. The admin can freely rename the option label at any time — the slug will not change and no image rules will break. The admin UI displays the slug as read-only after creation with the note: _"Slug is locked after creation."_

> **Image Tag behaviour:** When "Affects Image" is enabled, the option slug is registered as an image tag. In the Image Rules section, admins always see the human-readable option label — never the slug. Renaming the label updates the display in Image Rules automatically. Rule matching always operates on the immutable slug.

---

#### Section 3 — Image Rules

A repeater where the admin defines which images display for each combination of active image-affecting options. Rules are evaluated from most specific (most match tags) to least specific. The first matching rule wins.

**Rule order matters for tie-breaking.** If two rules have the same number of match tags and both match the active tag set, the rule that appears **higher in the list** wins. The admin can drag and drop rules to set explicit priority order. The UI displays the rule order number to make this clear.

**Each rule contains:**

| Field       | Type         | Description                                                                                                                                                                                       |
| ----------- | ------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Rule Name   | Text         | Internal label for the admin (e.g. "Assembled + 1.6kW + Kitchenette")                                                                                                                             |
| Match Tags  | Multi-select | Populated from all options in this config that have "Affects Image" enabled. Always displays human-readable option labels. All selected tags must be active simultaneously for this rule to match |
| Front Image | Image upload | Shown when the Front view toggle is active                                                                                                                                                        |
| Back Image  | Image upload | Shown when the Back view toggle is active                                                                                                                                                         |

**Default Fallback (outside the repeater, always present):**

| Field               | Type         | Description                                                  |
| ------------------- | ------------ | ------------------------------------------------------------ |
| Default Front Image | Image upload | Shown when no image rule matches the current selection state |
| Default Back Image  | Image upload | Shown when no image rule matches the current selection state |

---

#### Section 4 — Duplication

The admin can duplicate any `hc-quote-configs` entry. The duplicated entry will:

- Receive a new post ID
- Have no linked product assigned (must be re-assigned manually)
- Copy all questions, options, and image rules exactly

This allows the client to create a new product's quote config by duplicating an existing one and adjusting only what differs — avoiding re-entry of shared questions.

---

## 6. Quote Builder Page — Frame 1

### 6.1 Overview

A single WordPress page hosting the `[hc_quote_builder]` shortcode. This page serves all products — the specific product's quote configuration is loaded dynamically from the `?product=` URL parameter.

```
/get-a-quote/?product=123
```

Where `123` is the `hc-containers` post ID. The system queries `hc-quote-configs` for the entry where `linked_product = 123` and builds the form from that data.

If no `?product=` parameter is present, or the product ID is invalid, or the linked quote config is inactive, an appropriate error message is displayed.

---

### 6.2 Page Layout

Two-column layout at 1440px canvas width:

| Column | Width  | Content                                    |
| ------ | ------ | ------------------------------------------ |
| Left   | ~490px | Questions panel                            |
| Right  | ~834px | Product image preview + live price display |

---

### 6.3 Left Panel — Product Field (Always First)

A special field that always appears at the top of the left panel — not part of the quote config questions.

```
[  3.15 × 3.15m Portable Building        ▼  ]  [Change]
    Pre-filled from ?product=ID — locked by default
```

**Change Button Flow:**

```
1. User clicks "Change"
2. Confirmation alert:
     "[Change Product Alert Text from settings Tab 3]"
     [Cancel]  [Yes, Change Product]

3a. Cancel
      → Alert closes
      → Field remains locked
      → Nothing changes

3b. Yes, Change Product
      → Field unlocks
      → Becomes a dropdown of all hc-containers products
        that have an active linked quote config
      → User selects a new product
      → URL updates: ?product=456
      → Page reloads with new quote config loaded
      → Product field pre-filled with new product, locked again
```

---

### 6.4 Left Panel — Questions

All questions from the linked `hc-quote-configs` entry render below the product field in the order defined in the admin.

**Rendering rules:**

- Conditional questions are hidden by default and only revealed when their trigger condition is met
- Required questions are marked with an asterisk (\*)
- Helper text renders as small note text below the question label
- Radio and dropdown questions allow only one selection at a time
- Checkbox questions allow multiple selections

---

### 6.5 Conditional Reveal — How It Works

> ⚠️ **This section is illustrative only.** The electrical question is used as a real-world example to explain how the conditional reveal pattern works. Nothing here is hardcoded — the plugin implements a fully generic conditional system that works identically for any question the admin creates. The developer should build a general-purpose reveal mechanism, not one with any knowledge of electrical questions specifically.

Any question marked **Is Conditional = true** in the admin will be hidden by default and only revealed when its **Show When** trigger is met — regardless of what the question is about.

The electrical question demonstrates this pattern clearly:

```
Tier 1 — Base Electrical (always visible)
  ○ No electrical required          → $0 added, Tier 2 stays hidden
  ● Install BASE electrical         → +$X added, reveals Tier 2 below

Tier 2 — Electrical Add-ons (hidden until Tier 1 = BASE electrical)
  □ Extra Power Points              → +$X
  □ Extra Lights                    → +$X
  □ External 10A Power Points       → +$X
  □ Data Ports                      → +$X

Standalone — always visible, not dependent on any other selection
  ○/● 15A Caravan Plug              → +$X / $0
  ○/● Generator Changeover Switch   → +$X / $0
```

In this example, Tier 2 questions are configured in the admin with:

- **Is Conditional:** true
- **Show When — Question:** "Do you require electrical to be installed?"
- **Show When — Option:** "Install BASE electrical"

The same pattern applies to any future question the client wants to make conditional on any other question and option combination.

---

### 6.6 Right Panel — Product Preview

| Element             | Behaviour                                                                                                                                                                                                            |
| ------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Product Image       | Updates based on Image Rule matching as selections change                                                                                                                                                            |
| Front / Back Toggle | Switches between front and back image for the current matched rule. Defaults to Front on load                                                                                                                        |
| Feature Pills       | 4 overlay chips showing the selected option label for each question marked **Show in Feature Pill = true** in the admin. Maximum 4 pills displayed — first 4 enabled questions in order. Updates on selection change |
| Price Label         | Static label: "Price:"                                                                                                                                                                                               |
| Live Price          | Running total displayed in large text. Recalculates on every option change without page reload                                                                                                                       |

---

## 7. Contact Information — Frame 2

Frame 2 sits directly below Frame 1 on the same page. The live total price is always visible in Frame 1's right panel. Frame 2 is the submission step — the customer must complete all required fields and check the consent checkbox before submitting.

---

### 7.1 Section Header

- **Heading:** "CONTACT INFORMATION"
- **Subheading:** Obligation-free notice explaining the team will be in touch to discuss the estimate

---

### 7.2 Form Fields (in order)

| #   | Field                     | Type                                                | Required | Notes                                                                                                                                                                                        |
| --- | ------------------------- | --------------------------------------------------- | -------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| 1   | First Name                | Text input                                          | ✅ Yes   |                                                                                                                                                                                              |
| 2   | Last Name                 | Text input                                          | ✅ Yes   |                                                                                                                                                                                              |
| 3   | Email                     | Email input                                         | ✅ Yes   | Validated as proper email format                                                                                                                                                             |
| 4   | Confirm Email             | Email input                                         | ✅ Yes   | Must match Email field exactly                                                                                                                                                               |
| 5   | Prefix                    | Dropdown                                            | No       | Options pulled from plugin settings Tab 4. Configurable by admin                                                                                                                             |
| 6   | Address Searcher          | Text + autocomplete                                 | No       | Google Places Autocomplete API. Restricted to countries defined in plugin settings Tab 1. On selection, auto-fills all address fields and resolves phone country prefix                      |
| 7   | Street Address            | Text input (read-only)                              | No       | Auto-filled from autocomplete result                                                                                                                                                         |
| 8   | Address Line 2            | Text input (read-only)                              | No       | Auto-filled from autocomplete result                                                                                                                                                         |
| 9   | City                      | Text input (read-only)                              | No       | Auto-filled from autocomplete result                                                                                                                                                         |
| 10  | State / Province / Region | Text input (read-only)                              | No       | Auto-filled from autocomplete result                                                                                                                                                         |
| 11  | ZIP / Postal Code         | Text input (read-only)                              | No       | Auto-filled from autocomplete result                                                                                                                                                         |
| 12  | Phone / Mobile            | Tel input                                           | No       | Country code prefix auto-set from resolved address country. Defaults to first country in plugin settings if no address entered. Helper text: "Please provide your BEST contact phone number" |
| 13  | Shipping Distance         | Text input (read-only)                              | No       | Auto-calculated via Google Distance Matrix API using warehouse coordinates (from settings) and resolved address coordinates. Displays in kilometres                                          |
| 14  | Google Map                | Embedded map                                        | —        | Displays a pin at the resolved address. Updates on each new address selection. Warehouse pin also shown as origin                                                                            |
| —   | Honeypot                  | Hidden text input (CSS hidden, not `type="hidden"`) | —        | Must be empty on submission. If populated (by a bot), submission is silently rejected server-side. Never shown to real users                                                                 |

> **Address fields are read-only.** If the customer wants to change their address, they use the Address Searcher field to select a new location. This ensures all address data is clean and geocoded.

> **Phone prefix flow:**
>
> ```
> Address resolves → country detected (e.g. Australia)
>   → Phone prefix auto-sets to +61
> Address changes to NZ → prefix updates to +64
> No address entered → prefix defaults to first country in settings
> ```

---

### 7.3 Summary & Submission

| Element                   | Type              | Notes                                                                                                            |
| ------------------------- | ----------------- | ---------------------------------------------------------------------------------------------------------------- |
| Total Cost                | Read-only display | Carried from Frame 1 live calculation                                                                            |
| Consent Checkbox          | Checkbox          | Required to submit. Label text from plugin settings Tab 3                                                        |
| Privacy Policy Fine Print | Collapsible text  | Shown on expand below consent checkbox. Content from plugin settings Tab 3                                       |
| Submit Button             | Full-width CTA    | Label from plugin settings Tab 3. Default: "Send my Estimate and Contact Request! ↗". Red background, white text |

---

### 7.4 Address Autocomplete → Field Population Flow

```
User types suburb or address in Address Searcher
        ↓
Google Places Autocomplete API fires → suggestions dropdown appears
        ↓
User selects a suggestion
        ↓
Google Places Details API returns:
  ├── Street number + street name  → Street Address field (read-only)
  ├── Suburb                       → Address Line 2 field (read-only)
  ├── City                         → City field (read-only)
  ├── State                        → State field (read-only)
  ├── Postcode                     → Postcode field (read-only)
  ├── Country                      → determines phone country prefix
  └── lat / lng coordinates        → used for Distance Matrix + Map embed
        ↓
Google Distance Matrix API called:
  Origin:      warehouse lat/lng (from plugin settings)
  Destination: resolved lat/lng from address
  Result:      distance in km → Shipping Distance field (read-only)
        ↓
Google Maps Embed:
  Updates pin to resolved address
  Warehouse pin remains as origin marker
        ↓
Phone field:
  Country prefix updated to match resolved country
```

---

## 8. Submission Flow

### 8.1 Frontend Validation

Before submission, client-side validation checks:

- First Name — not empty
- Last Name — not empty
- Email — valid email format, not empty
- Confirm Email — matches Email field exactly
- Consent checkbox — must be checked

Inline error messages appear next to each failing field. Submission is blocked until all pass.

---

### 8.2 Server-Side Processing

On a valid submission POST request:

1. **Verify WordPress nonce** — the quote builder form includes a nonce field generated server-side on page load. The submission endpoint verifies this nonce via `wp_verify_nonce()` before processing anything. Requests with missing or invalid nonces are rejected with a 403 response. This prevents CSRF attacks
2. **Check honeypot field** — the form includes a hidden honeypot field that must be empty on submission. If it contains any value (filled in by a bot), the submission is silently rejected and a false success response is returned to avoid alerting the bot
3. **Sanitise the `?product=` URL parameter** — cast to `absint()` before any database query. If the result is 0 or no matching post is found, reject the request
4. **Re-validate all required fields** server-side — never rely on client-side validation alone
5. **Sanitise all input data** — all text fields sanitised with `sanitize_text_field()`, email fields with `sanitize_email()`, URLs with `esc_url_raw()`
6. **Create a new `hc-quote-submissions` post** with all submission data stored as post meta
7. **Set submission status** to the first status key in plugin settings (default: `status_1`)
8. **Send Admin Notification Email**
9. **Send Customer Copy Email**
10. **Return a success response** to the frontend

---

### 8.3 Post-Submission UI

**On success:**

- Form is cleared
- A success message replaces the form:
  > _"Thank you! Your estimate request has been sent. One of our team members will be in touch with you shortly."_
- Live price in Frame 1 right panel remains visible

**On server failure:**

- An error message appears below the submit button
- All form data is preserved so the customer does not need to re-enter

---

## 9. Quote Submissions Admin View

### 9.1 Submissions List Table

The `hc-quote-submissions` list table shows all submissions with:

| Column            | Description                                                           |
| ----------------- | --------------------------------------------------------------------- |
| Customer Name     | Prefix + First Name + Last Name                                       |
| Email             | Clickable mailto link                                                 |
| Phone             | With country prefix                                                   |
| Product           | Linked product name                                                   |
| Total Estimate    | Formatted dollar value                                                |
| Shipping Distance | In kilometres                                                         |
| Status            | Colour-coded badge — New (blue) / Contacted (orange) / Closed (green) |
| Submitted         | Date and time                                                         |

Default sort: newest first. Filterable by Status.

---

### 9.2 Single Submission Detail View

Each submission opens as a clean, modern read-only admin view. The layout uses panels/cards. The only editable element is the Status field.

---

**Panel 1 — Customer Details**

```
┌─────────────────────────────────────────┐
│  CUSTOMER DETAILS                       │
├─────────────────────────────────────────┤
│  Name:     Mr John Smith                │
│  Email:    john@example.com             │
│  Phone:    +61 412 345 678              │
│  Address:  12 Example St                │
│            Brisbane QLD 4000            │
│            Australia                    │
└─────────────────────────────────────────┘
```

**Panel 2 — Quote Summary**

```
┌─────────────────────────────────────────┐
│  QUOTE SUMMARY                          │
├─────────────────────────────────────────┤
│  Product: 3.15×3.15m Portable Building  │
├─────────────────────────────────────────┤
│  SELECTED OPTIONS                       │
│                                         │
│  Assembly                               │
│  Please assemble                +$3,000 │
│                                         │
│  Air Conditioning                       │
│  1.6kW A/C Cooling Only           +$850 │
│                                         │
│  Kitchenette                            │
│  Please install a Kitchenette   +$1,200 │
│                                         │
│  15A Caravan Plug                       │
│  Please install 15A Caravan Plug  +$380 │
├─────────────────────────────────────────┤
│  Base Price                     $7,500  │
│  TOTAL ESTIMATE                $12,930  │
└─────────────────────────────────────────┘
```

**Panel 3 — Shipping**

```
┌─────────────────────────────────────────┐
│  SHIPPING                               │
├─────────────────────────────────────────┤
│  Delivery Address:                      │
│  12 Example St, Brisbane QLD 4000       │
│                                         │
│  Shipping Distance:  142 km             │
│  from warehouse to delivery address     │
└─────────────────────────────────────────┘
```

**Panel 4 — Submission Info & Status**

```
┌─────────────────────────────────────────┐
│  SUBMISSION INFO                        │
├─────────────────────────────────────────┤
│  Submitted:  21 Feb 2026, 10:43 AM      │
│                                         │
│  Status:  [ New ▼ ]  [Save Status]      │
└─────────────────────────────────────────┘
```

Status labels and colour coding are driven by the configurable labels in plugin settings Tab 4.

---

## 10. Product Page Logic

### 10.1 URL Parameter

The `hc-containers` single post template checks for a `?view=` URL parameter.

| Parameter                       | Display Mode                                                                   |
| ------------------------------- | ------------------------------------------------------------------------------ |
| `?view=product` or no parameter | Purchase view                                                                  |
| `?view=lease`                   | Lease view — falls back to purchase view if lease is disabled for this product |

---

### 10.2 Purchase View

| Element                           | Source                                                                                  |
| --------------------------------- | --------------------------------------------------------------------------------------- |
| Product image gallery             | Product Info tab — Product Images                                                       |
| Star rating + review count        | Product Info tab                                                                        |
| Flatpack price                    | Product Info tab — Product Price                                                        |
| Assembled price                   | Product Price + assembly add-on price from linked `hc-quote-configs`                    |
| Product description               | Product Info tab                                                                        |
| Features list with icons          | Product Info tab — Features repeater                                                    |
| Additional notes                  | Product Info tab                                                                        |
| "View full plan here" link        | Product Info tab — Plan Document                                                        |
| "Click here for shipping details" | Product Info tab — Shipping Details Link                                                |
| "Get a Custom Quote ↗" button     | Links to `/get-a-quote/?product={ID}`. Hidden if linked quote config status is Inactive |

---

### 10.3 Lease View

| Element                             | Source                                           |
| ----------------------------------- | ------------------------------------------------ |
| Product image gallery               | Product Info tab — Product Images                |
| Product title                       | Post title + "– LEASE" appended                  |
| Weekly price                        | Lease Info tab — Lease Price + Lease Price Label |
| Lease description                   | Lease Info tab — Lease Terms                     |
| Standard layout title + description | Lease Info tab                                   |
| Lease optional extras               | Lease Info tab — Lease Optional Extras repeater  |
| Categories                          | WordPress native taxonomy                        |
| "Enquire Now ↗" button              | Lease Info tab — Enquiry Button Label            |

> No quote builder is triggered from the lease view.

---

### 10.4 Landing Pages

The client manages two WordPress pages manually. The plugin does not create or manage these pages.

| Page                  | Shortcode           | Card links with |
| --------------------- | ------------------- | --------------- |
| Products Landing Page | `[hc_product_grid]` | `?view=product` |
| Lease Landing Page    | `[hc_lease_grid]`   | `?view=lease`   |

---

## 11. Pricing Logic

### 11.1 Base Price

Always the **Product Price** from the linked `hc-containers` CPT entry. Never duplicated in the quote config.

```
Base Price = hc-containers → Product Price (flatpack price)
```

### 11.2 Total Calculation

```
Quote Total = Base Price
            + SUM of all selected options where Price Type = Addition
            - SUM of all selected options where Price Type = Deduction
```

### 11.3 Live Update

Recalculates and updates in Frame 1's right panel on every option change. Handled entirely in vanilla JavaScript using price and price-type data attributes on rendered form elements. No page reload required.

### 11.4 Assembled Price on Product Page

```
Assembled Price = Base Price + price of the option with role = "assembly" in linked hc-quote-configs
```

The assembly option is identified by its **Option Role = `assembly`** flag, not by its label or position. Only one option per config can hold this role. If no option in the config has the `assembly` role, the assembled price is not displayed on the product page. Fetched dynamically when the product page loads.

---

## 12. Image Switching Logic

### 12.1 Tag Assignment

Options with **Affects Image = true** participate in image rule matching. A slug is auto-generated from the option label on **first save only** and then frozen — it never changes regardless of future label edits.

```
Option Label:  "Please install 1.6kW A/C Cooling Only"
Internal Slug: please_install_16kw_ac_cooling_only  ← immutable after first save
Admin sees:    "Please install 1.6kW A/C Cooling Only"  ← always human-readable
```

Renaming the option label updates the display everywhere in the admin but the slug — and therefore all image rule matching — is completely unaffected.

### 12.2 Active Tags

The system maintains a live list of **active tags** — slugs of all currently selected options that have Affects Image enabled.

### 12.3 Rule Matching

Rules evaluated from most specific (most match tags) to least specific. First match wins.

```
Active Tags: [please_assemble, please_install_16kw_ac, please_install_a_kitchenette]

Rule: [please_assemble, please_install_16kw_ac, please_install_a_kitchenette]
  → ✅ ALL PRESENT → USE THIS RULE → show its front/back images
```

**Tie-breaking:** If two rules have the same number of matching tags and both fully match the active tag set, the rule that appears **higher in the admin-defined order** wins. The admin controls this via drag-and-drop ordering in the Image Rules repeater (Section 5.2 — Section 3 of the quote config edit screen).

### 12.4 Best-Match Fallback

If no exact rule matches, the rule with the highest count of matching tags is used:

```
Active Tags: [please_assemble, please_install_22kw_ac, benchtop_with_sink]

No exact match found. Partial evaluation:
  Rule A: [please_assemble]                        → 1 match
  Rule B: [please_assemble, benchtop_with_sink]    → 2 matches ← best partial
  → Use Rule B images
```

If no partial match exists, **Default Fallback images** from the quote config are shown.

### 12.5 Front / Back Toggle

Each matched rule has a front and a back image. Toggle buttons switch between them without re-evaluating rules. Default on page load: **Front**.

### 12.6 Image State Matrix

| #   | Assembly  | A/C   | Kitchen         | Suggested Rule Name       |
| --- | --------- | ----- | --------------- | ------------------------- |
| 1   | Flatpack  | —     | —               | `flatpack`                |
| 2   | Assembled | None  | None            | `assembled_base`          |
| 3   | Assembled | None  | Kitchenette     | `assembled_kitchen`       |
| 4   | Assembled | None  | Benchtop & Sink | `assembled_benchtop`      |
| 5   | Assembled | 1.6kW | None            | `assembled_ac16`          |
| 6   | Assembled | 2.2kW | None            | `assembled_ac22`          |
| 7   | Assembled | 1.6kW | Kitchenette     | `assembled_ac16_kitchen`  |
| 8   | Assembled | 1.6kW | Benchtop & Sink | `assembled_ac16_benchtop` |
| 9   | Assembled | 2.2kW | Kitchenette     | `assembled_ac22_kitchen`  |
| 10  | Assembled | 2.2kW | Benchtop & Sink | `assembled_ac22_benchtop` |

**Maximum: 20 images per product** (10 states × front + back). Not all states require images — unused states fall through to best-match or default fallback.

---

## 13. Shortcodes

### 13.1 `[hc_product_grid]`

Renders a grid of all active `hc-containers` products in purchase view.

| Attribute  | Default | Description                  |
| ---------- | ------- | ---------------------------- |
| `columns`  | `3`     | Number of grid columns       |
| `limit`    | `-1`    | Number of products. -1 = all |
| `category` | `""`    | Filter by category slug      |
| `orderby`  | `date`  | date / title / price         |
| `order`    | `DESC`  | ASC or DESC                  |

Each card links to the product page with `?view=product`.

---

### 13.2 `[hc_lease_grid]`

Renders a grid of all `hc-containers` products where lease is enabled.

| Attribute  | Default | Description                  |
| ---------- | ------- | ---------------------------- |
| `columns`  | `3`     | Number of grid columns       |
| `limit`    | `-1`    | Number of products. -1 = all |
| `category` | `""`    | Filter by category slug      |
| `orderby`  | `date`  | date / title / price         |
| `order`    | `DESC`  | ASC or DESC                  |

Each card links to the product page with `?view=lease`.

---

### 13.3 `[hc_quote_builder]`

Renders the full quote builder interface — Frame 1 and Frame 2. Used on the dedicated quote builder page only.

| Attribute | Default  | Description                                            |
| --------- | -------- | ------------------------------------------------------ |
| `product` | from URL | Can be set directly or read from `?product=` URL param |

> Place this shortcode only on the one page configured in plugin settings Tab 3.

---

## 14. URL Parameter Reference

| Parameter   | Page                            | Values              | Effect                                                                                                                                                                                                                                       |
| ----------- | ------------------------------- | ------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `?view=`    | `hc-containers` single template | `product` / `lease` | Switches display mode. Defaults to `product` if omitted or if lease is disabled                                                                                                                                                              |
| `?product=` | Quote builder page              | `{post_id}`         | Loads the quote config for the specified `hc-containers` post ID. **Must be cast to `absint()` server-side before any database query.** Shows an error message if the result is 0, no matching post exists, or the linked config is inactive |

---

## 15. Database & Data Structure

All data stored as WordPress post meta. No custom database tables required.

### 15.1 `hc-containers` Meta Keys

| Meta Key                  | Type   | Description                          |
| ------------------------- | ------ | ------------------------------------ |
| `hcqb_short_description`  | string | Grid tagline                         |
| `hcqb_product_price`      | float  | Base/flatpack price                  |
| `hcqb_rating`             | float  | Star rating                          |
| `hcqb_review_count`       | int    | Number of reviews                    |
| `hcqb_product_images`     | array  | Attachment IDs                       |
| `hcqb_features`           | array  | Repeater: icon attachment ID + label |
| `hcqb_additional_notes`   | string | Plain text                           |
| `hcqb_plan_document`      | int    | Attachment ID (PDF)                  |
| `hcqb_shipping_link`      | string | URL                                  |
| `hcqb_lease_enabled`      | bool   | 0 or 1                               |
| `hcqb_lease_price`        | float  | Weekly lease price                   |
| `hcqb_lease_price_label`  | string | e.g. "/Week"                         |
| `hcqb_lease_terms`        | string | Rich text                            |
| `hcqb_lease_layout_title` | string | Layout section heading               |
| `hcqb_lease_layout_desc`  | string | Rich text                            |
| `hcqb_lease_extras`       | array  | Repeater: label + weekly price       |
| `hcqb_enquiry_btn_label`  | string | Lease CTA label                      |

---

### 15.2 `hc-quote-configs` Meta Keys

| Meta Key                   | Type   | Description                                   |
| -------------------------- | ------ | --------------------------------------------- |
| `hcqb_linked_product`      | int    | Post ID of linked `hc-containers` entry       |
| `hcqb_config_status`       | string | `active` / `inactive`                         |
| `hcqb_questions`           | array  | Full questions + options structure (see 15.4) |
| `hcqb_image_rules`         | array  | Image rules structure (see 15.5)              |
| `hcqb_default_front_image` | int    | Attachment ID                                 |
| `hcqb_default_back_image`  | int    | Attachment ID                                 |

---

### 15.3 `hc-quote-submissions` Meta Keys

| Meta Key                    | Type   | Description                                                                                                                                                                                             |
| --------------------------- | ------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `hcqb_linked_product_id`    | int    | `hc-containers` post ID at time of submission                                                                                                                                                           |
| `hcqb_product_name`         | string | Snapshot of product name at submission time                                                                                                                                                             |
| `hcqb_base_price`           | float  | Base price at submission time                                                                                                                                                                           |
| `hcqb_selected_options`     | array  | Each entry: question label + option label + price + price type                                                                                                                                          |
| `hcqb_total_price`          | float  | Final calculated total                                                                                                                                                                                  |
| `hcqb_prefix`               | string | e.g. "Mr"                                                                                                                                                                                               |
| `hcqb_first_name`           | string |                                                                                                                                                                                                         |
| `hcqb_last_name`            | string |                                                                                                                                                                                                         |
| `hcqb_email`                | string |                                                                                                                                                                                                         |
| `hcqb_phone`                | string | Includes country prefix                                                                                                                                                                                 |
| `hcqb_address_street`       | string |                                                                                                                                                                                                         |
| `hcqb_address_line2`        | string |                                                                                                                                                                                                         |
| `hcqb_address_city`         | string |                                                                                                                                                                                                         |
| `hcqb_address_state`        | string |                                                                                                                                                                                                         |
| `hcqb_address_postcode`     | string |                                                                                                                                                                                                         |
| `hcqb_address_country`      | string |                                                                                                                                                                                                         |
| `hcqb_shipping_distance_km` | float  | Calculated distance in km                                                                                                                                                                               |
| `hcqb_submission_status`    | string | Stores the **stable internal key** from the configured status labels (e.g. `status_1`, `status_2`). Never stores the label text directly. Display label is resolved at render time from plugin settings |
| `hcqb_submitted_at`         | string | ISO 8601 datetime                                                                                                                                                                                       |

---

### 15.4 Questions Array Structure

```json
[
  {
    "label": "Do you require Air Conditioning?",
    "key": "do_you_require_air_conditioning",
    "input_type": "radio",
    "required": false,
    "helper_text": "",
    "is_conditional": false,
    "show_when_question": "",
    "show_when_option": "",
    "options": [
      {
        "label": "Air-con not required",
        "slug": "air_con_not_required",
        "price": 0,
        "price_type": "addition",
        "affects_image": false,
        "default_selected": true
      },
      {
        "label": "Please install 1.6kW A/C Cooling Only",
        "slug": "please_install_16kw_ac_cooling_only",
        "price": 850.0,
        "price_type": "addition",
        "affects_image": true,
        "default_selected": false
      },
      {
        "label": "Please install 2.2kW A/C Heating and Cooling",
        "slug": "please_install_22kw_ac_heating_and_cooling",
        "price": 1100.0,
        "price_type": "addition",
        "affects_image": true,
        "default_selected": false
      }
    ]
  },
  {
    "label": "Do you require Extra Power Points?",
    "key": "do_you_require_extra_power_points",
    "input_type": "radio",
    "required": false,
    "helper_text": "",
    "is_conditional": true,
    "show_when_question": "do_you_require_electrical",
    "show_when_option": "please_install_the_base_electrical",
    "options": []
  }
]
```

---

### 15.5 Image Rules Array Structure

```json
[
  {
    "rule_name": "Assembled + 1.6kW A/C + Kitchenette",
    "match_tags": [
      "please_assemble",
      "please_install_16kw_ac_cooling_only",
      "please_install_a_kitchenette"
    ],
    "front_image": 201,
    "back_image": 202
  },
  {
    "rule_name": "Assembled + 1.6kW A/C + Benchtop",
    "match_tags": [
      "please_assemble",
      "please_install_16kw_ac_cooling_only",
      "please_install_benchtop_with_sink"
    ],
    "front_image": 203,
    "back_image": 204
  }
]
```

---

### 15.6 Plugin Settings Structure

Stored as a single serialised WordPress option: `hcqb_settings`

```json
{
  "warehouse_address": "123 Example Rd, Bundaberg QLD 4670",
  "warehouse_lat": -24.8661,
  "warehouse_lng": 152.3489,
  "google_maps_api_key": "AIza...",
  "supported_countries": ["AU", "NZ"],
  "admin_notification_email": "sales@hccontainers.com.au",
  "email_from_name": "HC Containers Quote Builder",
  "email_from_address": "noreply@hccontainers.com.au",
  "admin_email_subject": "New Quote Request — {product_name} — {customer_name}",
  "customer_email_subject": "Your HC Containers Quote Estimate — {product_name}",
  "customer_email_intro": "Thank you for your enquiry...",
  "company_name": "HC Containers",
  "company_phone": "(07) 4153 1700",
  "company_website": "https://hccontainers.com.au",
  "quote_builder_page_id": 42,
  "change_product_alert_text": "Changing the product will reload the quote builder and your current selections will be lost. Do you want to continue?",
  "submit_button_label": "Send my Estimate and Contact Request!",
  "consent_checkbox_text": "I agree to the privacy policy and I consent to be contacted regarding this enquiry.",
  "privacy_fine_print": "By submitting this form, you hereby agree to our Privacy Policy...",
  "prefix_options": ["Mr", "Mrs", "Ms", "Dr"],
  "submission_status_labels": [
    { "key": "status_1", "label": "New" },
    { "key": "status_2", "label": "Contacted" },
    { "key": "status_3", "label": "Closed" }
  ]
}
```

---

## 16. User Flows

### 16.1 Purchase & Quote Flow

```
Products Landing Page
  └── User browses [hc_product_grid]
      └── Clicks a product card
          └── Product Page (?view=product)
              ├── Views product details, pricing, images
              └── Clicks "Get a Custom Quote ↗"
                  └── Quote Builder Page (?product=123)
                      ├── Frame 1
                      │   ├── Product field pre-filled + locked
                      │   ├── User works through questions
                      │   ├── Live price updates in right panel
                      │   └── Product image updates based on selections
                      └── Frame 2
                          ├── Fills First Name, Last Name, Email,
                          │   Confirm Email (required)
                          ├── Optionally: Prefix, Address, Phone
                          ├── Address autocomplete fires on selection
                          │   ├── Address fields auto-filled (read-only)
                          │   ├── Map pin updates
                          │   ├── Shipping distance calculated
                          │   └── Phone prefix auto-set from country
                          ├── Checks consent checkbox
                          └── Clicks Submit
                              ├── Client-side validation passes
                              ├── Server-side validation passes
                              ├── Submission saved to hc-quote-submissions
                              ├── Admin notification email sent
                              ├── Customer copy email sent
                              └── Success message displayed
```

---

### 16.2 Lease Flow

```
Lease Landing Page
  └── User browses [hc_lease_grid]
      └── Clicks a product card
          └── Product Page (?view=lease)
              ├── Views lease price, terms, layout info
              ├── Reviews optional lease extras
              └── Clicks "Enquire Now ↗"
                  └── Enquiry/contact page (external to plugin)
```

---

### 16.3 Product Change Flow (Quote Builder)

```
Quote Builder loaded — Product A (?product=123)
  └── User clicks "Change" on product field
      └── Confirmation alert appears
          ├── [Cancel]
          │   └── Alert closes, field stays locked, no change
          └── [Yes, Change Product]
              └── Field unlocks
                  └── Dropdown shows all products with active configs
                      └── User selects Product B
                          └── URL updates to ?product=456
                              └── Page reloads
                                  └── Product B config loads
                                      └── Field pre-filled with Product B, locked
```

---

## 17. Email Templates

### 17.1 Admin Notification Email

```
Subject: New Quote Request — [Product Name] — [Prefix] [First Name] [Last Name]

─────────────────────────────────────────
NEW QUOTE SUBMISSION
─────────────────────────────────────────

CUSTOMER DETAILS
Name:     [Prefix] [First Name] [Last Name]
Email:    [Email]
Phone:    [Phone with country prefix]
Address:  [Full resolved address]

─────────────────────────────────────────
QUOTE SUMMARY
Product:  [Product Name]

Selected Options:
  [Question Label]
  [Option Label]                    +$[Price]

  [Question Label]
  [Option Label]                    -$[Price]

  ...

Base Price:                          $[Base Price]
─────────────────────────────────────────
TOTAL ESTIMATE:                     $[Total]
─────────────────────────────────────────

SHIPPING
Delivery Address: [Full address]
Shipping Distance: approximately [X] km from warehouse

Submitted: [Date and Time]

─────────────────────────────────────────
View full submission in WordPress:
[Link to hc-quote-submissions admin detail view]
```

---

### 17.2 Customer Copy Email

```
Subject: Your HC Containers Quote Estimate — [Product Name]

[Customer Email Intro Text from settings]

Hi [Prefix] [Last Name],

Thank you for your enquiry. Here is a summary of your estimate:

─────────────────────────────────────────
QUOTE SUMMARY
─────────────────────────────────────────
Product: [Product Name]

Selected Options:
  [Question Label]
  [Option Label]                    +$[Price]

  ...

Base Price:                          $[Base Price]
─────────────────────────────────────────
TOTAL ESTIMATE:                     $[Total]
─────────────────────────────────────────

SHIPPING
Delivery Address: [Full address]
Estimated Distance: approximately [X] km
Final shipping cost will be confirmed in your formal quote.

─────────────────────────────────────────
This estimate is OBLIGATION FREE.
One of our team members will be in touch shortly
to discuss your enquiry. You are under no obligation
to proceed with any purchase.
─────────────────────────────────────────

[Company Name]
[Company Phone]
[Company Website]
```

---

## 18. Open Items & Pending Confirmations

| #   | Item                                                                                               | Impact                                                       |
| --- | -------------------------------------------------------------------------------------------------- | ------------------------------------------------------------ |
| 1   | Base prices for all container sizes                                                                | Required to populate initial `hc-containers` product entries |
| 2   | Prices for all electrical sub-options (Extra Power Points, Extra Lights, External 10A, Data Ports) | Required to complete question options in quote config        |
| 3   | Final wording for all question and option labels                                                   | Required before frontend build                               |
| 4   | Product images per combination state — up to 20 per product (front + back)                         | Required before image rules can be completed by client       |
| 5   | Confirmation of which options trigger image changes beyond Assembly, A/C, and Kitchen              | Affects number of image rules and "Affects Image" flags      |
| 6   | Does Sliding Glass Door selection affect the product image?                                        | Affects image rule count                                     |
| 7   | Does 2.2kW A/C require a different image from 1.6kW A/C?                                           | Affects image rule count                                     |
| 8   | Enquiry form/page destination for lease "Enquire Now" CTA                                          | Required to complete lease flow wiring                       |
| 9   | Should "Get a Custom Quote" be hidden or visually disabled when quote config is Inactive?          | UX decision                                                  |
| 10  | Full list of supported countries for address autocomplete and phone prefix                         | Required for plugin settings configuration                   |
| 11  | Warehouse address for shipping distance calculation                                                | Required for plugin settings configuration                   |
| 12  | Company details for customer email footer (name, phone, website)                                   | Required for email template                                  |

---

_Document version 1.2 — incorporates developer review feedback: slug immutability enforced after first save, submission status keys decoupled from display labels, Option Role field added for assembly price identification, single-level conditional constraint documented as v1.0 constraint, image rule tie-breaking via admin drag-order, Show in Feature Pill toggle added to questions, Google Maps API key restriction documented as required deployment step, WordPress nonce verification and honeypot spam protection added to submission flow, absint() sanitisation documented for ?product= URL parameter._
