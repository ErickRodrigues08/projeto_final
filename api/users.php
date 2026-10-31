<?php
/**
 * Passe Livre Suzano — Endpoint de Gestão de Usuários (Gestor)
 * Listagem, detalhe, atualização de status, bloqueio/desbloqueio e perfil do cidadão.
 *
 * Ações disponíveis:
 *   GET  ?action=list          — Lista usuários com filtros
 *   GET  ?action=detail&id=N   — Detalhe de um usuário específico
 *   POST ?action=update_status — Atualiza status (ativo/bloqueado)
 *   POST ?action=update_role   — Atualiza role/permissão
 *   POST ?action=update_profile— Informações pessoais; foto usa upload/removal
 *   POST ?action=remove_avatar — Volta a conta autenticada para a foto padrão
 *   GET  ?action=stats         — Contadores e KPIs de usuários
 */

require_once __DIR__ . '/_db_restore.php';
require_once __DIR__ . '/../includes/acessibilidade.php';

$action = $_GET['action'] ?? 'list';
$db     = get_db();
$data   = get_request_data();

switch ($action) {

    // =========================================================================
    // 1. LISTAGEM DE USUÁRIOS (Gestor) — suporta 'list' e 'list_citizens'
    // =========================================================================
    case 'list':
    case 'list_citizens':
        require_admin();

        $q       = trim($_GET['q']      ?? '');
        $status  = trim($_GET['status'] ?? '');
        $isAdmin = isset($_GET['is_admin']) ? (int)$_GET['is_admin'] : -1;
        $page    = max(1, (int)($_GET['page']  ?? 1));
        $limit   = max(1, min(100, (int)($_GET['limit'] ?? 20)));
        $offset  = ($page - 1) * $limit;

        $where  = ['1=1'];
        $params = [];

        if (!empty($q)) {
            $where[]  = '(name LIKE ? OR email LIKE ? OR user_code LIKE ?)';
            $needle   = "%{$q}%";
            $params[] = $needle;
            $params[] = $needle;
            $params[] = $needle;
        }
        if (!empty($status)) {
            $where[]  = 'status = ?';
            $params[] = $status;
        }
        if ($isAdmin >= 0) {
            $where[]  = 'is_admin = ?';
            $params[] = $isAdmin;
        }

        $whereClause = implode(' AND ', $where);

        $stmtCount = $db->prepare("SELECT COUNT(*) FROM usuarios WHERE {$whereClause}");
        $stmtCount->execute($params);
        $total = (int)$stmtCount->fetchColumn();

        $stmt = $db->prepare("
            SELECT id, user_code, name, email, phone, city, bairro, role, is_admin,
                   avatar, initials, status, email_verified, pcd_categories,
                   failed_login_attempts, created_at, updated_at
            FROM usuarios
            WHERE {$whereClause}
            ORDER BY created_at DESC
            LIMIT {$limit} OFFSET {$offset}
        ");
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        foreach ($rows as &$r) {
            if (!empty($r['pcd_categories']) && is_string($r['pcd_categories'])) {
                $r['pcd_categories'] = json_decode($r['pcd_categories'], true) ?: [];
            }
            $r['is_admin']       = (int)$r['is_admin'];
            $r['email_verified'] = (int)$r['email_verified'];
            unset($r['password_hash']);
        }

        json_response([
            'success'     => true,
            'total'       => $total,
            'page'        => $page,
            'limit'       => $limit,
            'total_pages' => ceil($total / $limit),
            'rows'        => $rows,
            'cidadaos'    => $rows // Alias de compatibilidade
        ]);
        break;

    // =========================================================================
    // 2. DETALHE DE UM USUÁRIO — suporta 'detail' e 'citizen_detail'
    // =========================================================================
    case 'detail':
    case 'citizen_detail':
        require_admin();

        $id   = (int)($_GET['id']   ?? 0);
        $code = trim($_GET['code']  ?? '');

        if ($id > 0) {
            $stmt = $db->prepare("SELECT * FROM usuarios WHERE id = ? LIMIT 1");
            $stmt->execute([$id]);
        } elseif (!empty($code)) {
            $stmt = $db->prepare("SELECT * FROM usuarios WHERE user_code = ? LIMIT 1");
            $stmt->execute([$code]);
        } else {
            json_error('ID ou código do usuário não fornecido.');
        }

        $user = $stmt->fetch();
        if (!$user) {
            json_error('Usuário não encontrado.', 404);
        }

        unset($user['password_hash']);
        if (!empty($user['pcd_categories']) && is_string($user['pcd_categories'])) {
            $user['pcd_categories'] = json_decode($user['pcd_categories'], true) ?: [];
        }
        if (!empty($user['saved_prefs']) && is_string($user['saved_prefs'])) {
            $user['saved_prefs'] = json_decode($user['saved_prefs'], true) ?: [];
        }
        $user['is_admin'] = (int)$user['is_admin'];

        // Conta solicitações do usuário
        $stmtSol = $db->prepare("SELECT status, COUNT(*) AS total FROM solicitacoes WHERE citizen_id = ? GROUP BY status");
        $stmtSol->execute([$user['id']]);
        $solStats = ['total' => 0, 'pending' => 0, 'analyzing' => 0, 'approved' => 0, 'rejected' => 0];
        while ($r = $stmtSol->fetch()) {
            $solStats[$r['status']] = (int)$r['total'];
            $solStats['total'] += (int)$r['total'];
        }

        // Lista de solicitações do usuário (para preencher perfil-cidadao-detalhe)
        $stmtSolsList = $db->prepare("SELECT * FROM solicitacoes WHERE citizen_id = ? ORDER BY created_at DESC LIMIT 20");
        $stmtSolsList->execute([$user['id']]);
        $solicitacoes = $stmtSolsList->fetchAll();

        // Conta avaliações do usuário
        $stmtAval = $db->prepare("SELECT COUNT(*) FROM avaliacoes WHERE user_id = ?");
        $stmtAval->execute([$user['id']]);
        $avalCount = (int)$stmtAval->fetchColumn();

        // Necessidades PcD cadastradas
        $stmtNec = $db->prepare("SELECT categoria_pcd, descricao FROM usuario_necessidades WHERE user_id = ?");
        $stmtNec->execute([$user['id']]);
        $necessidades = $stmtNec->fetchAll();

        json_response([
            'success'      => true,
            'user'         => $user,
            'cidadao'      => $user, // Alias para perfil-cidadao-detalhe.js
            'sol_stats'    => $solStats,
            'solicitacoes' => $solicitacoes,
            'aval_count'   => $avalCount,
            'necessidades' => $necessidades
        ]);
        break;

    // =========================================================================
    // 3. ATUALIZAR STATUS DO USUÁRIO — suporta 'update_status' e 'toggle_status'
    // =========================================================================
    case 'update_status':
    case 'toggle_status':
        $admin  = require_admin();
        $id     = (int)($data['id']     ?? 0);
        $status = trim($data['status']  ?? '');

        if ($id <= 0 || !in_array($status, ['ativo', 'bloqueado', 'pendente'])) {
            json_error('ID e status (ativo|bloqueado|pendente) são obrigatórios.');
        }
        // Impede que o gestor bloqueie a si próprio
        if ($id === (int)$admin['id']) {
            json_error('Você não pode alterar o status da sua própria conta.');
        }

        $stmt = $db->prepare("SELECT name, email FROM usuarios WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $target = $stmt->fetch();
        if (!$target) {
            json_error('Usuário não encontrado.');
        }

        $db->prepare("UPDATE usuarios SET status = ?, updated_at = NOW() WHERE id = ?")->execute([$status, $id]);

        log_audit(
            'usuarios',
            "Status de Usuário Alterado: {$status}",
            "Usuário \"{$target['name']}\" ({$target['email']}) teve status alterado para \"{$status}\" por {$admin['name']}",
            $status === 'bloqueado' ? 'alto' : 'medio',
            $admin['id']
        );

        json_response([
            'success' => true,
            'message' => "Status do usuário atualizado para \"{$status}\" com sucesso!"
        ]);
        break;

    // =========================================================================
    // 3.1. NOTIFICAR CIDADÃO (Integração com notificacoes.php)
    // =========================================================================
    case 'notify_citizen':
        $admin = require_admin();

        $destId = (int)($data['user_id'] ?? 0);
        $titulo = trim($data['title'] ?? '');
        $corpo  = trim($data['message'] ?? ($data['body'] ?? ''));
        $tipo   = trim($data['type'] ?? 'gestor');

        if ($destId <= 0) {
            json_error('Destinatário (user_id) é obrigatório.');
        }
        if (empty($titulo) || empty($corpo)) {
            json_error('Título e mensagem da notificação são obrigatórios.');
        }

        $stmtDest = $db->prepare("SELECT id, name FROM usuarios WHERE id = ? LIMIT 1");
        $stmtDest->execute([$destId]);
        $destUser = $stmtDest->fetch();
        if (!$destUser) {
            json_error('Cidadão destinatário não encontrado no sistema.', 404);
        }

        $link = trim($data['link'] ?? '');

        $stmtIns = $db->prepare("
            INSERT INTO notificacoes (user_id, sender_id, title, body, type, link, is_read, created_at)
            VALUES (?, ?, ?, ?, ?, ?, 0, NOW())
        ");
        $stmtIns->execute([$destId, $admin['id'], $titulo, $corpo, $tipo, $link ?: null]);
        $notifId = (int)$db->lastInsertId();

        log_audit(
            'notificacoes',
            'Notificação Enviada pelo Gestor',
            "Gestor \"{$admin['name']}\" enviou notificação #{$notifId} para cidadão \"{$destUser['name']}\"",
            'baixo',
            $admin['id']
        );

        json_response([
            'success'         => true,
            'notificacao_id'  => $notifId,
            'notification_id' => $notifId,
            'id'              => $notifId,
            'message'         => "Notificação enviada com sucesso para {$destUser['name']}!"
        ]);
        break;

    // =========================================================================
    // 4. ATUALIZAR PERFIL DO USUÁRIO (nome, telefone, bairro) — usado em editar-perfil
    // =========================================================================
    case 'update_profile':
        $authUser = require_auth();

        $targetId = (int)($data['id'] ?? $authUser['id']);

        // Cidadão só pode editar seu próprio perfil; gestor pode editar qualquer um
        $isAdmin = (int)($authUser['is_admin'] ?? 0) === 1;
        if (!$isAdmin && $targetId !== (int)$authUser['id']) {
            json_error('Acesso negado. Você só pode editar seu próprio perfil.', 403);
        }

        $fields = [];
        $params = [];

        // Campos sempre gravados quando enviados: assim também é possível limpar
        // telefone, bairro e a descrição (antes, valores vazios eram ignorados).
        if (array_key_exists('name', $data)) {
            $nome = trim((string)$data['name']);
            if ($nome === '') {
                json_error('O nome completo é obrigatório.');
            }
            if (mb_strlen($nome) > 120) {
                json_error('O nome completo deve ter no máximo 120 caracteres.');
            }
            $fields[] = 'name = ?';
            $params[] = $nome;
        }

        $optionalText = [
            'phone'       => 'phone',
            'city'        => 'city',
            'bairro'      => 'bairro',
            'description' => 'description',
            'gender'      => 'gender',
        ];
        foreach ($optionalText as $key => $column) {
            if (!array_key_exists($key, $data) || ($data[$key] !== null && !is_scalar($data[$key]))) {
                continue;
            }
            $value = trim((string)($data[$key] ?? ''));
            if ($key === 'description') {
                $value = mb_substr($value, 0, 2000);
            }
            $fields[] = "{$column} = ?";
            $params[] = $value === '' ? null : $value;
        }

        $descricao = array_key_exists('description', $data)
            && ($data['description'] === null || is_scalar($data['description']))
            ? trim((string)($data['description'] ?? ''))
            : '';

        if (array_key_exists('avatar', $data)) {
            json_error('Para alterar a foto, confirme o enquadramento ou utilize a remoção de foto.', 400, ['code' => 'avatar_invalido']);
        }

        // Categorias PcD: aceita chaves do perfil e rótulos do cadastro.
        // Grava no formato do cadastro para manter compatibilidade com as demais telas.
        $pcdKeys = null;
        if (array_key_exists('pcd_categories', $data)) {
            if (!is_array($data['pcd_categories'])) {
                json_error('Lista de categorias de acessibilidade inválida.');
            }
            $pcdKeys = pl_pcd_keys($data['pcd_categories']);
            $fields[] = 'pcd_categories = ?';
            $params[] = json_encode(pl_pcd_labels($pcdKeys), JSON_UNESCAPED_UNICODE);
        }

        // Preferências urbanas do formulário (antes eram salvas apenas pelo fallback JS).
        $prefKeys = null;
        if (array_key_exists('saved_prefs', $data)) {
            if (!is_array($data['saved_prefs'])) {
                json_error('Lista de preferências de acessibilidade inválida.');
            }
            $prefKeys = pl_pref_keys($data['saved_prefs']);
            $fields[] = 'saved_prefs = ?';
            $params[] = json_encode($prefKeys, JSON_UNESCAPED_UNICODE);
        }

        if (empty($fields)) {
            json_error('Nenhum campo válido para atualização.');
        }

        try {
            $db->beginTransaction();

            $fields[] = 'updated_at = NOW()';
            $params[] = $targetId;
            $db->prepare("UPDATE usuarios SET " . implode(', ', $fields) . " WHERE id = ?")->execute($params);

            // Mantém `usuario_necessidades` sincronizada com o perfil. A tabela é
            // usada pelo detalhe do cidadão, relatórios e fallback da busca.
            if ($pcdKeys !== null) {
                $stmtOld = $db->prepare("SELECT categoria_pcd, descricao FROM usuario_necessidades WHERE user_id = ?");
                $stmtOld->execute([$targetId]);
                $descs = [];
                foreach ($stmtOld->fetchAll() as $row) {
                    $key = pl_pcd_key((string)($row['categoria_pcd'] ?? ''));
                    if ($key !== null) {
                        $descs[$key] = trim((string)($row['descricao'] ?? ''));
                    }
                }

                $db->prepare("DELETE FROM usuario_necessidades WHERE user_id = ?")->execute([$targetId]);

                if (!empty($pcdKeys)) {
                    $stmtIns = $db->prepare("
                        INSERT INTO usuario_necessidades (user_id, categoria_pcd, descricao)
                        VALUES (?, ?, ?)
                    ");
                    foreach ($pcdKeys as $key) {
                        $needDesc = $descs[$key] ?? '';
                        if ($needDesc === '') {
                            $needDesc = $descricao !== '' ? $descricao : 'Categoria informada no perfil';
                        }
                        $stmtIns->execute([$targetId, pl_pcd_label($key), $needDesc]);
                    }
                }
            }

            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            json_error('Não foi possível salvar as informações do perfil. Tente novamente.', 500);
        }

        log_audit('usuarios', 'Perfil Atualizado', "Perfil do usuário ID {$targetId} atualizado", 'baixo', $authUser['id']);

        // Retorna dados atualizados direto do banco
        $stmtUp = $db->prepare("
            SELECT id, user_code, name, email, phone, city, bairro, description,
                   role, is_admin, avatar, initials, pcd_categories, saved_prefs,
                   status, email_verified
            FROM usuarios
            WHERE id = ?
            LIMIT 1
        ");
        $stmtUp->execute([$targetId]);
        $updated = $stmtUp->fetch();
        if ($updated) {
            unset($updated['password_hash']);
            foreach (['pcd_categories', 'saved_prefs'] as $jsonField) {
                if (isset($updated[$jsonField]) && is_string($updated[$jsonField])) {
                    $updated[$jsonField] = json_decode($updated[$jsonField], true) ?: [];
                }
            }
        }

        json_response([
            'success' => true,
            'message' => 'Perfil atualizado com sucesso!',
            'user'    => $updated
        ]);
        break;

    // =========================================================================
    // 4.1. REMOVER FOTO DE PERFIL — só a conta autenticada
    // =========================================================================
    case 'remove_avatar':
        $authUser = require_auth();
        $userId   = (int)$authUser['id'];

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Allow: POST');
            json_error('Método não permitido.', 405);
        }
        try {
            $db->beginTransaction();
            $stmtAtual = $db->prepare('SELECT id FROM usuarios WHERE id = ? FOR UPDATE');
            $stmtAtual->execute([$userId]);
            if (!$stmtAtual->fetch()) throw new RuntimeException('Conta indisponível.');
            $db->prepare('DELETE FROM usuarios_avatares WHERE user_id = ?')->execute([$userId]);
            $db->prepare('UPDATE usuarios SET avatar = ?, updated_at = NOW() WHERE id = ?')
               ->execute([PL_AVATAR_PADRAO, $userId]);
            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            handle_db_error($e, 'Não foi possível remover sua foto de perfil. Tente novamente.');
        }

        log_audit('usuarios', 'Foto de Perfil Removida', "Usuário ID {$userId} removeu a própria foto de perfil", 'baixo', $userId);

        json_response([
            'success' => true,
            'message' => 'Foto de perfil removida.',
            'avatar'  => pl_avatar_exibicao(PL_AVATAR_PADRAO)
        ]);
        break;

    // =========================================================================
    // 5. ESTATÍSTICAS DE USUÁRIOS (KPIs para o gestor)
    // =========================================================================
    case 'stats':
        require_admin();

        $stmtStats = $db->query("
            SELECT
              COUNT(*)                                                AS total,
              SUM(CASE WHEN status = 'ativo' THEN 1 ELSE 0 END)      AS ativos,
              SUM(CASE WHEN status = 'bloqueado' THEN 1 ELSE 0 END)  AS bloqueados,
              SUM(CASE WHEN is_admin = 1 THEN 1 ELSE 0 END)          AS gestores,
              SUM(CASE WHEN is_google = 1 THEN 1 ELSE 0 END)         AS google_users,
              SUM(CASE WHEN email_verified = 1 THEN 1 ELSE 0 END)    AS verificados
            FROM usuarios
        ");
        $stats = $stmtStats->fetch();

        // Últimos 30 dias
        $stmtNew = $db->query("SELECT COUNT(*) FROM usuarios WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
        $stats['novos_30d'] = (int)$stmtNew->fetchColumn();

        // Distribuição PcD
        $stmtPcd = $db->query("
            SELECT categoria_pcd, COUNT(*) AS total
            FROM usuario_necessidades
            GROUP BY categoria_pcd
            ORDER BY total DESC
        ");
        $stats['distribuicao_pcd'] = $stmtPcd->fetchAll();

        json_response([
            'success' => true,
            'stats'   => array_map('intval', array_filter($stats, 'is_numeric') + $stats)
        ]);
        break;

    default:
        json_error('Ação de usuários não suportada.', 404);
}
