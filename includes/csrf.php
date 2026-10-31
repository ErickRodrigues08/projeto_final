<?php
/**
 * Passe Livre Suzano — Token de proteção CSRF
 *
 * O token vive na sessão PHP do navegador autenticado e é devolvido pela página
 * em <meta name="csrf-token">. Toda ação de escrita do painel exige o header
 * X-CSRF-Token (ou o campo csrf_token no corpo) igual ao valor da sessão.
 *
 * Uso:
 *   require_once __DIR__ . '/csrf.php';
 *   $token = pl_csrf_token();                       // na página (renderiza a meta)
 *   pl_csrf_require(get_request_data());            // na API (encerra com 403 se inválido)
 *
 * A sessão PHP é iniciada por api/_db_restore.php; esta include não deve ser usada antes dele.
 */

if (!defined('PL_CSRF_LOADED')) {
    define('PL_CSRF_LOADED', true);
}

/**
 * Retorna o token CSRF da sessão, criando um quando ainda não existe.
 */
function pl_csrf_token(): string {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return '';
    }
    if (empty($_SESSION['pl_csrf_token']) || !is_string($_SESSION['pl_csrf_token'])) {
        $_SESSION['pl_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['pl_csrf_token'];
}

/**
 * Confere o token recebido com o da sessão em tempo constante.
 */
function pl_csrf_is_valid(?string $token): bool {
    if (session_status() !== PHP_SESSION_ACTIVE || $token === null || $token === '') {
        return false;
    }
    $expected = $_SESSION['pl_csrf_token'] ?? '';
    return is_string($expected) && $expected !== '' && hash_equals($expected, $token);
}

/**
 * Lê o token enviado pelo cliente: header primeiro, depois o corpo da requisição.
 */
function pl_csrf_received(array $requestData): ?string {
    $header = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (is_string($header) && $header !== '') {
        return $header;
    }
    $body = $requestData['csrf_token'] ?? '';
    return is_string($body) && $body !== '' ? $body : null;
}

/**
 * Exige método POST + token CSRF válido. Encerra a requisição com JSON 403/405.
 * Nunca registra o valor do token em log ou resposta.
 */
function pl_csrf_require(array $requestData): void {
    $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if ($method !== 'POST') {
        json_error('Este comando aceita apenas requisições POST.', 405);
    }
    if (!pl_csrf_is_valid(pl_csrf_received($requestData))) {
        log_audit(
            'seguranca',
            'Requisição Administrativa Rejeitada (CSRF)',
            "Ação administrativa no módulo de administradores rejeitada: token CSRF ausente ou inválido (método {$method})",
            'alto'
        );
        json_error('Sessão de segurança inválida ou expirada. Recarregue a página e tente novamente.', 403, [
            'code' => 'csrf_invalido'
        ]);
    }
}
