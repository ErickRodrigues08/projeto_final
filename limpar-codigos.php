<?php
/**
 * Limpa códigos expirados (use para debug)
 */
require_once __DIR__ . '/includes/auth_guard.php';
auth_require_admin();

$db = get_db();

// Se passar ?email=xxx, limpa só daquele email
$email = isset($_GET['email']) ? trim($_GET['email']) : '';

echo '<pre style="font-family:monospace;padding:20px;background:#1a1a1a;color:#fff">';
echo "=== LIMPEZA DE CÓDIGOS ===\n\n";

if ($email) {
    $stmt = $db->prepare("DELETE FROM codigos_verificacao WHERE email = ?");
    $stmt->execute([$email]);
    $deleted = $stmt->rowCount();
    echo "✅ Removidos {$deleted} códigos do email: {$email}\n";
} else {
    // Remove todos os códigos expirados ou usados
    $stmt = $db->query("DELETE FROM codigos_verificacao WHERE usado = 1 OR expires_at < NOW()");
    $deleted = $stmt->rowCount();
    echo "✅ Removidos {$deleted} códigos expirados/usados de todos os emails\n";
}

echo "\n<a href='ver-codigos.php' style='color:#51cf66'>Ver códigos restantes</a>\n";
echo "<a href='limpar-codigos.php' style='color:#ff6b6b;margin-left:20px'>Limpar tudo de novo</a>\n";
echo '</pre>';
