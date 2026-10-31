<?php
/**
 * Passe Livre Suzano — Leitura administrativa do histórico de notificações
 *
 * `notificacoes.is_read` responde "o CIDADÃO destinatário já leu?". O painel do
 * gestor precisa de outra resposta: "EU, administrador, já triagem esta
 * mensagem?". Essa segunda informação vive em `notificacoes_leitura_admin`
 * (migracao_leitura_admin_notificacoes.sql) e nunca sobrescreve a primeira.
 *
 * Este arquivo é a única fonte das consultas do histórico administrativo: a
 * página `notificacoes.php` e `api/notificacoes.php` o incluem para que os
 * números mostrados na tela e os devolvidos pela API venham do mesmo SQL.
 */

if (!defined('PL_NOTIF_ADMIN_LOADED')) {
    define('PL_NOTIF_ADMIN_LOADED', true);
}

/**
 * A tabela de leitura administrativa existe no banco conectado?
 *
 * O banco é compartilhado entre as versões do projeto e por dumps: se a
 * migração ainda não foi aplicada, a página tem de continuar mostrando o
 * histórico (tudo como não lida) em vez de quebrar com exceção de SQL.
 */
function pl_notif_leitura_admin_existe(PDO $db): bool {
    static $existe = null;
    if ($existe === null) {
        $stmt = $db->prepare("
            SELECT COUNT(*) FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'notificacoes_leitura_admin'
        ");
        $stmt->execute();
        $existe = (int)$stmt->fetchColumn() === 1;
    }
    return $existe;
}

/**
 * Últimas notificações do sistema com o estado de leitura do administrador.
 *
 * @return array<int, array<string, mixed>>
 */
function pl_notif_feed_admin(PDO $db, int $adminId, int $limite = 25): array {
    $limite    = max(1, min(100, $limite));
    $comLida   = pl_notif_leitura_admin_existe($db);

    $sql = "
        SELECT n.id, n.user_id, n.sender_id, n.title, n.body, n.type, n.link,
               n.is_read, n.created_at,
               u.name  AS dest_nome,
               u.email AS dest_email"
        . ($comLida ? ",
               la.read_at AS admin_read_at
        FROM notificacoes n
        LEFT JOIN usuarios u ON u.id = n.user_id
        LEFT JOIN notificacoes_leitura_admin la
               ON la.notificacao_id = n.id AND la.admin_id = ?" : ",
               NULL AS admin_read_at
        FROM notificacoes n
        LEFT JOIN usuarios u ON u.id = n.user_id") . "
        ORDER BY n.created_at DESC, n.id DESC
        LIMIT {$limite}
    ";

    $stmt = $db->prepare($sql);
    $stmt->execute($comLida ? [$adminId] : []);

    return array_map(static function (array $n): array {
        return [
            'id'            => (int)$n['id'],
            'user_id'       => (int)$n['user_id'],
            'title'         => (string)($n['title'] ?? ''),
            'body'          => (string)($n['body'] ?? ''),
            'type'          => (string)($n['type'] ?? 'info'),
            'link'          => $n['link'] ?? null,
            'criada_em'     => (string)($n['created_at'] ?? ''),
            'destino_nome'  => ($n['dest_nome'] ?? '') ?: 'Todos / Sistema',
            'lida_cidadao'  => (int)$n['is_read'] === 1,
            'lida_admin'    => !empty($n['admin_read_at']),
        ];
    }, $stmt->fetchAll(PDO::FETCH_ASSOC));
}

/**
 * Contadores reais do histórico (cidadão e administrador separados).
 *
 * @return array<string, int>
 */
function pl_notif_contagens_admin(PDO $db, int $adminId): array {
    $contagens = [
        'total'          => (int)$db->query("SELECT COUNT(*) FROM notificacoes")->fetchColumn(),
        'urgentes'       => (int)$db->query("SELECT COUNT(*) FROM notificacoes WHERE type IN ('warning','alert','urgente')")->fetchColumn(),
        'lidas_cidadao'  => (int)$db->query("SELECT COUNT(*) FROM notificacoes WHERE is_read = 1")->fetchColumn(),
        'lidas_admin'    => 0,
    ];
    $contagens['nao_lidas_cidadao'] = $contagens['total'] - $contagens['lidas_cidadao'];

    if (pl_notif_leitura_admin_existe($db)) {
        $stmt = $db->prepare("
            SELECT COUNT(*)
            FROM notificacoes n
            LEFT JOIN notificacoes_leitura_admin la
                   ON la.notificacao_id = n.id AND la.admin_id = ?
            WHERE la.id IS NULL
        ");
        $stmt->execute([$adminId]);
        $contagens['nao_lidas_admin'] = (int)$stmt->fetchColumn();
    } else {
        $contagens['nao_lidas_admin'] = $contagens['total'];
    }
    $contagens['lidas_admin'] = $contagens['total'] - $contagens['nao_lidas_admin'];

    return $contagens;
}

/**
 * Marca como lidas pelo administrador todas as notificações do histórico.
 *
 * Idempotente: a chave única (notificacao_id, admin_id) e o anti-join fazem a
 * segunda clique inserir zero linhas. Em hipótese alguma toca em `is_read`.
 *
 * @return int quantas notificações passaram a lidas nesta chamada
 */
function pl_notif_marcar_todas_lidas_admin(PDO $db, int $adminId): int {
    $stmt = $db->prepare("
        INSERT INTO notificacoes_leitura_admin (notificacao_id, admin_id, read_at)
        SELECT n.id, ?, NOW()
        FROM notificacoes n
        LEFT JOIN notificacoes_leitura_admin la
               ON la.notificacao_id = n.id AND la.admin_id = ?
        WHERE la.id IS NULL
    ");
    $stmt->execute([$adminId, $adminId]);
    return $stmt->rowCount();
}
