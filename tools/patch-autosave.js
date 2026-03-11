#!/usr/bin/env node
/**
 * patch-autosave.js — Adds per-field AJAX autosave to memdir.js.
 *
 * Changes:
 *   1. Replaces initSectionSave/markUnsaved/saveSection with
 *      initFieldAutosave and supporting utilities.
 *   2. Refactors createMiniModal Save button to "Done" (no saveSection call).
 *   3. Adds initFieldAutosave() to boot sequence, removes initSectionSave().
 *   4. Suppresses ACF beforeunload on boot.
 */

const fs = require('fs');
const path = require('path');

const filePath = path.join(__dirname, '..', 'assets', 'js', 'memdir.js');
let src = fs.readFileSync(filePath, 'utf8');

// ─────────────────────────────────────────────────────────────────────────────
// 1. Replace section 4 (initSectionSave + markUnsaved + saveSection) with
//    autosave utilities + initFieldAutosave
// ─────────────────────────────────────────────────────────────────────────────

const oldSection4Start = '\t// -----------------------------------------------------------------------\r\n\t// 4. Section save (AJAX)';
const oldSection4End = '\t// -----------------------------------------------------------------------\r\n\t// 5. Right panel controls';

const idx4Start = src.indexOf(oldSection4Start);
const idx4End = src.indexOf(oldSection4End);

if (idx4Start === -1 || idx4End === -1) {
	console.error('Could not find section 4 boundaries');
	process.exit(1);
}

const newSection4 = `\t// -----------------------------------------------------------------------
\t// 4. Field Autosave (AJAX)
\t//
\t// Each .acf-field saves its value individually via AJAX on blur/change.
\t// Reuses the existing md_save_section endpoint (which calls update_field
\t// per key). No save button, no page reload, no unsaved-changes banner.
\t//
\t// Per-field status indicator: spinner -> checkmark -> (auto-clear).
\t// Error state shows X and stays until the next successful save.
\t// -----------------------------------------------------------------------

\t/**
\t * Get or create a status indicator element inside an .acf-field's label.
\t *
\t * @param {Element} acfField  The .acf-field wrapper.
\t * @returns {Element}
\t */
\tfunction getOrCreateIndicator( acfField ) {
\t\tvar existing = acfField.querySelector( '.memdir-field-status' );
\t\tif ( existing ) { return existing; }

\t\tvar el = document.createElement( 'span' );
\t\tel.className = 'memdir-field-status';
\t\tvar label = acfField.querySelector( '.acf-label' );
\t\tif ( label ) { label.appendChild( el ); }
\t\treturn el;
\t}

\t/**
\t * Set the visual state of a field status indicator.
\t *
\t * @param {Element} el     The .memdir-field-status element.
\t * @param {string}  state  'saving' | 'saved' | 'error' | ''
\t */
\tfunction setIndicatorState( el, state ) {
\t\tel.className = 'memdir-field-status' + ( state ? ' memdir-field-status--' + state : '' );
\t\tif ( state === 'saving' ) {
\t\t\tel.textContent = '';
\t\t} else if ( state === 'saved' ) {
\t\t\tel.textContent = '\\u2713';
\t\t\tclearTimeout( el._clearTimer );
\t\t\tel._clearTimer = setTimeout( function () {
\t\t\t\tel.className = 'memdir-field-status';
\t\t\t\tel.textContent = '';
\t\t\t}, 3000 );
\t\t} else if ( state === 'error' ) {
\t\t\tel.textContent = '\\u2717';
\t\t} else {
\t\t\tel.textContent = '';
\t\t}
\t}

\t/**
\t * Save a single ACF field value via AJAX (reuses md_save_section endpoint).
\t *
\t * @param {string}       postId    Member directory post ID.
\t * @param {string}       fieldKey  ACF field key (e.g. 'field_md_profile_name').
\t * @param {*}            value     The value to save (string, array, or object).
\t * @param {Element}      acfField  The .acf-field wrapper (for status indicator).
\t * @returns {Promise}
\t */
\tfunction saveField( postId, fieldKey, value, acfField ) {
\t\tvar indicator = getOrCreateIndicator( acfField );
\t\tsetIndicatorState( indicator, 'saving' );

\t\tvar formData = new FormData();
\t\tformData.set( 'action',  'md_save_section' );
\t\tformData.set( 'nonce',   ( window.mdAjax && window.mdAjax.nonce ) ? window.mdAjax.nonce : '' );
\t\tformData.set( 'post_id', postId );

\t\t// Append the value in the format the existing handler expects.
\t\tif ( Array.isArray( value ) ) {
\t\t\tif ( value.length === 0 ) {
\t\t\t\t// Empty array: send empty string so ACF clears the field.
\t\t\t\tformData.set( 'acf[' + fieldKey + ']', '' );
\t\t\t} else {
\t\t\t\tvalue.forEach( function ( v ) {
\t\t\t\t\tformData.append( 'acf[' + fieldKey + '][]', v );
\t\t\t\t} );
\t\t\t}
\t\t} else if ( typeof value === 'object' && value !== null ) {
\t\t\tObject.keys( value ).forEach( function ( k ) {
\t\t\t\tformData.append( 'acf[' + fieldKey + '][' + k + ']', value[ k ] );
\t\t\t} );
\t\t} else {
\t\t\tformData.set( 'acf[' + fieldKey + ']', value );
\t\t}

\t\tvar ajaxUrl = ( window.mdAjax && window.mdAjax.ajaxurl )
\t\t\t? window.mdAjax.ajaxurl
\t\t\t: '/wp-admin/admin-ajax.php';

\t\treturn fetch( ajaxUrl, {
\t\t\tmethod:      'POST',
\t\t\tcredentials: 'same-origin',
\t\t\tbody:        formData,
\t\t} )
\t\t\t.then( function ( r ) { return r.json(); } )
\t\t\t.then( function ( data ) {
\t\t\t\tif ( data.success ) {
\t\t\t\t\tsetIndicatorState( indicator, 'saved' );
\t\t\t\t} else {
\t\t\t\t\tsetIndicatorState( indicator, 'error' );
\t\t\t\t\tconsole.error( 'MemberDirectory: field save error', fieldKey, data );
\t\t\t\t}
\t\t\t\treturn data;
\t\t\t} )
\t\t\t.catch( function ( err ) {
\t\t\t\tsetIndicatorState( indicator, 'error' );
\t\t\t\tconsole.error( 'MemberDirectory: field save failed', fieldKey, err );
\t\t\t} );
\t}

\t/**
\t * Wait for a TinyMCE editor instance to become available.
\t *
\t * @param {string}   editorId  The textarea ID that TinyMCE wraps.
\t * @param {Function} callback  Called with the editor instance.
\t */
\tfunction waitForTinyMCE( editorId, callback ) {
\t\tvar attempts = 0;
\t\tvar check = setInterval( function () {
\t\t\tattempts++;
\t\t\tif ( window.tinyMCE && tinyMCE.get( editorId ) ) {
\t\t\t\tclearInterval( check );
\t\t\t\tcallback( tinyMCE.get( editorId ) );
\t\t\t}
\t\t\tif ( attempts > 50 ) { clearInterval( check ); }
\t\t}, 100 );
\t}

\t/**
\t * Collect all sub-values from an ACF google_map field into an object.
\t *
\t * @param {Element} acfField  The .acf-field[data-type="google_map"] wrapper.
\t * @returns {Object}  { address, lat, lng, ... }
\t */
\tfunction collectGoogleMapValue( acfField ) {
\t\tvar result = {};
\t\tacfField.querySelectorAll( 'input' ).forEach( function ( inp ) {
\t\t\tvar name = inp.name || '';
\t\t\tvar match = name.match( /acf\\[[^\\]]+\\]\\[([^\\]]+)\\]/ );
\t\t\tif ( match ) {
\t\t\t\tresult[ match[1] ] = inp.value;
\t\t\t}
\t\t} );
\t\treturn result;
\t}

\t/**
\t * Extract the current value from an .acf-field based on its type.
\t *
\t * @param {Element} acfField  The .acf-field wrapper.
\t * @returns {*}  The field value, or undefined if extraction fails.
\t */
\tfunction extractFieldValue( acfField ) {
\t\tvar fieldType = ( acfField.dataset.type || '' ).toLowerCase();

\t\tswitch ( fieldType ) {
\t\t\tcase 'text':
\t\t\tcase 'email':
\t\t\tcase 'number':
\t\t\tcase 'url': {
\t\t\t\tvar inp = acfField.querySelector( 'input[type="text"], input[type="email"], input[type="number"], input[type="url"]' );
\t\t\t\treturn inp ? inp.value : undefined;
\t\t\t}

\t\t\tcase 'textarea': {
\t\t\t\tvar ta = acfField.querySelector( 'textarea' );
\t\t\t\treturn ta ? ta.value : undefined;
\t\t\t}

\t\t\tcase 'wysiwyg': {
\t\t\t\tvar wysTA = acfField.querySelector( 'textarea' );
\t\t\t\tif ( ! wysTA ) { return undefined; }
\t\t\t\tvar edId = wysTA.id || '';
\t\t\t\tif ( window.tinyMCE && edId && tinyMCE.get( edId ) ) {
\t\t\t\t\treturn tinyMCE.get( edId ).getContent();
\t\t\t\t}
\t\t\t\treturn wysTA.value;
\t\t\t}

\t\t\tcase 'select': {
\t\t\t\tvar sel = acfField.querySelector( 'select' );
\t\t\t\tif ( ! sel ) { return undefined; }
\t\t\t\tif ( sel.multiple ) {
\t\t\t\t\treturn Array.from( sel.selectedOptions ).map( function ( o ) { return o.value; } ).filter( Boolean );
\t\t\t\t}
\t\t\t\treturn sel.value;
\t\t\t}

\t\t\tcase 'radio': {
\t\t\t\tvar checked = acfField.querySelector( 'input[type="radio"]:checked' );
\t\t\t\treturn checked ? checked.value : '';
\t\t\t}

\t\t\tcase 'checkbox': {
\t\t\t\tvar vals = [];
\t\t\t\tacfField.querySelectorAll( 'input[type="checkbox"]:checked' ).forEach( function ( cb ) {
\t\t\t\t\tif ( cb.value ) { vals.push( cb.value ); }
\t\t\t\t} );
\t\t\t\treturn vals;
\t\t\t}

\t\t\tcase 'true_false': {
\t\t\t\tvar tf = acfField.querySelector( 'input[type="checkbox"]' );
\t\t\t\treturn tf ? ( tf.checked ? '1' : '0' ) : undefined;
\t\t\t}

\t\t\tcase 'taxonomy': {
\t\t\t\tvar taxoSel = acfField.querySelector( 'select' );
\t\t\t\tif ( ! taxoSel ) { return undefined; }
\t\t\t\tif ( taxoSel.multiple ) {
\t\t\t\t\treturn Array.from( taxoSel.selectedOptions ).map( function ( o ) { return o.value; } ).filter( Boolean );
\t\t\t\t}
\t\t\t\treturn taxoSel.value;
\t\t\t}

\t\t\tcase 'google_map': {
\t\t\t\treturn collectGoogleMapValue( acfField );
\t\t\t}

\t\t\tdefault:
\t\t\t\treturn undefined;
\t\t}
\t}

\t/**
\t * Bind autosave events to a single .acf-field based on its type.
\t *
\t * @param {Element} acfField   The .acf-field wrapper.
\t * @param {string}  postId     The member-directory post ID.
\t * @param {string}  fieldKey   The ACF field key.
\t * @param {string}  fieldType  The ACF field type (from data-type).
\t */
\tfunction bindFieldAutosave( acfField, postId, fieldKey, fieldType ) {
\t\tvar debounceTimer = null;

\t\tfunction debouncedSave( delay ) {
\t\t\tclearTimeout( debounceTimer );
\t\t\tdebounceTimer = setTimeout( function () {
\t\t\t\tvar value = extractFieldValue( acfField );
\t\t\t\tif ( value !== undefined ) {
\t\t\t\t\tsaveField( postId, fieldKey, value, acfField );
\t\t\t\t}
\t\t\t}, delay );
\t\t}

\t\tfunction immediateSave() {
\t\t\tclearTimeout( debounceTimer );
\t\t\tvar value = extractFieldValue( acfField );
\t\t\tif ( value !== undefined ) {
\t\t\t\tsaveField( postId, fieldKey, value, acfField );
\t\t\t}
\t\t}

\t\tswitch ( fieldType ) {
\t\t\tcase 'text':
\t\t\tcase 'email':
\t\t\tcase 'number':
\t\t\tcase 'url': {
\t\t\t\tvar textInp = acfField.querySelector( 'input[type="text"], input[type="email"], input[type="number"], input[type="url"]' );
\t\t\t\tif ( ! textInp ) { return; }
\t\t\t\ttextInp.addEventListener( 'blur', immediateSave );
\t\t\t\t// Also save on Enter key (prevent form submit).
\t\t\t\ttextInp.addEventListener( 'keydown', function ( e ) {
\t\t\t\t\tif ( e.key === 'Enter' ) {
\t\t\t\t\t\te.preventDefault();
\t\t\t\t\t\timmediateSave();
\t\t\t\t\t}
\t\t\t\t} );
\t\t\t\tbreak;
\t\t\t}

\t\t\tcase 'textarea': {
\t\t\t\tvar ta = acfField.querySelector( 'textarea' );
\t\t\t\tif ( ! ta ) { return; }
\t\t\t\tta.addEventListener( 'blur', immediateSave );
\t\t\t\tbreak;
\t\t\t}

\t\t\tcase 'wysiwyg': {
\t\t\t\tvar wysTA = acfField.querySelector( 'textarea' );
\t\t\t\tif ( ! wysTA ) { return; }
\t\t\t\tvar editorId = wysTA.id || '';
\t\t\t\tif ( ! editorId ) { return; }
\t\t\t\twaitForTinyMCE( editorId, function ( editor ) {
\t\t\t\t\teditor.on( 'blur', immediateSave );
\t\t\t\t\tvar wysiTimer = null;
\t\t\t\t\teditor.on( 'input change keyup', function () {
\t\t\t\t\t\tclearTimeout( wysiTimer );
\t\t\t\t\t\twysiTimer = setTimeout( immediateSave, 2000 );
\t\t\t\t\t} );
\t\t\t\t} );
\t\t\t\tbreak;
\t\t\t}

\t\t\tcase 'select': {
\t\t\t\tvar sel = acfField.querySelector( 'select' );
\t\t\t\tif ( ! sel ) { return; }
\t\t\t\tsel.addEventListener( 'change', immediateSave );
\t\t\t\tbreak;
\t\t\t}

\t\t\tcase 'radio': {
\t\t\t\tacfField.querySelectorAll( 'input[type="radio"]' ).forEach( function ( radio ) {
\t\t\t\t\tradio.addEventListener( 'change', function () {
\t\t\t\t\t\tif ( radio.checked ) { immediateSave(); }
\t\t\t\t\t} );
\t\t\t\t} );
\t\t\t\tbreak;
\t\t\t}

\t\t\tcase 'checkbox': {
\t\t\t\tacfField.addEventListener( 'change', function ( e ) {
\t\t\t\t\tif ( e.target.type === 'checkbox' ) { immediateSave(); }
\t\t\t\t} );
\t\t\t\tbreak;
\t\t\t}

\t\t\tcase 'true_false': {
\t\t\t\tvar tf = acfField.querySelector( 'input[type="checkbox"]' );
\t\t\t\tif ( ! tf ) { return; }
\t\t\t\ttf.addEventListener( 'change', immediateSave );
\t\t\t\tbreak;
\t\t\t}

\t\t\tcase 'taxonomy': {
\t\t\t\t// The custom taxonomy search modifies the hidden <select> programmatically.
\t\t\t\t// Use MutationObserver to catch those changes.
\t\t\t\tvar taxoSel = acfField.querySelector( 'select' );
\t\t\t\tif ( ! taxoSel ) { return; }
\t\t\t\tvar taxoObserver = new MutationObserver( function () {
\t\t\t\t\tdebouncedSave( 300 );
\t\t\t\t} );
\t\t\t\ttaxoObserver.observe( taxoSel, { childList: true, attributes: true, subtree: true } );
\t\t\t\t// Also listen for direct change events (single-select).
\t\t\t\ttaxoSel.addEventListener( 'change', function () { debouncedSave( 300 ); } );
\t\t\t\tbreak;
\t\t\t}

\t\t\tcase 'google_map': {
\t\t\t\t// ACF stores map data in hidden inputs; changes are programmatic.
\t\t\t\tvar mapInputs = acfField.querySelectorAll( 'input[type="hidden"], input[type="text"]' );
\t\t\t\tvar mapObserver = new MutationObserver( function () {
\t\t\t\t\tdebouncedSave( 1000 );
\t\t\t\t} );
\t\t\t\tmapInputs.forEach( function ( inp ) {
\t\t\t\t\tmapObserver.observe( inp, { attributes: true, attributeFilter: [ 'value' ] } );
\t\t\t\t\tinp.addEventListener( 'change', function () { debouncedSave( 1000 ); } );
\t\t\t\t} );
\t\t\t\tbreak;
\t\t\t}
\t\t}
\t}

\t/**
\t * Live-update the header title in the DOM when a name field saves.
\t *
\t * @param {string} fieldKey  The ACF field key that just saved.
\t * @param {*}      value     The saved value.
\t */
\tfunction onFieldSaved( fieldKey, value ) {
\t\tif ( fieldKey === 'field_md_profile_page_name' ) {
\t\t\tvar titleEl = document.querySelector( '.memdir-header-wrap[data-header="profile"] .memdir-header__title' );
\t\t\tif ( titleEl && typeof value === 'string' ) { titleEl.textContent = value; }
\t\t}
\t\tif ( fieldKey === 'field_md_business_name' ) {
\t\t\tvar bTitleEl = document.querySelector( '.memdir-header-wrap[data-header="business"] .memdir-header__title' );
\t\t\tif ( bTitleEl && typeof value === 'string' ) { bTitleEl.textContent = value; }
\t\t}
\t}

\t/**
\t * Initialize per-field AJAX autosave for all edit-mode sections.
\t * Replaces the old bulk saveSection() approach.
\t */
\tfunction initFieldAutosave() {
\t\tdocument.querySelectorAll( '.memdir-section--edit' ).forEach( function ( section ) {
\t\t\tvar postId = section.dataset.postId || '';
\t\t\tvar fieldContent = section.querySelector( '.memdir-field-content' );
\t\t\tif ( ! fieldContent || ! postId ) { return; }

\t\t\t// Header fields are managed by initHeaderEditing() -- skip them.
\t\t\tvar headerKeys = getHeaderFieldKeys( section );

\t\t\tfieldContent.querySelectorAll( '.acf-field[data-key]' ).forEach( function ( acfField ) {
\t\t\t\t// Skip fields inside dialogs (header modals manage their own save).
\t\t\t\tif ( acfField.closest( 'dialog' ) ) { return; }

\t\t\t\t// Skip sub-fields inside repeaters/groups -- only top-level fields autosave.
\t\t\t\tif ( acfField.parentElement && acfField.parentElement.closest( '.acf-field[data-type="repeater"], .acf-field[data-type="flexible_content"], .acf-field[data-type="group"]' ) ) { return; }

\t\t\t\tvar fieldKey  = acfField.dataset.key  || '';
\t\t\t\tvar fieldType = ( acfField.dataset.type || '' ).toLowerCase();

\t\t\t\tif ( ! fieldKey || ! fieldKey.indexOf( 'field_' ) === 0 ) { return; }

\t\t\t\t// Skip header-owned fields.
\t\t\t\tif ( headerKeys.indexOf( fieldKey ) !== -1 ) { return; }

\t\t\t\t// Skip PMP companion fields (button_group with _pmp_ in key).
\t\t\t\tif ( fieldKey.indexOf( '_pmp_' ) !== -1 ) { return; }

\t\t\t\t// Skip system fields (button_group type).
\t\t\t\tif ( fieldType === 'button_group' ) { return; }

\t\t\t\t// Skip image/gallery fields -- already have dedicated AJAX handlers.
\t\t\t\tif ( fieldType === 'image' || fieldType === 'gallery' || fieldType === 'file' ) { return; }
\t\t\t\tif ( acfField.querySelector( '.memdir-img-uploader, .memdir-gallery-wrap' ) ) { return; }

\t\t\t\t// Skip tab fields.
\t\t\t\tif ( fieldType === 'tab' || fieldType === 'message' || fieldType === 'accordion' ) { return; }

\t\t\t\tbindFieldAutosave( acfField, postId, fieldKey, fieldType );
\t\t\t} );

\t\t\t// Intercept Enter key in text inputs to prevent native form submit.
\t\t\tfieldContent.addEventListener( 'keydown', function ( event ) {
\t\t\t\tif ( event.key !== 'Enter' ) { return; }
\t\t\t\tif ( event.target.tagName === 'TEXTAREA' ) { return; }
\t\t\t\tif ( event.target.closest( '.acf-field' ) ) {
\t\t\t\t\tevent.preventDefault();
\t\t\t\t}
\t\t\t} );
\t\t} );

\t\t// Suppress ACF's beforeunload warning -- all changes save in real time.
\t\twindow.onbeforeunload = null;
\t\tif ( typeof jQuery !== 'undefined' ) { jQuery( window ).off( 'beforeunload' ); }
\t}

`;

src = src.substring(0, idx4Start) + newSection4 + src.substring(idx4End);

// ─────────────────────────────────────────────────────────────────────────────
// 2. Refactor createMiniModal: change Save to Done, remove saveSection call
// ─────────────────────────────────────────────────────────────────────────────

// Replace the save button block inside createMiniModal
const oldModalSave = `\t\t\t\tif ( ! opts.noSave ) {\r\n\t\t\t\t\tvar saveBtn = document.createElement( 'button' );\r\n\t\t\t\t\tsaveBtn.type = 'button';\r\n\t\t\t\t\tsaveBtn.className = 'memdir-modal-save';\r\n\t\t\t\t\tsaveBtn.textContent = 'Save';\r\n\t\t\t\t\tdialog.appendChild( saveBtn );\r\n\r\n\t\t\t\t\tsaveBtn.addEventListener( 'click', function () {\r\n\t\t\t\t\t\t// Move dialog back before saving so saveSection() finds fields\r\n\t\t\t\t\t\tif ( dialog.parentElement !== fieldContent ) {\r\n\t\t\t\t\t\t\tfieldContent.appendChild( dialog );\r\n\t\t\t\t\t\t}\r\n\t\t\t\t\t\tvar sBtn = section.querySelector( '.memdir-section-save' );\r\n\t\t\t\t\t\tvar ban  = section.querySelector( '.memdir-unsaved-banner' );\r\n\t\t\t\t\t\tif ( sBtn ) { saveSection( section, sBtn, ban ); }\r\n\t\t\t\t\t\tdialog.close();\r\n\t\t\t\t\t\tif ( opts.onSave ) { opts.onSave(); }\r\n\t\t\t\t\t} );\r\n\t\t\t\t}`;

const newModalSave = `\t\t\t\tif ( ! opts.noSave ) {\r\n\t\t\t\t\tvar doneBtn = document.createElement( 'button' );\r\n\t\t\t\t\tdoneBtn.type = 'button';\r\n\t\t\t\t\tdoneBtn.className = 'memdir-modal-save';\r\n\t\t\t\t\tdoneBtn.textContent = 'Done';\r\n\t\t\t\t\tdialog.appendChild( doneBtn );\r\n\r\n\t\t\t\t\tdoneBtn.addEventListener( 'click', function () {\r\n\t\t\t\t\t\t// Move dialog back so autosave can find fields on subsequent edits.\r\n\t\t\t\t\t\tif ( dialog.parentElement !== fieldContent ) {\r\n\t\t\t\t\t\t\tfieldContent.appendChild( dialog );\r\n\t\t\t\t\t\t}\r\n\t\t\t\t\t\t// Save all fields in the modal via per-field autosave.\r\n\t\t\t\t\t\tvar postId = section.dataset.postId || '';\r\n\t\t\t\t\t\tdialog.querySelectorAll( '.acf-field[data-key]' ).forEach( function ( af ) {\r\n\t\t\t\t\t\t\tvar fk  = af.dataset.key  || '';\r\n\t\t\t\t\t\t\tvar val = extractFieldValue( af );\r\n\t\t\t\t\t\t\tif ( fk && val !== undefined && postId ) {\r\n\t\t\t\t\t\t\t\tsaveField( postId, fk, val, af );\r\n\t\t\t\t\t\t\t\tonFieldSaved( fk, val );\r\n\t\t\t\t\t\t\t}\r\n\t\t\t\t\t\t} );\r\n\t\t\t\t\t\tdialog.close();\r\n\t\t\t\t\t\tif ( opts.onSave ) { opts.onSave(); }\r\n\t\t\t\t\t} );\r\n\t\t\t\t}`;

if (src.indexOf(oldModalSave) === -1) {
	console.error('Could not find createMiniModal save button block');
	process.exit(1);
}

src = src.replace(oldModalSave, newModalSave);

// ─────────────────────────────────────────────────────────────────────────────
// 3. Update boot sequence: replace initSectionSave with initFieldAutosave
// ─────────────────────────────────────────────────────────────────────────────

src = src.replace(
	'\t\tinitSectionSave();\r\n',
	'\t\tinitFieldAutosave();\r\n'
);

// ─────────────────────────────────────────────────────────────────────────────
// 4. Update file header comment (section 4 description)
// ─────────────────────────────────────────────────────────────────────────────

src = src.replace(
	' *   4. Section save       -- AJAX save for all fields in a section without reload',
	' *   4. Field autosave     -- per-field AJAX save on blur/change (no save button)'
);

// ─────────────────────────────────────────────────────────────────────────────
// Write result
// ─────────────────────────────────────────────────────────────────────────────

fs.writeFileSync(filePath, src, 'utf8');
console.log('patch-autosave.js: memdir.js patched successfully');
console.log('  - Replaced section 4 (saveSection) with field autosave utilities');
console.log('  - Refactored createMiniModal Save -> Done');
console.log('  - Updated boot sequence');
console.log('  - Updated file header comment');
