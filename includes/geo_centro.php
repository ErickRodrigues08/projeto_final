<?php
/**
 * Passe Livre — Helpers geográficos compartilhados pela busca de locais
 *
 * Existe um único critério de "centro de busca" no projeto: coordenadas
 * informadas pelo cliente e dentro do Brasil. api/locais.php, api/map-places.php
 * e as páginas de resultado usam estas funções para que a lista e o mapa nunca
 * resolvam a região de forma diferente.
 */

if (!defined('PL_LOCAIS_RAIO_PADRAO_KM')) {
    define('PL_LOCAIS_RAIO_PADRAO_KM', 10.0);
}
if (!defined('PL_LOCAIS_RAIO_MAX_KM')) {
    // 60 km cobre uma região metropolitana inteira sem virar varredura do estado
    define('PL_LOCAIS_RAIO_MAX_KM', 60.0);
}
if (!defined('PL_LOCAIS_MAX_RESULTADOS')) {
    // Teto único para a lista e para os marcadores do mapa. Antes o mapa parava
    // em 30 e a lista não tinha limite: numa região com mais de 30 locais o mapa
    // desenhava um subconjunto diferente do que a lista mostrava.
    define('PL_LOCAIS_MAX_RESULTADOS', 60);
}

if (!function_exists('pl_geo_haversine_sql')) {
    /** Expressão Haversine (km) com três placeholders de lat/lng/lat. */
    function pl_geo_haversine_sql(): string
    {
        return "(6371 * ACOS(
                COS(RADIANS(?)) * COS(RADIANS(lat)) *
                COS(RADIANS(lng) - RADIANS(?)) +
                SIN(RADIANS(?)) * SIN(RADIANS(lat))
              ))";
    }
}

if (!function_exists('pl_geo_centro_valido')) {
    /**
     * Centro geográfico da busca: `['ok' => bool, 'lat' => ?float, 'lng' => ?float]`.
     *
     * Aceita vírgula decimal (teclado pt-BR) e rejeita tudo fora da faixa usada
     * como sanidade em api/geocode.php. Antes cada endpoint tinha um "padrão
     * Suzano" embutido, o que fazia uma busca em outra cidade responder como se
     * o usuário estivesse na cidade da seed.
     */
    function pl_geo_centro_valido($lat, $lng): array
    {
        $norm = static function ($v) {
            if (is_string($v)) {
                $v = trim($v);
                if ($v !== '' && strpos($v, ',') !== false && stripos($v, 'e') === false) {
                    $v = str_replace(',', '.', $v);
                }
            }
            return $v;
        };

        $lat = filter_var($norm($lat), FILTER_VALIDATE_FLOAT);
        $lng = filter_var($norm($lng), FILTER_VALIDATE_FLOAT);
        if ($lat === false || $lng === false || $lat === null || $lng === null) {
            return ['ok' => false, 'lat' => null, 'lng' => null];
        }
        if ($lat < -35.0 || $lat > 6.0 || $lng < -75.0 || $lng > -30.0) {
            return ['ok' => false, 'lat' => null, 'lng' => null];
        }
        return ['ok' => true, 'lat' => (float)$lat, 'lng' => (float)$lng];
    }
}

if (!function_exists('pl_geo_raio_km')) {
    /** Raio pedido pelo cliente, sempre dentro de 0,5 km a 60 km. */
    function pl_geo_raio_km($raio): float
    {
        $raio = filter_var($raio, FILTER_VALIDATE_FLOAT);
        if ($raio === false || $raio === null || $raio <= 0) {
            return PL_LOCAIS_RAIO_PADRAO_KM;
        }
        return round(max(0.5, min(PL_LOCAIS_RAIO_MAX_KM, (float)$raio)), 1);
    }
}

if (!function_exists('pl_geo_distancia_km')) {
    /**
     * Distância em linha reta (km). Usada quando o cliente salva uma rota sem o
     * traçado calculado, para não inventar um valor de outra cidade.
     */
    function pl_geo_distancia_km(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a    = sin($dLat / 2) ** 2
              + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        return round(6371.0 * 2 * asin(min(1.0, sqrt($a))), 2);
    }
}

if (!function_exists('pl_geo_municipio_normalizado')) {
    /** Nome de município pronto para igualdade em `locais.municipio`. */
    function pl_geo_municipio_normalizado($municipio): ?string
    {
        $m = trim((string)$municipio);
        if ($m === '' || strcasecmp($m, 'all') === 0) {
            return null;
        }
        return mb_substr($m, 0, 120);
    }
}
