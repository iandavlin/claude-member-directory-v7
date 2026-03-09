/**
 * patch-fallback-fix.js
 *
 * Fixes two issues in the avatar modal:
 *
 * 1. Delete button shows on fallback images — the check used avatarImg.src
 *    which is truthy for fallback images. Now checks ACF's hidden input
 *    value instead (empty when fallback is in use).
 *
 * 2. Preview image shows the fallback — modal preview should be empty when
 *    the member hasn't uploaded a real image.
 *
 * Run: node patch-fallback-fix.js
 */

const fs = require('fs');
const path = require('path');
const file = path.join(__dirname, 'assets', 'js', 'memdir.js');

let src = fs.readFileSync(file, 'utf8');
let count = 0;

// 1. Add a variable to read the ACF hidden input value, and use it for
//    delete button + preview visibility checks instead of avatarImg.src.
//
//    Insert `var hasRealImage = ...` right after `imageField.style.display = 'none';`
//    and update the two conditionals that check avatarImg.src.
{
  // Add hasRealImage variable after imageField is hidden
  const anchor1 = "if ( imageField && avatarWrap ) {\r\n\t\t\t\timageField.style.display = 'none';";
  const replacement1 = "if ( imageField && avatarWrap ) {\r\n\t\t\t\timageField.style.display = 'none';\r\n\r\n\t\t\t\t// Check ACF hidden input — empty when member uses a fallback avatar.\r\n\t\t\t\tvar acfHiddenInput = imageField.querySelector( 'input[type=\"hidden\"]' );\r\n\t\t\t\tvar hasRealImage   = acfHiddenInput && acfHiddenInput.value && acfHiddenInput.value !== '0';";

  if (src.includes(anchor1) && !src.includes('hasRealImage')) {
    src = src.replace(anchor1, replacement1);
    count++;
    console.log('✓ Added hasRealImage variable from ACF hidden input');
  } else if (src.includes('hasRealImage')) {
    console.log('⏩ hasRealImage already present');
  } else {
    console.log('⚠ Could not find anchor for hasRealImage');
  }
}

// 2. Fix the preview image: use hasRealImage instead of avatarImg.src
{
  const anchor2 = "avPreview.src = avatarImg ? avatarImg.src : '';\r\n\t\t\t\tavPreview.alt = 'Current photo';\r\n\t\t\t\tif ( ! avatarImg || ! avatarImg.src ) { avPreview.style.display = 'none'; }";
  const replacement2 = "avPreview.src = hasRealImage && avatarImg ? avatarImg.src : '';\r\n\t\t\t\tavPreview.alt = 'Current photo';\r\n\t\t\t\tif ( ! hasRealImage ) { avPreview.style.display = 'none'; }";

  if (src.includes(anchor2)) {
    src = src.replace(anchor2, replacement2);
    count++;
    console.log('✓ Fixed preview image to use hasRealImage');
  } else {
    console.log('⚠ Could not find anchor for preview fix');
  }
}

// 3. Fix the delete button: use hasRealImage instead of avatarImg.src
{
  const anchor3 = "if ( ! avatarImg || ! avatarImg.src ) { deleteBtn.style.display = 'none'; }";
  const replacement3 = "if ( ! hasRealImage ) { deleteBtn.style.display = 'none'; }";

  if (src.includes(anchor3)) {
    src = src.replace(anchor3, replacement3);
    count++;
    console.log('✓ Fixed delete button to use hasRealImage');
  } else {
    console.log('⚠ Could not find anchor for delete button fix');
  }
}

if (count > 0) {
  fs.writeFileSync(file, src, 'utf8');
  console.log(`\nDone — ${count} changes applied to memdir.js`);
} else {
  console.log('\nNo changes made.');
}
