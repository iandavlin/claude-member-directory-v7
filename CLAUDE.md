# Member Directory — Claude Code Context

## Project Summary
WordPress plugin: section-based member profile and directory system powered by ACF Pro. Members own a CPT post (`member-directory`); each post renders sections (edit or view mode) with per-field PMP (Public/Member/Private) visibility control. No CPT UI, no Gutenberg — purely ACF Pro + PHP.

## Build Status

### Complete
- `SectionRegistry` — JSON→DB sync + runtime DB→ACF registration; derives content fields from `acf_group.fields` at runtime
- `TemplateLoader` — routes `member-directory` single/archive to plugin templates
- `AdminSync` — admin page that triggers `SectionRegistry::sync()`
- `PmpResolver` — PMP waterfall resolution + viewer context + view-as spoofing
- `FieldRenderer` — field-to-HTML rendering for view mode (text, textarea, url, wysiwyg, image, gallery, file, google_map, true_false, checkbox, radio, taxonomy, select)
- `GlobalFields` — ACF group for global PMP + primary section controls (**⚠ debug code present — see Known Issues**)
- `AcfFormHelper` — `acf_form_head()` guard + edit-mode detection + `acf_form()` rendering + AJAX handlers for section save, section enabled, section PMP
- `templates/single-member-directory.php` — full edit/view mode branching
- `templates/parts/section-edit.php` — edit partial (left controls panel + ACF form)
- `templates/parts/section-view.php` — view partial (PMP waterfall + FieldRenderer per field)
- `templates/parts/right-panel.php` — author/admin panel: View As button group, Global Default block, Primary Section block, Notes block
- `templates/parts/header-profile.php` — sticky profile header variant
- `templates/parts/header-business.php` — sticky business header variant
- `templates/parts/pill-nav.php` — pill navigation row; All Sections + per-section pills with enable/disable checkboxes
- `sections/profile.json` — section definition (Profile)
- `sections/business.json` — section definition (Business) — fully formed, 18 content fields + 18 PMP companions
- `sections/discovery.json` — section definition (Discovery) — fully formed
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

3. **SectionRegistry runtime = DB only.** `load_from_db()` reads `member_directory_sections` option → `acf_add_local_field_group()`. Filesystem (JSON) is only read during admin sync. Never call `sync()` on a regular page load.

4. **PMP waterfall order: field → section → global.** `PmpResolver::can_view()` receives all three levels. Author and admin always see everything. Ghost behavior: hidden fields/sections render zero HTML — no empty wrappers.

5. **Static classes, static `init()` entry points.** All classes use only static methods. Each class has a `static init()` called from `Plugin::init()`. No singletons, no `new`.

6. **No closing PHP tags in partials.** Prevents accidental whitespace before HTTP headers.

7. **Namespace `MemberDirectory` everywhere.** All includes use `namespace MemberDirectory;` and `use` statements at the top of each consumer file.

## File Structure

```
member-directory.php              Entry point. ACF dependency check. Boots Plugin on plugins_loaded.
member-directory-architecture.html Primary design reference. Read this when starting work on any new feature.
includes/
  Plugin.php                  Bootstrap. Requires all classes. Registers CPT + hooks. Calls each class init().
  SectionRegistry.php         Section data. sync() = JSON→DB. load_from_db() = DB→ACF.
                              Public API: get_sections(), get_section(), get_all_fields(), get_field_groups(),
                              validate_for_upload(), removed_content_keys().
                              Content fields derived at runtime from acf_group.fields — field_groups in JSON is ignored.
  GlobalFields.php            ACF group: global_pmp + primary_section. ⚠ Has temporary debug code.
  AcfFormHelper.php           maybe_render_form_head(), is_edit_mode(), render_edit_form().
                              acf_form() scoped to content field keys. AJAX: section save, enabled toggle, section PMP.
  AdminSync.php               Admin page + nonce-protected handler that calls SectionRegistry::sync().
  TemplateLoader.php          template_include filter → plugin templates for member-directory CPT.
  PmpResolver.php             resolve_viewer(), spoof_viewer(), can_view() (waterfall), is_member().
  FieldRenderer.php           render() — field definition + post_id → escaped HTML output.
  DirectoryQuery.php          🔜 Not yet created.
sections/
  profile.json                Section config (Profile). order=1, can_be_primary=true.
  discovery.json              Section config (Discovery). order=3, can_be_primary=false.
  business.json               Section config (Business). order=5, can_be_primary=true. 18 content fields + 18 PMP companions.
templates/
  single-member-directory.php Single profile. Calls form_head first, then branches edit/view per section.
  archive-member-directory.php Scaffold only — no real implementation.
  parts/
    header-profile.php        Sticky profile header variant.
    header-business.php       Sticky business header variant.
    pill-nav.php              Pill navigation. All Sections pill + one pill per section with enable/disable checkbox.
    section-edit.php          Edit partial. Left controls (PMP buttons, tab list, save button) + ACF form.
    section-view.php          View partial. Resolves PMP waterfall per field, calls FieldRenderer.
    right-panel.php           Author/admin panel. View As buttons, Global Default block, Primary Section block.
tools/
  acf-to-config.md            Claude skill: convert ACF JSON export to section config format.
```

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

## Section JSON Schema (sections/*.json)

```json
{
  "key": "business",
  "label": "Business",
  "order": 5,
  "can_be_primary": true,
  "pmp_default": "inherit",
  "acf_group": {
    "key": "group_md_05_business",
    "title": "Member Directory — Business",
    "location": [[{"param": "post_type", "operator": "==", "value": "member-directory"}]],
    "fields": [
      { "key": "field_md_business_enabled", "name": "member_directory_business_enabled", "type": "true_false", ... },
      { "key": "field_md_business_privacy_mode", "name": "member_directory_business_privacy_mode", "type": "button_group",
        "choices": {"inherit":"Inherit","public":"Public","member":"Members Only","private":"Private"}, ... },
      { "key": "field_md_business_tab_general", "type": "tab", "label": "General" },
      { "key": "field_md_business_name", "name": "member_directory_business_name", "type": "text",
        "pmp_default": "inherit", "filterable": false, "required": true },
      { "key": "field_md_business_pmp_name", "name": "member_directory_field_pmp_business_name",
        "type": "button_group", "choices": {"inherit":"Inherit","public":"Public","member":"Members Only","private":"Private"} },
      ...
    ]
  }
}
```

**Field ordering in acf_group.fields:**
1. System fields first: `{section}_enabled`, `{section}_privacy_mode`
2. Tab marker
3. Content field
4. Companion PMP field (immediately after its content field)
5. Repeat 3–4 per field

**`field_groups` key is no longer used.** `SectionRegistry` derives content groups at runtime by parsing `acf_group.fields` directly. Old configs that still carry `field_groups` are accepted without error — the key is ignored.

## SectionRegistry Field Derivation

`derive_field_groups()` parses `acf_group.fields` and produces tab-grouped content field objects. Each field object exposes:

```php
[
  'key'         => 'field_md_business_name',
  'name'        => 'member_directory_business_name',  // used by section-view.php for PMP companion lookup
  'label'       => 'Business Name',
  'type'        => 'text',
  'pmp_default' => 'inherit',
  'filterable'  => false,
  'taxonomy'    => null,
  'required'    => true,
]
```

System fields are excluded via `is_system_field()` which checks:
- `type === 'tab'`
- `type` in `SKIP_TYPES` (`button_group`)
- `key` contains any `SKIP_KEY_PATTERNS` (`_enabled`, `_privacy_mode`, `_privacy_level`, `_pmp_`)
- `name` in `SKIP_NAMES` (`post_title`, `display_name`, etc.)

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

Three section configs live in `sections/`. Each must be synced via **WP Admin → Member Directory → Sync** before it is active at runtime. Sync writes the config to `member_directory_sections` in the WP options table; `SectionRegistry::load_from_db()` reads that option and registers ACF field groups on every page load.

| File | Key | Label | order | can_be_primary |
|------|-----|-------|-------|----------------|
| `sections/profile.json` | `profile` | Profile | 1 | true |
| `sections/discovery.json` | `discovery` | Discovery | 3 | false |
| `sections/business.json` | `business` | Business | 5 | true |

> **After editing any section JSON, always run Sync — the runtime registry will not reflect file changes until it does.**

---

> **If anything you discover contradicts or is missing from this file, update it.**
