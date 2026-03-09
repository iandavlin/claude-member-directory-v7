# Plan: "Image In, Image Out" Upload UI for All Image & Gallery Fields

## Context

The header avatar already has a custom upload modal that deletes the old attachment when a new image is uploaded ("one in, one out"), preventing orphaned media. All other image and gallery fields use ACF's native uploader, which opens the WP media library and never cleans up replaced/removed attachments.

**Goal**: Replace ACF's native image/gallery upload UI in section edit forms with custom inline uploaders that follow the same "image in, image out" pattern. Create reusable functions for both single-image and gallery fields.

---

## Files to Modify

| File | What changes |
|------|-------------|
| `includes/AcfFormHelper.php` | Add 4 new AJAX handlers + register in `init()` |
| `assets/js/memdir.js` | Add 6 new functions + 1 boot call |
| `assets/css/memdir.css` | Add image-upload + gallery-upload style blocks |

No existing functionality is modified beyond adding action registrations and one boot call.

---

## PHP: 4 New AJAX Handlers (AcfFormHelper.php)

All follow the existing `handle_avatar_upload()` pattern: nonce → post_id → permission → field_key validation.

### 1. `handle_image_upload()` → `wp_ajax_memdir_ajax_upload_image`
Generic single-image upload. Gets old attachment ID, uploads new via `media_handle_upload()`, calls `update_field()`, deletes old attachment. Returns `{ url, id }` with `medium`-sized URL for inline preview.

### 2. `handle_delete_image()` → `wp_ajax_memdir_ajax_delete_image`
Clears an image field and deletes its attachment. Calls `update_field( key, '', post_id )` then `wp_delete_attachment()`. Returns success.

### 3. `handle_gallery_upload()` → `wp_ajax_memdir_ajax_gallery_upload`
Appends one image to a gallery field. Gets current gallery array via `get_field( key, post_id, false )`, uploads new image, appends ID, calls `update_field()`. Returns `{ id, url }`.

### 4. `handle_gallery_remove()` → `wp_ajax_memdir_ajax_gallery_remove`
Removes one image from a gallery and deletes the attachment. Gets gallery array, filters out the ID, calls `update_field()`, then `wp_delete_attachment()`. POST param: `attachment_id`.

---

## JS: 6 New Functions (memdir.js)

### Boot sequence change
Add `initImageUploaders()` call **after** `initHeaderEditing()` in the DOMContentLoaded listener (line 2113):
```
initHeaderEditing();
initImageUploaders();   // ← new
```

### `getHeaderFieldKeys( section )` — helper
Reads the Header tab button's `data-field-keys` JSON to get the set of field keys owned by the header editing system. Used to skip those fields.

### `initImageUploaders()` — entry point
Loops through `.memdir-section--edit` sections. For each, finds all `.acf-field[data-type="image"]` and `.acf-field[data-type="gallery"]` fields, skips any whose `data-key` is in the header field key set (or is inside a `<dialog>`), then calls the appropriate replacer function.

### `replaceImageUploader( field, fieldKey, postId )` — single image
- Hides ACF's `.acf-image-uploader` via `display: none`
- Reads current attachment ID from ACF's hidden input + current preview src
- Injects inline UI: preview image + "Upload/Replace Image" button + "Remove" button + status text
- Upload handler: POSTs to `memdir_ajax_upload_image`, updates preview + ACF hidden input on success
- Delete handler: POSTs to `memdir_ajax_delete_image`, clears preview + ACF hidden input

### `replaceGalleryUploader( field, fieldKey, postId )` — gallery
- Hides ACF's `.acf-gallery` via `display: none`
- Marks ACF's original gallery inputs with `data-memdir-skip` so `saveSection()` ignores them
- Reads current gallery items from ACF DOM (attachment IDs + thumbnail src)
- Injects inline UI: thumbnail grid + "Add Image" button + status text
- Each thumbnail has an × remove button
- Add handler: POSTs to `memdir_ajax_gallery_upload`, appends new thumb, syncs hidden inputs
- Remove handler: POSTs to `memdir_ajax_gallery_remove`, removes thumb, syncs hidden inputs

### `addGalleryThumb( grid, id, src, fieldKey, postId, status )` — creates one gallery thumbnail with remove button

### `syncGalleryHiddenInputs( field, fieldKey, grid )` — keeps hidden inputs in sync
After each gallery add/remove, rebuilds a set of `<input type="hidden" class="memdir-gallery-sync">` elements inside the `.acf-field` wrapper so `saveSection()` collects the correct gallery array.

---

## CSS: New Style Blocks (memdir.css)

### `.memdir-image-upload*` — single image inline UI
- `__preview`: `max-width: 200px`, rounded corners, `object-fit: cover`
- `__actions`: flex row with gap for Upload + Remove buttons
- `__btn`: matches existing `--md-green-sage` button style
- `__btn--delete`: red outline/hover style (matches avatar delete button)
- `__status`: 13px muted text for upload/remove feedback

### `.memdir-gallery-upload*` — gallery inline UI
- `__grid`: CSS grid, `repeat(auto-fill, minmax(100px, 1fr))`, 8px gap
- `__item`: relative positioned, `aspect-ratio: 1`, rounded corners, `overflow: hidden`
- `__item img`: `object-fit: cover`, fills container
- `__remove`: absolute-positioned × button, top-right, appears on hover (opacity transition)
- `__add`: matches green sage button style
- `__status`: same muted status text

---

## How It Interacts with `saveSection()`

**Single image**: Uploads/deletes are instant via AJAX (field is already saved in DB). ACF's hidden input value is updated in JS to match, so `saveSection()` sends the same ID — idempotent.

**Gallery**: Same instant AJAX pattern. ACF's original gallery inputs get `data-memdir-skip` so they're ignored. New `memdir-gallery-sync` hidden inputs are created inside the `.acf-field` wrapper, which `saveSection()` collects correctly.

---

## Implementation Order

1. **PHP** — Add 4 handlers + registrations (purely additive, no existing code changes)
2. **CSS** — Add style blocks at end of file (purely additive)
3. **JS** — Add functions + boot call (Write tool or Node.js script due to CRLF)

---

## Verification

- Load a member profile in edit mode
- Switch to a tab that has an image field → should see custom UI (preview + Upload/Remove buttons) instead of ACF's native uploader
- Upload an image → check Media Library: new attachment exists, old one is gone
- Remove the image → check Media Library: attachment deleted
- Switch to a tab with a gallery → should see thumbnail grid + Add Image button
- Add images → verify each appears in grid and in Media Library
- Remove one → verify it's gone from grid and from Media Library
- Click section Save → verify page reloads with correct state
- Verify header avatar still works as before (should be skipped by `initImageUploaders()`)
