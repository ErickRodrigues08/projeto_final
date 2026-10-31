<?php
/**
 * Passe Livre Suzano — Proxy de consulta de CEP (ViaCEP)
 *
 * Ação:
 *   ?cep=08674010  -> { cep, street, complement, suburb, city, state }
 *
 * A consulta externa é feita pelo servidor (com cache e limite por IP),
 * evitando chamadas diretas do navegador e repetição de requisições.
 */

require_once __DIR__ . '/_db_restore.php';
require_once __DIR__ . '/http.php';

pl_proxy_guard('cep', 30, 60, false);

$raw = trim((string) ($_GET['cep'] ?? ''));
$digits = preg_replace('/\D+/', '', $raw);

if (strlen($digits) !== 8) {
    json_error('Informe um CEP válido com 8 dígitos.', 400);
}

$formatted = substr($digits, 0, 5) . '-' . substr($digits, 5, 3);
$cacheKey = 'cep|v1|' . $digits;

$cached = pl_cache_get($cacheKey, 604800);
if (is_array($cached) && isset($cached['cep'])) {
    json_response(['success' => true, 'cached' => true] + $cached);
}

pl_throttle_wait('cep_viacep', 0.35);

$contact = MAIL_FROM;
$userAgent = 'PasseLivreSuzano/1.0 (' .
    ($contact !== '' ? 'contato: ' . $contact . '; ' : '') .
    'consulta de CEP em Suzano-SP)';

$url = 'https://viacep.com.br/ws/' . $digits . '/json/';
$resp = pl_http_get_json($url, ['User-Agent: ' . $userAgent], 8);

if (!$resp['success'] || !is_array($resp['data'])) {
    error_log('[cep] ViaCEP indisponivel para ' . $digits . ': ' . ($resp['error'] ?? 'resposta invalida'));
    json_error('Não foi possível consultar o CEP agora. Preencha o endereço manualmente.', 503, ['code' => 'servico_indisponivel']);
}

$data = $resp['data'];

if (!empty($data['erro'])) {
    json_response([
        'success'   => false,
        'not_found' => true,
        'error'     => 'CEP não encontrado. Confira o número ou preencha o endereço manualmente.'
    ], 404);
}

$result = [
    'cep'        => $formatted,
    'street'     => mb_substr(trim((string) ($data['logradouro'] ?? '')), 0, 120),
    'complement' => mb_substr(trim((string) ($data['complemento'] ?? '')), 0, 80),
    'suburb'     => mb_substr(trim((string) ($data['bairro'] ?? '')), 0, 100),
    'city'       => mb_substr(trim((string) ($data['localidade'] ?? '')), 0, 100),
    'state'      => strtoupper(mb_substr(trim((string) ($data['uf'] ?? '')), 0, 2)),
];

pl_cache_set($cacheKey, $result, 604800);

json_response(['success' => true, 'cached' => false] + $result);
