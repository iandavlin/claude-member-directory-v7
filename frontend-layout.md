# Member Directory — Frontend Layout Implementation Reference

This document describes what is **actually built** in the Member Directory plugin as of Mar 2026. It is the authoritative reference for CSS and JS development. For the original design specification see git history.

---

## Brand Color Palette

CSS custom properties — **all scoped to `.memdir-profile`**, not `:root`. They apply only inside the plugin's wrapper and do not bleed into BuddyBoss theme styles.

```css
/* Defined in memdir.css, scoped to .memdir-profile */
--md-gold:         #ECB351   /* primary accent */
--md-gold-light:   #F1DE83   /* secondary accent */
--md-green-pale:   #D4E0B8   /* active tab bg, active section pill tint */
--md-green-light:  #C2D5AA
--md-green-mid:    #A8BE8B
--md-green-sage:   #97A97C   /* All Sections pill bg, active View As btn */
--md-green-dark:   #87986A
--md-coral:        #FE6B4F   /* unsaved banner, error states only */
--md-white:        #ffffff
--md-bg:           #f8f9f5   /* tab item resting bg */
--md-text-muted:   #6b7280
--md-border:       #e2e8d9
--md-panel-width:  280px     /* right column width in CSS grid */
```

Coral is reserved for errors, unsaved banners, and destructive actions only.

---

## Page Structure

```
┌─────────────────────────────────────────────┬──────────────┐
│  .memdir-sticky (position:sticky; top:0)    │              │
│  ┌───────────────────────────────────────┐  │              │
│  │  .memdir-header                       │  │  .memdir-    │
│  └───────────────────────────────────────┘  │  right-panel │
│  ┌───────────────────────────────────────┐  │  (author/    │
│  │  .memdir-pills                        │  │   admin      │
│  └───────────────────────────────────────┘  │   only)      │
├─────────────────────────────────────────────│              │
│  .memdir-sections                           │  position:   │
│  ┌──────────────┬────────────────────────┐  │  sticky;     │
│  │ .memdir-     │  .memdir-field-content │  │  top: 80px   │
│  │ section-     │                        │  │              │
│  │ controls     │                        │  │              │
│  └──────────────┴────────────────────────┘  │              │
└─────────────────────────────────────────────┴──────────────┘
```

**CSS grid** on `.memdir-profile`:
```css
display: grid;
grid-template-columns: 1fr var(--md-panel-width);
```

The right column is 280px (`--md-panel-width`). The left column takes all remaining space.

**Sticky zone** — `.memdir-sticky` wraps `.memdir-header` + `.memdir-pills` as a single unit:
```css
.memdir-sticky {
    position: sticky;
    top: 0;
}
```
On dev with WP admin bar: the admin bar is 32px tall and pushes `top` down. If content is cut off at the top on dev, account for this 32px offset.

**Right panel** — `position: sticky; top: 80px` inside the grid right column.

### BuddyBoss layout overrides (load-bearing)

At the top of `memdir.css`, before any plugin styles, BuddyBoss container constraints are overridden for `member-directory` pages:

```css
.single-member-directory .container,
.single-member-directory #primary {
    max-width: 100% !important;
    padding: 0 !important;
}
.single-member-directory .bb-grid.site-content-grid {
    display: block !important;
}
```

These are **load-bearing** — without them BuddyBoss caps the container width and adds its own sidebar grid, breaking the plugin's two-column layout. Do not remove or scope these differently.

---

## Header

### Overview

The header is rendered by `templates/parts/header-section.php` — a **generic, data-driven** partial. It scans the primary section's ACF field group for a tab whose label contains "header" (case-insensitive), then maps the fields under that tab to display slots by type:

| Field type | Slot | Matching rule |
|-----------|------|---------------|
| `text` (first found) | Title (`<h1>`) | First text field with a value |
| `image` (first found) | Avatar (circular) | First image field with a value |
| `taxonomy` | Category badge pills | All taxonomy fields |
| `url` | Social icon links | Matched by field name suffix to platform SVGs |

Social platform suffixes: `_website`, `_linkedin`, `_instagram`, `_twitter`, `_facebook`, `_youtube`, `_tiktok`, `_vimeo`, `_linktree`.

The header variant class (`memdir-header--{section_key}`) comes from the primary section key. The title falls back to `get_the_title()` if no text field has a value.

### DOM structure

```html
<header class="memdir-header memdir-header--{section_key}">

  <div class="memdir-header__body">

    <div class="memdir-header__identity">
      <!-- Avatar (only if image field has value or section has default_avatar) -->
      <div class="memdir-header__avatar-wrap">
        <img class="memdir-header__avatar" src="..." alt="...">
      </div>

      <div class="memdir-header__text">
        <h1 class="memdir-header__title">Name</h1>
        <p class="memdir-header__eyebrow">SECTION LABEL</p>
      </div>
    </div>

    <!-- Meta block: taxonomy badges + social icons (only if data exists) -->
    <div class="memdir-header__meta">
      <div class="memdir-header__taxo">
        <span class="memdir-header__taxo-badge">Category</span>
      </div>
      <span class="memdir-header__divider" aria-hidden="true"></span>
      <div class="memdir-header__social">
        <a class="memdir-social-link memdir-social-link--instagram" href="..." target="_blank">
          <!-- inline SVG -->
        </a>
      </div>
    </div>

  </div>

</header>
```

### Edit-mode overlays (JS-injected)

In edit mode, `initHeaderEditing()` adds per-element editing controls on top of the sticky header:

| Element | Overlay | Modal contents |
|---------|---------|---------------|
| Avatar | Camera icon overlay on the image | Preview + "Choose New Photo" + "Delete Photo" buttons |
| Title | Pencil icon next to name | ACF text field for the name |
| Category badges | Pencil icon next to badges | Custom taxonomy search (debounced AJAX, `mousedown` focus guard) |
| Social icons | Pencil icon next to social row | ACF URL fields + "Import from [Section]" buttons |

All modals use native `<dialog>` elements created by `createMiniModal()`. They are appended inside `.memdir-field-content` so `saveSection()` can find the ACF fields within them. See `docs/js-behavior.md` for full details.

**Empty-state pulse:** Pencil icons pulse with a gold glow animation (`memdir-hdr-edit--pulse`) when all fields of that type are empty. Pulse stops after the modal is closed with filled values.

### CSS classes
```
.memdir-header
.memdir-header--{section_key}
.memdir-header__body
.memdir-header__identity
.memdir-header__avatar-wrap
.memdir-header__avatar
.memdir-header__text
.memdir-header__eyebrow
.memdir-header__title
.memdir-header__meta
.memdir-header__taxo
.memdir-header__taxo-badge
.memdir-header__divider
.memdir-header__social
.memdir-social-link
.memdir-social-link--{platform}
.memdir-hdr-edit
.memdir-hdr-edit--pulse
.memdir-header-modal
.memdir-header-modal__body
.memdir-header-modal__avatar-btn
.memdir-header-modal__avatar-btn--delete
.memdir-taxo-search
.memdir-taxo-search__input
.memdir-taxo-search__results
.memdir-taxo-search__result-item
.memdir-taxo-search__badge
.memdir-import-social-btn
```

---

## Pill Navigation

### Rendered HTML structure

```html
<nav class="memdir-pills"
     data-post-id="{post_id}"
     data-primary-section="{primary_section_key}">

  <!-- All Sections pill -->
  <div class="memdir-pill memdir-pill--all memdir-pill--active"
       data-section="all">
    <span class="memdir-pill__label">All Sections</span>
    <span class="memdir-pill__count">2 enabled</span>
  </div>

  <!-- Primary section pill — no checkbox -->
  <div class="memdir-pill memdir-pill--primary"
       data-section="{primary_key}">
    <span class="memdir-pill__label">{Primary Label}</span>
  </div>

  <!-- Non-primary enabled pills -->
  <div class="memdir-pill"
       data-section="{section_key}">
    <input class="memdir-pill__checkbox" type="checkbox"
           data-section="{section_key}" checked>
    <span class="memdir-pill__label">{Section Label}</span>
  </div>

  <!-- Disabled pills (sorted to end by JS) -->
  <div class="memdir-pill memdir-pill--disabled"
       data-section="{section_key}">
    <input class="memdir-pill__checkbox" type="checkbox"
           data-section="{section_key}">
    <span class="memdir-pill__label">{Section Label}</span>
  </div>

</nav>
```

### Pill classes
- `memdir-pill--all` — the All Sections pill (dark sage bg, white text)
- `memdir-pill--primary` — the primary section pill (no checkbox; cannot be disabled)
- `memdir-pill--active` — currently selected pill (one at a time)
- `memdir-pill--disabled` — unchecked/hidden section (sorted to end by JS)

### Nav data attributes

The `.memdir-pills` nav element carries two data attributes that JS reads:
- `data-post-id` — used by JS when firing AJAX requests
- `data-primary-section` — tracks the current primary section key; updated by `updatePrimarySection()` when primary changes

### All Sections pill

- Uses `data-section="all"`
- Count badge reads `{N} enabled` (e.g. "2 enabled") — updated live by JS on checkbox toggle
- Background: `--md-green-sage`; text: white; always dark, never greyed

### Section pills

- Min-height: 36px, padding: 8px 14px
- Checkbox is 16px, flush left inside pill
- Active state: `--md-green-pale` background, `--md-green-dark` border, bold text
- Disabled state: checkbox unchecked, label muted/greyed

### Interaction model

- Clicking the pill body (not the checkbox) calls `activatePill(sectionKey)` — shows only that section
- Clicking the checkbox toggles enabled/disabled state and fires AJAX save (`memdir_ajax_save_section_enabled`)
- JS distinguishes checkbox clicks from pill clicks via `e.target.tagName === 'INPUT'` guard

### CSS classes
```
.memdir-pills
.memdir-pill
.memdir-pill--all
.memdir-pill--primary
.memdir-pill--active
.memdir-pill--disabled
.memdir-pill__checkbox
.memdir-pill__label
.memdir-pill__count
```

---

## Content Area

### Section visibility modes

- **All Sections** (`data-section="all"` active): all enabled `.memdir-section` elements visible
- **Single section** (`data-section="{key}"` active): only the matching `.memdir-section[data-section="{key}"]` visible; all others `display:none`

PHP renders only enabled sections (checked at `get_field('member_directory_{key}_enabled', $post_id)` — `false` = disabled). Disabled sections are skipped entirely in the PHP loop; they are not present in the DOM at all for non-edit users.

### Two-column section layout

Each section renders as:
```html
<div class="memdir-section memdir-section--{edit|view}"
     data-section="{section_key}"
     data-post-id="{post_id}"
     data-field-pmp="{JSON object}">

  <div class="memdir-section-controls">   <!-- left column, ~280px -->
    ...
  </div>

  <div class="memdir-field-content">      <!-- right column, flex-grow -->
    ...
  </div>

</div>
```

The `data-field-pmp` attribute (edit mode only) carries a JSON object mapping each content field's ACF key to its companion PMP key, companion name, and stored PMP value. This is read by `initFieldPmp()` to inject per-field visibility controls.

**Section card treatment:** border-radius 12px, `box-shadow: 0 1px 4px rgba(0,0,0,0.06)`, clean 1px border.

---

## Section Controls (Left Column)

White background (`--md-white`). Contains:

1. **Section title** — all-caps, small, bold
2. **Unsaved banner** — `.memdir-unsaved-banner` — coral background, white text. Shown on any `input`/`change` event inside `.memdir-field-content`. Hidden on successful save.
3. **Tab nav** — one button per ACF tab group in the section. Rendered as mini-cards:
   - Resting: `--md-bg` background, 1px border, `border-radius: 8px`, subtle shadow
   - Active (`is-active`): `--md-green-pale` background, `--md-border` border, bold text, slightly stronger shadow
4. **Save button** (edit mode only)
5. **Section PMP controls** (edit mode only) — heading "Section Default Visibility", 4-state button group (inherit / public / member / private), and a status label showing the effective resolved PMP after waterfall. Wired via `initSectionPmp()` in JS.

### CSS classes
```
.memdir-section-controls
.memdir-section-controls__title
.memdir-section-controls__pmp-heading
.memdir-section-controls__pmp
.memdir-section-controls__pmp-btn
.memdir-section-controls__pmp-btn--inherit
.memdir-section-controls__pmp-btn--public
.memdir-section-controls__pmp-btn--member
.memdir-section-controls__pmp-btn--private
.memdir-section-controls__pmp-status
.memdir-section-controls__tabs
.memdir-section-controls__tab-item
.memdir-unsaved-banner
.memdir-section-save
.memdir-section-save--saving
.memdir-section-save--saved
.memdir-section-save--error
```

---

## Field Content (Right Column)

White background (`--md-white`), padding 24px.

### Structure (edit mode)

```html
<div class="memdir-field-content">
  <h2 class="memdir-section-title">{Section Label}</h2>
  <p class="memdir-section-subtitle">Edit surface mirrors live layout; fields update immediately.</p>
  <!-- ACF form rendered by AcfFormHelper::render_edit_form() -->
</div>
```

### Per-field PMP controls (edit mode)

`initFieldPmp()` injects a small 4-button visibility row (inherit / public / member / private) next to each content field. Only non-system, non-PMP-companion fields get controls. Clicking a button fires `memdir_ajax_save_field_pmp` to persist the value.

### Edit mode tab navigation

JS uses `data-field-keys` (a JSON array of ACF field keys) on each tab button to drive show/hide of `.acf-field[data-key]` elements:

```html
<button class="memdir-section-controls__tab-item"
        data-tab="Identity"
        data-field-keys='["field_md_profile_page_name","field_md_profile_bio"]'>
  Identity
</button>
```

On tab activation, `activateTab()` sets `display: none` on all `.acf-field[data-key]` elements not in the current tab's key list, and clears `display` on those that are. Fields inside `<dialog>` elements (header modals) are skipped. Fields on hidden tabs are still collected on save.

### Section save AJAX

- **Action:** `md_save_section`
- **Nonce:** `md_save_nonce`, localized via `window.mdAjax.nonce`
- **Trigger:** Save button click, or Enter key in any non-textarea input
- **Payload:** All `.acf-field[data-key]` elements collected **regardless of tab visibility** (hidden tab fields are still saved). Inputs with `data-memdir-skip` are excluded (used by custom taxonomy search input).
- **Post-save:** If a profile or business name field was in the payload, JS updates `.memdir-header__title` in place without a page reload
- **URL params on reload:** `?active_section={key}&active_tab={label}` — restores pill and tab state after a full-page reload

### View mode

Rendered HTML from `FieldRenderer::render()`. Fields hidden by PMP render **zero HTML** — no wrappers, no placeholders. FieldRenderer supports: text, email, number, textarea, url, image, gallery, file, google_map, wysiwyg, true_false, taxonomy, checkbox, radio, select.

### CSS classes
```
.memdir-field-content
.memdir-section-title
.memdir-section-subtitle
.memdir-field
.memdir-field--{type}
.memdir-field-label
.memdir-field-value
.memdir-wysiwyg
.memdir-gallery
.memdir-field-list
.memdir-field-pmp
.memdir-field-pmp__btn
.memdir-field-pmp__btn--inherit
.memdir-field-pmp__btn--public
.memdir-field-pmp__btn--member
.memdir-field-pmp__btn--private
```

---

## Right Panel (Author/Admin Only)

Rendered only when `$is_privileged` (real viewer is author or admin). `position: sticky; top: 80px` inside the right grid column. White card: `border-radius: 12px`, `box-shadow: 0 1px 4px rgba(0,0,0,0.06)`.

### Panel heading

```html
<h2 class="memdir-panel__heading">CONTROLS</h2>
```

All-caps, 11px, `letter-spacing: 0.1em`, muted color. First element in the card.

### View As block

```html
<div class="memdir-panel__label">VIEW AS</div>
<div class="memdir-panel__view-group">
  <a class="memdir-panel__view-btn {is-active?}" href="?">Edit</a>
  <a class="memdir-panel__view-btn" href="?view_as=member">Member</a>
  <a class="memdir-panel__view-btn" href="?view_as=public">Public</a>
</div>
```

Buttons are `<a>` tags (not `<button>`). Clicking appends or removes `?view_as=member` / `?view_as=public` from the URL, causing a full page reload in spoofed-viewer mode. The Edit link removes the param. Active state is set on the button matching the current `?view_as` param (or Edit if none).

### Global Default block

Renders three buttons for Public / Members / Private global PMP:

```html
<div class="memdir-panel__label">GLOBAL DEFAULT</div>
<button class="memdir-panel__global-btn {is-active?}" data-pmp="public">Public</button>
<button class="memdir-panel__global-btn" data-pmp="member">Members</button>
<button class="memdir-panel__global-btn" data-pmp="private">Private</button>
```

Wired via `initRightPanel()` in JS. Clicking fires `memdir_ajax_save_global_pmp` AJAX. On success, updates all section PMP status labels to reflect the new global default. On failure, reverts the active class.

### Primary Section block

```html
<div class="memdir-panel__label">PRIMARY SECTION</div>
<button class="memdir-panel__primary-btn {is-active?}" data-section-key="profile">Profile</button>
<button class="memdir-panel__primary-btn" data-section-key="business">Business</button>
```

Wired via `initRightPanel()` in JS. Clicking fires `memdir_ajax_save_primary_section` AJAX, then calls `updatePrimarySection()` to reorder pills and update the nav DOM.

### Notes block

```html
<div class="memdir-panel__notes">
  <p class="memdir-panel__notes-text">Notes appear here.</p>
</div>
```

Separated from above by a top border. Hardcoded placeholder — will be made dynamic in a future iteration.

### CSS classes
```
.memdir-right-panel
.memdir-right-panel__card
.memdir-panel__heading
.memdir-panel__label
.memdir-panel__view-group
.memdir-panel__view-btn
.memdir-panel__global-btn
.memdir-panel__global-btn--active
.memdir-panel__primary-btn
.memdir-panel__notes
.memdir-panel__notes-text
```

---

## PMP Waterfall

Field naming conventions (exact ACF field names):

| Level | ACF field name | Values |
|-------|---------------|--------|
| Global | `member_directory_global_pmp` | `public` \| `member` \| `private` |
| Section | `member_directory_{section_key}_privacy_mode` | `inherit` \| `public` \| `member` \| `private` |
| Field | `member_directory_field_pmp_{section}_{suffix}` | `inherit` \| `public` \| `member` \| `private` |

Resolution order: **field → section → global**. The lowest explicit override wins. `inherit` causes the waterfall to continue upward. Global can never be `inherit`. See `docs/pmp-system.md` for full details.

---

## JS Responsibilities Summary

All JS lives in `assets/js/memdir.js`. See `docs/js-behavior.md` for full documentation.

| Behavior | Trigger | Status |
|----------|---------|--------|
| Pill activate (single section / all) | Click pill body | ✅ wired |
| Pill enable/disable + AJAX save | Click pill checkbox | ✅ wired |
| Pill reorder (disabled → end) | Checkbox change | ✅ wired |
| All Sections badge count update | Checkbox change | ✅ wired |
| Tab nav show/hide fields | Click tab button | ✅ wired |
| Section save AJAX | Save button / Enter | ✅ wired |
| Header title in-place update | Save success | ✅ wired |
| Primary Section save + pill reorder | Click primary btn | ✅ wired |
| Global Default PMP save | Click global btn | ✅ wired |
| Section PMP save | Click section PMP btn | ✅ wired |
| Field PMP save | Click field PMP btn | ✅ wired |
| Header editing (avatar/title/taxo/social) | Pencil/camera click | ✅ wired |
| Custom taxonomy search in modal | Debounced input | ✅ wired |
| Avatar upload + delete | Modal buttons | ✅ wired |
| Import social links from other section | Modal button | ✅ wired |
| State restore from URL params | Page load | ✅ wired |
| Hide pills for empty/PMP-blocked sections | Page load | ✅ wired |
| Sync controls top offset with sticky header | Page load | ✅ wired |
| Relocate ACF field instructions | Page load | ✅ wired |

---

## Open Items

- **Sticky header WP admin bar offset** — on dev, admin bar is 32px; `.memdir-sticky { top: 0 }` may need to be `top: 32px` in admin context
- **Responsive** — no breakpoints defined yet; right panel and section columns do not reflow below 768px
