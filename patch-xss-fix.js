const fs = require('fs');
const f = 'C:/Users/ianda/git-repos/claude-member-directory-v7/assets/js/memdir.js';
let src = fs.readFileSync(f, 'utf8');

// Fix indentation on line 2042 (inside success:false block, 9 tabs)
src = src.replace(
  "\t\t\t\t\t\t\t\t\timportBtn.innerHTML = importSvg;\r\n\t\t\t\t\timportBtn.appendChild(document.createTextNode(' Import from ' + srcLabel));\r\n\t\t\t\t\t\t\t\t}",
  "\t\t\t\t\t\t\t\t\timportBtn.innerHTML = importSvg;\r\n\t\t\t\t\t\t\t\t\timportBtn.appendChild(document.createTextNode(' Import from ' + srcLabel));\r\n\t\t\t\t\t\t\t\t}"
);

// Fix indentation on line 2049 (inside catch block, 8 tabs)
src = src.replace(
  "\t\t\t\t\t\t\t\timportBtn.innerHTML = importSvg;\r\n\t\t\t\t\timportBtn.appendChild(document.createTextNode(' Import from ' + srcLabel));\r\n\t\t\t\t\t\t\t} );",
  "\t\t\t\t\t\t\t\timportBtn.innerHTML = importSvg;\r\n\t\t\t\t\t\t\t\timportBtn.appendChild(document.createTextNode(' Import from ' + srcLabel));\r\n\t\t\t\t\t\t\t} );"
);

fs.writeFileSync(f, src, 'utf8');
console.log('Indentation fixed.');
