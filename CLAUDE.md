# Member Directory — Claude Code Context

## Workflow Rule
**Always commit and push after every completed change.** Do not wait for the user to ask — commit and push as soon as the work is done.

## Project Summary
WordPress plugin: section-based member profile and directory system powered by ACF Pro. Members own a CPT post (`member-directory`); each post renders sections (edit or view mode) with per-field PMP (Public/Member/Private) visibility control. No CPT UI, no Gutenberg — purely ACF Pro + PHP.

## Build Status

### Complete
- `SectionRegistry` — JSON→DB sync (immutable pointers); runtime DB cache; mutable metadata in DB only
- `TemplateLoader` — routes `member-directory` single/archive to plugin templates
- `AdminSync` — admin page that triggers `SectionRegistry::sync()`; section editor UI (rename, reorder, toggle primary, toggle always_on, default avatar, delete); Add Section form; collapsible Plugin Guide panel with instructions for all major features
- `PmpResolver` — PMP waterfall resolution + viewer context + view-as spoofing
- `FieldRenderer` — field-to-HTML rendering for view mode (text, textarea, url, wysiwyg, image, gallery, file, google_map, true_false, checkbox, radio, taxonomy, select). Images/galleries render with GLightbox links + `<figcaption>` captions.
- `GlobalFields` — ACF group for global PMP + primary section controls (**⚠ debug code present — see Known Issues**)
- `AcfFormHelper` — `acf_form_head()` guard + edit-mode detection + `acf_form()` rendering + AJAX handlers (section save, enabled toggle, section PMP, field PMP, avatar upload, image upload/delete, gallery upload/remove, caption update, taxonomy search, social import)
- `templates/single-member-directory.php` — full edit/view mode branching
- `templates/parts/section-edit.php` — edit partial (left controls panel + ACF form)
- `templates/parts/section-view.php` — view partial (PMP waterfall + FieldRenderer per field)
- `templates/parts/right-panel.php` — author/admin panel: View As button group, Global Default block, Primary Section block, Section toggles (edit mode), Notes block
- `templates/parts/header-section.php` — generic data-driven sticky header (scans for ACF tab with "header" in label; maps fields to slots: text→title, image→avatar, taxonomy→badges, url→social icons). Edit-mode fallbacks: "Edit Quick Focus" and "Add Links" placeholder text when badges/socials are empty
- `templates/parts/pill-nav.php` — pill navigation row; All Sections + per-section pills (navigation only; enable/disable toggles live in right panel)
- Custom image/gallery uploaders — "image in, image out" pattern for all image and gallery fields in edit mode. Replaces ACF's native media library UI with inline upload/remove buttons + caption inputs. Old attachments auto-deleted on replace/remove. Galleries use thumbnail grid with per-image captions.
- GLightbox integration — view-mode images and galleries open in a lightbox with captions. Galleries support prev/next navigation. Initialized via `initLightbox()` in JS boot sequence. GLightbox 3.3.0 loaded from jsDelivr CDN.
- Header editing system — per-element pencil/camera overlays with mini-modals:
  - Avatar modal (upload + delete photo via AJAX)
  - Name modal (inline text editing)
  - Categories modal (custom taxonomy search replacing select2, AJAX-backed)
  - Social Links modal (inline URL editing + import from other primary sections)
- Section PMP controls — 4-state button group in edit mode left panel (inherit/public/member/private)
- Field PMP controls — per-field visibility buttons injected into edit mode
- Global PMP controls — right panel buttons wired with AJAX save
- Custom taxonomy search — replaces ACF's select2 with debounced AJAX search UI for all taxonomy fields in edit mode. Supports both single-select (one badge) and multi-select (badge pills with × remove). Applied globally via `initTaxonomySearch()` boot function; header modal taxonomy fields initialized separately by `initHeaderEditing()`. `getHeaderFieldKeys()` guard prevents double-init. Multi-select fields include a "Browse all" link that opens a checkbox modal with the full alphabetical term list (up to 200 terms via `browse_all` AJAX flag).
- Social link import — cross-section import for primary-capable sections (matched by URL field suffix)
- `TrustNetwork` — first non-ACF, code-driven section. Custom DB table `{prefix}memdir_trust_network` for trusted repair partner relationships. Builder→luthier request/accept/decline flow. Enabled state via post meta `_memdir_trust_enabled`. Batch profile resolution via `resolve_profiles()` / `resolve_post_profiles()`. Hard-coded Trust pill in pill-nav + Trust toggle in right panel (distinguished by `data-trust-toggle="1"` attribute). Ghost logic: section hidden in view mode when disabled. JS `initTrustNetwork()` handles action buttons + toggle.
- `Onboarding` — `[memdir_onboarding]` shortcode for self-service member creation. Redirect funnel: existing members → profile, new members → form (primary section radio + URL slug text input). Post-create: sets primary, enables always_on + primary sections, disables rest, redirects to profile in edit mode with primary pill active. Logged-out users handled by BuddyBoss login redirect. Inline CSS scoped to `.memdir-onboarding`.
- AJAX handlers wired:
  - `md_save_section` → `AcfFormHelper::handle_ajax_save`
  - `memdir_ajax_save_section_enabled` → `AcfFormHelper::handle_save_section_enabled`
  - `memdir_ajax_save_section_pmp` → `AcfFormHelper::handle_save_section_pmp`
  - `memdir_ajax_save_field_pmp` → `AcfFormHelper::handle_save_field_pmp`
  - `memdir_ajax_upload_avatar` → `AcfFormHelper::handle_avatar_upload`
  - `memdir_search_taxonomy_terms` → `AcfFormHelper::handle_search_taxonomy_terms`
  - `memdir_ajax_import_social` → `AcfFormHelper::handle_import_social`
  - `memdir_ajax_upload_image` → `AcfFormHelper::handle_image_upload`
  - `memdir_ajax_delete_image` → `AcfFormHelper::handle_delete_image`
  - `memdir_ajax_gallery_upload` → `AcfFormHelper::handle_gallery_upload`
  - `memdir_ajax_gallery_remove` → `AcfFormHelper::handle_gallery_remove`
  - `memdir_ajax_update_caption` → `AcfFormHelper::handle_update_caption`
  - `memdir_ajax_save_primary_section` → `GlobalFields::handle_save_primary_section`
  - `memdir_ajax_save_global_pmp` → `GlobalFields::handle_save_global_pmp`
  - `memdir_ajax_trust_request` → `TrustNetwork::handle_request`
  - `memdir_ajax_trust_respond` → `TrustNetwork::handle_respond`
  - `memdir_ajax_trust_cancel` → `TrustNetwork::handle_cancel`
  - `memdir_ajax_trust_remove` → `TrustNetwork::handle_remove`
  - `memdir_ajax_trust_toggle` → `TrustNetwork::handle_toggle`

### Not Started / Scaffold Only
- `includes/DirectoryQuery.php` — 🔜 not created yet
- `templates/archive-member-directory.php` — placeholder `<div>` only
- `templates/parts/sidebar.php` — not created
- `templates/parts/directory-card.php` — not created

## Architecture Rules — Never Violate

1. **`acf_form_head()` before any HTML.** `AcfFormHelper::maybe_render_form_head()` must be the first call in `single-member-directory.php`, before `get_header()`. Without it, `acf_form()` silently fails.

2. **ACF hook timing guard.** ACF fires `acf/init` inside its own `plugins_loaded:10` callback. Because ACF (`a`) loads before our plugin (`m`) alphabetically, our `plugins_loaded:10` runs *after* `acf/init` has already fired. Pattern used everywhere:
   ```php
   if ( did_action( 'acf/init' ) ) {
       self::register();  // already fired — call directly
   } else {
       add_action( 'acf/init', [ self::class, 'register' ] );
   }
   ```

3. **ACF is the field source of truth.** Field groups live entirely in ACF's own database. The plugin never registers, overrides, or caches field groups — no `acf-json/` folder, no `load_json`/`save_json` hooks. Templates call `acf_get_fields( $section['acf_group_key'] )` directly. Editing a field group in ACF admin and clicking Save is all that's needed — changes are live on the next page load.

4. **Section JSON files are immutable registration-only pointers (gitignored).** `sections/*.json` contains only `acf_group_key`. The section key is derived from the filename. Mutable metadata (`label`, `can_be_primary`, `always_on`, `default_avatar`, order) lives in the `member_directory_sections` DB option only, managed through the AdminSync UI. JSON files are never written back to after creation. Field definitions live in ACF's database only. **The `sections/` directory is gitignored** — section pointers are created per-environment via AdminSync or manually.

5. **PMP waterfall order: field → section → global.** `PmpResolver::can_view()` receives all three levels. Author and admin always see everything. Ghost behavior: hidden fields/sections render zero HTML — no empty wrappers.

6. **Static classes, static `init()` entry points.** All classes use only static methods. Each class has a `static init()` called from `Plugin::init()`. No singletons, no `new`.

7. **No closing PHP tags in partials.** Prevents accidental whitespace before HTTP headers.

8. **Namespace `MemberDirectory` everywhere.** All includes use `namespace MemberDirectory;` and `use` statements at the top of each consumer file.

## File Structure

```
member-directory.php              Entry point. ACF dependency check. Boots Plugin on plugins_loaded.
member-directory-architecture.html Primary design reference. Read this when starting work on any new feature.
frontend-layout.md                Frontend layout documentation.
acf-location-import.json          ACF location field import/reference data.
.gitignore                        Ignores sections/, *.log, node_modules/.
includes/
  Plugin.php                  Bootstrap. Registers CPT + hooks. Calls each class init().
                              enqueue_assets() passes mdAjax to JS (ajaxurl, nonce,
                              search_nonce, socialSources, currentUserId). Dequeues
                              conflicting scripts (elementor, buddypress).
  SectionRegistry.php         Section metadata store. sync() = sections/*.json → merge with DB option.
                              JSON files are immutable (acf_group_key only); mutable metadata
                              (label, can_be_primary, always_on, default_avatar, order) in DB only.
                              load_from_db() = DB option → in-memory cache.
                              Public API: get_sections(), get_section(), update_section_meta(),
                              validate_for_upload(), removed_content_keys() (always []),
                              is_system_field().
                              System field detection: SKIP_TYPES (button_group), SKIP_KEY_PATTERNS
                              (_enabled, _privacy_mode, _privacy_level, _pmp_, _display_precision).
  GlobalFields.php            ACF group: global_pmp + primary_section. ⚠ Has temporary debug code.
  AcfFormHelper.php           maybe_render_form_head(), is_edit_mode(), render_edit_form().
                              acf_form() scoped to content field keys from acf_get_fields().
                              AJAX: section save, enabled toggle, section PMP, field PMP,
                              avatar upload, image upload/delete, gallery upload/remove,
                              caption update, taxonomy term search, social link import.
                              Helpers: get_header_fields(), get_social_suffix(),
                              section_has_social_data().
  AdminSync.php               Admin page + nonce-protected handler that calls SectionRegistry::sync().
                              Section editor UI: rename label, reorder, toggle can_be_primary,
                              toggle always_on, default avatar upload/remove, delete.
                              Add Section form for creating new section pointers inline.
                              Backs up deleted section JSON files to sections/backups/.
                              All mutable metadata operations are DB-only — no JSON file writes.
                              Collapsible Plugin Guide panel: quick start, sections, flags,
                              header auto-detection, conditional tabs, PMP, trust, onboarding.
  TemplateLoader.php          template_include filter → plugin templates for member-directory CPT.
  PmpResolver.php             resolve_viewer(), spoof_viewer(), can_view() (waterfall), is_member().
  FieldRenderer.php           render() — field definition + post_id → escaped HTML output.
                              format_location() — Google Maps by display_precision level.
                              Images/galleries wrapped in <figure> with GLightbox <a> links,
                              data-description for lightbox captions, <figcaption> inline.
  TrustNetwork.php            Trust Network — first non-ACF code-driven section.
                              Custom DB table {prefix}memdir_trust_network.
                              Static class: init(), install_table(), is_trust_enabled(),
                              get_trusting_builders(), get_trusted_by_user(),
                              get_pending_requests(), get_relationship(),
                              resolve_profiles(), resolve_post_profiles().
                              5 AJAX handlers: handle_request/respond/cancel/remove/toggle.
                              Enabled state: post meta _memdir_trust_enabled (default: off).
  Onboarding.php              [memdir_onboarding] shortcode. Redirect funnel for member
                              creation. Logged-out → BuddyBoss login. Existing member →
                              redirect to profile. New member → form (primary section radio
                              + URL slug text input). Post-create: sets primary, enables
                              always_on + primary sections, disables rest, redirects to
                              profile in edit mode. Inline CSS, no JS dependency.
  DirectoryQuery.php          🔜 Not yet created.
sections/                         ⚠ GITIGNORED — not tracked in git. Created per-environment.
  *.json                      Immutable section pointers. { acf_group_key }. Key from filename.
                              Current sections on dev: profile, discovery, business, location.
templates/
  single-member-directory.php Single profile. Calls form_head first, then branches edit/view
                              per section. Pre-fetches $cached_acf_fields, $all_post_meta,
                              $global_pmp for perf and passes to child partials.
                              View mode uses ob_start() + $section_field_count to ghost
                              sections where all fields are hidden by PMP.
  archive-member-directory.php Scaffold only — no real implementation.
  parts/
    header-section.php        Generic sticky header. Scans ACF fields for a tab with "header"
                              in label; maps fields to slots by type (text→title, image→avatar,
                              taxonomy→badges, url→social icons). Fallback avatar from
                              section default_avatar. Inline SVG icons for 9 social platforms
                              (website, linkedin, instagram, twitter, facebook, youtube,
                              tiktok, vimeo, linktree). Location section special-case:
                              pulls google_map field + display_precision.
    pill-nav.php              Pill navigation. All Sections pill + one pill per section (nav only;
                              enable/disable toggles in right-panel.php). Hard-coded Trust pill
                              appended after the SectionRegistry loop.
    trust-network.php         Trust Network section partial. Non-ACF code-driven. View mode:
                              trusted-by cards, request button (state-dependent), outbound
                              network. Edit mode: pending requests with accept/decline, accepted
                              relationships with remove, outbound list. Ghost logic when disabled.
    section-edit.php          Edit partial. Left controls (section PMP buttons, tab list, save button) + ACF form.
                              Tab list derived from acf_get_fields( $section['acf_group_key'] ).
    section-view.php          View partial. Resolves PMP waterfall per field, calls FieldRenderer.
                              Field list derived from acf_get_fields( $section['acf_group_key'] ).
    right-panel.php           Author/admin panel. View As buttons, Global Default block,
                              Primary Section block, Section toggles (edit mode only).
                              Hard-coded Trust toggle (data-trust-toggle="1") appended
                              after the SectionRegistry toggle loop.
assets/
  css/memdir.css              All plugin styles. Scoped to .memdir-profile. Includes modal,
                              header editing, taxonomy search, import button, PMP control,
                              image upload, gallery upload, figure/caption, and lightbox styles.
                              CSS vars redeclared on dialog.memdir-header-modal for portaled dialogs.
                              Trust network styles: .memdir-trust-* (block, list, card, btn, badge).
  js/memdir.js                All frontend JS. ⚠ CRLF line endings — use Write tool or Node.js
                              scripts for edits (Edit tool fails on this file).
                              Boot sequence: initHeaderEditing() → initImageUploaders() →
                              initTaxonomySearch() → initLightbox() → initTrustNetwork().
tools/
  acf-field-prep.md           Claude skill: enrich a bare ACF field group export with full
                              iPMP apparatus (section system fields + per-field PMP companions)
                              and validate header tab structure. Single skill for all section prep.
  patch-memdir.js             Node.js patching utility for memdir.js (handles CRLF issues).
  patch-trust.js              Node.js patch script — adds initTrustNetwork() to memdir.js.
docs/
  js-behavior.md              Frontend JS behavior documentation.
  pmp-system.md               PMP (visibility) system documentation.
  plan-image-upload-cleanup.md Plan for image upload system refactoring.
```

## Workflow: Sections

### Add a new section
1. Build the field group in ACF admin → click Save (ACF saves to its own DB)
2. Create `sections/key.json` with just `{ "acf_group_key": "group_..." }` on the server, then use the **Add Section** form in WP Admin → Member Directory Sync, or run Sync to pick up the new JSON file
3. Rename, toggle can_be_primary, toggle always_on, and reorder via the Section Editor UI (DB-only, no file writes)
4. Both edit and view surfaces are live

### Modify fields (add, remove, rename, reorder tabs)
1. Edit the field group in ACF admin → click Save
2. Done — ACF saves to its DB, next page load both surfaces reflect the change. No sync needed.

### Modify section metadata (label, order, can_be_primary, always_on, default_avatar)
- Use the AdminSync UI controls (rename, reorder arrows, checkbox toggle, avatar upload) — all DB-only, no file writes needed

### Delete a section
1. Delete or deactivate the field group in ACF admin
2. Delete the section via the AdminSync UI

### Git/deploy flow
- `sections/` is **gitignored** — section JSON pointers are created per-environment
- ACF field groups live in the database only — no JSON files in the plugin
- After `git pull` on a new server: manually create `sections/*.json` files, then run AdminSync
- Field groups must be created/edited in ACF admin on each environment (or use ACF's own import/export)

## Coding Conventions

- PHP 8.0+, typed properties and return types throughout
- `defined( 'ABSPATH' ) || exit;` at top of every file
- `sanitize_text_field( wp_unslash( $_GET['...'] ) )` for all `$_GET` reads
- `esc_html()` / `esc_attr()` on all HTML output
- ACF field names follow pattern: `member_directory_{section_key}_{purpose}` (e.g. `member_directory_profile_name`)
- ACF group keys: `group_md_{nn}_{section_key}` (e.g. `group_md_02_profile`)
- ACF field keys: `field_md_{section_key}_{purpose}` (e.g. `field_md_profile_name`)

## ACF Field Naming Conventions

| Purpose | ACF name pattern | ACF key pattern |
|---------|-----------------|-----------------|
| Content field | `member_directory_{section}_{suffix}` | `field_md_{section}_{suffix}` |
| Section enabled toggle | `member_directory_{section}_enabled` | `field_md_{section}_enabled` |
| Section PMP (4-state) | `member_directory_{section}_privacy_mode` | `field_md_{section}_privacy_mode` |
| Per-field PMP companion | `member_directory_field_pmp_{section}_{suffix}` | `field_md_{section}_pmp_{suffix}` |
| Global PMP | `member_directory_global_pmp` | `field_md_global_pmp` |
| Primary section | `member_directory_primary_section` | `field_md_primary_section` |
| Display precision | `member_directory_{section}_display_precision` | `field_md_{section}_display_precision` |

**Note on field PMP companion lookup in `section-view.php`:**
```php
// Correct — strips 'member_directory_' prefix from the content field's name:
$field_name_suffix = preg_replace( '/^member_directory_/', '', $field['name'] );
$field_pmp = get_field( 'member_directory_field_pmp_' . $field_name_suffix, $post_id );
// e.g. field name  member_directory_business_name
//      companion   member_directory_field_pmp_business_name
```

## PMP System

### 4-State Section PMP
Each section has a single `privacy_mode` ACF field (type: `button_group`) with four values:
- `inherit` — defer to global PMP (the default; stored as missing/null resolves to inherit)
- `public`  — everyone sees this section
- `member`  — logged-in users only
- `private` — author and admin only

**Read:** `get_field( 'member_directory_{section_key}_privacy_mode', $post_id ) ?: 'inherit'`
**Write (AJAX):** `update_field( 'field_md_{section_key}_privacy_mode', $pmp, $post_id )`

### Per-Field PMP Companions
Every content field has a companion `button_group` field with 4 choices:
- `inherit` — defer to section PMP (default)
- `public / member / private` — explicit override

**ACF name:** `member_directory_field_pmp_{section}_{suffix}`
**ACF key:** `field_md_{section}_pmp_{suffix}`
**Type:** `button_group` — excluded from content field loops by `SectionRegistry::is_system_field()` (matches `SKIP_TYPES` for `button_group` and `SKIP_KEY_PATTERNS` for `_pmp_` substring)

### Waterfall Resolution
```
field_pmp → section_pmp → global_pmp
```
Any level set to `inherit` passes through to the next. Global is always explicit. `PmpResolver::can_view()` is the single authoritative check.

## Conditional Tabs

ACF tab labels can include a `[if:section_key]` marker to make the tab and all its fields conditional on another section being enabled.

### Convention
Tab label: `Storefront [if:business]`
- The `[if:business]` part is parsed and stripped from the display label
- When the Business section is disabled for the post, the entire tab (button + fields) is hidden
- When enabled, the tab appears normally with label "Storefront"

### How it works
All three rendering paths detect the marker using the same walk-and-flag pattern:
1. **`section-edit.php`** — Parses tab labels during field group building. Disabled conditional tabs are excluded from tab buttons, field PMP data, and the `acf_form()` call (via `$conditional_excluded_keys` passed to `render_edit_form()`).
2. **`section-view.php`** — Scans for conditional tabs after the header tab scan. Excluded field keys are added to the content filter alongside header field keys.
3. **`AcfFormHelper::render_edit_form()`** — Accepts optional `$excluded_keys` parameter from the edit template to filter fields out of the ACF form.

### Condition check
```php
$ref_enabled = get_field( 'member_directory_' . $ref_key . '_enabled', $post_id );
$is_active   = ! empty( $ref_enabled ); // false/0/null → not active
```
The `_enabled` field defaults to `1` (enabled), so a section that has never been toggled is treated as enabled.

### Use case
In the Location section, parking details, accessibility details, and a storefront image sit under a tab labelled `Storefront [if:business]`. These fields only appear when the member has their Business section enabled.

## Header Editing System

In edit mode, `initHeaderEditing()` in memdir.js creates per-element editing overlays on the sticky header:

- **Avatar** — camera overlay on the avatar image → "Update Photo" modal (upload + delete photo via AJAX to `memdir_ajax_upload_avatar`)
- **Name** — pencil icon next to the title → "Edit Name" modal (text input, saves via `saveSection()`)
- **Categories** — pencil icon next to badges → "Edit Categories" modal (custom taxonomy search with AJAX to `memdir_search_taxonomy_terms`, replaces select2)
- **Social Links** — pencil icon next to social icons → "Edit Social Links" modal (URL inputs + "Import from [Section]" button for cross-section import via `memdir_ajax_import_social`)

All modals use native `<dialog>` elements with `showModal()`. Fields are DOM-moved into the dialog body but remain inside `.memdir-field-content` so `saveSection()` can still find them.

**CSS guard for closed dialogs:** `dialog.memdir-header-modal:not([open]) { display: none !important; }` — required because `display: flex` on the dialog interferes with native `<dialog>` closed state.

**CSS variable scoping for portaled dialogs:** `showDialogSafe()` moves dialogs to `document.body` (outside `.memdir-profile` where `--md-*` custom properties are defined). All CSS variables are redeclared on `dialog.memdir-header-modal` to keep dialogs self-contained.

## Image Upload System ("Image In, Image Out")

In edit mode, `initImageUploaders()` replaces ACF's native media library UI for all image and gallery fields (except header-owned fields) with custom inline uploaders:

### Single Image Fields
- ACF's `.acf-image-uploader` is hidden (`display: none`)
- Custom UI injected: preview image + caption input + Upload/Remove buttons + status text
- Upload: POSTs to `memdir_ajax_upload_image`, auto-deletes old attachment, updates ACF hidden input
- Remove: POSTs to `memdir_ajax_delete_image`, clears ACF hidden input
- Caption: blur-saves to `memdir_ajax_update_caption` (stored as WP attachment `post_excerpt`)

### Gallery Fields
- ACF's `.acf-gallery` is hidden; original inputs marked with `data-memdir-skip`
- Custom UI injected: thumbnail grid + per-image caption inputs + Add Image button + status text
- Add: POSTs to `memdir_ajax_gallery_upload`, appends thumbnail to grid
- Remove (× button): POSTs to `memdir_ajax_gallery_remove`, deletes attachment
- `syncGalleryHiddenInputs()` rebuilds `input.memdir-gallery-sync` elements for `saveSection()` compatibility

### Integration with `saveSection()`
- **Single image**: uploads/deletes are instant via AJAX. ACF hidden input synced, so `saveSection()` is idempotent.
- **Gallery**: ACF's original inputs get `data-memdir-skip` (ignored by `saveSection()`). Custom `memdir-gallery-sync` hidden inputs are collected instead.

## GLightbox (View Mode)

View-mode images and galleries use GLightbox 3.3.0 (loaded from jsDelivr CDN via `Plugin::enqueue_assets()`):
- `FieldRenderer::render_image()` wraps images in `<a class="glightbox">` with `data-description` for captions
- `FieldRenderer::render_gallery()` groups images with `data-gallery` attribute for prev/next navigation
- Captions sourced from WP attachment `post_excerpt`, shown as `<figcaption>` inline and in lightbox overlay
- Initialized by `initLightbox()` in JS boot sequence

## Trust Network (Non-ACF Section)

The plugin's first non-ACF, code-driven section. Uses a custom DB table instead of ACF field groups but appears as a peer section with its own pill and right-panel toggle.

### Flow
Builder visits luthier's profile → clicks "Request as Trusted Repair Partner" → luthier sees pending request in edit mode → accepts or declines. Accepted relationships are publicly visible. Either party can remove an accepted relationship.

### DB table: `{prefix}memdir_trust_network`
| Column | Type | Notes |
|--------|------|-------|
| `id` | BIGINT PK AUTO_INCREMENT | |
| `requester_id` | BIGINT | Builder's user ID |
| `target_post` | BIGINT | Luthier's member-directory post ID |
| `status` | VARCHAR(10) | `pending` / `accepted` / `declined` |
| `created_at` | DATETIME | |
| `responded_at` | DATETIME NULL | |

UNIQUE KEY on `(requester_id, target_post)`. Indexes on `(target_post, status)` and `(requester_id, status)`.

### Section enabled state
Post meta `_memdir_trust_enabled` (underscore prefix = hidden from ACF). Default: disabled (empty meta = never set = off). Members must opt in via the right-panel toggle.

### Integration points
- **pill-nav.php**: Hard-coded Trust pill after the SectionRegistry loop. Uses `TrustNetwork::is_trust_enabled()` for disabled class.
- **right-panel.php**: Hard-coded Trust toggle after the section toggle loop. `data-trust-toggle="1"` distinguishes it from ACF-backed toggles so JS routes to the trust-specific handler.
- **single-member-directory.php**: Trust section rendered after the `endforeach` for ACF sections, using the same `ob_start()` ghost pattern.
- **memdir.js**: `initTrustNetwork()` uses event delegation on `[data-trust-action]` buttons. Guard in `initSectionToggles()` skips `data-trust-toggle` checkboxes.

### AJAX endpoints
All reuse `md_save_nonce`:
| Action | Handler | Who | What |
|--------|---------|-----|------|
| `memdir_ajax_trust_request` | `handle_request()` | Builder | Insert pending row |
| `memdir_ajax_trust_respond` | `handle_respond()` | Luthier | Update to accepted/declined |
| `memdir_ajax_trust_cancel` | `handle_cancel()` | Builder | Delete pending row |
| `memdir_ajax_trust_remove` | `handle_remove()` | Either | Delete accepted row |
| `memdir_ajax_trust_toggle` | `handle_toggle()` | Author | Toggle post meta |

## Onboarding Shortcode (`[memdir_onboarding]`)

Self-service member creation form + redirect funnel. Place on any page.

### Flow
1. **Logged-out** → shortcode returns empty (BuddyBoss handles login redirect)
2. **Existing member** → `wp_safe_redirect` to their profile permalink (at `template_redirect`, before headers)
3. **New member** → form: primary section radio + URL slug text input → on submit: create post, set primary, enable `always_on` + primary sections, disable the rest, redirect to profile in edit mode with `?active_section=` query param

### Hooks
- `add_shortcode( 'memdir_onboarding', render_shortcode )` — form HTML
- `add_action( 'template_redirect', maybe_redirect )` — handles POST processing + existing-member redirect before headers are sent

### Post creation (process_form)
1. Verify nonce
2. Race guard: re-check no existing profile
3. Validate primary key against `can_be_primary` sections from SectionRegistry
4. `sanitize_title()` slug, check non-empty, check uniqueness via direct DB query
5. `wp_insert_post()`: type=member-directory, status=publish, author, title=display_name, post_name=slug
6. `update_field( 'field_md_primary_section', $primary_key, $post_id )`
7. Loop all sections: enable (`1`) if `always_on` or primary, disable (`0`) otherwise
8. `wp_safe_redirect( permalink + '?active_section=' . $primary_key )` + exit

### Styling
Inline `<style>` scoped to `.memdir-onboarding`. Uses brand palette CSS vars. No external CSS/JS dependency.

## Section JSON Schema

### Immutable section pointer (`sections/*.json`)
```json
{
  "acf_group_key": "group_md_05_business"
}
```
The section `key` is derived from the filename (`business.json` → `business`). Mutable metadata (`label`, `can_be_primary`, `always_on`, `default_avatar`, position order) lives in the `member_directory_sections` DB option, managed through the AdminSync UI. JSON files are never modified after creation.

### ACF field group (managed in ACF admin, stored in ACF's DB)
Field ordering convention inside each field group:
1. System fields first: `{section}_enabled` (true_false), `{section}_privacy_mode` (button_group)
2. Tab marker (type: tab) — if label contains "header", fields under it drive the sticky header
3. Content field
4. Companion PMP field (button_group, immediately after its content field)
5. Repeat 3–4 per field

## Known Issues

### ⚠ UNRESOLVED BLOCKER — GlobalFields meta box not appearing
The Global Controls meta box (profile visibility + primary section) does not appear in the WP Admin post editor. All registration code runs but the meta box is invisible.

- `GlobalFields::register()` is confirmed called (error_log fires)
- `acf_add_local_field_group()` is confirmed called with key `group_md_global_controls`
- Location rule nesting is correct (3-level: `[ [ [ param/op/value ] ] ]`)
- No DB conflict (no row with that key in `wp_posts`)
- No JSON file conflict on server
- `acf/get_field_groups` debug filter is **not firing** — this is the key unresolved clue
- Suspect: ACF version may process locally-registered groups differently for admin meta boxes at this hook timing

### Temporary debug code in GlobalFields.php (needs cleanup)
Remove once meta box issue resolved:
- `error_log(...)` calls in `init()` and `register()` (lines 42, 76, 98, 99)
- `debug_filters()` static method + its call in `init()` (lines 61–69 and line 49)

## Sections (gitignored — per-environment)

The `sections/` directory is gitignored. Each environment maintains its own JSON pointer files. Current sections on dev:

| Key (from filename) | ACF group key |
|---------------------|---------------|
| `profile` | `group_md_02_profile` |
| `discovery` | `group_md_03_discovery` |
| `business` | `group_md_05_business` |
| `location` | `group_69ac2395a43da` |

Label, can_be_primary, always_on, default_avatar, and order live in the DB option only (managed via AdminSync UI).

> **On a new server: create `sections/*.json` files manually, then run AdminSync Sync.**

---

> **If anything you discover contradicts or is missing from this file, update it.**
