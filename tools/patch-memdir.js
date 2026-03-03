/**
 * Patch script: disable ACF unload warning before save-triggered navigation
 * Run: node tools/patch-memdir.js
 */
const fs = require('fs');
const path = require('path');

const jsPath = path.join(__dirname, '../assets/js/memdir.js');
let code = fs.readFileSync(jsPath, 'utf8');
const rn = '\r\n';

// -----------------------------------------------------------------------
// Change 1: Disable ACF's beforeunload handler before navigating on save
// -----------------------------------------------------------------------
const old1 =
	'\t\t\t\t\tsetTimeout( function () {' + rn +
	'\t\t\t\t\t\tvar reloadUrl = new URL( window.location.href );';

const new1 =
	'\t\t\t\t\tsetTimeout( function () {' + rn +
	'\t\t\t\t\t\t// Disable ACF\'s form-change unload warning before navigating.' + rn +
	'\t\t\t\t\t\tif ( typeof acf !== \'undefined\' && acf.unload ) {' + rn +
	'\t\t\t\t\t\t\tacf.unload.active = false;' + rn +
	'\t\t\t\t\t\t}' + rn +
	'\t\t\t\t\t\tvar reloadUrl = new URL( window.location.href );';

if ( code.indexOf(old1) === -1 ) { console.error('CHANGE 1 NOT FOUND'); process.exit(1); }
code = code.replace(old1, new1);
console.log('Change 1 OK: disable ACF unload before navigation');

fs.writeFileSync(jsPath, code, 'utf8');
console.log('memdir.js written successfully.');
