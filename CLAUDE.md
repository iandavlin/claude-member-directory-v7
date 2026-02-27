# Member Directory — Claude Code Context

## Project Summary
WordPress plugin: section-based member profile and directory system powered by ACF Pro. Members own a CPT post (`member-directory`); each post renders sections (edit or view mode) with per-field PMP (Public/Member/Private) visibility control. No CPT UI, no Gutenberg — purely ACF Pro + PHP.

## Build Status

### Complete
- `SectionRegistry` — JSON→DB sync + runtime DB→ACF registration
- `TemplateLoader` — routes `member-directory` single/archive to plugin templates
- `AdminSync` — admin page that triggers `SectionRegistry::sync()`
- `PmpResolver` — PMP waterfall resolution + viewer context + view-as spoofing
- `FieldRenderer` — field-to-HTML rendering for view mode
- `GlobalFields` — ACF group for global PMP + primary section controls (**⚠ debug code present — see Known Issues**)
- `AcfFormHelper` — `acf_form_head()` guard + edit-mode detection + `acf_form()` rendering
- `templates/single-member-directory.php` — full edit/view mode branching
- `templates/parts/section-edit.php` — edit partial (acf_form per section)
- `templates/parts/section-view.php` — view partial (PMP checks + FieldRenderer)
- `sections/profile.json` — only section definition on disk

### Not Started / Scaffold Only
- `includes/DirectoryQuery.php` — 🔜 not created yet
- `templates/archive-member-directory.php` — placeholder `<div>` only
- `templates/parts/header.php` — not created
- `templates/parts/sidebar.php` — not created
- `templates/parts/right-panel.php` — not created (View As + Global PMP UI)
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
  SectionRegistry.php         Section data. sync() = JSON→DB. load_from_db() = DB→ACF. get_sections()/get_section().
  GlobalFields.php            ACF group: global_pmp + primary_section. ⚠ Has temporary debug code.
  AcfFormHelper.php           maybe_render_form_head(), is_edit_mode(), render_edit_form().
  AdminSync.php               Admin page + nonce-protected handler that calls SectionRegistry::sync().
  TemplateLoader.php          template_include filter → plugin templates for member-directory CPT.
  PmpResolver.php             resolve_viewer(), spoof_viewer(), can_view() (waterfall), is_member().
  FieldRenderer.php           render() — field definition + post_id → escaped HTML output.
  DirectoryQuery.php          🔜 Not yet created.
sections/
  profile.json                Section config. Keys: key, label, order, can_be_primary, pmp_default, fields[], acf_group{}.
templates/
  single-member-directory.php Single profile. Calls form_head first, then branches edit/view per section.
  archive-member-directory.php Scaffold only — no real implementation.
  parts/
    section-edit.php          Edit partial. Calls AcfFormHelper::render_edit_form() per section.
    section-view.php          View partial. Resolves section/global PMP, loops fields, calls FieldRenderer.
tools/
  acf-to-config.md            Claude skill: convert ACF JSON export to section config format.
```

## Coding Conventions

- PHP 8.0+, typed properties and return types throughout
- `defined( 'ABSPATH' ) || exit;` at top of every file
- `sanitize_text_field( wp_unslash( $_GET['...'] ) )` for all `$_GET` reads
- `esc_html()` / `esc_attr()` on all HTML output
- ACF field names follow pattern: `member_directory_{section_key}_{purpose}` (e.g. `member_directory_profile_privacy_mode`)
- Field PMP ACF names: `member_directory_field_pmp_{field_key}`
- Global PMP ACF name: `member_directory_global_pmp`
- Section enabled toggle: `member_directory_{section_key}_enabled` (false = hidden; null/missing = show)
- ACF group keys: `group_md_{nn}_{section_key}` (e.g. `group_md_02_profile`)
- ACF field keys: `field_md_{section_key}_{purpose}`

## Section JSON Schema (sections/*.json)

```json
{
  "key": "profile",
  "label": "Profile",
  "order": 20,
  "can_be_primary": true,
  "pmp_default": "member",
  "fields": [
    { "key": "display_name", "label": "Display Name", "type": "text", "acf_key": "field_md_profile_display_name" }
  ],
  "acf_group": { /* full ACF field group definition — location rule: post_type == member-directory */ }
}
```

## Known Issues

### GlobalFields meta box not appearing (UNRESOLVED)
- `GlobalFields::register()` is confirmed called (error_log fires)
- `acf_add_local_field_group()` is confirmed called with key `group_md_global_controls`
- Location rule nesting is correct (3-level: `[ [ [ param/op/value ] ] ]`)
- No DB conflict (no row with that key in `wp_posts`)
- No JSON file conflict on server
- `acf/get_field_groups` debug filter is **not firing** — this is the key unresolved clue
- Suspect: ACF version may process locally-registered groups differently for admin meta boxes at this hook timing

### Temporary debug code in GlobalFields.php (needs cleanup)
Remove once meta box issue resolved:
- `error_log(...)` calls in `init()` and `register()`
- `debug_filters()` static method + its call in `init()`

---

> **If anything you discover contradicts or is missing from this file, update it.**
