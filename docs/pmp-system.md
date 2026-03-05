# Member Directory — PMP System Reference

Documents the Privacy/Visibility system as implemented in `includes/PmpResolver.php`. PMP stands for **Public / Member / Private**.

---

## The Waterfall

PMP values exist at three levels. Resolution starts at the **lowest** (most specific) level and walks upward until a non-`inherit` value is found:

```
FIELD     (most specific) — public | member | private | inherit
  ↓ if inherit
SECTION   (middle)        — public | member | private | inherit
  ↓ if inherit
GLOBAL    (least specific) — public | member | private  (never inherit)
```

**"Lowest explicit override wins."** A field set to `private` stays private even if the section or global is `public`. This is the opposite of a "most permissive" rule.

The waterfall always terminates because global can never be `inherit`.

---

## Field Naming Conventions

Exact ACF field names used in `get_field()` / `update_field()` calls:

### Global PMP

| ACF field name | Values | Stored on |
|---------------|--------|-----------|
| `member_directory_global_pmp` | `public` \| `member` \| `private` | member-directory post |

Default value: `member` (set in GlobalFields field definition).

### Section PMP

Each section has a **single 4-state field** using `{section_key}` as the interpolated key:

| ACF field name | Values | Purpose |
|---------------|--------|---------|
| `member_directory_{section_key}_privacy_mode` | `inherit` \| `public` \| `member` \| `private` | Section visibility override; `inherit` defers to global PMP |

Examples for the `profile` section:
- `member_directory_profile_privacy_mode` → `'inherit'` (default) or `'public'` / `'member'` / `'private'`

**Read:** `get_field( 'member_directory_' . $section_key . '_privacy_mode', $post_id ) ?: 'inherit'`
**Write (AJAX `memdir_ajax_save_section_pmp`):** `update_field( 'field_md_{section_key}_privacy_mode', $pmp, $post_id )`

### Field-level PMP

Each content field has a companion PMP field. The companion name is built from the content field's name by stripping the `member_directory_` prefix and prepending `member_directory_field_pmp_`:

| ACF field name | Values |
|---------------|--------|
| `member_directory_field_pmp_{section}_{suffix}` | `public` \| `member` \| `private` \| `inherit` |

Example: content field `member_directory_business_name` → companion `member_directory_field_pmp_business_name`

**Read:** `get_field( 'member_directory_field_pmp_' . $field_name_suffix, $post_id ) ?: 'inherit'`
**Write (AJAX `memdir_ajax_save_field_pmp`):** `update_field( $companion_name, $pmp, $post_id )`

### Section enabled toggle

| ACF field name | Values | Meaning |
|---------------|--------|---------|
| `member_directory_{section_key}_enabled` | `1` \| `0` (stored as ACF true_false) | Whether the section is shown. `get_field()` returns PHP `false` for `0`; `null`/empty for unset (treated as enabled). |

---

## Resolution Logic — `PmpResolver::can_view()`

```php
PmpResolver::can_view(
    [
        'field_pmp'   => 'inherit',  // from post meta for this field
        'section_pmp' => 'member',   // from post meta for this section
        'global_pmp'  => 'public',   // from post meta for the profile
    ],
    $viewer  // array: is_author, is_admin, is_logged_in
);
```

### Step 1 — Author and admin bypass everything

```php
if ( !empty($viewer['is_author']) || !empty($viewer['is_admin']) ) {
    return true;
}
```

The post author and WordPress administrators always see all fields regardless of any PMP setting.

### Step 2 — Walk the waterfall

```php
// is_explicit() checks membership in ['public', 'member', 'private']
if ( is_explicit($field_pmp) )   → $effective = $field_pmp
elseif ( is_explicit($section_pmp) ) → $effective = $section_pmp
else                              → $effective = $global_pmp
```

`is_explicit()` returns `false` for `'inherit'`, empty strings, nulls, and unrecognized values — causing the waterfall to continue upward.

### Step 3 — Apply effective value against viewer

```php
match ($effective) {
    'public'  => true,
    'member'  => !empty($viewer['is_logged_in']),  // any logged-in WP user
    'private' => false,
    default   => false,  // unknown value — fail closed
}
```

**`'member'`** means any logged-in WordPress user (`is_user_logged_in()`). There is no separate PMP membership check — it maps directly to `is_logged_in`. A `private` field reaching step 3 always returns `false` because author/admin were caught in step 1.

---

## Ghost Behavior

When `can_view()` returns `false`, the field **must not render any HTML**. No placeholders, no "this field is private" labels, no empty wrappers. The content does not exist for that viewer.

Templates enforce this by checking `can_view()` before emitting any output for a field. `FieldRenderer::render()` returns an empty string for zero-value fields, and `section-view.php` checks `can_view()` before calling `FieldRenderer::render()`.

**At the section level:** `section-view.php` checks the section's resolved PMP before entering the field loop. If the section as a whole is hidden, the entire section wrapper is suppressed — including the section title and controls.

---

## View As Simulation

`PmpResolver::spoof_viewer(string $level)` returns a fake `$viewer` array:

| `$level` | `is_author` | `is_admin` | `is_logged_in` |
|---------|------------|-----------|---------------|
| `'member'` | false | false | true |
| `'public'` (or any unrecognized) | false | false | false |

The spoofed viewer is passed to `can_view()` in place of the real viewer. No saved data changes.

**Important:** `single-member-directory.php` captures `$is_privileged` from the **real** viewer before any spoof:

```php
$viewer        = PmpResolver::resolve_viewer($post_id);
$is_privileged = $viewer['is_author'] || $viewer['is_admin'];

if (isset($_GET['view_as']) && $is_privileged) {
    $viewer = PmpResolver::spoof_viewer(sanitize_text_field(...));
}
```

`$is_privileged` is passed to `header-section.php` so header badges and the right panel remain visible to the author even while previewing a spoofed viewer. The `$viewer` passed to section rendering is the spoofed one — so field PMP resolves as the spoofed viewer would see it.

---

## Edit-Mode PMP Controls

### Section PMP (left panel)
`initSectionPmp()` in memdir.js renders 4-state button groups (inherit/public/member/private) in the section controls panel. Clicking a button fires `memdir_ajax_save_section_pmp` to persist the value. The active button reflects the stored value; a status label shows the effective resolved PMP (after waterfall).

### Field PMP (per-field)
`initFieldPmp()` in memdir.js injects small visibility button groups next to each content field in edit mode. Clicking a button fires `memdir_ajax_save_field_pmp`. Only non-system, non-PMP-companion fields get controls.

### Global PMP (right panel)
`initRightPanel()` in memdir.js wires `.memdir-panel__global-btn` buttons. Clicking fires `memdir_ajax_save_global_pmp` to persist the global default. Updates section PMP status labels after save.

---

## Viewer Context Array Shape

```php
// resolve_viewer() — real viewer:
[
    'is_author'    => bool,  // current user is post author
    'is_admin'     => bool,  // current user has manage_options capability
    'is_logged_in' => bool,  // is_user_logged_in()
]

// spoof_viewer('member') — logged-in non-author:
['is_author' => false, 'is_admin' => false, 'is_logged_in' => true]

// spoof_viewer('public') — logged-out visitor:
['is_author' => false, 'is_admin' => false, 'is_logged_in' => false]
```

---

## Usage Example (from section-view.php)

```php
$global_pmp  = get_field('member_directory_global_pmp', $post_id) ?: 'public';
$section_pmp = get_field('member_directory_' . $section_key . '_privacy_mode', $post_id) ?: 'inherit';

foreach ($fields as $field) {
    $field_name_suffix = preg_replace( '/^member_directory_/', '', $field['name'] );
    $field_pmp = get_field('member_directory_field_pmp_' . $field_name_suffix, $post_id) ?: 'inherit';

    if (!PmpResolver::can_view([
        'field_pmp'   => $field_pmp,
        'section_pmp' => $section_pmp,
        'global_pmp'  => $global_pmp,
    ], $viewer)) {
        continue; // Ghost — emit nothing
    }

    echo FieldRenderer::render($field, $post_id);
}
```
