# Member Directory — Frontend Layout Specification

This document defines the frontend layout, interaction model, and visual system for the Member Directory profile page. It is the authoritative reference for all CSS and JS development.

---

## Brand Color Palette

```
$color-gold:        #ECB351  /* primary accent */
$color-gold-light:  #F1DE83  /* secondary accent */
$color-green-pale:  #D4E0B8  /* section tint 1 */
$color-green-light: #C2D5AA  /* section tint 2 */
$color-green-mid:   #A8BE8B  /* section tint 3 */
$color-green-sage:  #97A97C  /* section tint 4 */
$color-green-dark:  #87986A  /* section tint 5 */
$color-coral:       #FE6B4F
```

Section background tints should be drawn from the green palette at low opacity (10–15%) so content remains readable. Assign tints sequentially by section order. Coral is reserved for errors, unsaved state banners, and destructive actions only.

---

## Page Structure

```
┌─────────────────────────────────────────────┬──────────────┐
│  STICKY ZONE                                │              │
│  ┌───────────────────────────────────────┐  │              │
│  │  HEADER                               │  │  RIGHT       │
│  └───────────────────────────────────────┘  │  PANEL       │
│  ┌───────────────────────────────────────┐  │  (author/    │
│  │  PILLS                                │  │   admin      │
│  └───────────────────────────────────────┘  │   only)      │
├─────────────────────────────────────────────│              │
│  CONTENT AREA                               │              │
│  ┌──────────────┬────────────────────────┐  │              │
│  │ SECTION      │  FIELD CONTENT         │  │              │
│  │ CONTROLS     │                        │  │              │
│  │ (left)       │  (right)               │  │              │
│  └──────────────┴────────────────────────┘  │              │
└─────────────────────────────────────────────┴──────────────┘
```

The sticky zone (header + pills) scrolls with the page until it hits the top, then sticks. Header and pills stick together as a unit — they never separate.

The right panel is fixed position, visible only to post author and admin. It does not scroll.

---

## Header

### Two header variants

**Profile header** — shown when the active/default section is Profile, or for any section that is not Business:
- Eyebrow label: `MEMBER PROFILE` (all caps, small, muted)
- Title: value of `member_directory_profile_page_name`
- Subline: TBD — likely tagline field + bullet + location field from Profile section, possibly augmented with taxonomy terms from Discovery section. The `.memdir-header__subline` div always renders in the DOM (even when empty) to reserve the layout slot.
- Social links: pulled from global social fields shared across all author posts

**Business header** — shown when the active/default section is Business:
- Eyebrow label: `BUSINESS PROFILE` (all caps, small, muted)
- Title: value of business name field from Business section
- Subline: TBD — likely industry/type + location from Business section, possibly augmented with Business taxonomy terms. Same always-render rule applies.
- Social links: same global social fields

**Switching rule:** If the member's Primary Section (set in Global Controls) is `profile`, all section views use the Profile header. If Primary Section is `business`, all section views use the Business header. The header does not change as the user navigates between section pills — it reflects the primary section only.

### Edit mode / Viewing state badges
- Top right of header, inline
- Only visible to post author and admin
- **Edit mode badge** — shown when in edit mode. Clicking has no action (already in edit mode).
- **Viewing: {Section}** badge — shows the currently active section name. In All Sections view shows "Viewing: All sections".
- Both badges are read-only indicators, not controls.

### CSS classes
```
.memdir-header
.memdir-header--profile
.memdir-header--business
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

A horizontal row of pills immediately below the header. Sticks with the header as a unit.

### Pills

**All Sections pill** (always first):
- Hamburger/list icon on left
- Label: "All sections"
- Count badge: "N enabled" text (not just the number)
- Dark filled background (--md-green-sage) with white text — always visually prominent
- Clicking shows all enabled sections stacked vertically in the content area

**Section pills** (one per registered section, in section `order`):
- Checkbox (16px) on left inside the pill — this is the section enabled/disabled toggle
- Label: section label
- Consistent min-height: 36px, padding: 8px 14px
- Active state: filled background using that section's tint color, darker border
- Disabled state: checkbox unchecked, label muted/greyed, pill is still present but content is hidden
- Toggling a pill checkbox immediately hides/shows that section in the content area without a page reload
- The enabled state is saved to the database via AJAX when toggled

### Pill interaction rules
- Only one section can be "active" (selected for single-section view) at a time
- Clicking the pill label/background (not the checkbox) activates that section for single view
- Clicking the checkbox toggles enabled/disabled
- "All sections" and the active pill are mutually exclusive — clicking a section pill deactivates All Sections and vice versa
- Disabled sections disappear from the content area immediately on uncheck

### CSS classes
```
.memdir-pills
.memdir-pill
.memdir-pill--all
.memdir-pill--active
.memdir-pill--disabled
.memdir-pill__checkbox
.memdir-pill__label
.memdir-pill__count
```

---

## Content Area

### All Sections view
All enabled sections render stacked vertically. Each section is a two-column block.

### Single section view
Only the selected section renders. Same two-column layout.

### Two-column section layout

```
┌──────────────────┬─────────────────────────────────────────┐
│  SECTION         │  FIELD CONTENT                          │
│  CONTROLS        │                                         │
│  ~280px fixed    │  flex-grow                              │
└──────────────────┴─────────────────────────────────────────┘
```

**Section card treatment:**
- Border-radius: 12px, subtle box-shadow (0 1px 4px rgba(0,0,0,0.06))
- Clean 1px border

**Left column — Section Controls:**
- White background (not tinted)
- Section title (all caps, small, bold)
- PMP control row (three icon buttons)
- Tab nav items rendered as white mini-cards: border, border-radius 8px, subtle box-shadow. Active tab: green tint background (--md-green-pale), sage border, bold text
- Save button at bottom

**Right column — Field Content:**
- Clean white background, padding 24px
- Section title (large, styled)
- Subtitle line below title: small, muted, italic. In edit mode: "Edit surface mirrors live layout; fields update immediately."
- In edit mode: ACF form scoped to this section's field group
- In view mode: rendered field output from FieldRenderer

---

## Section Controls (Left Column)

### PMP control row
Three icon buttons in a row:
- 🌐 Globe — Public
- 👥 People — Members only  
- 🔒 Lock — Private

**Default state:** The active effective PMP level for this section is highlighted. The buttons reflect the resolved value (after waterfall), not just the raw stored value.

**Override behavior:**
- Initially the row shows the inherited/effective value as a read-only indicator
- A fourth element — "Override" button or toggle — must be clicked first to engage section-level PMP editing
- Once Override is active: the three PMP buttons become clickable and save via AJAX immediately on click
- Once a section override is set, field-level PMP override controls become visible inside the ACF form for each field
- Field-level PMP controls are hidden (vis: 0) until section override is engaged — this enforces the hierarchy

**Saving:** Section PMP changes save via AJAX immediately on click. No save button needed for PMP controls.

### Field list
- One row per content field in the section config
- Clicking a field row scrolls to / highlights that field in the right content column
- In edit mode the active field row is highlighted
- In view mode the field list is purely navigational

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
.memdir-section-controls__fields
.memdir-section-controls__field-item
.memdir-section-controls__field-item--active
```

---

## Field Content (Right Column)

### View mode
- Rendered HTML from FieldRenderer
- Ghost behavior: fields hidden by PMP do not render at all — no placeholder, no empty space
- Field wrapper: `<div class="memdir-field memdir-field--{type}">`

### Edit mode
- ACF form rendered by AcfFormHelper::render_edit_form()
- Scoped to the section's field group key
- ACF tabs from the field group are used for sub-navigation within the section
- Save button: per section, saves all fields in that section's form
- Unsaved state: if any field in the section has been changed but not saved, show the unsaved banner (see below)

### Unsaved state banner
- Coral background (`#FE6B4F`), white text
- Message: "You have unsaved changes in this section."
- Positioned: TBD — either sticky to the section or floating at bottom of viewport
- Disappears on successful save
- Appears as soon as any field value changes (before save attempt)

### Section save button
- One per section in edit mode
- Label: "Save [Section Label]" e.g. "Save Profile"
- Position: TBD — bottom of field content column, or inside section controls left column, or floating sticky
- On success: unsaved banner disappears, brief success state on button
- On failure: error message inline

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

Sticky position inside the CSS grid right column. Visible only to post author and admin. White card treatment with 12px border-radius and subtle shadow.

### Panel heading
- "CONTROLS" — all caps, 11px, bold, muted color. Always the first element in the card.

### View As block
- Heading: "VIEW AS" (all caps, small, muted)
- Three buttons rendered as a **single horizontal button group row**: Edit | Member | Public — side by side, not stacked
- Active button: filled green background (--md-green-sage), white text
- Behavior: clicking Member or Public appends `?view_as=member` or `?view_as=public` to the URL and reloads — the page then renders in the spoofed viewer mode. Edit removes the param and returns to edit mode.
- In Member or Public mode: edit form is hidden, view mode renders, right panel still shows with View As active state

### Global Default block
- Heading: "GLOBAL DEFAULT" (all caps, small, muted)
- Three full-width rows: icon on left + label on right
  - 🌐 Public / 👥 Members / 🔒 Private
- Rounded card style per row: border, border-radius, subtle background on active (--md-green-pale)
- Behavior: clicking a row saves the global PMP via AJAX immediately — no save button
- Updates take effect immediately for subsequent page views by other users
- The currently saved value is highlighted on load

### Primary Section block
- Heading: "PRIMARY SECTION" (all caps, small, muted)
- One button per primary-capable section (Profile, Business)
- Active button highlighted

### Notes block
- At the bottom of the panel, separated by a top border
- Small muted text. Currently hardcoded placeholder: "Notes appear here."
- Will be made dynamic in a future iteration.

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

## Section Color System

Each section gets a light background tint drawn from the green palette. Assign by section order:

| Order | Section | Suggested tint |
|-------|---------|----------------|
| 1 | Profile | `#D4E0B8` at 15% opacity |
| 2 | Business | `#F1DE83` at 15% opacity |
| 3+ | Others | Cycle through greens sequentially |

As of the Feb 2026 mockup refresh, both columns use white backgrounds. Tints are preserved as CSS custom properties for accent use (hover states, badges) but no longer drive the section background directly. Active pill uses the section tint at full opacity.

Coral (`#FE6B4F`) is reserved for: unsaved banner, error states, destructive actions. Never use as a section tint.

---

## JS Responsibilities

All JS lives in `assets/js/memdir.js`.

| Behavior | Trigger | Action |
|----------|---------|--------|
| Pill section toggle | Click pill checkbox | AJAX save enabled state, show/hide section in DOM |
| Pill section activate | Click pill label | Show single section, update Viewing badge |
| All Sections activate | Click All Sections pill | Show all enabled sections |
| PMP override engage | Click Override button | Enable PMP buttons, show field-level PMP controls |
| Section PMP change | Click PMP button | AJAX save section PMP, update button active state |
| Global PMP change | Click Global Default row | AJAX save global PMP, update row active state |
| Unsaved state | Any field input event | Show unsaved banner for that section |
| Section save | Click Save button | ACF form submit for that section, hide banner on success |
| Field navigation | Click field in left list | Scroll to / focus that field in right column |
| Sticky header | Scroll | Header + pills stick to top as a unit |

---

## Responsive Considerations

- Below ~768px: right panel collapses or moves to a bottom drawer
- Below ~768px: section controls left column stacks above field content
- Pills wrap or scroll horizontally on narrow viewports
- Header subline truncates with ellipsis if too long, full text on hover/tap

Responsive breakpoints TBD — define when CSS work begins.

---

## Open Items

- Header subline exact field sources (Profile and Business variants)
- Unsaved banner position (sticky section vs floating viewport)
- Save button position (bottom of content, inside controls, or floating)
- Section color assignment strategy (static per section key vs dynamic by order)
- Responsive breakpoints
- Social links field source and display format in header
