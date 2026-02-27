# ACF Field Group → Member Directory Section Config Converter

You are converting an ACF field group JSON export into a Member Directory section config file. The output is a single valid JSON file that gets dropped into the plugin's `sections/` folder. You have everything you need in this document — no additional context is required.

---

## How This Works

The Member Directory plugin uses JSON config files to define sections (e.g. Profile, Business, Craft & Skills). Each config file lives in the `sections/` folder. When an admin runs a sync, the plugin reads these files and registers the sections.

Your job: take a raw ACF field group JSON export that the user pastes in, extract the content fields, add the required PMP system fields, and produce the final config file.

---

## Step 1 — Ask the User These Questions

Before producing any output, ask the user all of the following questions. Do not skip any.

1. **Section key** — What is the section key? This is a short lowercase slug with underscores, no spaces, no hyphens. Examples: `profile`, `business`, `craft_and_skills`, `vibe`.

2. **Section label** — What is the display name for this section? This is what users see in the UI. Examples: "Profile", "Business", "Craft & Skills".

3. **Order** — What position should this section appear in? This is an integer. Lower numbers appear first. Example: `1` for Profile, `2` for Business, `3` for Craft & Skills.

4. **Can be primary?** — Can this section be used as the primary section? Answer yes or no. Only `profile` and `business` should ever be `true`. Everything else is `false`.

5. **Default PMP level** — What is the default privacy level for this section? Options are `public`, `member`, or `private`. This is the starting PMP value for the section when a new member post is created.

6. **Filterable taxonomies** — For each taxonomy field found in the ACF export (you will list them), ask: "Should this taxonomy be filterable in the directory?" Yes or no, per field.

7. **Required fields** — Are any fields other than the mandatory name field required? If yes, which ones?

---

## Step 2 — Extract Content Fields from the ACF Export

Read the ACF field group JSON the user pasted. Look at the `fields` array inside it. For each field, apply these rules:

### Fields to SKIP entirely (do not include in the `fields` array)

- Any field with `"type": "tab"`
- Any field with `"type": "button_group"`
- Any field with `"type": "select"` **where the field key contains** `privacy`
- Any field with `"type": "post_title"`
- Any field with `"type": "display_name"`
- Any field with `"type": "first_name"`
- Any field with `"type": "last_name"`
- Any field with `"type": "allow_comments"`
- Any field with `"type": "repeater"`
- Any field whose `key` contains `privacy_mode`
- Any field whose `key` contains `privacy_level`
- Any field whose `key` contains `enabled`

These are either UI layout fields or PMP system fields that the skill generates automatically (see Step 3). They are not content.

### Fields to EXTRACT (include in the `fields` array)

For every remaining field, extract the following properties:

| Property | Where it comes from |
|---|---|
| `key` | The ACF field's `key` value, copied exactly |
| `label` | The ACF field's `label` value, copied exactly |
| `type` | The ACF field's `type` value, copied exactly — do **not** rename or translate types |
| `required` | The ACF field's `required` value, as a boolean (`true` or `false`) — also mark as `true` any field the user flagged as required in Step 1 |
| `pmp_default` | Always `"inherit"` unless the user specifies otherwise |
| `filterable` | `false` by default. Set to `true` **only** if the field type is `"taxonomy"` **and** the user said yes to making it filterable in Step 1 |
| `taxonomy` | `null` by default. If the field type is `"taxonomy"`, set this to the taxonomy slug from the ACF field's `taxonomy` property |

### Supported field types

Only these ACF field types should appear in the output. If you encounter a type not on this list, warn the user and ask how to handle it.

`text`, `textarea`, `wysiwyg`, `image`, `gallery`, `url`, `email`, `number`, `file`, `google_map`, `taxonomy`, `true_false`, `checkbox`, `radio`

---

## Step 3 — Generate PMP System Fields

These three fields are **not** in the ACF export. You generate them yourself and add them to the `acf_group.fields` array in the output. They go at the **beginning** of the `acf_group.fields` array, before the content fields.

Replace `{section_key}` with the actual section key from Step 1.

### Field 1: Section Enabled Toggle

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

### Field 2: Privacy Mode Toggle

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

### Field 3: Privacy Level Selector

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

---

## Step 4 — Assemble the Output

The final output is a single JSON object with this exact shape:

```json
{
  "key": "",
  "label": "",
  "order": 0,
  "can_be_primary": false,
  "pmp_default": "member",
  "fields": [],
  "acf_group": {}
}
```

Fill in the values:

| Key | Value |
|---|---|
| `key` | Section key from Step 1 |
| `label` | Section label from Step 1 |
| `order` | Order integer from Step 1 |
| `can_be_primary` | Boolean from Step 1 |
| `pmp_default` | PMP level from Step 1 |
| `fields` | The array of extracted content fields from Step 2 |
| `acf_group` | The **entire original ACF field group object** the user pasted, completely untouched, **except**: prepend the three PMP system fields from Step 3 to the beginning of its `fields` array |

---

## Step 5 — Validate Before Outputting

Run these checks and warn the user if any fail:

1. **Required field check** — If no field in the `fields` array has `"required": true`, warn: *"No required fields found. Every section typically needs at least one required field (usually the name). Are you sure this is correct?"*

2. **Primary section check** — If `can_be_primary` is `true` and the section key is not `profile` or `business`, warn: *"Only profile and business sections should be marked as primary. Are you sure you want can_be_primary: true for this section?"*

3. **Field count confirmation** — Before outputting, state: *"This config contains N content fields and 3 auto-generated PMP system fields. The acf_group contains N+3 total fields. Ready to output?"* Wait for confirmation.

---

## Worked Example

### Input — User pastes this ACF field group export:

```json
{
  "key": "group_artwork",
  "title": "Artwork",
  "fields": [
    {
      "key": "field_artwork_tab",
      "label": "Artwork",
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
      "key": "field_artwork_medium",
      "label": "Medium",
      "name": "artwork_medium",
      "type": "taxonomy",
      "taxonomy": "artwork_medium",
      "required": 0
    }
  ],
  "location": [
    [
      {
        "param": "post_type",
        "operator": "==",
        "value": "member-directory"
      }
    ]
  ]
}
```

### Conversation with the user:

> **Q1: Section key?** → `artwork`
> **Q2: Section label?** → `Artwork`
> **Q3: Order?** → `4`
> **Q4: Can be primary?** → No
> **Q5: Default PMP?** → `member`
> **Q6: I found one taxonomy field: "Medium" (artwork_medium). Should it be filterable?** → Yes
> **Q7: Any fields other than name that are required?** → No, just the ones already marked required in the export

### Validation:

> "This config contains 2 content fields and 3 auto-generated PMP system fields. The acf_group contains 5 total fields. Ready to output?"

### Output — `artwork.json`:

```json
{
  "key": "artwork",
  "label": "Artwork",
  "order": 4,
  "can_be_primary": false,
  "pmp_default": "member",
  "fields": [
    {
      "key": "field_artwork_title",
      "label": "Artwork Title",
      "type": "text",
      "pmp_default": "inherit",
      "filterable": false,
      "taxonomy": null,
      "required": true
    },
    {
      "key": "field_artwork_medium",
      "label": "Medium",
      "type": "taxonomy",
      "pmp_default": "inherit",
      "filterable": true,
      "taxonomy": "artwork_medium",
      "required": false
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
        "choices": {
          "inherit": "Inherit",
          "custom": "Custom"
        },
        "default_value": "inherit",
        "layout": "horizontal"
      },
      {
        "key": "field_md_artwork_privacy_level",
        "label": "Privacy Level",
        "name": "member_directory_artwork_privacy_level",
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
              "field": "field_md_artwork_privacy_mode",
              "operator": "==",
              "value": "custom"
            }
          ]
        ]
      },
      {
        "key": "field_artwork_tab",
        "label": "Artwork",
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
        "key": "field_artwork_medium",
        "label": "Medium",
        "name": "artwork_medium",
        "type": "taxonomy",
        "taxonomy": "artwork_medium",
        "required": 0
      }
    ],
    "location": [
      [
        {
          "param": "post_type",
          "operator": "==",
          "value": "member-directory"
        }
      ]
    ]
  }
}
```

### What happened in the example:

- The `tab` field was **skipped** in the `fields` array (it's a layout field, not content) but **kept** in `acf_group` (which is the original export, untouched except for the three prepended PMP fields).
- The three PMP system fields (`_enabled`, `_privacy_mode`, `_privacy_level`) were **generated** and **prepended** to the `acf_group.fields` array.
- The `required` values were converted from ACF's `1`/`0` integers to proper `true`/`false` booleans in the `fields` array.
- The taxonomy field got `"filterable": true` and `"taxonomy": "artwork_medium"` because the user confirmed it.
- The `acf_group` contains the **full original export** with the PMP system fields prepended — nothing else was changed inside it.
