<?php
/**
 * Passe Livre Suzano — Endpoint de Autenticação e Gestão de Sessões
 * Suporte a cadastro, 2FA/OTP, login, bloqueio por força bruta, Google login,
 * recuperação e redefinição de senha com MySQL 8.0+.
 */

require_once __DIR__ . '/_db_restore.php';
require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/http.php';
require_once __DIR__ . '/../includes/otp_cooldown.php';

$action = $_GET['action'] ?? '';
$data   = get_request_data();
$db     = get_db();

/**
 * Limites por IP, aplicados ANTES de consultar o banco: dificultan enumeração
 * de contas e disparo em rajada, sem atrelar o cooldown a um endereço específico.
 */
function pl_auth_otp_ip_limit(string $action): void {
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');

    if (!pl_rate_limit_hit("otp_burst|ip|{$ip}", 6, 60)) {
        json_error('Muitas solicitações de código a partir deste dispositivo. Aguarde um minuto e tente novamente.', 429, [
            'code'        => 'limite_excedido',
            'retry_after' => 60
        ]);
    }

    if (!pl_rate_limit_hit("otp_hour|{$action}|ip|{$ip}", 5, 3600)) {
        json_error('Limite de 5 códigos por hora atingido. Tente novamente mais tarde.', 429, [
            'code'        => 'limite_excedido',
            'retry_after' => 3600
        ]);
    }
}

/**
 * Limites vinculados ao e-mail informado: cooldown de 60 s medido a partir do
 * último código enviado PARA ESTE E-MAIL (codigos_verificacao) e cota horária
 * própria. Um e-mail diferente não herda o contador do anterior.
 */
function pl_auth_otp_email_limit(PDO $db, string $action, string $email, string $tipo): void {
    $restante = pl_otp_cooldown_remaining($db, $email, $tipo);
    if ($restante > 0) {
        json_error("Aguarde {$restante} segundos para solicitar um novo código.", 429, [
            'code'         => 'limite_excedido',
            'retry_after'  => $restante,
            'email'        => strtolower(trim($email)),
            'cooldown_max' => PL_OTP_COOLDOWN_SECONDS
        ]);
    }

    $emailKey = hash('sha256', strtolower($email));
    if (!pl_rate_limit_hit("otp_hour|{$action}|email|{$emailKey}", 5, 3600)) {
        json_error('Limite de 5 códigos por hora atingido para este e-mail. Tente novamente mais tarde.', 429, [
            'code'        => 'limite_excedido',
            'retry_after' => 3600
        ]);
    }
}

/**
 * Limita as consultas de recuperação por IP antes de tocar no banco.
 * A resposta explícita de "e-mail não cadastrado" revela a existência de
 * contas, então o limite dificulta a enumeração automatizada.
 */
function pl_auth_forgot_lookup_limit(): void {
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    if (!pl_rate_limit_hit("forgot_lookup|ip|{$ip}", 10, 600)) {
        json_error('Muitas tentativas de recuperação de senha. Aguarde alguns minutos e tente novamente.', 429, [
            'code'        => 'limite_excedido',
            'retry_after' => 600
        ]);
    }
}

switch ($action) {

    // =========================================================================
    // 1. PRÉ-CADASTRO — Envia OTP e guarda dados temporariamente.
    //    O usuário só é inserido em `usuarios` APÓS verificar o e-mail.
    // =========================================================================
    case 'register':
        $nome        = trim($data['name'] ?? '');
        $email       = strtolower(trim($data['email'] ?? ''));
        $telefone    = trim($data['phone'] ?? '');
        // Limite de caracteres do telefone: maximo 15 caracteres (formato brasileiro com mascara)
        if (mb_strlen($telefone) > 15) {
            json_error('O número de telefone deve ter no máximo 15 caracteres.');
        }
        $senha       = $data['password'] ?? '';
        $cidade      = trim($data['city'] ?? 'Suzano - SP');
        $bairro      = trim($data['bairro'] ?? 'Centro');
        $pcdSelected = is_array($data['pcd_categories'] ?? null) ? $data['pcd_categories'] : [];
        $termos      = !empty($data['termos']);

        if (empty($nome)) {
            json_error('O nome completo é obrigatório.');
        }
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            json_error('Informe um endereço de e-mail válido.');
        }
        if (strlen($senha) < 6) {
            json_error('A senha deve conter no mínimo 6 caracteres.');
        }
        if (!$termos) {
            json_error('É obrigatório aceitar os termos de uso e política de privacidade.');
        }

        pl_auth_otp_ip_limit('register');

        // Verifica se e-mail já pertence a um usuário VERIFICADO antes de qualquer
        // efeito colateral: sem código criado e sem cooldown iniciado.
        $stmt = $db->prepare("SELECT id, email_verified FROM usuarios WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $existingUser = $stmt->fetch();
        if ($existingUser && $existingUser['email_verified']) {
            json_error('Este e-mail já possui uma conta cadastrada. Faça login para continuar.', 400, [
                'code'      => 'email_ja_cadastrado',
                'email'     => $email,
                'login_url' => 'login.php'
            ]);
        }

        pl_auth_otp_email_limit($db, 'register', $email, 'cadastro');

        // Invalida qualquer OTP de pré-cadastro anterior para este e-mail
        $db->prepare("UPDATE codigos_verificacao SET usado = 1 WHERE email = ? AND tipo = 'cadastro' AND usado = 0")
           ->execute([$email]);

        // Se existia um usuário não verificado com este e-mail, remove-o
        if ($existingUser && !$existingUser['email_verified']) {
            $db->prepare("DELETE FROM usuarios WHERE id = ? AND email_verified = 0")->execute([$existingUser['id']]);
        }

        // Gera OTP de 6 dígitos e armazena os dados do cadastro como JSON em `dados_pendentes`.
        // O usuário real em `usuarios` só será criado em verify_code, após confirmação do e-mail.
        $otp = str_pad((string)random_int(100000, 999999), 6, '0', STR_PAD_LEFT);

        // Hash seguro — gerado agora para não guardar senha em texto puro nos dados pendentes
        $passwordHash = password_hash($senha, PASSWORD_DEFAULT);
        $userCode = 'USR-' . date('Y') . '-' . str_pad((string)mt_rand(100, 999), 3, '0', STR_PAD_LEFT);
        $role = !empty($pcdSelected) ? 'Cidadão PCD (' . implode(', ', $pcdSelected) . ')' : 'Cidadão Conectado';
        $initials = mb_strtoupper(mb_substr($nome, 0, 1) . (strpos($nome, ' ') ? mb_substr(explode(' ', $nome)[1], 0, 1) : ''));
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        $dadosPendentes = json_encode([
            'user_code'     => $userCode,
            'name'          => $nome,
            'email'         => $email,
            'password_hash' => $passwordHash,
            'phone'         => $telefone,
            'city'          => $cidade,
            'bairro'        => $bairro,
            'role'          => $role,
            'initials'      => $initials,
            'pcd_categories'=> $pcdSelected,
            'ip'            => $ip,
        ], JSON_UNESCAPED_UNICODE);

        $stmtOtp = $db->prepare("
            INSERT INTO codigos_verificacao (user_id, email, codigo, tipo, dados_pendentes, expires_at)
            VALUES (NULL, ?, ?, 'cadastro', ?, DATE_ADD(NOW(), INTERVAL 15 MINUTE))
        ");
        $stmtOtp->execute([$email, $otp, $dadosPendentes]);

        // Monta a resposta antes de liberar o buffer ao browser
        $respData = [
            'success' => true,
            'message' => 'Código de verificação enviado! Confirme seu e-mail para concluir o cadastro.',
            'email'   => $email,
        ];
        if (defined('APP_ENV') && APP_ENV === 'development') {
            $respData['demo_code'] = $otp;
        }

        // Envia a resposta JSON ao browser PRIMEIRO, depois envia o e-mail em segundo plano.
        // O OTP já está gravado no banco: o fluxo de verificação é seguro independente do e-mail.
        try {
            pl_respond_then_mail($respData, 'cadastro', $email, $nome, $otp, 'cadastro');
        } catch (\Throwable $e) {
            error_log('[auth] falha ao disparar e-mail de cadastro: ' . $e->getMessage());
            json_response($respData);
        }
        break;

    // =========================================================================
    // 2. VALIDAÇÃO DE CÓDIGO OTP (2FA E RECUPERAÇÃO)
    // =========================================================================
    case 'verify_code':
        $email  = strtolower(trim($data['email'] ?? ''));
        $codigo = trim($data['code'] ?? '');
        $flow   = trim($data['flow'] ?? 'cadastro');

        if (empty($email) || empty($codigo)) {
            json_error('E-mail e código de verificação são obrigatórios.');
        }

        // Validação estrita por tipo de operação (não permite usar OTP de cadastro para recuperação e vice-versa)
        $expectedTipo = ($flow === 'cadastro') ? 'cadastro' : 'recuperacao_senha';

        $stmt = $db->prepare("
            SELECT * FROM codigos_verificacao
            WHERE email = ? AND tipo = ? AND usado = 0
            ORDER BY created_at DESC LIMIT 1
        ");
        $stmt->execute([$email, $expectedTipo]);
        $row = $stmt->fetch();

        if (!$row) {
            json_error('Nenhum código ativo encontrado para este e-mail nesta operação. Solicite um novo código.');
        }

        // Verifica limite de tentativas (proteção contra força bruta)
        if ($row['tentativas'] >= 5) {
            json_error('Limite de tentativas excedido para este código. Solicite um novo código.');
        }

        // Verifica expiração (15 minutos) — compara diretamente com o tempo do servidor
        // O MySQL e o PHP usam o mesmo timezone do sistema (America/Sao_Paulo),
        // portanto não é necessário nenhum ajuste manual de fuso horário.
        $expiresTimestamp = strtotime($row['expires_at']);
        if ($expiresTimestamp === false || $expiresTimestamp < time()) {
            json_error('Este código expirou (limite de 15 minutos). Solicite um novo código.');
        }

        // Incrementa tentativas
        $db->prepare("UPDATE codigos_verificacao SET tentativas = tentativas + 1 WHERE id = ?")->execute([$row['id']]);

        if ($row['codigo'] !== $codigo) {
            json_error('Código incorreto. Verifique os dígitos e tente novamente.');
        }

        // Marca OTP como usado
        $db->prepare("UPDATE codigos_verificacao SET usado = 1 WHERE id = ?")->execute([$row['id']]);

        // Se for recuperação de senha, gera TOKEN TEMPORÁRIO DE USO ÚNICO para autorizar o reset_password
        if ($flow === 'recuperacao' || $flow === 'recuperacao_senha') {
            $resetToken = bin2hex(random_bytes(32));
            $stmtTok = $db->prepare("
                UPDATE codigos_verificacao
                SET token_autorizacao = ?, token_expires_at = DATE_ADD(NOW(), INTERVAL 15 MINUTE)
                WHERE id = ?
            ");
            $stmtTok->execute([$resetToken, $row['id']]);

            json_response([
                'success'     => true,
                'message'     => 'Código validado com sucesso! Prossiga para redefinir sua senha.',
                'flow'        => $flow,
                'reset_token' => $resetToken
            ]);
        }

        // Se for cadastro, cria o usuário em `usuarios` a partir dos dados guardados em
        // `dados_pendentes`, marca e-mail como verificado e inicia sessão segura.
        if ($flow === 'cadastro') {
            $pending = null;
            if (!empty($row['dados_pendentes'])) {
                $pending = json_decode($row['dados_pendentes'], true);
            }

            if (!$pending) {
                json_error('Dados de pré-cadastro não encontrados. Refaça o cadastro.');
            }

            try {
                $db->beginTransaction();

                $stmt = $db->prepare("
                    INSERT INTO usuarios
                      (user_code, name, email, password_hash, phone, city, bairro, role, is_admin, avatar, initials, status, email_verified, pcd_categories, saved_prefs, is_google)
                    VALUES
                      (?, ?, ?, ?, ?, ?, ?, ?, 0, 'assets/images/avatar.jpg', ?, 'ativo', 1, ?, '[]', 0)
                ");
                $stmt->execute([
                    $pending['user_code'],
                    $pending['name'],
                    $pending['email'],
                    $pending['password_hash'],
                    $pending['phone'] ?? '',
                    $pending['city'] ?? 'Suzano - SP',
                    $pending['bairro'] ?? 'Centro',
                    $pending['role'] ?? 'Cidadão Conectado',
                    $pending['initials'] ?? '',
                    json_encode($pending['pcd_categories'] ?? [], JSON_UNESCAPED_UNICODE)
                ]);
                $userId = (int)$db->lastInsertId();

                // Atualiza o OTP com o user_id agora criado
                $db->prepare("UPDATE codigos_verificacao SET user_id = ? WHERE id = ?")
                   ->execute([$userId, $row['id']]);

                // Grava categorias PcD em `usuario_necessidades`
                if (!empty($pending['pcd_categories'])) {
                    $stmtNec = $db->prepare("INSERT INTO usuario_necessidades (user_id, categoria_pcd, descricao) VALUES (?, ?, ?)");
                    foreach ($pending['pcd_categories'] as $cat) {
                        $stmtNec->execute([$userId, $cat, "Categoria informada no cadastro inicial"]);
                    }
                }

                // Grava aceite de termos em `usuario_consentimentos`
                $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
                $stmtCons = $db->prepare("INSERT INTO usuario_consentimentos (user_id, tipo_consentimento, aceito, ip_address) VALUES (?, ?, 1, ?)");
                $stmtCons->execute([$userId, 'termos_uso', $ip]);
                $stmtCons->execute([$userId, 'privacidade_lgpd', $ip]);

                // Auditoria
                log_audit('usuarios', 'Novo Cadastro de Cidadão', "Usuário {$pending['name']} ({$pending['email']}) cadastrado após verificação de e-mail", 'baixo', $userId);

                // Cria sessão
                $token = bin2hex(random_bytes(32));
                $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
                $stmtSess = $db->prepare("
                    INSERT INTO sessoes (user_id, session_token, ip_address, user_agent, is_remember, expires_at)
                    VALUES (?, ?, ?, ?, 0, DATE_ADD(NOW(), INTERVAL 24 HOUR))
                ");
                $stmtSess->execute([$userId, $token, $ip, $ua]);

                $db->commit();

                $_SESSION['pl_session_token'] = $token;
                set_auth_cookie($token, time() + 86400);

                $stmtUser = $db->prepare("SELECT * FROM usuarios WHERE id = ? LIMIT 1");
                $stmtUser->execute([$userId]);
                $user = $stmtUser->fetch();
                unset($user['password_hash']);
                $user['avatar'] = pl_avatar_exibicao($user['avatar'] ?? null);

                json_response([
                    'success'       => true,
                    'message'       => 'E-mail verificado com sucesso! Cadastro concluído.',
                    'flow'          => $flow,
                    'session_token' => $token,
                    'user'          => $user
                ]);
            } catch (Exception $e) {
                $db->rollBack();
                handle_db_error($e, 'Erro ao concluir o cadastro após verificação. Tente novamente.');
            }
        }

        json_response([
            'success' => true,
            'message' => 'Código validado com sucesso!',
            'flow'    => $flow
        ]);
        break;

    // =========================================================================
    // 3. REENVIAR CÓDIGO OTP (Cooldown de 30 segundos)
    // =========================================================================
    case 'resend_code':
        $email = strtolower(trim($data['email'] ?? ''));
        $flow  = trim($data['flow'] ?? 'cadastro');

        if (empty($email)) {
            json_error('Informe o e-mail para reenvio.');
        }

        $tipo = ($flow === 'recuperacao' || $flow === 'recuperacao_senha') ? 'recuperacao_senha' : 'cadastro';

        pl_auth_otp_ip_limit('resend_code');

        // Reenvio do fluxo de cadastro não pode atingir um e-mail que já virou conta:
        // sem código novo, sem notificação e sem cooldown iniciado.
        if ($tipo === 'cadastro') {
            $stmtExiste = $db->prepare("SELECT id FROM usuarios WHERE email = ? AND email_verified = 1 LIMIT 1");
            $stmtExiste->execute([$email]);
            if ($stmtExiste->fetch()) {
                json_error('Este e-mail já possui uma conta cadastrada. Faça login para continuar.', 400, [
                    'code'      => 'email_ja_cadastrado',
                    'email'     => $email,
                    'login_url' => 'login.php'
                ]);
            }
        }

        // Cooldown de 60 s medido pelo último código enviado para este e-mail
        pl_auth_otp_email_limit($db, 'resend_code', $email, $tipo);

        $userId = null;

        // Para reenvio de cadastro, recupera dados_pendentes do OTP anterior
        $dadosPendentes = null;
        $nomeParaEmail  = '';
        if ($tipo === 'cadastro') {
            $stmtPend = $db->prepare("
                SELECT dados_pendentes FROM codigos_verificacao
                WHERE email = ? AND tipo = 'cadastro' AND usado = 0
                ORDER BY created_at DESC LIMIT 1
            ");
            $stmtPend->execute([$email]);
            $rowPend = $stmtPend->fetch();
            if ($rowPend && !empty($rowPend['dados_pendentes'])) {
                $dadosPendentes = $rowPend['dados_pendentes'];
                $pend = json_decode($dadosPendentes, true);
                $nomeParaEmail = $pend['name'] ?? '';
            }
            if (!$dadosPendentes) {
                json_error('Dados de pré-cadastro não encontrados. Refaça o cadastro.');
            }
            // Invalida OTP anterior para não haver múltiplos ativos
            $db->prepare("UPDATE codigos_verificacao SET usado = 1 WHERE email = ? AND tipo = 'cadastro' AND usado = 0")
               ->execute([$email]);
        } else {
            // Recuperação só prossegue para conta existente e ativa
            $stmtUser = $db->prepare("SELECT id, name, status FROM usuarios WHERE email = ? LIMIT 1");
            $stmtUser->execute([$email]);
            $u = $stmtUser->fetch();

            if (!$u) {
                json_error(
                    'Este e-mail não está cadastrado em nossa plataforma. Verifique o endereço informado ou crie uma conta para continuar.',
                    404,
                    ['code' => 'email_nao_cadastrado']
                );
            }
            if (($u['status'] ?? 'ativo') !== 'ativo') {
                json_error(
                    'Não é possível recuperar a senha desta conta no momento. Entre em contato com o suporte.',
                    403,
                    ['code' => 'conta_indisponivel']
                );
            }

            $userId = (int)$u['id'];
            $nomeParaEmail = (string)($u['name'] ?? '');
        }

        $otp = str_pad((string)random_int(100000, 999999), 6, '0', STR_PAD_LEFT);

        $stmtOtp = $db->prepare("
            INSERT INTO codigos_verificacao (user_id, email, codigo, tipo, dados_pendentes, expires_at)
            VALUES (?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 15 MINUTE))
        ");
        $stmtOtp->execute([$userId, $email, $otp, $tipo, $dadosPendentes]);

        $resendResp = [
            'success' => true,
            'message' => 'Novo código enviado com sucesso!'
        ];
        if (defined('APP_ENV') && APP_ENV === 'development') {
            $resendResp['demo_code'] = $otp;
        }

        // Envia a resposta JSON ao browser PRIMEIRO, depois envia o e-mail em segundo plano.
        try {
            pl_respond_then_mail($resendResp, 'reenvio', $email, $nomeParaEmail, $otp, $tipo);
        } catch (\Throwable $e) {
            error_log('[auth] falha ao disparar e-mail de reenvio: ' . $e->getMessage());
            json_response($resendResp);
        }
        break;

    // =========================================================================
    // 4. LOGIN COM E-MAIL E SENHA
    // =========================================================================
    case 'login':
        $email    = strtolower(trim($data['email'] ?? ''));
        $senha    = $data['password'] ?? '';
        $lembrar  = !empty($data['remember']);

        if (empty($email) || empty($senha)) {
            json_error('Informe o e-mail e a senha.');
        }

        $stmt = $db->prepare("SELECT * FROM usuarios WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        // Hash descartável: equaliza o tempo de resposta quando o e-mail não existe
        $dummyHash = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';

        if (!$user) {
            password_verify($senha, $dummyHash);
            json_error('E-mail ou senha incorretos.', 401);
        }

        // Confere senha exclusivamente com hash Bcrypt
        $senhaValida = false;
        if (!empty($user['password_hash'])) {
            $senhaValida = password_verify($senha, $user['password_hash']);
        }

        if (!$senhaValida) {
            $tentativas = (int)$user['failed_login_attempts'] + 1;
            if ($tentativas >= 5) {
                // Bloqueia por 15 minutos
                $db->prepare("
                    UPDATE usuarios
                    SET failed_login_attempts = ?, locked_until = DATE_ADD(NOW(), INTERVAL 15 MINUTE)
                    WHERE id = ?
                ")->execute([$tentativas, $user['id']]);

                log_audit('seguranca', 'Bloqueio de Conta por Força Bruta', "Conta de {$email} bloqueada após 5 tentativas falhas", 'alto', $user['id']);
            } else {
                $db->prepare("UPDATE usuarios SET failed_login_attempts = ? WHERE id = ?")->execute([$tentativas, $user['id']]);
            }
            // Mensagem genérica: não revela existência da conta nem tentativas restantes
            json_error('E-mail ou senha incorretos.', 401);
        }

        // Bloqueios só são informados após a senha correta (evita enumeração de contas)
        if ($user['status'] === 'bloqueado') {
            json_error('Esta conta foi bloqueada pela administração do sistema.');
        }
        if (!empty($user['locked_until']) && strtotime($user['locked_until']) > time()) {
            $minutos = ceil((strtotime($user['locked_until']) - time()) / 60);
            json_error("Conta temporariamente bloqueada por excesso de tentativas falhas. Tente novamente em {$minutos} minutos.", 429);
        }

        // Login com sucesso: reseta tentativas e bloqueio
        $db->prepare("UPDATE usuarios SET failed_login_attempts = 0, locked_until = NULL WHERE id = ?")->execute([$user['id']]);

        // Cria sessão em `sessoes`
        $sessionToken = bin2hex(random_bytes(32));
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';

        $diasExpiracao = $lembrar ? 30 : 1;
        $expiresAt = date('Y-m-d H:i:s', time() + ($diasExpiracao * 86400));

        $stmtSess = $db->prepare("
            INSERT INTO sessoes (user_id, session_token, ip_address, user_agent, is_remember, expires_at)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmtSess->execute([$user['id'], $sessionToken, $ip, $ua, $lembrar ? 1 : 0, $expiresAt]);

        $_SESSION['pl_session_token'] = $sessionToken;
        set_auth_cookie($sessionToken, time() + ($diasExpiracao * 86400));

        $isAdmin = (int)$user['is_admin'] === 1;
        log_audit('autenticacao', 'Login no Sistema', "Usuário \"{$user['name']}\" ({$user['email']}) realizou login com sucesso", $isAdmin ? 'medio' : 'baixo', $user['id']);

        unset($user['password_hash']);
        $user['avatar'] = pl_avatar_exibicao($user['avatar'] ?? null);
        if (isset($user['pcd_categories']) && is_string($user['pcd_categories'])) {
            $user['pcd_categories'] = json_decode($user['pcd_categories'], true) ?: [];
        }
        if (isset($user['saved_prefs']) && is_string($user['saved_prefs'])) {
            $user['saved_prefs'] = json_decode($user['saved_prefs'], true) ?: [];
        }
        $user['is_admin'] = $isAdmin ? 1 : 0;

        json_response([
            'success'       => true,
            'message'       => 'Login efetuado com sucesso!',
            'session_token' => $sessionToken,
            'redirect'      => $isAdmin ? 'dashboard.php' : 'adaptar-busca.php',
            'user'          => $user
        ]);
        break;

    // =========================================================================
    // 5. LOGIN COM GOOGLE (OAuth 2.0 / `usuario_identidades_externas`)
    //    Exige id_token do Google Identity Services e valida aud/iss/exp.
    //    O `sub` é o identificador principal da conta Google.
    // =========================================================================
    case 'google_login':
        $googleClientId = trim((string) pl_get_env('GOOGLE_CLIENT_ID', ''));
        if ($googleClientId === '') {
            json_error('Login com Google não está configurado neste ambiente.', 503);
        }

        $idToken = trim($data['id_token'] ?? '');
        if ($idToken === '') {
            json_error('Credencial do Google (id_token) não informada.', 400);
        }

        // Valida o JWT com a API do Google (tokeninfo)
        $tokenInfoUrl = 'https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($idToken);
        $ctx = stream_context_create(['http' => ['timeout' => 8, 'ignore_errors' => true]]);
        $tokenInfoRaw = @file_get_contents($tokenInfoUrl, false, $ctx);
        if (!$tokenInfoRaw) {
            json_error('Não foi possível validar o token do Google. Tente novamente.', 401);
        }

        $tokenInfo = json_decode($tokenInfoRaw, true);
        if (!is_array($tokenInfo)) {
            json_error('Resposta inválida do Google ao validar o token.', 401);
        }

        // Token precisa ter sido emitido para esta aplicação
        if (($tokenInfo['aud'] ?? '') !== $googleClientId) {
            json_error('Token do Google emitido para outra aplicação.', 401);
        }

        // Emissor oficial do Google
        $issuer = (string)($tokenInfo['iss'] ?? '');
        if (!in_array($issuer, ['accounts.google.com', 'https://accounts.google.com'], true)) {
            json_error('Emissor do token Google inválido.', 401);
        }

        // Token expirado
        if ((int)($tokenInfo['exp'] ?? 0) <= time()) {
            json_error('Token do Google expirado. Tente novamente.', 401);
        }

        // E-mail verificado (a API pode devolver booleano ou string)
        $emailVerifiedRaw = $tokenInfo['email_verified'] ?? false;
        if (!in_array($emailVerifiedRaw, [true, 1, '1', 'true'], true)) {
            json_error('A conta Google informada não possui e-mail verificado.', 401);
        }

        $googleEmail = strtolower(trim((string)($tokenInfo['email'] ?? '')));
        $googleId    = trim((string)($tokenInfo['sub'] ?? ''));
        $googleName  = trim((string)($tokenInfo['name'] ?? $tokenInfo['given_name'] ?? 'Usuário Google'));
        // A foto do Google só é usada quando o Google realmente envia uma URL de
        // imagem; sem ela, a conta nasce com a foto padrão do projeto.
        $googlePic   = trim((string)($tokenInfo['picture'] ?? ''));
        if (preg_match('#^https://[^"\'\s<>]{4,250}$#i', $googlePic) !== 1) {
            $googlePic = PL_AVATAR_PADRAO;
        }

        if ($googleEmail === '' || $googleId === '') {
            json_error('Token do Google incompleto (sub/email ausentes).', 401);
        }

        // 1º: procura vínculo existente pelo `sub` (identificador principal)
        $stmtIdent = $db->prepare("SELECT user_id FROM usuario_identidades_externas WHERE provider = 'google' AND provider_user_id = ? LIMIT 1");
        $stmtIdent->execute([$googleId]);
        $identRow = $stmtIdent->fetch();

        $user = null;
        if ($identRow) {
            $stmtUser = $db->prepare("SELECT * FROM usuarios WHERE id = ? LIMIT 1");
            $stmtUser->execute([(int)$identRow['user_id']]);
            $user = $stmtUser->fetch();
        }

        // 2º: sem vínculo, só associa a conta existente pelo e-mail (já verificado pelo Google)
        if (!$user) {
            $stmt = $db->prepare("SELECT * FROM usuarios WHERE email = ? LIMIT 1");
            $stmt->execute([$googleEmail]);
            $user = $stmt->fetch();
        }

        if ($user) {
            // Proteção contra elevação indevida de privilégio: não permite login social para admins
            $isAdminUser = (int)$user['is_admin'] === 1;
            if ($isAdminUser) {
                log_audit('seguranca', 'Bloqueio de Login Google Administrativo', "Tentativa de login Google para conta gestora {$googleEmail} rejeitada", 'alto', $user['id']);
                json_error('Contas com privilégios administrativos devem autenticar-se utilizando usuário e senha oficiais.', 403);
            }
            $userId = (int)$user['id'];
        } else {
            // Cria cidadão comum a partir do perfil Google
            $code = 'USR-' . date('Y') . '-' . str_pad((string)random_int(100, 999), 3, '0', STR_PAD_LEFT);
            $initials = mb_strtoupper(mb_substr($googleName, 0, 1) . 'G');
            $stmtNew = $db->prepare("
                INSERT INTO usuarios
                  (user_code, name, email, role, is_admin, avatar, initials, status, email_verified, is_google)
                VALUES
                  (?, ?, ?, 'Cidadão Conectado', 0, ?, ?, 'ativo', 1, 1)
            ");
            $stmtNew->execute([$code, $googleName, $googleEmail, $googlePic, $initials]);
            $userId = (int)$db->lastInsertId();

            $stmtUser = $db->prepare("SELECT * FROM usuarios WHERE id = ? LIMIT 1");
            $stmtUser->execute([$userId]);
            $user = $stmtUser->fetch();
        }

        // Registra/atualiza em `usuario_identidades_externas`
        $stmtExt = $db->prepare("
            INSERT INTO usuario_identidades_externas (user_id, provider, provider_user_id, email, dados_adicionais)
            VALUES (?, 'google', ?, ?, ?)
            ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP
        ");
        $stmtExt->execute([
            $userId,
            $googleId,
            $googleEmail,
            json_encode(['name' => $googleName, 'picture' => $googlePic], JSON_UNESCAPED_UNICODE)
        ]);

        // Cria sessão
        $sessionToken = bin2hex(random_bytes(32));
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';

        $stmtSess = $db->prepare("
            INSERT INTO sessoes (user_id, session_token, ip_address, user_agent, is_remember, expires_at)
            VALUES (?, ?, ?, ?, 1, DATE_ADD(NOW(), INTERVAL 30 DAY))
        ");
        $stmtSess->execute([$userId, $sessionToken, $ip, $ua]);

        $_SESSION['pl_session_token'] = $sessionToken;
        set_auth_cookie($sessionToken, time() + (30 * 86400));

        $isAdmin = ((int)$user['is_admin'] === 1);
        log_audit('autenticacao', 'Login Google', "Usuário autenticado via conta Google ({$googleEmail})", 'baixo', $userId);

        unset($user['password_hash']);
        $user['avatar'] = pl_avatar_exibicao($user['avatar'] ?? null);
        $user['is_admin'] = $isAdmin ? 1 : 0;

        json_response([
            'success'       => true,
            'message'       => 'Login Google realizado com sucesso!',
            'session_token' => $sessionToken,
            'redirect'      => $isAdmin ? 'dashboard.php' : 'adaptar-busca.php',
            'user'          => $user
        ]);
        break;

    // =========================================================================
    // 6. RECUPERAÇÃO DE SENHA (Solicitar código)
    // =========================================================================
    case 'forgot_password':
        $email = strtolower(trim($data['email'] ?? ''));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            json_error('Informe um endereço de e-mail válido.', 400, ['code' => 'email_invalido']);
        }

        // A mensagem de e-mail não cadastrado revela quais contas existem:
        // limita as consultas por IP antes de acessar o banco.
        pl_auth_forgot_lookup_limit();

        $stmt = $db->prepare("SELECT id, name, status FROM usuarios WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user) {
            json_error(
                'Este e-mail não está cadastrado em nossa plataforma. Verifique o endereço informado ou crie uma conta para continuar.',
                404,
                ['code' => 'email_nao_cadastrado']
            );
        }

        if (($user['status'] ?? 'ativo') !== 'ativo') {
            json_error(
                'Não é possível recuperar a senha desta conta no momento. Entre em contato com o suporte.',
                403,
                ['code' => 'conta_indisponivel']
            );
        }

        pl_auth_otp_ip_limit('forgot_password');
        pl_auth_otp_email_limit($db, 'forgot_password', $email, 'recuperacao_senha');

        $otp = str_pad((string)random_int(100000, 999999), 6, '0', STR_PAD_LEFT);

        $stmtOtp = $db->prepare("
            INSERT INTO codigos_verificacao (user_id, email, codigo, tipo, expires_at)
            VALUES (?, ?, ?, 'recuperacao_senha', DATE_ADD(NOW(), INTERVAL 15 MINUTE))
        ");
        $stmtOtp->execute([$user['id'], $email, $otp]);

        log_audit('autenticacao', 'Código de Recuperação Gerado', "Código solicitado para {$email}", 'baixo', $user['id']);

        // Só chega aqui quando a conta existe e está ativa.
        $respForgot = [
            'success' => true,
            'message' => 'Código de recuperação enviado. Verifique sua caixa de entrada e o spam.',
            'email'   => $email
        ];
        if (defined('APP_ENV') && APP_ENV === 'development') {
            $respForgot['demo_code'] = $otp;
        }

        try {
            pl_respond_then_mail($respForgot, 'recuperacao', $email, (string)($user['name'] ?? ''), $otp, 'recuperacao_senha');
        } catch (\Throwable $e) {
            error_log('[auth] falha ao disparar e-mail de recuperacao: ' . $e->getMessage());
            json_response($respForgot);
        }
        break;

    // =========================================================================
    // 7. REDEFINIÇÃO DE SENHA (Nova senha com validação de token de uso único)
    // =========================================================================
    case 'reset_password':
        $resetToken   = trim($data['reset_token'] ?? '');
        $email        = strtolower(trim($data['email'] ?? ''));
        $novaSenha    = $data['password'] ?? '';
        $confirmSenha = $data['confirm_password'] ?? '';

        if (empty($resetToken)) {
            json_error('Token de autorização para recuperação de senha não informado ou inválido.', 403);
        }
        if (empty($email) || empty($novaSenha)) {
            json_error('E-mail e nova senha são obrigatórios.');
        }
        if ($novaSenha !== $confirmSenha) {
            json_error('As senhas digitadas não coincidem.');
        }
        if (strlen($novaSenha) < 8 || !preg_match('/[A-Z]/', $novaSenha) || !preg_match('/[0-9]/', $novaSenha)) {
            json_error('A senha deve ter no mínimo 8 caracteres, incluindo pelo menos uma letra maiúscula e um número.');
        }

        // Valida token de uso único vinculado ao usuário/email em codigos_verificacao
        $stmtTok = $db->prepare("
            SELECT id, user_id FROM codigos_verificacao
            WHERE token_autorizacao = ? AND email = ? AND tipo = 'recuperacao_senha'
              AND token_expires_at > NOW()
            ORDER BY id DESC LIMIT 1
        ");
        $stmtTok->execute([$resetToken, $email]);
        $tokenRow = $stmtTok->fetch();

        if (!$tokenRow) {
            json_error('Token de autorização inválido ou expirado. Inicie novamente o processo de recuperação.', 403);
        }

        $stmt = $db->prepare("SELECT id FROM usuarios WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user) {
            json_error('Usuário não encontrado.');
        }

        // Atualiza senha com novo hash Bcrypt
        $novoHash = password_hash($novaSenha, PASSWORD_DEFAULT);
        $stmtUp = $db->prepare("UPDATE usuarios SET password_hash = ?, failed_login_attempts = 0, locked_until = NULL WHERE id = ?");
        $stmtUp->execute([$novoHash, $user['id']]);

        // Invalida o token imediatamente garantindo uso estritamente único
        $db->prepare("UPDATE codigos_verificacao SET token_autorizacao = NULL, token_expires_at = NULL WHERE id = ?")->execute([$tokenRow['id']]);

        // Revoga sessões ativas anteriores por segurança
        $db->prepare("DELETE FROM sessoes WHERE user_id = ?")->execute([$user['id']]);

        log_audit('seguranca', 'Redefinição de Senha Concluída', "Senha redefinida com sucesso para o usuário {$email}", 'medio', $user['id']);

        json_response([
            'success' => true,
            'message' => 'Senha alterada com sucesso! Você já pode realizar o login com a nova senha.'
        ]);
        break;

    // =========================================================================
    // 8. VERIFICAÇÃO DE SESSÃO ATIVA (Server-side validation)
    // =========================================================================
    case 'check':
        $user = get_auth_user();
        if ($user) {
            $headerToken = trim($_SERVER['HTTP_X_SESSION_TOKEN'] ?? '');
            $cookieToken = $_COOKIE['pl_session_token'] ?? '';
            if ($headerToken !== '' && (!is_string($cookieToken) || !hash_equals($cookieToken, $headerToken))) {
                $expiresAt = strtotime($user['expires_at'] ?? '');
                if ($expiresAt !== false && $expiresAt > time()) {
                    set_auth_cookie($headerToken, $expiresAt);
                }
            }

            json_response([
                'authenticated'    => true,
                'user'             => $user,
                'is_admin'         => ((int)$user['is_admin'] === 1),
                'is_primary_admin' => ((int)$user['is_admin'] === 1 && (int)($user['is_primary_admin'] ?? 0) === 1)
            ]);
        } else {
            json_response([
                'authenticated'    => false,
                'user'             => null,
                'is_admin'         => false,
                'is_primary_admin' => false
            ]);
        }
        break;

    // =========================================================================
    // 9. LOGOUT
    // =========================================================================
    case 'logout':
        $token = get_session_token();
        $user = get_auth_user();
        if ($token) {
            $db->prepare("DELETE FROM sessoes WHERE session_token = ?")->execute([$token]);
        }
        if ($user) {
            log_audit('autenticacao', 'Logout do Sistema', "Sessão do usuário \"{$user['name']}\" finalizada", 'baixo', $user['id']);
        }
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        set_auth_cookie('', time() - 3600);

        json_response([
            'success' => true,
            'message' => 'Sessão encerrada com sucesso.'
        ]);
        break;

    default:
        json_error('Ação de autenticação não reconhecida.', 404);
}
