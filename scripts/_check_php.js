// Verifica sintaxe PHP usando c:\xampp\php\php.exe
// Escreve resultado em _php_result.txt
const { spawnSync } = require('child_process');
const fs = require('fs');
const path = require('path');

const PHP = 'C:\\xampp\\php\\php.exe';
const ROOT = path.resolve(__dirname, '..');
const OUTFILE = path.join(__dirname, '_php_result.txt');

function getAllPhp(dir, results) {
  results = results || [];
  var entries = fs.readdirSync(dir, { withFileTypes: true });
  for (var i = 0; i < entries.length; i++) {
    var e = entries[i];
    if (e.name === 'node_modules' || e.name === '.git') continue;
    var full = path.join(dir, e.name);
    if (e.isDirectory()) getAllPhp(full, results);
    else if (e.name.endsWith('.php')) results.push(full);
  }
  return results;
}

var phpFiles = getAllPhp(ROOT);
var errors = 0;
var lines = ['=== VERIFICAÇÃO 1: PHP lint (' + phpFiles.length + ' arquivos) ==='];

for (var i = 0; i < phpFiles.length; i++) {
  var f = phpFiles[i];
  var r = spawnSync(PHP, ['-l', f], { encoding: 'utf8', timeout: 10000 });
  var rel = f.replace(ROOT + path.sep, '');
  if (r.status !== 0) {
    var msg = (r.stderr || r.stdout || '').trim();
    lines.push('ERRO: ' + rel + ' — ' + msg);
    errors++;
  } else {
    lines.push('OK: ' + rel);
  }
}

lines.push('');
lines.push('Total: ' + phpFiles.length + ' arquivos. Erros de sintaxe: ' + errors);

fs.writeFileSync(OUTFILE, lines.join('\n') + '\n', 'utf8');
process.stdout.write('PHP lint concluído. Resultado em ' + OUTFILE + '\n');
