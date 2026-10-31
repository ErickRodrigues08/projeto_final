<?php
/**
 * Passe Livre Suzano — Endpoint de Solicitações e Ordens de Serviço
 * Gestão de triagem, ordens de serviço, contadores reais, despachos e auditoria
 */

require_once __DIR__ . '/_db_restore.php';

$action = $_GET['action'] ?? 'list';
$db     = get_db();
$data   = get_request_data();

switch ($action) {

    // =========================================================================
    // 1. CONTADOR REAL DE PENDENTES (Para o Badge da Sidebar do Gestor)
    // =========================================================================
    case 'pending_count':
        require_admin();
        $stmt = $db->query("SELECT COUNT(*) AS total FROM solicitacoes WHERE status = 'pending'");
        $row = $stmt->fetch();
        json_response([
            'success' => true,
            'count'   => (int)($row['total'] ?? 0)
        ]);
        break;

    // =========================================================================
    // 2. CONTADORES GERAIS POR STATUS E KPIs DO DASHBOARD
    // =========================================================================
    case 'kpis':
        require_admin();
        $stmtStatus = $db->query("
            SELECT status, COUNT(*) AS total
            FROM solicitacoes
            GROUP BY status
        ");
        $statusCounts = [
            'pending'   => 0,
            'analyzing' => 0,
            'approved'  => 0,
            'rejected'  => 0,
            'total'     => 0
        ];
        while ($r = $stmtStatus->fetch()) {
            $st = $r['status'];
            $cnt = (int)$r['total'];
            $statusCounts[$st] = $cnt;
            $statusCounts['total'] += $cnt;
        }

        // Total de usuários ativos
        $stmtUsers = $db->query("SELECT COUNT(*) AS total FROM usuarios WHERE status = 'ativo'");
        $userRow = $stmtUsers->fetch();
        $totalUsers = (int)($userRow['total'] ?? 0);

        // Distribuição por região
        $stmtRegion = $db->query("
            SELECT COALESCE(region_name, 'Região Central') AS regiao, COUNT(*) AS total
            FROM solicitacoes
            GROUP BY regiao
        ");
        $regions = $stmtRegion->fetchAll();

        // Evolução mensal (últimos 6 meses)
        $stmtMonthly = $db->query("
            SELECT 
              DATE_FORMAT(created_at, '%b/%y') AS mes,
              COUNT(*) AS total,
              SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) AS aprovados,
              SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) AS recusados,
              SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pendentes
            FROM solicitacoes
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
            GROUP BY DATE_FORMAT(created_at, '%Y-%m'), mes
            ORDER BY DATE_FORMAT(created_at, '%Y-%m') ASC
        ");
        $monthly = $stmtMonthly->fetchAll();

        json_response([
            'success'       => true,
            'status_counts' => $statusCounts,
            'total_users'   => $totalUsers,
            'regions'       => $regions,
            'monthly'       => $monthly
        ]);
        break;

    // =========================================================================
    // 3. LISTAGEM PAGINADA COM FILTROS AVANÇADOS
    // =========================================================================
    case 'list':
        require_admin();
        $status  = $_GET['status'] ?? '';
        $type    = $_GET['type'] ?? '';
        $region  = $_GET['region'] ?? '';
        $q       = trim($_GET['q'] ?? '');
        $page    = max(1, (int)($_GET['page'] ?? 1));
        $limit   = max(1, min(100, (int)($_GET['limit'] ?? 15)));
        $offset  = ($page - 1) * $limit;

        // Período: só aceita data ISO (Y-m-d). Um valor malformado era ignorado
        // em silêncio e a tela mostrava o conjunto inteiro como se o filtro
        // estivesse aplicado.
        $dateFrom = trim((string)($_GET['date_from'] ?? ''));
        $dateTo   = trim((string)($_GET['date_to'] ?? ''));
        foreach (['date_from' => $dateFrom, 'date_to' => $dateTo] as $label => $value) {
            if ($value === '') {
                continue;
            }
            $d = DateTime::createFromFormat('Y-m-d', $value);
            if (!$d || $d->format('Y-m-d') !== $value) {
                json_error("Informe {$label} no formato AAAA-MM-DD.");
            }
        }
        if ($dateFrom !== '' && $dateTo !== '' && $dateFrom > $dateTo) {
            json_error('A data inicial não pode ser posterior à data final.');
        }

        $where = ["1=1"];
        $params = [];

        if (!empty($status) && $status !== 'all') {
            $where[] = "status = ?";
            $params[] = $status;
        }
        if (!empty($type) && $type !== 'all') {
            $where[] = "type_key = ?";
            $params[] = $type;
        }
        if (!empty($region) && $region !== 'all') {
            $where[] = "region = ?";
            $params[] = $region;
        }
        if (!empty($q)) {
            $where[] = "(title LIKE ? OR citizen_name LIKE ? OR address LIKE ? OR protocol LIKE ?)";
            $needle = "%{$q}%";
            $params[] = $needle;
            $params[] = $needle;
            $params[] = $needle;
            $params[] = $needle;
        }
        if ($dateFrom !== '') {
            $where[] = "created_at >= ?";
            $params[] = $dateFrom . ' 00:00:00';
        }
        if ($dateTo !== '') {
            $where[] = "created_at <= ?";
            $params[] = $dateTo . ' 23:59:59';
        }

        $whereClause = implode(" AND ", $where);

        // Contagem total para paginação
        $stmtCount = $db->prepare("SELECT COUNT(*) AS total FROM solicitacoes WHERE {$whereClause}");
        $stmtCount->execute($params);
        $totalRecords = (int)$stmtCount->fetchColumn();

        // Consulta de registros
        $sql = "
            SELECT * FROM solicitacoes
            WHERE {$whereClause}
            ORDER BY created_at DESC
            LIMIT {$limit} OFFSET {$offset}
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        // Decodifica badges
        foreach ($rows as &$r) {
            if (!empty($r['badges']) && is_string($r['badges'])) {
                $r['badges'] = json_decode($r['badges'], true) ?: [];
            }
        }

        json_response([
            'success'       => true,
            'page'          => $page,
            'limit'         => $limit,
            'total_records' => $totalRecords,
            'total_pages'   => ceil($totalRecords / $limit),
            'rows'          => $rows
        ]);
        break;

    // =========================================================================
    // 4. DETALHE DA SOLICITAÇÃO (Com dados do Cidadão e Ordem de Serviço)
    // =========================================================================
    case 'detail':
        $id = (int)($_GET['id'] ?? 0);
        $protocol = trim($_GET['protocol'] ?? '');

        if ($id > 0) {
            $stmt = $db->prepare("SELECT * FROM solicitacoes WHERE id = ? LIMIT 1");
            $stmt->execute([$id]);
        } elseif (!empty($protocol)) {
            $stmt = $db->prepare("SELECT * FROM solicitacoes WHERE protocol = ? LIMIT 1");
            $stmt->execute([$protocol]);
        } else {
            json_error('ID ou Protocolo da solicitação não informado.');
        }

        $solicitacao = $stmt->fetch();
        if (!$solicitacao) {
            json_error('Solicitação não encontrada.', 404);
        }

        // Previne IDOR: cidadão só pode acessar a própria solicitação; gestor pode acessar todas
        $authUser = get_auth_user();
        $isAdmin = $authUser && (int)$authUser['is_admin'] === 1;
        if (!$isAdmin) {
            if (!$authUser || (int)$solicitacao['citizen_id'] !== (int)$authUser['id']) {
                json_error('Acesso não autorizado a esta solicitação.', 403);
            }
        }

        if (!empty($solicitacao['badges']) && is_string($solicitacao['badges'])) {
            $solicitacao['badges'] = json_decode($solicitacao['badges'], true) ?: [];
        }

        // Busca ordens de serviço associadas
        $stmtOs = $db->prepare("
            SELECT os.*, u.name AS gestor_nome
            FROM ordens_servico os
            LEFT JOIN usuarios u ON u.id = os.gestor_id
            WHERE os.solicitacao_id = ?
            ORDER BY os.data_despacho DESC
        ");
        $stmtOs->execute([$solicitacao['id']]);
        $ordensServico = $stmtOs->fetchAll();

        // Busca dados adicionais do cidadão caso vinculado
        $cidadao = null;
        if (!empty($solicitacao['citizen_id'])) {
            $stmtCid = $db->prepare("SELECT id, name, email, phone, city, bairro, address, avatar, role, pcd_categories FROM usuarios WHERE id = ?");
            $stmtCid->execute([$solicitacao['citizen_id']]);
            $cidadao = $stmtCid->fetch();
            if ($cidadao && !empty($cidadao['pcd_categories']) && is_string($cidadao['pcd_categories'])) {
                $cidadao['pcd_categories'] = json_decode($cidadao['pcd_categories'], true) ?: [];
            }
        }

        json_response([
            'success'        => true,
            'solicitacao'    => $solicitacao,
            'ordens_servico' => $ordensServico,
            'cidadao'        => $cidadao
        ]);
        break;

    // =========================================================================
    // 5. ATUALIZAR STATUS / TRIAGEM (Aprovar, Reanalisar, Recusar)
    // =========================================================================
    case 'update_status':
        $admin = require_admin();

        $id        = (int)($data['id'] ?? 0);
        $newStatus = trim($data['status'] ?? '');
        $parecer   = trim($data['notes'] ?? '');

        $validStatus = ['pending', 'analyzing', 'approved', 'rejected'];
        if (!in_array($newStatus, $validStatus)) {
            json_error('Status inválido informado.');
        }

        $statusNames = [
            'pending'   => 'Pendente de Triagem',
            'analyzing' => 'Em Análise',
            'approved'  => 'Aprovada',
            'rejected'  => 'Recusada'
        ];
        $statusName = $statusNames[$newStatus];

        $stmtCheck = $db->prepare("SELECT * FROM solicitacoes WHERE id = ? LIMIT 1");
        $stmtCheck->execute([$id]);
        $sol = $stmtCheck->fetch();
        if (!$sol) {
            json_error('Solicitação não encontrada.');
        }

        $stmtUp = $db->prepare("
            UPDATE solicitacoes
            SET status = ?, status_name = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmtUp->execute([$newStatus, $statusName, $id]);

        // Registra log de auditoria
        log_audit(
            'solicitacoes',
            "Triagem de Solicitação: {$statusName}",
            "Solicitação #{$sol['protocol']} alterada de '{$sol['status']}' para '{$newStatus}' por {$admin['name']}. Parecer: {$parecer}",
            $newStatus === 'rejected' ? 'medio' : 'baixo',
            $admin['id']
        );

        // Se o cidadão estiver vinculado, gera notificação para ele
        if (!empty($sol['citizen_id'])) {
            $msgNotif = "Sua solicitação {$sol['protocol']} foi atualizada para \"{$statusName}\".";
            $db->prepare("
                INSERT INTO notificacoes (user_id, sender_id, title, body, type, is_read, created_at)
                VALUES (?, ?, 'Atualização de Solicitação', ?, ?, 0, NOW())
            ")->execute([$sol['citizen_id'], $admin['id'], $msgNotif, $newStatus === 'approved' ? 'success' : 'info']);
        }

        // Retorna contador de pendentes atualizado
        $newPending = (int)$db->query("SELECT COUNT(*) FROM solicitacoes WHERE status = 'pending'")->fetchColumn();

        json_response([
            'success'       => true,
            'message'       => "Solicitação atualizada para \"{$statusName}\" com sucesso!",
            'pending_count' => $newPending,
            'solicitacao'   => [
                'id'          => $id,
                'status'      => $newStatus,
                'status_name' => $statusName
            ]
        ]);
        break;

    // =========================================================================
    // 6. CRIAR ORDEM DE SERVIÇO (DESPACHO)
    // =========================================================================
    case 'despachar_os':
        $admin = require_admin();

        $solicitacaoId = (int)($data['solicitacao_id'] ?? 0);
        $orgao         = trim($data['orgao_responsavel'] ?? 'Secretaria Municipal de Manutenção Urbana');
        $fiscal        = trim($data['fiscal_responsavel'] ?? '');
        $prioridade    = trim($data['prioridade'] ?? 'media');
        $parecer       = trim($data['parecer'] ?? '');

        if ($solicitacaoId <= 0 || empty($fiscal) || empty($parecer)) {
            json_error('Preencha o fiscal responsável e o parecer técnico do despacho.');
        }

        // Gera número de OS
        $numeroOs = 'OS-' . date('Y') . '-' . str_pad((string)mt_rand(1000, 9999), 4, '0', STR_PAD_LEFT);

        try {
            $db->beginTransaction();

            $stmtOs = $db->prepare("
                INSERT INTO ordens_servico
                  (solicitacao_id, gestor_id, numero_os, orgao_responsavel, fiscal_responsavel, prioridade, parecer, status, data_despacho)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'aberta', NOW())
            ");
            $stmtOs->execute([
                $solicitacaoId,
                $admin['id'],
                $numeroOs,
                $orgao,
                $fiscal,
                $prioridade,
                $parecer
            ]);
            $osId = (int)$db->lastInsertId();

            // Atualiza status da solicitação para aprovada/em execução
            $db->prepare("
                UPDATE solicitacoes
                SET status = 'approved', status_name = 'Aprovada / Em Execução', updated_at = NOW()
                WHERE id = ?
            ")->execute([$solicitacaoId]);

            // Log de auditoria
            log_audit(
                'ordens_servico',
                "Ordem de Serviço Emitida: {$numeroOs}",
                "Despacho emitido para solicitação ID {$solicitacaoId}. Órgão: {$orgao}, Fiscal: {$fiscal}, Prioridade: {$prioridade}",
                'medio',
                $admin['id']
            );

            $db->commit();

            json_response([
                'success'   => true,
                'message'   => "Ordem de Serviço {$numeroOs} despachada com sucesso!",
                'numero_os' => $numeroOs,
                'os_id'     => $osId
            ]);

        } catch (Exception $e) {
            $db->rollBack();
            json_error('Erro ao despachar Ordem de Serviço: ' . $e->getMessage(), 500);
        }
        break;

    // =========================================================================
    // 7. APROVAÇÃO / RECUSA EM LOTE
    // =========================================================================
    case 'batch_action':
        $admin = require_admin();

        $ids    = is_array($data['ids'] ?? null) ? array_map('intval', $data['ids']) : [];
        $action = trim($data['batch_action'] ?? ''); // 'approve' ou 'reject'

        if (empty($ids) || !in_array($action, ['approve', 'reject'])) {
            json_error('Selecione ao menos uma solicitação e uma ação válida.');
        }

        $newStatus  = ($action === 'approve') ? 'approved' : 'rejected';
        $statusName = ($action === 'approve') ? 'Aprovada' : 'Recusada';

        $inClause = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $db->prepare("
            UPDATE solicitacoes
            SET status = ?, status_name = ?, updated_at = NOW()
            WHERE id IN ({$inClause})
        ");
        $stmt->execute(array_merge([$newStatus, $statusName], $ids));

        log_audit(
            'solicitacoes',
            "Ação em Lote ({$statusName})",
            count($ids) . " solicitações alteradas para '{$newStatus}' por {$admin['name']}",
            'medio',
            $admin['id']
        );

        $newPending = (int)$db->query("SELECT COUNT(*) FROM solicitacoes WHERE status = 'pending'")->fetchColumn();

        json_response([
            'success'       => true,
            'message'       => count($ids) . " solicitações foram marcadas como \"{$statusName}\".",
            'affected'      => count($ids),
            'pending_count' => $newPending
        ]);
        break;

    // =========================================================================
    // 8. NOVA SOLICITAÇÃO (INSERT Cidadão ou Gestor)
    // =========================================================================
    case 'create':
        $citizenName = trim($data['citizen_name'] ?? '');
        $typeKey     = trim($data['type_key'] ?? 'outro');
        $typeName    = trim($data['type_name'] ?? 'Solicitação Geral');
        $title       = trim($data['title'] ?? 'Nova Solicitação');
        $address     = trim($data['address'] ?? '');
        $region      = trim($data['region'] ?? 'centro');
        $regionName  = trim($data['region_name'] ?? 'Região Central');
        $description = trim($data['description'] ?? '');
        $priority    = trim($data['priority'] ?? 'media');

        $bairro    = mb_substr(trim((string) ($data['bairro'] ?? '')), 0, 100);
        $cidade    = mb_substr(trim((string) ($data['cidade'] ?? '')), 0, 100);
        $estado    = strtoupper(trim((string) ($data['estado'] ?? '')));
        $cepDigits = preg_replace('/\D+/', '', (string) ($data['cep'] ?? ''));

        if (empty($citizenName)) {
            json_error('Nome do solicitante é obrigatório.');
        }

        if ($cepDigits !== '' && strlen($cepDigits) !== 8) {
            json_error('Informe um CEP válido com 8 dígitos ou deixe o campo em branco.');
        }
        $cep = strlen($cepDigits) === 8
            ? substr($cepDigits, 0, 5) . '-' . substr($cepDigits, 5, 3)
            : null;

        if ($estado !== '' && !preg_match('/^[A-Z]{2}$/', $estado)) {
            json_error('Informe a UF com 2 letras.');
        }

        $latitude  = null;
        $longitude = null;
        foreach (['latitude', 'longitude'] as $coordKey) {
            $rawCoord = $data[$coordKey] ?? null;
            if ($rawCoord === null || $rawCoord === '') {
                continue;
            }
            if (!is_numeric($rawCoord)) {
                json_error('Coordenadas inválidas.');
            }
            if ($coordKey === 'latitude') {
                $latitude = round((float) $rawCoord, 7);
            } else {
                $longitude = round((float) $rawCoord, 7);
            }
        }

        if (($latitude === null) !== ($longitude === null)) {
            json_error('Informe latitude e longitude juntas.');
        }
        if ($latitude !== null
            && ($latitude < -35.0 || $latitude > 6.0 || $longitude < -75.0 || $longitude > -30.0)) {
            json_error('Coordenadas fora da área atendida.');
        }

        if ($address === '' && $cep === null && $latitude === null) {
            json_error('Informe ao menos uma forma de localização: endereço, CEP ou localização no mapa.');
        }

        if ($address === '') {
            $addressParts = array_values(array_filter([$bairro, $cidade, $estado], static fn($v) => $v !== ''));
            if ($cep !== null) {
                $address = 'CEP ' . $cep . ($addressParts ? ' — ' . implode(', ', $addressParts) : '');
            } else {
                $address = 'Coordenadas ' . number_format($latitude, 6, '.', '') . ', '
                    . number_format($longitude, 6, '.', '');
            }
        }
        $address = mb_substr($address, 0, 255);

        $user = get_auth_user();
        $citizenId = $user ? (int)$user['id'] : null;
        $citizenEmail = $user ? $user['email'] : ($data['citizen_email'] ?? null);
        $citizenPhone = $user ? $user['phone'] : ($data['citizen_phone'] ?? null);
        $citizenAvatar = $user ? ($user['avatar'] ?? 'assets/images/avatar.jpg') : 'assets/images/avatar.jpg';

        $protocol = 'SOL-' . date('Y') . '-' . str_pad((string)mt_rand(100, 999), 3, '0', STR_PAD_LEFT);

        $stmt = $db->prepare("
            INSERT INTO solicitacoes
              (protocol, citizen_id, citizen_name, citizen_email, citizen_phone, citizen_avatar, type_key, type_name, title, address, region, region_name, complement, cep, bairro, cidade, estado, latitude, longitude, description, status, status_name, priority, priority_name)
            VALUES
              (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, ?, ?, ?, ?, ?, ?, ?, 'pending', 'Pendente de Triagem', ?, 'Média')
        ");
        $stmt->execute([
            $protocol,
            $citizenId,
            $citizenName,
            $citizenEmail,
            $citizenPhone,
            $citizenAvatar,
            $typeKey,
            $typeName,
            $title,
            $address,
            $region,
            $regionName,
            $cep,
            $bairro !== '' ? $bairro : null,
            $cidade !== '' ? $cidade : null,
            $estado !== '' ? $estado : null,
            $latitude,
            $longitude,
            $description,
            $priority
        ]);
        $newId = (int)$db->lastInsertId();

        log_audit('solicitacoes', 'Nova Solicitação Registrada', "Solicitação #{$protocol} criada por {$citizenName}", 'baixo', $citizenId);

        $newPending = (int)$db->query("SELECT COUNT(*) FROM solicitacoes WHERE status = 'pending'")->fetchColumn();

        json_response([
            'success'       => true,
            'message'       => "Solicitação registrada com sucesso sob protocolo {$protocol}!",
            'id'            => $newId,
            'protocol'      => $protocol,
            'pending_count' => $newPending,
            'location'      => [
                'cep'       => $cep,
                'bairro'    => $bairro !== '' ? $bairro : null,
                'cidade'    => $cidade !== '' ? $cidade : null,
                'estado'    => $estado !== '' ? $estado : null,
                'latitude'  => $latitude,
                'longitude' => $longitude
            ]
        ]);
        break;

    default:
        json_error('Ação de solicitação não suportada.', 404);
}
