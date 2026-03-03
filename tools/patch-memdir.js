/**
 * Patch: replace acf.unload.active approach with direct jQuery beforeunload strip.
 * Run: node tools/patch-memdir.js
 */
const fs   = require( 'fs' );
const path = require( 'path' );

const file = path.resolve( __dirname, '../assets/js/memdir.js' );
let code   = fs.readFileSync( file, 'utf8' );

const t6 = '\t\t\t\t\t\t';
const t7 = '\t\t\t\t\t\t\t';
const rn = '\r\n';

const OLD = [
	t6 + '// Disable ACF\'s form-change unload warning before navigating.' + rn,
	t6 + 'if ( typeof acf !== \'undefined\' && acf.unload ) {' + rn,
	t7 + 'acf.unload.active = false;' + rn,
	t6 + '}',
].join( '' );

const NEW = [
	t6 + '// Strip ACF\'s beforeunload warning before navigating.' + rn,
	t6 + 'window.onbeforeunload = null;' + rn,
	t6 + 'if ( typeof jQuery !== \'undefined\' ) { jQuery( window ).off( \'beforeunload\' ); }',
].join( '' );

if ( ! code.includes( OLD ) ) {
	console.error( 'MATCH FAILED. Actual string around "acf.unload":' );
	const idx = code.indexOf( 'acf.unload' );
	if ( idx !== -1 ) {
		console.error( JSON.stringify( code.slice( idx - 100, idx + 100 ) ) );
	}
	process.exit( 1 );
}

code = code.replace( OLD, NEW );
fs.writeFileSync( file, code, 'utf8' );
console.log( 'Patched OK.' );
