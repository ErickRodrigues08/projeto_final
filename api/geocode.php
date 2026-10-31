<?php
/**
 * Passe Livre Suzano — Proxy de Geocodificação (busca de endereço)
 *
 * Ações:
 *   ?action=search&q=<texto>          -> lista de lugares {name, address, lat, lng}
 *   ?action=reverse&lat=<f>&lng=<f>   -> endereço {name, address, lat, lng}
 *
 * A chave do provedor fica apenas no servidor (.env). Sem chave ou com falha
 * da API externa, responde success=false para que o JS use a lista estática.
 */

require_once __DIR__ . '/_db_restore.php';
require_once __DIR__ . '/http.php';

// Centro do estado de São Paulo e caixa de preferência do estado inteiro.
// A viewbox do Nominatim é apenas preferência de ordenação (não filtro): um
// endereço fora dela continua retornável. A caixa antiga cobria só o Alto Tietê
// e fazia endereço de outra cidade perder para um homônimo de Suzano.
if (!defined('PL_GEO_CENTER_LAT')) define('PL_GEO_CENTER_LAT', -22.6000);
if (!defined('PL_GEO_CENTER_LNG')) define('PL_GEO_CENTER_LNG', -46.8000);
if (!defined('PL_GEO_VIEWBOX')) define('PL_GEO_VIEWBOX', '-53.2000,-19.9000,-40.4000,-25.4000');
// Raio de preferência do Geoapify (metros) em torno do centro usado
if (!defined('PL_GEO_BIAS_METERS')) define('PL_GEO_BIAS_METERS', 300000);
if (!defined('PL_GEO_CACHE_TTL')) define('PL_GEO_CACHE_TTL', 86400);

$action = $_GET['action'] ?? 'search';

// Busca de endereco e aberta a visitantes; limite menor por IP (429 em JSON).
pl_proxy_guard('geocode', 20, 60, false);

/**
 * Monta uma linha de endereço legível a partir das partes conhecidas.
 */
function pl_geo_address(array $parts, string $fallback = ''): string
{
    $parts = array_values(array_filter(array_map('trim', $parts), static fn($v) => $v !== ''));
    if (!$parts) {
        return $fallback;
    }
    return implode(', ', $parts);
}

/**
 * Reduz o registro do provedor ao formato usado pelo front-end.
 */
function pl_geo_normalize(array $item, string $provider): array
{
    $lat = $item['lat'] ?? null;
    $lng = $item['lon'] ?? ($item['lng'] ?? null);
    if (!is_numeric($lat) || !is_numeric($lng)) return [];
    $lat = (float) $lat;
    $lng = (float) $lng;
    if (!is_finite($lat) || !is_finite($lng) || abs($lat) > 90 || abs($lng) > 180) return [];

    $addr = is_array($item['address'] ?? null) ? array_filter($item['address'], 'is_string') : [];
    foreach (['name', 'display_name', 'housenumber', 'street', 'suburb', 'district', 'neighbourhood', 'city', 'town', 'county', 'state', 'postcode', 'formatted'] as $field) {
        if (isset($item[$field]) && !is_string($item[$field])) return [];
    }

    if ($provider === 'nominatim') {
        $house    = trim((string) ($addr['house_number'] ?? ''));
        $road     = trim((string) ($addr['road'] ?? ($addr['pedestrian'] ?? ($addr['footway'] ?? ''))));
        $suburb   = trim((string) ($addr['suburb'] ?? ($addr['neighbourhood'] ?? ($addr['city_district'] ?? ''))));
        $city     = trim((string) ($addr['city'] ?? ($addr['town'] ?? ($addr['municipality'] ?? ($addr['village'] ?? '')))));
        $state    = trim((string) ($addr['state'] ?? ''));
        $postcode = trim((string) ($addr['postcode'] ?? ''));
        $street   = implode(', ', array_filter([$road, $house], static fn($v) => $v !== ''));
        $name     = trim((string) ($item['name'] ?? ''));
        if ($name === '') {
            $name = $street !== '' ? $street : ($suburb !== '' ? $suburb : '');
        }
        if ($name === '') {
            $name = trim(explode(',', (string) ($item['display_name'] ?? ''))[0]);
        }
        $address = pl_geo_address([
            $street,
            $suburb,
            $city,
            $state,
            $postcode
        ], (string) ($item['display_name'] ?? ''));
    } else {
        // Geoapify
        $house    = trim((string) ($item['housenumber'] ?? ''));
        $road     = trim((string) ($item['street'] ?? ''));
        $suburb   = trim((string) ($item['suburb'] ?? ($item['district'] ?? ($item['neighbourhood'] ?? ''))));
        $city     = trim((string) ($item['city'] ?? ($item['town'] ?? ($item['county'] ?? ''))));
        $state    = trim((string) ($item['state'] ?? ''));
        $postcode = trim((string) ($item['postcode'] ?? ''));
        $name     = trim((string) ($item['name'] ?? ''));
        $street   = trim(implode(' ', array_filter([$house, $road], static fn($v) => $v !== '')));
        if ($name === '') {
            $name = $street;
        }
        $address = pl_geo_address([
            $street,
            $suburb,
            $city,
            $state,
            $postcode
        ], trim((string) ($item['formatted'] ?? ($item['address'] ?? ''))));
    }

    return [
        'name'    => mb_substr($name, 0, 120),
        'address' => mb_substr($address, 0, 200),
        'lat'     => round($lat, 6),
        'lng'     => round($lng, 6),
        'parts'   => [
            'road'         => mb_substr($road, 0, 120),
            'house_number' => mb_substr($house, 0, 20),
            'suburb'       => mb_substr($suburb, 0, 80),
            'city'         => mb_substr($city, 0, 80),
            'state'        => mb_substr($state, 0, 80),
            'postcode'     => mb_substr($postcode, 0, 16),
        ],
    ];
}

/**
 * Cabeçalho User-Agent identificando o projeto (exigência do Nominatim público).
 */
function pl_geo_user_agent(): string
{
    $contact = trim((string) pl_get_env('NOMINATIM_CONTACT', MAIL_FROM));
    if (!filter_var($contact, FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n]/', $contact)) {
        json_error('O serviço de endereço precisa de um contato válido configurado pelo responsável.', 503);
    }
    return 'PasseLivreSuzano/1.0 (contato: ' . $contact . ')';
}

function pl_geo_nominatim_base(): string
{
    $base = rtrim((string) pl_get_env('NOMINATIM_URL', 'https://nominatim.openstreetmap.org'), '/');
    $parts = parse_url($base);
    if (!filter_var($base, FILTER_VALIDATE_URL) || !is_array($parts)
        || ($parts['scheme'] ?? '') !== 'https' || empty($parts['host'])
        || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
        json_error('O serviço de endereço não está configurado corretamente.', 503);
    }
    return $base;
}

function pl_geo_nominatim_request(string $url): array
{
    $headers = ['User-Agent: ' . pl_geo_user_agent()];
    $key = 'nominatim|raw|' . $url;
    $cached = pl_cache_get($key, PL_GEO_CACHE_TTL);
    if (is_array($cached)) return $cached;

    // O lock cobre também o cURL; liberar antes permitiria chamadas concorrentes.
    $lock = @fopen(pl_cache_dir() . '/' . hash('sha256', 'geocode_nominatim') . '.lock', 'c+b');
    if (!$lock) json_error('O serviço de endereço está temporariamente indisponível.', 503);
    if (!flock($lock, LOCK_EX | LOCK_NB)) {
        fclose($lock);
        header('Retry-After: 1');
        json_error('Aguarde um segundo antes de consultar outro endereço.', 429);
    }

    try {
        $cached = pl_cache_get($key, PL_GEO_CACHE_TTL);
        if (is_array($cached)) return $cached;
        $last = (float) stream_get_contents($lock);
        if (microtime(true) - $last < 1.0) {
            header('Retry-After: 1');
            json_error('Aguarde um segundo antes de consultar outro endereço.', 429);
        }
        rewind($lock);
        if (!ftruncate($lock, 0) || fwrite($lock, (string) microtime(true)) === false || !fflush($lock)) {
            json_error('O serviço de endereço está temporariamente indisponível.', 503);
        }
        $resp = pl_http_get_json($url, $headers, 8);
        rewind($lock);
        if (!ftruncate($lock, 0) || fwrite($lock, (string) microtime(true)) === false || !fflush($lock)) {
            json_error('O serviço de endereço está temporariamente indisponível.', 503);
        }
        if ($resp['success']) pl_cache_set($key, $resp, PL_GEO_CACHE_TTL);
        return $resp;
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

/**
 * Viewbox (lng1,lat1,lng2,lat2) em torno de um ponto, usada só como viés de
 * ordenação. O recorte nunca é menor que o estado de São Paulo para o padrão.
 */
function pl_geo_viewbox_ao_redor(float $lat, float $lng, int $metros): string
{
    $deltaLat = $metros / 111000.0;
    $deltaLng = $metros / max(1.0, 111000.0 * cos(deg2rad($lat)));
    return sprintf(
        '%F,%F,%F,%F',
        $lng - $deltaLng,
        $lat + $deltaLat,
        $lng + $deltaLng,
        $lat - $deltaLat
    );
}

if ($action === 'search') {
    $q = trim((string) ($_GET['q'] ?? ''));
    // Textos com acento podem chegar em ISO-8859-1 (formulários no Windows). Sem
    // converter, o preg abaixo com o modificador /u falha e devolve NULL, e a
    // busca inteira era rejeitada como "menor que 3 caracteres".
    if ($q !== '' && !mb_check_encoding($q, 'UTF-8')) {
        $q = mb_convert_encoding($q, 'UTF-8', 'ISO-8859-1');
    }
    $q = preg_replace('/\s+/u', ' ', $q) ?? $q;
    $q = trim($q);
    if (mb_strlen($q) < 3) {
        json_error('Informe ao menos 3 caracteres para a busca de endereço.');
    }
    if (mb_strlen($q) > 120) {
        $q = mb_substr($q, 0, 120);
    }
    $limit = max(1, min(8, (int) ($_GET['limit'] ?? 6)));

    // Viés opcional: quando o cliente informa a posição confirmada (GPS ou ponto
    // escolhido no mapa), a ordenação prefere endereços perto dela — mas dentro
    // do estado inteiro, não de uma única cidade.
    $biasLat = filter_input(INPUT_GET, 'lat', FILTER_VALIDATE_FLOAT);
    $biasLng = filter_input(INPUT_GET, 'lng', FILTER_VALIDATE_FLOAT);
    $temBias = ($biasLat !== false && $biasLng !== false && $biasLat !== null && $biasLng !== null
        && $biasLat >= -35.0 && $biasLat <= 6.0 && $biasLng >= -75.0 && $biasLng <= -30.0);

    $centroBiasLat = $temBias ? $biasLat : PL_GEO_CENTER_LAT;
    $centroBiasLng = $temBias ? $biasLng : PL_GEO_CENTER_LNG;
    $metrosBias    = $temBias ? 120000 : PL_GEO_BIAS_METERS;
    $viewbox       = $temBias
        ? pl_geo_viewbox_ao_redor($centroBiasLat, $centroBiasLng, $metrosBias)
        : PL_GEO_VIEWBOX;

    $autocomplete = ($_GET['autocomplete'] ?? '') === '1';
    $provider = (GEOCODER_PROVIDER === 'geoapify' || $autocomplete) && GEOAPIFY_KEY !== '' ? 'geoapify' : 'nominatim';
    $nominatimBase = pl_geo_nominatim_base();
    if ($autocomplete && $provider === 'nominatim' && strtolower((string) parse_url($nominatimBase, PHP_URL_HOST)) === 'nominatim.openstreetmap.org') {
        json_response([
            'success' => false, 'results' => [], 'code' => 'autocomplete_nao_configurado',
            'mensagem' => 'Sugestões externas indisponíveis. Use os locais cadastrados; o responsável precisa configurar um provedor que permita autocomplete.'
        ]);
    }
    $cacheKey = 'geocode|v4|search|' . $provider . '|' . $nominatimBase . '|' . $limit . '|'
        . round($centroBiasLat, 3) . ',' . round($centroBiasLng, 3) . '|' . mb_strtolower($q);

    $cached = pl_cache_get($cacheKey, PL_GEO_CACHE_TTL);
    if (is_array($cached)) {
        json_response(['success' => true, 'provider' => $provider, 'cached' => true, 'results' => $cached]);
    }

    if ($provider === 'geoapify') pl_throttle_wait('geocode_' . $provider, 1.0);

    if ($provider === 'geoapify') {
        $url = 'https://api.geoapify.com/v1/geocode/search?' . http_build_query([
            'text'   => $q,
            'lang'   => 'pt',
            'limit'  => $limit,
            'bias'   => $centroBiasLat . ',' . $centroBiasLng . ',' . $metrosBias,
            'filter' => 'countrycode.br',
            'apiKey' => GEOAPIFY_KEY
        ]);
        $resp = pl_http_get_json($url, [], 8);
        $raw  = $resp['data']['features'] ?? null;
        $items = [];
        if (is_array($raw)) {
            foreach ($raw as $f) {
                $props = is_array($f['properties'] ?? null) ? $f['properties'] : $f;
                if (isset($f['geometry']['coordinates'])) {
                    $props['lon'] = (float) $f['geometry']['coordinates'][0];
                    $props['lat'] = (float) $f['geometry']['coordinates'][1];
                }
                $items[] = $props;
            }
        }
    } else {
        $url = $nominatimBase . '/search?' . http_build_query([
            'q'               => $q,
            'format'          => 'jsonv2',
            'addressdetails'  => 1,
            'accept-language' => 'pt-BR,pt',
            'countrycodes'    => 'br',
            'limit'           => $limit,
            'viewbox'         => $viewbox
        ]);
        $resp = pl_geo_nominatim_request($url);
        $items = is_array($resp['data'] ?? null) ? $resp['data'] : [];
    }

    if (!$resp['success']) {
        error_log('[geocode search] provedor ' . $provider . ' indisponivel: ' . $resp['error']);
        // A interface deve dizer que não conseguiu localizar o endereço; antes ela
        // silenciava e mantinha a lista fixa de Suzano, parecerndo que a busca
        // tinha encontrado a cidade.
        json_response([
            'success'  => false,
            'provider' => $provider,
            'results'  => [],
            'fallback' => 'estatico',
            'motivo'   => 'provedor_indisponivel',
            'mensagem' => 'Não foi possível localizar esse endereço agora. Escolha um ponto no mapa ou tente novamente.'
        ]);
    }

    $results = [];
    $seen = [];
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        $one = pl_geo_normalize($item, $provider);
        if (!$one) {
            continue;
        }
        $sig = mb_strtolower($one['name']) . '|' . $one['lat'] . '|' . $one['lng'];
        if (isset($seen[$sig])) {
            continue;
        }
        $seen[$sig] = true;
        $results[] = $one;
    }
    $results = array_slice($results, 0, $limit);

    // Cache também o "zero resultado" para não repetir a chamada externa a cada tecla
    pl_cache_set($cacheKey, $results, PL_GEO_CACHE_TTL);

    json_response([
        'success'  => true,
        'provider' => $provider,
        'cached'   => false,
        'results'  => $results,
        'bias'     => ['lat' => round($centroBiasLat, 5), 'lng' => round($centroBiasLng, 5), 'viewbox' => $viewbox]
    ]);
}

if ($action === 'reverse') {
    $lat = filter_input(INPUT_GET, 'lat', FILTER_VALIDATE_FLOAT);
    $lng = filter_input(INPUT_GET, 'lng', FILTER_VALIDATE_FLOAT);
    if ($lat === false || $lng === false || $lat === null || $lng === null
        || !is_finite($lat) || !is_finite($lng) || abs($lat) > 90 || abs($lng) > 180) {
        json_error('Coordenadas inválidas.');
    }
    // Sanidade: aceita apenas uma faixa ampla que cobre o Brasil
    if ($lat < -35.0 || $lat > 6.0 || $lng < -75.0 || $lng > -30.0) {
        json_error('Coordenadas fora da área atendida.');
    }

    $provider = GEOCODER_PROVIDER === 'geoapify' && GEOAPIFY_KEY !== '' ? 'geoapify' : 'nominatim';
    $nominatimBase = pl_geo_nominatim_base();
    $cacheKey = 'geocode|v3|reverse|' . $provider . '|' . $nominatimBase . '|' . round($lat, 5) . '|' . round($lng, 5);

    $cached = pl_cache_get($cacheKey, PL_GEO_CACHE_TTL);
    if (is_array($cached) && isset($cached['lat'])) {
        json_response(['success' => true, 'provider' => $provider, 'cached' => true, 'results' => [$cached]]);
    }

    if ($provider === 'geoapify') pl_throttle_wait('geocode_' . $provider, 1.0);

    if ($provider === 'geoapify') {
        $url = 'https://api.geoapify.com/v1/geocode/reverse?' . http_build_query([
            'lat'   => $lat,
            'lon'   => $lng,
            'lang'  => 'pt',
            'apiKey' => GEOAPIFY_KEY
        ]);
        $resp  = pl_http_get_json($url, [], 8);
        $raw   = $resp['data']['features'] ?? [];
        $items = [];
        foreach ((array) $raw as $f) {
            $props = is_array($f['properties'] ?? null) ? $f['properties'] : $f;
            if (isset($f['geometry']['coordinates'])) {
                $props['lon'] = (float) $f['geometry']['coordinates'][0];
                $props['lat'] = (float) $f['geometry']['coordinates'][1];
            }
            $items[] = $props;
        }
    } else {
        $url = $nominatimBase . '/reverse?' . http_build_query([
            'lat'              => $lat,
            'lon'              => $lng,
            'zoom'             => 18,
            'format'           => 'jsonv2',
            'addressdetails'   => 1,
            'accept-language'  => 'pt-BR,pt'
        ]);
        $resp  = pl_geo_nominatim_request($url);
        $items = is_array($resp['data'] ?? null) ? [$resp['data']] : [];
    }

    if (!$resp['success']) {
        error_log('[geocode reverse] provedor ' . $provider . ' indisponivel: ' . $resp['error']);
        $limited = $resp['http_code'] === 429;
        if ($limited) header('Retry-After: 1');
        json_error('Coordenadas recebidas, mas não foi possível identificar o endereço agora.', $limited ? 429 : 502, [
            'provider' => $provider, 'results' => [], 'code' => 'geocodificacao_indisponivel'
        ]);
    }

    $one = [];
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        $normalized = pl_geo_normalize($item, $provider);
        if ($normalized && trim($normalized['address']) !== '') {
            // O provedor devolve o ponto real; mantém a coordenada pedida pelo cliente
            $normalized['lat'] = round($lat, 6);
            $normalized['lng'] = round($lng, 6);
            $one = $normalized;
            break;
        }
    }

    if (!$one) {
        json_error('Coordenadas recebidas, mas nenhum endereço utilizável foi encontrado.', 404, [
            'provider' => $provider, 'results' => [], 'code' => 'endereco_nao_encontrado'
        ]);
    }
    pl_cache_set($cacheKey, $one, PL_GEO_CACHE_TTL);

    json_response([
        'success'  => true,
        'provider' => $provider,
        'cached'   => false,
        'results'  => $one ? [$one] : []
    ]);
}

json_error('Ação de geocodificação não suportada.', 404);
