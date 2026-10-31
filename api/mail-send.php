<?php
// O chamador desconecta apos ~500ms; o script precisa continuar ate concluir o SMTP.
ignore_user_abort(true);
@set_time_limit(60);

/**
 * Passe Livre — Endpoint interno de envio de e-mail
 * Chamado de forma nao-bloqueante por pl_respond_then_mail().
 * Nao deve ser acessivel publicamente — valida token interno.
 */
require_once __DIR__ . "/_db_restore.php";
require_once __DIR__ . "/mailer.php";

// Aceita apenas requisicoes POST internas com o token correto
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    exit;
}

$raw = file_get_contents("php://input");
$data = json_decode($raw, true);

// Endpoint interno: exige segredo dedicado (>= 32 bytes) e conexao loopback
$internalSecret = trim((string) pl_get_env("MAIL_INTERNAL_SECRET", ""));
$remoteAddr     = (string) ($_SERVER["REMOTE_ADDR"] ?? "");
if (strlen($internalSecret) < 32 || !in_array($remoteAddr, ["127.0.0.1", "::1"], true)) {
    http_response_code(403);
    exit;
}

if (!is_array($data)) {
    http_response_code(400);
    exit;
}

// Token = HMAC do segredo com a data (rotaciona diariamente, nao expoe o segredo)
$expectedToken = hash_hmac("sha256", "mail-send|" . date("Ymd"), $internalSecret);
if (empty($data["_token"]) || !hash_equals($expectedToken, (string) $data["_token"])) {
    http_response_code(403);
    exit;
}

$para      = $data["para"]      ?? "";
$nome      = $data["nome"]      ?? "";
$otp       = $data["otp"]       ?? "";
$tipo      = $data["tipo"]      ?? "cadastro";
$contexto  = $data["contexto"]  ?? "envio";

if (empty($para) || empty($otp)) {
    http_response_code(400);
    exit;
}

// Responde ao chamador imediatamente (ele nao le a resposta, mas fecha a conexao)
http_response_code(202);
header("Content-Length: 0");
if (function_exists("fastcgi_finish_request")) {
    fastcgi_finish_request();
} else {
    ob_flush(); flush();
}

$resultado = pl_mail_codigo($para, $nome, $otp, $tipo);
pl_mail_log_fail($contexto, $resultado, $para);
exit(0);
