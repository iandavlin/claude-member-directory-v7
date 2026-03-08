#!/usr/bin/env node
/**
 * Patch memdir.js — improve trust network error handling.
 * Adds console.error for debugging + restores button text on failure.
 */

const fs = require('fs');
const path = require('path');

const jsPath = path.join(__dirname, '..', 'assets', 'js', 'memdir.js');
let content = fs.readFileSync(jsPath, 'utf8');

let patchCount = 0;

// 1. Replace the fetch chain with better error handling
const oldFetch = [
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
].join('\r\n');

const newFetch = [
	"\t\t\tvar originalText = btn.textContent;",
	"",
	"\t\t\tfetch( ajaxUrl, {",
	"\t\t\t\tmethod:      'POST',",
	"\t\t\t\tcredentials: 'same-origin',",
	"\t\t\t\tbody:        formData,",
	"\t\t\t} ).then( function ( res ) {",
	"\t\t\t\tif ( ! res.ok ) {",
	"\t\t\t\t\tconsole.error( 'Trust AJAX HTTP error:', res.status, res.statusText );",
	"\t\t\t\t}",
	"\t\t\t\treturn res.json();",
	"\t\t\t} ).then( function ( json ) {",
	"\t\t\t\tif ( json.success ) {",
	"\t\t\t\t\twindow.location.reload();",
	"\t\t\t\t} else {",
	"\t\t\t\t\tvar msg = ( json.data && json.data.message ) || 'An error occurred.';",
	"\t\t\t\t\tconsole.error( 'Trust AJAX error:', msg, json );",
	"\t\t\t\t\talert( msg );",
	"\t\t\t\t\tbtn.disabled = false;",
	"\t\t\t\t\tbtn.textContent = originalText;",
	"\t\t\t\t}",
	"\t\t\t} ).catch( function ( err ) {",
	"\t\t\t\tconsole.error( 'Trust AJAX catch:', err );",
	"\t\t\t\talert( 'Request failed. Check browser console for details.' );",
	"\t\t\t\tbtn.disabled = false;",
	"\t\t\t\tbtn.textContent = originalText;",
	"\t\t\t} );",
].join('\r\n');

if (content.includes(oldFetch)) {
	content = content.replace(oldFetch, newFetch);
	patchCount++;
	console.log('OK: patched fetch chain with better error handling');
} else {
	console.log('FAIL: could not find fetch chain');
}

fs.writeFileSync(jsPath, content, 'utf8');
console.log(`\nDone. ${patchCount}/1 patches applied.`);
