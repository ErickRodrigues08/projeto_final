const fs = require('fs');
const path = require('path');

const rootDir = path.resolve(__dirname, '..');

const brokenLinks = [];
let totalLinksChecked = 0;

function isExternal(url) {
  return (
    url.startsWith('http://') ||
    url.startsWith('https://') ||
    url.startsWith('//') ||
    url.startsWith('mailto:') ||
    url.startsWith('tel:') ||
    url.startsWith('javascript:') ||
    url.startsWith('#') ||
    url.startsWith('data:')
  );
}

function cleanUrl(raw) {
  let url = raw.trim();
  // ignore template variables or dynamic strings
  if (url.includes('${') || url.includes('<?') || url.includes('+') || url === '') {
    return null;
  }
  // Strip query string and hash
  url = url.split('?')[0].split('#')[0].trim();
  if (!url || isExternal(url)) {
    return null;
  }
  return url;
}

function checkTarget(targetUrl, sourceFile, lineNum, matchType) {
  const cleaned = cleanUrl(targetUrl);
  if (!cleaned) return;

  totalLinksChecked++;

  // Resolve target path:
  // 1. Try relative to root directory
  let resolved = path.resolve(rootDir, cleaned);
  if (fs.existsSync(resolved)) return;

  // 2. Try relative to source file's directory
  let resolvedSource = path.resolve(path.dirname(sourceFile), cleaned);
  if (fs.existsSync(resolvedSource)) return;

  // If still not found, check if it's pointing to root '/'
  if (cleaned.startsWith('/')) {
    let resolvedRoot = path.resolve(rootDir, cleaned.substring(1));
    if (fs.existsSync(resolvedRoot)) return;
  }

  brokenLinks.push({
    file: path.relative(rootDir, sourceFile),
    line: lineNum,
    type: matchType,
    url: targetUrl,
    cleaned
  });
}

function scanFile(filePath) {
  if (filePath.endsWith('leaflet.js')) return;
  if (filePath.includes('fix_links') || filePath.includes('verify_internal_links.js') || filePath.includes('node_modules')) return;

  const content = fs.readFileSync(filePath, 'utf8');
  const lines = content.split('\n');

  lines.forEach((line, index) => {
    const lineNum = index + 1;

    // 1. href="..."
    const hrefMatches = line.matchAll(/\bhref\s*=\s*["']([^"']+)["']/gi);
    for (const m of hrefMatches) {
      checkTarget(m[1], filePath, lineNum, 'href');
    }

    // 2. src="..."
    const srcMatches = line.matchAll(/\bsrc\s*=\s*["']([^"']+)["']/gi);
    for (const m of srcMatches) {
      checkTarget(m[1], filePath, lineNum, 'src');
    }

    // 3. action="..." (make sure not data-action)
    const actionMatches = line.matchAll(/(?<![a-zA-Z\-])action\s*=\s*["']([^"']+)["']/gi);
    for (const m of actionMatches) {
      checkTarget(m[1], filePath, lineNum, 'action');
    }

    // 4. fetch("...")
    const fetchMatches = line.matchAll(/\bfetch\s*\(\s*["'`]([^"'`]+)["'`]/gi);
    for (const m of fetchMatches) {
      checkTarget(m[1], filePath, lineNum, 'fetch');
    }

    // 5. window.location = "..." or window.location.href = "..." or window.location.replace("...")
    const locMatches = line.matchAll(/window\.location(?:\.href|\.replace)?\s*(?:=|\()\s*["'`]([^"'`)]+)["'`]/gi);
    for (const m of locMatches) {
      checkTarget(m[1], filePath, lineNum, 'window.location');
    }

    // 6. header('Location: ...')
    const headerMatches = line.matchAll(/header\s*\(\s*["']Location:\s*([^"';]+)["']/gi);
    for (const m of headerMatches) {
      checkTarget(m[1], filePath, lineNum, 'header-location');
    }
  });
}

function walk(dir) {
  const entries = fs.readdirSync(dir, { withFileTypes: true });
  for (const entry of entries) {
    if (['node_modules', '.git', '.vscode', 'scripts'].includes(entry.name)) continue;
    const fullPath = path.join(dir, entry.name);
    if (entry.isDirectory()) {
      walk(fullPath);
    } else if (entry.isFile()) {
      const ext = path.extname(entry.name).toLowerCase();
      if (['.php', '.js'].includes(ext)) {
        scanFile(fullPath);
      }
    }
  }
}

walk(rootDir);

console.log('=============================================');
console.log(`TOTAL INTERNAL LINKS VERIFIED: ${totalLinksChecked}`);
console.log(`TOTAL BROKEN LINKS FOUND: ${brokenLinks.length}`);
console.log('=============================================');

if (brokenLinks.length > 0) {
  console.log('\nBROKEN LINKS DETAILS:');
  brokenLinks.forEach(b => {
    console.log(`[${b.type}] in ${b.file}:${b.line} -> "${b.url}" (Resolved: ${b.cleaned})`);
  });
  process.exit(1);
} else {
  console.log('\nSUCCESS: 0 broken internal links detected! All links point to existing files.');
  process.exit(0);
}
