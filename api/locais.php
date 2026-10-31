<?php
/**
 * Passe Livre Suzano — Endpoint de Gestão e Busca de Locais Acessíveis
 * Listagem com cálculo de distância (Haversine), filtro de recursos, % compatibilidade,
 * detalhes, avaliação de recursos, arquivamento e exportação GeoJSON.
 */

require_once __DIR__ . '/_db_restore.php';
require_once __DIR__ . '/../includes/compatibilidade.php';
require_once __DIR__ . '/../includes/geo_centro.php';

$action = $_GET['action'] ?? 'list';
$db     = get_db();
$data   = get_request_data();

switch ($action) {

    // =========================================================================
    // 1. LISTA DE LOCAIS COM FILTROS GEOGRÁFICOS E COMPATIBILIDADE PCD
    // =========================================================================
    case 'list':
        $catKey   = $_GET['category'] ?? ($_GET['category_key'] ?? '');
        $status   = $_GET['status'] ?? ($_GET['access_status'] ?? '');
        $raioKm   = pl_geo_raio_km($_GET['raio_km'] ?? ($_GET['radius'] ?? null));
        $recursos = isset($_GET['recursos']) ? (is_array($_GET['recursos']) ? $_GET['recursos'] : explode(',', $_GET['recursos'])) : [];
        $q        = trim($_GET['q'] ?? '');
        $municipio = pl_geo_municipio_normalizado($_GET['municipio'] ?? '');
        $archived = isset($_GET['include_archived']) ? 1 : 0;

        // Centro da busca: só valem coordenadas informadas pelo cliente.
        // O antigo padrão fixo em Suzano fazia qualquer busca sem coordenadas
        // responder como se o usuário estivesse naquela cidade.
        $centro = pl_geo_centro_valido($_GET['lat'] ?? null, $_GET['lng'] ?? null);
        $userLat = $centro['lat'];
        $userLng = $centro['lng'];

        $where = ["is_archived = ?"];
        $params = [$archived];

        if (!empty($catKey) && $catKey !== 'all') {
            $where[] = "category_key = ?";
            $params[] = $catKey;
        }
        if (!empty($status) && $status !== 'all') {
            // "full" (interface do gestor e filtros salvos) e "verified" (coluna
            // access_status) descrevem o mesmo nível; aceitos os dois grafismos.
            $statusAlternativos = $status === 'full' ? ['full', 'verified'] : [$status];
            $placeholders = implode(', ', array_fill(0, count($statusAlternativos), '?'));
            $where[] = "access_status IN ({$placeholders})";
            foreach ($statusAlternativos as $s) {
                $params[] = $s;
            }
        }
        if ($municipio !== null) {
            $where[] = "municipio = ?";
            $params[] = $municipio;
        }
        if (!empty($q)) {
            $where[] = "(name LIKE ? OR address LIKE ? OR bairro LIKE ? OR municipio LIKE ?)";
            array_push($params, "%{$q}%", "%{$q}%", "%{$q}%", "%{$q}%");
        }

        $whereClause = implode(" AND ", $where);
        $haversine = pl_geo_haversine_sql();

        if ($centro['ok']) {
            // Fórmula Haversine para cálculo de distância em quilômetros
            $sql = "
                SELECT *, {$haversine} AS distancia_km
                FROM locais
                WHERE {$whereClause}
                HAVING distancia_km <= ?
                ORDER BY distancia_km ASC, rating DESC
                LIMIT " . (int) PL_LOCAIS_MAX_RESULTADOS . "
            ";

            $fullParams = array_merge([$userLat, $userLng, $userLat], $params, [$raioKm]);
            $stmt = $db->prepare($sql);
            $stmt->execute($fullParams);
            $locais = $stmt->fetchAll();
        } else {
            // Sem centro geográfico não há como prometer proximidade: devolve a
            // listagem ordenada por avaliação e marca distancia_km como ausente
            // para a interface não exibir uma distância inventada.
            $stmt = $db->prepare("
                SELECT *, NULL AS distancia_km
                FROM locais
                WHERE {$whereClause}
                ORDER BY rating DESC
            ");
            $stmt->execute($params);
            $locais = $stmt->fetchAll();
        }

        // Quando o raio não alcança nada, o caminho antigo recarregava a tabela
        // inteira — o que devolvia os locais de sempre (todos de Suzano) para um
        // usuário que havia pesquisado outra cidade. Agora a resposta diz quais
        // foram o centro e o raio usados e onde está o local cadastrado mais
        // próximo, para a interface poder propor ampliar a busca.
        $maisProximo = null;
        if ($centro['ok'] && empty($locais)) {
            $stmtNear = $db->prepare("
                SELECT id, name, address, municipio, {$haversine} AS distancia_km
                FROM locais
                WHERE {$whereClause} AND lat IS NOT NULL AND lng IS NOT NULL
                ORDER BY distancia_km ASC
                LIMIT 1
            ");
            $stmtNear->execute(array_merge([$userLat, $userLng, $userLat], $params));
            $row = $stmtNear->fetch();
            if ($row) {
                $maisProximo = [
                    'id'           => (int)$row['id'],
                    'name'         => $row['name'],
                    'address'      => $row['address'],
                    'municipio'    => $row['municipio'],
                    'distancia_km' => round((float)$row['distancia_km'], 1)
                ];
            }
        }

        $municipiosCadastrados = array_values(array_filter(
            $db->query("SELECT DISTINCT municipio FROM locais WHERE is_archived = 0 AND municipio IS NOT NULL AND municipio <> '' ORDER BY municipio")
                ->fetchAll(PDO::FETCH_COLUMN) ?: []
        ));

        // "Cadastrado" e "verificado pela equipe" são coberturas diferentes. Desde
        // que a base passou a receber endereços de todo o estado, anunciar cada
        // município como verificado diria que a equipe vistoriou onde só existe
        // um ponto geocodificado aguardando avaliação.
        $stmtVerificados = $db->prepare("
            SELECT DISTINCT municipio FROM locais
            WHERE is_archived = 0 AND municipio IS NOT NULL AND municipio <> ''
              AND access_status IN ('verified', 'full')
            ORDER BY municipio
        ");
        $stmtVerificados->execute();
        $municipiosVerificados = array_values(array_filter($stmtVerificados->fetchAll(PDO::FETCH_COLUMN) ?: []));

        // Necessidades do usuário autenticado (cadastro PcD; fallback usuario_necessidades)
        $user = get_auth_user();
        $userNeeds = pl_compat_user_needs($user, $db);

        // Processa recursos e calcula compatibilidade para cada local
        $stmtRecs = $db->prepare("SELECT recurso_key, nome_recurso, status FROM local_recursos WHERE local_id = ?");

        foreach ($locais as &$loc) {
            $loc['features'] = is_string($loc['features']) ? json_decode($loc['features'], true) : ($loc['features'] ?: []);
            // Sem centro geográfico a distância é desconhecida, não estimada:
            // o `?? 1.0` antigo fazia todo local parecer estar a 1,2 km do usuário.
            $dist = $loc['distancia_km'] ?? null;
            $loc['distancia_km'] = ($dist === null || $dist === '') ? null : round((float)$dist, 1);
            $loc['distancia_m']  = $loc['distancia_km'] === null ? null : (int)($loc['distancia_km'] * 1000);

            // Busca recursos específicos da tabela `local_recursos`
            $stmtRecs->execute([$loc['id']]);
            $recursosBanco = $stmtRecs->fetchAll();
            $loc['recursos_detalhados'] = $recursosBanco;

            // Filtro por recursos solicitados na busca (se especificado)
            if (!empty($recursos)) {
                $locFeats = array_merge($loc['features'], array_column($recursosBanco, 'recurso_key'));
                $match = false;
                foreach ($recursos as $reqRec) {
                    if (in_array($reqRec, $locFeats)) {
                        $match = true;
                        break;
                    }
                }
                // Se nenhum recurso combinou, reduz score
            }

            // Compatibilidade ponderada pelas necessidades do usuário.
            // Sem perfil PcD não há score personalizado.
            $compat = $userNeeds
                ? pl_compat_score($loc['features'], $recursosBanco, (string)$loc['access_status'], $userNeeds)
                : null;

            if ($compat !== null) {
                $loc['compatibilidade_pct']      = $compat['pct'];
                $loc['compatibilidade_nivel']    = $compat['nivel'];
                $loc['compatibilidade_dados']    = $compat['dados'];
                $loc['compatibilidade_total']    = $compat['total'];
                $loc['compatibilidade_criterios'] = $compat['criterios'];
            } else {
                $loc['compatibilidade_pct']      = null;
                $loc['compatibilidade_nivel']    = null;
                $loc['compatibilidade_dados']    = 0;
                $loc['compatibilidade_total']    = 0;
                $loc['compatibilidade_criterios'] = [];
            }
        }
        unset($loc);

        // Se total de resultados for 0, registra na tabela `buscas_historico` conforme especificação
        $userId = $user ? (int)$user['id'] : null;
        $filtrosLog = json_encode([
            'status'    => $status,
            'recursos'  => $recursos,
            'q'         => $q,
            'municipio' => $municipio,
            'centro'    => $centro['ok'] ? [$userLat, $userLng] : null
        ], JSON_UNESCAPED_UNICODE);
        if (count($locais) === 0) {
            $db->prepare("
                INSERT INTO buscas_historico (user_id, categoria_key, raio_km, filtros, total_resultados)
                VALUES (?, ?, ?, ?, 0)
            ")->execute([
                $userId,
                $catKey,
                $raioKm,
                $filtrosLog
            ]);
        } elseif ($userId) {
            $db->prepare("
                INSERT INTO buscas_historico (user_id, categoria_key, raio_km, filtros, total_resultados)
                VALUES (?, ?, ?, ?, ?)
            ")->execute([
                $userId,
                $catKey,
                $raioKm,
                $filtrosLog,
                count($locais)
            ]);
        }

        $resumoVazio = null;
        if (empty($locais)) {
            $resumoVazio = [
                'motivo'        => $centro['ok'] ? 'nenhum_local_no_raio' : 'sem_centro_geografico',
                'raio_km'       => $centro['ok'] ? $raioKm : null,
                'mais_proximo'  => $maisProximo,
                // Cobertura real da base cadastrada — exibida sem afirmar que o
                // estado inteiro está mapeado.
                'municipios'    => $municipiosCadastrados,
                'municipios_verificados' => $municipiosVerificados,
                'sugestoes_raio' => $centro['ok']
                    ? array_values(array_unique(array_filter(
                        [$raioKm * 3, PL_LOCAIS_RAIO_MAX_KM],
                        static fn($r) => $r <= PL_LOCAIS_RAIO_MAX_KM
                    )))
                    : []
            ];
        }

        json_response([
            'success'             => true,
            'total'               => count($locais),
            'centro'              => $centro['ok'] ? ['lat' => round($userLat, 6), 'lng' => round($userLng, 6)] : null,
            'raio_km'             => $raioKm,
            'municipio_filtro'    => $municipio,
            'necessidades_usuario' => $userNeeds,
            'locais'              => $locais,
            'total_resultados'    => count($locais),
            'sem_resultado'       => $resumoVazio
        ]);
        break;

    // =========================================================================
    // 2. DETALHES COMPLETOS DE UM LOCAL
    // =========================================================================
    case 'detail':
        $id   = (int)($_GET['id'] ?? 0);
        $code = trim($_GET['code'] ?? '');

        if ($id > 0) {
            $stmt = $db->prepare("SELECT * FROM locais WHERE id = ? LIMIT 1");
            $stmt->execute([$id]);
        } elseif (!empty($code)) {
            $stmt = $db->prepare("SELECT * FROM locais WHERE local_code = ? LIMIT 1");
            $stmt->execute([$code]);
        } else {
            json_error('ID ou código do local não fornecido.');
        }

        $local = $stmt->fetch();
        if (!$local) {
            json_error('Local não encontrado.', 404);
        }

        $local['features'] = is_string($local['features']) ? json_decode($local['features'], true) : ($local['features'] ?: []);

        // Busca recursos e suas situações (disponível, parcial, indisponível)
        $stmtRec = $db->prepare("SELECT * FROM local_recursos WHERE local_id = ? ORDER BY id ASC");
        $stmtRec->execute([$local['id']]);
        $recursos = $stmtRec->fetchAll();

        // Busca avaliações recentes com fotos
        $stmtAval = $db->prepare("
            SELECT a.*, u.avatar AS user_avatar
            FROM avaliacoes a
            LEFT JOIN usuarios u ON u.id = a.user_id
            WHERE a.local_id = ?
            ORDER BY a.created_at DESC
            LIMIT 10
        ");
        $stmtAval->execute([$local['id']]);
        $avaliacoes = $stmtAval->fetchAll();
        foreach ($avaliacoes as &$av) {
            $av['photos'] = is_string($av['photos']) ? json_decode($av['photos'], true) : ($av['photos'] ?: []);
        }

        // Ocorrências ativas no local
        $stmtOcorr = $db->prepare("SELECT * FROM ocorrencias WHERE local_id = ? AND status = 'ativa'");
        $stmtOcorr->execute([$local['id']]);
        $ocorrencias = $stmtOcorr->fetchAll();

        json_response([
            'success'     => true,
            'local'       => $local,
            'recursos'    => $recursos,
            'avaliacoes'  => $avaliacoes,
            'ocorrencias' => $ocorrencias
        ]);
        break;

    // =========================================================================
    // 3. INCREMENTAR CONTADOR "ÚTIL" DE UM LOCAL
    // =========================================================================
    case 'mark_useful':
        $id = (int)($data['local_id'] ?? ($_GET['id'] ?? 0));
        if ($id <= 0) {
            json_error('ID do local não informado.');
        }

        $stmt = $db->prepare("UPDATE locais SET useful_count = useful_count + 1 WHERE id = ?");
        $stmt->execute([$id]);

        $stmtCnt = $db->prepare("SELECT useful_count FROM locais WHERE id = ?");
        $stmtCnt->execute([$id]);
        $newCount = (int)$stmtCnt->fetchColumn();

        json_response([
            'success'      => true,
            'useful_count' => $newCount,
            'message'      => 'Agradecemos seu feedback! Marcado como útil.'
        ]);
        break;

    // =========================================================================
    // 4. CADASTRO / EDIÇÃO DE LOCAL (GESTOR)
    // =========================================================================
    case 'save':
        $admin = require_admin();

        $id       = (int)($data['id'] ?? 0);
        $name     = trim($data['name'] ?? '');
        $catKey   = trim($data['category_key'] ?? 'outro');
        $catName  = trim($data['category'] ?? 'Outros');
        $address  = trim($data['address'] ?? '');
        $bairro   = trim($data['bairro'] ?? '');
        // Município/UF vêm do formulário, normalmente preenchidos a partir de
        // api/geocode.php (results[].parts.city / .state). Ficam NULL quando não
        // informados — o cadastro não pode assumir Suzano.
        $municipio = pl_geo_municipio_normalizado($data['municipio'] ?? '');
        $uf        = mb_substr(mb_strtoupper(trim((string)($data['uf'] ?? ''))), 0, 2);
        $status   = trim($data['access_status'] ?? 'unverified');

        // Coordenadas são obrigatórias e não têm mais padrão de fallback: antes,
        // um cadastro sem lat/lng era salvo silenciosamente no centro de Suzano.
        $centroNovo = pl_geo_centro_valido($data['lat'] ?? null, $data['lng'] ?? null);
        if (!$centroNovo['ok']) {
            json_error('Informe as coordenadas do local (lat/lng). Selecione o ponto no mapa ou use a busca de endereço.');
        }
        $lat = $centroNovo['lat'];
        $lng = $centroNovo['lng'];

        $hours    = trim($data['opening_hours'] ?? 'Segunda a Sexta: 08:00 - 18:00');
        $busLines = trim($data['bus_lines'] ?? '');
        $features = is_array($data['features'] ?? null) ? $data['features'] : [];

        if (empty($name) || empty($address)) {
            json_error('Nome e endereço são obrigatórios.');
        }
        if ($uf !== '' && $uf !== 'SP') {
            json_error('Fora do estado de São Paulo o cadastro ainda não é suportado pela base de locais.');
        }
        if ($municipio !== null && $uf === '') {
            $uf = 'SP';
        }

        $statusTexts = [
            'verified'   => 'Totalmente Acessível',
            'partial'    => 'Acessibilidade Parcial',
            'unverified' => 'Não verificado',
            'critical'   => 'Crítico / Inacessível'
        ];
        $accessText = $statusTexts[$status] ?? 'Não verificado';

        if ($id > 0) {
            // Edição
            $stmt = $db->prepare("
                UPDATE locais
                SET name = ?, category = ?, category_key = ?, address = ?, bairro = ?,
                    municipio = ?, uf = ?,
                    access_status = ?, access_text = ?, lat = ?, lng = ?, opening_hours = ?,
                    bus_lines = ?, features = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                $name, $catName, $catKey, $address, $bairro,
                $municipio, $uf,
                $status, $accessText, $lat, $lng, $hours,
                $busLines, json_encode($features, JSON_UNESCAPED_UNICODE), $id
            ]);
            log_audit('locais', 'Atualização de Local', "Local #{$id} \"{$name}\" atualizado por {$admin['name']}", 'medio', $admin['id']);
        } else {
            // Criação
            $code = 'LOC-' . str_pad((string)mt_rand(100, 999), 3, '0', STR_PAD_LEFT);
            $stmt = $db->prepare("
                INSERT INTO locais
                  (local_code, name, category, category_key, address, bairro, municipio, uf, access_status, access_text, lat, lng, opening_hours, bus_lines, features, last_audit)
                VALUES
                  (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, DATE_FORMAT(NOW(), '%d/%m/%Y'))
            ");
            $stmt->execute([
                $code, $name, $catName, $catKey, $address, $bairro,
                $municipio, $uf, $status, $accessText, $lat, $lng, $hours, $busLines,
                json_encode($features, JSON_UNESCAPED_UNICODE)
            ]);
            $id = (int)$db->lastInsertId();
            log_audit('locais', 'Novo Local Cadastrado', "Local {$code} \"{$name}\" inserido por {$admin['name']}", 'medio', $admin['id']);
        }

        json_response([
            'success' => true,
            'id'      => $id,
            'message' => 'Local salvo com sucesso!'
        ]);
        break;

    // =========================================================================
    // 5. ARQUIVAR LOCAL (GESTOR)
    // =========================================================================
    case 'archive':
        $admin = require_admin();
        $id = (int)($data['id'] ?? 0);
        if ($id <= 0) {
            json_error('ID do local não informado.');
        }

        $stmt = $db->prepare("UPDATE locais SET is_archived = 1, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$id]);

        log_audit('locais', 'Local Arquivado', "Local ID {$id} arquivado por {$admin['name']}", 'alto', $admin['id']);

        json_response([
            'success' => true,
            'message' => 'Local arquivado com sucesso.'
        ]);
        break;

    // =========================================================================
    // 6. EXPORTAR GEOJSON
    // =========================================================================
    case 'export_geojson':
        $stmt = $db->query("SELECT * FROM locais WHERE is_archived = 0 AND lat IS NOT NULL AND lng IS NOT NULL");
        $locais = $stmt->fetchAll();

        $features = [];
        foreach ($locais as $loc) {
            $features[] = [
                'type' => 'Feature',
                'geometry' => [
                    'type'        => 'Point',
                    'coordinates' => [(float)$loc['lng'], (float)$loc['lat']]
                ],
                'properties' => [
                    'id'            => (int)$loc['id'],
                    'code'          => $loc['local_code'],
                    'name'          => $loc['name'],
                    'category'      => $loc['category'],
                    'category_key'  => $loc['category_key'],
                    'address'       => $loc['address'],
                    'access_status' => $loc['access_status'],
                    'access_text'   => $loc['access_text'],
                    'rating'        => (float)$loc['rating'],
                    'reviews_count' => (int)$loc['reviews_count'],
                    'municipio'     => $loc['municipio'] ?? null,
                    'uf'            => $loc['uf'] ?? null
                ]
            ];
        }

        // O arquivo declara a cobertura real da base em vez de pressupor um
        // município: quem recebe o GeoJSON precisa saber onde existem dados.
        $municipios = array_values(array_unique(array_filter(
            array_map(static fn($l) => trim((string)($l['municipio'] ?? '')), $locais)
        )));

        $geoJson = [
            'type'      => 'FeatureCollection',
            'generator' => 'Passe Livre Suzano GIS v3.5',
            'gerado_em' => date('c'),
            'municipios_cobertos' => $municipios,
            'features'  => $features
        ];

        header('Content-Type: application/geo+json; charset=utf-8');
        header('Content-Disposition: attachment; filename="locais_acessiveis.geojson"');
        echo json_encode($geoJson, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;

    default:
        json_error('Ação de locais não suportada.', 404);
}
