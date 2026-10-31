<?php
/**
 * Passe Livre Suzano — Envio de e-mail por SMTP próprio (sem dependências)
 *
 * Usa os ajustes do ambiente: MAIL_HOST, MAIL_PORT, MAIL_USER, MAIL_PASS, MAIL_FROM.
 * Porta 465 = SSL direto; qualquer outra (587) = STARTTLS.
 *
 * MAIL_HOST vazio = nenhum e-mail é enviado (skipped). Toda falha é devolvida
 * no array para o chamador apenas registrar em log: o fluxo do sistema continua.
 */

require_once __DIR__ . '/_db_restore.php';

if (!function_exists('pl_mail_write')) {
    /** Envia uma linha CRLF e retorna false se o socket estiver morto. */
    function pl_mail_write($socket, string $line): bool
    {
        return @fwrite($socket, $line . "\r\n") !== false;
    }
}

if (!function_exists('pl_mail_read')) {
    /**
     * Lê a resposta SMTP (aceita respostas multi-linha "250-...").
     *
     * @return array{code:int,text:string}
     */
    function pl_mail_read($socket): array
    {
        $text = '';
        $code = 0;
        while (!feof($socket)) {
            $line = @fgets($socket, 1024);
            if ($line === false) {
                break;
            }
            $text .= $line;
            if (strlen($line) >= 4 && $line[3] === ' ') {
                $code = (int) substr($line, 0, 3);
                break;
            }
            if (strlen($line) < 4) {
                break;
            }
        }
        return ['code' => $code, 'text' => $text];
    }
}

if (!function_exists('pl_mail_expect')) {
    /** Lê a resposta e encerra a conexão se o código não for esperado. */
    function pl_mail_expect($socket, array $codes, string $etapa = ''): ?array
    {
        $resp = pl_mail_read($socket);
        if ($resp['code'] <= 0 || !in_array($resp['code'], $codes, true)) {
            $texto = trim(preg_replace('/\s+/', ' ', $resp['text']));
            // Nunca registra o que foi enviado (usuario/senha em base64 ou OTP);
            // apenas a resposta do servidor, com tokens longos mascarados.
            $texto = preg_replace('#[A-Za-z0-9+/]{20,}={0,2}#', '[redacted]', $texto);
            if (strlen($texto) > 300) {
                $texto = substr($texto, 0, 300) . '...';
            }
            $GLOBALS['pl_mail_smtp_error'] = [
                'etapa' => $etapa,
                'code'  => $resp['code'],
                'text'  => $texto,
            ];
            error_log('[mailer] Resposta SMTP inesperada'
                . ($etapa !== '' ? ' na etapa ' . $etapa : '')
                . ': codigo ' . $resp['code']
                . ($texto !== '' ? ' - ' . $texto : ' (sem texto)'));
            return null;
        }
        return $resp;
    }
}

if (!function_exists('pl_mail_smtp_error_detail')) {
    /** Detalhe seguro (etapa + resposta SMTP) para o campo error/log. */
    function pl_mail_smtp_error_detail(): string
    {
        $erro = $GLOBALS['pl_mail_smtp_error'] ?? null;
        if (!is_array($erro)) {
            return '';
        }
        $partes = [];
        if ($erro['etapa'] !== '') {
            $partes[] = 'etapa ' . $erro['etapa'];
        }
        $partes[] = 'resposta SMTP ' . $erro['code'] . ($erro['text'] !== '' ? ': ' . $erro['text'] : '');
        return ' [' . implode('; ', $partes) . ']';
    }
}

if (!function_exists('pl_mail_encode')) {
    /** Cabeçalho codificado em UTF-8 (assunto, remetente). */
    function pl_mail_encode(string $value): string
    {
        $value = str_replace(["\r", "\n"], '', $value);
        if (preg_match('//u', $value) && !mb_check_encoding($value, 'ASCII')) {
            return '=?UTF-8?B?' . base64_encode($value) . '?=';
        }
        return $value;
    }
}

if (!function_exists('pl_send_mail')) {
    /**
     * Envia um e-mail multipart/alternative (texto + HTML).
     *
     * @return array{success:bool,skipped:bool,error:string}
     */
    function pl_send_mail(string $to, string $subject, string $htmlBody, string $textBody = ''): array
    {
        $resultado = ['success' => false, 'skipped' => true, 'error' => ''];
        $GLOBALS['pl_mail_smtp_error'] = null;

        if (MAIL_HOST === '') {
            $resultado['error'] = 'smtp_nao_configurado';
            return $resultado;
        }
        $resultado['skipped'] = false;

        $to = strtolower(trim($to));
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $resultado['error'] = 'destinatario_invalido';
            return $resultado;
        }

        $remetente = MAIL_FROM !== '' ? MAIL_FROM : (MAIL_USER !== '' ? MAIL_USER : $to);
        if (!filter_var($remetente, FILTER_VALIDATE_EMAIL)) {
            $resultado['error'] = 'remetente_invalido';
            return $resultado;
        }

        if ($textBody === '') {
            $textBody = trim(preg_replace('/\s+/', ' ', strip_tags($htmlBody)));
        }

        $porta   = MAIL_PORT > 0 ? MAIL_PORT : 587;
        $protocolo = $porta === 465 ? 'ssl' : 'tcp';
        $endereco  = $protocolo . '://' . MAIL_HOST . ':' . $porta;

        $isLocal = in_array(APP_ENV, ['development', 'local'], true);
        $sslOptions = [
            'verify_peer'       => !$isLocal,
            'verify_peer_name'  => !$isLocal,
            'allow_self_signed' => $isLocal,
            'SNI_enabled'       => true,
        ];
        $caBundle = MAIL_CA_FILE !== '' ? MAIL_CA_FILE : 'C:\xampp\apache\bin\curl-ca-bundle.crt';
        if (MAIL_CA_FILE !== '' && !is_file($caBundle)) {
            $resultado['error'] = 'arquivo_ca_nao_encontrado';
            return $resultado;
        }
        if (is_file($caBundle)) {
            $sslOptions['cafile'] = $caBundle;
        }
        $contexto = stream_context_create(['ssl' => $sslOptions]);

        $socket = @stream_socket_client($endereco, $errno, $errstr, 5, STREAM_CLIENT_CONNECT, $contexto);
        if (!$socket) {
            $resultado['error'] = 'conexao falhou: ' . ($errstr ?: 'errno ' . $errno);
            return $resultado;
        }
        stream_set_timeout($socket, 5);

        $falha = null;
        $ehloHost = php_uname('n');
        if (!$ehloHost) {
            $ehloHost = 'localhost';
        }

        $borda = 'pl_' . bin2hex(random_bytes(8));
        $corpo = implode("\r\n", [
            'MIME-Version: 1.0',
            'From: ' . pl_mail_encode($remetente),
            'To: ' . pl_mail_encode($to),
            'Subject: ' . pl_mail_encode($subject),
            'Date: ' . gmdate('r'),
            'Message-ID: <' . bin2hex(random_bytes(8)) . '@passe-livre-suzano>',
            'Content-Type: multipart/alternative; boundary="' . $borda . '"',
            '',
            '--' . $borda,
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
            '',
            chunk_split(base64_encode($textBody)),
            '--' . $borda,
            'Content-Type: text/html; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
            '',
            chunk_split(base64_encode($htmlBody)),
            '--' . $borda . '--',
            ''
        ]);

        // Conversa SMTP
        if (pl_mail_expect($socket, [220], 'greeting') === null) {
            $falha = 'greeting';
        }

        if (!$falha) {
            pl_mail_write($socket, 'EHLO ' . $ehloHost);
            $resp = pl_mail_expect($socket, [250], 'ehlo');
            if ($resp === null) {
                $falha = 'ehlo';
            } elseif ($porta !== 465) {
                // STARTTLS quando o servidor oferecer
                $temStarttls = stripos($resp['text'], 'STARTTLS') !== false;
                if (!$temStarttls) {
                    $falha = 'smtp_sem_starttls';
                } else {
                    pl_mail_write($socket, 'STARTTLS');
                    if (pl_mail_expect($socket, [220], 'starttls') === null) {
                        $falha = 'starttls';
                    } elseif (!@stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                        $falha = 'criptografia_tls';
                    } else {
                        pl_mail_write($socket, 'EHLO ' . $ehloHost);
                        if (pl_mail_expect($socket, [250], 'ehlo apos starttls') === null) {
                            $falha = 'ehlo apos starttls';
                        }
                    }
                }
            }
        }

        if (!$falha && MAIL_USER !== '') {
            if (MAIL_PASS === '') {
                $falha = 'smtp_sem_senha';
            } else {
                pl_mail_write($socket, 'AUTH LOGIN');
                $auth = pl_mail_expect($socket, [334], 'auth_login');
                if ($auth === null) {
                    $falha = 'auth_login_nao_suportado';
                } else {
                    pl_mail_write($socket, base64_encode(MAIL_USER));
                    $userResp = pl_mail_expect($socket, [334], 'auth_usuario');
                    if ($userResp === null) {
                        $falha = 'auth_usuario';
                    } else {
                        pl_mail_write($socket, base64_encode(MAIL_PASS));
                        $passResp = pl_mail_expect($socket, [235], 'auth_recusado');
                        if ($passResp === null) {
                            $falha = 'auth_recusado';
                        }
                    }
                }
            }
        }

        if (!$falha) {
            pl_mail_write($socket, 'MAIL FROM:<' . $remetente . '>');
            if (pl_mail_expect($socket, [250], 'mail_from') === null) {
                $falha = 'mail_from';
            }
        }
        if (!$falha) {
            pl_mail_write($socket, 'RCPT TO:<' . $to . '>');
            if (pl_mail_expect($socket, [250, 251], 'rcpt_to') === null) {
                $falha = 'rcpt_to';
            }
        }
        if (!$falha) {
            pl_mail_write($socket, 'DATA');
            if (pl_mail_expect($socket, [354], 'data') === null) {
                $falha = 'data';
            }
        }
        if (!$falha) {
            // Ponto no inicio de linha precisa vir duplo (escape SMTP)
            $corpoEnviado = preg_replace('/^\./m', '..', $corpo);
            pl_mail_write($socket, $corpoEnviado . '.');
            if (pl_mail_expect($socket, [250], 'envio_do_corpo') === null) {
                $falha = 'envio_do_corpo';
            }
        }

        pl_mail_write($socket, 'QUIT');
        fclose($socket);

        if ($falha !== null) {
            $resultado['error'] = 'smtp: ' . $falha . pl_mail_smtp_error_detail();
            return $resultado;
        }

        $resultado['success'] = true;
        return $resultado;
    }
}

if (!function_exists('pl_mail_codigo')) {
    /**
     * E-mail de codigo de verificacao (cadastro ou recuperacao de senha).
     * Nunca grava o codigo em log.
     */
    function pl_mail_codigo(string $para, string $nome, string $codigo, string $tipo): array
    {
        if (MAIL_HOST === '') {
            return ['success' => false, 'skipped' => true, 'error' => 'smtp_nao_configurado'];
        }

        $ehRecuperacao = $tipo === 'recuperacao_senha';
        $assunto = $ehRecuperacao
            ? 'Passe Livre Suzano — Codigo de recuperacao de senha'
            : 'Passe Livre Suzano — Confirme seu cadastro';
        $primeiro = trim(explode(' ', $nome)[0]) ?: 'cidada';
        $primeiro = pl_mail_encode($primeiro);

        $html = '<div style="font-family:Arial,Helvetica,sans-serif;max-width:560px;margin:0 auto;color:#1f2933">'
            . '<p style="font-size:18px;font-weight:bold;color:#0b5cff">Passe Livre Suzano</p>'
            . '<p>Ol&aacute;, ' . $primeiro . '.</p>'
            . '<p>' . ($ehRecuperacao
                ? 'Recebemos um pedido para recuperar sua senha. Use o c&oacute;digo abaixo para definir uma nova.'
                : 'Bem-vindo(a)! Use o c&oacute;digo abaixo para confirmar seu cadastro.') . '</p>'
            . '<p style="font-size:32px;letter-spacing:6px;font-weight:bold;background:#f1f5f9;padding:14px;border-radius:8px;text-align:center">'
            . htmlspecialchars($codigo, ENT_QUOTES, 'UTF-8') . '</p>'
            . '<p>O c&oacute;digo expira em 15 minutos. Se voc&ecirc; n&atilde;o pediu, ignore este e-mail.</p>'
            . '<p style="color:#6b7280;font-size:12px">Mensagem autom&aacute;tica — n&atilde;o responda.</p>'
            . '</div>';

        $texto = "Passe Livre Suzano\n\n"
            . "Ola, {$primeiro}.\n\n"
            . ($ehRecuperacao ? "Codigo de recuperacao de senha: " : "Codigo de confirmacao de cadastro: ")
            . $codigo . "\n\nO codigo expira em 15 minutos. Se voce nao pediu, ignore este e-mail.";

        return pl_send_mail($para, $assunto, $html, $texto);
    }
}

if (!function_exists('pl_mail_log_fail')) {
    /** Registra o resultado do envio sem expor credenciais ou codigos. */
    function pl_mail_log_fail(string $contexto, array $resultado, string $para): void
    {
        if (!empty($resultado['success']) || !empty($resultado['skipped'])) {
            return;
        }
        $detalhe = strpos((string)$resultado['error'], 'resposta SMTP') === false
            ? pl_mail_smtp_error_detail()
            : '';
        error_log('[mailer] ' . $contexto . ' falhou para ' . $para . ': ' . $resultado['error'] . $detalhe);
    }
}

if (!function_exists('pl_respond_then_mail')) {
    /**
     * Responde ao browser imediatamente e dispara o envio de e-mail
     * em segundo plano via curl com timeout minimo (fire-and-forget).
     *
     * O endpoint interno exige MAIL_INTERNAL_SECRET (>= 32 bytes) e só aceita
     * conexões loopback. Sem segredo configurado, o envio é síncrono.
     * O OTP ja esta gravado no banco antes desta chamada.
     */
    function pl_respond_then_mail(
        array  $respData,
        string $contexto,
        string $para,
        string $nome,
        string $otp,
        string $tipo,
        int    $status = 200
    ): void {
        $internalSecret = trim((string) pl_get_env('MAIL_INTERNAL_SECRET', ''));

        if (strlen($internalSecret) < 32) {
            // Sem segredo interno: envia de forma síncrona, sem expor endpoint HTTP
            $resultado = pl_mail_codigo($para, $nome, $otp, $tipo);
            pl_mail_log_fail($contexto, $resultado, $para);
            json_response($respData, $status);
        }

        $internalToken = hash_hmac('sha256', 'mail-send|' . date('Ymd'), $internalSecret);

        $payload = json_encode([
            '_token'   => $internalToken,
            'para'     => $para,
            'nome'     => $nome,
            'otp'      => $otp,
            'tipo'     => $tipo,
            'contexto' => $contexto,
        ], JSON_UNESCAPED_UNICODE);

        // URL interna fixada em loopback (o endpoint só aceita 127.0.0.1 / ::1)
        $scheme    = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $port      = (int)($_SERVER['SERVER_PORT'] ?? ($scheme === 'https' ? 443 : 80));
        $host      = '127.0.0.1';
        $scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/api/auth.php'), '/');
        $mailUrl   = $scheme . '://' . $host . ':' . $port . $scriptDir . '/mail-send.php';

        $disparado = false;

        if (function_exists('curl_init')) {
            $ch = curl_init($mailUrl);
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $payload,
                CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
                CURLOPT_RETURNTRANSFER => true,
                // Timeout de 500ms: apenas tempo para o Apache aceitar a conexao.
                // O mail-send.php roda no seu proprio processo ate completar o SMTP.
                CURLOPT_TIMEOUT_MS     => 500,
                CURLOPT_CONNECTTIMEOUT_MS => 500,
                CURLOPT_NOSIGNAL       => 1,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => 0,
            ]);
            curl_exec($ch);
            $curlErr = curl_errno($ch);
            $httpStatus = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            curl_close($ch);
            $disparado = $curlErr === 0 && $httpStatus === 202;
            if ($curlErr === 0 && !$disparado) {
                error_log('[mailer] endpoint interno recusou o disparo (HTTP ' . $httpStatus . ')');
            }
        }

        if (!$disparado) {
            // Fallback: fsockopen nao-bloqueante (sempre loopback)
            $host2 = '127.0.0.1';
            $port2 = (int)($_SERVER['SERVER_PORT'] ?? 80);
            $isHttps2 = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $port2 === 443;
            $path2 = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/api/auth.php'), '/') . '/mail-send.php';
            $req2 = "POST {$path2} HTTP/1.1\r\nHost: {$host2}\r\nContent-Type: application/json\r\n"
                  . "Content-Length: " . strlen($payload) . "\r\nConnection: close\r\n\r\n" . $payload;
            $prefix = $isHttps2 ? 'ssl://' : '';
            $sock = @fsockopen($prefix . $host2, $port2, $errno, $errstr, 1);
            if ($sock) {
                stream_set_timeout($sock, 1);
                $written = 0;
                $requestLength = strlen($req2);
                while ($written < $requestLength) {
                    $sent = @fwrite($sock, substr($req2, $written));
                    if ($sent === false || $sent === 0) {
                        break;
                    }
                    $written += $sent;
                }
                $statusLine = $written === $requestLength ? @fgets($sock, 256) : false;
                $disparado = is_string($statusLine)
                    && preg_match('#^HTTP/\d(?:\.\d)? 202(?:\s|$)#', $statusLine) === 1;
                @fclose($sock);
                if (!$disparado && is_string($statusLine)) {
                    error_log('[mailer] endpoint interno recusou o disparo: ' . trim($statusLine));
                }
            }
        }

        if (!$disparado) {
            // Fallback final: envio sincrono (bloqueante, mas garante entrega)
            error_log('[mailer] fire-and-forget falhou, enviando sincrono para ' . $para);
            $resultado = pl_mail_codigo($para, $nome, $otp, $tipo);
            pl_mail_log_fail($contexto, $resultado, $para);
        }

        // Responde ao browser imediatamente
        json_response($respData, $status);
    }
}

