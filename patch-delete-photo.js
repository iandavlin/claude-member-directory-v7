const fs = require('fs');
const file = 'C:/Users/ianda/git-repos/claude-member-directory-v7/assets/js/memdir.js';
let code = fs.readFileSync(file, 'utf8');
const le = code.includes('\r\n') ? '\r\n' : '\n';
const lines = code.split(/\r?\n/);

// Find the line: uploadBtn.textContent = 'Choose New Photo';
// Then insert the delete button right after: avFragment.appendChild( uploadBtn );
let insertAfter = -1;
for (let i = 0; i < lines.length; i++) {
	if (lines[i].includes("avFragment.appendChild( uploadBtn );") && lines[i-1] && lines[i-1].includes("'Choose New Photo'")) {
		// Actually we want the appendChild line
		insertAfter = i;
		break;
	}
}

// More robust: find the appendChild(uploadBtn) line that comes after 'Choose New Photo'
if (insertAfter < 0) {
	for (let i = 0; i < lines.length; i++) {
		if (lines[i].includes("uploadBtn.textContent = 'Choose New Photo'")) {
			// Next line should be the appendChild
			for (let j = i + 1; j < i + 5; j++) {
				if (lines[j].includes('avFragment.appendChild( uploadBtn )')) {
					insertAfter = j;
					break;
				}
			}
			break;
		}
	}
}

if (insertAfter < 0) {
	console.error('Could not find insertion point');
	process.exit(1);
}

console.log('Inserting after line', insertAfter, ':', lines[insertAfter]);

const T4 = '\t\t\t\t';
const T5 = '\t\t\t\t\t';
const T6 = '\t\t\t\t\t\t';

const newLines = [
'',
T4 + "var deleteBtn = document.createElement( 'button' );",
T4 + "deleteBtn.type = 'button';",
T4 + "deleteBtn.className = 'memdir-header-modal__avatar-btn memdir-header-modal__avatar-btn--delete';",
T4 + "deleteBtn.textContent = 'Delete Photo';",
T4 + "if ( ! avatarImg || ! avatarImg.src ) { deleteBtn.style.display = 'none'; }",
T4 + "avFragment.appendChild( deleteBtn );",
'',
T4 + "deleteBtn.addEventListener( 'click', function () {",
T5 + "if ( ! confirm( 'Remove your profile photo?' ) ) { return; }",
'',
T5 + "avStatus.textContent = 'Removing\u2026';",
T5 + "deleteBtn.disabled = true;",
T5 + "uploadBtn.disabled = true;",
'',
T5 + 'var fd = new FormData();',
T5 + "fd.append( 'action',  'md_save_section' );",
T5 + "fd.append( 'nonce',   window.mdAjax.nonce );",
T5 + "fd.append( 'post_id', postId );",
T5 + "fd.append( 'acf[' + imageFieldKey + ']', '' );",
'',
T5 + "fetch( window.mdAjax.ajaxurl, { method: 'POST', body: fd } )",
T6 + ".then( function ( r ) { return r.json(); } )",
T6 + ".then( function ( res ) {",
T6 + "\tif ( res.success ) {",
T6 + "\t\tavPreview.src = '';",
T6 + "\t\tavPreview.style.display = 'none';",
T6 + "\t\tif ( avatarImg ) { avatarImg.src = ''; avatarImg.style.display = 'none'; }",
T6 + "\t\tavStatus.textContent = 'Photo removed.';",
T6 + "\t\tdeleteBtn.style.display = 'none';",
T6 + "\t} else {",
T6 + "\t\tavStatus.textContent = 'Error: ' + ( res.data && res.data.message ? res.data.message : 'Remove failed.' );",
T6 + "\t}",
T6 + "\tdeleteBtn.disabled = false;",
T6 + "\tuploadBtn.disabled = false;",
T6 + "} )",
T6 + ".catch( function () {",
T6 + "\tavStatus.textContent = 'Network error.';",
T6 + "\tdeleteBtn.disabled = false;",
T6 + "\tuploadBtn.disabled = false;",
T6 + "} );",
T4 + "} );"
];

const before = lines.slice(0, insertAfter + 1);
const after = lines.slice(insertAfter + 1);
const result = [...before, ...newLines, ...after].join(le);

fs.writeFileSync(file, result, 'utf8');
console.log('SUCCESS: Added delete photo button');
