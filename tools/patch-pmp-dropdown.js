#!/usr/bin/env node
/**
 * Patch memdir.js: replace all 3 PMP button-group handlers with dropdown equivalents.
 *
 * Handles CRLF line endings safely.
 */
'use strict';

const fs   = require('fs');
const path = require('path');

const filePath = path.resolve(__dirname, '..', 'assets', 'js', 'memdir.js');
let code = fs.readFileSync(filePath, 'utf8');

// Detect line ending
const eol = code.includes('\r\n') ? '\r\n' : '\n';

// ─────────────────────────────────────────────────────────────────────────────
// 1. Replace getGlobalPmp() — reads from new dropdown wrapper
// ─────────────────────────────────────────────────────────────────────────────

const oldGetGlobalPmp = [
	'\tfunction getGlobalPmp() {',
	"\t\tvar activeBtn = document.querySelector( '.memdir-panel__global-btn--active' );",
	"\t\treturn activeBtn ? ( activeBtn.dataset.pmp || 'public' ) : 'public';",
	'\t}',
].join(eol);

const newGetGlobalPmp = [
	'\tfunction getGlobalPmp() {',
	"\t\tvar dd = document.querySelector( '.memdir-right-panel .memdir-pmp-dropdown' );",
	"\t\treturn dd ? ( dd.dataset.pmp || 'public' ) : 'public';",
	'\t}',
].join(eol);

if (!code.includes(oldGetGlobalPmp)) {
	console.error('ERROR: could not find getGlobalPmp() — aborting');
	process.exit(1);
}
code = code.replace(oldGetGlobalPmp, newGetGlobalPmp);
console.log('✓ Patched getGlobalPmp()');

// ─────────────────────────────────────────────────────────────────────────────
// 2. Replace computeFieldPmpStatus() — reads section PMP from dropdown wrapper
// ─────────────────────────────────────────────────────────────────────────────

const oldSectionRead = [
	"\t\tvar activeSectionBtn = section",
	"\t\t\t? section.querySelector( '.memdir-section-controls__pmp-btn.is-active' )",
	"\t\t\t: null;",
	"\t\tvar sectionPmp = activeSectionBtn ? ( activeSectionBtn.dataset.pmp || 'inherit' ) : 'inherit';",
].join(eol);

const newSectionRead = [
	"\t\tvar sectionDropdown = section",
	"\t\t\t? section.querySelector( '.memdir-section-controls .memdir-pmp-dropdown' )",
	"\t\t\t: null;",
	"\t\tvar sectionPmp = sectionDropdown ? ( sectionDropdown.dataset.pmp || 'inherit' ) : 'inherit';",
].join(eol);

if (!code.includes(oldSectionRead)) {
	console.error('ERROR: could not find section PMP DOM read in computeFieldPmpStatus() — aborting');
	process.exit(1);
}
code = code.replace(oldSectionRead, newSectionRead);
console.log('✓ Patched computeFieldPmpStatus() section read');

// ─────────────────────────────────────────────────────────────────────────────
// 3. Replace initSectionPmp() — use dropdown instead of buttons
// ─────────────────────────────────────────────────────────────────────────────

// Find the old function (from "function initSectionPmp()" to the closing "}")
const initSectionPmpStart = '\tfunction initSectionPmp() {';
const initSectionPmpEnd   = [
	"\t\t\t} );",
	"\t\t} );",
	"\t}",
	"",
	"\t/**",
	"\t * Update a section's eyebrow text and data-pmp-mode attribute.",
].join(eol);

const startIdx = code.indexOf(initSectionPmpStart);
const endIdx   = code.indexOf(initSectionPmpEnd, startIdx);
if (startIdx === -1 || endIdx === -1) {
	console.error('ERROR: could not find initSectionPmp() boundaries — aborting');
	process.exit(1);
}

const newInitSectionPmp = [
	"\tfunction initSectionPmp() {",
	"\t\tdocument.querySelectorAll( '.memdir-section--edit' ).forEach( function ( section ) {",
	"\t\t\tvar controls = section.querySelector( '.memdir-section-controls' );",
	"\t\t\tif ( ! controls ) { return; }",
	"",
	"\t\t\tvar dropdown = controls.querySelector( '.memdir-pmp-dropdown' );",
	"\t\t\tvar status   = controls.querySelector( '.memdir-section-controls__pmp-status' );",
	"\t\t\tif ( ! dropdown ) { return; }",
	"",
	"\t\t\t// Toggle dropdown open/close on trigger click.",
	"\t\t\tvar trigger = dropdown.querySelector( '.memdir-pmp-dropdown__trigger' );",
	"\t\t\tif ( trigger ) {",
	"\t\t\t\ttrigger.addEventListener( 'click', function ( e ) {",
	"\t\t\t\t\te.stopPropagation();",
	"\t\t\t\t\ttogglePmpDropdown( dropdown );",
	"\t\t\t\t} );",
	"\t\t\t}",
	"",
	"\t\t\t// Wire option clicks.",
	"\t\t\tdropdown.querySelectorAll( '.memdir-pmp-dropdown__option' ).forEach( function ( opt ) {",
	"\t\t\t\topt.addEventListener( 'click', function () {",
	"\t\t\t\t\tvar pmp        = opt.dataset.pmp || '';",
	"\t\t\t\t\tvar postId     = section.dataset.postId || '';",
	"\t\t\t\t\tvar sectionKey = section.dataset.section || '';",
	"",
	"\t\t\t\t\tif ( ! pmp || ! postId || ! sectionKey ) { return; }",
	"",
	"\t\t\t\t\tvar prevPmp = dropdown.dataset.pmp || 'inherit';",
	"\t\t\t\t\tupdatePmpDropdown( dropdown, pmp );",
	"\t\t\t\t\ttogglePmpDropdown( dropdown, false );",
	"",
	"\t\t\t\t\tif ( status ) { updateSectionPmpStatus( status, pmp ); }",
	"\t\t\t\t\trefreshSectionFieldPmpEyebrows( section );",
	"",
	"\t\t\t\t\t// AJAX save.",
	"\t\t\t\t\tvar ajaxUrl = ( window.mdAjax && window.mdAjax.ajaxurl )",
	"\t\t\t\t\t\t? window.mdAjax.ajaxurl : '/wp-admin/admin-ajax.php';",
	"\t\t\t\t\tvar nonce = ( window.mdAjax && window.mdAjax.nonce )",
	"\t\t\t\t\t\t? window.mdAjax.nonce : '';",
	"",
	"\t\t\t\t\tvar formData = new FormData();",
	"\t\t\t\t\tformData.set( 'action',      'memdir_ajax_save_section_pmp' );",
	"\t\t\t\t\tformData.set( 'nonce',       nonce );",
	"\t\t\t\t\tformData.set( 'post_id',     postId );",
	"\t\t\t\t\tformData.set( 'section_key', sectionKey );",
	"\t\t\t\t\tformData.set( 'pmp',         pmp );",
	"",
	"\t\t\t\t\tfetch( ajaxUrl, { method: 'POST', credentials: 'same-origin', body: formData } )",
	"\t\t\t\t\t\t.then( function ( r ) { return r.json(); } )",
	"\t\t\t\t\t\t.then( function ( data ) {",
	"\t\t\t\t\t\t\tif ( ! data.success ) {",
	"\t\t\t\t\t\t\t\tconsole.error( 'MemberDirectory: section PMP AJAX error', data );",
	"\t\t\t\t\t\t\t\tupdatePmpDropdown( dropdown, prevPmp );",
	"\t\t\t\t\t\t\t\tif ( status ) { updateSectionPmpStatus( status, prevPmp ); }",
	"\t\t\t\t\t\t\t\trefreshSectionFieldPmpEyebrows( section );",
	"\t\t\t\t\t\t\t}",
	"\t\t\t\t\t\t} )",
	"\t\t\t\t\t\t.catch( function ( err ) {",
	"\t\t\t\t\t\t\tconsole.error( 'MemberDirectory: section PMP AJAX failed', err );",
	"\t\t\t\t\t\t} );",
	"\t\t\t\t} );",
	"\t\t\t} );",
	"\t\t} );",
	"\t}",
	"",
	"\t/**",
	"\t * Update a section's eyebrow text and data-pmp-mode attribute.",
].join(eol);

code = code.substring(0, startIdx) + newInitSectionPmp + code.substring(endIdx + initSectionPmpEnd.length);
console.log('✓ Patched initSectionPmp()');

// ─────────────────────────────────────────────────────────────────────────────
// 4. Replace global PMP handler inside initRightPanel()
// ─────────────────────────────────────────────────────────────────────────────

const oldGlobalPmp = [
	"\t\t// GLOBAL PMP buttons -- clicking a .memdir-panel__global-btn saves the",
	"\t\t// profile-wide default visibility level via AJAX and toggles the active",
	"\t\t// highlight to the clicked button. Also cascades to inherit-mode sections.",
	"\t\tpanel.querySelectorAll( '.memdir-panel__global-btn' ).forEach( function ( btn ) {",
	"\t\t\tbtn.addEventListener( 'click', function () {",
	"\t\t\t\tvar pmp    = btn.dataset.pmp || '';",
	"\t\t\t\tvar nav    = document.querySelector( '.memdir-pills' );",
	"\t\t\t\tvar postId = nav ? ( nav.dataset.postId || '' ) : '';",
	"",
	"\t\t\t\tif ( ! pmp || ! postId ) {",
	"\t\t\t\t\treturn;",
	"\t\t\t\t}",
	"",
	"\t\t\t\t// Optimistic UI: apply the active class immediately and blur the button",
	"\t\t\t\t// so BuddyBoss's :focus/:active styles don't override the new background.",
	"\t\t\t\tvar prevPmp = '';",
	"\t\t\t\tpanel.querySelectorAll( '.memdir-panel__global-btn' ).forEach( function ( b ) {",
	"\t\t\t\t\tif ( b.classList.contains( 'memdir-panel__global-btn--active' ) ) { prevPmp = b.dataset.pmp || ''; }",
	"\t\t\t\t\tb.classList.toggle( 'memdir-panel__global-btn--active', b.dataset.pmp === pmp );",
	"\t\t\t\t} );",
	"\t\t\t\tbtn.blur();",
	"",
	"\t\t\t\t// Cascade to inherit-mode sections immediately.",
	"\t\t\t\tcascadeGlobalPmpToSections( pmp );",
	"",
	"\t\t\t\tvar ajaxUrl = ( window.mdAjax && window.mdAjax.ajaxurl )",
	"\t\t\t\t\t? window.mdAjax.ajaxurl",
	"\t\t\t\t\t: '/wp-admin/admin-ajax.php';",
	"\t\t\t\tvar nonce = ( window.mdAjax && window.mdAjax.nonce )",
	"\t\t\t\t\t? window.mdAjax.nonce",
	"\t\t\t\t\t: '';",
	"",
	"\t\t\t\tvar formData = new FormData();",
	"\t\t\t\tformData.set( 'action',  'memdir_ajax_save_global_pmp' );",
	"\t\t\t\tformData.set( 'nonce',   nonce );",
	"\t\t\t\tformData.set( 'post_id', postId );",
	"\t\t\t\tformData.set( 'pmp',     pmp );",
	"",
	"\t\t\t\tfetch( ajaxUrl, {",
	"\t\t\t\t\tmethod:      'POST',",
	"\t\t\t\t\tcredentials: 'same-origin',",
	"\t\t\t\t\tbody:        formData,",
	"\t\t\t\t} )",
	"\t\t\t\t\t.then( function ( response ) { return response.json(); } )",
	"\t\t\t\t\t.then( function ( data ) {",
	"\t\t\t\t\t\tif ( ! data.success ) {",
	"\t\t\t\t\t\t\tconsole.error( 'MemberDirectory: global PMP AJAX returned error', data );",
	"\t\t\t\t\t\t\t// Revert optimistic change.",
	"\t\t\t\t\t\t\tpanel.querySelectorAll( '.memdir-panel__global-btn' ).forEach( function ( b ) {",
	"\t\t\t\t\t\t\t\tb.classList.toggle( 'memdir-panel__global-btn--active', b.dataset.pmp === prevPmp );",
	"\t\t\t\t\t\t\t} );",
	"\t\t\t\t\t\t\tcascadeGlobalPmpToSections( prevPmp );",
	"\t\t\t\t\t\t}",
	"\t\t\t\t\t} )",
	"\t\t\t\t\t.catch( function ( err ) {",
	"\t\t\t\t\t\tconsole.error( 'MemberDirectory: global PMP AJAX failed', err );",
	"\t\t\t\t\t} );",
	"\t\t\t} );",
	"\t\t} );",
	"\t}",
].join(eol);

const newGlobalPmp = [
	"\t\t// GLOBAL PMP dropdown — clicking an option saves the profile-wide",
	"\t\t// default visibility level via AJAX. Cascades to inherit-mode sections.",
	"\t\tvar globalDropdown = panel.querySelector( '.memdir-pmp-dropdown[data-context=\"global\"]' );",
	"\t\tif ( globalDropdown ) {",
	"\t\t\tvar globalTrigger = globalDropdown.querySelector( '.memdir-pmp-dropdown__trigger' );",
	"\t\t\tif ( globalTrigger ) {",
	"\t\t\t\tglobalTrigger.addEventListener( 'click', function ( e ) {",
	"\t\t\t\t\te.stopPropagation();",
	"\t\t\t\t\ttogglePmpDropdown( globalDropdown );",
	"\t\t\t\t} );",
	"\t\t\t}",
	"",
	"\t\t\tglobalDropdown.querySelectorAll( '.memdir-pmp-dropdown__option' ).forEach( function ( opt ) {",
	"\t\t\t\topt.addEventListener( 'click', function () {",
	"\t\t\t\t\tvar pmp    = opt.dataset.pmp || '';",
	"\t\t\t\t\tvar nav    = document.querySelector( '.memdir-pills' );",
	"\t\t\t\t\tvar postId = nav ? ( nav.dataset.postId || '' ) : '';",
	"",
	"\t\t\t\t\tif ( ! pmp || ! postId ) { return; }",
	"",
	"\t\t\t\t\tvar prevPmp = globalDropdown.dataset.pmp || 'public';",
	"\t\t\t\t\tupdatePmpDropdown( globalDropdown, pmp );",
	"\t\t\t\t\ttogglePmpDropdown( globalDropdown, false );",
	"\t\t\t\t\tcascadeGlobalPmpToSections( pmp );",
	"",
	"\t\t\t\t\tvar ajaxUrl = ( window.mdAjax && window.mdAjax.ajaxurl )",
	"\t\t\t\t\t\t? window.mdAjax.ajaxurl : '/wp-admin/admin-ajax.php';",
	"\t\t\t\t\tvar nonce = ( window.mdAjax && window.mdAjax.nonce )",
	"\t\t\t\t\t\t? window.mdAjax.nonce : '';",
	"",
	"\t\t\t\t\tvar formData = new FormData();",
	"\t\t\t\t\tformData.set( 'action',  'memdir_ajax_save_global_pmp' );",
	"\t\t\t\t\tformData.set( 'nonce',   nonce );",
	"\t\t\t\t\tformData.set( 'post_id', postId );",
	"\t\t\t\t\tformData.set( 'pmp',     pmp );",
	"",
	"\t\t\t\t\tfetch( ajaxUrl, { method: 'POST', credentials: 'same-origin', body: formData } )",
	"\t\t\t\t\t\t.then( function ( r ) { return r.json(); } )",
	"\t\t\t\t\t\t.then( function ( data ) {",
	"\t\t\t\t\t\t\tif ( ! data.success ) {",
	"\t\t\t\t\t\t\t\tconsole.error( 'MemberDirectory: global PMP AJAX error', data );",
	"\t\t\t\t\t\t\t\tupdatePmpDropdown( globalDropdown, prevPmp );",
	"\t\t\t\t\t\t\t\tcascadeGlobalPmpToSections( prevPmp );",
	"\t\t\t\t\t\t\t}",
	"\t\t\t\t\t\t} )",
	"\t\t\t\t\t\t.catch( function ( err ) {",
	"\t\t\t\t\t\t\tconsole.error( 'MemberDirectory: global PMP AJAX failed', err );",
	"\t\t\t\t\t\t} );",
	"\t\t\t\t} );",
	"\t\t\t} );",
	"\t\t}",
	"\t}",
].join(eol);

if (!code.includes(oldGlobalPmp)) {
	console.error('ERROR: could not find global PMP handler in initRightPanel() — aborting');
	process.exit(1);
}
code = code.replace(oldGlobalPmp, newGlobalPmp);
console.log('✓ Patched global PMP handler in initRightPanel()');

// ─────────────────────────────────────────────────────────────────────────────
// 5. Replace initFieldPmp() — use buildPmpDropdown() instead of manual buttons
// ─────────────────────────────────────────────────────────────────────────────

const oldInitFieldPmpStart = '\tfunction initFieldPmp() {';
const oldInitFieldPmpEnd   = eol + eol + '\t// -----------------------------------------------------------------------' + eol + '\t// 10. Sticky section controls';

const fpStart = code.indexOf(oldInitFieldPmpStart);
const fpEnd   = code.indexOf(oldInitFieldPmpEnd, fpStart);
if (fpStart === -1 || fpEnd === -1) {
	console.error('ERROR: could not find initFieldPmp() boundaries — aborting');
	process.exit(1);
}

const newInitFieldPmp = [
	"\tfunction initFieldPmp() {",
	"\t\tdocument.querySelectorAll( '.memdir-section--edit' ).forEach( function ( section ) {",
	"\t\t\tvar fieldPmpData = {};",
	"\t\t\ttry { fieldPmpData = JSON.parse( section.dataset.fieldPmp || '{}' ); }",
	"\t\t\tcatch ( e ) { fieldPmpData = {}; }",
	"",
	"\t\t\tObject.keys( fieldPmpData ).forEach( function ( fieldKey ) {",
	"\t\t\t\tvar data          = fieldPmpData[ fieldKey ];",
	"\t\t\t\tvar storedPmp     = data.storedPmp    || 'inherit';",
	"\t\t\t\tvar companionKey  = data.companionKey  || '';",
	"\t\t\t\tvar companionName = data.companionName || '';",
	"",
	"\t\t\t\tvar fieldEl = section.querySelector( '.acf-field[data-key=\"' + fieldKey + '\"]' );",
	"\t\t\t\tif ( ! fieldEl ) { return; }",
	"",
	"\t\t\t\t// Build the control wrapper.",
	"\t\t\t\tvar wrap = document.createElement( 'div' );",
	"\t\t\t\twrap.className            = 'memdir-field-pmp';",
	"\t\t\t\twrap.dataset.fieldKey     = fieldKey;",
	"\t\t\t\twrap.dataset.companionKey = companionKey;",
	"\t\t\t\twrap.dataset.storedPmp    = storedPmp;",
	"",
	"\t\t\t\tvar labelEl = fieldEl.querySelector( '.acf-label label' );",
	"\t\t\t\twrap.dataset.fieldLabel = labelEl ? labelEl.textContent.trim().replace( /\\s*\\*$/, '' ) : '';",
	"",
	"\t\t\t\t// Build dropdown.",
	"\t\t\t\tvar dropdown = buildPmpDropdown( storedPmp, [ 'inherit', 'public', 'member', 'private' ] );",
	"\t\t\t\twrap.appendChild( dropdown );",
	"",
	"\t\t\t\t// Status eyebrow.",
	"\t\t\t\tvar statusSpan       = document.createElement( 'span' );",
	"\t\t\t\tstatusSpan.className = 'memdir-field-pmp__status';",
	"\t\t\t\tstatusSpan.textContent = computeFieldPmpStatus( wrap );",
	"\t\t\t\twrap.appendChild( statusSpan );",
	"",
	"\t\t\t\tvar acfLabel = fieldEl.querySelector( '.acf-label' );",
	"\t\t\t\t( acfLabel || fieldEl ).appendChild( wrap );",
	"",
	"\t\t\t\t// Wire trigger click.",
	"\t\t\t\tvar trigger = dropdown.querySelector( '.memdir-pmp-dropdown__trigger' );",
	"\t\t\t\tif ( trigger ) {",
	"\t\t\t\t\ttrigger.addEventListener( 'click', function ( e ) {",
	"\t\t\t\t\t\te.stopPropagation();",
	"\t\t\t\t\t\ttogglePmpDropdown( dropdown );",
	"\t\t\t\t\t} );",
	"\t\t\t\t}",
	"",
	"\t\t\t\t// Wire option clicks.",
	"\t\t\t\tdropdown.querySelectorAll( '.memdir-pmp-dropdown__option' ).forEach( function ( opt ) {",
	"\t\t\t\t\topt.addEventListener( 'click', function () {",
	"\t\t\t\t\t\tvar pmp    = opt.dataset.pmp || '';",
	"\t\t\t\t\t\tvar postId = section.dataset.postId || '';",
	"",
	"\t\t\t\t\t\tif ( ! pmp || ! postId || ! companionName ) { return; }",
	"",
	"\t\t\t\t\t\tvar prevPmp = wrap.dataset.storedPmp || 'inherit';",
	"\t\t\t\t\t\twrap.dataset.storedPmp = pmp;",
	"\t\t\t\t\t\tupdatePmpDropdown( dropdown, pmp );",
	"\t\t\t\t\t\ttogglePmpDropdown( dropdown, false );",
	"\t\t\t\t\t\tstatusSpan.textContent = computeFieldPmpStatus( wrap );",
	"",
	"\t\t\t\t\t\t// AJAX save.",
	"\t\t\t\t\t\tvar ajaxUrl = ( window.mdAjax && window.mdAjax.ajaxurl )",
	"\t\t\t\t\t\t\t? window.mdAjax.ajaxurl : '/wp-admin/admin-ajax.php';",
	"\t\t\t\t\t\tvar nonce = ( window.mdAjax && window.mdAjax.nonce )",
	"\t\t\t\t\t\t\t? window.mdAjax.nonce : '';",
	"",
	"\t\t\t\t\t\tvar formData = new FormData();",
	"\t\t\t\t\t\tformData.set( 'action',         'memdir_ajax_save_field_pmp' );",
	"\t\t\t\t\t\tformData.set( 'nonce',          nonce );",
	"\t\t\t\t\t\tformData.set( 'post_id',        postId );",
	"\t\t\t\t\t\tformData.set( 'companion_name', companionName );",
	"\t\t\t\t\t\tformData.set( 'pmp',            pmp );",
	"",
	"\t\t\t\t\t\tfetch( ajaxUrl, { method: 'POST', credentials: 'same-origin', body: formData } )",
	"\t\t\t\t\t\t\t.then( function ( r ) { return r.json(); } )",
	"\t\t\t\t\t\t\t.then( function ( data ) {",
	"\t\t\t\t\t\t\t\tif ( data.success ) {",
	"\t\t\t\t\t\t\t\t\tvar savedText = statusSpan.textContent;",
	"\t\t\t\t\t\t\t\t\tstatusSpan.textContent = '✓ Saved';",
	"\t\t\t\t\t\t\t\t\tstatusSpan.classList.add( 'memdir-field-pmp__status--saved' );",
	"\t\t\t\t\t\t\t\t\tsetTimeout( function () {",
	"\t\t\t\t\t\t\t\t\t\tstatusSpan.textContent = savedText;",
	"\t\t\t\t\t\t\t\t\t\tstatusSpan.classList.remove( 'memdir-field-pmp__status--saved' );",
	"\t\t\t\t\t\t\t\t\t}, 1200 );",
	"\t\t\t\t\t\t\t\t} else {",
	"\t\t\t\t\t\t\t\t\tconsole.error( 'MemberDirectory: field PMP AJAX error', data );",
	"\t\t\t\t\t\t\t\t\twrap.dataset.storedPmp = prevPmp;",
	"\t\t\t\t\t\t\t\t\tupdatePmpDropdown( dropdown, prevPmp );",
	"\t\t\t\t\t\t\t\t\tstatusSpan.textContent = computeFieldPmpStatus( wrap );",
	"\t\t\t\t\t\t\t\t}",
	"\t\t\t\t\t\t\t} )",
	"\t\t\t\t\t\t\t.catch( function ( err ) {",
	"\t\t\t\t\t\t\t\tconsole.error( 'MemberDirectory: field PMP AJAX failed', err );",
	"\t\t\t\t\t\t\t\twrap.dataset.storedPmp = prevPmp;",
	"\t\t\t\t\t\t\t\tupdatePmpDropdown( dropdown, prevPmp );",
	"\t\t\t\t\t\t\t\tstatusSpan.textContent = computeFieldPmpStatus( wrap );",
	"\t\t\t\t\t\t\t} );",
	"\t\t\t\t\t} );",
	"\t\t\t\t} );",
	"\t\t\t} );",
	"\t\t} );",
	"\t}",
].join(eol);

code = code.substring(0, fpStart) + newInitFieldPmp + code.substring(fpEnd);
console.log('✓ Patched initFieldPmp()');

// ─────────────────────────────────────────────────────────────────────────────
// 6. Insert shared dropdown utility functions before PMP_LABELS
// ─────────────────────────────────────────────────────────────────────────────

const utilityAnchor = '\t/** Human-readable labels matching the PHP $pmp_labels array. */';

const sharedUtilities = [
	"\t// -----------------------------------------------------------------------",
	"\t// PMP Dropdown — shared utilities",
	"\t//",
	"\t// togglePmpDropdown()  — open/close a .memdir-pmp-dropdown with aria.",
	"\t// updatePmpDropdown()  — set trigger label/icon/class + option state.",
	"\t// buildPmpDropdown()   — DOM builder for JS-created dropdowns (field PMP).",
	"\t// Global listeners     — click-outside close + keyboard navigation.",
	"\t// -----------------------------------------------------------------------",
	"",
	"\tvar PMP_TRIGGER_LABELS = {",
	"\t\t'inherit': 'Inherit',",
	"\t\t'public':  'Public',",
	"\t\t'member':  'Members',",
	"\t\t'private': 'Private',",
	"\t};",
	"",
	"\tfunction togglePmpDropdown( dropdown, open ) {",
	"\t\tvar isOpen = dropdown.classList.contains( 'memdir-pmp-dropdown--open' );",
	"\t\tvar next   = ( typeof open === 'boolean' ) ? open : ! isOpen;",
	"\t\tvar trigger = dropdown.querySelector( '.memdir-pmp-dropdown__trigger' );",
	"",
	"\t\t// Close all other open dropdowns first.",
	"\t\tif ( next ) {",
	"\t\t\tdocument.querySelectorAll( '.memdir-pmp-dropdown--open' ).forEach( function ( dd ) {",
	"\t\t\t\tif ( dd !== dropdown ) {",
	"\t\t\t\t\tdd.classList.remove( 'memdir-pmp-dropdown--open' );",
	"\t\t\t\t\tvar t = dd.querySelector( '.memdir-pmp-dropdown__trigger' );",
	"\t\t\t\t\tif ( t ) { t.setAttribute( 'aria-expanded', 'false' ); }",
	"\t\t\t\t}",
	"\t\t\t} );",
	"\t\t}",
	"",
	"\t\tdropdown.classList.toggle( 'memdir-pmp-dropdown--open', next );",
	"\t\tif ( trigger ) {",
	"\t\t\ttrigger.setAttribute( 'aria-expanded', next ? 'true' : 'false' );",
	"\t\t}",
	"\t}",
	"",
	"\tfunction updatePmpDropdown( dropdown, pmp ) {",
	"\t\tdropdown.dataset.pmp = pmp;",
	"\t\tvar trigger = dropdown.querySelector( '.memdir-pmp-dropdown__trigger' );",
	"\t\tif ( trigger ) {",
	"\t\t\ttrigger.className = trigger.className.replace(",
	"\t\t\t\t/memdir-pmp-dropdown__trigger--\\w+/,",
	"\t\t\t\t'memdir-pmp-dropdown__trigger--' + pmp",
	"\t\t\t);",
	"\t\t\tvar labelEl = trigger.querySelector( '.memdir-pmp-dropdown__label' );",
	"\t\t\tif ( labelEl ) { labelEl.textContent = PMP_TRIGGER_LABELS[ pmp ] || pmp; }",
	"\t\t}",
	"\t\tdropdown.querySelectorAll( '.memdir-pmp-dropdown__option' ).forEach( function ( opt ) {",
	"\t\t\topt.setAttribute( 'aria-selected', opt.dataset.pmp === pmp ? 'true' : 'false' );",
	"\t\t} );",
	"\t}",
	"",
	"\tfunction buildPmpDropdown( currentPmp, options ) {",
	"\t\tvar dd = document.createElement( 'div' );",
	"\t\tdd.className  = 'memdir-pmp-dropdown';",
	"\t\tdd.dataset.pmp = currentPmp;",
	"",
	"\t\tvar trigger = document.createElement( 'button' );",
	"\t\ttrigger.type = 'button';",
	"\t\ttrigger.className = 'memdir-pmp-dropdown__trigger memdir-pmp-dropdown__trigger--' + currentPmp;",
	"\t\ttrigger.setAttribute( 'aria-haspopup', 'listbox' );",
	"\t\ttrigger.setAttribute( 'aria-expanded', 'false' );",
	"",
	"\t\tvar iconSpan = document.createElement( 'span' );",
	"\t\ticonSpan.className = 'memdir-pmp-dropdown__icon';",
	"\t\ticonSpan.setAttribute( 'aria-hidden', 'true' );",
	"\t\ttrigger.appendChild( iconSpan );",
	"",
	"\t\tvar labelSpan = document.createElement( 'span' );",
	"\t\tlabelSpan.className   = 'memdir-pmp-dropdown__label';",
	"\t\tlabelSpan.textContent = PMP_TRIGGER_LABELS[ currentPmp ] || currentPmp;",
	"\t\ttrigger.appendChild( labelSpan );",
	"",
	"\t\tvar caret = document.createElement( 'span' );",
	"\t\tcaret.className = 'memdir-pmp-dropdown__caret';",
	"\t\tcaret.setAttribute( 'aria-hidden', 'true' );",
	"\t\ttrigger.appendChild( caret );",
	"",
	"\t\tdd.appendChild( trigger );",
	"",
	"\t\tvar menu = document.createElement( 'ul' );",
	"\t\tmenu.className = 'memdir-pmp-dropdown__menu';",
	"\t\tmenu.setAttribute( 'role', 'listbox' );",
	"\t\tmenu.setAttribute( 'tabindex', '-1' );",
	"",
	"\t\toptions.forEach( function ( pmpVal ) {",
	"\t\t\tvar li = document.createElement( 'li' );",
	"\t\t\tli.className = 'memdir-pmp-dropdown__option memdir-pmp-dropdown__option--' + pmpVal;",
	"\t\t\tli.setAttribute( 'role', 'option' );",
	"\t\t\tli.dataset.pmp = pmpVal;",
	"\t\t\tli.setAttribute( 'aria-selected', currentPmp === pmpVal ? 'true' : 'false' );",
	"\t\t\tli.setAttribute( 'tabindex', '-1' );",
	"",
	"\t\t\tvar optIcon = document.createElement( 'span' );",
	"\t\t\toptIcon.className = 'memdir-pmp-dropdown__option-icon';",
	"\t\t\toptIcon.setAttribute( 'aria-hidden', 'true' );",
	"\t\t\tli.appendChild( optIcon );",
	"\t\t\tli.appendChild( document.createTextNode( ' ' + ( PMP_TRIGGER_LABELS[ pmpVal ] || pmpVal ) ) );",
	"\t\t\tmenu.appendChild( li );",
	"\t\t} );",
	"",
	"\t\tdd.appendChild( menu );",
	"\t\treturn dd;",
	"\t}",
	"",
	"\t// Global click-outside handler — close all open PMP dropdowns.",
	"\tdocument.addEventListener( 'click', function () {",
	"\t\tdocument.querySelectorAll( '.memdir-pmp-dropdown--open' ).forEach( function ( dd ) {",
	"\t\t\ttogglePmpDropdown( dd, false );",
	"\t\t} );",
	"\t} );",
	"",
	"\t// Keyboard: Escape closes, ArrowUp/Down navigates, Enter/Space selects.",
	"\tdocument.addEventListener( 'keydown', function ( e ) {",
	"\t\tvar openDd = document.querySelector( '.memdir-pmp-dropdown--open' );",
	"\t\tif ( ! openDd ) { return; }",
	"",
	"\t\tif ( e.key === 'Escape' ) {",
	"\t\t\ttogglePmpDropdown( openDd, false );",
	"\t\t\tvar trigger = openDd.querySelector( '.memdir-pmp-dropdown__trigger' );",
	"\t\t\tif ( trigger ) { trigger.focus(); }",
	"\t\t\te.preventDefault();",
	"\t\t\treturn;",
	"\t\t}",
	"",
	"\t\tvar options = Array.from( openDd.querySelectorAll( '.memdir-pmp-dropdown__option' ) );",
	"\t\tvar focused = document.activeElement;",
	"\t\tvar idx     = options.indexOf( focused );",
	"",
	"\t\tif ( e.key === 'ArrowDown' ) {",
	"\t\t\te.preventDefault();",
	"\t\t\toptions[ ( idx + 1 ) % options.length ].focus();",
	"\t\t} else if ( e.key === 'ArrowUp' ) {",
	"\t\t\te.preventDefault();",
	"\t\t\toptions[ ( idx - 1 + options.length ) % options.length ].focus();",
	"\t\t} else if ( e.key === 'Enter' || e.key === ' ' ) {",
	"\t\t\tif ( focused && focused.classList.contains( 'memdir-pmp-dropdown__option' ) ) {",
	"\t\t\t\te.preventDefault();",
	"\t\t\t\tfocused.click();",
	"\t\t\t}",
	"\t\t}",
	"\t} );",
	"",
	"",
].join(eol);

if (!code.includes(utilityAnchor)) {
	console.error('ERROR: could not find PMP_LABELS anchor — aborting');
	process.exit(1);
}
code = code.replace(utilityAnchor, sharedUtilities + utilityAnchor);
console.log('✓ Inserted shared dropdown utilities');

// ─────────────────────────────────────────────────────────────────────────────
// 7. Update section header comments to reflect dropdown
// ─────────────────────────────────────────────────────────────────────────────

code = code.replace(
	/\/\/ Each \.memdir-section--edit has a \.memdir-section-controls__pmp block with\r?\n\t\/\/ four buttons: inherit \(link\), public \(globe\), member \(people\), private \(lock\)\./,
	'// Each .memdir-section--edit has a .memdir-pmp-dropdown in its controls panel\n\t// with options: inherit, public, member, private.'
);

code = code.replace(
	/\/\/ Clicking a button:\r?\n\t\/\/   - Moves is-active to the clicked button \(optimistic\)\.\r?\n\t\/\/   - Updates the eyebrow text \(\.memdir-section-controls__pmp-status\)\.\r?\n\t\/\/   - POSTs to memdir_ajax_save_section_pmp\.\r?\n\t\/\/   - On error: reverts button and eyebrow\./,
	'// Clicking an option:\n\t//   - Updates the dropdown trigger (optimistic).\n\t//   - Updates the eyebrow text (.memdir-section-controls__pmp-status).\n\t//   - POSTs to memdir_ajax_save_section_pmp.\n\t//   - On error: reverts dropdown and eyebrow.'
);

console.log('✓ Updated section comments');

// ─────────────────────────────────────────────────────────────────────────────
// Write patched file
// ─────────────────────────────────────────────────────────────────────────────

fs.writeFileSync(filePath, code, 'utf8');
console.log('\n✅ memdir.js patched successfully');
