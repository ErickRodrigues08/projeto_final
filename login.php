<?php
/**
 * Passe Livre Suzano — Página de Login
 * Redireciona usuário já autenticado para sua área.
 */
require_once __DIR__ . '/includes/auth_guard.php';
auth_redirect_if_logged(); // Redireciona se já estiver logado
$googleClientId = trim((string) pl_get_env('GOOGLE_CLIENT_ID', ''));
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Entrar na sua conta — Passe Livre | Rotas e locais acessíveis</title>
  <meta name="description" content="Acesse sua conta no Passe Livre Suzano para descobrir rotas acessíveis, locais adaptados e transporte público inclusivo.">
  <link rel="stylesheet" href="assets/fonts/fonts.css">
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="css/login.css?v=5.5">
  <link rel="stylesheet" href="css/auth-polish.css?v=5.5">
  <link rel="icon" type="image/x-icon" href="favicon.ico">
  <link rel="icon" type="image/png" sizes="32x32" href="assets/icons/favicon-32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="assets/icons/favicon-16.png">
  <link rel="apple-touch-icon" href="assets/icons/apple-touch-icon.png">
</head>
<body class="login-page">
  <a href="#form-login" class="skip-link">Pular para o formulário de login</a>

  <main id="main-content" class="login-main-container">
    <div class="login-illustration-column">
      <aside class="auth-panel auth-panel--photo" aria-label="Apresentação do Passe Livre">
        <span class="auth-panel-kicker">Passe Livre Suzano · Mobilidade acessível</span>
        <p class="auth-panel-title">O caminho livre começa <em>com um acesso.</em></p>
        <p class="auth-panel-text">Passe Livre — Rotas e locais acessíveis, a um toque de distância.</p>
      </aside>
    </div>

    <div class="login-card-column">
      <div class="login-form-card">
        <a href="index.php" class="auth-card-brand" title="Voltar para a página inicial">
          <img src="assets/icons/logo.png" alt="Passe Livre" onerror="this.src='assets/icons/logo.svg'">
        </a>
        <div class="login-title-wrap">
          <h1 class="login-card-title">Bem-vindo!</h1>
          <p class="login-card-subtitle">Acesse a sua conta</p>
        </div>

        <?php if (isset($_GET['next']) && $_GET['next']): ?>
        <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:8px;padding:8px 12px;font-size:12px;color:#92400e;margin-bottom:14px;">
          Faça login para continuar.
        </div>
        <?php endif; ?>

        <form id="form-login" novalidate>
          <div class="form-field-item">
            <label for="log-email" class="form-label-visible">E-mail</label>
            <input type="email" id="log-email" class="form-input-login" placeholder="seuemail@exemplo.com" required autocomplete="email">
          </div>

          <div class="form-field-item">
            <label for="log-senha" class="form-label-visible">Senha</label>
            <div class="password-input-wrap">
              <input type="password" id="log-senha" class="form-input-login" placeholder="Sua senha" required autocomplete="current-password">
              <button type="button" class="btn-toggle-eye" data-target="log-senha" title="Alternar visibilidade da senha" aria-label="Alternar visibilidade da senha">
                <img src="assets/icons/eye.svg" alt="Visualizar senha" aria-hidden="true">
              </button>
            </div>
          </div>

          <div class="login-options-row">
            <label class="remember-checkbox-wrap" for="log-lembrar">
              <input type="checkbox" id="log-lembrar" class="custom-checkbox-input" aria-label="Manter-me conectado">
              <span>Manter-me conectado?</span>
            </label>
            <a href="esqueci-senha.php" class="login-forgot-link">Esqueceu a senha?</a>
          </div>

          <button type="submit" class="btn-submit-login">Entrar na conta</button>

          <button type="button" id="btn-admin-test" class="btn-admin-test" aria-label="Acesso rápido à área administrativa (teste)">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
              <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
            </svg>
            <span>Acesso Admin (teste)</span>
          </button>

          <div class="login-card-footer">
            <span>Não tem uma conta?</span>
            <a href="cadastro.php" class="login-signup-link">Cadastrar-se</a>
          </div>

          <div class="login-terms-row">
            <button type="button" class="login-terms-link" id="btn-open-termos" aria-haspopup="dialog">Termos de Uso</button>
            <span aria-hidden="true">·</span>
            <button type="button" class="login-terms-link" id="btn-open-privacidade" aria-haspopup="dialog">Política de Privacidade</button>
          </div>

          <?php if ($googleClientId !== ''): ?>
          <div class="login-divider"><span>Ou acessar com</span></div>

          <!-- LOGIN COM GOOGLE — Google Identity Services (OAuth real) -->
          <div id="g_id_onload"
               data-client_id="<?= htmlspecialchars($googleClientId, ENT_QUOTES, 'UTF-8') ?>"
               data-context="signin"
               data-ux_mode="popup"
               data-callback="plHandleGoogleCredential"
               data-auto_prompt="false">
          </div>

          <button type="button" class="btn-google-login" id="btn-google-login" aria-label="Continuar com Google">
            <svg width="18" height="18" viewBox="0 0 18 18" aria-hidden="true">
              <path fill="#4285F4" d="M17.64 9.2c0-.64-.06-1.25-.16-1.84H9v3.48h4.84c-.21 1.13-.84 2.08-1.8 2.72v2.26h2.91c1.7-1.57 2.69-3.88 2.69-6.62z"/>
              <path fill="#34A853" d="M9 18c2.43 0 4.47-.8 5.96-2.18l-2.91-2.26c-.81.54-1.84.86-3.05.86-2.34 0-4.33-1.58-5.04-3.71H.96v2.33C2.44 15.98 5.48 18 9 18z"/>
              <path fill="#FBBC05" d="M3.96 10.71c-.18-.54-.28-1.11-.28-1.71s.1-1.17.28-1.71V4.96H.96A8.996 8.996 0 000 9c0 1.45.35 2.83.96 4.04l3-2.33z"/>
              <path fill="#EA4335" d="M9 3.58c1.32 0 2.51.45 3.44 1.35l2.58-2.58C13.46.89 11.43 0 9 0 5.48 0 2.44 2.02.96 4.96l3 2.33C4.67 5.16 6.66 3.58 9 3.58z"/>
            </svg>
            <span>Continuar com Google</span>
          </button>
          <?php endif; ?>
        </form>
      </div>
    </div>
  </main>

  <div class="toast-notice" id="app-toast">
    <img src="assets/icons/check.svg" width="18" height="18" alt="">
    <span id="toast-message">Operação realizada com sucesso!</span>
  </div>


  <?php if ($googleClientId !== ''): ?>
  <!-- Google Identity Services SDK (necessário para login com Google real) -->
  <script src="https://accounts.google.com/gsi/client" async defer></script>
  <?php endif; ?>
  <script src="js/db.js" defer></script>
  <script src="js/db-queries.js" defer></script>
  <script src="js/mock-data.js" defer></script>
  <script src="js/auth.js"></script>
  <script src="js/login.js"></script>

  <!-- =====================================================================
       MODAIS: Termos de Uso e Política de Privacidade
       ===================================================================== -->
  <div class="login-modal-backdrop" id="modal-termos" role="dialog" aria-modal="true" aria-labelledby="modal-termos-title" hidden>
    <div class="login-modal-card" role="document">
      <div class="login-modal-header">
        <h2 class="login-modal-title" id="modal-termos-title">Termos de Uso</h2>
        <button type="button" class="login-modal-close" id="btn-close-termos" aria-label="Fechar Termos de Uso">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
          </svg>
        </button>
      </div>
      <div class="login-modal-body">
        <h3>1. Objeto e Finalidade da Plataforma</h3>
        <p>O <strong>Passe Livre Suzano</strong> é uma solução tecnológica voltada à promoção da mobilidade urbana inclusiva, mapeamento de rotas acessíveis, auditoria colaborativa de calçadas e fiscalização de barreiras arquitetônicas no território do município de Suzano, desenvolvida em parceria acadêmica com o SENAI Suzano.</p>
        <h3>2. Cadastro e Responsabilidade do Usuário</h3>
        <p>Ao realizar o cadastro, o cidadão compromete-se a fornecer informações verdadeiras e atualizadas. É vedada a utilização de contas falsas, bem como o reporte deliberado de obstáculos ou barreiras inverídicas. O cidadão é responsável pela confidencialidade de sua senha de acesso.</p>
        <h3>3. Reporte Comunitário e Avaliações de Acessibilidade</h3>
        <p>As avaliações e imagens enviadas por usuários sobre estabelecimentos, calçadas e travessias integram o banco público de acessibilidade do município. O conteúdo submetido não deve conter material difamatório, ofensivo ou violação de direitos autorais de terceiros.</p>
        <h3>4. Cálculo de Rotas e Navegação Assistida</h3>
        <p>As rotas sugeridas pela plataforma priorizam a eliminação de barreiras arquitetônicas com base nas informações geográficas disponíveis e nos laudos da comunidade. Em casos de obras emergenciais não reportadas, o cidadão deve sempre priorizar sua integridade física.</p>
        <h3>5. Legislação Aplicável e Foro</h3>
        <p>Estes Termos são regidos pelas leis da República Federativa do Brasil, em especial pela Lei Brasileira de Inclusão (Lei nº 13.146/2015), pelo Marco Civil da Internet (Lei nº 12.965/2014) e pela LGPD (Lei nº 13.709/2018). Foro: Comarca de Suzano — SP.</p>
        <p class="login-modal-updated">Última atualização: Outubro de 2026 — Versão 2.6</p>
      </div>
    </div>
  </div>

  <div class="login-modal-backdrop" id="modal-privacidade" role="dialog" aria-modal="true" aria-labelledby="modal-privacidade-title" hidden>
    <div class="login-modal-card" role="document">
      <div class="login-modal-header">
        <h2 class="login-modal-title" id="modal-privacidade-title">Política de Privacidade</h2>
        <button type="button" class="login-modal-close" id="btn-close-privacidade" aria-label="Fechar Política de Privacidade">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
          </svg>
        </button>
      </div>
      <div class="login-modal-body">
        <h3>1. Compromisso com a Privacidade</h3>
        <p>A Prefeitura Municipal de Suzano e o SENAI Suzano têm o compromisso irrestrito de proteger a privacidade e os dados pessoais de todos os cidadãos que utilizam a plataforma <strong>Passe Livre Suzano</strong>, em estrito cumprimento à Lei Geral de Proteção de Dados Pessoais (LGPD — Lei Federal nº 13.709/2018).</p>
        <h3>2. Dados Coletados e Finalidade</h3>
        <ul>
          <li><strong>Dados Cadastrais:</strong> Nome completo, e-mail, telefone e cidade. Finalidade: identificação do cidadão e autenticação segura.</li>
          <li><strong>Dados de Saúde e PcD:</strong> Categoria de deficiência ou mobilidade reduzida. Finalidade: personalização de rotas acessíveis.</li>
          <li><strong>Dados de Localização (GPS):</strong> Coletados apenas mediante autorização ativa do usuário durante a navegação.</li>
        </ul>
        <h3>3. Segurança da Informação</h3>
        <p>Todas as senhas são armazenadas com hash criptográfico irreversível <code>Bcrypt</code>. Sessões e tokens de recuperação possuem tempo de vida limitado (15 minutos) e são gerados com geradores pseudoaleatórios criptograficamente seguros (CSPRNG).</p>
        <h3>4. Direitos do Titular de Dados</h3>
        <p>Em conformidade com o Art. 18 da LGPD, o cidadão pode a qualquer momento: confirmar a existência de tratamento de seus dados, acessar, corrigir ou solicitar a exclusão definitiva de sua conta.</p>
        <h3>5. Contato do Encarregado (DPO)</h3>
        <p>Para exercer seus direitos ou esclarecer dúvidas, entre em contato com o Encarregado pelo e-mail <strong>dpo@suzano.sp.gov.br</strong>.</p>
        <p class="login-modal-updated">Em vigor desde Outubro de 2026 • LGPD — Lei nº 13.709/2018</p>
      </div>
    </div>
  </div>

  <script>
    // =========================================================================
    // Modais: Termos de Uso e Política de Privacidade
    // =========================================================================
    (function () {
      function openModal(id) {
        var modal = document.getElementById(id);
        if (!modal) return;
        modal.hidden = false;
        modal.classList.add('active');
        // Foca no botão de fechar para acessibilidade por teclado
        var closeBtn = modal.querySelector('.login-modal-close');
        if (closeBtn) closeBtn.focus();
        document.body.style.overflow = 'hidden';
      }

      function closeModal(id) {
        var modal = document.getElementById(id);
        if (!modal) return;
        modal.classList.remove('active');
        modal.hidden = true;
        document.body.style.overflow = '';
      }

      // Abre modais pelos botões
      var btnTermos = document.getElementById('btn-open-termos');
      var btnPriv   = document.getElementById('btn-open-privacidade');
      if (btnTermos) btnTermos.addEventListener('click', function () { openModal('modal-termos'); });
      if (btnPriv)   btnPriv.addEventListener('click',   function () { openModal('modal-privacidade'); });

      // Fecha pelos botões de fechar internos
      var btnCloseTermos = document.getElementById('btn-close-termos');
      var btnClosePriv   = document.getElementById('btn-close-privacidade');
      if (btnCloseTermos) btnCloseTermos.addEventListener('click', function () { closeModal('modal-termos'); });
      if (btnClosePriv)   btnClosePriv.addEventListener('click',   function () { closeModal('modal-privacidade'); });

      // Fecha ao clicar fora do card (backdrop)
      ['modal-termos', 'modal-privacidade'].forEach(function (id) {
        var modal = document.getElementById(id);
        if (!modal) return;
        modal.addEventListener('click', function (e) {
          if (e.target === modal) closeModal(id);
        });
      });

      // Fecha com tecla Escape
      document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
          closeModal('modal-termos');
          closeModal('modal-privacidade');
        }
      });
    })();
  </script>
  <script src="https://cdn.jsdelivr.net/npm/gsap@3.12.5/dist/gsap.min.js" defer></script>
  <script src="https://cdn.jsdelivr.net/npm/gsap@3.12.5/dist/ScrollTrigger.min.js" defer></script>
  <script src="js/entrada-animacoes.js" defer></script>
</body>
</html>
