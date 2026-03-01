# Member Directory — Frontend Layout Implementation Reference

This document describes what is **actually built** in the Member Directory plugin as of Feb 2026. It is the authoritative reference for CSS and JS development. For the original design specification see git history.

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

### DOM structure

```html
<header class="memdir-header memdir-header--{profile|business}">

  <div class="memdir-header__identity">         <!-- always present -->
    <p class="memdir-header__eyebrow">...</p>
    <h1 class="memdir-header__title">...</h1>
    <div class="memdir-header__subline"></div>  <!-- always in DOM, always empty -->
    <div class="memdir-header__social"></div>   <!-- always in DOM, always empty -->
  </div>

  <!-- Only rendered for author/admin ($show_badges = true) -->
  <div class="memdir-header__badges">
    <div class="memdir-header__badge memdir-header__badge--edit">Edit mode</div>
    <div class="memdir-header__badge memdir-header__badge--viewing">Viewing: {label}</div>
  </div>

</header>
```

Note: `.memdir-header__identity` is a wrapper `<div>` in the DOM (not in the original spec). The `subline` and `social` divs **always render empty** — they are DOM placeholders for future content.

### Two header variants

The `$variant` is determined once on page load by reading `member_directory_primary_section` from `get_field()`. It does **not** change as the user navigates between pills.

**Profile header** (`memdir-header--profile`):
- Eyebrow: `MEMBER PROFILE`
- Title: `get_field('member_directory_profile_page_name', $post_id)` — falls back to `get_the_title()` if empty

**Business header** (`memdir-header--business`):
- Eyebrow: `BUSINESS PROFILE`
- Title: **⚠ TODO** — field `field_md_business_name` exists in `business.json` but `profile-header.php` has this as a placeholder comment. The title falls back to `get_the_title()`. Fix: replace the TODO comment with `get_field('member_directory_business_name', $post_id)`.

### Subline and Social — not implemented

Both `<div class="memdir-header__subline">` and `<div class="memdir-header__social">` always render empty. Field sources are TBD (see Open Items).

### Badges

Only rendered when `$show_badges` is true (author or admin, based on the **real** viewer captured before any View As spoof). This ensures badges remain visible to the author even while previewing as Member or Public.

- **Edit mode badge** — shown when `AcfFormHelper::is_edit_mode()` returns true (edit mode, no `?view_as`)
- **Viewing badge** — shows `$active_section_label` (e.g. "All sections", "Profile")

### CSS classes
```
.memdir-header
.memdir-header--profile
.memdir-header--business
.memdir-header__identity
.memdir-header__eyebrow
.memdir-header__title
.memdir-header__subline
.memdir-header__social
.memdir-header__badges
.memdir-header__badge
.memdir-header__badge--edit
.memdir-header__badge--viewing
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
     data-post-id="{post_id}">

  <div class="memdir-section-controls">   <!-- left column, ~280px -->
    ...
  </div>

  <div class="memdir-field-content">      <!-- right column, flex-grow -->
    ...
  </div>

</div>
```

**Section card treatment:** border-radius 12px, `box-shadow: 0 1px 4px rgba(0,0,0,0.06)`, clean 1px border.

---

## Section Controls (Left Column)

White background (`--md-white`). Contains:

1. **Section title** — all-caps, small, bold
2. **PMP control row** — three icon buttons (Public / Members / Private). Currently renders as placeholders. The Override button exists in `section-view.php` but **has no JS click handler** — it is not yet wired.
3. **Tab nav** — one button per ACF tab group in the section. Rendered as mini-cards:
   - Resting: `--md-bg` background, 1px border, `border-radius: 8px`, subtle shadow
   - Active (`is-active`): `--md-green-pale` background, `--md-border` border, bold text, slightly stronger shadow
4. **Save button** (edit mode only)
5. **Unsaved banner** — `.memdir-unsaved-banner` — inside the section controls div (LEFT column, not right). Coral background, white text. Shown on any `input`/`change` event inside `.memdir-field-content`. Hidden on successful save.

### CSS classes
```
.memdir-section-controls
.memdir-section-controls__title
.memdir-section-controls__pmp
.memdir-section-controls__pmp-btn
.memdir-section-controls__pmp-btn--active
.memdir-section-controls__pmp-btn--public
.memdir-section-controls__pmp-btn--member
.memdir-section-controls__pmp-btn--private
.memdir-section-controls__override
.memdir-section-controls__override--active
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

### Edit mode tab navigation

JS uses `data-field-keys` (a JSON array of ACF field keys) on each tab button to drive show/hide of `.acf-field[data-key]` elements:

```html
<button class="memdir-section-controls__tab-item"
        data-tab="Identity"
        data-field-keys='["field_md_profile_page_name","field_md_profile_bio"]'>
  Identity
</button>
```

On tab activation, `activateTab()` sets `display: none` on all `.acf-field[data-key]` elements not in the current tab's key list, and clears `display` on those that are. Fields on hidden tabs are still collected on save.

### Section save AJAX

- **Action:** `md_save_section`
- **Nonce:** `md_save_nonce`, localized via `window.mdAjax.nonce`
- **Trigger:** Save button click, or Enter key in any non-textarea input
- **Payload:** All `.acf-field[data-key]` elements collected **regardless of tab visibility** (hidden tab fields are still saved)
- **Post-save:** If `field_md_profile_page_name` was in the payload, JS updates `.memdir-header__title` in place without a page reload
- **URL params on reload:** `?active_section={key}&active_tab={label}` — restores pill and tab state after a full-page reload

### View mode

Rendered HTML from `FieldRenderer::render()`. Fields hidden by PMP render **zero HTML** — no wrappers, no placeholders. FieldRenderer supports: text, email, number, textarea, url, image, gallery, file, google_map, wysiwyg, true_false, taxonomy, checkbox, radio. **`select` type is not handled** — falls through with no output.

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
.memdir-unsaved-banner
.memdir-section-save
.memdir-section-save--saving
.memdir-section-save--saved
.memdir-section-save--error
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
<button class="memdir-panel__global-btn {is-active?}" data-pmp="public">🌐 Public</button>
<button class="memdir-panel__global-btn" data-pmp="member">👥 Members</button>
<button class="memdir-panel__global-btn" data-pmp="private">🔒 Private</button>
```

**⚠ NOT WIRED IN JS** — the buttons render but clicking them does nothing. No click handler exists in `memdir.js` for `.memdir-panel__global-btn`. This is a known gap.

### Primary Section block

```html
<div class="memdir-panel__label">PRIMARY SECTION</div>
<button class="memdir-panel__primary-btn {is-active?}" data-section-key="profile">Profile</button>
<button class="memdir-panel__primary-btn" data-section-key="business">Business</button>
```

**IS wired and working.** Clicking fires `memdir_ajax_save_primary_section` AJAX, then calls `updatePrimarySection()` to reorder pills and update the nav DOM.

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
| Section mode | `member_directory_{section_key}_privacy_mode` | `inherit` \| `custom` |
| Section level | `member_directory_{section_key}_privacy_level` | `public` \| `member` \| `private` |
| Field level | `member_directory_field_pmp_{field_key}` | `public` \| `member` \| `private` \| `inherit` |

Resolution order: **field → section → global**. The lowest explicit override wins. See `docs/pmp-system.md` for full details.

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
| State restore from URL params | Page load | ✅ wired |
| Global Default PMP save | Click global btn | ❌ not wired |
| Section PMP override engage | Click Override btn | ❌ not wired |

---

## Open Items

- **Header subline** — field sources for both profile and business variants are TBD
- **Social links** — global social fields: source and display format TBD
- **Global Default AJAX** — `.memdir-panel__global-btn` click handler not yet written in JS
- **Section PMP Override** — Override button exists in `section-view.php` but has no JS click handler
- **FieldRenderer coverage** — `select`, `image` (partial), `url` (no link wrapping), `taxonomy` (no term links), `repeater` not fully implemented
- **Business header title** — `profile-header.php` has a TODO where `field_md_business_name` should be read
- **Sticky header WP admin bar offset** — on dev, admin bar is 32px; `.memdir-sticky { top: 0 }` may need to be `top: 32px` in admin context
- **Responsive** — no breakpoints defined yet; right panel and section columns do not reflow below 768px
