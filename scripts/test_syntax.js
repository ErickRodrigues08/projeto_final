const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');

function getFiles(dir, ext) {
  let results = [];
  const list = fs.readdirSync(dir);
  for (const file of list) {
    const fullPath = path.join(dir, file);
    const stat = fs.statSync(fullPath);
    if (stat.isDirectory()) {
      if (file !== 'node_modules' && file !== '.git') {
        results = results.concat(getFiles(fullPath, ext));
      }
    } else if (file.endsWith(ext)) {
      results.push(fullPath);
    }
  }
  return results;
}

console.log('--- LINTING PHP FILES ---');
const phpFiles = getFiles('.', '.php');
let phpErrors = 0;
for (const file of phpFiles) {
  try {
    execSync(`c:\\xampp\\php\\php.exe -l "${file}"`, { stdio: 'pipe' });
  } catch (err) {
    console.error(`PHP Error in ${file}:`, err.stderr ? err.stderr.toString() : err.message);
    phpErrors++;
  }
}
console.log(`PHP Files checked: ${phpFiles.length}, Errors: ${phpErrors}`);

console.log('\n--- LINTING JS FILES ---');
const jsFiles = getFiles('js', '.js');
let jsErrors = 0;
for (const file of jsFiles) {
  if (file.includes('leaflet')) continue; // leaflet.js must not be touched or validated
  try {
    execSync(`node --check "${file}"`, { stdio: 'pipe' });
  } catch (err) {
    console.error(`JS Error in ${file}:`, err.stderr ? err.stderr.toString() : err.message);
    jsErrors++;
  }
}
console.log(`JS Files checked (excluding leaflet): ${jsFiles.length - 1}, Errors: ${jsErrors}`);
