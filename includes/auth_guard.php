<?php
/**
 * Passe Livre Suzano — Guard de Autenticação para páginas PHP
 * Include este arquivo no topo de páginas protegidas.
 *
 * Uso:
 *   require_once __DIR__ . '/../includes/auth_guard.php';
 *   auth_require();             // Qualquer usuário autenticado
 *   auth_require_admin();       // Apenas gestores/admins
 *
 * A verificação é feita diretamente no banco MySQL, sem confiar em localStorage.
 */

if (!defined('AUTH_GUARD_LOADED')) {
    define('AUTH_GUARD_LOADED', true);
}

require_once __DIR__ . '/../api/_db_restore.php';

/**
 * URL de um CSS/JS do projeto com a versão do arquivo.
 *
 * O Apache entrega esses arquivos sem Cache-Control, então o navegador estima a
 * validade pela data do arquivo e, depois de publicar uma mudança, quem já abriu
 * o site continua executando o JavaScript velho do próprio cache (requisição
 * nenhuma: transferSize 0). O `?v=` com o mtime renova a chave de cache sempre
 * que o arquivo realmente muda.
 */
function pl_asset_url(string $caminho): string {
    $arquivo = dirname(__DIR__) . '/' . ltrim($caminho, '/');
    $url     = $caminho . '?v=' . (is_file($arquivo) ? (int)filemtime($arquivo) : '0');
    return htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
}

/**
 * Retorna o usuário autenticado atual ou null.
 * Verifica cookie pl_session_token → tabela sessoes → tabela usuarios.
 */
function auth_get_user(): ?array {
    return get_auth_user();
}

/**
 * Exige usuário autenticado.
 * Se não autenticado, redireciona para login.php com ?next=URL_ATUAL.
 */
function auth_require(): array {
    $user = get_auth_user();
    if (!$user) {
        $next = urlencode($_SERVER['REQUEST_URI'] ?? '');
        header('Location: login.php' . ($next ? '?next=' . $next : ''));
        exit;
    }
    return $user;
}

/**
 * Exige permissão de Gestor/Admin.
 * Se não autenticado → redireciona para login.php.
 * Se cidadão comum → redireciona para adaptar-busca.php com aviso.
 *
 * Critério único: flag `usuarios.is_admin` vinda do banco nesta requisição.
 * O texto de `role` não concede acesso (é apenas rotulagem institucional).
 */
function auth_require_admin(): array {
    $user = get_auth_user();
    if (!$user) {
        $next = urlencode($_SERVER['REQUEST_URI'] ?? '');
        header('Location: login.php' . ($next ? '?next=' . $next : ''));
        exit;
    }

    $isAdmin = (int)($user['is_admin'] ?? 0) === 1;

    if (!$isAdmin) {
        // Registra tentativa de acesso não autorizado
        log_audit(
            'seguranca',
            'Tentativa de Acesso Não Autorizado',
            "Usuário ID {$user['id']} ({$user['email']}) tentou acessar página de gestor sem permissão via PHP",
            'alto',
            $user['id']
        );
        header('Location: adaptar-busca.php?erro=acesso_negado');
        exit;
    }

    return $user;
}

/**
 * Exige o nível mais alto: administrador principal (is_admin = 1 e
 * is_primary_admin = 1, conferidos no banco a cada requisição).
 * Cidadão comum cai em auth_require_admin(); administrador comum é devolvido
 * ao dashboard com aviso — a UI nunca é a única barreira.
 */
function auth_require_primary_admin(): array {
    $user = auth_require_admin();
    require_once __DIR__ . '/admins.php';

    if (!pl_admin_is_primary(get_db(), (int)$user['id'])) {
        log_audit(
            'seguranca',
            'Acesso Restrito à Gestão de Administradores',
            "Usuário ID {$user['id']} ({$user['email']}) tentou abrir a gestão de administradores sem ser o administrador principal",
            'alto',
            (int)$user['id']
        );
        header('Location: dashboard.php?erro=acesso_restrito');
        exit;
    }

    return $user;
}

/**
 * Retorna dados seguros do usuário para uso no HTML (sem campos sensíveis).
 * Útil para preencher sidebar/header dinamicamente via PHP.
 */
function auth_user_safe(?array $user = null): array {
    if ($user === null) {
        $user = get_auth_user();
    }
    if (!$user) return [];
    return [
        'id'                => (int)($user['id'] ?? 0),
        'name'              => htmlspecialchars($user['name'] ?? '', ENT_QUOTES, 'UTF-8'),
        'email'             => htmlspecialchars($user['email'] ?? '', ENT_QUOTES, 'UTF-8'),
        'role'              => htmlspecialchars($user['role'] ?? 'Cidadão', ENT_QUOTES, 'UTF-8'),
        'avatar'            => htmlspecialchars(pl_avatar_exibicao($user['avatar'] ?? null), ENT_QUOTES, 'UTF-8'),
        'initials'          => htmlspecialchars($user['initials'] ?? 'U', ENT_QUOTES, 'UTF-8'),
        'is_admin'          => (int)($user['is_admin'] ?? 0),
        'is_primary_admin'  => (int)($user['is_primary_admin'] ?? 0),
    ];
}

/**
 * Rótulo fixo exibido abaixo do nome em todo cabeçalho do painel administrativo.
 *
 * É apenas apresentação: o cargo real continua em `usuarios.role` (lido pelas
 * listas de usuários/histórico) e nunca concede acesso — quem autoriza é
 * `is_admin`. Centralizado aqui para as páginas do painel não voltarem a
 * divergir entre "Administrador", "Administrador Municipal" e complementos de
 * instituição.
 */
function pl_admin_header_role_label(): string {
    return 'Administrador';
}

/**
 * Bloco nome + cargo do administrador logado para o cabeçalho do painel.
 * Recebe a saída de auth_user_safe(), cujos campos já vêm escapados — por isso
 * não reescapa aqui (escaparia duas vezes e mostraria "&amp;" no nome).
 */
function pl_admin_header_profile(array $gestorUser): string {
    $nome  = $gestorUser['name'] ?? '';
    $cargo = pl_admin_header_role_label();
    return "<span class=\"gestor-admin-name-span\">{$nome}</span>\n"
         . "              <span class=\"gestor-admin-role-span\">{$cargo}</span>";
}

/**
 * Redireciona usuário já autenticado para fora de páginas de login/cadastro.
 */
function auth_redirect_if_logged(): void {
    $user = get_auth_user();
    if (!$user) return;

    $isAdmin = (int)($user['is_admin'] ?? 0) === 1;

    header('Location: ' . ($isAdmin ? 'dashboard.php' : 'adaptar-busca.php'));
    exit;
}
