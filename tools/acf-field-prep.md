ACF Field Group Prep

You take a bare ACF field group export — one that has only tabs and content fields — and
enrich it with the full Member Directory iPMP apparatus. You also check for a header tab
and validate its structure against what the sticky header template expects.


What You Produce

1. ENRICHED ACF JSON — the original field group with system fields prepended and per-field
   PMP companions injected after every content field. Ready for re-import via
   WP Admin → ACF → Tools → Import.

2. LEAN SECTION CONFIG — a 5-property JSON pointer for sections/{key}.json. This is the
   only file the plugin reads at runtime; the field definitions stay in ACF's database.


Step 1 — Derive and Confirm Metadata

Parse the incoming JSON to extract what you can:

  Group key:   top-level "key" (e.g. "group_md_06_workspace")
  Title:       top-level "title" (e.g. "MD: Workspace")
  Section key: extract the slug after the last underscore-delimited number segment
               in the group key (group_md_06_workspace → workspace)
  Label:       strip leading "MD: " from the title if present (MD: Workspace → Workspace)

Present your interpretation and ask the user to confirm or correct:

  Section key:    {key}
  Display label:  {label}
  Order:          ? (ask — integer, use odd numbers with gaps: 1, 3, 5, 7…)
  Can be primary: ? (ask — yes/no, determines if this section can drive the sticky header)
  ACF group key:  {group_key} (from the export)


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


Step 4 — Inject Per-Field PMP Companions

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


Step 5 — Header Tab Check

Scan all tab fields for one whose label contains the word "header" (case-insensitive,
substring match — "Header", "Header Info", "HEADER SECTION" all qualify).

--- If NO header tab found ---

Report: "No header tab detected. The sticky header will not render content for this
section (it will fall back to the post title)."

If can_be_primary is true, escalate:
  "⚠ This section can be primary but has no header tab. The sticky header uses the
   first tab whose label contains 'header' to populate the avatar, title, badges, and
   social icons. Without one, the header will show only the post title."

--- If a header tab IS found ---

Collect all content fields (after skip-list filtering) between the header tab and the
next tab (or end of fields). Map them to the display slots the template expects:

  Slot          Source                     Match rule
  ─────────     ────────────────────────   ─────────────────────────────────────
  Title         First text field           type == "text"
  Avatar        First image field          type == "image"
  Badges        All taxonomy fields        type == "taxonomy"
  Social icons  All url fields             type == "url", name suffix → platform

Social platform detection — the template matches url field names by suffix:

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

URL fields whose names don't end with a recognised suffix are silently ignored by the
template (the link won't appear in the header).

Report a table:

  Slot          Field                                Status
  ─────────     ──────────────────────────────────   ──────
  Title         field_md_{key}_name                  ✅ text
  Avatar        field_md_{key}_photo                 ✅ image
  Badges        field_md_{key}_category              ✅ taxonomy
  Social        field_md_{key}_linkedin              ✅ url → LinkedIn
  Social        field_md_{key}_website               ✅ url → Website
  Social        field_md_{key}_myspace               ⚠ url — no platform match

Warnings:
  • Missing text field: "No text field in header tab — title will fall back to the post title."
  • Missing image field: "No image field in header tab — no avatar will display."
  • URL with no platform match: "URL field '{name}' has no recognised platform suffix.
    It will be ignored by the header. Recognised suffixes: _website, _linkedin,
    _instagram, _twitter, _facebook, _youtube, _tiktok, _vimeo, _linktree."
  • If can_be_primary and title or avatar missing:
    "⚠ This section can be primary. A missing title/avatar means the sticky header
     will look incomplete when this section drives it."


Step 6 — Summary

Report before outputting:

  System fields added:      2
  Content fields found:     N
  PMP companions injected:  N  (must equal content field count)
  Header tab:               found / not found
  Total fields in group:    N  (system + tabs + content + companions)

Wait for user confirmation before outputting.


Step 7 — Output

Output 1: Enriched ACF JSON

Label: ENRICHED ACF JSON — import via WP Admin → ACF → Tools → Import

Output the complete field group JSON wrapped in an array (ACF import format):

  [
    {
      "key": "{acf_group_key}",
      "title": "{title}",
      "fields": [
        ... system fields, then original fields interleaved with PMP companions ...
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
      ... all other original top-level properties preserved (menu_order, position, style, etc.) ...
    }
  ]

Preserve every original top-level property from the input (menu_order, position, style,
label_placement, instruction_placement, hide_on_screen, active, description, show_in_rest).
Only modify the "fields" array and ensure the "location" rule targets member-directory.

Output 2: Lean Section Config

Label: SECTION CONFIG — save to: sections/{key}.json

  {
    "key": "{key}",
    "label": "{label}",
    "order": {order},
    "can_be_primary": {true|false},
    "acf_group_key": "{acf_group_key}"
  }


Remind: "Import the enriched JSON via ACF → Tools → Import. Save the section config to
sections/{key}.json. Then run WP Admin → Member Directory → Sync."


Drop-In Shortcut

If the user pastes a raw ACF JSON export alongside a short command like "prep this",
"add ipmp", or "enrich this for {section name}":

  1. Skip the step-by-step preamble.
  2. Derive section key and label from the group key/title.
  3. Ask only for order and can_be_primary (two quick questions).
  4. Run Steps 2–7 in one pass.
  5. If a section name was given in the command, pre-fill the label and suggest a key.
