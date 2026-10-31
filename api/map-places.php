<?php
/**
 * Passe Livre — API de Locais para o Mapa Interativo
 * Retorna locais com coordenadas para renderizar no Leaflet.js
 *
 * O centro da busca vem sempre do cliente (posição confirmada no mapa/GPS ou
 * endereço geocodificado). Não há "caixa padrão de Suzano": sem centro válido a
 * resposta marca centro ausente, e o front-end avisa que a região não tem local
 * cadastrado em vez de desenhar marcadores de outra cidade.
 */
require_once __DIR__ . '/_db_restore.php';
require_once __DIR__ . '/../includes/geo_centro.php';

header('Content-Type: application/json; charset=utf-8');
$appUrl = trim((string) pl_get_env('APP_URL', ''));
if ($appUrl !== '') {
    header('Access-Control-Allow-Origin: ' . $appUrl);
    header('Vary: Origin');
}

$db = get_db();

$q         = trim($_GET['q'] ?? '');
$municipio = pl_geo_municipio_normalizado($_GET['municipio'] ?? '');
$raioKm    = pl_geo_raio_km($_GET['raio_km'] ?? ($_GET['radius'] ?? null));
$centro    = pl_geo_centro_valido($_GET['lat'] ?? null, $_GET['lng'] ?? null);
$destId    = isset($_GET['dest_id']) ? (int)$_GET['dest_id'] : 0;

// Busca local de destino especifico
$destino = null;
if ($destId > 0) {
    $stmt = $db->prepare("SELECT id, name, lat, lng, address, category, access_status, access_text, rating, municipio, uf FROM locais WHERE id = ? AND lat IS NOT NULL LIMIT 1");
    $stmt->execute([$destId]);
    $destino = $stmt->fetch() ?: null;
}

// Se tiver termo de busca, tenta encontrar local pelo nome
if (!$destino && !empty($q)) {
    $stmt = $db->prepare("SELECT id, name, lat, lng, address, category, access_status, access_text, rating, municipio, uf FROM locais WHERE (name LIKE ? OR address LIKE ? OR category LIKE ? OR municipio LIKE ?) AND lat IS NOT NULL ORDER BY rating DESC LIMIT 1");
    $stmt->execute(["%{$q}%", "%{$q}%", "%{$q}%", "%{$q}%"]);
    $destino = $stmt->fetch() ?: null;
}

// Busca locais proximos ao centro informado
$locais = [];
if ($centro['ok']) {
    $sql = "
        SELECT id, name, lat, lng, address, category, category_key, access_status, access_text, rating, reviews_count, municipio, uf,
          " . pl_geo_haversine_sql() . " AS distancia_km
        FROM locais
        WHERE lat IS NOT NULL AND lng IS NOT NULL AND is_archived = 0
        HAVING distancia_km <= ?
        ORDER BY distancia_km ASC, rating DESC
        LIMIT " . (int) PL_LOCAIS_MAX_RESULTADOS . "
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute([$centro['lat'], $centro['lng'], $centro['lat'], $raioKm]);
    $locais = $stmt->fetchAll();
}

// Sem local no raio a resposta fica vazia de propósito. O antigo fallback
// recarregava os 20 melhores da tabela — todos de Suzano — e o mapa desenhava
// marcadores de outra cidade como se estivessem perto do usuário.
$semResultado = null;
if ($centro['ok'] && empty($locais)) {
    $stmtNear = $db->prepare("
        SELECT id, name, municipio, " . pl_geo_haversine_sql() . " AS distancia_km
        FROM locais
        WHERE lat IS NOT NULL AND lng IS NOT NULL AND is_archived = 0
        ORDER BY distancia_km ASC
        LIMIT 1
    ");
    $stmtNear->execute([$centro['lat'], $centro['lng'], $centro['lat']]);
    $near = $stmtNear->fetch();
    $semResultado = [
        'motivo'       => $near ? 'nenhum_local_no_raio' : 'nenhum_local_cadastrado',
        'raio_km'      => $raioKm,
        'mais_proximo' => $near ? [
            'id'           => (int)$near['id'],
            'name'         => $near['name'],
            'municipio'    => $near['municipio'],
            'distancia_km' => round((float)$near['distancia_km'], 1)
        ] : null
    ];
}

$centroOut = $centro['ok']
    ? ['lat' => round($centro['lat'], 6), 'lng' => round($centro['lng'], 6)]
    : null;

echo json_encode([
    'success'       => true,
    'destino'       => $destino,
    'locais'        => $locais,
    'origem'        => $centroOut,
    'centro'        => $centroOut,
    'raio_km'       => $raioKm,
    'municipio'     => $municipio,
    'sem_resultado' => $semResultado
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
