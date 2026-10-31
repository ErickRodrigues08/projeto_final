<?php
/**
 * Passe Livre Suzano — Endpoint de Ocorrências (Barreiras e Obstáculos Urbanos)
 * Leitura de ocorrências ativas, reporte de novas barreiras e registro de escolha de desvio.
 */

require_once __DIR__ . '/_db_restore.php';
require_once __DIR__ . '/../includes/geo_centro.php';

$action = $_GET['action'] ?? 'list';
$db     = get_db();
$data   = get_request_data();

switch ($action) {

    // =========================================================================
    // 1. LISTAR OCORRÊNCIAS (Para Mapas e Alertas)
    // =========================================================================
    case 'list':
        $status = $_GET['status'] ?? 'ativa';
        $limit  = max(1, min(100, (int)($_GET['limit'] ?? 50)));

        $where = ["1=1"];
        $params = [];
        if ($status !== 'all') {
            $where[] = "status = ?";
            $params[] = $status;
        }

        $whereClause = implode(" AND ", $where);
        $stmt = $db->prepare("
            SELECT o.*, l.name AS local_nome
            FROM ocorrencias o
            LEFT JOIN locais l ON l.id = o.local_id
            WHERE {$whereClause}
            ORDER BY o.created_at DESC
            LIMIT {$limit}
        ");
        $stmt->execute($params);
        $ocorrencias = $stmt->fetchAll();

        json_response([
            'success'     => true,
            'total'       => count($ocorrencias),
            'ocorrencias' => $ocorrencias
        ]);
        break;

    // =========================================================================
    // 2. DETALHE DE UMA OCORRÊNCIA (Para alerta-obstaculo.php)
    // =========================================================================
    case 'detail':
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            // Busca a ocorrência ativa mais urgente por padrão
            $stmt = $db->query("
                SELECT * FROM ocorrencias
                WHERE status = 'ativa'
                ORDER BY CASE gravidade WHEN 'urgente' THEN 1 WHEN 'alta' THEN 2 ELSE 3 END ASC, id DESC
                LIMIT 1
            ");
        } else {
            $stmt = $db->prepare("SELECT * FROM ocorrencias WHERE id = ? LIMIT 1");
            $stmt->execute([$id]);
        }
        $ocorr = $stmt->fetch();
        if (!$ocorr) {
            json_error('Nenhuma ocorrência encontrada.', 404);
        }

        json_response([
            'success'    => true,
            'ocorrencia' => $ocorr
        ]);
        break;

    // =========================================================================
    // 3. REGISTRAR ESCOLHA DO USUÁRIO (DESVIAR OU CONTINUAR)
    // =========================================================================
    case 'choose_action':
        $user = require_auth();
        $id      = (int)($data['id'] ?? 0);
        $navId   = (int)($data['navigation_id'] ?? 0);
        $escolha = trim($data['escolha'] ?? ''); // 'desviar' ou 'continuar'

        if (!in_array($escolha, ['desviar', 'continuar'])) {
            json_error('Escolha inválida. Informe desviar ou continuar.');
        }

        // Valida se o obstáculo referenciado existe
        if ($id > 0) {
            $stmtOc = $db->prepare("SELECT id FROM ocorrencias WHERE id = ? LIMIT 1");
            $stmtOc->execute([$id]);
            if (!$stmtOc->fetch()) {
                json_error('Ocorrência não encontrada.', 404);
            }
        }

        // Vincula a escolha à sessão de navegação do usuário (evita alterar estado global compartilhado)
        if ($navId > 0) {
            $stmtNav = $db->prepare("UPDATE navegacoes SET acao_desvio = ? WHERE id = ? AND user_id = ?");
            $stmtNav->execute([$escolha, $navId, $user['id']]);
        } else {
            // Atualiza na navegação ativa do próprio usuário
            $stmtNav = $db->prepare("UPDATE navegacoes SET acao_desvio = ? WHERE user_id = ? AND status = 'iniciada' ORDER BY id DESC LIMIT 1");
            $stmtNav->execute([$escolha, $user['id']]);
        }

        log_audit(
            'rotas',
            "Escolha diante de Obstáculo ({$escolha})",
            "Cidadão ID {$user['id']} ({$user['name']}) optou por '{$escolha}' diante do obstáculo ID " . ($id > 0 ? $id : 'geral'),
            'baixo',
            $user['id']
        );

        json_response([
            'success'  => true,
            'escolha'  => $escolha,
            'message'  => ($escolha === 'desviar')
                ? 'Calculando rota alternativa acessível...'
                : 'Você optou por continuar no trajeto original. Tenha atenção redobrada!'
        ]);
        break;

    // =========================================================================
    // 4. REPORTE DE NOVA BARREIRA PELO CIDADÃO OU GESTOR
    // =========================================================================
    case 'create':
        $user = get_auth_user();
        $userId   = $user ? (int)$user['id'] : null;
        $userName = $user ? $user['name'] : trim($data['reporter_nome'] ?? 'Cidadão');

        $titulo    = trim($data['titulo'] ?? ($data['title'] ?? ''));
        $descricao = trim($data['descricao'] ?? ($data['description'] ?? ''));
        $categoria = trim($data['categoria'] ?? 'Obstáculo na Calçada');
        $endereco  = trim($data['endereco'] ?? ($data['address'] ?? ''));
        $gravidade = trim($data['gravidade'] ?? 'media');
        // Coordenadas só entram quando são uma posição válida; o (float) de um
        // texto vazio virava 0,0 e desenhava a barreira no oceano.
        $posOcorr  = pl_geo_centro_valido($data['lat'] ?? null, $data['lng'] ?? null);
        $lat       = $posOcorr['ok'] ? $posOcorr['lat'] : null;
        $lng       = $posOcorr['ok'] ? $posOcorr['lng'] : null;
        $localId   = isset($data['local_id']) ? (int)$data['local_id'] : null;
        $fotoUrl   = trim($data['foto_url'] ?? 'assets/images/map-background.jpg');

        if (empty($titulo) || empty($descricao)) {
            json_error('Título e descrição da ocorrência são obrigatórios.');
        }

        $proto = 'OCO-' . date('Y') . '-' . str_pad((string)mt_rand(100, 999), 3, '0', STR_PAD_LEFT);

        $stmt = $db->prepare("
            INSERT INTO ocorrencias
              (user_id, local_id, protocolo, titulo, descricao, categoria, status, gravidade, lat, lng, endereco, foto_url, reporter_nome, tempo_decorrido)
            VALUES
              (?, ?, ?, ?, ?, ?, 'ativa', ?, ?, ?, ?, ?, ?, 'Agora mesmo')
        ");
        $stmt->execute([
            $userId,
            $localId,
            $proto,
            $titulo,
            $descricao,
            $categoria,
            $gravidade,
            $lat,
            $lng,
            $endereco,
            $fotoUrl,
            $userName
        ]);
        $newId = (int)$db->lastInsertId();

        log_audit('ocorrencias', 'Nova Barreira Reportada', "Ocorrência #{$proto} reportada em {$endereco}", 'medio', $userId);

        json_response([
            'success'   => true,
            'id'        => $newId,
            'protocolo' => $proto,
            'message'   => 'Barreira reportada com sucesso! A comunidade e a central foram notificadas.'
        ]);
        break;

    default:
        json_error('Ação de ocorrência não suportada.', 404);
}
