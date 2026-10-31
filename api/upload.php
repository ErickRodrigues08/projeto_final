<?php
/**
 * Passe Livre Suzano — Upload de fotos (avaliação de local e foto de perfil)
 *
 * POST multipart/form-data com o campo "foto" (aceita também "file" e "avatar").
 * Campo opcional "contexto": quando vale "perfil", a imagem gravada passa a ser
 * a foto de perfil do usuário da sessão (bytes persistidos no banco).
 *
 * Resposta:
 *   { success, url, mime, tamanho, avatar? }
 *
 * Exige sessão válida (mesmo mecanismo dos outros endpoints de api/).
 * Aceita apenas PNG, JPEG e WebP até 5 MB, conferidos pelo conteúdo real do
 * arquivo (finfo), não pela extensão enviada pelo navegador.
 */

require_once __DIR__ . '/_db_restore.php';

if (!defined('PL_UPLOAD_MAX_BYTES')) define('PL_UPLOAD_MAX_BYTES', 5 * 1024 * 1024);

$TIPOS_PERMITIDOS = [
    'image/png'  => 'png',
    'image/jpeg' => 'jpg',
    'image/webp' => 'webp'
];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    json_error('Método não permitido.', 405);
}
try {
    $usuario = require_auth();
} catch (\Throwable $e) {
    handle_db_error($e, 'Não foi possível validar sua sessão. Tente novamente.');
}
$contexto = strtolower(trim((string)($_POST['contexto'] ?? '')));

if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > PL_UPLOAD_MAX_BYTES + 64 * 1024) {
    json_error('A imagem excede o tamanho máximo permitido de 5 MB.', 413, ['code' => 'arquivo_grande']);
}

$arquivo = $_FILES['foto'] ?? $_FILES['file'] ?? $_FILES['avatar'] ?? null;
if (!is_array($arquivo)) {
    $valores = array_values($_FILES);
    $arquivo = $valores[0] ?? null;
}
if (!is_array($arquivo) || ($arquivo['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
    json_error('Nenhum arquivo de imagem foi enviado.', 400, ['code' => 'arquivo_ausente']);
}

switch ($arquivo['error']) {
    case UPLOAD_ERR_OK:
        break;
    case UPLOAD_ERR_INI_SIZE:
    case UPLOAD_ERR_FORM_SIZE:
        json_error('A imagem excede o tamanho máximo permitido de 5 MB.', 413, ['code' => 'arquivo_grande']);
    case UPLOAD_ERR_PARTIAL:
        json_error('O envio da imagem foi interrompido. Tente novamente.', 400, ['code' => 'envio_parcial']);
    default:
        json_error('Não foi possível receber a imagem. Tente novamente.', 500, ['code' => 'falha_no_upload']);
}

$tamanho = (int) ($arquivo['size'] ?? 0);
if ($tamanho <= 0) {
    json_error('Arquivo de imagem vazio.', 400, ['code' => 'arquivo_vazio']);
}
if ($tamanho > PL_UPLOAD_MAX_BYTES) {
    json_error('A imagem excede o tamanho máximo permitido de 5 MB.', 413, ['code' => 'arquivo_grande']);
}

$caminhoTemporario = (string) ($arquivo['tmp_name'] ?? '');
if ($caminhoTemporario === '' || !is_uploaded_file($caminhoTemporario)) {
    json_error('Envio inválido.', 400, ['code' => 'envio_invalido']);
}

$mime = '';
if (class_exists('finfo')) {
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = (string) $finfo->file($caminhoTemporario);
}
if ($mime === '' || !isset($TIPOS_PERMITIDOS[$mime])) {
    json_error('Formato não suportado. Envie PNG, JPEG ou WebP.', 415, ['code' => 'formato_invalido']);
}
$imagem = @getimagesize($caminhoTemporario);
if ($imagem === false || ($imagem['mime'] ?? '') !== $mime) {
    json_error('O arquivo não é uma imagem válida.', 415, ['code' => 'imagem_invalida']);
}

if ($contexto === 'perfil') {
    $conteudo = @file_get_contents($caminhoTemporario);
    if ($conteudo === false || strlen($conteudo) !== $tamanho) {
        json_error('Não foi possível ler a imagem enviada.', 500, ['code' => 'leitura_falhou']);
    }
    $db = get_db();
    try {
        $db->beginTransaction();
        $stmt = $db->prepare('SELECT id FROM usuarios WHERE id = ? FOR UPDATE');
        $stmt->execute([(int)$usuario['id']]);
        if (!$stmt->fetch()) throw new RuntimeException('Conta indisponível.');
        $url = pl_gravar_avatar_binario($db, (int)$usuario['id'], $conteudo, $mime);
        $db->commit();
    } catch (\Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        handle_db_error($e, 'Não foi possível salvar sua foto de perfil. Tente novamente.');
    }
    json_response(['success' => true, 'url' => $url, 'mime' => $mime, 'tamanho' => $tamanho, 'avatar' => true]);
}

$extensao = $TIPOS_PERMITIDOS[$mime];
$nomeFinal = date('Ymd') . '-' . bin2hex(random_bytes(12)) . '.' . $extensao;

$pasta = dirname(__DIR__) . '/uploads';
if (!is_dir($pasta) && !@mkdir($pasta, 0775, true)) {
    error_log('[upload.php] nao foi possivel criar a pasta de uploads: ' . $pasta);
    json_error('O armazenamento de imagens está indisponível no momento.', 500, ['code' => 'pasta_indisponivel']);
}

$destino = $pasta . '/' . $nomeFinal;
if (!@move_uploaded_file($caminhoTemporario, $destino)) {
    error_log('[upload.php] move_uploaded_file falhou para ' . $destino);
    json_error('Não foi possível gravar a imagem. Tente novamente.', 500, ['code' => 'gravacao_falhou']);
}
@chmod($destino, 0644);

$caminhoRelativo = 'uploads/' . $nomeFinal;

json_response([
    'success' => true,
    'url'     => $caminhoRelativo,
    'mime'    => $mime,
    'tamanho' => $tamanho,
    'avatar'  => false
]);
