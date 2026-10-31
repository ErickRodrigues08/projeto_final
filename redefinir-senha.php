<?php
/**
 * Passe Livre Suzano — Redefinição de Senha
 * Não redireciona logados — usuário pode precisar redefinir mesmo logado.
 */
require_once __DIR__ . '/api/_db_restore.php';

$isDev = (defined('APP_ENV') && APP_ENV === 'development');
$demoCode = null;
$demoEmail = null;
$demoToken = null;
if ($isDev) {
    try {
        $db = get_db();
        $email = $_GET['email'] ?? '';
        if ($email) {
            $stmt = $db->prepare("SELECT id, email, codigo, token_autorizacao FROM codigos_verificacao WHERE email = ? AND tipo = 'recuperacao_senha' ORDER BY created_at DESC LIMIT 1");
            $stmt->execute([$email]);
            $row = $stmt->fetch();
        } else {
            $stmt = $db->query("SELECT id, email, codigo, token_autorizacao FROM codigos_verificacao WHERE tipo = 'recuperacao_senha' ORDER BY created_at DESC LIMIT 1");
            $row = $stmt->fetch();
            if (!$row) {
                $stmt = $db->query("SELECT id, email, codigo, token_autorizacao FROM codigos_verificacao ORDER BY created_at DESC LIMIT 1");
                $row = $stmt->fetch();
            }
        }
        if ($row) {
            $demoEmail = $row['email'];
            $demoCode  = $row['codigo'];
            $demoToken = $row['token_autorizacao'];
            if (!$demoToken) {
                $demoToken = bin2hex(random_bytes(32));
                $stmtTok = $db->prepare("UPDATE codigos_verificacao SET token_autorizacao = ?, token_expires_at = DATE_ADD(NOW(), INTERVAL 30 MINUTE) WHERE id = ?");
                $stmtTok->execute([$demoToken, $row['id']]);
            }
        }
    } catch (\Throwable $e) {}
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Crie sua Nova Senha — Passe Livre | Rotas e locais acessíveis</title>
  <meta name="description" content="Crie sua nova senha de acesso ao Passe Livre Suzano.">
  <link rel="stylesheet" href="assets/fonts/fonts.css">
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="css/redefinir-senha.css">
  <link rel="icon" type="image/x-icon" href="favicon.ico">
  <link rel="icon" type="image/png" sizes="32x32" href="assets/icons/favicon-32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="assets/icons/favicon-16.png">
  <link rel="apple-touch-icon" href="assets/icons/apple-touch-icon.png">
</head>
<body class="reset-page">
  <a href="#form-redefinir" class="skip-link">Pular para o formulário de redefinição de senha</a>

  <header class="reset-header">
    <a href="index.php" class="reset-brand-link" title="Voltar para a página inicial do Passe Livre">
      <img src="assets/icons/logo.png" alt="Passe Livre Suzano" width="160" height="38" onerror="this.src='assets/icons/logo.svg'">
    </a>
    <div class="reset-header-right">
      <a href="ajuda.php" class="header-action-link" title="Central de Ajuda">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="12" cy="12" r="10"></circle>
          <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path>
          <line x1="12" y1="17" x2="12.01" y2="17"></line>
        </svg>
        <span>Central de Ajuda</span>
      </a>
      <span class="header-divider" aria-hidden="true"></span>
      <a href="login.php" class="header-action-link" title="Voltar ao portal de login">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
          <line x1="19" y1="12" x2="5" y2="12"></line>
          <polyline points="12 19 5 12 12 5"></polyline>
        </svg>
        <span>Voltar ao portal</span>
      </a>
    </div>
  </header>

  <main id="main-content" class="reset-main-container">
    <div class="reset-info-column">
      <div class="step-pill">
        <span class="pill-dot"></span>
        <span class="pill-bar"></span>
        <span>ETAPA FINAL (2 DE 2)</span>
      </div>
      <h1 class="page-title">Crie sua nova senha de<br>acesso</h1>
      <p class="page-subtitle">
        Sua identidade foi verificada com sucesso. Agora escolha uma senha segura para proteger seus dados, preferências de mobilidade e rotas acessíveis salvas.
      </p>
      <div class="status-badge-card">
        <div class="status-badge-left">
          <div class="status-shield-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
              <polyline points="9 12 11 14 15 10"></polyline>
            </svg>
          </div>
          <div class="status-text-block">
            <span class="status-kicker">STATUS DA SOLICITAÇÃO</span>
            <strong class="status-main">Identidade validada com sucesso</strong>
          </div>
        </div>
        <div class="status-badge-right" aria-hidden="true">
          <svg class="status-check-circle" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10"></circle>
            <polyline points="16 10 11 15 8 12"></polyline>
          </svg>
        </div>
      </div>
      <div class="security-cards-grid">
        <div class="security-card">
          <div class="security-card-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
              <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
            </svg>
          </div>
          <div class="security-card-content">
            <strong class="security-card-title">PROTEÇÃO DUPLA</strong>
            <p class="security-card-desc">Criptografia de ponta a ponta na proteção do seu acesso.</p>
          </div>
        </div>
        <div class="security-card">
          <div class="security-card-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
              <line x1="8" y1="21" x2="16" y2="21"></line>
              <line x1="12" y1="17" x2="12" y2="21"></line>
            </svg>
          </div>
          <div class="security-card-content">
            <strong class="security-card-title">ACESSO ÚNICO</strong>
            <p class="security-card-desc">A nova senha valerá para o app e para a web do Passe Livre.</p>
          </div>
        </div>
      </div>
    </div>

    <div class="reset-card-column">
      <div class="redefinir-card">
        <div class="form-card-header">
          <div class="form-header-left">
            <div class="refresh-badge-icon" aria-hidden="true">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="23 4 23 10 17 10"></polyline>
                <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path>
              </svg>
              <span class="refresh-check-badge">✓</span>
            </div>
            <div>
              <h2 class="form-title">Redefinição de Senha</h2>
              <span class="form-subtitle-tag">
                <span class="green-dot"></span>
                Identidade validada com sucesso
              </span>
            </div>
          </div>
          <span class="etapa-final-badge">Etapa final</span>
        </div>

        <?php if ($isDev): ?>
        <div class="demo-code-pill" id="demo-code-pill" data-demo-token="<?= htmlspecialchars($demoToken ?? '') ?>" data-demo-email="<?= htmlspecialchars($demoEmail ?? '') ?>" style="display:flex;align-items:center;gap:10px;background:#fff7ed;border:1px solid #fed7aa;color:#b45309;padding:10px 14px;border-radius:10px;margin-bottom:16px;font-size:12px;font-weight:600;">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
          <span>Modo Demo ativo — Código verificado para <strong><?= htmlspecialchars($demoEmail ?: 'cidadão') ?></strong>: <strong id="demo-code-value"><?= htmlspecialchars($demoCode ?: '123456') ?></strong></span>
        </div>
        <?php endif; ?>

        <form id="form-redefinir" class="reset-form" novalidate>
          <div class="form-group">
            <label for="nova-senha" class="field-label">Nova senha</label>
            <div class="input-pill-wrap">
              <span class="input-left-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                  <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                </svg>
              </span>
              <input type="password" id="nova-senha" class="form-input-pill" placeholder="Digite sua nova senha" required autocomplete="new-password">
              <button type="button" class="btn-eye-toggle" data-target="nova-senha" title="Mostrar/ocultar senha">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                  <circle cx="12" cy="12" r="3"></circle>
                </svg>
              </button>
            </div>
          </div>

          <div class="form-group">
            <label for="confirm-senha" class="field-label">Confirmar nova senha</label>
            <div class="input-pill-wrap">
              <span class="input-left-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                  <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                </svg>
              </span>
              <input type="password" id="confirm-senha" class="form-input-pill" placeholder="Repita a nova senha" required autocomplete="new-password">
              <button type="button" class="btn-eye-toggle" data-target="confirm-senha" title="Mostrar/ocultar senha">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                  <circle cx="12" cy="12" r="3"></circle>
                </svg>
              </button>
            </div>
            <span class="field-error" id="confirm-error" hidden></span>
          </div>

          <div class="requirements-card">
            <div class="requirements-header">
              <span class="req-title">SUA SENHA DEVE CONTER:</span>
              <span class="req-counter" id="req-counter">0 de 3 requisitos</span>
            </div>
            <ul class="requirements-items">
              <li class="req-item" id="req-len"><span class="req-circle"></span><span>No mínimo 8 caracteres</span></li>
              <li class="req-item" id="req-mix"><span class="req-circle"></span><span>Pelo menos uma letra maiúscula e um número</span></li>
              <li class="req-item" id="req-spec"><span class="req-circle"></span><span>Um caractere especial (!, @, #, $, %, etc.)</span></li>
            </ul>
          </div>

          <button type="submit" class="btn-redefinir-final" id="btn-redefinir">
            <span>Redefinir senha</span>
            <span class="btn-arrow" aria-hidden="true">&rarr;</span>
          </button>

          <a href="login.php" class="link-cancelar">&lt; Cancelar e voltar para o login</a>

          <div class="ssl-trust-footer">
            <svg class="ssl-icon" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
            </svg>
            <span>Ambiente seguro com certificação SSL de 256 bits</span>
          </div>
        </form>
      </div>
    </div>
  </main>

  <footer class="reset-page-footer">
    <div class="footer-left">
      &copy; 2026 Passe Livre. Todos os direitos reservados. Projeto SENAI Suzano.
    </div>
    <div class="footer-right">
      <a href="direitos.php#termos">Termos de Uso</a>
      <a href="direitos.php#privacidade">Privacidade de Dados</a>
    </div>
  </footer>

  <div class="reset-toast" id="reset-toast" role="status" aria-live="polite">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
      <polyline points="20 6 9 17 4 12"></polyline>
    </svg>
    <span id="reset-toast-message">Operação realizada!</span>
  </div>

  <script src="js/db.js"></script>
  <script src="js/db-queries.js"></script>
  <script src="js/auth.js"></script>
  <script src="js/redefinir-senha.js"></script>
</body>
</html>
