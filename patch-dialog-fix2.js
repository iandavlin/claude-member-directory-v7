/**
 * patch-dialog-fix2.js
 *
 * Applies the remaining 2 changes that patch-dialog-fix.js missed
 * due to CRLF line endings.
 *
 * Run: node patch-dialog-fix2.js
 */

const fs = require('fs');
const path = require('path');
const file = path.join(__dirname, 'assets', 'js', 'memdir.js');

let src = fs.readFileSync(file, 'utf8');
let count = 0;

// 1. Add close-event restore handler after fieldContent.appendChild(dialog)
{
  const anchor = '// Append inside fieldContent so saveSection() finds all ACF fields\r\n\t\t\t\tfieldContent.appendChild( dialog );';
  const replacement = anchor +
    '\r\n' +
    '\r\n\t\t\t\t// When the dialog closes (X, backdrop, Escape, or Save),\r\n' +
    '\t\t\t\t// move it back to fieldContent so saveSection() finds its fields.\r\n' +
    '\t\t\t\t// showDialogSafe() moves it to document.body before opening.\r\n' +
    '\t\t\t\tdialog.addEventListener( \'close\', function () {\r\n' +
    '\t\t\t\t\tif ( dialog.parentElement !== fieldContent ) {\r\n' +
    '\t\t\t\t\t\tfieldContent.appendChild( dialog );\r\n' +
    '\t\t\t\t\t}\r\n' +
    '\t\t\t\t} );';

  if (src.includes(anchor) && !src.includes('move it back to fieldContent')) {
    src = src.replace(anchor, replacement);
    count++;
    console.log('✓ Added close-event fieldContent restore');
  } else if (src.includes('move it back to fieldContent')) {
    console.log('⏩ close-event restore already present');
  } else {
    console.log('⚠ Could not find anchor for close-event restore');
  }
}

// 2. In createMiniModal save handler: move dialog back BEFORE saveSection()
{
  const anchor = 'saveBtn.addEventListener( \'click\', function () {\r\n\t\t\t\t\t\tvar sBtn = section.querySelector( \'.memdir-section-save\' );';
  const replacement = 'saveBtn.addEventListener( \'click\', function () {\r\n' +
    '\t\t\t\t\t\t// Move dialog back before saving so saveSection() finds fields\r\n' +
    '\t\t\t\t\t\tif ( dialog.parentElement !== fieldContent ) {\r\n' +
    '\t\t\t\t\t\t\tfieldContent.appendChild( dialog );\r\n' +
    '\t\t\t\t\t\t}\r\n' +
    '\t\t\t\t\t\tvar sBtn = section.querySelector( \'.memdir-section-save\' );';

  if (src.includes(anchor) && !src.includes('Move dialog back before saving')) {
    src = src.replace(anchor, replacement);
    count++;
    console.log('✓ Added pre-save dialog restore in createMiniModal');
  } else if (src.includes('Move dialog back before saving')) {
    console.log('⏩ pre-save restore already present');
  } else {
    console.log('⚠ Could not find anchor for pre-save restore');
  }
}

if (count > 0) {
  fs.writeFileSync(file, src, 'utf8');
  console.log(`\nDone — ${count} additional changes applied to memdir.js`);
} else {
  console.log('\nNo changes made.');
}
