<?php
/**
 * Passe Livre Suzano — API Central de Estatísticas e Dashboard
 * GET /api/dashboard.php ou GET /api/estatisticas.php
 * Retorna dados analíticos 100% reais do MySQL para o painel do gestor e indicadores do cidadão.
 */

require_once __DIR__ . '/_db_restore.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $db = get_db();
    $action = $_GET['action'] ?? 'stats';

    // Ação pública devolve apenas contagens agregadas (sem dados de pessoas).
    // Qualquer outra ação é exclusiva de gestores.
    $isPublic = in_array($action, ['public', 'public_stats', 'aggregate'], true);
    if (!$isPublic) {
        require_admin();
    }

    // 1. Contagens de Solicitações
    $stmtSols = $db->query("
        SELECT 
            COUNT(*) AS total,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending,
            SUM(CASE WHEN status = 'analyzing' THEN 1 ELSE 0 END) AS analyzing,
            SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) AS approved,
            SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) AS rejected
        FROM solicitacoes
    ");
    $solsData = $stmtSols->fetch(PDO::FETCH_ASSOC) ?: [
        'total' => 0, 'pending' => 0, 'analyzing' => 0, 'approved' => 0, 'rejected' => 0
    ];

    // 2. Contagens de Usuários
    $stmtUsers = $db->query("
        SELECT 
            COUNT(*) AS total,
            SUM(CASE WHEN status = 'ativo' THEN 1 ELSE 0 END) AS ativos,
            SUM(CASE WHEN status = 'bloqueado' THEN 1 ELSE 0 END) AS bloqueados,
            SUM(CASE WHEN is_admin = 1 THEN 1 ELSE 0 END) AS gestores
        FROM usuarios
    ");
    $usersData = $stmtUsers->fetch(PDO::FETCH_ASSOC) ?: [
        'total' => 0, 'ativos' => 0, 'bloqueados' => 0, 'gestores' => 0
    ];

    // 3. Locais
    $stmtLocais = $db->query("
        SELECT 
            COUNT(*) AS total,
            SUM(CASE WHEN access_status = 'verified' THEN 1 ELSE 0 END) AS verificados,
            ROUND(AVG(rating), 1) AS media_avaliacao
        FROM locais
        WHERE is_archived = 0
    ");
    $locaisData = $stmtLocais->fetch(PDO::FETCH_ASSOC) ?: [
        'total' => 0, 'verificados' => 0, 'media_avaliacao' => 5.0
    ];

    // 4. Avaliações e Ocorrências
    $totalAvaliacoes = (int)$db->query("SELECT COUNT(*) FROM avaliacoes")->fetchColumn();
    $stmtOcorr = $db->query("
        SELECT 
            COUNT(*) AS total,
            SUM(CASE WHEN status = 'ativa' THEN 1 ELSE 0 END) AS ativas,
            SUM(CASE WHEN gravidade = 'urgente' AND status = 'ativa' THEN 1 ELSE 0 END) AS urgentes
        FROM ocorrencias
    ");
    $ocorrData = $stmtOcorr->fetch(PDO::FETCH_ASSOC) ?: ['total' => 0, 'ativas' => 0, 'urgentes' => 0];

    // 5. Notificações e Mensagens
    $totalNotif = (int)$db->query("SELECT COUNT(*) FROM notificacoes")->fetchColumn();
    $totalMsgs  = (int)$db->query("SELECT COUNT(*) FROM mensagens_contato")->fetchColumn();

    // 6. Solicitações por Região / Bairro
    $stmtRegioes = $db->query("
        SELECT 
            COALESCE(NULLIF(region, ''), 'Centro') AS regiao,
            COUNT(*) AS total
        FROM solicitacoes
        GROUP BY COALESCE(NULLIF(region, ''), 'Centro')
        ORDER BY total DESC
    ");
    $regioes = $stmtRegioes->fetchAll(PDO::FETCH_ASSOC);

    // 7. Solicitações por Tipo
    $stmtTipos = $db->query("
        SELECT 
            type_key AS tipo_chave,
            COALESCE(NULLIF(type_name, ''), type_key) AS tipo_nome,
            COUNT(*) AS total
        FROM solicitacoes
        GROUP BY type_key, COALESCE(NULLIF(type_name, ''), type_key)
        ORDER BY total DESC
    ");
    $tipos = $stmtTipos->fetchAll(PDO::FETCH_ASSOC);

    // 8. Gráfico Mensal (Últimos 6 meses baseado em dados reais do banco)
    $stmtMonthly = $db->query("
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') AS periodo,
            DATE_FORMAT(created_at, '%b/%y') AS rotulo,
            COUNT(*) AS total,
            SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) AS approved,
            SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) AS rejected,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending
        FROM solicitacoes
        GROUP BY periodo, rotulo
        ORDER BY periodo ASC
        LIMIT 12
    ");
    $monthlyRows = $stmtMonthly->fetchAll(PDO::FETCH_ASSOC);

    // Se o banco tiver poucas solicitações agrupadas por mês, cria pontos dos últimos 6 meses para o gráfico
    if (empty($monthlyRows)) {
        $mesesPt = ['01'=>'Jan','02'=>'Fev','03'=>'Mar','04'=>'Abr','05'=>'Mai','06'=>'Jun','07'=>'Jul','08'=>'Ago','09'=>'Set','10'=>'Out','11'=>'Nov','12'=>'Dez'];
        $curY = (int)date('Y');
        $curM = (int)date('m');
        $monthlyRows = [];
        for ($i = 5; $i >= 0; $i--) {
            $m = $curM - $i;
            $y = $curY;
            if ($m <= 0) { $m += 12; $y--; }
            $mPad = str_pad((string)$m, 2, '0', STR_PAD_LEFT);
            $lbl = ($mesesPt[$mPad] ?? 'Mês') . '/' . substr((string)$y, 2);
            $monthlyRows[] = [
                'periodo'  => "{$y}-{$mPad}",
                'rotulo'   => $lbl,
                'total'    => $i === 0 ? (int)$solsData['total'] : 0,
                'approved' => $i === 0 ? (int)$solsData['approved'] : 0,
                'rejected' => $i === 0 ? (int)$solsData['rejected'] : 0,
                'pending'  => $i === 0 ? (int)$solsData['pending'] : 0
            ];
        }
    }

    // 9. Solicitações Recentes (10 últimas) — somente para gestores
    $recentSols = [];
    if (!$isPublic) {
        $stmtRecent = $db->query("
            SELECT s.*, 
                   COALESCE(u.name, s.citizen_name) AS citizen_name, 
                   COALESCE(u.email, s.citizen_email) AS citizen_email, 
                   COALESCE(u.avatar, s.citizen_avatar) AS citizen_avatar
            FROM solicitacoes s
            LEFT JOIN usuarios u ON u.id = s.citizen_id
            ORDER BY s.created_at DESC
            LIMIT 10
        ");
        $recentSols = $stmtRecent->fetchAll(PDO::FETCH_ASSOC);
    }

    $payload = [
        'kpis' => [
            'solicitacoes_total'      => (int)$solsData['total'],
            'solicitacoes_pending'    => (int)$solsData['pending'],
            'solicitacoes_analyzing'  => (int)$solsData['analyzing'],
            'solicitacoes_approved'   => (int)$solsData['approved'],
            'solicitacoes_rejected'   => (int)$solsData['rejected'],
            'usuarios_total'          => (int)$usersData['total'],
            'usuarios_ativos'         => (int)$usersData['ativos'],
            'usuarios_bloqueados'     => (int)$usersData['bloqueados'],
            'locais_total'            => (int)$locaisData['total'],
            'locais_verificados'      => (int)$locaisData['verificados'],
            'locais_avaliacao_media'  => (float)($locaisData['media_avaliacao'] ?: 5.0),
            'avaliacoes_total'        => $totalAvaliacoes,
            'ocorrencias_total'       => (int)$ocorrData['total'],
            'ocorrencias_ativas'      => (int)$ocorrData['ativas'],
            'ocorrencias_urgentes'    => (int)$ocorrData['urgentes'],
            'notificacoes_total'      => $totalNotif,
            'mensagens_contato_total' => $totalMsgs
        ],
        'solicitacoes_por_regiao' => $regioes,
        'solicitacoes_por_tipo'   => $tipos,
        'grafico_mensal'          => $monthlyRows
    ];

    if (!$isPublic) {
        $payload['solicitacoes_recentes'] = $recentSols;
    }

    echo json_encode([
        'success' => true,
        'data'    => $payload,
        'message' => 'Indicadores consolidados com sucesso.'
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    error_log("[API Dashboard Error] " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'data'    => null,
        'message' => 'Erro ao compilar indicadores do sistema.'
    ], JSON_UNESCAPED_UNICODE);
}
