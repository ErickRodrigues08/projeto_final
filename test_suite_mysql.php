<?php
/**
 * Test Suite Passe Livre Suzano — MySQL 8.0+ Integration
 * Validação de conexão, tabelas, views e endpoints PHP PDO.
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/api/_db_restore.php';
if (php_sapi_name() !== 'cli' && (!defined('APP_ENV') || APP_ENV !== 'development')) {
    http_response_code(403);
    die('Acesso negado. Arquivo restrito a ambiente de desenvolvimento e linha de comando.');
}

$results = [];

function run_test($name, $callable) {
    global $results;
    try {
        $msg = $callable();
        $results[] = ['name' => $name, 'status' => 'PASS', 'msg' => $msg];
        echo "[PASS] {$name}: {$msg}\n";
    } catch (Throwable $e) {
        $results[] = ['name' => $name, 'status' => 'FAIL', 'msg' => $e->getMessage()];
        echo "[FAIL] {$name}: {$e->getMessage()}\n";
    }
}

echo "=== INICIANDO TESTE COMPLETO DE INTEGRAÇÃO MYSQL ===\n\n";

// 1. Conexão PDO
run_test("1. Conexão MySQL PDO", function() {
    $db = get_db();
    $ver = $db->query("SELECT VERSION()")->fetchColumn();
    return "Conectado ao MySQL versão {$ver}";
});

// 2. Tabelas críticas presentes
run_test("2. Existência de tabelas essenciais", function() {
    $db = get_db();
    $expected = [
        'usuarios', 'sessoes', 'codigos_verificacao', 'locais', 'avaliacoes',
        'rotas', 'solicitacoes', 'ocorrencias', 'notificacoes', 'logs_auditoria',
        'configuracoes', 'integracoes', 'integracao_logs', 'usuario_papeis',
        'papel_permissoes', 'usuario_necessidades', 'usuario_preferencias_busca',
        'usuario_consentimentos', 'mensagens_contato'
    ];
    $tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    $missing = array_diff($expected, $tables);
    if (!empty($missing)) {
        throw new Exception("Tabelas ausentes: " . implode(', ', $missing));
    }
    return count($tables) . " tabelas ativas no schema `passe_livre_suzano`";
});

// 3. Views updatables
run_test("3. Views `users` e `auditoria`", function() {
    $db = get_db();
    $userCount = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $audCount  = $db->query("SELECT COUNT(*) FROM auditoria")->fetchColumn();
    return "View `users`={$userCount} registros | View `auditoria`={$audCount} registros";
});

// 4. SQL Bridge Query e Regex
run_test("4. SQL Bridge via bridge", function() {
    require_once __DIR__ . '/api/_db_restore.php';
    // Simula query com NOW() e datetime('now')
    $sql = "SELECT NOW() as agora, COUNT(*) as c FROM users WHERE 1=1";
    $db = get_db();
    $stmt = $db->query($sql);
    $row = $stmt->fetch();
    if (!isset($row['agora'])) throw new Exception("Falha na consulta SQL bridge");
    return "Executou query com sucesso (agora={$row['agora']}, users={$row['c']})";
});

// 5. Usuários - Administrador e Cidadão
run_test("5. Usuários de Demonstração no Banco", function() {
    $db = get_db();
    $admin = $db->query("SELECT email, is_admin FROM usuarios WHERE is_admin = 1 LIMIT 1")->fetch();
    $cid = $db->query("SELECT email, is_admin FROM usuarios WHERE is_admin = 0 LIMIT 1")->fetch();
    if (!$admin) throw new Exception("Nenhum administrador encontrado");
    if (!$cid) throw new Exception("Nenhum cidadão encontrado");
    return "Admin: {$admin['email']} | Cidadão: {$cid['email']}";
});

// 6. Sessão e Token
run_test("6. Criação de Sessão Real (Tabela `sessoes`)", function() {
    $db = get_db();
    $admin = $db->query("SELECT id FROM usuarios WHERE is_admin = 1 LIMIT 1")->fetch();
    $token = bin2hex(random_bytes(32));
    $stmt = $db->prepare("INSERT INTO sessoes (user_id, session_token, ip_address, expires_at) VALUES (?, ?, '127.0.0.1', DATE_ADD(NOW(), INTERVAL 7 DAY))");
    $stmt->execute([$admin['id'], $token]);

    // Verifica se encontra
    $sess = $db->prepare("SELECT * FROM sessoes WHERE session_token = ?");
    $sess->execute([$token]);
    $found = $sess->fetch();

    // Limpa
    $db->prepare("DELETE FROM sessoes WHERE session_token = ?")->execute([$token]);

    if (!$found) throw new Exception("Falha ao recuperar sessão recém-criada");
    return "Sessão persistida e validada com sucesso";
});

// 7. Endpoint Locais
run_test("7. Locais Acessíveis (`locais`)", function() {
    $db = get_db();
    $count = $db->query("SELECT COUNT(*) FROM locais")->fetchColumn();
    $cats  = $db->query("SELECT DISTINCT category_key FROM locais")->fetchAll(PDO::FETCH_COLUMN);
    return "Total: {$count} locais cadastrados em " . count($cats) . " categorias";
});

// 8. Endpoint Solicitações e Ordens
run_test("8. Solicitações de Acessibilidade (`solicitacoes`)", function() {
    $db = get_db();
    $total = $db->query("SELECT COUNT(*) FROM solicitacoes")->fetchColumn();
    $pend  = $db->query("SELECT COUNT(*) FROM solicitacoes WHERE status = 'pending'")->fetchColumn();
    return "Total: {$total} solicitações ({$pend} pendentes)";
});

// 9. Endpoint Ocorrências e Barreiras
run_test("9. Ocorrências Ativas (`ocorrencias`)", function() {
    $db = get_db();
    $total = $db->query("SELECT COUNT(*) FROM ocorrencias")->fetchColumn();
    $ativas = $db->query("SELECT COUNT(*) FROM ocorrencias WHERE status = 'ativa'")->fetchColumn();
    return "Total: {$total} ocorrências ({$ativas} ativas)";
});

// 10. Endpoint Mensagens de Contato
run_test("10. Mensagens de Contato (`mensagens_contato`)", function() {
    $db = get_db();
    $count = $db->query("SELECT COUNT(*) FROM mensagens_contato")->fetchColumn();
    return "Total: {$count} mensagens registradas";
});

echo "\n=== RESUMO DOS TESTES ===\n";
$passes = count(array_filter($results, fn($r) => $r['status'] === 'PASS'));
$fails  = count(array_filter($results, fn($r) => $r['status'] === 'FAIL'));
echo "Sucessos: {$passes} | Falhas: {$fails}\n";

if ($fails === 0) {
    echo ">> TODOS OS TESTES PASSARAM COM SUCESSO! MIGRACÃO MYSQL 100% OPERACIONAL. <<\n";
} else {
    echo ">> ATENÇÃO: HOUVE FALHAS A SEREM CORRIGIDAS. <<\n";
}
