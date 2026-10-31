<?php
/**
 * Passe Livre Suzano — Endpoint de Rotas, Passos e Navegação Assistida
 * Grava em `rotas`, `rota_opcoes`, `rota_passos`, lê `trechos_viarios`,
 * gerencia sessões de navegação em `navegacoes` e alerta sobre `ocorrencias`.
 */

require_once __DIR__ . '/_db_restore.php';
require_once __DIR__ . '/../includes/geo_centro.php';

$action = $_GET['action'] ?? 'plan';
$db     = get_db();
$data   = get_request_data();

switch ($action) {

    // =========================================================================
    // 1. PLANEJAR E SALVAR ROTA + OPÇÕES + PASSOS
    // =========================================================================
    case 'plan':
    case 'save':
        $user = get_auth_user();
        $userId = $user ? (int)$user['id'] : null;

        $origemText = trim((string)($data['origin_text'] ?? ($data['origem'] ?? '')));
        $destText   = trim((string)($data['destination_text'] ?? ($data['destino'] ?? '')));
        $origemGeo  = pl_geo_centro_valido($data['origin_lat'] ?? null, $data['origin_lng'] ?? null);
        $destinoGeo = pl_geo_centro_valido($data['dest_lat'] ?? null, $data['dest_lng'] ?? null);

        /* Antes, uma chamada sem parâmetros gravava "Av. Senador Roberto Simonsen →
           Estação CPTM Suzano" com coordenadas de Suzano embutidas. Agora a rota só
           existe a partir do trajeto real escolhido pelo usuário. */
        if ($origemText === '' || $destText === '') {
            json_error('Informe os endereços de origem e destino da rota.', 422);
        }
        if (!$origemGeo['ok'] || !$destinoGeo['ok']) {
            json_error('Coordenadas de origem e destino inválidas ou ausentes.', 422);
        }
        $origemLat = $origemGeo['lat'];
        $origemLng = $origemGeo['lng'];
        $destLat   = $destinoGeo['lat'];
        $destLng   = $destinoGeo['lng'];
        $routeType = mb_substr(trim((string)($data['route_type'] ?? 'acessivel')), 0, 30);

        /* Perfil e geometria do trajeto escolhido. Ficam em rota_opcoes.detalhes
           (LONGTEXT já existente) para a navegação usar exatamente o mesmo
           caminho que o usuário viu — sem alterar o esquema das tabelas. */
        $perfil = trim((string)($data['profile'] ?? ''));
        $perfil = in_array($perfil, ['foot-walking', 'wheelchair'], true) ? $perfil : null;
        $provedor = mb_substr(trim((string)($data['provider'] ?? '')), 0, 40);
        $distanceM = is_numeric($data['distance_m'] ?? null) ? max(0, (int)$data['distance_m']) : null;
        $durationS = is_numeric($data['duration_s'] ?? null) ? max(0, (int)$data['duration_s']) : null;

        $geometria = [];
        if (is_array($data['geometria'] ?? null)) {
            foreach ($data['geometria'] as $ponto) {
                if (!is_array($ponto) || !isset($ponto[0], $ponto[1])) {
                    continue;
                }
                $pLat = filter_var($ponto[0], FILTER_VALIDATE_FLOAT);
                $pLng = filter_var($ponto[1], FILTER_VALIDATE_FLOAT);
                if ($pLat === false || $pLng === false) {
                    continue;
                }
                if ($pLat < -35.0 || $pLat > 6.0 || $pLng < -75.0 || $pLng > -30.0) {
                    continue;
                }
                $geometria[] = [round($pLat, 6), round($pLng, 6)];
                if (count($geometria) >= 20000) {
                    break;
                }
            }
        }
        if (count($geometria) < 2) {
            $geometria = [];
        }

        $temMedida = is_numeric($data['distance_km'] ?? null) && is_numeric($data['duration_min'] ?? null);
        $distanciaKm = $temMedida ? round((float)$data['distance_km'], 2)
                                  : pl_geo_distancia_km($origemLat, $origemLng, $destLat, $destLng);
        $duracaoMin  = $temMedida ? max(1, (int)$data['duration_min'])
                                  : max(1, (int)round($distanciaKm / 4.2 * 60));

        try {
            $db->beginTransaction();

            // 1. Grava em `rotas`
            $stmtRota = $db->prepare("
                INSERT INTO rotas
                  (user_id, origin_text, destination_text, origin_lat, origin_lng, dest_lat, dest_lng, route_type, distance_km, duration_min, status)
                VALUES
                  (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'ativa')
            ");
            $stmtRota->execute([
                $userId,
                $origemText,
                $destText,
                $origemLat,
                $origemLng,
                $destLat,
                $destLng,
                $routeType,
                $distanciaKm,
                $duracaoMin
            ]);
            $rotaId = (int)$db->lastInsertId();

            // 2. Grava a opção escolhida em `rota_opcoes`
            $stmtOpcao = $db->prepare("
                INSERT INTO rota_opcoes (rota_id, tipo_opcao, distancia_km, duracao_min, compatibilidade_pct, detalhes)
                VALUES (?, ?, ?, ?, 0, ?)
            ");
            /* As três "alternativas" antigas (100% acessível, rápida, transporte)
               eram números inventados sobre o trajeto, com piso tátil e rampas
               afirmados sem vistoria. Fica a opção real pedida, sem alegação. */
            $stmtOpcao->execute([
                $rotaId,
                $routeType !== '' ? $routeType : 'acessivel',
                $distanciaKm,
                $duracaoMin,
                json_encode([
                    'fonte'          => $temMedida ? 'tracado_calculado' : 'estimativa_linha_reta',
                    'acessibilidade' => 'nao_verificada',
                    'profile'        => $perfil,
                    'provider'       => $provedor !== '' ? $provedor : null,
                    'distance_m'     => $distanceM,
                    'duration_s'     => $durationS,
                    'geometria'      => $geometria
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            ]);

            // 3. Grava passos da rota em `rota_passos`
            $passos = is_array($data['passos'] ?? null) ? $data['passos'] : [];

            $stmtPasso = $db->prepare("
                INSERT INTO rota_passos (rota_id, ordem, instrucao, icone, distancia_m, acessivel, alerta_obstaculo)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            foreach ($passos as $p) {
                $stmtPasso->execute([
                    $rotaId,
                    (int)($p['ordem'] ?? 1),
                    $p['instrucao'] ?? '',
                    $p['icone'] ?? 'turn-right',
                    (int)($p['distancia_m'] ?? 100),
                    (int)($p['acessivel'] ?? 0),
                    $p['alerta_obstaculo'] ?? null
                ]);
            }

            // 4. Ocorrências ativas dentro da caixa do trajeto
            $margem  = 0.01; // ~1 km
            $stmtOcorr = $db->prepare("
                SELECT * FROM ocorrencias
                WHERE status = 'ativa'
                  AND lat BETWEEN ? AND ?
                  AND lng BETWEEN ? AND ?
                ORDER BY created_at DESC
                LIMIT 5
            ");
            $stmtOcorr->execute([
                min($origemLat, $destLat) - $margem, max($origemLat, $destLat) + $margem,
                min($origemLng, $destLng) - $margem, max($origemLng, $destLng) + $margem
            ]);
            $ocorrenciasAtivas = $stmtOcorr->fetchAll();

            log_audit('rotas', 'Traçado de Rota Salvo', "Rota #{$rotaId} de {$origemText} para {$destText}", 'baixo', $userId);

            $db->commit();

            json_response([
                'success'            => true,
                'rota_id'            => $rotaId,
                'message'            => 'Rota planejada e gravada com sucesso no MySQL!',
                'distancia_km'       => $distanciaKm,
                'duracao_min'        => $duracaoMin,
                'ocorrencias_ativas' => $ocorrenciasAtivas
            ]);

        } catch (Exception $e) {
            $db->rollBack();
            json_error('Erro ao planejar rota: ' . $e->getMessage(), 500);
        }
        break;

    // =========================================================================
    // 2. BUSCAR ROTA E SEUS PASSOS
    // =========================================================================
    case 'get':
        $user    = get_auth_user();
        // Acesso administrativo vem somente da flag is_admin do banco nesta
        // requisição; o texto de `role` é rotulagem e não concede privilégio.
        $isAdmin = $user && (int)($user['is_admin'] ?? 0) === 1;

        $rotaId = (int)($_GET['id'] ?? 0);
        if ($rotaId > 0) {
            $stmt = $db->prepare("SELECT * FROM rotas WHERE id = ? LIMIT 1");
            $stmt->execute([$rotaId]);
        } elseif ($user) {
            // Sem ID: prioriza a última rota do próprio usuário
            $stmt = $db->prepare("SELECT * FROM rotas WHERE user_id = ? ORDER BY id DESC LIMIT 1");
            $stmt->execute([(int)$user['id']]);
        } else {
            // Sem sessão: apenas rotas anônimas
            $stmt = $db->query("SELECT * FROM rotas WHERE user_id IS NULL ORDER BY id DESC LIMIT 1");
        }
        $rota = $stmt->fetch();
        if (!$rota) {
            json_error('Nenhuma rota encontrada.', 404);
        }

        // Previne IDOR: rota vinculada a um usuário só é acessível pelo dono ou admin
        if (!$isAdmin && !empty($rota['user_id']) && (int)$rota['user_id'] !== (int)($user['id'] ?? 0)) {
            json_error('Acesso não autorizado a esta rota.', 403);
        }

        // Opções
        $stmtOp = $db->prepare("SELECT * FROM rota_opcoes WHERE rota_id = ?");
        $stmtOp->execute([$rota['id']]);
        $opcoes = $stmtOp->fetchAll();

        // Passos
        $stmtPassos = $db->prepare("SELECT * FROM rota_passos WHERE rota_id = ? ORDER BY ordem ASC");
        $stmtPassos->execute([$rota['id']]);
        $passos = $stmtPassos->fetchAll();

        // Ocorrências ativas ao longo deste trajeto (não as de outra cidade)
        $ocorrencias = [];
        $temCaixa = is_numeric($rota['origin_lat'] ?? null) && is_numeric($rota['dest_lat'] ?? null)
                 && is_numeric($rota['origin_lng'] ?? null) && is_numeric($rota['dest_lng'] ?? null);
        if ($temCaixa) {
            $oLat = (float)$rota['origin_lat']; $oLng = (float)$rota['origin_lng'];
            $dLat = (float)$rota['dest_lat'];   $dLng = (float)$rota['dest_lng'];
            $margem = 0.01;
            $stmtOcorr = $db->prepare("
                SELECT * FROM ocorrencias
                WHERE status = 'ativa'
                  AND lat BETWEEN ? AND ?
                  AND lng BETWEEN ? AND ?
                ORDER BY created_at DESC
                LIMIT 20
            ");
            $stmtOcorr->execute([
                min($oLat, $dLat) - $margem, max($oLat, $dLat) + $margem,
                min($oLng, $dLng) - $margem, max($oLng, $dLng) + $margem
            ]);
            $ocorrencias = $stmtOcorr->fetchAll();
        }

        json_response([
            'success'     => true,
            'rota'        => $rota,
            'opcoes'      => $opcoes,
            'passos'      => $passos,
            'ocorrencias' => $ocorrencias
        ]);
        break;

    // =========================================================================
    // 3. NAVEGAÇÃO — INÍCIO, RECÁLCULO E FIM
    // =========================================================================
    case 'nav_start':
        $user = require_auth();
        $userId = (int)$user['id'];
        $rotaId = (int)($data['rota_id'] ?? ($_GET['rota_id'] ?? 1));

        $stmt = $db->prepare("
            INSERT INTO navegacoes (user_id, rota_id, status, passo_atual, recalculos_count, iniciada_em)
            VALUES (?, ?, 'iniciada', 1, 0, NOW())
        ");
        $stmt->execute([$userId, $rotaId]);
        $navId = (int)$db->lastInsertId();

        json_response([
            'success'       => true,
            'navegacao_id'  => $navId,
            'status'        => 'iniciada',
            'passo_atual'   => 1
        ]);
        break;

    case 'nav_recalc':
        $user = require_auth();
        $navId = (int)($data['navegacao_id'] ?? ($_GET['navegacao_id'] ?? 0));
        if ($navId <= 0) {
            json_error('ID de navegação inválido.');
        }

        // Previne IDOR: valida que a navegação pertence ao usuário autenticado (ou admin)
        $isAdmin = ((int)$user['is_admin'] === 1);
        if ($isAdmin) {
            $stmt = $db->prepare("
                UPDATE navegacoes
                SET status = 'recalculada', recalculos_count = recalculos_count + 1
                WHERE id = ?
            ");
            $stmt->execute([$navId]);
        } else {
            $stmt = $db->prepare("
                UPDATE navegacoes
                SET status = 'recalculada', recalculos_count = recalculos_count + 1
                WHERE id = ? AND user_id = ?
            ");
            $stmt->execute([$navId, $user['id']]);
        }

        if ($stmt->rowCount() === 0) {
            json_error('Navegação não encontrada ou acesso não autorizado.', 403);
        }

        json_response([
            'success' => true,
            'message' => 'Navegação recalculada com rota de desvio acessível.'
        ]);
        break;

    case 'nav_finish':
        $user = require_auth();
        $navId = (int)($data['navegacao_id'] ?? ($_GET['navegacao_id'] ?? 0));
        if ($navId <= 0) {
            json_error('ID de navegação inválido.');
        }

        // Previne IDOR: valida que a navegação pertence ao usuário autenticado (ou admin)
        $isAdmin = ((int)$user['is_admin'] === 1);
        if ($isAdmin) {
            $stmt = $db->prepare("
                UPDATE navegacoes
                SET status = 'finalizada', finalizada_em = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$navId]);
        } else {
            $stmt = $db->prepare("
                UPDATE navegacoes
                SET status = 'finalizada', finalizada_em = NOW()
                WHERE id = ? AND user_id = ?
            ");
            $stmt->execute([$navId, $user['id']]);
        }

        if ($stmt->rowCount() === 0) {
            json_error('Navegação não encontrada ou acesso não autorizado.', 403);
        }

        json_response([
            'success'  => true,
            'message'  => 'Navegação concluída com sucesso! Redirecionando para avaliação.',
            'redirect' => 'avaliar-local.php'
        ]);
        break;

    default:
        json_error('Ação de rota não suportada.', 404);
}
