/**
 * Patch script: applies 5 UI changes to memdir.js
 * Run: node tools/patch-memdir.js
 */
const fs = require('fs');
const path = require('path');

const jsPath = path.join(__dirname, '../assets/js/memdir.js');
let code = fs.readFileSync(jsPath, 'utf8');
const rn = '\r\n';

// -----------------------------------------------------------------------
// Change 1: reorderPills — push first disabled pill far right via margin-left:auto
// -----------------------------------------------------------------------
const old1 =
	'allPill.concat( primary ).concat( enabled ).concat( disabled ).forEach( function ( p ) {' + rn +
	'\t\t\tnav.appendChild( p );' + rn +
	'\t\t} );' + rn +
	'\t}';

const new1 =
	'allPill.concat( primary ).concat( enabled ).concat( disabled ).forEach( function ( p ) {' + rn +
	'\t\t\tp.style.marginLeft = \'\';' + rn +
	'\t\t\tnav.appendChild( p );' + rn +
	'\t\t} );' + rn +
	rn +
	'\t\t// Push first disabled pill to far right via auto left margin.' + rn +
	'\t\tif ( disabled.length ) {' + rn +
	'\t\t\tdisabled[ 0 ].style.marginLeft = \'auto\';' + rn +
	'\t\t}' + rn +
	'\t}';

if ( code.indexOf(old1) === -1 ) { console.error('CHANGE 1 NOT FOUND'); process.exit(1); }
code = code.replace(old1, new1);
console.log('Change 1 OK: reorderPills push-right');

// -----------------------------------------------------------------------
// Change 2: restoreState — remove sessionStorage restore step
// -----------------------------------------------------------------------
const old2 =
	'\t\t// 2. sessionStorage -- remembers which pill the user last activated for' + rn +
	'\t\t//    this specific post, persisted across navigations in the same session.' + rn +
	'\t\tif ( postId ) {' + rn +
	'\t\t\tvar stored = \'\';' + rn +
	'\t\t\ttry {' + rn +
	'\t\t\t\tstored = sessionStorage.getItem( \'memdir_active_pill_\' + postId ) || \'\';' + rn +
	'\t\t\t} catch ( e ) {' + rn +
	'\t\t\t\t// sessionStorage unavailable -- private browsing or quota exceeded.' + rn +
	'\t\t\t}' + rn +
	'\t\t\tif ( stored ) {' + rn +
	'\t\t\t\tactivatePill( stored );' + rn +
	'\t\t\t\treturn;' + rn +
	'\t\t\t}' + rn +
	'\t\t}' + rn +
	rn +
	'\t\t// 3. Default: activate the primary section pill (not \'all\') so the' + rn +
	'\t\t//    header and content are in sync on first load.';

const new2 =
	'\t\t// 2. Default: activate the primary section pill (not \'all\') so the' + rn +
	'\t\t//    header and content are in sync on every page load.';

if ( code.indexOf(old2) === -1 ) { console.error('CHANGE 2 NOT FOUND'); process.exit(1); }
code = code.replace(old2, new2);
console.log('Change 2 OK: restoreState remove sessionStorage');

// -----------------------------------------------------------------------
// Change 3: saveSection success — reload page with section/tab URL params
// -----------------------------------------------------------------------
const old3 =
	'\t\t\t\t\t// Show \'Saved checkmark\' on button for 2 s, then restore original label.' + rn +
	'\t\t\t\t\tsaveBtn.textContent = \'Saved \\u2713\';' + rn +
	'\t\t\t\t\tsaveBtn.classList.add( \'memdir-section-save--saved\' );' + rn +
	'\t\t\t\t\tsetTimeout( function () {' + rn +
	'\t\t\t\t\t\tsaveBtn.classList.remove( \'memdir-section-save--saved\' );' + rn +
	'\t\t\t\t\t\tsaveBtn.textContent = originalBtnText;' + rn +
	'\t\t\t\t\t}, 2000 );';

const new3 =
	'\t\t\t\t\t// Show \'Saved checkmark\' then reload with section/tab params preserved.' + rn +
	'\t\t\t\t\tsaveBtn.textContent = \'Saved \\u2713\';' + rn +
	'\t\t\t\t\tsaveBtn.classList.add( \'memdir-section-save--saved\' );' + rn +
	'\t\t\t\t\tvar reloadSectionKey = section.dataset.section || \'all\';' + rn +
	'\t\t\t\t\tvar reloadTabBtn = section.querySelector( \'.memdir-section-controls__tab-item.is-active\' );' + rn +
	'\t\t\t\t\tvar reloadTabLabel = reloadTabBtn ? reloadTabBtn.textContent.trim() : \'\';' + rn +
	'\t\t\t\t\tsetTimeout( function () {' + rn +
	'\t\t\t\t\t\tvar reloadUrl = new URL( window.location.href );' + rn +
	'\t\t\t\t\t\treloadUrl.searchParams.set( \'active_section\', reloadSectionKey );' + rn +
	'\t\t\t\t\t\tif ( reloadTabLabel ) {' + rn +
	'\t\t\t\t\t\t\treloadUrl.searchParams.set( \'active_tab\', reloadTabLabel );' + rn +
	'\t\t\t\t\t\t}' + rn +
	'\t\t\t\t\t\twindow.location.href = reloadUrl.toString();' + rn +
	'\t\t\t\t\t}, 1500 );';

if ( code.indexOf(old3) === -1 ) { console.error('CHANGE 3 NOT FOUND'); process.exit(1); }
code = code.replace(old3, new3);
console.log('Change 3 OK: saveSection reload on success');

// Write patched file back.
fs.writeFileSync(jsPath, code, 'utf8');
console.log('memdir.js written successfully.');
