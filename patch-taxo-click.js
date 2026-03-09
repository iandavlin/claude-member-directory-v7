const fs = require('fs');
const file = 'C:/Users/ianda/git-repos/claude-member-directory-v7/assets/js/memdir.js';
let code = fs.readFileSync(file, 'utf8');
const lineEnding = code.includes('\r\n') ? '\r\n' : '\n';
const lines = code.split(/\r?\n/);

// Find the function boundaries
let startLine = -1;
let endLine = -1;
for (let i = 0; i < lines.length; i++) {
	if (lines[i].includes('function createTaxonomySearch( acfField )')) {
		startLine = (i > 0 && lines[i-1].trim().startsWith('// Helper:')) ? i - 1 : i;
	}
	if (startLine >= 0 && endLine < 0 && i > startLine + 10) {
		if (lines[i].trim() === '}' && lines[i-1] && lines[i-1].includes('return wrapper;')) {
			endLine = i;
			break;
		}
	}
}

if (startLine < 0 || endLine < 0) {
	console.error('Could not find function boundaries');
	process.exit(1);
}

console.log('Replacing lines', startLine, 'to', endLine);

const T1 = '\t';
const T2 = '\t\t';
const T3 = '\t\t\t';
const T4 = '\t\t\t\t';
const T5 = '\t\t\t\t\t';
const T6 = '\t\t\t\t\t\t';

const newFunc = [
T1 + '// Helper: Create custom taxonomy search box (replaces select2)',
T2 + 'function createTaxonomySearch( acfField ) {',
T3 + "var selectElement = acfField.querySelector( 'select' );",
T3 + 'if ( ! selectElement ) { return; }',
'',
T3 + "var fieldKey = acfField.dataset.key || '';",
'',
T3 + '// Destroy select2 and hide the entire ACF input wrapper',
T3 + "var acfInput = acfField.querySelector( '.acf-input' );",
T3 + "if ( typeof jQuery !== 'undefined' && jQuery.fn.select2 ) {",
T4 + "try { jQuery( selectElement ).select2( 'destroy' ); } catch ( e ) { /* ok */ }",
T3 + '}',
T3 + "if ( acfInput ) { acfInput.style.display = 'none'; }",
'',
T3 + '// Create search wrapper',
T3 + "var wrapper = document.createElement( 'div' );",
T3 + "wrapper.className = 'memdir-taxo-search';",
'',
T3 + "var input = document.createElement( 'input' );",
T3 + "input.type = 'text';",
T3 + "input.className = 'memdir-taxo-search__input';",
T3 + "input.dataset.memdirSkip = '1';",
T3 + "input.placeholder = 'Type to search\u2026';",
'',
T3 + "var results = document.createElement( 'div' );",
T3 + "results.className = 'memdir-taxo-search__results';",
'',
T3 + '// Selected term badge (visual confirmation)',
T3 + "var badge = document.createElement( 'div' );",
T3 + "badge.className = 'memdir-taxo-search__badge';",
T3 + "badge.style.display = 'none';",
'',
T3 + 'wrapper.appendChild( input );',
T3 + 'wrapper.appendChild( results );',
T3 + 'wrapper.appendChild( badge );',
T3 + 'acfField.appendChild( wrapper );',
'',
T3 + '// Show currently selected value with badge',
T3 + 'function updateSelectedDisplay() {',
T4 + "var selectedOpt = selectElement.querySelector( 'option:checked' );",
T4 + 'if ( selectedOpt && selectedOpt.value ) {',
T5 + 'var name = selectedOpt.textContent.trim();',
T5 + "badge.textContent = '\u2713 ' + name;",
T5 + "badge.style.display = 'block';",
T5 + "input.value = '';",
T5 + "input.placeholder = 'Change selection\u2026';",
T4 + '} else {',
T5 + "badge.textContent = '';",
T5 + "badge.style.display = 'none';",
T5 + "input.value = '';",
T5 + "input.placeholder = 'Type to search\u2026';",
T4 + '}',
T3 + '}',
T3 + 'updateSelectedDisplay();',
'',
T3 + '// Debounce timer',
T3 + 'var debounceTimer = null;',
'',
T3 + "// Read the taxonomy slug from ACF's field config",
T3 + "var taxonomySlug = '';",
T3 + "var acfFieldData = acfField.querySelector( '[data-taxonomy]' );",
T3 + 'if ( acfFieldData ) {',
T4 + "taxonomySlug = acfFieldData.dataset.taxonomy || '';",
T3 + '}',
T3 + '// Fallback: try to find it from select name',
T3 + 'if ( ! taxonomySlug && selectElement.name ) {',
T4 + 'var nameMatch = selectElement.name.match( /acf[.*?]/ );',
T4 + "if ( ! nameMatch ) { taxonomySlug = selectElement.dataset.taxonomy || ''; }",
T3 + '}',
'',
T3 + '// Search terms via our own AJAX endpoint (no ACF nonce issues)',
T3 + 'function searchTerms( query ) {',
T4 + "results.innerHTML = '<div class=\"memdir-taxo-search__no-results\">Searching\u2026</div>';",
T4 + "results.style.display = 'block';",
'',
T4 + "var ajaxUrl = ( typeof mdAjax !== 'undefined' ) ? mdAjax.ajaxurl : '/wp-admin/admin-ajax.php';",
T4 + "var nonce   = ( typeof mdAjax !== 'undefined' ) ? mdAjax.search_nonce : '';",
'',
T4 + 'var formData = new FormData();',
T4 + "formData.append( 'action', 'memdir_search_taxonomy_terms' );",
T4 + "formData.append( 'taxonomy', taxonomySlug );",
T4 + "formData.append( 'search', query );",
T4 + "formData.append( '_wpnonce', nonce );",
'',
T4 + 'fetch( ajaxUrl, {',
T5 + "method: 'POST',",
T5 + "credentials: 'same-origin',",
T5 + 'body: formData',
T4 + '} )',
T4 + '.then( function ( res ) { return res.json(); } )',
T4 + '.then( function ( data ) {',
T5 + "results.innerHTML = '';",
'',
T5 + 'var items = ( data && data.results ) ? data.results : [];',
'',
T5 + 'if ( items.length === 0 ) {',
T6 + "results.innerHTML = '<div class=\"memdir-taxo-search__no-results\">No matches</div>';",
T6 + "results.style.display = 'block';",
T6 + 'return;',
T5 + '}',
'',
T5 + 'items.forEach( function ( term ) {',
T6 + "var item = document.createElement( 'div' );",
T6 + "item.className = 'memdir-taxo-search__result-item';",
T6 + "item.textContent = term.text || term.name || '';",
T6 + "item.dataset.id = term.id || '';",
'',
T6 + '// Prevent mousedown from stealing focus \u2014 stops blur from hiding results',
T6 + "item.addEventListener( 'mousedown', function ( e ) {",
T6 + '\te.preventDefault();',
T6 + '} );',
'',
T6 + "item.addEventListener( 'click', function () {",
T6 + '\t// Deselect all existing options first',
T6 + '\tArray.from( selectElement.options ).forEach( function ( o ) { o.selected = false; } );',
'',
T6 + '\t// Set the select value',
T6 + "\tvar existing = selectElement.querySelector( 'option[value=\"' + term.id + '\"]' );",
T6 + '\tif ( ! existing ) {',
T6 + "\t\tvar opt = document.createElement( 'option' );",
T6 + '\t\topt.value = term.id;',
T6 + "\t\topt.textContent = term.text || term.name || '';",
T6 + '\t\topt.selected = true;',
T6 + '\t\tselectElement.appendChild( opt );',
T6 + '\t} else {',
T6 + '\t\texisting.selected = true;',
T6 + '\t}',
T6 + "\tselectElement.dispatchEvent( new Event( 'change', { bubbles: true } ) );",
'',
T6 + '\t// Close results and show badge',
T6 + "\tresults.innerHTML = '';",
T6 + "\tresults.style.display = 'none';",
T6 + '\tupdateSelectedDisplay();',
T6 + '} );',
'',
T6 + 'results.appendChild( item );',
T5 + '} );',
'',
T5 + "results.style.display = 'block';",
T4 + '} )',
T4 + '.catch( function ( err ) {',
T5 + "console.error( 'Taxonomy search error:', err );",
T5 + "results.innerHTML = '<div class=\"memdir-taxo-search__no-results\">Search error</div>';",
T4 + '} );',
T3 + '}',
'',
T3 + '// Debounced input handler',
T3 + "input.addEventListener( 'input', function () {",
T4 + 'var query = input.value.trim();',
T4 + 'clearTimeout( debounceTimer );',
'',
T4 + 'if ( ! query ) {',
T5 + "results.innerHTML = '';",
T5 + "results.style.display = 'none';",
T5 + 'return;',
T4 + '}',
'',
T4 + 'debounceTimer = setTimeout( function () {',
T5 + 'searchTerms( query );',
T4 + '}, 250 );',
T3 + '} );',
'',
T3 + '// Close results on blur (fallback; mousedown preventDefault is primary guard)',
T3 + "input.addEventListener( 'blur', function () {",
T4 + 'setTimeout( function () {',
T5 + "results.style.display = 'none';",
T4 + '}, 300 );',
T3 + '} );',
'',
T3 + 'return wrapper;',
T2 + '}'
];

const before = lines.slice(0, startLine);
const after = lines.slice(endLine + 1);
const result = [...before, ...newFunc, ...after].join(lineEnding);

fs.writeFileSync(file, result, 'utf8');
console.log('SUCCESS: Patched createTaxonomySearch');
console.log('Old lines:', endLine - startLine + 1, '-> New lines:', newFunc.length);
