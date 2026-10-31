<?php
/**
 * Passe Livre Suzano — Cliente HTTP (cURL), cache em arquivo e throttle
 * para os proxies de APIs externas (geocodificação, rotas, OpenStreetMap).
 */

require_once __DIR__ . '/_db_restore.php';

if (!function_exists('pl_cache_dir')) {
    /**
     * Diretório de cache/locks dos proxies (api/cache).
     */
    function pl_cache_dir(): string
    {
        $dir = __DIR__ . '/cache';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        return $dir;
    }
}

if (!function_exists('pl_cache_path')) {
    function pl_cache_path(string $key): string
    {
        return pl_cache_dir() . '/' . hash('sha256', $key) . '.cache.json';
    }
}

if (!function_exists('pl_cache_get')) {
    /**
     * Lê um valor de cache; retorna null quando expirado ou inexistente.
     */
    function pl_cache_get(string $key, int $ttlSeconds = 900)
    {
        if ($ttlSeconds <= 0) {
            return null;
        }
        $file = pl_cache_path($key);
        if (!is_file($file)) {
            return null;
        }
        $raw = @file_get_contents($file);
        if ($raw === false) {
            return null;
        }
        $payload = json_decode($raw, true);
        if (!is_array($payload) || !array_key_exists('expires_at', $payload)) {
            return null;
        }
        if ((int) $payload['expires_at'] < time()) {
            return null;
        }
        return $payload['value'] ?? null;
    }
}

if (!function_exists('pl_cache_set')) {
    /**
     * Grava um valor de cache com expiração. Falhas de escrita são silenciosas.
     */
    function pl_cache_set(string $key, $value, int $ttlSeconds = 900): void
    {
        if ($ttlSeconds <= 0) {
            return;
        }
        $file = pl_cache_path($key);
        $tmp  = $file . '.' . getmypid() . '.tmp';
        $data = json_encode([
            'key'        => $key,
            'expires_at' => time() + $ttlSeconds,
            'value'      => $value
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (@file_put_contents($tmp, $data, LOCK_EX) === false) {
            @unlink($tmp);
            return;
        }
        @rename($tmp, $file);
        @chmod($file, 0644);
    }
}

if (!function_exists('pl_throttle_wait')) {
    /**
     * Serialize chamadas a um mesmo provedor, garantindo no mínimo
     * $minIntervalSeconds entre requisições (ex.: 1 s para o Nominatim público).
     */
    function pl_throttle_wait(string $key, float $minIntervalSeconds = 1.0): void
    {
        $lock = pl_cache_dir() . '/' . hash('sha256', $key) . '.lock';
        $fh = @fopen($lock, 'c+b');
        if (!$fh) {
            return;
        }
        if (!@flock($fh, LOCK_EX)) {
            fclose($fh);
            return;
        }
        $last = (float) @file_get_contents($lock);
        $wait = $minIntervalSeconds - (microtime(true) - $last);
        if ($wait > 0) {
            usleep((int) round($wait * 1000000));
        }
        ftruncate($fh, 0);
        fwrite($fh, (string) microtime(true));
        fflush($fh);
        flock($fh, LOCK_UN);
        fclose($fh);
    }
}

if (!function_exists('pl_rate_limit_hit')) {
    /**
     * Limite simples de chamadas por chave em uma janela de tempo.
     * Usa o mesmo diretório de cache; se ele estiver indisponível, não bloqueia o usuário.
     */
    function pl_rate_limit_hit(string $key, int $maxCalls, int $windowSeconds = 60): bool
    {
        if ($maxCalls <= 0) {
            return true;
        }
        $file = pl_cache_dir() . '/' . hash('sha256', 'rate|' . $key) . '.rate.json';
        $fh = @fopen($file, 'c+b');
        if (!$fh) {
            return true;
        }
        if (!@flock($fh, LOCK_EX)) {
            fclose($fh);
            return true;
        }
        $now   = time();
        $state = json_decode((string) stream_get_contents($fh), true);
        if (!is_array($state) || (int) ($state['reset_at'] ?? 0) <= $now) {
            $state = ['count' => 0, 'reset_at' => $now + $windowSeconds];
        }
        $state['count'] = (int) $state['count'] + 1;
        $allowed = $state['count'] <= $maxCalls;
        ftruncate($fh, 0);
        rewind($fh);
        fwrite($fh, (string) json_encode($state));
        fflush($fh);
        flock($fh, LOCK_UN);
        fclose($fh);
        return $allowed;
    }
}

if (!function_exists('pl_proxy_guard')) {
    /**
     * Guarda dos proxies de APIs externas: opcionalmente exige sessão válida
     * (401) e aplica limite por sessão/IP (429). Encerra a execução com JSON
     * nos dois casos.
     */
    function pl_proxy_guard(string $endpoint, int $maxCalls = 60, int $windowSeconds = 60, bool $requireAuth = true): array
    {
        $user = get_auth_user();
        if (!$user && $requireAuth) {
            json_error('Sessão válida obrigatória para consultar este serviço.', 401, [
                'code' => 'sessao_obrigatoria'
            ]);
        }
        $identity = ($user ? (int) ($user['id'] ?? 0) : 'anon') . '|' . ip2long((string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'));
        if (!pl_rate_limit_hit($endpoint . '|' . $identity, $maxCalls, $windowSeconds)) {
            json_error('Limite de consultas atingido. Aguarde um minuto e tente de novo.', 429, [
                'code'        => 'limite_excedido',
                'retry_after' => $windowSeconds
            ]);
        }
        return $user ?? [];
    }
}

if (!function_exists('pl_http_redact_url')) {
    /**
     * Oculta chaves/tokens do log de erros (apiKey=..., key=..., token=...).
     */
    function pl_http_redact_url(string $url): string
    {
        return preg_replace('/([?&](?:apiKey|api_key|apikey|key|token|access_token|secret)=)[^&#]*/i', '$1***', $url);
    }
}

if (!function_exists('pl_http_request_json')) {
    /**
     * Requisição HTTP com cURL e decodificação JSON.
     * Nunca lança exceção: devolve sempre o envelope de resultado.
     *
     * @return array{success:bool,http_code:int,data:?array,error:?string}
     */
    function pl_http_request_json(string $method, string $url, array $headers = [], $payload = null, int $timeout = 6): array
    {
        $fail = static function (string $message, int $httpCode = 0): array {
            return ['success' => false, 'http_code' => $httpCode, 'data' => null, 'error' => $message];
        };

        if (!function_exists('curl_init')) {
            return $fail('A extensão cURL não está disponível no servidor.');
        }
        if (!preg_match('#^https?://#i', $url)) {
            return $fail('URL externa inválida.');
        }

        $method  = strtoupper($method) === 'POST' ? 'POST' : 'GET';
        $timeout = max(1, min($timeout, 15));

        $headers[] = 'Accept: application/json';

        $ch = curl_init();
        $options = [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING       => '',
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
            CURLOPT_CONNECTTIMEOUT => $timeout,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_PROTOCOLS      => stripos($url, 'https://') === 0 ? CURLPROTO_HTTPS : CURLPROTO_HTTPS | CURLPROTO_HTTP,
            CURLOPT_REDIR_PROTOCOLS => stripos($url, 'https://') === 0 ? CURLPROTO_HTTPS : CURLPROTO_HTTPS | CURLPROTO_HTTP,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_USERAGENT      => 'PasseLivreSuzano/1.0 (projeto municipal de acessibilidade)'
        ];

        if ($method === 'POST') {
            $options[CURLOPT_POST] = true;
            if (is_array($payload)) {
                $options[CURLOPT_POSTFIELDS] = json_encode($payload, JSON_UNESCAPED_UNICODE);
                $headers[] = 'Content-Type: application/json';
                $options[CURLOPT_HTTPHEADER] = $headers;
            } elseif ($payload !== null) {
                $options[CURLOPT_POSTFIELDS] = (string) $payload;
            }
        }

        curl_setopt_array($ch, $options);
        $body = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $curlErrNo = curl_errno($ch);
        $curlErrMsg = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            error_log('[pl_http_request_json] falha cURL ' . $curlErrNo . ': ' . $curlErrMsg . ' em ' . pl_http_redact_url($url));
            return $fail('Não foi possível contactar o serviço externo.', $httpCode);
        }
        if ($httpCode < 200 || $httpCode >= 300) {
            error_log('[pl_http_request_json] HTTP ' . $httpCode . ' em ' . pl_http_redact_url($url));
            return $fail('O serviço externo respondeu com erro HTTP ' . $httpCode . '.', $httpCode);
        }

        $decoded = json_decode((string) $body, true);
        if (!is_array($decoded)) {
            error_log('[pl_http_request_json] resposta não é JSON válido: ' . substr((string) $body, 0, 200));
            return $fail('O serviço externo devolveu uma resposta inesperada.', $httpCode);
        }

        return ['success' => true, 'http_code' => $httpCode, 'data' => $decoded, 'error' => null];
    }
}

if (!function_exists('pl_http_get_json')) {
    /**
     * GET JSON de uma API externa com timeout curto (5 a 8 s por padrão).
     *
     * @return array{success:bool,http_code:int,data:?array,error:?string}
     */
    function pl_http_get_json(string $url, array $headers = [], int $timeout = 6): array
    {
        return pl_http_request_json('GET', $url, $headers, null, $timeout);
    }
}

if (!function_exists('pl_http_post_json')) {
    /**
     * POST de um corpo JSON para uma API externa.
     *
     * @return array{success:bool,http_code:int,data:?array,error:?string}
     */
    function pl_http_post_json(string $url, array $payload, array $headers = [], int $timeout = 6): array
    {
        return pl_http_request_json('POST', $url, $headers, $payload, $timeout);
    }
}
