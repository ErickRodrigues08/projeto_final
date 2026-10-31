<?php
require_once __DIR__ . '/api/_db_restore.php';
$db = get_db();
$phpTime = date('Y-m-d H:i:s');
$stmt = $db->query("SELECT NOW() as mysql_now");
$mysqlTime = $stmt->fetch()['mysql_now'];
?>
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><title>Relógio</title>  <link rel="icon" type="image/x-icon" href="favicon.ico">
  <link rel="icon" type="image/png" sizes="32x32" href="assets/icons/favicon-32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="assets/icons/favicon-16.png">
  <link rel="apple-touch-icon" href="assets/icons/apple-touch-icon.png">
</head>
<body style="font-family:monospace;padding:40px;background:#1a1a1a;color:#fff">
<h2>Comparação de Horários</h2>

<div style="display:grid;grid-template-columns:200px 1fr;gap:10px;margin:20px 0">
    <div>Navegador (JavaScript):</div>
    <div id="browser-time" style="color:#51cf66;font-size:18px"></div>
    
    <div>Servidor PHP:</div>
    <div style="color:#51cf66;font-size:18px"><?php echo $phpTime; ?></div>
    
    <div>MySQL:</div>
    <div style="color:#51cf66;font-size:18px"><?php echo $mysqlTime; ?></div>
</div>

<div style="margin-top:30px;padding:15px;background:#2a2a2a;border-radius:8px">
    <strong>Diferença detectada:</strong>
    <div id="diff" style="margin-top:10px;font-size:16px;color:#ffa94d"></div>
</div>

<script>
const phpTimeStr = "<?php echo $phpTime; ?>";

function update() {
    const now = new Date();
    const browserTime = now.getFullYear() + '-' +
        String(now.getMonth()+1).padStart(2,'0') + '-' +
        String(now.getDate()).padStart(2,'0') + ' ' +
        String(now.getHours()).padStart(2,'0') + ':' +
        String(now.getMinutes()).padStart(2,'0') + ':' +
        String(now.getSeconds()).padStart(2,'0');
    
    document.getElementById('browser-time').textContent = browserTime;
    
    const phpTime = new Date(phpTimeStr.replace(' ', 'T'));
    const diffMs = now - phpTime;
    const diffSec = Math.abs(Math.round(diffMs / 1000));
    
    if (diffSec > 10) {
        document.getElementById('diff').innerHTML = 
            '❌ ' + diffSec + ' segundos de diferença! O relógio do Windows pode estar errado.';
    } else {
        document.getElementById('diff').innerHTML = 
            '✅ Sincronizados (diferença de ' + diffSec + ' segundos é aceitável)';
    }
}
update();
setInterval(update, 1000);
</script>
</body>
</html>
