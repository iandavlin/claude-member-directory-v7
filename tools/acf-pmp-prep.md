ACF Field Group → PMP Field Injector

You are injecting Member Directory PMP system fields into a raw ACF field group JSON export. The output is the original field group JSON with two system fields prepended to its fields array. This output can be (a) re-imported into ACF to register the section with the correct field structure, or (b) fed directly into the acf-to-config skill.

How This Works
The Member Directory plugin requires two system fields on every section's ACF field group:

1. An enabled toggle (true_false) — stores whether the member has activated this section on their profile.
2. A visibility selector (button_group, 4 states) — stores the per-section PMP override: Inherit (use global default), Public, Member, or Private.

These fields must appear at the top of the acf_group.fields array, before any tab or content fields. They follow the naming convention member_directory_{section_key}_* so the plugin can resolve them by key at runtime.

This skill only adds those two fields. It does not modify, reorder, rename, or remove any of the original fields.


Step 1 — Get the Section Key
Ask the user: "What is the section key for this field group?" The key is a short lowercase slug — underscores only, no hyphens, no spaces. Examples: profile, business, craft_and_skills. Confirm the key before proceeding.


Step 2 — Accept and Validate the Input
Accept the ACF field group JSON from the user. Then run two checks:

Already-injected check
Scan the fields array for any object whose key exactly matches:
  field_md_{section_key}_enabled
  field_md_{section_key}_privacy_mode

If either is found, stop immediately and tell the user:
"PMP fields already present in this group for section key '{section_key}'. No injection needed."

Structure check
Confirm the input JSON has a top-level "fields" array. If missing or malformed, stop and ask the user to paste a valid ACF field group export (the array exported from ACF → Tools → Export Field Groups).


Step 3 — Build the Two System Fields
Substitute the actual section key for {section_key} in both field definitions below.

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


Step 4 — Assemble and Output
Return the original field group JSON completely unchanged, except with the two system fields from Step 3 prepended to the fields array (Field 1 first, then Field 2, then all original fields in their original order).

Before outputting, confirm:
"Ready to inject 2 PMP system fields into group '{group_key}' for section key '{section_key}'. {N} original fields will follow unchanged."

Wait for the user to confirm, then output the complete JSON.


Next Step
Feed the output of this skill directly into the acf-to-config skill. That skill's skip rules will detect the injected PMP fields and exclude them from the content field extraction — they will not be duplicated.
