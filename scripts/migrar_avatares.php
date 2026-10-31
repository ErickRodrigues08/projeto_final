<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/../api/_db_restore.php';

$db = get_db();
$fotos = $db->prepare("SELECT id, avatar FROM usuarios WHERE avatar LIKE 'uploads/%' ORDER BY id");
$fotos->execute();
$usuarios = $fotos->fetchAll();
$raiz = realpath(__DIR__ . '/../uploads');
$migrados = 0;
$falhas = 0;

foreach ($usuarios as $usuario) {
    try {
        $caminho = $usuario['avatar'];
        $arquivo = realpath(__DIR__ . '/../' . $caminho);
        if (!pl_avatar_path_valido($caminho) || $raiz === false || $arquivo === false
            || !str_starts_with($arquivo, $raiz . DIRECTORY_SEPARATOR) || !is_file($arquivo)) {
            throw new RuntimeException('Arquivo local não encontrado ou caminho inválido.');
        }
        $tamanho = filesize($arquivo);
        if ($tamanho <= 0 || $tamanho > 5 * 1024 * 1024) {
            throw new RuntimeException('Imagem vazia ou maior que 5 MB.');
        }
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($arquivo);
        $imagem = @getimagesize($arquivo);
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)
            || $imagem === false || ($imagem['mime'] ?? '') !== $mime) {
            throw new RuntimeException('Conteúdo de imagem inválido.');
        }
        $conteudo = file_get_contents($arquivo);
        if ($conteudo === false || strlen($conteudo) !== $tamanho) {
            throw new RuntimeException('Não foi possível ler a imagem inteira.');
        }
        $db->beginTransaction();
        $atual = $db->prepare('SELECT avatar FROM usuarios WHERE id = ? FOR UPDATE');
        $atual->execute([(int)$usuario['id']]);
        if ($atual->fetchColumn() !== $caminho) {
            $db->rollBack();
            continue;
        }
        pl_gravar_avatar_binario($db, (int)$usuario['id'], $conteudo, $mime);
        $db->commit();
        $migrados++;
        fwrite(STDOUT, 'Migrado usuário ' . (int)$usuario['id'] . PHP_EOL);
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        $falhas++;
        fwrite(STDERR, 'Preservado usuário ' . (int)$usuario['id'] . ': ' . $e->getMessage() . PHP_EOL);
    }
}
fwrite(STDOUT, "Migrados: {$migrados}; preservados com erro: {$falhas}. Nenhum arquivo foi apagado." . PHP_EOL);
exit($falhas > 0 ? 1 : 0);
