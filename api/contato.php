<?php
/**
 * Passe Livre Suzano — Endpoint do Formulário de Contato
 * Grava em `mensagens_contato` e registra log de auditoria
 */

require_once __DIR__ . '/_db_restore.php';

$data = get_request_data();
$db   = get_db();

$name    = trim($data['name'] ?? '');
$email   = strtolower(trim($data['email'] ?? ''));
$phone   = trim($data['phone'] ?? '');
$subject = trim($data['subject'] ?? 'Contato via Site');
$message = trim($data['message'] ?? '');

if (empty($name)) {
    json_error('Por favor informe seu nome.');
}
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_error('Informe um endereço de e-mail válido.');
}
if (empty($message)) {
    json_error('A mensagem não pode ficar em branco.');
}

// Verifica se há usuário logado
$user = get_auth_user();
$userId = $user ? (int)$user['id'] : null;

try {
    $stmt = $db->prepare("
        INSERT INTO mensagens_contato (name, email, phone, subject, message, user_id, status)
        VALUES (?, ?, ?, ?, ?, ?, 'pendente')
    ");
    $stmt->execute([$name, $email, $phone, $subject, $message, $userId]);
    $msgId = (int)$db->lastInsertId();

    // Registra log de auditoria
    log_audit(
        'contato',
        'Mensagem de Contato Recebida',
        "Mensagem #{$msgId} de \"{$name}\" ({$email}): \"" . mb_substr($message, 0, 80) . "...\"",
        'baixo',
        $userId
    );

    json_response([
        'success'    => true,
        'message_id' => $msgId,
        'message'    => 'Mensagem enviada com sucesso! A equipe do SENAI Suzano entrará em contato.'
    ]);
} catch (Exception $e) {
    json_error('Erro ao salvar mensagem de contato: ' . $e->getMessage(), 500);
}
