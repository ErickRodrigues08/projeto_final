<?php
/**
 * Visualizador de códigos OTP (remover após debug)
 */
require_once __DIR__ . '/includes/auth_guard.php';
auth_require_admin();

$db = get_db();

// Se passar ?email=xxx na URL, filtra por email
$email = isset($_GET['email']) ? trim($_GET['email']) : '';

if ($email) {
    $stmt = $db->prepare("
        SELECT id, user_id, email, codigo, tipo, tentativas, usado, expires_at, created_at
        FROM codigos_verificacao
        WHERE email = ?
        ORDER BY created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$email]);
} else {
    $stmt = $db->query("
        SELECT id, user_id, email, codigo, tipo, tentativas, usado, expires_at, created_at
        FROM codigos_verificacao
        ORDER BY created_at DESC
        LIMIT 20
    ");
}

$codigos = $stmt->fetchAll();

echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Códigos OTP — Debug</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #1a1a1a; color: #fff; }
        table { border-collapse: collapse; width: 100%; background: #2a2a2a; }
        th, td { padding: 10px; text-align: left; border: 1px solid #444; }
        th { background: #333; }
        .expired { color: #ff6b6b; }
        .valid { color: #51cf66; }
        .used { color: #868e96; text-decoration: line-through; }
        .code { font-size: 18px; font-weight: bold; letter-spacing: 2px; }
        .filter { margin-bottom: 20px; }
        input { padding: 8px; font-size: 14px; }
    </style>
  <link rel="icon" type="image/x-icon" href="favicon.ico">
  <link rel="icon" type="image/png" sizes="32x32" href="assets/icons/favicon-32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="assets/icons/favicon-16.png">
  <link rel="apple-touch-icon" href="assets/icons/apple-touch-icon.png">
</head>
<body>
    <h1>🔐 Códigos OTP Gerados</h1>
    
    <div class="filter">
        <form method="get">
            <label>Filtrar por email: 
                <input type="email" name="email" value="' . htmlspecialchars($email) . '" placeholder="email@exemplo.com">
            </label>
            <button type="submit">Buscar</button>
            <a href="ver-codigos.php" style="margin-left:10px;color:#51cf66">Ver todos</a>
        </form>
    </div>
    
    <table>
        <thead>
            <tr>
                <th>Email</th>
                <th>Código</th>
                <th>Tipo</th>
                <th>Status</th>
                <th>Tentativas</th>
                <th>Expira em</th>
                <th>Criado em</th>
            </tr>
        </thead>
        <tbody>';

foreach ($codigos as $c) {
    $expirado = strtotime($c['expires_at']) < time();
    $usado    = (int)$c['usado'] === 1;
    $valido   = !$expirado && !$usado;
    
    $statusClass = $usado ? 'used' : ($expirado ? 'expired' : 'valid');
    $statusText  = $usado ? '✓ Usado' : ($expirado ? '✗ Expirado' : '✓ Válido');
    
    $tempoRestante = '';
    if ($valido) {
        $segundos = strtotime($c['expires_at']) - time();
        $minutos = floor($segundos / 60);
        $tempoRestante = " ({$minutos}min)";
    }
    
    echo '<tr>
        <td>' . htmlspecialchars($c['email']) . '</td>
        <td class="code ' . $statusClass . '">' . htmlspecialchars($c['codigo']) . '</td>
        <td>' . htmlspecialchars($c['tipo']) . '</td>
        <td class="' . $statusClass . '">' . $statusText . '</td>
        <td>' . $c['tentativas'] . '/5</td>
        <td>' . date('d/m H:i:s', strtotime($c['expires_at'])) . $tempoRestante . '</td>
        <td>' . date('d/m H:i:s', strtotime($c['created_at'])) . '</td>
    </tr>';
}

if (empty($codigos)) {
    echo '<tr><td colspan="7" style="text-align:center;color:#868e96">Nenhum código encontrado</td></tr>';
}

echo '</tbody>
    </table>
</body>
</html>';
