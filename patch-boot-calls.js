/**
 * patch-boot-calls.js — Add initImageUploaders + initLightbox to boot sequence.
 */
const fs = require('fs');
const file = require('path').join(__dirname, 'assets', 'js', 'memdir.js');
let src = fs.readFileSync(file, 'utf8');

const anchor = "initHeaderEditing();  // per-element header pencils + modals\r\n\t\thideEmptySectionPills";
const replacement = "initHeaderEditing();  // per-element header pencils + modals\r\n\t\tinitImageUploaders(); // custom image/gallery upload UIs\r\n\t\tinitLightbox();       // GLightbox on view-mode images\r\n\t\thideEmptySectionPills";

if (src.includes(anchor) && !src.includes('initImageUploaders();')) {
  src = src.replace(anchor, replacement);
  fs.writeFileSync(file, src, 'utf8');
  console.log('✓ Added boot calls');
} else if (src.includes('initImageUploaders();')) {
  console.log('⏩ Already present');
} else {
  console.log('⚠ Anchor not found');
}
