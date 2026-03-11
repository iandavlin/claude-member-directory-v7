/**
 * Patch memdir.js to add client-side image upload validation + limit hints.
 * Run: node tools/patch-image-validation.js
 */
const fs = require('fs');
const path = require('path');

const file = path.join(__dirname, '..', 'assets', 'js', 'memdir.js');
let src = fs.readFileSync(file, 'utf8');

function assertReplace(label, search, replacement) {
	if (!src.includes(search)) {
		console.error('FAILED to find marker for: ' + label);
		console.error('  Expected (first 200 chars): ' + JSON.stringify(search.slice(0, 200)));
		// Show nearby content for debugging
		const key = search.slice(0, 40);
		const idx = src.indexOf(key);
		if (idx !== -1) {
			console.error('  Found key at offset ' + idx + ', context:');
			console.error('  ' + JSON.stringify(src.substring(idx, idx + 300)));
		}
		process.exit(1);
	}
	src = src.replace(search, replacement);
	console.log('OK: ' + label);
}

// --------------------------------------------------------------------------
// 1. Add validateImageFile helper + constants after 'use strict';
// --------------------------------------------------------------------------
const helperBlock = [
	"",
	"\t// -----------------------------------------------------------------------",
	"\t// Image upload constraints (client-side pre-validation)",
	"\t// -----------------------------------------------------------------------",
	"\tvar IMAGE_MAX_SIZE   = 5 * 1024 * 1024; // 5 MB",
	"\tvar IMAGE_MAX_DIM    = 4000;             // px",
	"\tvar IMAGE_MIN_DIM    = 200;              // px",
	"\tvar IMAGE_ALLOWED    = [ 'image/jpeg', 'image/png', 'image/webp' ];",
	"\tvar IMAGE_LIMITS_TEXT = 'JPG, PNG, or WebP \\u2022 Max 5 MB \\u2022 200\\u20134000 px';",
	"",
	"\t/**",
	"\t * Validate an image File object against upload constraints.",
	"\t * Returns a promise that resolves to an error string ('' = valid).",
	"\t */",
	"\tfunction validateImageFile( file ) {",
	"\t\treturn new Promise( function ( resolve ) {",
	"\t\t\tif ( ! file ) { resolve( 'No file selected.' ); return; }",
	"",
	"\t\t\tif ( IMAGE_ALLOWED.indexOf( file.type ) === -1 ) {",
	"\t\t\t\tresolve( 'Invalid file type. Allowed: JPG, PNG, WebP.' );",
	"\t\t\t\treturn;",
	"\t\t\t}",
	"",
	"\t\t\tif ( file.size > IMAGE_MAX_SIZE ) {",
	"\t\t\t\tvar mb = ( file.size / ( 1024 * 1024 ) ).toFixed( 1 );",
	"\t\t\t\tresolve( 'File too large (' + mb + ' MB). Maximum is 5 MB.' );",
	"\t\t\t\treturn;",
	"\t\t\t}",
	"",
	"\t\t\tvar url = URL.createObjectURL( file );",
	"\t\t\tvar img = new Image();",
	"\t\t\timg.onload = function () {",
	"\t\t\t\tURL.revokeObjectURL( url );",
	"\t\t\t\tvar w = img.naturalWidth;",
	"\t\t\t\tvar h = img.naturalHeight;",
	"\t\t\t\tif ( w > IMAGE_MAX_DIM || h > IMAGE_MAX_DIM ) {",
	"\t\t\t\t\tresolve( 'Image too large (' + w + '\\u00d7' + h + 'px). Max ' + IMAGE_MAX_DIM + 'px per side.' );",
	"\t\t\t\t} else if ( w < IMAGE_MIN_DIM || h < IMAGE_MIN_DIM ) {",
	"\t\t\t\t\tresolve( 'Image too small (' + w + '\\u00d7' + h + 'px). Min ' + IMAGE_MIN_DIM + 'px per side.' );",
	"\t\t\t\t} else {",
	"\t\t\t\t\tresolve( '' );",
	"\t\t\t\t}",
	"\t\t\t};",
	"\t\t\timg.onerror = function () {",
	"\t\t\t\tURL.revokeObjectURL( url );",
	"\t\t\t\tresolve( 'Could not read image file.' );",
	"\t\t\t};",
	"\t\t\timg.src = url;",
	"\t\t} );",
	"\t}",
	"",
	"\t/** Create a small hint paragraph for upload areas. */",
	"\tfunction createUploadHint() {",
	"\t\tvar p = document.createElement( 'p' );",
	"\t\tp.className = 'memdir-upload-hint';",
	"\t\tp.textContent = IMAGE_LIMITS_TEXT;",
	"\t\treturn p;",
	"\t}",
	"",
].join("\r\n");

assertReplace(
	'helper block after use strict',
	"\t'use strict';",
	"\t'use strict';\r\n" + helperBlock
);

// --------------------------------------------------------------------------
// 2. Avatar modal \u2014 add hint + wrap upload in validation
// --------------------------------------------------------------------------

// Add hint after "Choose New Photo" button
assertReplace(
	'avatar hint',
	"uploadBtn.textContent = 'Choose New Photo';\r\n\t\t\t\tavFragment.appendChild( uploadBtn );",
	"uploadBtn.textContent = 'Choose New Photo';\r\n\t\t\t\tavFragment.appendChild( uploadBtn );\r\n\r\n\t\t\t\tavFragment.appendChild( createUploadHint() );"
);

// Wrap avatar file change handler in validateImageFile
// Match: fileInput change -> file var -> guard -> avStatus uploading
assertReplace(
	'avatar validation wrap',
	"fileInput.addEventListener( 'change', function () {\r\n\t\t\t\t\tvar file = fileInput.files[ 0 ];\r\n\t\t\t\t\tif ( ! file ) { return; }\r\n\r\n\t\t\t\t\tavStatus.textContent = 'Uploading\\u2026';",
	"fileInput.addEventListener( 'change', function () {\r\n\t\t\t\t\tvar file = fileInput.files[ 0 ];\r\n\t\t\t\t\tif ( ! file ) { return; }\r\n\r\n\t\t\t\t\tvalidateImageFile( file ).then( function ( err ) {\r\n\t\t\t\t\t\tif ( err ) { avStatus.textContent = err; fileInput.value = ''; return; }\r\n\r\n\t\t\t\t\t\tavStatus.textContent = 'Uploading\\u2026';"
);

// Close the .then() \u2014 avatar catch has 7-tab indent, deleteBtn before uploadBtn, no fileInput.value
assertReplace(
	'avatar .then() close',
	"\t\t\t\t\t\t\tavStatus.textContent = 'Network error.';\r\n\t\t\t\t\t\t\tdeleteBtn.disabled = false;\r\n\t\t\t\t\t\t\tuploadBtn.disabled = false;\r\n\t\t\t\t\t\t} );\r\n\t\t\t\t} );\r\n\r\n\t\t\t\t\r\n\t\t\t\t// \u2500\u2500 Avatar link URL \u2500\u2500",
	"\t\t\t\t\t\t\tavStatus.textContent = 'Network error.';\r\n\t\t\t\t\t\t\tdeleteBtn.disabled = false;\r\n\t\t\t\t\t\t\tuploadBtn.disabled = false;\r\n\t\t\t\t\t\t} );\r\n\t\t\t\t\t\t} ); // end validateImageFile .then()\r\n\t\t\t\t} );\r\n\r\n\t\t\t\t\r\n\t\t\t\t// \u2500\u2500 Avatar link URL \u2500\u2500"
);

// --------------------------------------------------------------------------
// 3. Banner modal \u2014 add hint + wrap upload in validation
// --------------------------------------------------------------------------

assertReplace(
	'banner hint',
	"bnUploadBtn.textContent = 'Choose Banner Image';\r\n\t\t\t\tbnFragment.appendChild( bnUploadBtn );",
	"bnUploadBtn.textContent = 'Choose Banner Image';\r\n\t\t\t\tbnFragment.appendChild( bnUploadBtn );\r\n\r\n\t\t\t\tbnFragment.appendChild( createUploadHint() );"
);

// Wrap banner file change
assertReplace(
	'banner validation wrap',
	"bnFileInput.addEventListener( 'change', function () {\r\n\t\t\t\t\tvar file = bnFileInput.files[ 0 ];\r\n\t\t\t\t\tif ( ! file ) { return; }\r\n\r\n\t\t\t\t\tbnStatus.textContent = 'Uploading\\u2026';",
	"bnFileInput.addEventListener( 'change', function () {\r\n\t\t\t\t\tvar file = bnFileInput.files[ 0 ];\r\n\t\t\t\t\tif ( ! file ) { return; }\r\n\r\n\t\t\t\t\tvalidateImageFile( file ).then( function ( err ) {\r\n\t\t\t\t\t\tif ( err ) { bnStatus.textContent = err; bnFileInput.value = ''; return; }\r\n\r\n\t\t\t\t\t\tbnStatus.textContent = 'Uploading\\u2026';"
);

// Banner catch: 7-tab indent, bnDeleteBtn before bnUploadBtn, no bnFileInput.value
// Ends before: var bnDialog = createMiniModal
assertReplace(
	'banner .then() close',
	"\t\t\t\t\t\t\tbnStatus.textContent = 'Network error.';\r\n\t\t\t\t\t\t\tbnDeleteBtn.disabled = false;\r\n\t\t\t\t\t\t\tbnUploadBtn.disabled = false;\r\n\t\t\t\t\t\t} );\r\n\t\t\t\t} );\r\n\r\n\t\t\t\tvar bnDialog = createMiniModal",
	"\t\t\t\t\t\t\tbnStatus.textContent = 'Network error.';\r\n\t\t\t\t\t\t\tbnDeleteBtn.disabled = false;\r\n\t\t\t\t\t\t\tbnUploadBtn.disabled = false;\r\n\t\t\t\t\t\t} );\r\n\t\t\t\t\t\t} ); // end validateImageFile .then()\r\n\t\t\t\t} );\r\n\r\n\t\t\t\tvar bnDialog = createMiniModal"
);

// --------------------------------------------------------------------------
// 4. Single image modal \u2014 add hint + wrap upload in validation
// --------------------------------------------------------------------------

// Add hint after modal.body.appendChild( btnRow );
assertReplace(
	'image modal hint',
	"modal.body.appendChild( btnRow );\r\n\r\n\t\t// Append dialog to document.body",
	"modal.body.appendChild( btnRow );\r\n\r\n\t\tmodal.body.appendChild( createUploadHint() );\r\n\r\n\t\t// Append dialog to document.body"
);

// Wrap single image file change handler (5-tab indent for catch contents)
assertReplace(
	'image validation wrap',
	"fileInput.addEventListener( 'change', function () {\r\n\t\t\tvar file = fileInput.files[ 0 ];\r\n\t\t\tif ( ! file ) { return; }\r\n\r\n\t\t\tstatus.textContent = 'Uploading\\u2026';\r\n\t\t\tuploadBtn.disabled = true;\r\n\t\t\tdeleteBtn.disabled = true;\r\n\r\n\t\t\tvar fd = new FormData();\r\n\t\t\tfd.append( 'action',    'memdir_ajax_upload_image' );",
	"fileInput.addEventListener( 'change', function () {\r\n\t\t\tvar file = fileInput.files[ 0 ];\r\n\t\t\tif ( ! file ) { return; }\r\n\r\n\t\t\tvalidateImageFile( file ).then( function ( err ) {\r\n\t\t\t\tif ( err ) { status.textContent = err; fileInput.value = ''; return; }\r\n\r\n\t\t\t\tstatus.textContent = 'Uploading\\u2026';\r\n\t\t\t\tuploadBtn.disabled = true;\r\n\t\t\t\tdeleteBtn.disabled = true;\r\n\r\n\t\t\t\tvar fd = new FormData();\r\n\t\t\t\tfd.append( 'action',    'memdir_ajax_upload_image' );"
);

// Close the .then() for single image \u2014 after catch block (5-tab indent)
assertReplace(
	'image .then() close',
	"\t\t\t\t\tstatus.textContent = 'Network error.';\r\n\t\t\t\t\tuploadBtn.disabled = false;\r\n\t\t\t\t\tdeleteBtn.disabled = false;\r\n\t\t\t\t\tfileInput.value = '';\r\n\t\t\t\t} );\r\n\t\t} );\r\n\r\n\t\t// --- Delete handler ---",
	"\t\t\t\t\tstatus.textContent = 'Network error.';\r\n\t\t\t\t\tuploadBtn.disabled = false;\r\n\t\t\t\t\tdeleteBtn.disabled = false;\r\n\t\t\t\t\tfileInput.value = '';\r\n\t\t\t\t} );\r\n\t\t\t} ); // end validateImageFile .then()\r\n\t\t} );\r\n\r\n\t\t// --- Delete handler ---"
);

// --------------------------------------------------------------------------
// 5. Gallery modal \u2014 add hint + wrap upload in validation
// --------------------------------------------------------------------------

// Add hint after addBtn
assertReplace(
	'gallery hint',
	"addBtn.textContent = 'Add Images';\r\n\t\tmodal.body.appendChild( addBtn );",
	"addBtn.textContent = 'Add Images';\r\n\t\tmodal.body.appendChild( addBtn );\r\n\r\n\t\tmodal.body.appendChild( createUploadHint() );"
);

// Gallery uploadNext: wrap file upload in validation
assertReplace(
	'gallery validation wrap',
	"\t\t\t\tvar idx = done + errors;\r\n\t\t\t\tstatus.textContent = 'Uploading ' + ( idx + 1 ) + ' of ' + total + '\\u2026';",
	"\t\t\t\tvar idx = done + errors;\r\n\r\n\t\t\t\t// Validate before uploading.\r\n\t\t\t\tvalidateImageFile( files[ idx ] ).then( function ( err ) {\r\n\t\t\t\t\tif ( err ) {\r\n\t\t\t\t\t\tstatus.textContent = 'Skipped file ' + ( idx + 1 ) + ': ' + err;\r\n\t\t\t\t\t\terrors++;\r\n\t\t\t\t\t\tuploadNext();\r\n\t\t\t\t\t\treturn;\r\n\t\t\t\t\t}\r\n\r\n\t\t\t\t\tstatus.textContent = 'Uploading ' + ( idx + 1 ) + ' of ' + total + '\\u2026';"
);

// Close gallery .then() \u2014 after the gallery catch block
assertReplace(
	'gallery .then() close',
	"\t\t\t\t\t.catch( function () {\r\n\t\t\t\t\t\terrors++;\r\n\t\t\t\t\t\tuploadNext();\r\n\t\t\t\t\t} );\r\n\t\t\t}\r\n\r\n\t\t\tuploadNext();",
	"\t\t\t\t\t.catch( function () {\r\n\t\t\t\t\t\terrors++;\r\n\t\t\t\t\t\tuploadNext();\r\n\t\t\t\t\t} );\r\n\t\t\t\t} ); // end validateImageFile .then()\r\n\t\t\t}\r\n\r\n\t\t\tuploadNext();"
);

// --------------------------------------------------------------------------
// 6. Restrict file input accept attributes to only JPG/PNG/WebP
// --------------------------------------------------------------------------
src = src.replace(/fileInput\.accept = 'image\/\*';/g, "fileInput.accept = 'image/jpeg,image/png,image/webp';");
src = src.replace(/bnFileInput\.accept = 'image\/\*';/g, "bnFileInput.accept = 'image/jpeg,image/png,image/webp';");

console.log('\nAll patches applied successfully!');
const acceptCount = (src.match(/image\/jpeg,image\/png,image\/webp/g) || []).length;
console.log('Restricted accept attributes: ' + acceptCount);
const validateCount = (src.match(/validateImageFile/g) || []).length;
console.log('validateImageFile references: ' + validateCount);
const hintCount = (src.match(/createUploadHint/g) || []).length;
console.log('createUploadHint references: ' + hintCount);

fs.writeFileSync(file, src, 'utf8');
console.log('\nFile saved.');
