const fs = require('fs');
const path = require('path');

const cssDir = 'C:\\xampp\\htdocs\\projeto_final\\teste4.4\\teste4\\css';
const files = fs.readdirSync(cssDir).filter(f => f.endsWith('.css') && f !== 'leaflet.css');

console.log('Total CSS files:', files.length);

const report = {};

for (const file of files) {
  const content = fs.readFileSync(path.join(cssDir, file), 'utf8');
  const lines = content.split('\n');
  
  const issues = [];
  
  lines.forEach((line, idx) => {
    const lineNum = idx + 1;
    
    // Check fixed large widths
    const widthMatch = line.match(/(?:^|[^-\w])(?:min-)?width\s*:\s*([4-9]\d\d|1\d\d\d)px/i);
    if (widthMatch) {
      issues.push({ line: lineNum, text: line.trim(), issue: `Fixed width ${widthMatch[1]}px` });
    }
    
    // Check large fixed paddings / margins
    const padMatch = line.match(/padding\s*:\s*[^;]*\b([4-9]\d|1\d\d)px\b/i);
    if (padMatch && !line.includes('var(')) {
      // only record if > 40px
      if (parseInt(padMatch[1]) >= 40) {
        issues.push({ line: lineNum, text: line.trim(), issue: `Large padding ${padMatch[1]}px` });
      }
    }
    
    // Check multi-column grids
    const gridMatch = line.match(/grid-template-columns\s*:\s*([^;]+);/i);
    if (gridMatch) {
      const val = gridMatch[1];
      if ((val.includes('1fr 1fr') || val.includes('repeat(3') || val.includes('repeat(4') || val.includes('repeat(5') || val.match(/\d+px\s+\d+px/)) && !val.includes('minmax')) {
        issues.push({ line: lineNum, text: line.trim(), issue: `Multi-column grid without minmax: ${val}` });
      }
    }
    
    // Check nowrap on elements that might overflow
    if (line.includes('white-space: nowrap') && (line.includes('title') || line.includes('nav') || line.includes('card') || line.includes('btn') || line.includes('header'))) {
      issues.push({ line: lineNum, text: line.trim(), issue: `white-space: nowrap on key component` });
    }
  });

  // Check media queries in file
  const mediaQueries = (content.match(/@media[^{]+/g) || []).map(m => m.trim());

  report[file] = {
    totalLines: lines.length,
    mediaQueries,
    issuesCount: issues.length,
    sampleIssues: issues.slice(0, 10)
  };
}

fs.writeFileSync('C:\\xampp\\htdocs\\projeto_final\\teste4.4\\teste4\\scratch\\css_audit_report.json', JSON.stringify(report, null, 2));
console.log('Relatório salvo em css_audit_report.json');

// Print summary
for (const [file, data] of Object.entries(report)) {
  if (data.issuesCount > 0 || data.mediaQueries.length <= 1) {
    console.log(`\n=== ${file} (${data.totalLines} linhas, ${data.mediaQueries.length} @media, ${data.issuesCount} potenciais problemas) ===`);
    console.log('  Media queries:', data.mediaQueries);
    data.sampleIssues.forEach(iss => console.log(`  [L${iss.line}] ${iss.issue} -> ${iss.text}`));
  }
}
