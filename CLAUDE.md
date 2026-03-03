# Member Directory — Claude Code Context

## Project Summary
WordPress plugin: section-based member profile and directory system powered by ACF Pro. Members own a CPT post (`member-directory`); each post renders sections (edit or view mode) with per-field PMP (Public/Member/Private) visibility control. No CPT UI, no Gutenberg — purely ACF Pro + PHP.

## Build Status

### Complete
- `SectionRegistry` — JSON→DB sync (immutable pointers); runtime DB cache; mutable metadata in DB only
- `TemplateLoader` — routes `member-directory` single/archive to plugin templates
- `AdminSync` — admin page that triggers `SectionRegistry::sync()`; section editor UI (rename, reorder, toggle primary, delete); Add Section form
- `PmpResolver` — PMP waterfall resolution + viewer context + view-as spoofing
- `FieldRenderer` — field-to-HTML rendering for view mode (text, textarea, url, wysiwyg, image, gallery, file, google_map, true_false, checkbox, radio, taxonomy, select)
- `GlobalFields` — ACF group for global PMP + primary section controls (**⚠ debug code present — see Known Issues**)
- `AcfFormHelper` — `acf_form_head()` guard + edit-mode detection + `acf_form()` rendering + AJAX handlers for section save, section enabled, section PMP
- `templates/single-member-directory.php` — full edit/view mode branching
- `templates/parts/section-edit.php` — edit partial (left controls panel + ACF form)
- `templates/parts/section-view.php` — view partial (PMP waterfall + FieldRenderer per field)
- `templates/parts/right-panel.php` — author/admin panel: View As button group, Global Default block, Primary Section block, Notes block
- `templates/parts/header-section.php` — generic data-driven sticky header (scans for ACF tab with "header" in label)
- `templates/parts/pill-nav.php` — pill navigation row; All Sections + per-section pills with enable/disable checkboxes
- `sections/profile.json` — lean section pointer (Profile)
- `sections/business.json` — lean section pointer (Business)
- `sections/discovery.json` — lean section pointer (Discovery)
- AJAX handlers wired:
  - `md_save_section` → `AcfFormHelper::handle_ajax_save`
  - `memdir_ajax_save_section_enabled` → `AcfFormHelper::handle_save_section_enabled`
  - `memdir_ajax_save_section_pmp` → `AcfFormHelper::handle_save_section_pmp`
  - `memdir_ajax_save_primary_section` → `GlobalFields::handle_save_primary_section`
  - `memdir_ajax_save_global_pmp` → `GlobalFields::handle_save_global_pmp`

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

4. **Section JSON files are immutable registration-only pointers.** `sections/*.json` contains only `acf_group_key`. The section key is derived from the filename. Mutable metadata (`label`, `can_be_primary`, order) lives in the `member_directory_sections` DB option only, managed through the AdminSync UI. JSON files are never written back to after creation. Field definitions live in ACF's database only.

5. **PMP waterfall order: field → section → global.** `PmpResolver::can_view()` receives all three levels. Author and admin always see everything. Ghost behavior: hidden fields/sections render zero HTML — no empty wrappers.

6. **Static classes, static `init()` entry points.** All classes use only static methods. Each class has a `static init()` called from `Plugin::init()`. No singletons, no `new`.

7. **No closing PHP tags in partials.** Prevents accidental whitespace before HTTP headers.

8. **Namespace `MemberDirectory` everywhere.** All includes use `namespace MemberDirectory;` and `use` statements at the top of each consumer file.

## File Structure

```
member-directory.php              Entry point. ACF dependency check. Boots Plugin on plugins_loaded.
member-directory-architecture.html Primary design reference. Read this when starting work on any new feature.
includes/
  Plugin.php                  Bootstrap. Registers CPT + hooks. Calls each class init().
  SectionRegistry.php         Section metadata store. sync() = sections/*.json → merge with DB option.
                              JSON files are immutable (acf_group_key only); mutable metadata
                              (label, can_be_primary, order) lives in the DB option only.
                              load_from_db() = DB option → in-memory cache.
                              Public API: get_sections(), get_section(), validate_for_upload(),
                              removed_content_keys() (always []), is_system_field().
  GlobalFields.php            ACF group: global_pmp + primary_section. ⚠ Has temporary debug code.
  AcfFormHelper.php           maybe_render_form_head(), is_edit_mode(), render_edit_form().
                              acf_form() scoped to content field keys from acf_get_fields().
                              AJAX: section save, enabled toggle, section PMP.
  AdminSync.php               Admin page + nonce-protected handler that calls SectionRegistry::sync().
                              Section editor UI: rename label, reorder, toggle can_be_primary, delete.
                              Add Section form for creating new section pointers inline.
                              All mutable metadata operations are DB-only — no JSON file writes.
  TemplateLoader.php          template_include filter → plugin templates for member-directory CPT.
  PmpResolver.php             resolve_viewer(), spoof_viewer(), can_view() (waterfall), is_member().
  FieldRenderer.php           render() — field definition + post_id → escaped HTML output.
  DirectoryQuery.php          🔜 Not yet created.
sections/
  profile.json                Immutable section pointer. { acf_group_key }. Key from filename.
  discovery.json              Immutable section pointer.
  business.json               Immutable section pointer.
templates/
  single-member-directory.php Single profile. Calls form_head first, then branches edit/view per section.
  archive-member-directory.php Scaffold only — no real implementation.
  parts/
    header-section.php        Generic sticky header. Scans ACF fields for a tab with "header"
                              in label; maps fields to slots by type (text→title, image→avatar,
                              taxonomy→badges, url→social icons).
    pill-nav.php              Pill navigation. All Sections pill + one pill per section with enable/disable checkbox.
    section-edit.php          Edit partial. Left controls (PMP buttons, tab list, save button) + ACF form.
                              Tab list derived from acf_get_fields( $section['acf_group_key'] ).
    section-view.php          View partial. Resolves PMP waterfall per field, calls FieldRenderer.
                              Field list derived from acf_get_fields( $section['acf_group_key'] ).
    right-panel.php           Author/admin panel. View As buttons, Global Default block, Primary Section block.
tools/
  acf-field-prep.md           Claude skill: enrich a bare ACF field group export with full
                              iPMP apparatus (section system fields + per-field PMP companions)
                              and validate header tab structure. Single skill for all section prep.
```

## Workflow: Sections

### Add a new section
1. Build the field group in ACF admin → click Save (ACF saves to its own DB)
2. Use the **Add Section** form in WP Admin → Member Directory Sync (type `key` + `acf_group_key` as JSON), or create `sections/key.json` with just `{ "acf_group_key": "group_md_xx_key" }` and run Sync
3. Rename, toggle can_be_primary, and reorder via the Section Editor UI (DB-only, no file writes)
4. Both edit and view surfaces are live

### Modify fields (add, remove, rename, reorder tabs)
1. Edit the field group in ACF admin → click Save
2. Done — ACF saves to its DB, next page load both surfaces reflect the change. No sync needed.

### Modify section metadata (label, order, can_be_primary)
- Use the AdminSync UI controls (rename, reorder arrows, checkbox toggle) — all DB-only, no file writes needed

### Delete a section
1. Delete or deactivate the field group in ACF admin
2. Delete the section via the AdminSync UI

### Git/deploy flow
- `sections/*.json` — committed to git (tiny, stable)
- ACF field groups live in the database only — no JSON files in the plugin
- After `git pull` on server: run AdminSync only if `sections/*.json` changed
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
**Type:** `button_group` — excluded from content field loops by `SectionRegistry::is_system_field()` via `SKIP_KEY_PATTERNS` (`_pmp_` substring)

### Waterfall Resolution
```
field_pmp → section_pmp → global_pmp
```
Any level set to `inherit` passes through to the next. Global is always explicit. `PmpResolver::can_view()` is the single authoritative check.

## Section JSON Schema

### Immutable section pointer (`sections/*.json`)
```json
{
  "acf_group_key": "group_md_05_business"
}
```
The section `key` is derived from the filename (`business.json` → `business`). Mutable metadata (`label`, `can_be_primary`, position order) lives in the `member_directory_sections` DB option, managed through the AdminSync UI. JSON files are never modified after creation.

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

## Sections on Disk

| File | Key (from filename) | ACF group key |
|------|---------------------|---------------|
| `sections/profile.json` | `profile` | `group_md_02_profile` |
| `sections/discovery.json` | `discovery` | `group_md_03_discovery` |
| `sections/business.json` | `business` | `group_md_05_business` |

Label, can_be_primary, and order live in the DB option only (managed via AdminSync UI).

> **After pulling on the server: run AdminSync Sync only if sections/*.json changed.**

---

> **If anything you discover contradicts or is missing from this file, update it.**
