#!/usr/bin/env node
const fs = require('fs');
const path = require('path');

const jsPath = path.join(__dirname, 'assets/js/memdir.js');
let js = fs.readFileSync(jsPath, 'utf8');

// Find the createMiniModal function and add better close handling
const searchFor = `closeBtn.addEventListener( 'click', function () {
				dialog.close();
			} );`;

const replaceWith = `closeBtn.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				e.stopPropagation();
				dialog.close();
			} );
			
			// Escape key closes modal (browser default, but ensure it works)
			dialog.addEventListener( 'keydown', function ( e ) {
				if ( e.key === 'Escape' ) {
					e.preventDefault();
					dialog.close();
				}
			} );
			
			// Backdrop click closes modal (for extra reliability)
			dialog.addEventListener( 'click', function ( e ) {
				if ( e.target === dialog ) {
					dialog.close();
				}
			} );`;

if (js.includes(searchFor)) {
	js = js.replace(searchFor, replaceWith);
	console.log('✓ Enhanced close button with Escape + backdrop click handlers');
} else {
	console.error('Could not find close button handler');
	process.exit(1);
}

fs.writeFileSync(jsPath, js, 'utf8');
