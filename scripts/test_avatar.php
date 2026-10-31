<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/../api/_db_restore.php';

$base = rtrim($argv[1] ?? '', '/');
$url = parse_url($base);
if (!str_starts_with(DB_NAME, 'pl_avatar_test_') || !$url
    || ($url['scheme'] ?? '') !== 'http' || !isset($url['port'])
    || !in_array($url['host'] ?? '', ['localhost', '127.0.0.1'], true)
    || isset($url['user']) || isset($url['pass']) || isset($url['query']) || isset($url['fragment'])) {
    fwrite(STDERR, "Use um banco pl_avatar_test_* e informe uma URL HTTP local com porta explícita.\n");
    exit(1);
}

$db = get_db();
$root = dirname(__DIR__);
$slug = bin2hex(random_bytes(8));
$emails = [];
$files = [];
$trigger = 'qa_avatar_fail_' . $slug;
$triggerCreated = false;
$passed = 0;
$exitCode = 0;

function avatar_test_assert(bool $condition, string $message): void {
    global $passed;
    if (!$condition) throw new RuntimeException($message);
    $passed++;
    fwrite(STDOUT, "PASS {$message}\n");
}

function avatar_test_request(string $path, string $method = 'GET', ?array $body = null, ?string $token = null, bool $json = false): array {
    global $base;
    $headers = $token ? ['X-Session-Token: ' . $token] : [];
    if ($json) $headers[] = 'Content-Type: application/json';
    $curl = curl_init($base . '/' . $path);
    $options = [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true,
        CURLOPT_CUSTOMREQUEST => $method, CURLOPT_HTTPHEADER => $headers,
        CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 30];
    if ($body !== null) $options[CURLOPT_POSTFIELDS] = $json ? json_encode($body) : $body;
    curl_setopt_array($curl, $options);
    $raw = curl_exec($curl);
    if ($raw === false) throw new RuntimeException('Falha HTTP: ' . curl_error($curl));
    $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $headerLength = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
    $responseHeaders = strtolower(substr($raw, 0, $headerLength));
    $content = substr($raw, $headerLength);
    curl_close($curl);
    return ['status' => $status, 'headers' => $responseHeaders, 'body' => $content, 'json' => json_decode($content, true)];
}

function avatar_test_snapshot(int $id): array {
    global $db;
    $q = $db->prepare('SELECT u.avatar, a.mime, a.tamanho, a.versao, OCTET_LENGTH(a.conteudo) AS bytes,
        SHA2(a.conteudo, 256) AS hash FROM usuarios u LEFT JOIN usuarios_avatares a ON a.user_id = u.id WHERE u.id = ?');
    $q->execute([$id]);
    return $q->fetch();
}

function avatar_test_upload(string $file, string $token, array $extra = []): array {
    return avatar_test_request('api/upload.php', 'POST', array_merge([
        'contexto' => 'perfil', 'foto' => new CURLFile($file, 'image/jpeg', 'foto.jpg')
    ], $extra), $token);
}

function avatar_test_import(): int {
    $process = proc_open([PHP_BINARY, __DIR__ . '/migrar_avatares.php'],
        [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes, dirname(__DIR__));
    if (!is_resource($process)) throw new RuntimeException('Não foi possível iniciar o importador.');
    fclose($pipes[0]);
    $output = stream_get_contents($pipes[1]);
    $error = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $code = proc_close($process);
    if ($code !== 0) throw new RuntimeException(trim($output . ' ' . $error));
    return $code;
}

try {
    $packet = $db->prepare('SELECT @@max_allowed_packet');
    $packet->execute();
    avatar_test_assert((int)$packet->fetchColumn() >= 16 * 1024 * 1024, 'MariaDB permite transportar imagens de 5 MB');
    $schema = file_get_contents($root . '/migracao_avatar_binario.sql');
    foreach ([1, 2] as $repeat) {
        foreach (array_filter(array_map('trim', explode(';', $schema))) as $sql) $db->prepare($sql)->execute();
    }
    avatar_test_assert(true, 'Migração incremental reaplicada duas vezes sem perda de dados');

    $users = [];
    foreach (['a', 'b', 'admin'] as $kind) {
        $email = 'qa-avatar-' . $slug . '-' . $kind . '@example.invalid';
        $emails[] = $email;
        $q = $db->prepare('INSERT INTO usuarios (user_code, name, email, password_hash, avatar, email_verified, status, is_admin, saved_prefs)
            VALUES (?, ?, ?, ?, ?, 1, ?, ?, ?)');
        $q->execute(['QA-' . $slug . '-' . $kind, 'QA Avatar ' . $kind, $email,
            password_hash('AvatarQA@123', PASSWORD_DEFAULT), PL_AVATAR_PADRAO, 'ativo', $kind === 'admin' ? 1 : 0, '["rampa"]']);
        $id = (int)$db->lastInsertId();
        $token = bin2hex(random_bytes(32));
        $db->prepare('INSERT INTO sessoes (user_id, session_token, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))')->execute([$id, $token]);
        $users[$kind] = ['id' => $id, 'token' => $token, 'email' => $email];
    }
    $a = $users['a'];
    $b = $users['b'];
    $check = avatar_test_request('api/auth.php?action=check', 'GET', null, $a['token']);
    avatar_test_assert(($check['json']['user']['email'] ?? '') === $a['email'], 'Servidor HTTP usa a mesma conta do banco isolado');

    $jpeg = base64_decode('/9j/4AAQSkZJRgABAQAAAQABAAD/4gHYSUNDX1BST0ZJTEUAAQEAAAHIAAAAAAQwAABtbnRyUkdCIFhZWiAH4AABAAEAAAAAAABhY3NwAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAQAA9tYAAQAAAADTLQAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAlkZXNjAAAA8AAAACRyWFlaAAABFAAAABRnWFlaAAABKAAAABRiWFlaAAABPAAAABR3dHB0AAABUAAAABRyVFJDAAABZAAAAChnVFJDAAABZAAAAChiVFJDAAABZAAAAChjcHJ0AAABjAAAADxtbHVjAAAAAAAAAAEAAAAMZW5VUwAAAAgAAAAcAHMAUgBHAEJYWVogAAAAAAAAb6IAADj1AAADkFhZWiAAAAAAAABimQAAt4UAABjaWFlaIAAAAAAAACSgAAAPhAAAts9YWVogAAAAAAAA9tYAAQAAAADTLXBhcmEAAAAAAAQAAAACZmYAAPKnAAANWQAAE9AAAApbAAAAAAAAAABtbHVjAAAAAAAAAAEAAAAMZW5VUwAAACAAAAAcAEcAbwBvAGcAbABlACAASQBuAGMALgAgADIAMAAxADb/2wBDAAUDBAQEAwUEBAQFBQUGBwwIBwcHBw8LCwkMEQ8SEhEPERETFhwXExQaFRERGCEYGh0dHx8fExciJCIeJBweHx7/2wBDAQUFBQcGBw4ICA4eFBEUHh4eHh4eHh4eHh4eHh4eHh4eHh4eHh4eHh4eHh4eHh4eHh4eHh4eHh4eHh4eHh4eHh7/wAARCAAQABgDASIAAhEBAxEB/8QAFgABAQEAAAAAAAAAAAAAAAAAAgAG/8QAFhABAQEAAAAAAAAAAAAAAAAAAANB/8QAFgEBAQEAAAAAAAAAAAAAAAAABwUI/8QAHREAAQMFAQAAAAAAAAAAAAAAAAKCsgMEBgc1Nv/aAAwDAQACEQMRAD8Aw5T0SnqjiPYouioYdjebuWTSNJGwy2f/2Q==', true);
    $webp = base64_decode('UklGRjwCAABXRUJQVlA4WAoAAAAgAAAAFwAADwAASUNDUMgBAAAAAAHIAAAAAAQwAABtbnRyUkdCIFhZWiAH4AABAAEAAAAAAABhY3NwAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAQAA9tYAAQAAAADTLQAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAlkZXNjAAAA8AAAACRyWFlaAAABFAAAABRnWFlaAAABKAAAABRiWFlaAAABPAAAABR3dHB0AAABUAAAABRyVFJDAAABZAAAAChnVFJDAAABZAAAAChiVFJDAAABZAAAAChjcHJ0AAABjAAAADxtbHVjAAAAAAAAAAEAAAAMZW5VUwAAAAgAAAAcAHMAUgBHAEJYWVogAAAAAAAAb6IAADj1AAADkFhZWiAAAAAAAABimQAAt4UAABjaWFlaIAAAAAAAACSgAAAPhAAAts9YWVogAAAAAAAA9tYAAQAAAADTLXBhcmEAAAAAAAQAAAACZmYAAPKnAAANWQAAE9AAAApbAAAAAAAAAABtbHVjAAAAAAAAAAEAAAAMZW5VUwAAACAAAAAcAEcAbwBvAGcAbABlACAASQBuAGMALgAgADIAMAAxADZWUDggTgAAALADAJ0BKhgAEAA+UR6MRCOhoRgEADgFBKALscgD8AK7/3AADNgAAP7vgNo75Z/jyk4mPTeceQm1P/76AfOWaPJf//vQA/nc0uVJtyAAAA==', true);
    $fixture = function (string $name, string $bytes) use (&$files, $slug): string {
        $path = sys_get_temp_dir() . '/qa-avatar-' . $slug . '-' . $name;
        if (file_put_contents($path, $bytes) !== strlen($bytes)) throw new RuntimeException('Falha ao gerar fixture.');
        $files[] = $path;
        return $path;
    };
    $images = [
        'image/jpeg' => $fixture('foto.jpg', $jpeg),
        'image/png' => $fixture('foto.png', file_get_contents($root . '/' . PL_AVATAR_PADRAO)),
        'image/webp' => $fixture('foto.webp', $webp)
    ];
    $noAuth = avatar_test_upload($images['image/jpeg'], '');
    avatar_test_assert($noAuth['status'] === 401, 'Upload anônimo retorna 401');
    foreach ($images as $mime => $file) {
        avatar_test_assert((new finfo(FILEINFO_MIME_TYPE))->file($file) === $mime && getimagesize($file) !== false, 'Fixture válida: ' . $mime);
        $response = avatar_test_upload($file, $a['token']);
        $imageUrl = $response['json']['url'] ?? '';
        avatar_test_assert($response['status'] === 200 && ($response['json']['avatar'] ?? false) === true, 'Upload persistente: ' . $mime);
        $snapshot = avatar_test_snapshot($a['id']);
        avatar_test_assert($snapshot['hash'] === hash_file('sha256', $file) && $snapshot['versao'] === $snapshot['hash']
            && (int)$snapshot['bytes'] === filesize($file) && (int)$snapshot['tamanho'] === filesize($file), 'Bytes reais e metadados íntegros: ' . $mime);
        $image = avatar_test_request($imageUrl, 'GET', null, $a['token']);
        avatar_test_assert($image['status'] === 200 && $image['body'] === file_get_contents($file)
            && str_contains($image['headers'], 'content-type: ' . $mime), 'Recuperação idêntica: ' . $mime);
        avatar_test_assert(str_contains($image['headers'], 'no-store') && str_contains($image['headers'], 'nosniff'), 'Leitura privada sem cache: ' . $mime);
    }
    $imageUrl = avatar_test_snapshot($a['id'])['avatar'];
    avatar_test_assert(avatar_test_request($imageUrl)['status'] === 401, 'Foto exige autenticação');
    avatar_test_assert(avatar_test_request($imageUrl, 'GET', null, $b['token'])['status'] === 403, 'Outra conta não pode visualizar a foto');
    avatar_test_assert(avatar_test_request($imageUrl, 'GET', null, $users['admin']['token'])['status'] === 200, 'Gestor autorizado conserva acesso à foto');
    $before = avatar_test_snapshot($a['id']);
    $spoof = avatar_test_upload($images['image/jpeg'], $b['token'], ['id' => $a['id'], 'user_id' => $a['id']]);
    avatar_test_assert($spoof['status'] === 200 && avatar_test_snapshot($a['id']) === $before
        && str_starts_with(avatar_test_snapshot($b['id'])['avatar'], 'api/avatar.php?ref=' . $b['id'] . '-'), 'ID forjado no upload não altera outra conta');
    $removeB = avatar_test_request('api/users.php?action=remove_avatar', 'POST', ['id' => $a['id']], $b['token']);
    avatar_test_assert($removeB['status'] === 200 && avatar_test_snapshot($a['id']) === $before
        && avatar_test_snapshot($b['id'])['avatar'] === PL_AVATAR_PADRAO, 'ID forjado na remoção não altera outra conta');

    $profile = avatar_test_request('api/users.php?action=update_profile', 'POST', ['name' => 'QA Avatar atualizado', 'phone' => '11900000000'], $a['token'], true);
    avatar_test_assert($profile['status'] === 200 && avatar_test_snapshot($a['id']) === $before, 'Informações pessoais não sobrescrevem a foto');
    $stale = avatar_test_request('api/users.php?action=update_profile', 'POST', ['avatar' => PL_AVATAR_PADRAO], $a['token'], true);
    avatar_test_assert($stale['status'] === 400 && avatar_test_snapshot($a['id']) === $before, 'Avatar antigo não pode ser reaplicado por update_profile');
    $bridge = avatar_test_request('api/sql_bridge.php?action=query', 'POST', ['sql' => 'SELECT a.conteudo FROM usuarios u JOIN usuarios_avatares a ON a.user_id = u.id', 'params' => []], $a['token'], true);
    avatar_test_assert($bridge['status'] === 403, 'Ponte SQL não expõe conteúdo binário');
    $bridge = avatar_test_request('api/sql_bridge.php?action=run', 'POST', ['sql' => 'UPDATE usuarios SET avatar = ? WHERE id = ?', 'params' => [PL_AVATAR_PADRAO, $a['id']]], $a['token'], true);
    avatar_test_assert($bridge['status'] === 403 && avatar_test_snapshot($a['id']) === $before, 'Ponte SQL não substitui avatar persistido');
    $q = $db->prepare('SELECT COUNT(*) FROM usuarios_avatares WHERE user_id = ?');
    $q->execute([$a['id']]);
    avatar_test_assert((int)$q->fetchColumn() === 1, 'Substituições mantêm uma única imagem por usuário');

    $invalid = $fixture('invalida.jpg', 'Este arquivo não é uma imagem.');
    avatar_test_assert(avatar_test_upload($invalid, $a['token'])['status'] === 415, 'Arquivo inválido retorna 415');
    $large = $fixture('limite.jpg', $jpeg . str_repeat("\0", 5 * 1024 * 1024 - strlen($jpeg)));
    avatar_test_assert(avatar_test_upload($large, $a['token'])['status'] === 200, 'Arquivo válido de exatamente 5 MB é aceito');
    $largeSnapshot = avatar_test_snapshot($a['id']);
    avatar_test_assert((int)$largeSnapshot['bytes'] === 5 * 1024 * 1024 && $largeSnapshot['hash'] === hash_file('sha256', $large), '5 MB são armazenados integralmente');
    $oversized = $fixture('excesso.jpg', file_get_contents($large) . 'x');
    avatar_test_assert(avatar_test_upload($oversized, $a['token'])['status'] === 413
        && avatar_test_snapshot($a['id']) === $largeSnapshot, 'Arquivo maior que 5 MB retorna 413 e preserva a foto');
    $empty = $fixture('vazia.jpg', '');
    avatar_test_assert(avatar_test_upload($empty, $a['token'])['status'] === 400, 'Arquivo vazio retorna 400');

    $db->prepare("CREATE TRIGGER {$trigger} BEFORE UPDATE ON usuarios FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Falha controlada QA avatar'")->execute();
    $triggerCreated = true;
    $failure = avatar_test_upload($images['image/jpeg'], $a['token']);
    avatar_test_assert($failure['status'] === 500 && ($failure['json']['success'] ?? true) === false
        && avatar_test_snapshot($a['id']) === $largeSnapshot, 'Falha SQL no upload reverte bytes e referência');
    $failure = avatar_test_request('api/users.php?action=remove_avatar', 'POST', [], $a['token']);
    avatar_test_assert($failure['status'] === 500 && ($failure['json']['success'] ?? true) === false
        && avatar_test_snapshot($a['id']) === $largeSnapshot, 'Falha SQL na remoção preserva a foto');
    $db->prepare("DROP TRIGGER {$trigger}")->execute();
    $triggerCreated = false;

    $logout = avatar_test_request('api/auth.php?action=logout', 'POST', [], $a['token']);
    $revoked = avatar_test_request('api/auth.php?action=check', 'GET', null, $a['token']);
    avatar_test_assert($logout['status'] === 200 && ($revoked['json']['authenticated'] ?? true) === false, 'Logout revoga a sessão');
    $login = avatar_test_request('api/auth.php?action=login', 'POST', ['email' => $a['email'], 'password' => 'AvatarQA@123'], null, true);
    avatar_test_assert($login['status'] === 200 && ($login['json']['user']['avatar'] ?? '') === $largeSnapshot['avatar'], 'Novo login recupera a foto persistida');
    $a['token'] = $login['json']['session_token'];
    avatar_test_assert(!isset($login['json']['user']['conteudo']), 'Autenticação não duplica imagem no JSON');
    avatar_test_assert(avatar_test_request('api/users.php?action=remove_avatar', 'GET', null, $a['token'])['status'] === 405, 'Remoção exige POST');
    $remove = avatar_test_request('api/users.php?action=remove_avatar', 'POST', [], $a['token']);
    $snapshot = avatar_test_snapshot($a['id']);
    avatar_test_assert($remove['status'] === 200 && $snapshot['avatar'] === PL_AVATAR_PADRAO && $snapshot['hash'] === null, 'Remoção exclui BLOB e restaura padrão');
    $q = $db->prepare('SELECT name, phone, saved_prefs, is_admin FROM usuarios WHERE id = ?');
    $q->execute([$a['id']]);
    $personal = $q->fetch();
    avatar_test_assert($personal['name'] === 'QA Avatar atualizado' && $personal['phone'] === '11900000000'
        && $personal['saved_prefs'] === '["rampa"]' && (int)$personal['is_admin'] === 0, 'Remoção preserva dados pessoais, preferências e permissões');
    avatar_test_request('api/auth.php?action=logout', 'POST', [], $a['token']);
    $login = avatar_test_request('api/auth.php?action=login', 'POST', ['email' => $a['email'], 'password' => 'AvatarQA@123'], null, true);
    avatar_test_assert($login['status'] === 200 && str_starts_with($login['json']['user']['avatar'] ?? '', PL_AVATAR_PADRAO . '?v='), 'Foto removida não reaparece após novo login');
    $a['token'] = $login['json']['session_token'];

    $legacy = 'uploads/' . date('Ymd') . '-' . bin2hex(random_bytes(12)) . '.jpg';
    $legacyFile = $root . '/' . $legacy;
    $handle = fopen($legacyFile, 'x');
    if ($handle === false) throw new RuntimeException('Falha ao criar fixture legada.');
    fwrite($handle, $jpeg);
    fclose($handle);
    $files[] = $legacyFile;
    $db->prepare('UPDATE usuarios SET avatar = ? WHERE id = ?')->execute([$legacy, $a['id']]);
    $legacyCheck = avatar_test_request('api/auth.php?action=check', 'GET', null, $a['token']);
    avatar_test_assert(($legacyCheck['json']['user']['avatar'] ?? '') === $legacy, 'Foto em uploads continua funcionando antes da migração');
    avatar_test_import();
    $snapshot = avatar_test_snapshot($a['id']);
    avatar_test_assert($snapshot['hash'] === hash('sha256', $jpeg) && is_file($legacyFile), 'Migração legada preserva bytes e arquivo antigo');
    avatar_test_import();
    avatar_test_assert(avatar_test_snapshot($a['id']) === $snapshot, 'Importador é idempotente');
    unlink($legacyFile);
    $read = avatar_test_request($snapshot['avatar'], 'GET', null, $a['token']);
    avatar_test_assert($read['status'] === 200 && $read['body'] === $jpeg, 'Foto migrada funciona sem o arquivo em uploads');

    $before = avatar_test_snapshot($a['id']);
    $rating = avatar_test_request('api/upload.php', 'POST', ['foto' => new CURLFile($images['image/png'])], $a['token']);
    $ratingPath = $rating['json']['url'] ?? '';
    if (preg_match('#^uploads/[0-9]{8}-[0-9a-f]{24}\.png$#', $ratingPath)) $files[] = $root . '/' . $ratingPath;
    avatar_test_assert($rating['status'] === 200 && ($rating['json']['avatar'] ?? true) === false
        && is_file($root . '/' . $ratingPath) && avatar_test_snapshot($a['id']) === $before, 'Upload de avaliação conserva arquivo e não altera avatar');
} catch (Throwable $e) {
    fwrite(STDERR, 'FAIL ' . $e->getMessage() . PHP_EOL);
    $exitCode = 1;
} finally {
    if ($triggerCreated) $db->prepare("DROP TRIGGER IF EXISTS {$trigger}")->execute();
    foreach ($emails as $email) {
        $db->prepare('DELETE FROM logs_auditoria WHERE user_id IN (SELECT id FROM usuarios WHERE email = ?)')->execute([$email]);
        $db->prepare('DELETE FROM usuarios WHERE email = ?')->execute([$email]);
    }
    foreach ($files as $file) if (is_file($file)) unlink($file);
    $q = $db->prepare('SELECT COUNT(*) FROM usuarios WHERE email = ?');
    foreach ($emails as $email) {
        $q->execute([$email]);
        if ((int)$q->fetchColumn() !== 0) $exitCode = 1;
    }
}
fwrite(STDOUT, "Resultado: {$passed} verificações aprovadas; saída {$exitCode}; fixtures removidas.\n");
exit($exitCode);
