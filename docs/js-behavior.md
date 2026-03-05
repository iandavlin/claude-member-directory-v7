# Member Directory — JS Behavior Reference

Documents what `assets/js/memdir.js` actually does. All code runs inside a self-executing anonymous function `(function() { 'use strict'; ... }())` with no global exports.

**⚠ File has CRLF line endings.** The Edit tool fails on this file. Use the Write tool (read full file first, write whole thing back) or Node.js inline scripts for edits.

---

## Initialization Order

On `DOMContentLoaded`, these functions are called in order:

```js
document.addEventListener('DOMContentLoaded', function () {
    initTabNav();              // 1. Wire tab nav for all edit sections
    initPillNav();             // 2. Wire pill click handlers
    initPillCheckboxes();      // 3. Sort disabled pills; wire checkbox handlers
    initSectionSave();         // 4. Wire save buttons and unsaved-state tracking
    initRightPanel();          // 5. Wire Primary Section + Global PMP AJAX buttons
    initSectionPmp();          // 6. Wire section-level PMP button groups
    relocateFieldInstructions(); // 7. Move ACF instruction text below fields
    initFieldPmp();            // 8. Inject per-field PMP controls (after section PMP wired)
    initHeaderEditing();       // 9. Per-element header pencils + modals
    hideEmptySectionPills();   // 10. Hide pills for empty/PMP-blocked sections
    restoreState();            // 11. Restore active pill + tab from URL params
    syncControlsTop();         // 12. Align section controls with sticky header
});
```

Each function is described below.

---

## 1. Tab Navigation — `initTabNav()`

### What it does

For every `.memdir-section--edit` on the page, finds the tab buttons (`.memdir-section-controls__tab-item`) and wires them to show/hide ACF fields in the right column.

On page load: activates the first tab (or the URL-restored tab — see below).
On tab click: calls `activateTab(section, btn)`.

### How `data-field-keys` drives field visibility

Each tab button carries a JSON array of ACF field keys:

```html
<button class="memdir-section-controls__tab-item"
        data-tab="Identity"
        data-field-keys='["field_md_profile_page_name","field_md_profile_bio"]'>
  Identity
</button>
```

`activateTab(section, activeBtn)`:
1. Parses `activeBtn.dataset.fieldKeys` as JSON (defaults to `[]` on parse error).
2. Adds `is-active` class to `activeBtn`; removes it from all other tab buttons.
3. Iterates all `.memdir-field-content .acf-field[data-key]` within the section.
4. Sets `style.display = ''` if the field's `data-key` is in the key array; `style.display = 'none'` otherwise.
5. **Dialog guard:** skips fields inside a `<dialog>` element (header modal fields).

ACF renders each field as `<div class="acf-field" data-key="{field_key}">`.

### URL param restoration

`initTabNav()` reads `URLSearchParams` for `active_section` and `active_tab` before iterating sections. If a tab button's `textContent.trim()` matches `active_tab` **and** the section's `data-section` matches `active_section`, that tab button is used as `defaultBtn` instead of the first button.

---

## 2. Pill Navigation — `initPillNav()`

### What it does

Adds a click listener to every `.memdir-pill`. On click, if the click target is **not** a checkbox (`INPUT[type=checkbox]`), calls `activatePill(pill.dataset.section)`.

The checkbox guard prevents section switching when the user clicks a pill's enable/disable checkbox — the checkbox change handler is wired separately by `initPillCheckboxes()`.

### `activatePill(sectionKey)`

Called by both `initPillNav()` and `restoreState()`.

```
activatePill('profile')    → shows only .memdir-section[data-section="profile"]
activatePill('all')        → shows all .memdir-section elements
```

Steps:
1. Toggles `memdir-pill--active` class: adds it to the pill whose `data-section === sectionKey`, removes it from all others.
2. Iterates all `.memdir-section` elements:
   - If `sectionKey === 'all'`: clears `style.display` on all sections.
   - Otherwise: clears `style.display` on the matching section, sets `style.display = 'none'` on all others.

### `data-section="all"` special case

The All Sections pill has `data-section="all"`. Passing `'all'` to `activatePill()` triggers the branch that clears display on every section — showing all enabled sections simultaneously.

---

## 3. Pill Enable/Disable — `initPillCheckboxes()`

### What it does

On page load:
1. Calls `reorderPills(nav)` to sort any already-disabled pills to the end.
2. Calls `updateAllSectionsBadge(nav)` to set the correct initial count.
3. Iterates all `.memdir-pill__checkbox` elements and calls `bindCheckbox(checkbox, nav)` on each.

### `bindCheckbox(checkbox, nav)`

Attaches a `change` event listener to a single pill checkbox. Called on init and also when `updatePrimarySection()` injects a new checkbox on the demoted primary pill.

On change:
1. Reads `checkbox.dataset.section` for the section key.
2. Reads `nav.dataset.postId` for the post ID.
3. Toggles `memdir-pill--disabled` on the pill (`!enabled`).
4. Sets `display = enabled ? '' : 'none'` on `.memdir-section[data-section="{key}"]`.
5. Calls `reorderPills(nav)`.
6. Calls `updateAllSectionsBadge(nav)`.
7. Calls `saveSectionEnabled(postId, sectionKey, enabled)` (fire-and-forget).

### `reorderPills(nav)`

Rebuilds pill order inside `.memdir-pills` using `appendChild` (which moves existing nodes). Final order:

```
All Sections pill
→ .memdir-pill--primary pill(s)
→ enabled non-primary pills (no .memdir-pill--disabled)
→ disabled non-primary pills (.memdir-pill--disabled)
```

Relative order within each group is preserved (DOM order at call time).

### `updateAllSectionsBadge(nav)`

Counts pills that are **not** `.memdir-pill--all` and **not** `.memdir-pill--disabled`. Sets the `.memdir-pill--all .memdir-pill__count` text to `"{N} enabled"`.

### `saveSectionEnabled(postId, sectionKey, enabled)`

Fire-and-forget `fetch` POST:

```
action:      memdir_ajax_save_section_enabled
nonce:       window.mdAjax.nonce
post_id:     {postId}
section_key: {sectionKey}
enabled:     "1" | "0"
```

Handler: `AcfFormHelper::handle_save_section_enabled`. No callback — the UI is already updated before this fires.

---

## 4. Section Save — `initSectionSave()`

### What it does

For every `.memdir-section--edit`:
- Listens for `input` and `change` events inside `.memdir-field-content` → calls `markUnsaved(section, banner)`.
- Listens for `keydown` inside `.memdir-field-content` → if `Enter` key and target is not a `<textarea>`, prevents default and calls `saveSection()`.
- Listens for click on `.memdir-section-save` → calls `saveSection()`.

### `markUnsaved(section, banner)`

Adds class `has-unsaved` to the section element and sets `banner.style.display = ''` (shows the unsaved banner).

### `saveSection(section, saveBtn, banner)`

**What gets collected:** All `.acf-field[data-key]` elements within `.memdir-field-content`, **regardless of tab visibility**. Hidden-tab fields are still included in the save payload. For each field, all `input`, `textarea`, and `select` descendants are iterated; unchecked checkboxes and radios are skipped. Inputs with `data-memdir-skip` attribute are skipped (used by the custom taxonomy search input).

**FormData payload:**
```
action:        md_save_section
nonce:         window.mdAjax.nonce
post_id:       section.dataset.postId
acf[{key}]:   {value}   (one entry per form control per field)
```

**On success:**
- Removes `has-unsaved` from section; hides banner.
- Shows "Saved ✓" on the button for 2 seconds, then restores original text.
- **Title update special case:** if a profile or business name field was in the payload, JS updates `.memdir-header__title` text content in place — no page reload needed.

**On error (HTTP success but `data.success === false`):**
- Adds `memdir-section-save--error` to the button for 3 seconds.

**On network/parse error:** Same error state, 3-second timeout.

---

## 5. Right Panel — `initRightPanel()`

### What it does

Wires click handlers on:
- `.memdir-panel__primary-btn` — Primary Section AJAX save
- `.memdir-panel__global-btn` — Global PMP AJAX save

### Primary Section save flow

On click of `.memdir-panel__primary-btn`:

1. Reads `btn.dataset.sectionKey` and `nav.dataset.postId`.
2. POSTs to `memdir_ajax_save_primary_section`:
   ```
   action:      memdir_ajax_save_primary_section
   nonce:       window.mdAjax.nonce
   post_id:     {postId}
   section_key: {sectionKey}
   ```
3. On success: moves `is-active` class among primary-btn siblings, then calls `updatePrimarySection(sectionKey)`.

### Global PMP save flow

On click of `.memdir-panel__global-btn`:

1. Reads `btn.dataset.pmp` and captures the previous active PMP value.
2. Optimistically updates the active class on all global buttons.
3. POSTs to `memdir_ajax_save_global_pmp`:
   ```
   action:  memdir_ajax_save_global_pmp
   nonce:   window.mdAjax.nonce
   post_id: {postId}
   pmp:     {pmp}
   ```
4. On success: updates all section PMP status labels to reflect the new global default.
5. On failure: reverts the active class to the previous value.

### `updatePrimarySection(newPrimaryKey)`

Updates the pill nav DOM when the primary section changes:

1. Reads `nav.dataset.primarySection` for the old primary key.
2. Finds `.memdir-pill[data-section="{newPrimaryKey}"]` (new primary pill).
3. **New primary pill:**
   - Removes its `.memdir-pill__checkbox` if present (primary cannot be disabled).
   - Adds `memdir-pill--primary` class.
   - Inserts it immediately after `.memdir-pill--all` via `nav.insertBefore(newPrimaryPill, allPill.nextSibling)`.
4. **Old primary pill:**
   - Creates a new `<input type="checkbox" class="memdir-pill__checkbox">` with `data-section="{oldPrimaryKey}"` and `checked=true`.
   - Inserts it as `firstChild` of the old primary pill.
   - Removes `memdir-pill--primary` from the old pill.
   - Calls `bindCheckbox(checkbox, nav)` to wire the new checkbox into the enable/disable handler.
5. Sets `nav.dataset.primarySection = newPrimaryKey`.

---

## 6. Section PMP — `initSectionPmp()`

### What it does

For every `.memdir-section--edit`, renders a 4-state PMP button group (inherit / public / member / private) in the section controls panel. Shows a status label with the effective resolved PMP after waterfall.

On button click:
1. Optimistically updates active state.
2. POSTs to `memdir_ajax_save_section_pmp`:
   ```
   action:      memdir_ajax_save_section_pmp
   nonce:       window.mdAjax.nonce
   post_id:     {postId}
   section_key: {sectionKey}
   pmp:         {inherit|public|member|private}
   ```
3. Updates the status label to show the new effective PMP.

---

## 7. Field PMP — `initFieldPmp()`

### What it does

Injects per-field visibility button groups into each content field in edit mode. Only non-system, non-PMP-companion fields get controls (filters using field type and key patterns).

Each field gets a small 4-button row (inherit / public / member / private). The active button reflects the stored companion field value.

On button click:
1. Optimistically updates active state.
2. POSTs to `memdir_ajax_save_field_pmp`:
   ```
   action:         memdir_ajax_save_field_pmp
   nonce:          window.mdAjax.nonce
   post_id:        {postId}
   companion_name: member_directory_field_pmp_{section}_{suffix}
   pmp:            {inherit|public|member|private}
   ```

---

## 8. Header Editing — `initHeaderEditing()`

### What it does

For every `.memdir-section--edit` that has a "Header" tab, creates per-element editing overlays on the sticky header. Finds header field keys from the tab button's `data-field-keys` attribute and classifies them by type.

### Field classification

| Type | Detected by | UI element |
|------|------------|------------|
| Image | `data-type="image"` (first only) | Camera overlay on avatar |
| Text | `data-type="text"` | Pencil icon next to title |
| Taxonomy | `data-type="taxonomy"` | Pencil icon next to category badges |
| Social URL | `data-type="url"` + social suffix match | Pencil icon next to social icons |

Social suffix detection uses `SOCIAL_SUFFIXES`: `_website`, `_linkedin`, `_instagram`, `_twitter`, `_facebook`, `_youtube`, `_tiktok`, `_vimeo`, `_linktree`.

### Mini-modals

All modals are created by `createMiniModal(title, fields, opts)`:
- Builds a native `<dialog>` element with header, body, and optional save button
- Appends inside `.memdir-field-content` so `saveSection()` can still find the ACF fields
- Handles close on × button, backdrop click, and Escape key
- On save: calls `saveSection()` then closes the dialog

### Avatar modal

- Custom content (not ACF fields): preview image + "Choose New Photo" button + "Delete Photo" button
- Upload: hidden file input → AJAX POST to `memdir_ajax_upload_avatar` → returns new thumbnail URL
- Delete: AJAX POST to `md_save_section` with empty value for the image field key
- Delete button auto-hides when no photo is set, reappears after upload

### Categories modal (custom taxonomy search)

`createTaxonomySearch(acfField)`:
- Destroys select2 and hides the `.acf-input` wrapper
- Creates a text input with debounced search (250ms)
- Searches via AJAX POST to `memdir_search_taxonomy_terms` (our own endpoint, not ACF's)
- Result items use `mousedown` with `preventDefault()` to keep focus on the input (prevents blur from hiding results before click registers)
- On selection: creates/selects an `<option>` on the hidden `<select>`, dispatches change event
- Shows a green checkmark badge (`✓ Term Name`) below the input for visual confirmation
- Search input has `data-memdir-skip="1"` so `saveSection()` skips it (the hidden select holds the real value)

### Social links modal

- Standard ACF URL fields moved into the dialog body
- **Import from other sections:** reads `mdAjax.socialSources` (map of section key → label for other primary-capable sections with social data). For each source, renders an "Import from [Section]" button at the top of the modal body. On click: AJAX POST to `memdir_ajax_import_social` → page reload on success.

### Empty-state pulse

Pencil icons pulse with a gold glow animation (`memdir-hdr-edit--pulse`) when all fields of that type are empty. Pulse stops after the modal is closed with filled values.

---

## 9. State Restoration — `restoreState()`

Reads `active_section` from `URLSearchParams`. If present, calls `activatePill(activeSection)`.

This runs **after** `initTabNav()`, which has already restored the tab state independently. Together they handle the full state restore after a save-triggered page reload.

---

## AJAX Actions Reference

| Action | JS Trigger | PHP Handler |
|--------|------------|-------------|
| `md_save_section` | Save button click / Enter key / modal save | `AcfFormHelper::handle_ajax_save` |
| `memdir_ajax_save_section_enabled` | Pill checkbox `change` event | `AcfFormHelper::handle_save_section_enabled` |
| `memdir_ajax_save_section_pmp` | Section PMP button click | `AcfFormHelper::handle_save_section_pmp` |
| `memdir_ajax_save_field_pmp` | Field PMP button click | `AcfFormHelper::handle_save_field_pmp` |
| `memdir_ajax_upload_avatar` | File input change in avatar modal | `AcfFormHelper::handle_avatar_upload` |
| `memdir_search_taxonomy_terms` | Debounced text input in taxonomy search | `AcfFormHelper::handle_search_taxonomy_terms` |
| `memdir_ajax_import_social` | "Import from [Section]" button click | `AcfFormHelper::handle_import_social` |
| `memdir_ajax_save_primary_section` | `.memdir-panel__primary-btn` click | `GlobalFields::handle_save_primary_section` |
| `memdir_ajax_save_global_pmp` | `.memdir-panel__global-btn` click | `GlobalFields::handle_save_global_pmp` |

All actions use:
- Nonce key: `md_save_nonce` (except `memdir_search_taxonomy_terms` which uses `memdir_search_terms`)
- Nonce value localized via `window.mdAjax.nonce` / `window.mdAjax.search_nonce`
- AJAX URL: `window.mdAjax.ajaxurl` (falls back to `/wp-admin/admin-ajax.php`)

`window.mdAjax` is enqueued by `Plugin::enqueue_assets()` via `wp_localize_script()`. It also includes `socialSources` (map of section keys with social data, for the import feature).
