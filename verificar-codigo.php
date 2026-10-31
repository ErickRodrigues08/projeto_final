<?php
/**
 * Passe Livre Suzano — Verificação de Código OTP
 * Redireciona usuário já autenticado de volta ao painel.
 */
require_once __DIR__ . '/includes/auth_guard.php';
require_once __DIR__ . '/includes/otp_cooldown.php';
auth_redirect_if_logged();

$isDev     = (defined('APP_ENV') && APP_ENV === 'development');
$emailGet  = htmlspecialchars($_GET['email'] ?? '', ENT_QUOTES, 'UTF-8');
$flowGet   = htmlspecialchars($_GET['flow']  ?? 'cadastro', ENT_QUOTES, 'UTF-8');

$demoCode  = null;
$otpExpiresAt = null; // timestamp Unix da expiração do código atual
$otpResendRemaining = 0; // segundos até liberar novo código para ESTE e-mail
try {
    $db    = get_db();
    $email = $_GET['email'] ?? '';
    $flow  = $_GET['flow']  ?? 'cadastro';
    $tipo  = ($flow === 'recuperacao' || $flow === 'recuperacao_senha') ? 'recuperacao_senha' : 'cadastro';

    $otpResendRemaining = pl_otp_cooldown_remaining($db, $email, $tipo);

    if ($email) {
        $stmt = $db->prepare("SELECT codigo, expires_at FROM codigos_verificacao WHERE email = ? AND tipo = ? AND usado = 0 ORDER BY created_at DESC LIMIT 1");
        $stmt->execute([$email, $tipo]);
    } else {
        $stmt = $db->query("SELECT codigo, expires_at FROM codigos_verificacao WHERE usado = 0 ORDER BY created_at DESC LIMIT 1");
    }
    $row = $stmt->fetch();
    if ($row) {
        if ($isDev) $demoCode = $row['codigo'];
        if (!empty($row['expires_at'])) {
            $otpExpiresAt = strtotime($row['expires_at']);
        }
    }
} catch (\Throwable $e) {}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Verificar Código — Passe Livre | Rotas e locais acessíveis</title>
  <meta name="description" content="Confirme o código de verificação enviado para seu e-mail e conclua o acesso ao Passe Livre Suzano.">
  <link rel="stylesheet" href="assets/fonts/fonts.css">
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="css/verificar-codigo.css">
  <link rel="icon" type="image/x-icon" href="favicon.ico">
  <link rel="icon" type="image/png" sizes="32x32" href="assets/icons/favicon-32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="assets/icons/favicon-16.png">
  <link rel="apple-touch-icon" href="assets/icons/apple-touch-icon.png">
</head>
<body class="verify-page">
  <a href="#otp-form" class="skip-link">Pular para o formulário de verificação</a>

  <svg class="verify-bg" viewBox="0 0 1920 1011" preserveAspectRatio="xMidYMid slice" aria-hidden="true">
    <path d="M0 300 C 180 420, 420 440, 560 300 C 620 240, 680 130, 712 0 L0 0 Z" fill="#f7e6c8"/>
    <path d="M0 1011 L0 960 C 300 1000, 560 1000, 900 800 C 1250 590, 1500 520, 1700 660 C 1800 730, 1860 800, 1920 880 L1920 1011 Z" fill="#f7e6c8"/>
  </svg>

  <header class="verify-header">
    <a href="index.php" class="verify-brand-link" title="Voltar para a página inicial">
      <img src="assets/icons/logo.png" alt="Passe Livre" width="160" height="80" onerror="this.src='assets/icons/logo.svg'">
    </a>
    <div class="verify-header-right">
      <a href="login.php" class="header-user-badge" title="Voltar para o login">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <line x1="19" y1="12" x2="5" y2="12"></line>
          <polyline points="12 19 5 12 12 5"></polyline>
        </svg>
        Voltar ao login
      </a>
    </div>
  </header>

  <main id="main-content" class="verify-main-container">

    <!-- Coluna Esquerda: Informações -->
    <div class="verify-info-column">
      <div class="verify-eyebrow-pill">
        <span class="verify-eyebrow-dot" aria-hidden="true"></span>
        VERIFICAÇÃO EM DUAS ETAPAS
      </div>

      <h1 class="verify-heading">Confirme seu<br>endereço de e-mail</h1>

      <p class="verify-description">
        Para garantir a segurança da sua conta, enviamos um código de 6 dígitos para o e-mail informado. Insira o código abaixo para continuar.
      </p>

      <div class="verify-status-card">
        <div class="verify-status-icon-wrap" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
            <polyline points="22,6 12,13 2,6"></polyline>
          </svg>
        </div>
        <div class="verify-status-content">
          <span class="verify-status-title">CÓDIGO ENVIADO</span>
          <p class="verify-status-desc">
            Verifique a caixa de entrada de <strong id="email-display"><?= $emailGet ?: 'seu e-mail' ?></strong>. O código expira em 15 minutos.
          </p>
        </div>
      </div>
    </div>

    <!-- Coluna Direita: Card OTP -->
    <div class="verify-card-column">
      <div class="verify-otp-card">

        <div class="verify-card-badge" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
            <polyline points="22,6 12,13 2,6"></polyline>
          </svg>
        </div>

        <h2 class="verify-card-title">Digite o código</h2>
        <p class="verify-card-subtitle">
          Enviamos o código para<br>
          <span class="email-target" id="card-email-target"><?= $emailGet ?: 'seu e-mail' ?></span>
        </p>

        <form class="verify-form" id="otp-form" novalidate>
          <div class="otp-inputs-grid" id="otp-inputs-grid" role="group" aria-label="Código de verificação de 6 dígitos">
            <input type="text" inputmode="numeric" maxlength="1" pattern="[0-9]" class="otp-digit-input" id="otp-0" autocomplete="one-time-code" aria-label="Dígito 1 do código" placeholder="·">
            <input type="text" inputmode="numeric" maxlength="1" pattern="[0-9]" class="otp-digit-input" id="otp-1" aria-label="Dígito 2 do código" placeholder="·">
            <input type="text" inputmode="numeric" maxlength="1" pattern="[0-9]" class="otp-digit-input" id="otp-2" aria-label="Dígito 3 do código" placeholder="·">
            <input type="text" inputmode="numeric" maxlength="1" pattern="[0-9]" class="otp-digit-input" id="otp-3" aria-label="Dígito 4 do código" placeholder="·">
            <input type="text" inputmode="numeric" maxlength="1" pattern="[0-9]" class="otp-digit-input" id="otp-4" aria-label="Dígito 5 do código" placeholder="·">
            <input type="text" inputmode="numeric" maxlength="1" pattern="[0-9]" class="otp-digit-input" id="otp-5" aria-label="Dígito 6 do código" placeholder="·">
          </div>

          <p class="otp-feedback-msg" id="otp-feedback" role="alert" aria-live="polite"></p>

          <!-- Timer de expiração do código -->
          <p class="otp-expiry-timer" id="otp-expiry-timer" aria-live="polite"></p>

          <?php if ($isDev): ?>
          <div class="demo-code-pill" id="demo-code-pill" title="Clique para preencher automaticamente (somente modo demo)">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
              <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
              <line x1="12" y1="9" x2="12" y2="13"/>
              <line x1="12" y1="17" x2="12.01" y2="17"/>
            </svg>
            Modo demo — código: <strong id="demo-code-value"><?= htmlspecialchars($demoCode ?: 'carregando…') ?></strong>
          </div>
          <?php if ($demoCode): ?>
          <script>
            // Garante que o código PHP esteja disponível na sessionStorage para o verificar-codigo.js
            sessionStorage.setItem('passeLivre_verify_code', <?= json_encode($demoCode) ?>);
          </script>
          <?php endif; ?>
          <?php endif; ?>

          <button type="submit" class="btn-submit-verify" id="btn-verify" disabled>
            <span class="btn-spinner" aria-hidden="true"></span>
            <span class="btn-text">Confirmar código</span>
          </button>
        </form>

        <div class="verify-resend-area" id="verify-resend-area">
          <p class="resend-timer-text" id="resend-timer-text">
            Reenviar código em <strong id="resend-countdown">30</strong>s
          </p>
          <button type="button" class="btn-resend-action" id="btn-resend" hidden>
            Reenviar código
          </button>
        </div>

        <div class="verify-links-footer">
          <a href="cadastro.php" class="verify-back-link">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <line x1="19" y1="12" x2="5" y2="12"></line>
              <polyline points="12 19 5 12 12 5"></polyline>
            </svg>
            Voltar ao cadastro
          </a>
          <a href="cadastro.php" class="verify-change-email">
            Usar outro e-mail
          </a>
        </div>

      </div>
    </div>

  </main>

  <div class="verify-toast" id="verify-toast" role="status" aria-live="polite">
    <svg class="verify-toast-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
      <polyline points="20 6 9 17 4 12"></polyline>
    </svg>
    <span id="verify-toast-message">Operação realizada!</span>
  </div>

  <script src="js/db.js"></script>
  <script src="js/db-queries.js"></script>
  <script src="js/auth.js"></script>
  <!-- Variáveis passadas pelo PHP: expiração do código e cooldown de reenvio do e-mail informado -->
  <script>
    window.plOtpExpiresAt       = <?= json_encode($otpExpiresAt) ?>;
    window.plOtpResendRemaining = <?= json_encode($otpResendRemaining) ?>;
    window.plOtpResendCooldown  = <?= json_encode(PL_OTP_COOLDOWN_SECONDS) ?>;
  </script>
  <script src="js/verificar-codigo.js"></script>
</body>
</html>
