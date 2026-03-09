/**
 * patch-dialog-fix.js
 *
 * Fixes the "page locks up" bug when editing header fields from a different
 * pill. The root cause: header editing dialogs live inside a section's
 * .memdir-field-content. When viewing another pill, that section is
 * display:none. showModal() opens the ::backdrop (blocking all clicks)
 * but the dialog can't render from inside a hidden parent.
 *
 * Fix: move dialogs to document.body before showModal(), move them back
 * to fieldContent on close so saveSection() can still find ACF fields.
 *
 * Run: node patch-dialog-fix.js
 */

const fs = require('fs');
const path = require('path');
const file = path.join(__dirname, 'assets', 'js', 'memdir.js');

let src = fs.readFileSync(file, 'utf8');
let count = 0;

// 1. Add showDialogSafe helper inside the section forEach callback,
//    right after the sectionKey/postId declarations.
{
  const anchor = "var postId     = section.dataset.postId  || '';";
  const replacement = anchor + `

			// Helper: safely show a modal dialog by temporarily moving it
			// to document.body. Prevents the dialog from being trapped inside
			// a hidden section when viewing a different pill (e.g. editing
			// Business header fields while viewing the Workspace section).
			// The dialog's 'close' event (added in createMiniModal) moves it
			// back to fieldContent so saveSection() can still find its fields.
			function showDialogSafe( dlg ) {
				document.body.appendChild( dlg );
				dlg.showModal();
			}`;
  if (src.includes(anchor) && !src.includes('showDialogSafe')) {
    src = src.replace(anchor, replacement);
    count++;
    console.log('✓ Added showDialogSafe helper');
  }
}

// 2. In createMiniModal: add a 'close' event listener that moves the
//    dialog back to fieldContent. Insert right after the existing
//    "fieldContent.appendChild( dialog );" line.
{
  const anchor = "// Append inside fieldContent so saveSection() finds all ACF fields\n\t\t\t\tfieldContent.appendChild( dialog );";
  const replacement = anchor + `

				// On close (X, backdrop, Escape, or Save), move the dialog back
				// to fieldContent so saveSection() can collect its ACF fields.
				// showDialogSafe() moves it to document.body before opening.
				dialog.addEventListener( 'close', function () {
					if ( dialog.parentElement !== fieldContent ) {
						fieldContent.appendChild( dialog );
					}
				} );`;
  if (src.includes(anchor) && !src.includes('move the dialog back')) {
    src = src.replace(anchor, replacement);
    count++;
    console.log('✓ Added close-event fieldContent restore');
  }
}

// 3. In createMiniModal save handler: move dialog back to fieldContent
//    BEFORE calling saveSection(), so the form fields are in the DOM tree
//    that saveSection() queries.
{
  const anchor = "saveBtn.addEventListener( 'click', function () {\n\t\t\t\t\t\tvar sBtn = section.querySelector( '.memdir-section-save' );";
  const replacement = "saveBtn.addEventListener( 'click', function () {\n\t\t\t\t\t\t// Move dialog back before saving so saveSection() finds fields\n\t\t\t\t\t\tif ( dialog.parentElement !== fieldContent ) {\n\t\t\t\t\t\t\tfieldContent.appendChild( dialog );\n\t\t\t\t\t\t}\n\t\t\t\t\t\tvar sBtn = section.querySelector( '.memdir-section-save' );";
  if (src.includes(anchor) && !src.includes('Move dialog back before saving')) {
    src = src.replace(anchor, replacement);
    count++;
    console.log('✓ Added pre-save dialog restore in createMiniModal');
  }
}

// 4. Replace all 4 showModal() calls with showDialogSafe()
const replacements = [
  {
    old: "overlay.addEventListener( 'click', function () { avDialog.showModal(); } );",
    new: "overlay.addEventListener( 'click', function () { showDialogSafe( avDialog ); } );",
    label: 'avatar overlay'
  },
  {
    old: "namePencil.addEventListener( 'click', function () { nameDialog.showModal(); } );",
    new: "namePencil.addEventListener( 'click', function () { showDialogSafe( nameDialog ); } );",
    label: 'name pencil'
  },
  {
    old: "taxoDialog.showModal();",
    new: "showDialogSafe( taxoDialog );",
    label: 'taxonomy pencil'
  },
  {
    old: "socialPencil.addEventListener( 'click', function () { socialDialog.showModal(); } );",
    new: "socialPencil.addEventListener( 'click', function () { showDialogSafe( socialDialog ); } );",
    label: 'social pencil'
  }
];

for (const r of replacements) {
  if (src.includes(r.old)) {
    src = src.replace(r.old, r.new);
    count++;
    console.log(`✓ Replaced showModal → showDialogSafe for ${r.label}`);
  } else {
    console.log(`⚠ Could not find anchor for ${r.label}`);
  }
}

if (count > 0) {
  fs.writeFileSync(file, src, 'utf8');
  console.log(`\nDone — ${count} changes applied to memdir.js`);
} else {
  console.log('\nNo changes made.');
}
