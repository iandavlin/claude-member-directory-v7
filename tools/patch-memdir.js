/**
 * Patch script: fix save reload caching + dynamic tooltip on pill toggle
 * Run: node tools/patch-memdir.js
 */
const fs = require('fs');
const path = require('path');

const jsPath = path.join(__dirname, '../assets/js/memdir.js');
let code = fs.readFileSync(jsPath, 'utf8');
const rn = '\r\n';

// -----------------------------------------------------------------------
// Change 1: Add cache-buster _t param to save reload URL
// -----------------------------------------------------------------------
const old1 =
	'\t\t\t\t\t\tvar reloadUrl = new URL( window.location.href );' + rn +
	'\t\t\t\t\t\treloadUrl.searchParams.set( \'active_section\', reloadSectionKey );';

const new1 =
	'\t\t\t\t\t\tvar reloadUrl = new URL( window.location.href );' + rn +
	'\t\t\t\t\t\treloadUrl.searchParams.set( \'active_section\', reloadSectionKey );' + rn +
	'\t\t\t\t\t\treloadUrl.searchParams.set( \'_t\', Date.now().toString() );';

if ( code.indexOf(old1) === -1 ) { console.error('CHANGE 1 NOT FOUND'); process.exit(1); }
code = code.replace(old1, new1);
console.log('Change 1 OK: cache-buster _t param on save reload');

// -----------------------------------------------------------------------
// Change 2: Toggle title tooltip when checkbox enables/disables a pill
//
// In bindCheckbox(), right after the pill.classList.toggle line, add
// title attribute management.
// -----------------------------------------------------------------------
const old2 =
	'\t\t\t// Toggle disabled class on the pill.' + rn +
	'\t\t\tpill.classList.toggle( \'memdir-pill--disabled\', ! enabled );';

const new2 =
	'\t\t\t// Toggle disabled class and tooltip on the pill.' + rn +
	'\t\t\tpill.classList.toggle( \'memdir-pill--disabled\', ! enabled );' + rn +
	'\t\t\tif ( ! enabled ) {' + rn +
	'\t\t\t\tpill.setAttribute( \'title\', \'Activate Check Box\' );' + rn +
	'\t\t\t} else {' + rn +
	'\t\t\t\tpill.removeAttribute( \'title\' );' + rn +
	'\t\t\t}';

if ( code.indexOf(old2) === -1 ) { console.error('CHANGE 2 NOT FOUND'); process.exit(1); }
code = code.replace(old2, new2);
console.log('Change 2 OK: dynamic tooltip on pill checkbox toggle');

// -----------------------------------------------------------------------
// Change 3: Update section 7 comment block — sessionStorage is no longer
// used in restoreState, so remove it from the priority list.
// -----------------------------------------------------------------------
const old3 =
	'\t// Priority order on DOMContentLoaded:' + rn +
	'\t//   1. URL param ?active_section={key} -- post-save reloads pass this.' + rn +
	'\t//   2. sessionStorage keyed to post ID -- remembers state within a session.' + rn +
	'\t//   3. Primary section key -- default active pill (not \'all\').' + rn +
	'\t//' + rn +
	'\t// activatePill() writes to sessionStorage on every activation, so the' + rn +
	'\t// stored value stays current as the user navigates between pills.';

const new3 =
	'\t// Priority order on DOMContentLoaded:' + rn +
	'\t//   1. URL param ?active_section={key} -- post-save reloads pass this.' + rn +
	'\t//   2. Primary section key -- default active pill (not \'all\').';

if ( code.indexOf(old3) === -1 ) { console.error('CHANGE 3 NOT FOUND'); process.exit(1); }
code = code.replace(old3, new3);
console.log('Change 3 OK: updated section 7 comment block');

fs.writeFileSync(jsPath, code, 'utf8');
console.log('memdir.js written successfully.');
