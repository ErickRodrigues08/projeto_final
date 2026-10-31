<?php
/**
 * Passe Livre Suzano — Endpoint de Auditoria do Sistema
 * Consulta somente-leitura à tabela `logs_auditoria`, validação de integridade criptográfica
 * SHA-256, filtros por período/módulo/risco, KPIs e exportação de relatórios.
 */

require_once __DIR__ . '/_db_restore.php';

require_admin();

$action = $_GET['action'] ?? 'list';
$db     = get_db();

switch ($action) {

    // =========================================================================
    // 1. LISTAGEM COM FILTROS E VALIDAÇÃO DE HASH
    // =========================================================================
    case 'list':
        $modulo  = trim($_GET['module'] ?? ($_GET['type'] ?? ''));
        $risco   = trim($_GET['risk'] ?? '');
        $periodo = trim($_GET['period'] ?? ''); // 'hoje', '7d', '30d', 'mes'
        $q       = trim($_GET['q'] ?? '');

        $where = ["1=1"];
        $params = [];

        if (!empty($modulo) && $modulo !== 'all') {
            $where[] = "action_module = ?";
            $params[] = $modulo;
        }
        if (!empty($risco) && $risco !== 'all') {
            $where[] = "risk_level = ?";
            $params[] = $risco;
        }

        if ($periodo === 'hoje') {
            $where[] = "DATE(created_at) = CURDATE()";
        } elseif ($periodo === '7d') {
            $where[] = "created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        } elseif ($periodo === '30d') {
            $where[] = "created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        } elseif ($periodo === 'mes') {
            $where[] = "MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())";
        }

        if (!empty($q)) {
            $where[] = "(action_title LIKE ? OR action_desc LIKE ? OR operator_name LIKE ? OR ip_address LIKE ?)";
            $params[] = "%{$q}%";
            $params[] = "%{$q}%";
            $params[] = "%{$q}%";
            $params[] = "%{$q}%";
        }

        $whereClause = implode(" AND ", $where);
        $stmt = $db->prepare("
            SELECT * FROM logs_auditoria
            WHERE {$whereClause}
            ORDER BY created_at DESC
            LIMIT 100
        ");
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        // Validação de hash criptográfico: só o formato armazenado é conferido.
        $hashOk = 0;
        foreach ($rows as &$r) {
            $r['hash_valido'] = !empty($r['hash_sha256']) && (strlen($r['hash_sha256']) === 64);
            if ($r['hash_valido']) {
                $hashOk++;
            }
        }
        unset($r);

        // KPIs de auditoria
        $stmtKpi = $db->query("
            SELECT 
              COUNT(*) AS total_eventos,
              SUM(CASE WHEN risk_level = 'critico' THEN 1 ELSE 0 END) AS criticos,
              SUM(CASE WHEN risk_level = 'alto' THEN 1 ELSE 0 END) AS altos,
              SUM(CASE WHEN risk_level = 'medio' THEN 1 ELSE 0 END) AS medios,
              SUM(CASE WHEN risk_level = 'baixo' THEN 1 ELSE 0 END) AS baixos
            FROM logs_auditoria
        ");
        $kpis = $stmtKpi->fetch();

        json_response([
            'success' => true,
            'kpis'    => [
                'total_eventos' => (int)($kpis['total_eventos'] ?? 0),
                'criticos'      => (int)($kpis['criticos'] ?? 0),
                'altos'         => (int)($kpis['altos'] ?? 0),
                'medios'        => (int)($kpis['medios'] ?? 0),
                'baixos'        => (int)($kpis['baixos'] ?? 0),
                'registros_pagina' => count($rows),
                'hash_validos'  => $hashOk,
                'integridade'   => count($rows) === 0
                    ? 'Sem registros nesta página'
                    : sprintf('%d de %d registros desta página com hash de 64 caracteres', $hashOk, count($rows))
            ],
            'rows'    => $rows
        ]);
        break;

    // =========================================================================
    // 2. EXPORTAÇÃO CSV DE AUDITORIA
    // =========================================================================
    case 'export_csv':
        $stmt = $db->query("SELECT id, operator_name, operator_role, action_module, action_title, action_desc, risk_level, ip_address, hash_sha256, created_at FROM logs_auditoria ORDER BY created_at DESC");
        $rows = $stmt->fetchAll();

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="auditoria_passelivre_' . date('Y-m-d') . '.csv"');

        $out = fopen('php://output', 'w');
        // BOM UTF-8 para Excel
        fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
        fputcsv($out, ['ID', 'Operador', 'Cargo', 'Módulo', 'Título da Ação', 'Descrição', 'Risco', 'IP', 'Hash SHA-256', 'Data/Hora'], ';');

        foreach ($rows as $r) {
            fputcsv($out, [
                $r['id'],
                $r['operator_name'],
                $r['operator_role'],
                $r['action_module'],
                $r['action_title'],
                $r['action_desc'],
                $r['risk_level'],
                $r['ip_address'],
                $r['hash_sha256'],
                $r['created_at']
            ], ';');
        }
        fclose($out);
        exit;

    default:
        json_error('Ação de auditoria não suportada.', 404);
}
