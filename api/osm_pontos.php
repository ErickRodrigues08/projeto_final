<?php
/**
 * Passe Livre Suzano — Proxy de pontos de acessibilidade do OpenStreetMap (Overpass)
 *
 * GET opcional: lat, lng, raio_km | bbox=s,w,n,e | limite (1 a 80)
 *
 * Resposta:
 *   { success, fonte, total, pontos:[{ id, nome, tipo, lat, lng, tags }] }
 *
 * Sem OVERPASS_URL (ou com falha/timeout do serviço) devolve success=false e o
 * front-end segue apenas com os marcadores já existentes.
 */

require_once __DIR__ . '/_db_restore.php';
require_once __DIR__ . '/http.php';
require_once __DIR__ . '/../includes/geo_centro.php';

// Cache longo: os dados do OSM mudam devagar e o Overpass é compartilhado.
if (!defined('PL_OSM_CACHE_TTL')) define('PL_OSM_CACHE_TTL', 86400);

$OVERPASS = trim((string) OVERPASS_URL);

// Sessao valida obrigatoria + limite por usuario/IP (401 / 429 em JSON).
pl_proxy_guard('osm_pontos');

$centro = pl_geo_centro_valido($_GET['lat'] ?? null, $_GET['lng'] ?? null);
$lat    = $centro['lat'];
$lng    = $centro['lng'];
$raioKm = isset($_GET['raio_km']) ? (float)$_GET['raio_km'] : 2.0;
$limite = isset($_GET['limite']) ? (int)$_GET['limite'] : 60;
$limite = max(1, min(80, $limite));
$raioKm = max(0.3, min(10.0, $raioKm));

// Caixa de busca em graus (sul,oeste,norte,leste)
if (isset($_GET['bbox']) && preg_match('/^(-?\d+(?:\.\d+)?),(-?\d+(?:\.\d+)?),(-?\d+(?:\.\d+)?),(-?\d+(?:\.\d+)?)$/', $_GET['bbox'], $m)) {
    $bbox = sprintf('%F,%F,%F,%F', (float)$m[1], (float)$m[2], (float)$m[3], (float)$m[4]);
} elseif ($centro['ok']) {
    $deltaLat = $raioKm / 111.0;
    $deltaLng = $raioKm / max(0.01, 111.0 * cos(deg2rad($lat)));
    $bbox = sprintf('%F,%F,%F,%F', $lat - $deltaLat, $lng - $deltaLng, $lat + $deltaLat, $lng + $deltaLng);
} else {
    // Não existe mais "caixa padrão de Suzano". Sem centro nem bbox a resposta diz
    // que é preciso informar a região, em vez de consultar sempre a mesma cidade.
    json_response([
        'success'  => false,
        'motivo'   => 'sem_centro_geografico',
        'fallback' => 'estatico',
        'fonte'    => 'OpenStreetMap',
        'total'    => 0,
        'pontos'   => [],
        'mensagem' => 'Informe as coordenadas da região (lat/lng ou bbox) para consultar os pontos de acessibilidade.'
    ]);
}

if ($OVERPASS === '') {
    json_response([
        'success'  => false,
        'motivo'   => 'sem_url_overpass',
        'fallback' => 'estatico',
        'fonte'    => 'OpenStreetMap',
        'total'    => 0,
        'pontos'   => []
    ]);
}

$cacheKey = 'osm_pontos_' . md5($bbox . '|' . $limite);
$cached   = pl_cache_get($cacheKey, PL_OSM_CACHE_TTL);
if (is_array($cached) && isset($cached['pontos'])) {
    json_response([
        'success'  => true,
        'fonte'    => 'OpenStreetMap',
        'cached'   => true,
        'bbox'     => $bbox,
        'total'    => count($cached['pontos']),
        'pontos'   => $cached['pontos']
    ]);
}

$consulta = sprintf(
    '[out:json][timeout:20];(' .
    'nwr["wheelchair"="yes"](%s);' .
    'nwr["tactile_paving"="yes"](%s);' .
    'nwr["ramp:wheelchair"="yes"](%s);' .
    'node["traffic_signals:sound"~"^(yes|signal)$"](%s);' .
    ');out center tags %d;',
    $bbox, $bbox, $bbox, $bbox, $limite
);

pl_throttle_wait('overpass', 1.0);

$resposta = pl_http_request_json(
    'POST',
    $OVERPASS,
    ['Content-Type: application/x-www-form-urlencoded'],
    http_build_query(['data' => $consulta]),
    8
);

$payload = $resposta['data'];

if (!$resposta['success'] || !is_array($payload) || !isset($payload['elements']) || !is_array($payload['elements'])) {
    error_log('[osm_pontos] Overpass indisponivel: ' . ($resposta['error'] ?? 'resposta inesperada'));
    json_response([
        'success'  => false,
        'motivo'   => 'falha_overpass',
        'fallback' => 'estatico',
        'fonte'    => 'OpenStreetMap',
        'total'    => 0,
        'pontos'   => []
    ]);
}

$tipos = [
    'tactile_paving'         => 'Piso tátil',
    'traffic_signals:sound'  => 'Semáforo sonoro',
    'ramp:wheelchair'        => 'Rampa',
    'wheelchair'             => 'Acesso em cadeira de rodas'
];

if (!function_exists('pl_osm_parse_elementos')) {
    /** Converte elementos do Overpass em pontos de acessibilidade. */
    function pl_osm_parse_elementos(array $elements, int $limite, array $tipos): array
    {
        $pontos = [];
        foreach ($elements as $el) {
            if (($el['type'] ?? '') === 'node') {
                $pLat = $el['lat'] ?? null;
                $pLng = $el['lon'] ?? null;
            } else {
                $pLat = $el['center']['lat'] ?? null;
                $pLng = $el['center']['lon'] ?? null;
            }
            if ($pLat === null || $pLng === null) {
                continue;
            }

            $tags = is_array($el['tags'] ?? null) ? $el['tags'] : [];
            $tipoChave = null;
            foreach ($tags as $k => $v) {
                if (isset($tipos[$k]) && in_array((string)$v, ['yes', 'signal'], true)) {
                    $tipoChave = $k;
                    break;
                }
            }
            if ($tipoChave === null) {
                continue;
            }

            $nome = trim((string)($tags['name'] ?? ''));
            if ($nome === '') {
                $detalhe = trim((string)($tags['amenity'] ?? $tags['shop'] ?? $tags['highway'] ?? ''));
                $nome = $detalhe !== ''
                    ? $tipos[$tipoChave] . ' — ' . $detalhe
                    : $tipos[$tipoChave];
            }

            $pontos[] = [
                'id'   => strtolower($el['type'] ?? 'node') . '-' . (int)($el['id'] ?? 0),
                'nome' => $nome,
                'tipo' => $tipos[$tipoChave],
                'lat'  => (float)$pLat,
                'lng'  => (float)$pLng,
                'tags' => array_intersect_key(
                    is_array($tags) ? $tags : [],
                    array_fill_keys(['amenity', 'shop', 'highway', 'wheelchair', 'tactile_paving', 'traffic_signals:sound', 'surface'], true)
                )
            ];

            if (count($pontos) >= $limite) {
                break;
            }
        }
        return $pontos;
    }
}

$pontos = pl_osm_parse_elementos($payload['elements'], $limite, $tipos);

pl_cache_set($cacheKey, ['pontos' => $pontos], PL_OSM_CACHE_TTL);

json_response([
    'success' => true,
    'fonte'   => 'OpenStreetMap',
    'cached'  => false,
    'bbox'    => $bbox,
    'total'   => count($pontos),
    'pontos'  => $pontos
]);
