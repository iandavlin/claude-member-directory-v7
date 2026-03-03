/**
 * Patch: add syncControlsTop() function + boot call to memdir.js
 * Run: node tools/patch-memdir.js
 */
const fs   = require( 'fs' );
const path = require( 'path' );

const file = path.resolve( __dirname, '../assets/js/memdir.js' );
let code   = fs.readFileSync( file, 'utf8' );

const t1 = '\t';
const t2 = '\t\t';
const rn = '\r\n';

// ---------------------------------------------------------------------------
// Change 1: insert syncControlsTop() function before the Boot section
// ---------------------------------------------------------------------------
const BOOT_HEADER =
	t1 + '// -----------------------------------------------------------------------' + rn +
	t1 + '// Boot' + rn +
	t1 + '// -----------------------------------------------------------------------';

const NEW_FN =
	t1 + '// -----------------------------------------------------------------------' + rn +
	t1 + '// 10. Sticky section controls' + rn +
	t1 + '//' + rn +
	t1 + '// The section controls column needs to stick just below the sticky header' + rn +
	t1 + '// zone. We measure .memdir-sticky\'s rendered height + CSS top at runtime' + rn +
	t1 + '// and apply it as an inline style so it always tracks the real layout.' + rn +
	t1 + '// -----------------------------------------------------------------------' + rn +
	rn +
	t1 + 'function syncControlsTop() {' + rn +
	t2 + 'var sticky = document.querySelector( \'.memdir-sticky\' );' + rn +
	t2 + 'if ( ! sticky ) { return; }' + rn +
	t2 + 'var stickyTop    = parseInt( getComputedStyle( sticky ).top, 10 ) || 0;' + rn +
	t2 + 'var controlsTop  = stickyTop + sticky.offsetHeight + 8;' + rn +
	t2 + 'document.querySelectorAll( \'.memdir-section-controls\' ).forEach( function ( el ) {' + rn +
	t2 + t1 + 'el.style.top = controlsTop + \'px\';' + rn +
	t2 + '} );' + rn +
	t1 + '}' + rn +
	rn +
	t1 + '// -----------------------------------------------------------------------' + rn +
	t1 + '// Boot' + rn +
	t1 + '// -----------------------------------------------------------------------';

if ( ! code.includes( BOOT_HEADER ) ) {
	console.error( 'CHANGE 1 NOT FOUND — boot header string missing' );
	process.exit( 1 );
}
code = code.replace( BOOT_HEADER, NEW_FN );
console.log( 'Change 1 OK: inserted syncControlsTop()' );

// ---------------------------------------------------------------------------
// Change 2: call syncControlsTop() in DOMContentLoaded + add resize listener
// ---------------------------------------------------------------------------
const OLD_BOOT_CLOSE =
	t2 + 'hideEmptySectionPills();  // hide pills for PHP-dropped empty/PMP-blocked sections' + rn +
	t2 + 'restoreState();' + rn +
	t1 + '} );' + rn;

const NEW_BOOT_CLOSE =
	t2 + 'hideEmptySectionPills();  // hide pills for PHP-dropped empty/PMP-blocked sections' + rn +
	t2 + 'restoreState();' + rn +
	t2 + 'syncControlsTop();' + rn +
	t1 + '} );' + rn +
	rn +
	t1 + 'window.addEventListener( \'resize\', syncControlsTop );' + rn;

if ( ! code.includes( OLD_BOOT_CLOSE ) ) {
	console.error( 'CHANGE 2 NOT FOUND — boot close string missing' );
	const idx = code.indexOf( 'restoreState' );
	if ( idx !== -1 ) console.error( JSON.stringify( code.slice( idx - 50, idx + 150 ) ) );
	process.exit( 1 );
}
code = code.replace( OLD_BOOT_CLOSE, NEW_BOOT_CLOSE );
console.log( 'Change 2 OK: added syncControlsTop() call + resize listener' );

fs.writeFileSync( file, code, 'utf8' );
console.log( 'memdir.js written successfully.' );
