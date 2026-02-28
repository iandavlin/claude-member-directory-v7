ACF Field Group → Member Directory Section Config Converter
You are converting an ACF field group JSON export into a Member Directory section config file. The output is a single valid JSON file that gets dropped into the plugin's sections/ folder. You have everything you need in this document — no additional context is required.

How This Works
The Member Directory plugin uses JSON config files to define sections (e.g. Profile, Business, Craft & Skills). Each config file lives in the sections/ folder. When an admin runs a sync, the plugin reads these files and registers the sections.
Fields are organized into tab groups — each group corresponds to an ACF tab and appears as a nav item in the section's left column on the profile page. Fields that appear before the first tab in the ACF export are placed into an "General" group automatically.
Your job: take a raw ACF field group JSON export, extract content fields grouped by their ACF tabs, add the required PMP system fields, and produce the final config file.

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
Any field with "type": "select" where the field key contains privacy
Any field with "type": "post_title"
Any field with "type": "display_name"
Any field with "type": "first_name"
Any field with "type": "last_name"
Any field with "type": "allow_comments"
Any field with "type": "repeater"
Any field whose key contains privacy_mode
Any field whose key contains privacy_level
Any field whose key contains enabled

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
text, textarea, wysiwyg, image, gallery, url, email, number, file, google_map, taxonomy, true_false, checkbox, radio

Step 3 — Generate PMP System Fields
These three fields are not in the ACF export. Generate them and prepend to acf_group.fields. Replace {section_key} with the actual section key.

Field 1: Section Enabled Toggle
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

Field 2: Privacy Mode Toggle
```json
{
  "key": "field_md_{section_key}_privacy_mode",
  "label": "Privacy Mode",
  "name": "member_directory_{section_key}_privacy_mode",
  "type": "button_group",
  "choices": {
    "inherit": "Inherit",
    "custom": "Custom"
  },
  "default_value": "inherit",
  "layout": "horizontal"
}
```

Field 3: Privacy Level Selector
```json
{
  "key": "field_md_{section_key}_privacy_level",
  "label": "Privacy Level",
  "name": "member_directory_{section_key}_privacy_level",
  "type": "select",
  "choices": {
    "public": "Public",
    "member": "Member",
    "private": "Private"
  },
  "default_value": "member",
  "conditional_logic": [
    [
      {
        "field": "field_md_{section_key}_privacy_mode",
        "operator": "==",
        "value": "custom"
      }
    ]
  ]
}
```

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
acf_group | The entire original ACF field group object, untouched, except: prepend the three PMP system fields from Step 3 to the beginning of its fields array

Step 5 — Validate Before Outputting

Required field check — If no field in any group has "required": true, warn: "No required fields found. Every section typically needs at least one required field. Are you sure?"
Primary section check — If can_be_primary is true and section key is not profile or business, warn: "Only profile and business sections should be marked as primary. Are you sure?"
Field count confirmation — State: "This config contains N tab groups with X total content fields, and 3 auto-generated PMP system fields. Ready to output?" Wait for confirmation.


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

Validation:

"This config contains 2 tab groups with 2 total content fields, and 3 auto-generated PMP system fields. Ready to output?"

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
        "label": "Privacy Mode",
        "name": "member_directory_artwork_privacy_mode",
        "type": "button_group",
        "choices": { "inherit": "Inherit", "custom": "Custom" },
        "default_value": "inherit",
        "layout": "horizontal"
      },
      {
        "key": "field_md_artwork_privacy_level",
        "label": "Privacy Level",
        "name": "member_directory_artwork_privacy_level",
        "type": "select",
        "choices": { "public": "Public", "member": "Member", "private": "Private" },
        "default_value": "member",
        "conditional_logic": [[{ "field": "field_md_artwork_privacy_mode", "operator": "==", "value": "custom" }]]
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

What changed from the old skill:

- fields (flat array) → field_groups (array of tab group objects)
- Tab fields now define group boundaries instead of being silently skipped
- Each group has "tab" (label) and "fields" (content fields belonging to that tab)
- Fields before the first tab go into a "General" group
- acf_group is still the full original export with PMP fields prepended — unchanged
