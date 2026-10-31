<?php
/**
 * Passe Livre Suzano — Endpoint de Gestão de Administradores
 *
 * Quem pode usar: somente o ADMINISTRADOR PRINCIPAL, identificado no banco a
 * cada requisição por `usuarios.is_admin = 1 AND usuarios.is_primary_admin = 1`
 * e conta `status = 'ativo'`. Nenhum critério usa nome, e-mail, texto de `role`,
 * parâmetro da URL ou variável do navegador.
 *
 * Ações:
 *   GET  ?action=list    — lista e busca usuários (nome/e-mail/código) com nível de acesso
 *   POST ?action=grant   — concede acesso de administrador (CSRF obrigatório)
 *   POST ?action=revoke  — remove acesso de administrador (CSRF obrigatório)
 *
 * Efeitos colaterais intencionais em grant/revoke:
 *   - A conta do alvo é preservada (mesmo id, e-mail, senha e perfil).
 *   - As sessões do alvo são encerradas, para que a permissão só valha depois
 *     de uma nova autenticação e nenhum valor antigo de sessão permaneça válido.
 *   - Toda concessão, remoção e recusa é registrada em `logs_auditoria`.
 */

require_once __DIR__ . '/_db_restore.php';
require_once __DIR__ . '/../includes/admins.php';
require_once __DIR__ . '/../includes/csrf.php';

$action = $_GET['action'] ?? 'list';
$db     = get_db();
$data   = get_request_data();

/**
 * Barreira de autorização deste endpoint (administrador principal).
 */
function pl_admins_actor(PDO $db): array {
    $acting = require_admin();

    if (!pl_admin_is_primary($db, (int)$acting['id'])) {
        log_audit(
            'seguranca',
            'Acesso Restrito à Gestão de Administradores',
            "Usuário ID {$acting['id']} ({$acting['email']}) acionou o endpoint de gestão de administradores sem ser o administrador principal",
            'alto',
            (int)$acting['id']
        );
        json_error(
            'Acesso restrito: apenas o administrador principal pode gerenciar acessos administrativos.',
            403,
            ['code' => 'admin_principal_exigido']
        );
    }

    return $acting;
}

/**
 * Formata uma linha de `usuarios` para a tela, com o nível de acesso e as
 * ações realmente permitidas (a UI usa, o servidor revalida sempre).
 */
function pl_admins_shape(array $row, int $actingId): array {
    $isAdmin   = (int)($row['is_admin'] ?? 0) === 1;
    $isPrimary = $isAdmin && (int)($row['is_primary_admin'] ?? 0) === 1;
    $isSelf    = (int)$row['id'] === $actingId;
    $isAtivo   = ($row['status'] ?? '') === 'ativo';
    $level     = pl_admin_level($row);

    return [
        'id'           => (int)$row['id'],
        'user_code'    => (string)($row['user_code'] ?? ''),
        'name'         => (string)($row['name'] ?? ''),
        'email'        => (string)($row['email'] ?? ''),
        'role'         => (string)($row['role'] ?? ''),
        'initials'     => (string)($row['initials'] ?? '') !== ''
                            ? (string)$row['initials']
                            : mb_strtoupper(mb_substr((string)($row['name'] ?? 'U'), 0, 1)),
        'status'       => (string)($row['status'] ?? ''),
        'level'        => $level,
        'level_label'  => pl_admin_level_label($level),
        'is_self'      => $isSelf,
        'is_primary'   => $isPrimary,
        'can_grant'    => !$isAdmin && !$isSelf && $isAtivo,
        'can_revoke'   => $isAdmin && !$isPrimary && !$isSelf,
        'created_at'   => $row['created_at']   ?? null,
        'updated_at'   => $row['updated_at']   ?? null,
        'access_since' => $isAdmin ? ($row['updated_at'] ?? null) : null,
    ];
}

/**
 * Carrega e valida o alvo de uma concessão/remoção. Encerra com JSON de erro.
 */
function pl_admins_target(PDO $db, int $targetId, array $acting, string $intent): array {
    if ($targetId <= 0) {
        json_error('Selecione um usuário válido para esta operação.');
    }
    if ($targetId === (int)$acting['id']) {
        log_audit(
            'administradores',
            'Autoalteração de Acesso Recusada',
            "O administrador principal \"{$acting['name']}\" (ID {$acting['id']}) tentou {$intent} o próprio nível de acesso. Operação recusada.",
            'alto',
            (int)$acting['id']
        );
        json_error('Você não pode alterar o seu próprio nível de acesso.', 403, ['code' => 'auto_alteracao']);
    }

    $stmt = $db->prepare("
        SELECT id, user_code, name, email, role, status, is_admin, is_primary_admin, initials
        FROM usuarios
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->execute([$targetId]);
    $target = $stmt->fetch();

    if (!$target) {
        log_audit(
            'administradores',
            'Alvo Inexistente em Operação de Acesso',
            "\"{$acting['name']}\" (ID {$acting['id']}) tentou {$intent} o acesso do usuário ID {$targetId}, que não existe. Operação recusada.",
            'alto',
            (int)$acting['id']
        );
        json_error('Usuário não encontrado.', 404);
    }

    if ((int)$target['is_primary_admin'] === 1) {
        log_audit(
            'administradores',
            'Alteração de Administrador Principal Bloqueada',
            "\"{$acting['name']}\" (ID {$acting['id']}) tentou {$intent} o acesso do administrador principal \"{$target['name']}\" (ID {$targetId}). Operação recusada.",
            'critico',
            (int)$acting['id']
        );
        json_error(
            'O administrador principal é uma conta protegida e não pode ter o nível de acesso alterado.',
            403,
            ['code' => 'principal_protegido']
        );
    }

    return $target;
}

switch ($action) {

    // =========================================================================
    // 1. LISTAGEM E BUSCA DE USUÁRIOS COM NÍVEL DE ACESSO
    // =========================================================================
    case 'list':
        $acting = pl_admins_actor($db);

        $q     = mb_substr(trim((string)($_GET['q'] ?? '')), 0, 120);
        $level = strtolower(trim((string)($_GET['level'] ?? 'all')));
        if (!in_array($level, ['all', 'principal', 'admin', 'cidadao'], true)) {
            $level = 'all';
        }
        $page   = max(1, (int)($_GET['page'] ?? 1));
        $limit  = max(1, min(100, (int)($_GET['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;

        $where  = ['1=1'];
        $params = [];

        if ($q !== '') {
            $where[]  = '(name LIKE ? OR email LIKE ? OR user_code LIKE ?)';
            $needle   = '%' . $q . '%';
            $params[] = $needle;
            $params[] = $needle;
            $params[] = $needle;
        }
        if ($level === 'principal') {
            $where[] = 'is_admin = 1 AND is_primary_admin = 1';
        } elseif ($level === 'admin') {
            $where[] = 'is_admin = 1';
        } elseif ($level === 'cidadao') {
            $where[] = 'is_admin = 0';
        }

        $whereClause = implode(' AND ', $where);

        $stmtCount = $db->prepare("SELECT COUNT(*) FROM usuarios WHERE {$whereClause}");
        $stmtCount->execute($params);
        $total = (int)$stmtCount->fetchColumn();

        // $limit/$offset são inteiros já normalizados acima; nada de entrada do usuário aqui
        $stmt = $db->prepare("
            SELECT id, user_code, name, email, role, status, is_admin, is_primary_admin,
                   initials, created_at, updated_at
            FROM usuarios
            WHERE {$whereClause}
            ORDER BY is_primary_admin DESC, is_admin DESC, name ASC
            LIMIT {$limit} OFFSET {$offset}
        ");
        $stmt->execute($params);

        $rows = array_map(
            static fn(array $r): array => pl_admins_shape($r, (int)$acting['id']),
            $stmt->fetchAll()
        );

        $stmtTotals = $db->query("
            SELECT
              COUNT(*)                                        AS total,
              SUM(is_admin = 1)                               AS administradores,
              SUM(is_admin = 1 AND is_primary_admin = 1)      AS principais,
              SUM(is_admin = 0)                               AS cidadaos,
              SUM(status <> 'ativo')                          AS inativos
            FROM usuarios
        ")->fetch();

        json_response([
            'success'          => true,
            'total'            => $total,
            'page'             => $page,
            'limit'            => $limit,
            'total_pages'      => max(1, (int)ceil($total / $limit)),
            'rows'             => $rows,
            'totais'           => [
                'usuarios'        => (int)($stmtTotals['total'] ?? 0),
                'administradores' => (int)($stmtTotals['administradores'] ?? 0),
                'principais'      => (int)($stmtTotals['principais'] ?? 0),
                'cidadaos'        => (int)($stmtTotals['cidadaos'] ?? 0),
                'inativos'        => (int)($stmtTotals['inativos'] ?? 0),
            ],
            'busca'            => $q,
            'nivel_filtrado'   => $level,
            'current_user_id'  => (int)$acting['id'],
        ]);
        break;

    // =========================================================================
    // 2. CONCEDER ACESSO DE ADMINISTRADOR
    // =========================================================================
    case 'grant':
        $acting = pl_admins_actor($db);
        pl_csrf_require($data);

        $target   = pl_admins_target($db, (int)($data['id'] ?? 0), $acting, 'conceder');
        $targetId = (int)$target['id'];

        if ((int)$target['is_admin'] === 1) {
            json_error('Este usuário já possui acesso de administrador.', 409, ['code' => 'ja_administrador']);
        }
        if (($target['status'] ?? '') !== 'ativo') {
            log_audit(
                'administradores',
                'Concessão de Acesso Recusada (Conta Inativa)',
                "\"{$acting['name']}\" tentou conceder acesso de administrador a \"{$target['name']}\" (ID {$targetId}) com status \"{$target['status']}\". Operação recusada.",
                'alto',
                (int)$acting['id']
            );
            json_error('Só é possível conceder acesso a uma conta ativa.', 409, ['code' => 'conta_inativa']);
        }

        $sessionsRevoked = 0;
        try {
            $db->beginTransaction();

            $stmtUp = $db->prepare("UPDATE usuarios SET is_admin = 1, updated_at = NOW() WHERE id = ? AND is_admin = 0");
            $stmtUp->execute([$targetId]);
            if ($stmtUp->rowCount() !== 1) {
                throw new RuntimeException('A permissão não foi alterada.');
            }

            // Nenhuma sessão antiga continua com privilégios: o alvo autentica de novo.
            $stmtSess = $db->prepare("DELETE FROM sessoes WHERE user_id = ?");
            $stmtSess->execute([$targetId]);
            $sessionsRevoked = $stmtSess->rowCount();

            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log('[admins.grant] ' . $e->getMessage());
            log_audit(
                'administradores',
                'Falha ao Conceder Acesso de Administrador',
                "\"{$acting['name']}\" (ID {$acting['id']}) tentou conceder acesso de administrador a \"{$target['name']}\" (ID {$targetId}) e a gravação no banco falhou.",
                'alto',
                (int)$acting['id']
            );
            json_error('Não foi possível conceder o acesso agora. Tente novamente.', 500);
        }

        log_audit(
            'administradores',
            'Acesso de Administrador Concedido',
            "Resultado: SUCESSO. Administrador principal \"{$acting['name']}\" (ID {$acting['id']}) concedeu acesso de administrador a \"{$target['name']}\" ({$target['email']}, ID {$targetId}). {$sessionsRevoked} sessão(ões) do alvo encerrada(s); acesso vale após novo login.",
            'alto',
            (int)$acting['id']
        );

        $stmtAfter = $db->prepare("
            SELECT id, user_code, name, email, role, status, is_admin, is_primary_admin,
                   initials, created_at, updated_at
            FROM usuarios WHERE id = ? LIMIT 1
        ");
        $stmtAfter->execute([$targetId]);

        json_response([
            'success'          => true,
            'message'          => "Acesso de administrador concedido a {$target['name']}. A nova permissão passa a valer no próximo login da conta.",
            'sessions_revoked' => $sessionsRevoked,
            'user'             => pl_admins_shape($stmtAfter->fetch() ?: $target, (int)$acting['id']),
        ]);
        break;

    // =========================================================================
    // 3. REMOVER ACESSO DE ADMINISTRADOR
    // =========================================================================
    case 'revoke':
        $acting = pl_admins_actor($db);
        pl_csrf_require($data);

        $target   = pl_admins_target($db, (int)($data['id'] ?? 0), $acting, 'remover');
        $targetId = (int)$target['id'];

        if ((int)$target['is_admin'] !== 1) {
            json_error('Este usuário não possui acesso de administrador.', 409, ['code' => 'nao_administrador']);
        }

        $sessionsRevoked = 0;
        try {
            $db->beginTransaction();

            $stmtUp = $db->prepare("UPDATE usuarios SET is_admin = 0, updated_at = NOW() WHERE id = ? AND is_admin = 1 AND is_primary_admin = 0");
            $stmtUp->execute([$targetId]);
            if ($stmtUp->rowCount() !== 1) {
                throw new RuntimeException('A permissão não foi alterada.');
            }

            // Revogação é imediata: derruba as sessões do alvo.
            $stmtSess = $db->prepare("DELETE FROM sessoes WHERE user_id = ?");
            $stmtSess->execute([$targetId]);
            $sessionsRevoked = $stmtSess->rowCount();

            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log('[admins.revoke] ' . $e->getMessage());
            log_audit(
                'administradores',
                'Falha ao Remover Acesso de Administrador',
                "\"{$acting['name']}\" (ID {$acting['id']}) tentou remover o acesso de administrador de \"{$target['name']}\" (ID {$targetId}) e a gravação no banco falhou.",
                'alto',
                (int)$acting['id']
            );
            json_error('Não foi possível remover o acesso agora. Tente novamente.', 500);
        }

        log_audit(
            'administradores',
            'Acesso de Administrador Removido',
            "Resultado: SUCESSO. Administrador principal \"{$acting['name']}\" (ID {$acting['id']}) removeu o acesso de administrador de \"{$target['name']}\" ({$target['email']}, ID {$targetId}), que voltou a ser usuário comum. {$sessionsRevoked} sessão(ões) encerrada(s) imediatamente.",
            'critico',
            (int)$acting['id']
        );

        $stmtAfter = $db->prepare("
            SELECT id, user_code, name, email, role, status, is_admin, is_primary_admin,
                   initials, created_at, updated_at
            FROM usuarios WHERE id = ? LIMIT 1
        ");
        $stmtAfter->execute([$targetId]);

        json_response([
            'success'          => true,
            'message'          => "Acesso de administrador removido de {$target['name']}. As sessões da conta foram encerradas.",
            'sessions_revoked' => $sessionsRevoked,
            'user'             => pl_admins_shape($stmtAfter->fetch() ?: $target, (int)$acting['id']),
        ]);
        break;

    default:
        json_error('Ação de gerenciamento de administradores não suportada.', 404);
}
