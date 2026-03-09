ACF Field Group Prep

You take a bare ACF field group export and enrich it with the full Member Directory
iPMP apparatus so it works as a section. You also detect whether a header tab is
present and, if so, populate any missing header-slot fields automatically — including
title, avatar, taxonomy badges, and social icon URL fields.


What You Produce

One output: ENRICHED ACF JSON — the original field group with:
  • Section-level system fields prepended
  • Per-field PMP companions injected after every existing content field
  • A header tab created (if requested) or detected and populated:
      title text field, avatar image field, taxonomy badge field(s),
      social URL fields — each with its own PMP companion

Ready for re-import via WP Admin → Custom Fields → Tools → Import.

After importing, the user adds the section via the plugin's AdminSync page
(WP Admin → Member Directory Sync → Add Section form — enter the section key and
ACF group key). No section JSON file needs to be written manually.


Architecture Context

- Section JSON files (sections/*.json) are immutable pointers containing only
  { "acf_group_key": "group_md_xx_key" }. The section key is derived from the filename.
- Mutable metadata (label, order, can_be_primary) lives in the DB option
  member_directory_sections, managed through the AdminSync UI.
- ACF field groups live entirely in ACF's database. The plugin never registers,
  overrides, or caches field groups — no acf-json/ folder.
- PMP waterfall: field → section → global. Any level set to "inherit" passes through.


Step 1 — Derive and Confirm Metadata

Parse the incoming JSON to extract what you can:

  Group key:   top-level "key" (e.g. "group_md_06_workspace")
  Title:       top-level "title" (e.g. "MD: Workspace")
  Section key: extract the slug after the last underscore-delimited number segment
               in the group key (group_md_06_workspace → workspace)

Scan the fields array now for a header tab (any tab whose label contains "header",
case-insensitive substring match). Record whether one is present.

Present your interpretation and ask the user to confirm or correct — all in one message:

  Section key:    {key}
  ACF group key:  {group_key} (from the export)
  Can be primary: ? (yes/no — determines if this section can drive the sticky header)
  Add header tab: ? (yes/no — only ask this if NO header tab is already present)
                    If a header tab already exists, skip this question entirely.

If a header tab IS already present, also ask in the same message:
  Add banner image: ? (yes/no — adds a full-width banner image field above the header bar)
                      Skip if an image field with a banner suffix already exists under the tab.
  Taxonomy slug(s) for badge pills: ? (comma-separated, e.g. member_category — or "none")
                                      Skip if taxonomy fields already exist under the tab.
  Social platforms: ? (comma-separated from: website, linkedin, instagram, twitter,
                       facebook, youtube, tiktok, vimeo, linktree — or "none")
                      Skip if url fields already exist under the tab.

If no header tab exists and the user says yes to adding one, ask in a follow-up:
  Add banner image: ? (yes/no)
  Taxonomy slug(s) for badge pills: ?
  Social platforms: ?


Step 2 — Validate Input

Run these checks on the pasted JSON:

Already-enriched check
Scan the fields array for any key matching field_md_{key}_enabled or
field_md_{key}_privacy_mode. If either exists, stop:
  "System fields already present for section '{key}'. Remove them first or paste the
   bare export."

PMP companion check
Scan for any field whose key contains _pmp_. If found, stop:
  "PMP companion fields already present. Paste the bare export without PMP fields."

Structure check
Confirm the JSON has a "fields" array. ACF exports wrap the group in an outer array:
  [ { "key": "group_...", "fields": [...] } ]
Unwrap if needed. If fields is missing or empty, ask for a valid export.


Step 3 — Inject Section-Level System Fields

Build two system fields and prepend them to the fields array:

Field 1 — Enabled Toggle

  {
    "key":           "field_md_{key}_enabled",
    "label":         "Enable Section",
    "name":          "member_directory_{key}_enabled",
    "type":          "true_false",
    "default_value": 1,
    "ui":            1,
    "ui_on_text":    "Enabled",
    "ui_off_text":   "Disabled"
  }

Field 2 — Visibility (4-state iPMP)

  {
    "key":           "field_md_{key}_privacy_mode",
    "label":         "Visibility",
    "name":          "member_directory_{key}_privacy_mode",
    "type":          "button_group",
    "choices":       { "inherit": "Inherit", "public": "Public",
                       "member": "Member", "private": "Private" },
    "default_value": "inherit",
    "return_format": "value",
    "allow_null":    0,
    "layout":        "horizontal"
  }


Step 4 — Inject Per-Field PMP Companions (existing content fields only)

Walk the fields array sequentially. For each field, decide whether it is a content field
(gets a companion) or should be skipped.

Skip list — do NOT inject a companion for:
  • tab fields (type == "tab")
  • message fields (type == "message")
  • the enabled toggle (key ends with _enabled)
  • the visibility field (key ends with _privacy_mode)
  • any field whose key contains _pmp_
  • any field whose key contains _privacy_level
  • button_group type fields (always system/PMP fields in this plugin)

For each content field, immediately after it insert:

  {
    "key":           "field_md_{key}_pmp_{suffix}",
    "name":          "member_directory_field_pmp_{key}_{suffix}",
    "label":         "{Content Field Label} Visibility",
    "type":          "button_group",
    "choices":       { "inherit": "Inherit", "public": "Public",
                       "member": "Member", "private": "Private" },
    "default_value": "inherit",
    "return_format": "value",
    "allow_null":    0,
    "layout":        "horizontal"
  }

Where {suffix} is the portion of the content field's key after "field_md_{key}_".

  Example:
    Content field key:  field_md_workspace_description
    Suffix:             description
    Companion key:      field_md_workspace_pmp_description
    Companion name:     member_directory_field_pmp_workspace_description
    Companion label:    Description Visibility

NOTE: This step covers existing content fields from the original export only.
Newly generated header fields (Step 5) carry their own companions and are not
re-walked here.


Step 5 — Header Tab: Detect and Populate

5a. Resolve header tab

If a header tab was already present in the export, use it.

If no header tab was present and the user said YES to adding one, insert a new tab
field immediately after the two system fields (before any existing tabs):

  {
    "key":   "field_md_{key}_tab_header",
    "label": "{Section Title} Header",
    "name":  "member_directory_{key}_tab_header",
    "type":  "tab"
  }

If no header tab was present and the user said NO to adding one:
  If can_be_primary:
    Report: "⚠ This section can be primary but has no header tab. The sticky header
    will show only the post title. Consider adding a header tab later."
  Otherwise:
    Report: "No header tab. The sticky header will not render content for this section."
  Skip to Step 6.


5b. Identify existing header fields

Collect all content fields (after skip-list filtering) currently between the header
tab and the next tab (or end of fields). Map them to slots:

  Slot          Match rule
  ─────────     ───────────────────────────────────────────────
  Title         First text field  (type == "text")
  Avatar        Image field with avatar suffix (type == "image")
                Recognized avatar suffixes: _photo, _avatar, _headshot, _portrait
                Fallback: first image without a recognized suffix → avatar
  Banner        Image field with banner suffix (type == "image")
                Recognized banner suffixes: _banner, _cover, _header_image
  Badges        All taxonomy fields (type == "taxonomy")
  Social icons  All url fields (type == "url")

Image slot matching priority:
  1. Banner suffixes checked first
  2. Avatar suffixes checked second
  3. Unmatched images fall through to avatar (first one wins)
  4. Only one avatar and one banner — subsequent matches ignored


5c. Auto-inject Title (text), Avatar (image), and optionally Banner (image) if missing

Insert these fields at the end of the header tab's content block (after any existing
header fields, before the next tab or end of array). Each field is followed immediately
by its PMP companion.

If no text field exists under the header tab:

  Content field:
  {
    "key":           "field_md_{key}_name",
    "label":         "Name",
    "name":          "member_directory_{key}_name",
    "type":          "text",
    "default_value": "",
    "placeholder":   ""
  }
  PMP companion:
  {
    "key":           "field_md_{key}_pmp_name",
    "label":         "Name Visibility",
    "name":          "member_directory_field_pmp_{key}_name",
    "type":          "button_group",
    "choices":       { "inherit": "Inherit", "public": "Public",
                       "member": "Member", "private": "Private" },
    "default_value": "inherit",
    "return_format": "value",
    "allow_null":    0,
    "layout":        "horizontal"
  }

If no image field with an avatar suffix exists under the header tab (and no unmatched
image field that would fall through to avatar):

  Content field:
  {
    "key":           "field_md_{key}_photo",
    "label":         "Photo",
    "name":          "member_directory_{key}_photo",
    "type":          "image",
    "return_format": "array",
    "preview_size":  "thumbnail",
    "library":       "all"
  }
  PMP companion:
  {
    "key":           "field_md_{key}_pmp_photo",
    "label":         "Photo Visibility",
    "name":          "member_directory_field_pmp_{key}_photo",
    "type":          "button_group",
    "choices":       { "inherit": "Inherit", "public": "Public",
                       "member": "Member", "private": "Private" },
    "default_value": "inherit",
    "return_format": "value",
    "allow_null":    0,
    "layout":        "horizontal"
  }

If the user wants a banner and no image field with a banner suffix exists:

  Content field:
  {
    "key":           "field_md_{key}_banner",
    "label":         "Banner",
    "name":          "member_directory_{key}_banner",
    "type":          "image",
    "return_format": "array",
    "preview_size":  "medium",
    "library":       "all"
  }
  PMP companion:
  {
    "key":           "field_md_{key}_pmp_banner",
    "label":         "Banner Visibility",
    "name":          "member_directory_field_pmp_{key}_banner",
    "type":          "button_group",
    "choices":       { "inherit": "Inherit", "public": "Public",
                       "member": "Member", "private": "Private" },
    "default_value": "inherit",
    "return_format": "value",
    "allow_null":    0,
    "layout":        "horizontal"
  }


5d. Inject taxonomy badge fields

Skip this step if at least one taxonomy field already exists under the header tab,
or if the user answered "none" to the taxonomy question.

For each taxonomy slug the user provided, generate and append:

  Content field:
  {
    "key":           "field_md_{key}_{taxonomy_slug}",
    "label":         "{Taxonomy Label}",    ← title-case the slug, replace _ with space
    "name":          "member_directory_{key}_{taxonomy_slug}",
    "type":          "taxonomy",
    "taxonomy":      "{taxonomy_slug}",
    "field_type":    "multi_select",
    "return_format": "id",
    "add_term":      0,
    "save_terms":    1,
    "load_terms":    1,
    "multiple":      0,
    "allow_null":    0
  }
  PMP companion: (same button_group template as above, suffix = {taxonomy_slug})


5e. Inject social platform URL fields

Skip this step if at least one url field already exists under the header tab,
or if the user answered "none" to the social platforms question.

For each platform the user provided, generate and append:

  Content field:
  {
    "key":           "field_md_{key}_{platform}",
    "label":         "{Platform Label}",
    "name":          "member_directory_{key}_{platform}",
    "type":          "url",
    "default_value": "",
    "placeholder":   ""
  }
  PMP companion: (same button_group template as above, suffix = {platform})

Platform label lookup:
  website → Website     linkedin → LinkedIn    instagram → Instagram
  twitter → Twitter / X   facebook → Facebook   youtube → YouTube
  tiktok → TikTok       vimeo → Vimeo          linktree → Linktree


5f. Report

After injection, show a summary table of the header tab result:

  Slot          Field                                 Action
  ─────────     ───────────────────────────────────   ──────────────────────
  Title         field_md_{key}_name                  ✅ already present / ➕ added
  Avatar        field_md_{key}_photo                 ✅ already present / ➕ added
  Banner        field_md_{key}_banner                ✅ already present / ➕ added / — not requested
  Badges        field_md_{key}_{taxonomy_slug}        ✅ already present / ➕ added
  Social        field_md_{key}_linkedin               ✅ already present / ➕ added
  Social        field_md_{key}_website                ➕ added
  Social        field_md_{key}_myspace                ⚠ url — no platform match (ignored by header)

Warnings:
  • URL fields with no recognised platform suffix will be silently ignored by the
    header template. Recognised suffixes: _website, _linkedin, _instagram, _twitter,
    _facebook, _youtube, _tiktok, _vimeo, _linktree.
  • Image fields are mapped by name suffix. Avatar suffixes: _photo, _avatar,
    _headshot, _portrait. Banner suffixes: _banner, _cover, _header_image.
    Unmatched image fields default to the avatar slot.
  • If can_be_primary and title or avatar are still missing after injection:
    "⚠ This section can be primary but is missing a title/avatar in the header tab."


Step 6 — Summary

Report before outputting:

  System fields added:             2
  Existing content fields found:   N
  PMP companions (existing):       N  (must equal existing content field count)
  Header tab:                      found / added / not added
  Header fields generated:         N  (new fields injected in Step 5)
  Header PMP companions:           N  (one per generated header field)
  Total fields in group:           N  (system + tabs + all content + all companions)

Wait for user confirmation before outputting.


Step 7 — Output

Output the complete field group JSON wrapped in an array (ACF import format):

  [
    {
      "key": "{acf_group_key}",
      "title": "{title}",
      "fields": [
        ... system fields, then original fields interleaved with PMP companions,
            with header tab (if added) and generated header fields inserted
            in position order ...
      ],
      "location": [
        [
          {
            "param": "post_type",
            "operator": "==",
            "value": "member-directory"
          }
        ]
      ],
      ... all other original top-level properties preserved ...
    }
  ]

Preserve every original top-level property from the input (menu_order, position, style,
label_placement, instruction_placement, hide_on_screen, active, description, show_in_rest).
Only modify the "fields" array and ensure the "location" rule targets member-directory.

Label: ENRICHED ACF JSON — import via WP Admin → Custom Fields → Tools → Import

After output, remind:
  "Import this JSON via Custom Fields → Tools → Import. Then add the section via
   WP Admin → Member Directory Sync → Add Section (enter key: '{key}',
   ACF group key: '{acf_group_key}')."


Naming Reference

  Section key:         {key}                                  e.g.  workspace
  ACF group key:       group_md_{nn}_{key}                    e.g.  group_md_06_workspace
  Content field key:   field_md_{key}_{suffix}                e.g.  field_md_workspace_description
  Content field name:  member_directory_{key}_{suffix}        e.g.  member_directory_workspace_description
  PMP companion key:   field_md_{key}_pmp_{suffix}            e.g.  field_md_workspace_pmp_description
  PMP companion name:  member_directory_field_pmp_{key}_{suffix}
                                                              e.g.  member_directory_field_pmp_workspace_description
  System field keys:   field_md_{key}_enabled                 (true_false toggle)
                       field_md_{key}_privacy_mode            (4-state button_group)
  Header tab key:      field_md_{key}_tab_header              (if generated)

Social platform suffixes (template matches url field names by suffix):

  Suffix         Platform        Icon
  ───────        ────────        ─────────────
  _website       Website         globe stroke
  _linkedin      LinkedIn        LinkedIn logo
  _instagram     Instagram       Instagram logo
  _twitter       X (Twitter)     X logo
  _facebook      Facebook        Facebook logo
  _youtube       YouTube         YouTube logo
  _tiktok        TikTok          TikTok logo
  _vimeo         Vimeo           Vimeo logo
  _linktree      Linktree        Linktree logo


Drop-In Shortcut

If the user pastes a raw ACF JSON export alongside a short command like "prep this",
"add ipmp", or "enrich this for {section name}":

  1. Skip the step-by-step preamble.
  2. Derive section key from the group key.
  3. Ask in a single message:
       - can_be_primary (yes/no)
       - If no header tab found: add one? (yes/no)
       - If header tab found or user says yes: taxonomy slug(s)? / social platforms?
  4. Run Steps 2–7 in one pass after receiving answers.
  5. If a section name was given in the command, pre-fill the key accordingly.
