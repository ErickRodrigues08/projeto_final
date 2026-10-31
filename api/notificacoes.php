<?php
/**
 * Passe Livre Suzano — Endpoint de Notificações e Comunicados em Massa
 * Notificações do Cidadão (notificacoes-cidadao.js) e Central de Comunicados do Gestor (notificacoes.php).
 */

require_once __DIR__ . '/_db_restore.php';
require_once __DIR__ . '/../includes/notificacoes_admin.php';
require_once __DIR__ . '/../includes/csrf.php';

$action = $_GET['action'] ?? 'my_notifications';
$db     = get_db();
$data   = get_request_data();

/**
 * Link de redirecionamento de uma notificação.
 *
 * O valor vira `href` no app do cidadão (js/central-notificacoes.js), então
 * `javascript:` ou `data:` gravado aqui seria executado na conta de quem lê.
 * Caminhos relativos do próprio sistema e URLs http(s) completas continuam
 * valendo; o teto de 250 caracteres protege a coluna VARCHAR(255).
 */
function pl_notif_link_valido(string $link): bool {
    $link = trim($link);
    if ($link === '') return true;
    if (mb_strlen($link) > 250) return false;
    if (preg_match('#^(?:javascript|data|vbscript):#i', $link)) return false;
    if (str_starts_with($link, '//')) return false;
    if (preg_match('#^https?://#i', $link)) return true;
    return preg_match('~^[\w./?=&%#-]+$~', $link) === 1;
}

switch ($action) {

    // =========================================================================
    // 1. NOTIFICAÇÕES DO CIDADÃO LOGADO (Central e Drawer)
    // =========================================================================
    case 'my_notifications':
        $user = require_auth();
        $limit = max(1, min(200, (int)($_GET['limit'] ?? 100)));

        $stmt = $db->prepare("
            SELECT n.*, u.name AS sender_name
            FROM notificacoes n
            LEFT JOIN usuarios u ON u.id = n.sender_id
            WHERE n.user_id = ?
            ORDER BY n.created_at DESC
            LIMIT {$limit}
        ");
        $stmt->execute([$user['id']]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Padroniza campos compatíveis com frontends (corpo, message, lida, titulo)
        foreach ($rows as &$r) {
            $r['body']    = $r['body'] ?? '';
            $r['message'] = $r['body'];
            $r['corpo']   = $r['body'];
            $r['titulo']  = $r['title'] ?? '';
            $r['lida']    = (bool)$r['is_read'];
            $r['id']      = (int)$r['id'];
            $r['user_id'] = (int)$r['user_id'];
        }
        unset($r);

        // Contagem real de não lidas
        $stmtUnread = $db->prepare("SELECT COUNT(*) FROM notificacoes WHERE user_id = ? AND is_read = 0");
        $stmtUnread->execute([$user['id']]);
        $unreadCount = (int)$stmtUnread->fetchColumn();

        json_response([
            'success'      => true,
            'user'         => [
                'id'    => $user['id'],
                'name'  => $user['name'],
                'email' => $user['email']
            ],
            'unread_count' => $unreadCount,
            'total'        => count($rows),
            'rows'         => $rows
        ]);
        break;

    // =========================================================================
    // 2. DETALHE DE UMA NOTIFICAÇÃO (Garante isolamento de usuário e marca como lida)
    // =========================================================================
    case 'detail':
        $user = require_auth();
        $id = (int)($_GET['id'] ?? ($data['id'] ?? 0));

        if ($id <= 0) {
            json_error('ID da notificação é obrigatório.');
        }

        // Busca restrita ao usuário logado — Impede vazamento entre contas
        $stmt = $db->prepare("
            SELECT n.*, u.name AS sender_name
            FROM notificacoes n
            LEFT JOIN usuarios u ON u.id = n.sender_id
            WHERE n.id = ? AND n.user_id = ?
            LIMIT 1
        ");
        $stmt->execute([$id, $user['id']]);
        $notif = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$notif) {
            json_error('Notificação não encontrada ou acesso não autorizado.', 404);
        }

        // Marca como lida no banco se ainda não estiver
        if ((int)$notif['is_read'] === 0) {
            $db->prepare("UPDATE notificacoes SET is_read = 1 WHERE id = ? AND user_id = ?")->execute([$id, $user['id']]);
            $notif['is_read'] = 1;
        }

        $notif['body']    = $notif['body'] ?? '';
        $notif['message'] = $notif['body'];
        $notif['corpo']   = $notif['body'];
        $notif['titulo']  = $notif['title'] ?? '';
        $notif['lida']    = (bool)$notif['is_read'];
        $notif['id']      = (int)$notif['id'];
        $notif['user_id'] = (int)$notif['user_id'];

        $stmtUnread = $db->prepare("SELECT COUNT(*) FROM notificacoes WHERE user_id = ? AND is_read = 0");
        $stmtUnread->execute([$user['id']]);
        $unreadCount = (int)$stmtUnread->fetchColumn();

        json_response([
            'success'      => true,
            'notificacao'  => $notif,
            'unread_count' => $unreadCount
        ]);
        break;

    // =========================================================================
    // 3. MARCAR NOTIFICAÇÃO COMO LIDA (OU TODAS)
    // =========================================================================
    case 'mark_read':
    case 'mark_all_read':
        $user = require_auth();
        $id = (int)($data['id'] ?? ($_GET['id'] ?? 0));

        if ($action === 'mark_all_read' || $id <= 0) {
            // Marca todas do usuário autenticado como lidas
            $stmt = $db->prepare("UPDATE notificacoes SET is_read = 1 WHERE user_id = ? AND is_read = 0");
            $stmt->execute([$user['id']]);
            $afetados = $stmt->rowCount();
        } else {
            // Marca notificação específica pertencente ao usuário
            $stmt = $db->prepare("UPDATE notificacoes SET is_read = 1 WHERE id = ? AND user_id = ?");
            $stmt->execute([$id, $user['id']]);
            $afetados = $stmt->rowCount();
        }

        $stmtUnread = $db->prepare("SELECT COUNT(*) FROM notificacoes WHERE user_id = ? AND is_read = 0");
        $stmtUnread->execute([$user['id']]);
        $unread = (int)$stmtUnread->fetchColumn();

        json_response([
            'success'      => true,
            'affected'     => $afetados,
            'unread_count' => $unread
        ]);
        break;

    // =========================================================================
    // 4. EXCLUIR NOTIFICAÇÃO (ISOLAMENTO DE USUÁRIO)
    // =========================================================================
    case 'delete':
        $user = require_auth();
        $id = (int)($data['id'] ?? ($_GET['id'] ?? 0));

        if ($id <= 0) {
            json_error('ID da notificação é obrigatório.');
        }

        $stmt = $db->prepare("DELETE FROM notificacoes WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $user['id']]);

        $stmtUnread = $db->prepare("SELECT COUNT(*) FROM notificacoes WHERE user_id = ? AND is_read = 0");
        $stmtUnread->execute([$user['id']]);
        $unread = (int)$stmtUnread->fetchColumn();

        json_response([
            'success'      => true,
            'unread_count' => $unread,
            'message'      => 'Notificação removida com sucesso.'
        ]);
        break;

    // =========================================================================
    // 5. ENVIO INDIVIDUAL DE NOTIFICAÇÃO PELO GESTOR (Endpoint nativo api/notificacoes.php)
    // =========================================================================
    case 'send_individual':
        $admin = require_admin();

        $rawUserId = $data['user_id'] ?? ($data['destinatario_id'] ?? ($data['id_usuario'] ?? 0));
        $titulo    = trim($data['title'] ?? ($data['titulo'] ?? 'Aviso da Central'));
        $mensagem  = trim($data['message'] ?? ($data['mensagem'] ?? ($data['corpo'] ?? '')));
        $tipo      = trim($data['type'] ?? ($data['tipo'] ?? 'gestor'));
        $link      = trim($data['link'] ?? '');

        if (is_numeric($rawUserId) && (int)$rawUserId > 0) {
            $destId = (int)$rawUserId;
        } else {
            $stmtFind = $db->prepare("SELECT id FROM usuarios WHERE user_code = ? OR email = ? LIMIT 1");
            $stmtFind->execute([trim($rawUserId), trim($rawUserId)]);
            $destId = (int)$stmtFind->fetchColumn();
        }

        if ($destId <= 0 || empty($mensagem)) {
            json_error('Destinatário e mensagem são obrigatórios.');
        }

        // O formulário já limita, mas a API também recusa: sem isso o MySQL em
        // modo não estrito trunca o título de 201 caracteres em silêncio.
        if (mb_strlen($titulo) > 200) {
            json_error('O título da notificação deve ter no máximo 200 caracteres.');
        }
        if (mb_strlen($mensagem) > 500) {
            json_error('A mensagem da notificação deve ter no máximo 500 caracteres.');
        }

        if (!pl_notif_link_valido($link)) {
            json_error('Link de redirecionamento inválido. Use um caminho do próprio sistema (ex.: solicitacao-detalhe.php?id=12) ou uma URL http(s), com até 250 caracteres.');
        }

        $stmtDest = $db->prepare("SELECT id, name FROM usuarios WHERE id = ? LIMIT 1");
        $stmtDest->execute([$destId]);
        $destUser = $stmtDest->fetch();
        if (!$destUser) {
            json_error('Cidadão destinatário não encontrado no sistema.', 404);
        }

        try {
            $stmt = $db->prepare("
                INSERT INTO notificacoes (user_id, sender_id, title, body, type, is_read, link, created_at)
                VALUES (?, ?, ?, ?, ?, 0, ?, NOW())
            ");
            $stmt->execute([$destId, $admin['id'], $titulo, $mensagem, $tipo, $link ?: null]);
            $notifId = (int)$db->lastInsertId();
        } catch (\Throwable $e) {
            handle_db_error($e, 'Não foi possível gravar a notificação no banco de dados.');
        }

        log_audit(
            'notificacoes',
            'Notificação Individual Enviada',
            "Gestor {$admin['name']} enviou notificação #{$notifId} ao cidadão ID {$destId}: \"{$titulo}\"",
            'baixo',
            $admin['id']
        );

        json_response([
            'success'        => true,
            'notificacao_id' => $notifId,
            'destinatario_id'=> $destId,
            'message'        => 'Notificação enviada com sucesso!'
        ]);
        break;

    // =========================================================================
    // 6. HISTÓRICO ADMINISTRATIVO — feed e leitura própria do gestor
    //    O estado lido é do administrador autenticado; `notificacoes.is_read`
    //    (leitura pelo cidadão) não é alterado por nenhuma destas ações.
    // =========================================================================
    case 'admin_feed':
        $admin  = require_admin();
        $limite = max(1, min(100, (int)($_GET['limit'] ?? 25)));

        json_response([
            'success'   => true,
            'linhas'    => pl_notif_feed_admin($db, (int)$admin['id'], $limite),
            'contagens' => pl_notif_contagens_admin($db, (int)$admin['id'])
        ]);
        break;

    case 'admin_mark_all_read':
        $admin = require_admin();
        pl_csrf_require($data);

        if (!pl_notif_leitura_admin_existe($db)) {
            json_error(
                'O painel ainda não tem onde guardar a leitura administrativa. Aplique migracao_leitura_admin_notificacoes.sql e recarregue a página.',
                500,
                ['code' => 'leitura_admin_pendente']
            );
        }

        try {
            $afetados = pl_notif_marcar_todas_lidas_admin($db, (int)$admin['id']);
        } catch (\Throwable $e) {
            handle_db_error($e, 'Não foi possível registrar a leitura das notificações no banco de dados.');
        }

        json_response([
            'success'   => true,
            'afetados'  => $afetados,
            'message'   => $afetados === 1
                ? '1 notificação marcada como lida no painel.'
                : ($afetados > 0
                    ? "{$afetados} notificações marcadas como lidas no painel."
                    : 'Nenhuma notificação nova para marcar — o histórico já estava todo lido.'),
            'linhas'    => pl_notif_feed_admin($db, (int)$admin['id'], 25),
            'contagens' => pl_notif_contagens_admin($db, (int)$admin['id'])
        ]);
        break;

    // =========================================================================
    // 3. FEED DO GESTOR E LAUDOS A VENCER (notificacoes.php)
    // =========================================================================
    case 'gestor_feed':
        require_admin();

        // Lista comunicados emitidos
        $stmtCom = $db->query("
            SELECT c.*, u.name AS gestor_nome
            FROM comunicados c
            LEFT JOIN usuarios u ON u.id = c.gestor_id
            ORDER BY c.enviado_em DESC
            LIMIT 20
        ");
        $comunicados = $stmtCom->fetchAll();

        // Alerta de laudos a vencer (simulado com cidadãos verificados recentes)
        $stmtLaudos = $db->query("
            SELECT id, user_code, name, email, pcd_categories, created_at,
                   DATE_ADD(created_at, INTERVAL 1 YEAR) AS data_vencimento,
                   DATEDIFF(DATE_ADD(created_at, INTERVAL 1 YEAR), NOW()) AS dias_restantes
            FROM usuarios
            WHERE is_admin = 0
            HAVING dias_restantes BETWEEN 0 AND 45
            ORDER BY dias_restantes ASC
            LIMIT 10
        ");
        $laudosVencendo = $stmtLaudos->fetchAll();
        foreach ($laudosVencendo as &$lv) {
            $lv['pcd_categories'] = is_string($lv['pcd_categories']) ? json_decode($lv['pcd_categories'], true) : [];
        }

        json_response([
            'success'         => true,
            'comunicados'     => $comunicados,
            'laudos_vencendo' => $laudosVencendo
        ]);
        break;

    // =========================================================================
    // 4. DISPARO DE COMUNICADO EM MASSA PELO GESTOR
    // =========================================================================
    case 'send_broadcast':
        $admin = require_admin();

        $titulo      = trim($data['titulo'] ?? ($data['title'] ?? ''));
        $mensagem    = trim($data['mensagem'] ?? ($data['message'] ?? ''));
        $tipo        = trim($data['tipo'] ?? 'informativo');
        $publicoAlvo = trim($data['publico_alvo'] ?? 'todos'); // 'todos', 'fisica', 'visual', 'auditiva'
        $canais      = is_array($data['canais'] ?? null) ? $data['canais'] : ['app', 'push'];

        if (empty($titulo) || empty($mensagem)) {
            json_error('Título e mensagem do comunicado são obrigatórios.');
        }

        try {
            $db->beginTransaction();

            // 1. Grava em `comunicados`
            $stmtCom = $db->prepare("
                INSERT INTO comunicados
                  (gestor_id, titulo, mensagem, tipo, publico_alvo, total_destinatarios, total_entregues, total_lidos)
                VALUES
                  (?, ?, ?, ?, ?, 0, 0, 0)
            ");
            $stmtCom->execute([
                $admin['id'],
                $titulo,
                $mensagem,
                $tipo,
                $publicoAlvo
            ]);
            $comunicadoId = (int)$db->lastInsertId();

            // 2. Grava canais em `comunicado_canais`
            $stmtCanal = $db->prepare("INSERT INTO comunicado_canais (comunicado_id, canal, status_envio) VALUES (?, ?, 'enviado')");
            foreach ($canais as $canal) {
                $stmtCanal->execute([$comunicadoId, $canal]);
            }

            // 3. Dispara notificações individuais no app para o público-alvo
            $whereAlvo = "is_admin = 0";
            if ($publicoAlvo === 'fisica') {
                $whereAlvo .= " AND pcd_categories LIKE '%Física%'";
            } elseif ($publicoAlvo === 'visual') {
                $whereAlvo .= " AND pcd_categories LIKE '%Visual%'";
            } elseif ($publicoAlvo === 'auditiva') {
                $whereAlvo .= " AND pcd_categories LIKE '%Auditiva%'";
            }

            $destinatarios = $db->query("SELECT id FROM usuarios WHERE {$whereAlvo}")->fetchAll(PDO::FETCH_COLUMN);
            $totalDest = count($destinatarios);

            if ($totalDest > 0) {
                $stmtNotif = $db->prepare("INSERT INTO notificacoes (user_id, sender_id, title, body, type, is_read, created_at) VALUES (?, ?, ?, ?, ?, 0, NOW())");
                foreach ($destinatarios as $destId) {
                    $stmtNotif->execute([$destId, $admin['id'], $titulo, $mensagem, $tipo === 'alerta' ? 'alert' : 'info']);
                }
            }

            // Atualiza métricas de envio
            $db->prepare("UPDATE comunicados SET total_destinatarios = ?, total_entregues = ? WHERE id = ?")
               ->execute([$totalDest, $totalDest, $comunicadoId]);

            log_audit(
                'comunicados',
                "Comunicado em Massa Disparado ({$tipo})",
                "Gestor {$admin['name']} disparou \"{$titulo}\" para {$totalDest} cidadãos via " . implode(', ', $canais),
                'medio',
                $admin['id']
            );

            $db->commit();

            json_response([
                'success'             => true,
                'comunicado_id'       => $comunicadoId,
                'total_destinatarios' => $totalDest,
                'message'             => "Comunicado disparado com sucesso para {$totalDest} cidadãos!"
            ]);

        } catch (Exception $e) {
            $db->rollBack();
            json_error('Erro ao disparar comunicado: ' . $e->getMessage(), 500);
        }
        break;

    default:
        json_error('Ação de notificação não suportada.', 404);
}
