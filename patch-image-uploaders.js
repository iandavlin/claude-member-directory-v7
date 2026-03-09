/**
 * patch-image-uploaders.js
 *
 * Adds the custom image/gallery upload UI functions and GLightbox init
 * to memdir.js. CRLF-aware patching.
 *
 * Run: node patch-image-uploaders.js
 */

const fs = require('fs');
const path = require('path');
const file = path.join(__dirname, 'assets', 'js', 'memdir.js');

let src = fs.readFileSync(file, 'utf8');
let count = 0;

// ─────────────────────────────────────────────────────────────
// 1. Insert all new functions before the Boot section
// ─────────────────────────────────────────────────────────────

const BOOT_ANCHOR = "\t// -----------------------------------------------------------------------\r\n\t// Boot\r\n\t// -----------------------------------------------------------------------";

const NEW_FUNCTIONS = `\t// -----------------------------------------------------------------------
\t// Custom image / gallery upload UIs
\t// -----------------------------------------------------------------------

\t/**
\t * Collect header-tab field keys from a section's tab buttons.
\t * Returns an array of ACF field keys that belong to the header tab.
\t */
\tfunction getHeaderFieldKeys( section ) {
\t\tvar keys = [];
\t\tsection.querySelectorAll( '.memdir-section-controls__tab-item' ).forEach( function ( btn ) {
\t\t\tif ( ( btn.dataset.tab || '' ).toLowerCase().indexOf( 'header' ) !== -1 ) {
\t\t\t\ttry { keys = JSON.parse( btn.dataset.fieldKeys || '[]' ); } catch ( e ) { /* ignore */ }
\t\t\t}
\t\t} );
\t\treturn keys;
\t}

\t/**
\t * Replace ACF\u2019s native image uploader with a custom inline UI
\t * that does \u201Cimage in, image out\u201D (delete old attachment on upload).
\t */
\tfunction replaceImageUploader( field, fieldKey, postId ) {
\t\tvar acfUploader = field.querySelector( '.acf-image-uploader' );
\t\tif ( ! acfUploader ) { return; }

\t\t// Read current state from ACF hidden input.
\t\tvar hiddenInput = acfUploader.querySelector( 'input[type="hidden"]' );
\t\tvar currentId   = hiddenInput ? ( hiddenInput.value || '' ) : '';
\t\tvar currentImg  = acfUploader.querySelector( '.show-if-value img' );
\t\tvar currentSrc  = currentImg ? ( currentImg.getAttribute( 'src' ) || '' ) : '';
\t\tvar currentCap  = '';

\t\t// Hide ACF native uploader.
\t\tacfUploader.style.display = 'none';

\t\t// Build custom UI.
\t\tvar wrap = document.createElement( 'div' );
\t\twrap.className = 'memdir-image-upload';

\t\t// Preview image.
\t\tvar preview = document.createElement( 'img' );
\t\tpreview.className = 'memdir-image-upload__preview';
\t\tpreview.src = currentSrc;
\t\tpreview.alt = 'Current image';
\t\tif ( ! currentSrc ) { preview.style.display = 'none'; }
\t\twrap.appendChild( preview );

\t\t// Caption input.
\t\tvar captionInput = document.createElement( 'input' );
\t\tcaptionInput.type = 'text';
\t\tcaptionInput.className = 'memdir-image-upload__caption';
\t\tcaptionInput.placeholder = 'Add a caption\u2026';
\t\tcaptionInput.value = currentCap;
\t\tcaptionInput.dataset.memdirSkip = '1';
\t\tif ( ! currentId ) { captionInput.style.display = 'none'; }
\t\twrap.appendChild( captionInput );

\t\t// Status.
\t\tvar status = document.createElement( 'p' );
\t\tstatus.className = 'memdir-image-upload__status';
\t\twrap.appendChild( status );

\t\t// Hidden file input.
\t\tvar fileInput = document.createElement( 'input' );
\t\tfileInput.type = 'file';
\t\tfileInput.accept = 'image/*';
\t\tfileInput.style.display = 'none';
\t\twrap.appendChild( fileInput );

\t\t// Buttons.
\t\tvar btnRow = document.createElement( 'div' );
\t\tbtnRow.className = 'memdir-image-upload__actions';

\t\tvar uploadBtn = document.createElement( 'button' );
\t\tuploadBtn.type = 'button';
\t\tuploadBtn.className = 'memdir-image-upload__btn';
\t\tuploadBtn.textContent = currentSrc ? 'Replace Image' : 'Upload Image';
\t\tbtnRow.appendChild( uploadBtn );

\t\tvar deleteBtn = document.createElement( 'button' );
\t\tdeleteBtn.type = 'button';
\t\tdeleteBtn.className = 'memdir-image-upload__btn memdir-image-upload__btn--delete';
\t\tdeleteBtn.textContent = 'Remove';
\t\tif ( ! currentId ) { deleteBtn.style.display = 'none'; }
\t\tbtnRow.appendChild( deleteBtn );

\t\twrap.appendChild( btnRow );

\t\tacfUploader.parentNode.insertBefore( wrap, acfUploader.nextSibling );

\t\t// --- Upload handler ---
\t\tuploadBtn.addEventListener( 'click', function () { fileInput.click(); } );

\t\tfileInput.addEventListener( 'change', function () {
\t\t\tvar file = fileInput.files[ 0 ];
\t\t\tif ( ! file ) { return; }

\t\t\tstatus.textContent = 'Uploading\\u2026';
\t\t\tuploadBtn.disabled = true;
\t\t\tdeleteBtn.disabled = true;

\t\t\tvar fd = new FormData();
\t\t\tfd.append( 'action',    'memdir_ajax_upload_image' );
\t\t\tfd.append( 'nonce',     window.mdAjax.nonce );
\t\t\tfd.append( 'post_id',   postId );
\t\t\tfd.append( 'field_key', fieldKey );
\t\t\tfd.append( 'image',     file );

\t\t\tvar cap = captionInput.value.trim();
\t\t\tif ( cap ) { fd.append( 'caption', cap ); }

\t\t\tfetch( window.mdAjax.ajaxurl, { method: 'POST', body: fd } )
\t\t\t\t.then( function ( r ) { return r.json(); } )
\t\t\t\t.then( function ( res ) {
\t\t\t\t\tif ( res.success && res.data ) {
\t\t\t\t\t\tpreview.src = res.data.url;
\t\t\t\t\t\tpreview.style.display = '';
\t\t\t\t\t\tcurrentId = String( res.data.id );
\t\t\t\t\t\tif ( hiddenInput ) { hiddenInput.value = currentId; }
\t\t\t\t\t\tcaptionInput.style.display = '';
\t\t\t\t\t\tstatus.textContent = 'Image updated.';
\t\t\t\t\t\tuploadBtn.textContent = 'Replace Image';
\t\t\t\t\t\tdeleteBtn.style.display = '';
\t\t\t\t\t} else {
\t\t\t\t\t\tstatus.textContent = 'Error: ' + ( res.data && res.data.message ? res.data.message : 'Upload failed.' );
\t\t\t\t\t}
\t\t\t\t\tuploadBtn.disabled = false;
\t\t\t\t\tdeleteBtn.disabled = false;
\t\t\t\t\tfileInput.value = '';
\t\t\t\t} )
\t\t\t\t.catch( function () {
\t\t\t\t\tstatus.textContent = 'Network error.';
\t\t\t\t\tuploadBtn.disabled = false;
\t\t\t\t\tdeleteBtn.disabled = false;
\t\t\t\t\tfileInput.value = '';
\t\t\t\t} );
\t\t} );

\t\t// --- Delete handler ---
\t\tdeleteBtn.addEventListener( 'click', function () {
\t\t\tif ( ! confirm( 'Remove this image? The file will be permanently deleted.' ) ) { return; }

\t\t\tstatus.textContent = 'Removing\\u2026';
\t\t\tuploadBtn.disabled = true;
\t\t\tdeleteBtn.disabled = true;

\t\t\tvar fd = new FormData();
\t\t\tfd.append( 'action',    'memdir_ajax_delete_image' );
\t\t\tfd.append( 'nonce',     window.mdAjax.nonce );
\t\t\tfd.append( 'post_id',   postId );
\t\t\tfd.append( 'field_key', fieldKey );

\t\t\tfetch( window.mdAjax.ajaxurl, { method: 'POST', body: fd } )
\t\t\t\t.then( function ( r ) { return r.json(); } )
\t\t\t\t.then( function ( res ) {
\t\t\t\t\tif ( res.success ) {
\t\t\t\t\t\tpreview.src = '';
\t\t\t\t\t\tpreview.style.display = 'none';
\t\t\t\t\t\tcurrentId = '';
\t\t\t\t\t\tif ( hiddenInput ) { hiddenInput.value = ''; }
\t\t\t\t\t\tcaptionInput.value = '';
\t\t\t\t\t\tcaptionInput.style.display = 'none';
\t\t\t\t\t\tstatus.textContent = 'Image removed.';
\t\t\t\t\t\tuploadBtn.textContent = 'Upload Image';
\t\t\t\t\t\tdeleteBtn.style.display = 'none';
\t\t\t\t\t} else {
\t\t\t\t\t\tstatus.textContent = 'Error: ' + ( res.data && res.data.message ? res.data.message : 'Remove failed.' );
\t\t\t\t\t}
\t\t\t\t\tuploadBtn.disabled = false;
\t\t\t\t\tdeleteBtn.disabled = false;
\t\t\t\t} )
\t\t\t\t.catch( function () {
\t\t\t\t\tstatus.textContent = 'Network error.';
\t\t\t\t\tuploadBtn.disabled = false;
\t\t\t\t\tdeleteBtn.disabled = false;
\t\t\t\t} );
\t\t} );

\t\t// --- Caption blur-save ---
\t\tcaptionInput.addEventListener( 'blur', function () {
\t\t\tif ( ! currentId ) { return; }
\t\t\tvar fd = new FormData();
\t\t\tfd.append( 'action',        'memdir_ajax_update_caption' );
\t\t\tfd.append( 'nonce',         window.mdAjax.nonce );
\t\t\tfd.append( 'post_id',       postId );
\t\t\tfd.append( 'attachment_id', currentId );
\t\t\tfd.append( 'caption',       captionInput.value.trim() );
\t\t\tfetch( window.mdAjax.ajaxurl, { method: 'POST', body: fd } ).catch( function () {} );
\t\t} );
\t}

\t/**
\t * Add a single thumbnail item to a gallery grid.
\t * Returns the created item element.
\t */
\tfunction addGalleryThumb( grid, attachmentId, src, caption, fieldKey, postId, statusEl ) {
\t\tvar item = document.createElement( 'div' );
\t\titem.className = 'memdir-gallery-upload__item';
\t\titem.dataset.attachmentId = attachmentId;

\t\tvar img = document.createElement( 'img' );
\t\timg.src = src;
\t\timg.alt = 'Gallery image';
\t\titem.appendChild( img );

\t\tvar removeBtn = document.createElement( 'button' );
\t\tremoveBtn.type = 'button';
\t\tremoveBtn.className = 'memdir-gallery-upload__remove';
\t\tremoveBtn.innerHTML = '&times;';
\t\tremoveBtn.title = 'Remove image';
\t\titem.appendChild( removeBtn );

\t\t// Per-image caption.
\t\tvar capInput = document.createElement( 'input' );
\t\tcapInput.type = 'text';
\t\tcapInput.className = 'memdir-gallery-upload__caption';
\t\tcapInput.placeholder = 'Caption';
\t\tcapInput.value = caption || '';
\t\tcapInput.dataset.memdirSkip = '1';
\t\titem.appendChild( capInput );

\t\tgrid.appendChild( item );

\t\t// Remove handler.
\t\tremoveBtn.addEventListener( 'click', function () {
\t\t\tif ( ! confirm( 'Remove this image from the gallery?' ) ) { return; }

\t\t\tstatusEl.textContent = 'Removing\\u2026';
\t\t\tremoveBtn.disabled = true;

\t\t\tvar fd = new FormData();
\t\t\tfd.append( 'action',        'memdir_ajax_gallery_remove' );
\t\t\tfd.append( 'nonce',         window.mdAjax.nonce );
\t\t\tfd.append( 'post_id',       postId );
\t\t\tfd.append( 'field_key',     fieldKey );
\t\t\tfd.append( 'attachment_id', attachmentId );

\t\t\tfetch( window.mdAjax.ajaxurl, { method: 'POST', body: fd } )
\t\t\t\t.then( function ( r ) { return r.json(); } )
\t\t\t\t.then( function ( res ) {
\t\t\t\t\tif ( res.success ) {
\t\t\t\t\t\titem.remove();
\t\t\t\t\t\tstatusEl.textContent = 'Image removed.';
\t\t\t\t\t\tvar acfField = grid.closest( '.acf-field' );
\t\t\t\t\t\tif ( acfField ) { syncGalleryHiddenInputs( acfField, fieldKey, grid ); }
\t\t\t\t\t} else {
\t\t\t\t\t\tstatusEl.textContent = 'Error: ' + ( res.data && res.data.message ? res.data.message : 'Remove failed.' );
\t\t\t\t\t\tremoveBtn.disabled = false;
\t\t\t\t\t}
\t\t\t\t} )
\t\t\t\t.catch( function () {
\t\t\t\t\tstatusEl.textContent = 'Network error.';
\t\t\t\t\tremoveBtn.disabled = false;
\t\t\t\t} );
\t\t} );

\t\t// Caption blur-save.
\t\tcapInput.addEventListener( 'blur', function () {
\t\t\tvar fd = new FormData();
\t\t\tfd.append( 'action',        'memdir_ajax_update_caption' );
\t\t\tfd.append( 'nonce',         window.mdAjax.nonce );
\t\t\tfd.append( 'post_id',       postId );
\t\t\tfd.append( 'attachment_id', attachmentId );
\t\t\tfd.append( 'caption',       capInput.value.trim() );
\t\t\tfetch( window.mdAjax.ajaxurl, { method: 'POST', body: fd } ).catch( function () {} );
\t\t} );

\t\treturn item;
\t}

\t/**
\t * Rebuild hidden inputs inside the .acf-field wrapper so saveSection()
\t * collects the correct gallery array.
\t */
\tfunction syncGalleryHiddenInputs( field, fieldKey, grid ) {
\t\tfield.querySelectorAll( 'input.memdir-gallery-sync' ).forEach( function ( el ) {
\t\t\tel.remove();
\t\t} );
\t\tgrid.querySelectorAll( '.memdir-gallery-upload__item' ).forEach( function ( item ) {
\t\t\tvar input = document.createElement( 'input' );
\t\t\tinput.type      = 'hidden';
\t\t\tinput.className = 'memdir-gallery-sync';
\t\t\tinput.name      = 'acf[' + fieldKey + '][]';
\t\t\tinput.value     = item.dataset.attachmentId;
\t\t\tfield.appendChild( input );
\t\t} );
\t}

\t/**
\t * Replace ACF\u2019s native gallery uploader with a custom grid UI.
\t * Each image has a remove button and caption input.
\t */
\tfunction replaceGalleryUploader( field, fieldKey, postId ) {
\t\tvar acfGallery = field.querySelector( '.acf-gallery' );
\t\tif ( ! acfGallery ) { return; }

\t\t// Read existing gallery items.
\t\tvar existingItems = [];
\t\tacfGallery.querySelectorAll( '.acf-gallery-attachment' ).forEach( function ( att ) {
\t\t\tvar img   = att.querySelector( 'img' );
\t\t\tvar input = att.querySelector( 'input[type="hidden"]' );
\t\t\tif ( input && input.value ) {
\t\t\t\texistingItems.push( { id: input.value, src: img ? img.src : '' } );
\t\t\t}
\t\t} );

\t\t// Mark ACF\u2019s original inputs so saveSection() skips them.
\t\tacfGallery.querySelectorAll( 'input' ).forEach( function ( inp ) {
\t\t\tinp.dataset.memdirSkip = '1';
\t\t} );

\t\t// Hide ACF native gallery.
\t\tacfGallery.style.display = 'none';

\t\t// Build custom UI.
\t\tvar wrap = document.createElement( 'div' );
\t\twrap.className = 'memdir-gallery-upload';

\t\tvar grid = document.createElement( 'div' );
\t\tgrid.className = 'memdir-gallery-upload__grid';
\t\twrap.appendChild( grid );

\t\tvar status = document.createElement( 'p' );
\t\tstatus.className = 'memdir-gallery-upload__status';
\t\twrap.appendChild( status );

\t\tvar fileInput = document.createElement( 'input' );
\t\tfileInput.type   = 'file';
\t\tfileInput.accept = 'image/*';
\t\tfileInput.style.display = 'none';
\t\twrap.appendChild( fileInput );

\t\tvar addBtn = document.createElement( 'button' );
\t\taddBtn.type      = 'button';
\t\taddBtn.className = 'memdir-gallery-upload__add';
\t\taddBtn.textContent = 'Add Image';
\t\twrap.appendChild( addBtn );

\t\tacfGallery.parentNode.insertBefore( wrap, acfGallery.nextSibling );

\t\t// Render existing items (captions loaded async).
\t\texistingItems.forEach( function ( item ) {
\t\t\taddGalleryThumb( grid, item.id, item.src, '', fieldKey, postId, status );
\t\t} );

\t\t// Sync hidden inputs for initial state.
\t\tsyncGalleryHiddenInputs( field, fieldKey, grid );

\t\t// --- Add handler ---
\t\taddBtn.addEventListener( 'click', function () { fileInput.click(); } );

\t\tfileInput.addEventListener( 'change', function () {
\t\t\tvar file = fileInput.files[ 0 ];
\t\t\tif ( ! file ) { return; }

\t\t\tstatus.textContent = 'Uploading\\u2026';
\t\t\taddBtn.disabled = true;

\t\t\tvar fd = new FormData();
\t\t\tfd.append( 'action',    'memdir_ajax_gallery_upload' );
\t\t\tfd.append( 'nonce',     window.mdAjax.nonce );
\t\t\tfd.append( 'post_id',   postId );
\t\t\tfd.append( 'field_key', fieldKey );
\t\t\tfd.append( 'image',     file );

\t\t\tfetch( window.mdAjax.ajaxurl, { method: 'POST', body: fd } )
\t\t\t\t.then( function ( r ) { return r.json(); } )
\t\t\t\t.then( function ( res ) {
\t\t\t\t\tif ( res.success && res.data ) {
\t\t\t\t\t\taddGalleryThumb( grid, String( res.data.id ), res.data.url, '', fieldKey, postId, status );
\t\t\t\t\t\tsyncGalleryHiddenInputs( field, fieldKey, grid );
\t\t\t\t\t\tstatus.textContent = 'Image added.';
\t\t\t\t\t} else {
\t\t\t\t\t\tstatus.textContent = 'Error: ' + ( res.data && res.data.message ? res.data.message : 'Upload failed.' );
\t\t\t\t\t}
\t\t\t\t\taddBtn.disabled = false;
\t\t\t\t\tfileInput.value = '';
\t\t\t\t} )
\t\t\t\t.catch( function () {
\t\t\t\t\tstatus.textContent = 'Network error.';
\t\t\t\t\taddBtn.disabled = false;
\t\t\t\t\tfileInput.value = '';
\t\t\t\t} );
\t\t} );
\t}

\t/**
\t * Scan all edit-mode sections for image and gallery fields,
\t * replace ACF\u2019s native uploaders with custom inline UIs.
\t * Skips fields owned by the header editing system.
\t */
\tfunction initImageUploaders() {
\t\tdocument.querySelectorAll( '.memdir-section--edit' ).forEach( function ( section ) {
\t\t\tvar postId = section.dataset.postId || '';
\t\t\tif ( ! postId ) { return; }

\t\t\tvar headerKeys   = getHeaderFieldKeys( section );
\t\t\tvar fieldContent = section.querySelector( '.memdir-field-content' );
\t\t\tif ( ! fieldContent ) { return; }

\t\t\t// Single image fields.
\t\t\tfieldContent.querySelectorAll( '.acf-field[data-type="image"]' ).forEach( function ( field ) {
\t\t\t\tvar key = field.dataset.key || '';
\t\t\t\tif ( headerKeys.indexOf( key ) !== -1 ) { return; }
\t\t\t\tif ( field.closest( 'dialog' ) ) { return; }
\t\t\t\treplaceImageUploader( field, key, postId );
\t\t\t} );

\t\t\t// Gallery fields.
\t\t\tfieldContent.querySelectorAll( '.acf-field[data-type="gallery"]' ).forEach( function ( field ) {
\t\t\t\tvar key = field.dataset.key || '';
\t\t\t\tif ( headerKeys.indexOf( key ) !== -1 ) { return; }
\t\t\t\tif ( field.closest( 'dialog' ) ) { return; }
\t\t\t\treplaceGalleryUploader( field, key, postId );
\t\t\t} );
\t\t} );
\t}

\t/**
\t * Initialise GLightbox on all .glightbox links in view-mode sections.
\t */
\tfunction initLightbox() {
\t\tif ( typeof GLightbox === 'undefined' ) { return; }
\t\tGLightbox( {
\t\t\tselector:  '.glightbox',
\t\t\ttouchNavigation: true,
\t\t\tloop:      true,
\t\t\tcloseOnOutsideClick: true,
\t\t} );
\t}

`;

// Normalize to CRLF
const NEW_FUNCTIONS_CRLF = NEW_FUNCTIONS.replace(/\r\n/g, '\n').replace(/\n/g, '\r\n');

if (src.includes(BOOT_ANCHOR)) {
  src = src.replace(BOOT_ANCHOR, NEW_FUNCTIONS_CRLF + BOOT_ANCHOR);
  count++;
  console.log('✓ Inserted image/gallery uploader functions + lightbox init');
} else {
  console.log('⚠ Could not find Boot section anchor');
}

// ─────────────────────────────────────────────────────────────
// 2. Add initImageUploaders() and initLightbox() calls to boot
// ─────────────────────────────────────────────────────────────

const BOOT_CALL_ANCHOR = "\t\tinitHeaderEditing();  // per-element header pencils + modals";
const BOOT_CALL_NEW    = "\t\tinitHeaderEditing();  // per-element header pencils + modals\r\n\t\tinitImageUploaders(); // custom image/gallery upload UIs\r\n\t\tinitLightbox();       // GLightbox on view-mode images";

if (src.includes(BOOT_CALL_ANCHOR) && !src.includes('initImageUploaders')) {
  src = src.replace(BOOT_CALL_ANCHOR, BOOT_CALL_NEW);
  count++;
  console.log('✓ Added initImageUploaders() + initLightbox() to boot sequence');
} else if (src.includes('initImageUploaders')) {
  console.log('⏩ Boot calls already present');
} else {
  console.log('⚠ Could not find boot call anchor');
}

if (count > 0) {
  fs.writeFileSync(file, src, 'utf8');
  console.log(`\nDone — ${count} patches applied to memdir.js`);
} else {
  console.log('\nNo changes made.');
}
