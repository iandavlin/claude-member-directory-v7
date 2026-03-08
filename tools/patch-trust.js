#!/usr/bin/env node
/**
 * Patch memdir.js to add Trust Network support.
 * Handles CRLF line endings.
 */

const fs = require('fs');
const path = require('path');

const jsPath = path.join(__dirname, '..', 'assets', 'js', 'memdir.js');
let content = fs.readFileSync(jsPath, 'utf8');

let patchCount = 0;

// --------------------------------------------------------------------------
// 1. Guard initSectionToggles to skip trust toggles
// --------------------------------------------------------------------------
const toggleSearch = "panel.querySelectorAll( '.memdir-panel__toggle input[type=\"checkbox\"]' ).forEach( function ( toggle ) {\r\n\t\t\ttoggle.addEventListener( 'change', function () {";

const toggleReplace = "panel.querySelectorAll( '.memdir-panel__toggle input[type=\"checkbox\"]' ).forEach( function ( toggle ) {\r\n\t\t\t// Skip trust toggle — handled by initTrustNetwork().\r\n\t\t\tif ( toggle.dataset.trustToggle ) {\r\n\t\t\t\treturn;\r\n\t\t\t}\r\n\t\t\ttoggle.addEventListener( 'change', function () {";

if (content.includes(toggleSearch)) {
	content = content.replace(toggleSearch, toggleReplace);
	patchCount++;
	console.log('OK: patched initSectionToggles guard');
} else {
	console.log('FAIL: could not find initSectionToggles target');
}

// --------------------------------------------------------------------------
// 2. Insert initTrustNetwork function before the Boot section
// --------------------------------------------------------------------------
const bootMarker = "\t// -----------------------------------------------------------------------\r\n\t// Boot\r\n\t// -----------------------------------------------------------------------";

const trustFn = [
	"\t// -----------------------------------------------------------------------",
	"\t// 11. Trust Network",
	"\t//",
	"\t// Event delegation on [data-trust-action] buttons inside the trust section.",
	"\t// Each action builds a FormData, POSTs to the matching AJAX endpoint,",
	"\t// and reloads on success.",
	"\t// -----------------------------------------------------------------------",
	"",
	"\tfunction initTrustNetwork() {",
	"\t\tvar ajaxUrl = ( window.mdAjax && window.mdAjax.ajaxurl )",
	"\t\t\t? window.mdAjax.ajaxurl",
	"\t\t\t: '/wp-admin/admin-ajax.php';",
	"\t\tvar nonce = ( window.mdAjax && window.mdAjax.nonce )",
	"\t\t\t? window.mdAjax.nonce",
	"\t\t\t: '';",
	"",
	"\t\t// --- Trust action buttons (request, respond, cancel, remove) ---",
	"\t\tdocument.addEventListener( 'click', function ( e ) {",
	"\t\t\tvar btn = e.target.closest( '[data-trust-action]' );",
	"\t\t\tif ( ! btn ) { return; }",
	"",
	"\t\t\tvar action   = btn.dataset.trustAction || '';",
	"\t\t\tvar formData = new FormData();",
	"\t\t\tformData.set( 'nonce', nonce );",
	"",
	"\t\t\tswitch ( action ) {",
	"\t\t\t\tcase 'request':",
	"\t\t\t\t\tformData.set( 'action', 'memdir_ajax_trust_request' );",
	"\t\t\t\t\tformData.set( 'target_post_id', btn.dataset.targetPost || '' );",
	"\t\t\t\t\tbreak;",
	"",
	"\t\t\t\tcase 'respond':",
	"\t\t\t\t\tformData.set( 'action', 'memdir_ajax_trust_respond' );",
	"\t\t\t\t\tformData.set( 'trust_id', btn.dataset.trustId || '' );",
	"\t\t\t\t\tformData.set( 'response', btn.dataset.trustResponse || '' );",
	"\t\t\t\t\tbreak;",
	"",
	"\t\t\t\tcase 'cancel':",
	"\t\t\t\t\tformData.set( 'action', 'memdir_ajax_trust_cancel' );",
	"\t\t\t\t\tformData.set( 'trust_id', btn.dataset.trustId || '' );",
	"\t\t\t\t\tbreak;",
	"",
	"\t\t\t\tcase 'remove':",
	"\t\t\t\t\tif ( ! confirm( 'Remove this trust relationship?' ) ) { return; }",
	"\t\t\t\t\tformData.set( 'action', 'memdir_ajax_trust_remove' );",
	"\t\t\t\t\tformData.set( 'trust_id', btn.dataset.trustId || '' );",
	"\t\t\t\t\tbreak;",
	"",
	"\t\t\t\tdefault:",
	"\t\t\t\t\treturn;",
	"\t\t\t}",
	"",
	"\t\t\tbtn.disabled = true;",
	"\t\t\tbtn.textContent = 'Saving\\u2026';",
	"",
	"\t\t\tfetch( ajaxUrl, {",
	"\t\t\t\tmethod:      'POST',",
	"\t\t\t\tcredentials: 'same-origin',",
	"\t\t\t\tbody:        formData,",
	"\t\t\t} ).then( function ( res ) { return res.json(); } )",
	"\t\t\t  .then( function ( json ) {",
	"\t\t\t\tif ( json.success ) {",
	"\t\t\t\t\twindow.location.reload();",
	"\t\t\t\t} else {",
	"\t\t\t\t\talert( ( json.data && json.data.message ) || 'An error occurred.' );",
	"\t\t\t\t\tbtn.disabled = false;",
	"\t\t\t\t}",
	"\t\t\t} ).catch( function () {",
	"\t\t\t\tbtn.disabled = false;",
	"\t\t\t} );",
	"\t\t} );",
	"",
	"\t\t// --- Trust toggle in right panel ---",
	"\t\tvar trustToggle = document.querySelector( 'input[data-trust-toggle=\"1\"]' );",
	"\t\tif ( trustToggle ) {",
	"\t\t\ttrustToggle.addEventListener( 'change', function () {",
	"\t\t\t\tvar nav    = document.querySelector( '.memdir-pills' );",
	"\t\t\t\tvar postId = nav ? ( nav.dataset.postId || '' ) : '';",
	"\t\t\t\tvar enabled = trustToggle.checked;",
	"",
	"\t\t\t\t// Update pill disabled state.",
	"\t\t\t\tif ( nav ) {",
	"\t\t\t\t\tvar pill = nav.querySelector( '.memdir-pill[data-section=\"trust\"]' );",
	"\t\t\t\t\tif ( pill ) {",
	"\t\t\t\t\t\tpill.classList.toggle( 'memdir-pill--disabled', ! enabled );",
	"\t\t\t\t\t}",
	"\t\t\t\t\tupdateAllSectionsBadge( nav );",
	"\t\t\t\t}",
	"",
	"\t\t\t\t// AJAX save via trust-specific endpoint.",
	"\t\t\t\tvar formData = new FormData();",
	"\t\t\t\tformData.set( 'action',  'memdir_ajax_trust_toggle' );",
	"\t\t\t\tformData.set( 'nonce',   nonce );",
	"\t\t\t\tformData.set( 'post_id', postId );",
	"\t\t\t\tformData.set( 'enabled', enabled ? '1' : '0' );",
	"",
	"\t\t\t\tfetch( ajaxUrl, {",
	"\t\t\t\t\tmethod:      'POST',",
	"\t\t\t\t\tcredentials: 'same-origin',",
	"\t\t\t\t\tbody:        formData,",
	"\t\t\t\t} ).then( function () {",
	"\t\t\t\t\twindow.location.reload();",
	"\t\t\t\t} ).catch( function () {",
	"\t\t\t\t\t// Silently fail — UI is already updated.",
	"\t\t\t\t} );",
	"\t\t\t} );",
	"\t\t}",
	"\t}",
	"",
].join('\r\n');

if (content.includes(bootMarker)) {
	content = content.replace(bootMarker, trustFn + bootMarker);
	patchCount++;
	console.log('OK: inserted initTrustNetwork function');
} else {
	console.log('FAIL: could not find boot marker');
}

// --------------------------------------------------------------------------
// 3. Add initTrustNetwork() to boot sequence after initLightbox()
// --------------------------------------------------------------------------
const bootCall = "\t\tinitLightbox();       // GLightbox on view-mode images";
const bootCallNew = "\t\tinitLightbox();       // GLightbox on view-mode images\r\n\t\tinitTrustNetwork();  // trust network action buttons + toggle";

if (content.includes(bootCall)) {
	content = content.replace(bootCall, bootCallNew);
	patchCount++;
	console.log('OK: added initTrustNetwork to boot sequence');
} else {
	console.log('FAIL: could not find boot initLightbox line');
}

// --------------------------------------------------------------------------
// 4. Update header comment
// --------------------------------------------------------------------------
const hdrOld = " *   9. Field PMP          -- per-field icon-button PMP controls injected after each ACF field";
const hdrNew = " *   9. Field PMP          -- per-field icon-button PMP controls injected after each ACF field\r\n *  11. Trust Network     -- trust request/respond/cancel/remove action buttons + toggle";

if (content.includes(hdrOld)) {
	content = content.replace(hdrOld, hdrNew);
	patchCount++;
	console.log('OK: updated header comment');
} else {
	console.log('FAIL: could not find header comment target');
}

// --------------------------------------------------------------------------
// Write
// --------------------------------------------------------------------------
fs.writeFileSync(jsPath, content, 'utf8');
console.log(`\nDone. ${patchCount}/4 patches applied.`);
