<?php
/**
 * Passe Livre Suzano — Proxy de rota a pé / cadeira de rodas
 *
 * Provedores (somente no servidor, nunca no navegador):
 *   1. OpenRouteService v2 (perfis foot-walking e wheelchair) quando ORS_API_KEY existe.
 *   2. OSRM público (perfil foot) como alternativa real quando não há chave ou o ORS falha.
 *
 * GET/POST com:
 *   origin_lat, origin_lng, dest_lat, dest_lng, profile (foot-walking | wheelchair)
 *   alternatives        0..3 — alternativas pedidas ao roteador, além da rota principal
 *   allow_foot_fallback 1    — única forma de receber um trajeto a pé quando o perfil
 *                              pedido é wheelchair e o roteador acessível falhou
 *
 * Resposta de sucesso:
 *   { success, provider, provider_label, profile, profile_effective,
 *     accessibility_verified:false, accessibility_note, disclaimer,
 *     coords:[[lat,lng]...], distance_km, duration_min, steps:[...],
 *     rotas:[{ id, numero, rotulo, rotulo_kind, coords, steps, distance_m, distance_km,
 *              duration_s, duration_min, profile_effective, provider, provider_label }] }
 *
 * Os campos de topo são sempre rotas[0] (a preferência do roteador) e continuam
 * existindo porque js/navegacao.js os consome; cada alternativa traz as próprias
 * métricas em rotas[].
 *
 * Resposta de falha (nunca devolve traçado fictício):
 *   { success:false, reason, message, coords:[], steps:[], rotas:[] }
 */

require_once __DIR__ . '/_db_restore.php';
require_once __DIR__ . '/http.php';

if (!defined('PL_ROTA_CACHE_TTL')) define('PL_ROTA_CACHE_TTL', 3600);
if (!defined('PL_ORS_BASE')) define('PL_ORS_BASE', 'https://api.openrouteservice.org/v2/directions/%s/geojson');
if (!defined('PL_OSRM_BASE')) define('PL_OSRM_BASE', 'https://routing.openstreetmap.de/routed-foot/route/v1/foot/%s');
// Teto de alternativas pedidas ao roteador. O serviço pode devolver menos — e
// nesse caso o painel mostra só as que existem.
if (!defined('PL_ROTA_MAX_ALT')) define('PL_ROTA_MAX_ALT', 3);

// A página de configuração de rota exige login -> o proxy também exige
// sessão válida (401) e respeita o limite por usuário/IP (429).
pl_proxy_guard('rota');

const PL_ROTA_DISCLAIMER = 'Sugestão de trajeto calculada por um serviço de roteamento. '
    . 'Condições reais de calçada, rampas, travessias e obstáculos não foram verificadas em campo.';

/**
 * Encerra a requisição com uma falha explícita (o front-end nunca desenha rota fictícia).
 */
function pl_rota_fail(string $reason, string $message, array $extra = []): void
{
    json_response(array_merge([
        'success'                => false,
        'reason'                 => $reason,
        'message'                => $message,
        'coords'                 => [],
        'steps'                  => [],
        'rotas'                  => [],
        'alternatives_requested' => 0,
        'alternatives_returned'  => 0,
        'accessibility_verified' => false,
        'disclaimer'             => PL_ROTA_DISCLAIMER
    ], $extra));
}

function pl_rota_valid_coord($lat, $lng): bool
{
    return is_numeric($lat) && is_numeric($lng)
        && (float) $lat >= -90.0 && (float) $lat <= 90.0
        && (float) $lng >= -180.0 && (float) $lng <= 180.0;
}

/**
 * Distância aproximada em metros (Haversine) — usada só para rejeitar origem == destino.
 */
function pl_rota_haversine_m(float $lat1, float $lng1, float $lat2, float $lng2): float
{
    $r = 6371000.0;
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);
    $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
    return 2 * $r * asin(min(1.0, sqrt($a)));
}

/**
 * Traduz o código HTTP do provedor em motivo/mensagem para o usuário.
 *
 * @return array{0:string,1:string}
 */
function pl_rota_http_reason(int $httpCode): array
{
    if ($httpCode === 0) {
        return ['falha_conexao', 'Não foi possível conectar ao serviço de rotas. Verifique sua conexão e tente novamente.'];
    }
    if ($httpCode === 401 || $httpCode === 403) {
        return ['chave_invalida', 'A chave da API de rotas é inválida ou expirou. Verifique ORS_API_KEY no servidor.'];
    }
    if ($httpCode === 429) {
        return ['limite_excedido', 'O limite de consultas ao serviço de rotas foi atingido. Aguarde alguns minutos e tente novamente.'];
    }
    if ($httpCode === 404) {
        return ['sem_rota', 'Nenhuma rota foi encontrada entre os pontos informados.'];
    }
    if ($httpCode >= 500) {
        return ['api_indisponivel', 'O serviço de rotas está indisponível no momento. Tente novamente em instantes.'];
    }
    return ['falha_api', 'Não foi possível calcular a rota neste momento. Verifique sua conexão ou tente novamente.'];
}

/**
 * Pares [lng, lat] do GeoJSON -> [[lat, lng], ...] com seis casas decimais.
 */
function pl_rota_geojson_para_latlng(array $geoCoords): array
{
    $lista = [];
    foreach ($geoCoords as $pair) {
        if (is_array($pair) && count($pair) >= 2) {
            $lista[] = [round((float) $pair[1], 6), round((float) $pair[0], 6)];
        }
    }
    return $lista;
}

/**
 * Estrutura comum de uma rota: geometria + métricas numéricas próprias.
 * distance_m e duration_s são os valores do roteador; km e min são derivados
 * daqui, nunca copiados de outra alternativa.
 */
function pl_rota_rota_normal(int $numero, array $coords, array $steps, int $distanceM, int $durationS,
                             string $provider, string $providerLabel, string $profileEffective): array
{
    return [
        'id'                => $provider . '-' . $numero,
        'numero'            => $numero,
        'coords'            => $coords,
        'steps'             => $steps,
        'distance_m'        => $distanceM,
        'distance_km'       => round($distanceM / 1000, 3),
        'duration_s'        => $durationS,
        'duration_min'      => $durationS > 0 ? max(1, (int) ceil($durationS / 60)) : 0,
        'profile_effective' => $profileEffective,
        'provider'          => $provider,
        'provider_label'    => $providerLabel
    ];
}

/**
 * Converte TODAS as features da resposta GeoJSON do OpenRouteService: com
 * alternative_routes o serviço devolve uma feature por rota, na ordem de
 * preferência dele. Com units=km, summary.distance vem em quilômetros e
 * summary.duration em segundos.
 *
 * @return array<int, array> rotas normalizadas (vazia quando não há rota válida)
 */
function pl_rota_parse_ors(array $data, string $profileEffective = 'foot-walking'): array
{
    $features = is_array($data['features'] ?? null) ? $data['features'] : [];
    $rotas = [];

    foreach ($features as $feature) {
        if (!is_array($feature)) {
            continue;
        }
        $props = is_array($feature['properties'] ?? null) ? $feature['properties'] : [];
        $geoCoords = is_array($feature['geometry']['coordinates'] ?? null) ? $feature['geometry']['coordinates'] : [];
        $coordsList = pl_rota_geojson_para_latlng($geoCoords);
        if (count($coordsList) < 2) {
            continue;
        }

        $summary = is_array($props['summary'] ?? null) ? $props['summary'] : [];
        $distanceM = (int) round(max(0.0, (float) ($summary['distance'] ?? 0)) * 1000);
        $durationS = (int) round(max(0.0, (float) ($summary['duration'] ?? 0)));

        $steps = [];
        $segments = is_array($props['segments'] ?? null) ? $props['segments'] : [];
        foreach ($segments as $segment) {
            if (!is_array($segment)) {
                continue;
            }
            $segmentSteps = is_array($segment['steps'] ?? null) ? $segment['steps'] : [];
            foreach ($segmentSteps as $step) {
                if (!is_array($step)) {
                    continue;
                }
                $instruction = trim((string) ($step['instruction'] ?? ''));
                if ($instruction === '') {
                    continue;
                }
                $stepMeters = max(0.0, (float) ($step['distance'] ?? 0)) * 1000;
                $wayPoints = is_array($step['way_points'] ?? null) ? $step['way_points'] : [];
                $pointIdx = isset($wayPoints[0]) ? (int) $wayPoints[0] : null;
                $point = ($pointIdx !== null && isset($geoCoords[$pointIdx])) ? $geoCoords[$pointIdx] : null;
                $steps[] = [
                    'instruction' => mb_substr($instruction, 0, 300),
                    'distance_km' => round($stepMeters / 1000, 3),
                    'distance_m'  => (int) round($stepMeters),
                    'lat'         => $point ? round((float) $point[1], 6) : null,
                    'lng'         => $point ? round((float) $point[0], 6) : null
                ];
            }
        }

        $rotas[] = pl_rota_rota_normal(count($rotas) + 1, $coordsList, $steps, $distanceM, $durationS,
            'ors', 'OpenRouteService', $profileEffective);
    }

    return $rotas;
}

/**
 * Traduz o modificador de manobra do OSRM para português.
 */
function pl_rota_osrm_side(string $modifier): string
{
    return match ($modifier) {
        'left'         => 'esquerda',
        'right'        => 'direita',
        'slight left'  => 'esquerda suave',
        'slight right' => 'direita suave',
        'sharp left'   => 'esquerda acentuada',
        'sharp right'  => 'direita acentuada',
        'uturn'        => 'retorno',
        default        => ''
    };
}

/**
 * Gera uma instrução em português a partir da manobra do OSRM.
 */
function pl_rota_osrm_instruction(string $type, string $modifier, string $name): string
{
    $road = $name !== '' ? ' — ' . $name : '';
    $side = pl_rota_osrm_side($modifier);

    return match ($type) {
        'depart'    => 'Siga' . ($name !== '' ? ' pela ' . $name : '') . '.',
        'arrive'    => 'Chegue ao destino' . ($name !== '' ? ' (' . $name . ')' : '') . '.',
        'roundabout', 'rotary' => 'Na rotatória, siga pela saída indicada' . $road . '.',
        'merge'     => 'Entre na via' . $road . '.',
        'on ramp'   => 'Pegue o acesso' . $road . '.',
        'off ramp'  => 'Saia em direção ao acesso' . $road . '.',
        'fork'      => 'Mantenha-se' . ($side !== '' ? ' à ' . $side : '') . $road . '.',
        'new name', 'continue' => 'Continue' . $road . '.',
        'end of road' => $side !== '' ? 'No fim da via, vire à ' . $side . $road . '.' : 'Continue' . $road . '.',
        default     => $side !== '' ? 'Vire à ' . $side . $road . '.' : 'Siga em frente' . $road . '.'
    };
}

/**
 * Converte TODAS as rotas da resposta do OSRM (route/v1). Cada rota traz a
 * própria distância (m) e duração (s); as instruções são geradas das manobras.
 *
 * @return array<int, array>
 */
function pl_rota_parse_osrm(array $data, string $profileEffective = 'foot-walking'): array
{
    $routes = is_array($data['routes'] ?? null) ? $data['routes'] : [];
    $rotas = [];

    foreach ($routes as $route) {
        if (!is_array($route)) {
            continue;
        }
        $coordsList = pl_rota_geojson_para_latlng(
            is_array($route['geometry']['coordinates'] ?? null) ? $route['geometry']['coordinates'] : []
        );
        if (count($coordsList) < 2) {
            continue;
        }

        $distanceM = (int) round(max(0.0, (float) ($route['distance'] ?? 0)));
        $durationS = (int) round(max(0.0, (float) ($route['duration'] ?? 0)));

        $steps = [];
        $legs = is_array($route['legs'] ?? null) ? $route['legs'] : [];
        foreach ($legs as $leg) {
            if (!is_array($leg)) {
                continue;
            }
            $legSteps = is_array($leg['steps'] ?? null) ? $leg['steps'] : [];
            foreach ($legSteps as $step) {
                if (!is_array($step)) {
                    continue;
                }
                $maneuver = is_array($step['maneuver'] ?? null) ? $step['maneuver'] : [];
                $instruction = pl_rota_osrm_instruction(
                    (string) ($maneuver['type'] ?? ''),
                    (string) ($maneuver['modifier'] ?? ''),
                    trim((string) ($step['name'] ?? ''))
                );
                $location = is_array($maneuver['location'] ?? null) ? $maneuver['location'] : [];
                $distanceMStep = max(0.0, (float) ($step['distance'] ?? 0));
                $steps[] = [
                    'instruction' => mb_substr($instruction, 0, 300),
                    'distance_km' => round($distanceMStep / 1000, 3),
                    'distance_m'  => (int) round($distanceMStep),
                    'lat'         => isset($location[1]) ? round((float) $location[1], 6) : null,
                    'lng'         => isset($location[0]) ? round((float) $location[0], 6) : null
                ];
            }
        }

        $rotas[] = pl_rota_rota_normal(count($rotas) + 1, $coordsList, $steps, $distanceM, $durationS,
            'osrm', 'OSRM / OpenStreetMap', $profileEffective);
    }

    return $rotas;
}

/**
 * Rótulos derivados apenas das métricas recebidas. A primeira rota é a
 * preferência do roteador; entre as demais, a de menor duração é "mais rápida" e
 * a de menor distância é "mais curta". Com uma rota só, nenhum comparativo é dito.
 */
function pl_rota_classificar(array $rotas): array
{
    $total = count($rotas);
    if ($total === 0) {
        return $rotas;
    }
    $minDur = min(array_column($rotas, 'duration_s'));
    $minDist = min(array_column($rotas, 'distance_m'));

    foreach ($rotas as $i => $rota) {
        if ($i === 0) {
            $rotas[$i]['rotulo'] = $total > 1 ? 'Recomendada' : 'Trajeto calculado';
            $rotas[$i]['rotulo_kind'] = 'recommended';
            continue;
        }
        if ($rota['duration_s'] === $minDur && $rota['duration_s'] < $rotas[0]['duration_s']) {
            $rotas[$i]['rotulo'] = 'Mais rápida';
            $rotas[$i]['rotulo_kind'] = 'fastest';
        } elseif ($rota['distance_m'] === $minDist && $rota['distance_m'] < $rotas[0]['distance_m']) {
            $rotas[$i]['rotulo'] = 'Mais curta';
            $rotas[$i]['rotulo_kind'] = 'shortest';
        } else {
            $rotas[$i]['rotulo'] = 'Alternativa ' . ($i + 1);
            $rotas[$i]['rotulo_kind'] = 'alternative';
        }
    }
    return $rotas;
}

/**
 * Envelope da resposta: rotas[] + os campos de topo da rota preferida, que
 * js/navegacao.js continua lendo.
 */
function pl_rota_resposta(array $rotas, array $meta, int $alternativesRequested): array
{
    $rotas = pl_rota_classificar($rotas);
    $principal = $rotas[0];

    return array_merge($meta, [
        'coords'                 => $principal['coords'],
        'steps'                  => $principal['steps'],
        'distance_m'             => $principal['distance_m'],
        'distance_km'            => $principal['distance_km'],
        'duration_s'             => $principal['duration_s'],
        'duration_min'           => $principal['duration_min'],
        'rotas'                  => $rotas,
        'alternatives_requested' => $alternativesRequested,
        'alternatives_returned'  => count($rotas) - 1
    ]);
}

// ---------------------------------------------------------------------------
// Entrada
// ---------------------------------------------------------------------------
$body = get_request_data();
$pick = static function (array $keys) use ($body) {
    foreach ($keys as $k) {
        if (isset($body[$k]) && $body[$k] !== '') {
            return $body[$k];
        }
        if (isset($_GET[$k]) && $_GET[$k] !== '') {
            return $_GET[$k];
        }
    }
    return null;
};

$profile = strtolower(trim((string) ($pick(['profile', 'perfil']) ?? 'foot-walking')));
if (!in_array($profile, ['foot-walking', 'wheelchair'], true)) {
    $profile = 'foot-walking';
}

$originLat = $pick(['origin_lat', 'origem_lat', 'lat_origem']);
$originLng = $pick(['origin_lng', 'origem_lng', 'lng_origem']);
$destLat   = $pick(['dest_lat', 'destino_lat', 'lat_destino']);
$destLng   = $pick(['dest_lng', 'destino_lng', 'lng_destino']);

if (!pl_rota_valid_coord($originLat, $originLng) || !pl_rota_valid_coord($destLat, $destLng)) {
    pl_rota_fail('coordenadas_invalidas', 'As coordenadas de origem ou destino são inválidas.');
}

$originLat = (float) $originLat; $originLng = (float) $originLng;
$destLat   = (float) $destLat;   $destLng   = (float) $destLng;

// Sanidade territorial. A caixa antiga (-24.05…-23.10 × -46.72…-45.92) era o
// Alto Tietê: uma rota em Campinas, Santos ou Ribeirão Preto era recusada antes
// mesmo de consultar o roteador, que atende o mundo todo. Agora só coordenadas
// fora do Brasil são recusadas, e ficar fora do estado vira um aviso explícito
// na resposta — não um erro.
$inBrasil = static function (float $lat, float $lng): bool {
    return $lat >= -35.0 && $lat <= 6.0 && $lng >= -75.0 && $lng <= -30.0;
};
if (!$inBrasil($originLat, $originLng) || !$inBrasil($destLat, $destLng)) {
    pl_rota_fail('fora_do_brasil', 'A origem ou o destino está fora do Brasil.');
}

$inSaoPaulo = static function (float $lat, float $lng): bool {
    return $lat >= -25.5 && $lat <= -19.7 && $lng >= -53.4 && $lng <= -40.3;
};
$avisoRegiao = ($inSaoPaulo($originLat, $originLng) && $inSaoPaulo($destLat, $destLng))
    ? null
    : 'O trajeto sai do estado de São Paulo: a base de locais cadastrados cobre apenas o estado, e o traçado vem do roteador do OpenStreetMap.';

if (pl_rota_haversine_m($originLat, $originLng, $destLat, $destLng) < 10.0) {
    pl_rota_fail('sem_rota', 'Origem e destino estão praticamente no mesmo ponto.');
}

$orsKey = trim((string) ORS_API_KEY);
$useOrs = $orsKey !== '';

/* Nº de alternativas pedidas ao roteador, além da rota principal. O serviço pode
   devolver menos — nunca se inventa rota para completar cartão. */
$altRequested = (int) ($pick(['alternatives', 'alternativas']) ?? PL_ROTA_MAX_ALT);
$altRequested = max(0, min(PL_ROTA_MAX_ALT, $altRequested));

/* A chave do roteador nunca viaja para o navegador: só o servidor sabe se o
   perfil wheelchair existe ou não. */
$cacheKeyPara = static function (string $provedor, string $perfilUsado) use (
    $originLat, $originLng, $destLat, $destLng, &$altRequested
): string {
    return 'rota|v4|' . $provedor . '|' . $perfilUsado . '|alt' . $altRequested . '|'
        . round($originLat, 5) . ',' . round($originLng, 5) . '|'
        . round($destLat, 5) . ',' . round($destLng, 5);
};

/*
 * O perfil "wheelchair" só existe no OpenRouteService. Entregar o trajeto a pé do
 * OSRM no lugar dele seria dizer ao cadeirante que a rota foi otimizada para
 * cadeira de rodas quando não foi. A substituição só acontece por pedido
 * explícito do usuário (allow_foot_fallback=1) e continua rotulada como a pé.
 */
$allowFootFallback = (string) ($pick(['allow_foot_fallback']) ?? '') === '1';

$recusaWheelchair = static function (string $causa): void {
    pl_rota_fail(
        'perfil_indisponivel',
        'Não foi possível calcular a rota para cadeira de rodas: ' . $causa . ' '
            . 'Um trajeto a pé não equivale a ele e não seria otimizado para acessibilidade.',
        [
            'profile_requested'   => 'wheelchair',
            'fallback_disponivel' => 'foot-walking',
            'alternativa_oferecida' => [
                'profile' => 'foot-walking',
                'rotulo'  => 'Ver o trajeto a pé (não otimizado para cadeira de rodas)'
            ]
        ]
    );
};

if ($profile === 'wheelchair' && !$useOrs && !$allowFootFallback) {
    $recusaWheelchair('o roteador que trata esse perfil (OpenRouteService) não tem chave configurada no servidor.');
}

$effectiveProfile = ($profile === 'wheelchair' && !$useOrs) ? 'foot-walking' : $profile;

$notaAcessibilidade = static function (string $provedor) use ($profile): string {
    if ($profile === 'wheelchair' && $provedor === 'ors') {
        return 'Perfil "wheelchair" do OpenRouteService: o roteador evita barreiras mapeadas, mas as '
            . 'condições reais da calçada não foram verificadas em campo.';
    }
    if ($profile === 'wheelchair') {
        return 'O perfil "wheelchair" estava indisponível e você pediu mesmo assim o trajeto a pé. '
            . 'Ele foi calculado pelo OSRM para pedestres e NÃO é otimizado para cadeira de rodas.';
    }
    return 'Trajeto a pé calculado sobre a malha de pedestres do OpenStreetMap. A acessibilidade do '
        . 'percurso não foi verificada em campo.';
};

// ---------------------------------------------------------------------------
// Provedor 1: OpenRouteService v2 (quando há chave)
// ---------------------------------------------------------------------------
$result = null;
$firstFailure = null;

if ($useOrs) {
    $cacheKey = $cacheKeyPara('ors', $profile);
    $cached = pl_cache_get($cacheKey, PL_ROTA_CACHE_TTL);
    if (is_array($cached) && !empty($cached['rotas'])) {
        json_response(array_merge($cached, ['success' => true, 'cached' => true, 'aviso_regiao' => $avisoRegiao]));
    }

    pl_throttle_wait('rota_ors', 1.0);

    $corpo = [
        'coordinates'  => [[$originLng, $originLat], [$destLng, $destLat]],
        'instructions' => true,
        'language'     => 'pt',
        'units'        => 'km'
    ];
    if ($altRequested > 0) {
        /* target_count é o campo que as duas grafias do alternative_routes têm em
           comum; weight/skip_length/share_factor variam por versão e um corpo
           rejeitado custaria a rota inteira ao usuário. */
        $corpo['alternative_routes'] = ['target_count' => $altRequested];
    }

    $resp = pl_http_post_json(
        sprintf(PL_ORS_BASE, $profile),
        $corpo,
        ['Authorization: ' . $orsKey],
        10
    );

    // 400 com corpo válido tem uma causa conhecida: o serviço não aceitou a
    // pedida de alternativas. Refaz-se a chamada só do jeito que ele entende.
    if (!$resp['success'] && (int) $resp['http_code'] === 400 && $altRequested > 0) {
        error_log('[rota.php] ORS recusou alternative_routes; refazendo a pedido da rota principal');
        unset($corpo['alternative_routes']);
        $altRequested = 0;
        $cacheKey = $cacheKeyPara('ors', $profile);
        pl_throttle_wait('rota_ors', 1.0);
        $resp = pl_http_post_json(
            sprintf(PL_ORS_BASE, $profile),
            $corpo,
            ['Authorization: ' . $orsKey],
            10
        );
    }

    if ($resp['success']) {
        $rotas = pl_rota_parse_ors((array) $resp['data'], $profile);
        if ($rotas) {
            $result = pl_rota_resposta($rotas, [
                'provider'               => 'ors',
                'provider_label'         => 'OpenRouteService',
                'profile'                => $profile,
                'profile_effective'      => $profile,
                'accessibility_verified' => false,
                'accessibility_note'     => $notaAcessibilidade('ors'),
                'disclaimer'             => PL_ROTA_DISCLAIMER
            ], $altRequested);
        } else {
            $firstFailure = ['sem_rota', 'Nenhuma rota foi encontrada entre os pontos informados.'];
        }
    } else {
        $firstFailure = pl_rota_http_reason((int) $resp['http_code']);
        error_log('[rota.php] ORS indisponível: ' . ($resp['error'] ?? 'erro desconhecido'));
    }
}

// ---------------------------------------------------------------------------
// Provedor 2: OSRM público (sem chave) — sempre real, nunca simulada
// ---------------------------------------------------------------------------
if ($result === null) {
    /* Havia chave, mas o ORS falhou: o perfil "wheelchair" está prestes a cair para
       o roteador a pé. Sem o pedido explícito do usuário a requisição é recusada —
       o OSRM não sabe nada sobre cadeira de rodas. */
    if ($profile === 'wheelchair' && $effectiveProfile === 'wheelchair') {
        if (!$allowFootFallback) {
            $recusaWheelchair('o OpenRouteService respondeu ' . ($firstFailure[0] ?? 'indisponível') . '.');
        }
        $effectiveProfile = 'foot-walking';
    }

    $cacheKey = $cacheKeyPara('osrm', $effectiveProfile);
    $cached = pl_cache_get($cacheKey, PL_ROTA_CACHE_TTL);
    if (is_array($cached) && !empty($cached['rotas'])) {
        json_response(array_merge($cached, ['success' => true, 'cached' => true, 'aviso_regiao' => $avisoRegiao]));
    }

    pl_throttle_wait('rota_osrm', 1.0);

    $pair = sprintf(
        '%F,%F;%F,%F',
        $originLng, $originLat,
        $destLng, $destLat
    );
    $url = sprintf(PL_OSRM_BASE, $pair) . '?' . http_build_query([
        'overview'    => 'full',
        'geometries'  => 'geojson',
        'steps'       => 'true',
        /* alternatives=N: o OSRM público devolve no máximo N rotas extras e não
           garante nenhuma — por isso o painel mostra as que chegarem. */
        'alternatives' => $altRequested > 0 ? (string) $altRequested : 'false'
    ]);

    $resp = pl_http_get_json($url, [], 10);

    if ($resp['success']) {
        $osrmCode = (string) ($resp['data']['code'] ?? '');
        $rotas = $osrmCode === 'Ok' ? pl_rota_parse_osrm((array) $resp['data'], $effectiveProfile) : [];
        if ($rotas) {
            $result = pl_rota_resposta($rotas, [
                'provider'               => 'osrm',
                'provider_label'         => 'OSRM / OpenStreetMap',
                'profile'                => $profile,
                'profile_effective'      => $effectiveProfile,
                'accessibility_verified' => false,
                'accessibility_note'     => $notaAcessibilidade('osrm'),
                'disclaimer'             => PL_ROTA_DISCLAIMER,
                'fallback_reason'        => $firstFailure[0] ?? null
            ], $altRequested);
        } else {
            pl_rota_fail(
                'sem_rota',
                'Nenhuma rota a pé foi encontrada entre os pontos informados.',
                ['ors_error' => $firstFailure[0] ?? null]
            );
        }
    } else {
        [$reason, $message] = pl_rota_http_reason((int) $resp['http_code']);
        error_log('[rota.php] OSRM indisponível: ' . ($resp['error'] ?? 'erro desconhecido'));
        pl_rota_fail(
            $reason,
            $reason === 'falha_conexao' || $reason === 'api_indisponivel'
                ? 'Não foi possível calcular a rota neste momento. Verifique sua conexão ou tente novamente.'
                : $message,
            ['ors_error' => $firstFailure[0] ?? null]
        );
    }
}

/* Só entra em cache uma resposta com rotas reais e geometria: uma falha ou uma
   resposta vazia não pode servir de resposta para os próximos usuários. */
if (!empty($result['rotas']) && !empty($result['coords'])) {
    pl_cache_set($cacheKey, $result, PL_ROTA_CACHE_TTL);
}

json_response(array_merge($result, ['success' => true, 'cached' => false, 'aviso_regiao' => $avisoRegiao]));
