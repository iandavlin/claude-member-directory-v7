const fs = require('fs');
const file = 'C:/Users/ianda/git-repos/claude-member-directory-v7/assets/js/memdir.js';
let code = fs.readFileSync(file, 'utf8');
const le = code.includes('\r\n') ? '\r\n' : '\n';
const lines = code.split(/\r?\n/);

// Find the line: socialDialog.addEventListener( 'close', function () { checkSocialEmpty(); } );
// We'll insert the import button logic right before that line.
let insertBefore = -1;
for (let i = 0; i < lines.length; i++) {
	if (lines[i].includes("socialDialog.addEventListener( 'close', function () { checkSocialEmpty(); } );")) {
		insertBefore = i;
		break;
	}
}

if (insertBefore < 0) {
	console.error('Could not find socialDialog close listener');
	process.exit(1);
}

console.log('Inserting import button code before line', insertBefore);

const T4 = '\t\t\t\t';
const T5 = '\t\t\t\t\t';
const T6 = '\t\t\t\t\t\t';

const newLines = [
'',
T4 + '// --- Import social links from other primary section(s) ---',
T4 + "var socialSources = ( window.mdAjax && window.mdAjax.socialSources ) || {};",
T4 + "var importSvg = '<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"14\" height=\"14\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\" stroke-linecap=\"round\" stroke-linejoin=\"round\"><path d=\"M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4\"/><polyline points=\"7 10 12 15 17 10\"/><line x1=\"12\" y1=\"15\" x2=\"12\" y2=\"3\"/></svg>';",
T4 + "Object.keys( socialSources ).forEach( function ( srcKey ) {",
T5 + "if ( srcKey === sectionKey ) { return; }",
T5 + "var srcLabel = socialSources[ srcKey ];",
'',
T5 + "var importBtn = document.createElement( 'button' );",
T5 + "importBtn.type = 'button';",
T5 + "importBtn.className = 'memdir-import-social-btn';",
T5 + "importBtn.innerHTML = importSvg + ' Import from ' + srcLabel;",
'',
T5 + "// Insert at the top of the modal body",
T5 + "var modalBody = socialDialog.querySelector( '.memdir-header-modal__body' );",
T5 + "if ( modalBody ) {",
T6 + "modalBody.insertBefore( importBtn, modalBody.firstChild );",
T5 + "}",
'',
T5 + "importBtn.addEventListener( 'click', function () {",
T6 + "importBtn.disabled = true;",
T6 + "importBtn.textContent = 'Importing\\u2026';",
'',
T6 + "var fd = new FormData();",
T6 + "fd.append( 'action',     'memdir_ajax_import_social' );",
T6 + "fd.append( 'nonce',      window.mdAjax.nonce );",
T6 + "fd.append( 'post_id',    postId );",
T6 + "fd.append( 'source_key', srcKey );",
T6 + "fd.append( 'target_key', sectionKey );",
'',
T6 + "fetch( window.mdAjax.ajaxurl, { method: 'POST', body: fd } )",
T6 + "\t.then( function ( r ) { return r.json(); } )",
T6 + "\t.then( function ( res ) {",
T6 + "\t\tif ( res.success ) {",
T6 + "\t\t\twindow.onbeforeunload = null;",
T6 + "\t\t\twindow.location.reload();",
T6 + "\t\t} else {",
T6 + "\t\t\talert( 'Import failed: ' + ( res.data && res.data.message ? res.data.message : 'Unknown error.' ) );",
T6 + "\t\t\timportBtn.disabled = false;",
T6 + "\t\t\timportBtn.innerHTML = importSvg + ' Import from ' + srcLabel;",
T6 + "\t\t}",
T6 + "\t} )",
T6 + "\t.catch( function () {",
T6 + "\t\talert( 'Network error. Please try again.' );",
T6 + "\t\timportBtn.disabled = false;",
T6 + "\t\timportBtn.innerHTML = importSvg + ' Import from ' + srcLabel;",
T6 + "\t} );",
T5 + "} );",
T4 + "} );",
''
];

const before = lines.slice(0, insertBefore);
const after = lines.slice(insertBefore);
const result = [...before, ...newLines, ...after].join(le);

fs.writeFileSync(file, result, 'utf8');
console.log('SUCCESS: Added import social links button code');
