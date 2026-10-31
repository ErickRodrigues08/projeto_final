<?php
// Proteção de segurança: restrito ao CLI ou ambiente de desenvolvimento
require_once __DIR__ . '/api/_db_restore.php';
if (php_sapi_name() !== 'cli' && (!defined('APP_ENV') || APP_ENV !== 'development')) {
    http_response_code(403);
    die('Acesso negado. Arquivo restrito a ambiente de desenvolvimento e linha de comando.');
}

// Test runner for API endpoints
function post_json($url, $data) {
    $ctx = stream_context_create([
        'http' => [
            'method'  => 'POST',
            'header'  => "Content-Type: application/json\r\nAccept: application/json\r\n",
            'content' => json_encode($data),
            'ignore_errors' => true
        ]
    ]);
    return file_get_contents($url, false, $ctx);
}

function get_json($url) {
    return file_get_contents($url);
}

echo "=== TESTANDO ENDPOINTS ===\n";

// 1. Contato
$res = post_json('http://localhost/teste3.5/api/contato.php', [
    'name'    => 'Mariana Teste',
    'email'   => 'mariana.teste@gmail.com',
    'phone'   => '(11) 98765-4321',
    'subject' => 'Dúvida Acessibilidade',
    'message' => 'Gostaria de saber se o parque Max Feffer possui banheiros abertos nos finais de semana.'
]);
echo "1. Contato: " . $res . "\n";

// 2. Pending Count
$res = get_json('http://localhost/teste3.5/api/solicitacoes.php?action=pending_count');
echo "2. Pending Count: " . $res . "\n";

// 3. Login
$res = post_json('http://localhost/teste3.5/api/auth.php?action=login', [
    'email'    => 'carlos.eduardo@suzano.sp.gov.br',
    'password' => 'admin123',
    'remember' => true
]);
echo "3. Login Gestor: " . $res . "\n";

// 4. SQL Bridge
$res = post_json('http://localhost/teste3.5/api/sql_bridge.php?action=query', [
    'sql'    => 'SELECT count(*) as total FROM locais',
    'params' => []
]);
echo "4. SQL Bridge: " . $res . "\n";
