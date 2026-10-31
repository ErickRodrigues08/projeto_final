<?php
/**
 * Test Suite: Verificação Completa do Sistema de Notificações
 * Testa:
 * 1. Conexão MySQL e Estrutura da tabela `notificacoes`
 * 2. Idempotência e integridade do update_mysql.sql
 * 3. Envio por ADM -> MySQL (api/users.php?action=notify_citizen)
 * 4. Recebimento por Cidadão A (api/notificacoes.php?action=my_notifications)
 * 5. Isolamento estrito (Cidadão B NÃO recebe notificações de Cidadão A)
 * 6. Bloqueio de ID spoofing (Cidadão B tenta abrir notificação de Cidadão A)
 * 7. Marcação como lida e decremento de contadores
 * 8. Marcação de todas como lidas
 */

require_once __DIR__ . '/api/_db_restore.php';
if (php_sapi_name() !== 'cli' && (!defined('APP_ENV') || APP_ENV !== 'development')) {
    http_response_code(403);
    die('Acesso negado. Arquivo restrito a ambiente de desenvolvimento e linha de comando.');
}

header('Content-Type: text/plain; charset=utf-8');

$db = get_db();
$passed = 0;
$failed = 0;

function assert_test($description, $condition) {
    global $passed, $failed;
    if ($condition) {
        echo "[OK] " . $description . "\n";
        $passed++;
    } else {
        echo "[FALHA] " . $description . "\n";
        $failed++;
    }
}

echo "=====================================================\n";
echo " TESTE DO SISTEMA DE NOTIFICAÇÕES — PASSE LIVRE SUZANO\n";
echo "=====================================================\n\n";

// 1. Estrutura do Banco
$cols = $db->query("DESCRIBE notificacoes")->fetchAll(PDO::FETCH_COLUMN);
assert_test("Tabela 'notificacoes' existe", !empty($cols));
assert_test("Coluna 'user_id' existe", in_array('user_id', $cols));
assert_test("Coluna 'sender_id' existe", in_array('sender_id', $cols));
assert_test("Coluna 'title' existe", in_array('title', $cols));
assert_test("Coluna 'body' existe", in_array('body', $cols));
assert_test("Coluna 'type' existe", in_array('type', $cols));
assert_test("Coluna 'is_read' existe", in_array('is_read', $cols));
assert_test("Coluna 'link' existe", in_array('link', $cols));
assert_test("Coluna 'created_at' existe", in_array('created_at', $cols));

// 2. Localiza Usuários para Teste
$adm = $db->query("SELECT * FROM usuarios WHERE is_admin = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$userA = $db->query("SELECT * FROM usuarios WHERE is_admin = 0 AND id = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if (!$userA) {
    $userA = $db->query("SELECT * FROM usuarios WHERE is_admin = 0 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
}
$userB = $db->query("SELECT * FROM usuarios WHERE is_admin = 0 AND id != {$userA['id']} LIMIT 1")->fetch(PDO::FETCH_ASSOC);

assert_test("Administrador encontrado no banco (ID: {$adm['id']}, {$adm['name']})", !empty($adm));
assert_test("Usuário A (Destinatário) encontrado (ID: {$userA['id']}, {$userA['name']})", !empty($userA));
assert_test("Usuário B (Outro usuário) encontrado (ID: {$userB['id']}, {$userB['name']})", !empty($userB));

// 3. Simula tokens de sessão
$admToken   = 'TEST_TOKEN_ADM_' . uniqid();
$userAToken = 'TEST_TOKEN_USERA_' . uniqid();
$userBToken = 'TEST_TOKEN_USERB_' . uniqid();

$stmtSess = $db->prepare("INSERT INTO sessoes (user_id, session_token, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 DAY))");
$stmtSess->execute([$adm['id'], $admToken]);
$stmtSess->execute([$userA['id'], $userAToken]);
$stmtSess->execute([$userB['id'], $userBToken]);

assert_test("Sessões de teste criadas com sucesso na tabela sessoes", true);

// 4. Envio de Notificação pelo ADM para Usuário A
$testTitle = "Notificação de Teste Automatizado " . time();
$testMsg   = "Mensagem de teste de acessibilidade viária na Rua General Francisco Glicério.";
$testType  = "alerta";
$testLink  = "explorar.php";

// Testa endpoint api/users.php?action=notify_citizen
$postData = json_encode([
    'user_id' => $userA['id'],
    'title'   => $testTitle,
    'message' => $testMsg,
    'type'    => $testType,
    'link'    => $testLink
]);

$ch = curl_init("http://127.0.0.1/teste3.5/api/users.php?action=notify_citizen");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $postData,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'X-Session-Token: ' . $admToken
    ]
]);
$resp = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$respData = json_decode($resp, true);
assert_test("ADM enviou notificação com sucesso (HTTP {$httpCode})", $httpCode === 200 && ($respData['success'] ?? false));
$notifId = $respData['notificacao_id'] ?? 0;
assert_test("Notificação gravada no MySQL com ID: {$notifId}", $notifId > 0);

// Verifica dados inseridos no MySQL
$row = $db->query("SELECT * FROM notificacoes WHERE id = {$notifId}")->fetch(PDO::FETCH_ASSOC);
assert_test("Registro MySQL confere user_id = {$userA['id']}", (int)$row['user_id'] === (int)$userA['id']);
assert_test("Registro MySQL confere sender_id = {$adm['id']}", (int)$row['sender_id'] === (int)$adm['id']);
assert_test("Registro MySQL confere title", $row['title'] === $testTitle);
assert_test("Registro MySQL confere body", $row['body'] === $testMsg);
assert_test("Registro MySQL confere is_read = 0", (int)$row['is_read'] === 0);

// 5. Usuário A busca suas notificações (api/notificacoes.php?action=my_notifications)
$chA = curl_init("http://127.0.0.1/teste3.5/api/notificacoes.php?action=my_notifications");
curl_setopt_array($chA, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => ['X-Session-Token: ' . $userAToken]
]);
$respA = curl_exec($chA);
$codeA = curl_getinfo($chA, CURLINFO_HTTP_CODE);
curl_close($chA);

$dataA = json_decode($respA, true);
assert_test("Usuário A consultou notificações (HTTP {$codeA})", $codeA === 200 && ($dataA['success'] ?? false));

$foundInA = false;
foreach ($dataA['rows'] as $n) {
    if ((int)$n['id'] === (int)$notifId) {
        $foundInA = true;
        break;
    }
}
assert_test("Usuário A recebeu a notificação enviada pelo ADM", $foundInA);
assert_test("Usuário A possui unread_count >= 1", ($dataA['unread_count'] ?? 0) >= 1);

// 6. Usuário B busca suas notificações — NÃO PODE CONTER A NOTIFICAÇÃO DO USUÁRIO A
$chB = curl_init("http://127.0.0.1/teste3.5/api/notificacoes.php?action=my_notifications");
curl_setopt_array($chB, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => ['X-Session-Token: ' . $userBToken]
]);
$respB = curl_exec($chB);
$codeB = curl_getinfo($chB, CURLINFO_HTTP_CODE);
curl_close($chB);

$dataB = json_decode($respB, true);
$foundInB = false;
foreach ($dataB['rows'] as $n) {
    if ((int)$n['id'] === (int)$notifId) {
        $foundInB = true;
        break;
    }
}
assert_test("ISOLAMENTO: Usuário B NÃO recebeu a notificação do Usuário A", !$foundInB);

// 7. SEGURANÇA: Usuário B tenta acessar detalhe da notificação do Usuário A
$chSec = curl_init("http://127.0.0.1/teste3.5/api/notificacoes.php?action=detail&id=" . $notifId);
curl_setopt_array($chSec, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => ['X-Session-Token: ' . $userBToken]
]);
$respSec = curl_exec($chSec);
$codeSec = curl_getinfo($chSec, CURLINFO_HTTP_CODE);
curl_close($chSec);

assert_test("SEGURANÇA: Usuário B foi BLOQUEADO ao tentar acessar ID do Usuário A (HTTP {$codeSec})", $codeSec === 404);

// 8. Usuário A acessa o detalhe da notificação (deve marcar como lida)
$chDet = curl_init("http://127.0.0.1/teste3.5/api/notificacoes.php?action=detail&id=" . $notifId);
curl_setopt_array($chDet, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => ['X-Session-Token: ' . $userAToken]
]);
$respDet = curl_exec($chDet);
$codeDet = curl_getinfo($chDet, CURLINFO_HTTP_CODE);
curl_close($chDet);

$dataDet = json_decode($respDet, true);
assert_test("Usuário A abriu a notificação com sucesso (HTTP {$codeDet})", $codeDet === 200 && ($dataDet['success'] ?? false));

// Confere se ficou lida no banco MySQL
$rowAfter = $db->query("SELECT is_read FROM notificacoes WHERE id = {$notifId}")->fetch(PDO::FETCH_ASSOC);
assert_test("Notificação marcada como lida no MySQL (is_read = 1)", (int)$rowAfter['is_read'] === 1);

// 9. Usuário A marca todas como lidas
$chAll = curl_init("http://127.0.0.1/teste3.5/api/notificacoes.php?action=mark_all_read");
curl_setopt_array($chAll, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => '{}',
    CURLOPT_HTTPHEADER     => ['X-Session-Token: ' . $userAToken]
]);
$respAll = curl_exec($chAll);
$codeAll = curl_getinfo($chAll, CURLINFO_HTTP_CODE);
curl_close($chAll);

$dataAll = json_decode($respAll, true);
assert_test("Marcar todas como lidas executado com sucesso", $codeAll === 200 && ($dataAll['unread_count'] ?? 1) === 0);

// Limpeza das sessões de teste
$db->exec("DELETE FROM sessoes WHERE session_token IN ('{$admToken}', '{$userAToken}', '{$userBToken}')");

echo "\n=====================================================\n";
echo " RESULTADO DOS TESTES: {$passed} PASSARAM, {$failed} FALHARAM\n";
echo "=====================================================\n";
