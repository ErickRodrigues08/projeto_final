<?php
/**
 * Passe Livre Suzano — Conexão e Utilitários de Banco de Dados MySQL (PDO)
 * MySQL 8.0+ / MariaDB 10.4+
 */

// Define timezone para Brasília (corrige diferença de 5h com Europe/Berlin)
date_default_timezone_set('America/Sao_Paulo');

// Inicia sessão PHP com configurações seguras
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_only_cookies', '1');
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        ini_set('session.cookie_secure', '1');
    }
    session_start();
}

// Carrega arquivo .env local se existir
if (file_exists(__DIR__ . '/../.env')) {
    $envLines = @file(__DIR__ . '/../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    foreach ($envLines as $envLine) {
        $envLine = trim($envLine);
        if ($envLine === '' || str_starts_with($envLine, '#')) continue;
        if (strpos($envLine, '=') !== false) {
            list($envKey, $envVal) = explode('=', $envLine, 2);
            $envKey = trim($envKey);
            $envVal = trim(trim($envVal), "\"'");
            if (getenv($envKey) === false) {
                putenv("{$envKey}={$envVal}");
                $_ENV[$envKey] = $envVal;
                $_SERVER[$envKey] = $envVal;
            }
        }
    }
}

if (!function_exists('pl_get_env')) {
    function pl_get_env(string $key, $default = null) {
        if (getenv($key) !== false && getenv($key) !== '') return getenv($key);
        if (isset($_ENV[$key]) && $_ENV[$key] !== '') return $_ENV[$key];
        if (isset($_SERVER[$key]) && $_SERVER[$key] !== '') return $_SERVER[$key];
        return $default;
    }
}

// Configurações do Banco de Dados via variáveis de ambiente com fallback local seguro
if (!defined('DB_HOST')) define('DB_HOST', pl_get_env('DB_HOST', '127.0.0.1'));
if (!defined('DB_PORT')) define('DB_PORT', pl_get_env('DB_PORT', '3306'));
if (!defined('DB_NAME')) define('DB_NAME', pl_get_env('DB_NAME', 'passe_livre_suzano'));
if (!defined('DB_USER')) define('DB_USER', pl_get_env('DB_USER', 'root'));
if (!defined('DB_PASS')) define('DB_PASS', pl_get_env('DB_PASS') !== null ? pl_get_env('DB_PASS') : '');
if (!defined('APP_ENV')) define('APP_ENV', pl_get_env('APP_ENV', 'production'));

// Proxies de APIs externas (chaves ficam apenas no servidor; vazias = usar fallback estático)
if (!defined('GEOCODER_PROVIDER')) define('GEOCODER_PROVIDER', strtolower((string) pl_get_env('GEOCODER_PROVIDER', 'nominatim')));
if (!defined('GEOAPIFY_KEY')) define('GEOAPIFY_KEY', (string) pl_get_env('GEOAPIFY_KEY', ''));
if (!defined('ORS_API_KEY')) define('ORS_API_KEY', (string) pl_get_env('ORS_API_KEY', ''));
if (!defined('OVERPASS_URL')) define('OVERPASS_URL', (string) pl_get_env('OVERPASS_URL', 'https://overpass-api.de/api/interpreter'));
if (!defined('MAIL_HOST')) define('MAIL_HOST', (string) pl_get_env('MAIL_HOST', ''));
if (!defined('MAIL_PORT')) define('MAIL_PORT', (int) pl_get_env('MAIL_PORT', 587));
if (!defined('MAIL_USER')) define('MAIL_USER', (string) pl_get_env('MAIL_USER', ''));
if (!defined('MAIL_PASS')) define('MAIL_PASS', (string) pl_get_env('MAIL_PASS', ''));
if (!defined('MAIL_FROM')) define('MAIL_FROM', (string) pl_get_env('MAIL_FROM', ''));
if (!defined('MAIL_CA_FILE')) define('MAIL_CA_FILE', (string) pl_get_env('MAIL_CA_FILE', ''));

/**
 * Retorna a instância única do PDO conectado ao MySQL
 * @return PDO
 */
function get_db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log('[PasseLivre MySQL Error] Conexão falhou: ' . $e->getMessage());
            http_response_code(500);
            $msg = (APP_ENV === 'development')
                ? 'Falha na conexão com o banco de dados MySQL: ' . $e->getMessage()
                : 'Não foi possível concluir a operação. Falha na conexão com o banco de dados.';
            echo json_encode([
                'success' => false,
                'error'   => $msg
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
    return $pdo;
}

/**
 * Define cookie seguro de autenticação
 */
function set_auth_cookie(string $token, int $expires): void {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    setcookie('pl_session_token', $token, [
        'expires'  => $expires,
        'path'     => '/',
        'httponly' => true,
        'secure'   => $secure,
        'samesite' => 'Lax'
    ]);
}

/**
 * Trata erros de banco com logs internos sem expor detalhes estruturais ao cliente
 */
function handle_db_error(\Throwable $e, string $userMessage = 'Não foi possível concluir a operação. Tente novamente.'): void {
    error_log("[PasseLivre DB Exception] " . $e->getMessage() . " em " . $e->getFile() . ":" . $e->getLine());
    $extra = (APP_ENV === 'development') ? ['debug' => $e->getMessage()] : [];
    json_error($userMessage, 500, $extra);
}

/**
 * Foto padrão de qualquer conta sem foto própria. É um arquivo real do projeto
 * (assets/images/avatar.jpg) e deve aparecer como imagem — não como círculo com
 * a inicial do nome.
 */
if (!defined('PL_AVATAR_PADRAO')) {
    define('PL_AVATAR_PADRAO', 'assets/images/avatar.jpg');
}

/**
 * Reconhece referências de fotos binárias, uploads antigos e a foto padrão.
 *
 * `usuarios.avatar` é VARCHAR(255) e o servidor roda sem sql_mode estrito: um
 * `data:` URL enviado pelo navegador seria truncado em silêncio e salvo como
 * sucesso, resultando em uma imagem quebrada. Essa validação fecha essa porta.
 */
function pl_avatar_path_valido($caminho): bool {
    if ($caminho === null) return true;
    if (!is_string($caminho)) return false;
    $c = trim($caminho);
    if ($c === '' || $c === PL_AVATAR_PADRAO) return true;
    if (preg_match('#^api/avatar\.php\?ref=[1-9][0-9]*-[0-9a-f]{64}$#', $c) === 1) return true;
    return preg_match('#^uploads/[0-9]{8}-[0-9a-f]{24}\.(?:jpg|png|webp)$#', $c) === 1;
}

function pl_gravar_avatar_binario(PDO $db, int $userId, string $conteudo, string $mime): string {
    $versao = hash('sha256', $conteudo);
    $url = 'api/avatar.php?ref=' . $userId . '-' . $versao;
    $stmt = $db->prepare('INSERT INTO usuarios_avatares (user_id, conteudo, mime, tamanho, versao)
        VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE conteudo = VALUES(conteudo),
        mime = VALUES(mime), tamanho = VALUES(tamanho), versao = VALUES(versao), updated_at = NOW()');
    $stmt->bindValue(1, $userId, PDO::PARAM_INT);
    $stmt->bindValue(2, $conteudo, PDO::PARAM_LOB);
    $stmt->bindValue(3, $mime);
    $stmt->bindValue(4, strlen($conteudo), PDO::PARAM_INT);
    $stmt->bindValue(5, $versao);
    $stmt->execute();
    $db->prepare('UPDATE usuarios SET avatar = ?, updated_at = NOW() WHERE id = ?')->execute([$url, $userId]);
    return $url;
}

/**
 * URL de um avatar que mora em assets/ com a data do arquivo como versão.
 *
 * Diferente de uploads/, que ganha um nome novo a cada envio, a foto padrão tem
 * URL fixa: quando o arquivo é substituído no servidor, o navegador continua
 * servindo a cópia antiga do próprio cache (o Apache não envia Cache-Control, e
 * ele estima a validade pela data do arquivo). `?v=` cria uma chave nova.
 */
function pl_avatar_asset_versionado(string $caminho): string {
    $arquivo = dirname(__DIR__) . '/' . $caminho;
    return $caminho . '?v=' . (is_file($arquivo) ? date('Ymd', (int)filemtime($arquivo)) : '0');
}

/**
 * Caminho seguro para EXIBIR o avatar de uma conta. Diferente de
 * pl_avatar_path_valido() (usado para gravar), aqui também passam os caminhos
 * já semeados em assets/ e a URL do Google; qualquer valor vazio, `data:` ou
 * truncado cai na foto padrão, para nunca renderizar <img> quebrada.
 *
 * Só o retorno é versionado — `usuarios.avatar` continua gravado sem query.
 */
function pl_avatar_exibicao($valor): string {
    if (!is_string($valor)) return pl_avatar_asset_versionado(PL_AVATAR_PADRAO);
    $c = trim($valor);
    if ($c === '') return pl_avatar_asset_versionado(PL_AVATAR_PADRAO);
    if (preg_match('#^api/avatar\.php\?ref=[1-9][0-9]*-[0-9a-f]{64}$#', $c) === 1) return $c;
    if (preg_match('#^uploads/[0-9]{8}-[0-9a-f]{24}\.(?:jpg|png|webp)$#', $c) === 1) return $c;
    if (preg_match('#^assets/[\w./-]+\.(?:jpg|jpeg|png|webp)$#i', $c) === 1) return pl_avatar_asset_versionado($c);
    if (preg_match('#^https://[^"\'\s<>]+#i', $c) === 1) return $c;
    return pl_avatar_asset_versionado(PL_AVATAR_PADRAO);
}

/**
 * Remove um arquivo de avatar do diretório de uploads, conferindo o caminho real
 * e a posse. Nunca apaga nada fora de uploads/ nem um arquivo que outro usuário
 * ainda referencia. Retorna true quando apaga.
 */
function pl_apagar_avatar_arquivo(?string $caminho): bool {
    if (!pl_avatar_path_valido($caminho) || trim((string)$caminho) === '') {
        return false;
    }
    // A foto padrão é compartilhada por todas as contas: jamais deve ser apagada.
    if (trim((string)$caminho) === PL_AVATAR_PADRAO) {
        return false;
    }

    $raizUploads = realpath(dirname(__DIR__) . '/uploads');
    $alvo        = realpath(dirname(__DIR__) . '/' . trim($caminho));
    if ($raizUploads === false || $alvo === false) {
        return false;
    }
    if (strpos($alvo, $raizUploads . DIRECTORY_SEPARATOR) !== 0 || !is_file($alvo)) {
        return false;
    }

    try {
        $stmt = get_db()->prepare("SELECT COUNT(*) FROM usuarios WHERE avatar = ?");
        $stmt->execute([trim($caminho)]);
        if ((int)$stmt->fetchColumn() > 0) {
            return false;
        }
    } catch (\Throwable $e) {
        error_log('[pl_apagar_avatar_arquivo] consulta de posse falhou: ' . $e->getMessage());
        return false;
    }

    return @unlink($alvo);
}

/**
 * Retorna resposta JSON e encerra execução
 */
function json_response($data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Retorna erro JSON formatado e encerra execução
 */
function json_error(string $message, int $status = 400, array $extra = []): void {
    $payload = array_merge([
        'success' => false,
        'error'   => $message
    ], $extra);
    json_response($payload, $status);
}

/**
 * Lê o corpo da requisição JSON (php://input) ou $_POST
 */
function get_request_data(): array {
    $raw = file_get_contents('php://input');
    if (!empty($raw)) {
        $json = json_decode($raw, true);
        if (is_array($json)) {
            return $json;
        }
    }
    return $_POST ?: [];
}

/**
 * Recupera o token de sessão do cabeçalho HTTP, cookie ou sessão nativa
 */
function get_session_token(): ?string {
    // 1. Variáveis de servidor diretas (Apache / Nginx / FastCGI)
    if (!empty($_SERVER['HTTP_X_SESSION_TOKEN'])) {
        return trim($_SERVER['HTTP_X_SESSION_TOKEN']);
    }
    if (!empty($_SERVER['HTTP_AUTHORIZATION']) && preg_match('/Bearer\s+(\S+)/i', $_SERVER['HTTP_AUTHORIZATION'], $m)) {
        return $m[1];
    }

    // 2. Cabeçalhos HTTP
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    if (isset($headers['Authorization']) && preg_match('/Bearer\s+(\S+)/i', $headers['Authorization'], $m)) {
        return $m[1];
    }
    if (isset($headers['authorization']) && preg_match('/Bearer\s+(\S+)/i', $headers['authorization'], $m)) {
        return $m[1];
    }
    if (!empty($headers['X-Session-Token'])) {
        return trim($headers['X-Session-Token']);
    }
    if (!empty($headers['x-session-token'])) {
        return trim($headers['x-session-token']);
    }

    // 3. Parâmetro explícito via query string ou body (fallback de alta resiliência)
    if (!empty($_GET['session_token'])) {
        return trim($_GET['session_token']);
    }

    // 4. Cookie HTTP
    if (!empty($_COOKIE['pl_session_token'])) {
        return trim($_COOKIE['pl_session_token']);
    }

    // 5. Sessão PHP
    if (!empty($_SESSION['pl_session_token'])) {
        return trim($_SESSION['pl_session_token']);
    }
    return null;
}

/**
 * Busca o usuário autenticado atual no banco MySQL
 * Valida validade da sessão em `sessoes`
 */
function get_auth_user(): ?array {
    $token = get_session_token();
    if (!$token) {
        return null;
    }

    $db = get_db();
    $stmt = $db->prepare("
        SELECT u.*, s.id AS session_id, s.expires_at, s.is_remember
        FROM sessoes s
        INNER JOIN usuarios u ON u.id = s.user_id
        WHERE s.session_token = ? AND s.expires_at > NOW() AND u.status = 'ativo'
        LIMIT 1
    ");
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if ($user) {
        unset($user['password_hash']);
        // Parse de JSON fields
        if (isset($user['pcd_categories']) && is_string($user['pcd_categories'])) {
            $user['pcd_categories'] = json_decode($user['pcd_categories'], true) ?: [];
        }
        if (isset($user['saved_prefs']) && is_string($user['saved_prefs'])) {
            $user['saved_prefs'] = json_decode($user['saved_prefs'], true) ?: [];
        }
        $user['is_admin'] = (int)$user['is_admin'];
        // Toda conta sem foto válida chega às telas com a foto padrão do projeto.
        $user['avatar'] = pl_avatar_exibicao($user['avatar'] ?? null);
        return $user;
    }

    return null;
}

/**
 * Exige autenticação. Retorna 401 se não autenticado.
 */
function require_auth(): array {
    $user = get_auth_user();
    if (!$user) {
        json_error('Acesso não autorizado. Por favor faça login.', 401);
    }
    return $user;
}

/**
 * Exige permissão de Gestor/Admin. Validação REAL no banco de dados.
 * Retorna 403 se usuário for cidadão comum.
 *
 * O único critério aceito é a flag `usuarios.is_admin` lida do banco nesta
 * requisição. O texto de `role` é rotulagem institucional e nunca concede acesso:
 * sem isso, revogar `is_admin` de uma conta cujo role diz "Administrador"
 * deixaria o acesso de pé.
 */
function require_admin(): array {
    $user = require_auth();
    $isAdmin = (int)($user['is_admin'] ?? 0) === 1;

    if (!$isAdmin) {
        // Registra tentativa indevida de privilégio em auditoria
        log_audit(
            'seguranca',
            'Tentativa de Acesso Não Autorizado ao Painel Gestor',
            "Usuário ID {$user['id']} ({$user['email']}) tentou acessar endpoint de gestor sem permissão",
            'alto',
            $user['id']
        );
        json_error('Acesso proibido. Esta funcionalidade exige privilégios de gestor.', 403);
    }
    return $user;
}

/**
 * Registra ação sensível na tabela `logs_auditoria` do MySQL
 */
function log_audit(
    string $module,
    string $title,
    string $desc = '',
    string $risk = 'baixo',
    ?int $userId = null
): void {
    try {
        $db = get_db();
        $operatorName = 'Sistema';
        $operatorRole = 'Automático';
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        if ($userId) {
            $stmt = $db->prepare("SELECT name, role FROM usuarios WHERE id = ? LIMIT 1");
            $stmt->execute([$userId]);
            if ($u = $stmt->fetch()) {
                $operatorName = $u['name'];
                $operatorRole = $u['role'];
            }
        } else {
            $current = get_auth_user();
            if ($current) {
                $userId = $current['id'];
                $operatorName = $current['name'];
                $operatorRole = $current['role'];
            }
        }

        $now = date('Y-m-d H:i:s');
        $hash = hash('sha256', "{$module}|{$title}|{$desc}|{$risk}|{$now}|" . uniqid('', true));

        $stmt = $db->prepare("
            INSERT INTO logs_auditoria
              (user_id, operator_name, operator_role, action_module, action_title, action_desc, risk_level, ip_address, location_desc, hash_sha256, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $userId,
            $operatorName,
            $operatorRole,
            $module,
            $title,
            $desc,
            $risk,
            $ip,
            // A auditoria não sabe em que cidade a ação aconteceu: o texto fixo
            // 'Suzano, SP — Brasil' fazia toda operação do estado parecer
            // executada em Suzano.
            null,
            $hash,
            $now
        ]);
    } catch (\Throwable $t) {
        error_log("[log_audit erro] " . $t->getMessage());
    }
}
