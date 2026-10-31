<?php
require_once __DIR__ . '/_db_restore.php';

header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'HEAD') {
    header('Allow: GET, HEAD');
    json_error('Método não permitido.', 405);
}

try {
    $usuario = require_auth();
    $ref = $_GET['ref'] ?? '';
    if (!is_string($ref) || !preg_match('/^([1-9][0-9]*)-([0-9a-f]{64})$/', $ref, $partes)) {
        json_error('Referência de foto inválida.', 400);
    }
    $userId = (int)$partes[1];
    if ($userId !== (int)$usuario['id'] && (int)$usuario['is_admin'] !== 1) {
        json_error('Você não tem permissão para visualizar esta foto.', 403);
    }

    // Referências antigas de solicitações também exibem a foto atual, nunca uma cópia removida.
    $stmt = get_db()->prepare('SELECT conteudo, mime, tamanho FROM usuarios_avatares WHERE user_id = ?');
    $stmt->execute([$userId]);
    $foto = $stmt->fetch();
    if (!$foto) {
        $padrao = dirname(__DIR__) . '/' . PL_AVATAR_PADRAO;
        header('Content-Type: ' . (new finfo(FILEINFO_MIME_TYPE))->file($padrao));
        header('Content-Length: ' . filesize($padrao));
        if ($_SERVER['REQUEST_METHOD'] === 'GET') readfile($padrao);
        exit;
    }
    header('Content-Type: ' . $foto['mime']);
    header('Content-Length: ' . (int)$foto['tamanho']);
    if ($_SERVER['REQUEST_METHOD'] === 'GET') echo $foto['conteudo'];
} catch (\Throwable $e) {
    handle_db_error($e, 'Não foi possível carregar a foto de perfil.');
}
