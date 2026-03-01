ACF Field Group → Member Directory Section Config Converter
You are converting an ACF field group JSON export into a Member Directory section config file. The output is a single valid JSON file that gets dropped into the plugin's sections/ folder. You have everything you need in this document — no additional context is required.

How This Works
The Member Directory plugin uses JSON config files to define sections (e.g. Profile, Business, Craft & Skills). Each config file lives in the sections/ folder. When an admin runs a sync, the plugin reads these files and registers the sections.
Fields are organized into tab groups — each group corresponds to an ACF tab and appears as a nav item in the section's left column on the profile page. Fields that appear before the first tab in the ACF export are placed into a "General" group automatically.
Your job: take a raw ACF field group JSON export, extract content fields grouped by their ACF tabs, add the required PMP system fields (unless already present), and produce the final config file.

Step 1 — Ask the User These Questions
Before producing any output, ask the user all of the following questions. Do not skip any.

Section key — What is the section key? Short lowercase slug with underscores, no spaces, no hyphens. Examples: profile, business, craft_and_skills.
Section label — What is the display name for this section? Examples: "Profile", "Business", "Craft & Skills".
Order — What position should this section appear in? Integer. Lower numbers appear first.
Can be primary? — Can this section be used as the primary section? Yes or no. Only profile and business should ever be true.
Default PMP level — Default privacy level for this section: public, member, or private.
Filterable taxonomies — For each taxonomy field found in the ACF export (you will list them), ask: "Should this taxonomy be filterable in the directory?" Yes or no, per field.
Required fields — Are any fields other than the mandatory name field required? If yes, which ones?


Step 2 — Extract Content Fields Grouped by Tab
Read the ACF field group JSON. Walk the fields array in order. Use ACF tab fields to define groups — each tab starts a new group. Fields before the first tab go into a group called "General".

Fields to SKIP entirely

Any field with "type": "button_group"
Any field with "type": "post_title"
Any field with "type": "display_name"
Any field with "type": "first_name"
Any field with "type": "last_name"
Any field with "type": "allow_comments"
Any field whose key contains privacy_mode
Any field whose key contains privacy_level
Any field whose key contains _enabled

Tab fields ("type": "tab") are NOT skipped — they define the group boundaries. They do not appear as content fields but their label becomes the group label.

Grouping algorithm

Start with current group = { "tab": "General", "fields": [] }
Walk the fields array in order:

If type === "tab" → close the current group (if it has fields, add it to output), start a new group with "tab": tab.label
If field is in the SKIP list → skip it
Otherwise → extract the field and add it to the current group


After the last field → close the final group (if it has fields, add it to output)
Discard any group with zero content fields

Fields to EXTRACT (per field within a group)

Property | Where it comes from
---------|--------------------
key | The ACF field's key value, copied exactly
label | The ACF field's label value, copied exactly
type | The ACF field's type value, copied exactly
required | ACF's required as boolean (true/false) — also true for any field the user flagged in Step 1
pmp_default | Always "inherit" unless user specifies otherwise
filterable | false by default. true only if type is "taxonomy" and user said yes in Step 1
taxonomy | null by default. If type is "taxonomy", set to the taxonomy slug from the ACF field's taxonomy property

Supported field types
Only these types should appear in output. Warn user and ask how to handle anything else.
text, textarea, wysiwyg, image, gallery, url, email, number, file, google_map, taxonomy, true_false, checkbox, radio, select, repeater

Note on repeater: include repeater fields in field_groups with type "repeater". The sub_fields array is not included in field_groups — FieldRenderer handles rendering at the repeater level.


Step 3 — PMP System Fields
First, scan the input ACF JSON's fields array for objects whose key matches:
  field_md_{section_key}_enabled
  field_md_{section_key}_privacy_mode

Pre-injected path (both found): The input was already processed by the acf-pmp-prep skill. Do not generate new system fields. Copy those two fields verbatim from the input into the beginning of acf_group.fields. Note to the user: "PMP fields detected in input — using existing system fields."

Generate path (neither found): Generate the two fields below and prepend them to acf_group.fields. Replace {section_key} with the actual section key.

Field 1 — Section Enabled Toggle
```json
{
  "key": "field_md_{section_key}_enabled",
  "label": "Enable Section",
  "name": "member_directory_{section_key}_enabled",
  "type": "true_false",
  "default_value": 1,
  "ui": 1,
  "ui_on_text": "Enabled",
  "ui_off_text": "Disabled"
}
```

Field 2 — Visibility (4-state)
```json
{
  "key": "field_md_{section_key}_privacy_mode",
  "label": "Visibility",
  "name": "member_directory_{section_key}_privacy_mode",
  "type": "button_group",
  "choices": {
    "inherit": "Inherit",
    "public": "Public",
    "member": "Member",
    "private": "Private"
  },
  "default_value": "inherit",
  "return_format": "value",
  "allow_null": 0,
  "layout": "horizontal"
}
```

Partial match (only one found): Stop and warn the user — the input appears to be in an inconsistent state. Ask them to re-run the acf-pmp-prep skill or remove the partial field and let this skill generate both.


Step 4 — Assemble the Output
The final output is a single JSON object with this exact shape:
```json
{
  "key": "",
  "label": "",
  "order": 0,
  "can_be_primary": false,
  "pmp_default": "member",
  "field_groups": [],
  "acf_group": {}
}
```

Key | Value
----|-------
key | Section key from Step 1
label | Section label from Step 1
order | Order integer from Step 1
can_be_primary | Boolean from Step 1
pmp_default | PMP level from Step 1
field_groups | Array of tab group objects from Step 2
acf_group | The entire original ACF field group object, untouched, except: the 2 PMP system fields are at the top of its fields array (generated or copied per Step 3)


Step 5 — Validate Before Outputting

Required field check — If no field in any group has "required": true, warn: "No required fields found. Every section typically needs at least one required field. Are you sure?"
Primary section check — If can_be_primary is true and section key is not profile or business, warn: "Only profile and business sections should be marked as primary. Are you sure?"
Field count confirmation — State: "This config contains N tab groups with X total content fields, and 2 PMP system fields (generated/pre-injected). Ready to output?" Wait for confirmation.


Worked Example
Input — User pastes this ACF export:
```json
{
  "key": "group_artwork",
  "title": "Artwork",
  "fields": [
    {
      "key": "field_artwork_tab_details",
      "label": "Details",
      "type": "tab"
    },
    {
      "key": "field_artwork_title",
      "label": "Artwork Title",
      "name": "artwork_title",
      "type": "text",
      "required": 1
    },
    {
      "key": "field_artwork_tab_taxonomy",
      "label": "Categories",
      "type": "tab"
    },
    {
      "key": "field_artwork_medium",
      "label": "Medium",
      "name": "artwork_medium",
      "type": "taxonomy",
      "taxonomy": "artwork_medium",
      "required": 0
    }
  ],
  "location": [[{ "param": "post_type", "operator": "==", "value": "member-directory" }]]
}
```

Conversation:

Q1: artwork — Q2: Artwork — Q3: 4 — Q4: No — Q5: member
Q6: Found one taxonomy: "Medium" (artwork_medium). Filterable? → Yes
Q7: No additional required fields

Step 3 check: no PMP fields found in input → generate path.

Validation:

"This config contains 2 tab groups with 2 total content fields, and 2 PMP system fields (generated). Ready to output?"

Output — artwork.json:
```json
{
  "key": "artwork",
  "label": "Artwork",
  "order": 4,
  "can_be_primary": false,
  "pmp_default": "member",
  "field_groups": [
    {
      "tab": "Details",
      "fields": [
        {
          "key": "field_artwork_title",
          "label": "Artwork Title",
          "type": "text",
          "pmp_default": "inherit",
          "filterable": false,
          "taxonomy": null,
          "required": true
        }
      ]
    },
    {
      "tab": "Categories",
      "fields": [
        {
          "key": "field_artwork_medium",
          "label": "Medium",
          "type": "taxonomy",
          "pmp_default": "inherit",
          "filterable": true,
          "taxonomy": "artwork_medium",
          "required": false
        }
      ]
    }
  ],
  "acf_group": {
    "key": "group_artwork",
    "title": "Artwork",
    "fields": [
      {
        "key": "field_md_artwork_enabled",
        "label": "Enable Section",
        "name": "member_directory_artwork_enabled",
        "type": "true_false",
        "default_value": 1,
        "ui": 1,
        "ui_on_text": "Enabled",
        "ui_off_text": "Disabled"
      },
      {
        "key": "field_md_artwork_privacy_mode",
        "label": "Visibility",
        "name": "member_directory_artwork_privacy_mode",
        "type": "button_group",
        "choices": { "inherit": "Inherit", "public": "Public", "member": "Member", "private": "Private" },
        "default_value": "inherit",
        "return_format": "value",
        "allow_null": 0,
        "layout": "horizontal"
      },
      {
        "key": "field_artwork_tab_details",
        "label": "Details",
        "type": "tab"
      },
      {
        "key": "field_artwork_title",
        "label": "Artwork Title",
        "name": "artwork_title",
        "type": "text",
        "required": 1
      },
      {
        "key": "field_artwork_tab_taxonomy",
        "label": "Categories",
        "type": "tab"
      },
      {
        "key": "field_artwork_medium",
        "label": "Medium",
        "name": "artwork_medium",
        "type": "taxonomy",
        "taxonomy": "artwork_medium",
        "required": 0
      }
    ],
    "location": [[{ "param": "post_type", "operator": "==", "value": "member-directory" }]]
  }
}
```
