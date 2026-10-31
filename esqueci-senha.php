<?php
/**
 * Passe Livre Suzano — Recuperação de Senha
 * Redireciona usuário já autenticado.
 */
require_once __DIR__ . '/includes/auth_guard.php';
auth_redirect_if_logged();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Esqueceu sua senha? — Passe Livre | Rotas e locais acessíveis</title>
  <meta name="description" content="Recupere o acesso à sua conta no Passe Livre Suzano: informe seu e-mail e receba um código para redefinir a senha.">
  <link rel="stylesheet" href="assets/fonts/fonts.css">
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="css/login.css">
  <link rel="stylesheet" href="css/esqueci-senha.css">
  <link rel="icon" type="image/x-icon" href="favicon.ico">
  <link rel="icon" type="image/png" sizes="32x32" href="assets/icons/favicon-32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="assets/icons/favicon-16.png">
  <link rel="apple-touch-icon" href="assets/icons/apple-touch-icon.png">
</head>
<body class="login-page recover-page">
  <a href="#form-recuperar" class="skip-link">Pular para o formulário de recuperação</a>

  <svg class="recover-bg" viewBox="0 0 1920 1011" preserveAspectRatio="xMidYMid slice" aria-hidden="true">
    <path d="M0 300 C 180 420, 420 440, 560 300 C 620 240, 680 130, 712 0 L0 0 Z" fill="#f7e6c8"/>
    <path d="M0 1011 L0 960 C 300 1000, 560 1000, 900 800 C 1250 590, 1500 520, 1700 660 C 1800 730, 1860 800, 1920 880 L1920 1011 Z" fill="#f7e6c8"/>
  </svg>

  <header class="login-header">
    <a href="index.php" class="login-brand-link" title="Voltar para a página inicial">
      <img src="assets/icons/logo.png" alt="Passe Livre" width="160" height="38" onerror="this.src='assets/icons/logo.svg'">
    </a>
  </header>

  <main id="main-content" class="login-main-container">
    <div class="login-illustration-column">
      <img src="assets/images/recover-illustration.png" alt="Ilustração de uma pessoa pensativa segurando o celular" class="login-vector-illustration recover-illustration">
    </div>

    <div class="login-card-column">
      <div class="login-form-card recover-card">
        <div class="login-title-wrap">
          <h1 class="login-card-title">Esqueceu sua senha?</h1>
          <p class="login-card-subtitle">Digite seu e-mail para redefinir sua senha</p>
        </div>

        <form id="form-recuperar" novalidate>
          <div class="form-field-item">
            <label for="rec-email" class="sr-only">E-mail</label>
            <input type="email" id="rec-email" class="form-input-login recover-input" placeholder="E-mail" required autocomplete="email" aria-describedby="rec-error" aria-invalid="false">
            <span class="recover-error" id="rec-error" role="alert" hidden></span>
          </div>
          <button type="submit" class="btn-submit-login" id="btn-enviar-codigo">Enviar código</button>
          <p class="recover-hint" id="rec-hint" hidden></p>
        </form>

        <a href="login.php" class="recover-back-link">
          <span aria-hidden="true">&lt;</span> voltar para a página de login
        </a>
      </div>
    </div>
  </main>

  <div class="toast-notice" id="app-toast">
    <img src="assets/icons/check.svg" width="18" height="18" alt="">
    <span id="toast-message">Operação realizada com sucesso!</span>
  </div>

    <script src="js/db.js"></script>
  <script src="js/db-queries.js"></script>
  <script src="js/auth.js"></script>
  <script src="js/esqueci-senha.js"></script>
</body>
</html>
