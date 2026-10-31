<?php
/**
 * Passe Livre Suzano — Perfil do Cidadão (Detalhes)
 * Proteção server-side: restrito a administradores e gestores.
 */
require_once __DIR__ . '/includes/auth_guard.php';
$gestorUserRaw = auth_require_admin();
$gestorUser    = auth_user_safe($gestorUserRaw);
$activePage    = 'perfil-gestor';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Perfil do Cidadão — Painel do Gestor | Passe Livre</title>
  <meta name="description" content="Visualização completa do perfil do cidadão, documentação médica e histórico de solicitações.">

  <link rel="stylesheet" href="assets/fonts/fonts.css">
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="css/gestor.css">
  <link rel="icon" type="image/x-icon" href="favicon.ico">
  <link rel="icon" type="image/png" sizes="32x32" href="assets/icons/favicon-32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="assets/icons/favicon-16.png">
  <link rel="apple-touch-icon" href="assets/icons/apple-touch-icon.png">
</head>
<body>
  <a href="#main-content" class="skip-link">Pular para o conteúdo principal</a>

  <div class="gestor-layout-wrapper">

    <!-- SIDEBAR DO GESTOR -->
    <?php require __DIR__ . '/includes/sidebar_gestor.php'; ?>

    <!-- ÁREA PRINCIPAL -->
    <div class="gestor-content-area">

      <header class="gestor-top-bar">
        <div class="gestor-title-crumb-wrap">
          <div class="gestor-breadcrumb-text">
            Passe Livre /
            <a href="perfil-gestor.php" style="color:#a8a29e;text-decoration:none;">Usuários</a>
            / <span id="breadcrumb-nome">Carregando...</span>
          </div>
          <h1 class="gestor-main-heading" id="page-title">Perfil do Cidadão</h1>
        </div>

        <div class="gestor-header-search-wrap">
          <img src="assets/icons/search.svg" alt="" aria-hidden="true">
          <input type="search" placeholder="Buscar cidadão..." aria-label="Buscar cidadão">
        </div>

        <div style="display:flex;align-items:center;gap:18px;">
          <button class="btn-icon-notify" id="btn-notify-top" title="Notificações" onclick="window.location.href='notificacoes.php'">
            <img src="assets/icons/bell.svg" alt="">
          </button>
          <div class="gestor-header-profile-tag" style="cursor:pointer;" onclick="window.location.href='dashboard.php'">
            <div class="gestor-avatar-badge-circle"><?= htmlspecialchars($gestorUser['initials'] ?? 'AD') ?></div>
            <div class="gestor-admin-tag-text">
              <?= pl_admin_header_profile($gestorUser) ?>
            </div>
          </div>
        </div>
      </header>

      <!-- Corpo — todo preenchido via JS -->
      <main class="gestor-page-body" id="main-content">

        <!-- Toolbar de ações -->
        <div class="gestor-action-toolbar">
          <a href="perfil-gestor.php" class="btn-toolbar-back" id="btn-voltar-lista">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/>
            </svg>
            Voltar para a lista
          </a>

          <div class="toolbar-buttons-right">
            <button type="button" class="btn-gestor-action btn-gestor-amber" id="btn-send-notification">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12 19.79 19.79 0 0 1 1.61 3.4 2 2 0 0 1 3.6 1.22h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 8.8a16 16 0 0 0 6 6l.86-.86a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 21.59 16"/></svg>
              <span>Enviar Notificação</span>
            </button>
            <button type="button" class="btn-gestor-action btn-gestor-danger" id="btn-block-account">
              <img src="assets/icons/slash-circle.svg" alt="" style="width:13px;height:13px;filter:brightness(0) invert(1);">
              <span>Bloquear Conta</span>
            </button>
          </div>
        </div>

        <!-- Hero card do usuário -->
        <section class="user-hero-card" id="hero-card" aria-label="Resumo do Cidadão">
          <!-- Preenchido por JS -->
        </section>

        <!-- Abas -->
        <nav class="gestor-tabs-nav" aria-label="Seções do Perfil">
          <button type="button" class="tab-nav-btn active" data-tab="pessoal">
            <img src="assets/icons/user.svg" alt="" aria-hidden="true">
            <span>Informações Pessoais</span>
          </button>
          <button type="button" class="tab-nav-btn" data-tab="solicitacoes">
            <img src="assets/icons/file-text.svg" alt="" aria-hidden="true">
            <span id="tab-sol-label">Solicitações</span>
          </button>
          <button type="button" class="tab-nav-btn" data-tab="auditoria">
            <img src="assets/icons/history.svg" alt="" aria-hidden="true">
            <span>Auditoria</span>
          </button>
        </nav>

        <!-- Conteúdo das abas -->
        <div id="tab-pessoal" class="tab-content">
          <div class="profile-grid-2col" id="grid-pessoal">
            <!-- Preenchido por JS -->
          </div>
        </div>

        <div id="tab-solicitacoes" class="tab-content" style="display:none;">
          <div class="gestor-section-card" id="card-solicitacoes">
            <!-- Preenchido por JS -->
          </div>
        </div>

        <div id="tab-auditoria" class="tab-content" style="display:none;">
          <div class="gestor-section-card" id="card-auditoria">
            <!-- Preenchido por JS -->
          </div>
        </div>

      </main>

      <footer class="site-footer" role="contentinfo" style="margin-top:auto;">
        <div class="footer-credits">
          &copy; 2026 Passe Livre. Todos os direitos reservados.
        </div>
        <div class="footer-links">
          <a href="#" class="footer-link-item">Termos de uso</a>
          <span>&bull;</span>
          <a href="ajuda.php" class="footer-link-item">Suporte</a>
        </div>
      </footer>
    </div>
  </div>

  <!-- MODAL: ENVIAR NOTIFICAÇÃO AO CIDADÃO -->
  <div id="modal-notificacao" class="modal-overlay" role="dialog" aria-modal="true" aria-labelledby="modal-notif-title" style="display:none;opacity:0;pointer-events:none;">
    <div class="modal-window" style="max-width:560px;width:90%;border-radius:20px;background:#fff;box-shadow:0 24px 60px rgba(0,0,0,.2);">

      <div style="padding:22px 26px 0;display:flex;align-items:flex-start;justify-content:space-between;gap:12px;">
        <div style="display:flex;align-items:center;gap:12px;">
          <div style="width:44px;height:44px;border-radius:12px;background:#fff7ed;border:1.5px solid #fed7aa;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#d97706" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12 19.79 19.79 0 0 1 1.61 3.4 2 2 0 0 1 3.6 1.22h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 8.8a16 16 0 0 0 6 6l.86-.86a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 21.59 16"/>
            </svg>
          </div>
          <div>
            <h3 id="modal-notif-title" style="font-size:16px;font-weight:700;color:#1c1917;margin:0;">Enviar Notificação</h3>
            <p style="font-size:12px;color:#78716c;margin:2px 0 0;" id="modal-notif-destinatario">Para: —</p>
          </div>
        </div>
        <button type="button" id="btn-close-modal-notif" aria-label="Fechar"
          style="width:32px;height:32px;background:#f5f5f4;border:none;border-radius:8px;cursor:pointer;font-size:18px;color:#57534e;display:flex;align-items:center;justify-content:center;flex-shrink:0;">&times;</button>
      </div>

      <div style="padding:20px 26px 24px;display:flex;flex-direction:column;gap:18px;">

        <div>
          <p style="font-size:12px;font-weight:700;color:#78716c;text-transform:uppercase;letter-spacing:.4px;margin:0 0 10px;">Notificações pré-definidas — clique para selecionar</p>
          <div id="notif-predefinidas" style="display:grid;grid-template-columns:1fr 1fr;gap:8px;"></div>
        </div>

        <div style="display:flex;align-items:center;gap:10px;">
          <div style="flex:1;height:1px;background:#ede4d8;"></div>
          <span style="font-size:12px;color:#a8a29e;white-space:nowrap;">ou personalizar</span>
          <div style="flex:1;height:1px;background:#ede4d8;"></div>
        </div>

        <div style="display:flex;flex-direction:column;gap:12px;">
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
            <div style="display:flex;flex-direction:column;gap:5px;">
              <label for="notif-tipo-select" style="font-size:12px;font-weight:600;color:#44403c;">Tipo</label>
              <select id="notif-tipo-select" style="height:38px;border:1px solid #e7ded2;border-radius:8px;padding:0 10px;font-family:var(--font-main);font-size:13px;color:#1c1917;background:#fff;appearance:none;cursor:pointer;">
                <option value="gestor">📋 Informação do Gestor</option>
                <option value="alerta">⚠️ Alerta de Rota</option>
                <option value="info">ℹ️ Informação Geral</option>
                <option value="sistema">⚙️ Sistema / Conta</option>
                <option value="rota">🗺️ Nova Rota</option>
              </select>
            </div>
            <div style="display:flex;flex-direction:column;gap:5px;">
              <label for="notif-prioridade" style="font-size:12px;font-weight:600;color:#44403c;">Prioridade</label>
              <select id="notif-prioridade" style="height:38px;border:1px solid #e7ded2;border-radius:8px;padding:0 10px;font-family:var(--font-main);font-size:13px;color:#1c1917;background:#fff;appearance:none;cursor:pointer;">
                <option value="normal">Normal</option>
                <option value="alta">Alta — aparece primeiro</option>
                <option value="urgente">Urgente — destaque vermelho</option>
              </select>
            </div>
          </div>

          <div style="display:flex;flex-direction:column;gap:5px;">
            <label for="notif-titulo-input" style="font-size:12px;font-weight:600;color:#44403c;">Título <span style="color:#b91c1c;">*</span></label>
            <input type="text" id="notif-titulo-input" maxlength="80"
              placeholder="Ex: Sua solicitação foi analisada"
              style="height:40px;border:1px solid #e7ded2;border-radius:8px;padding:0 14px;font-family:var(--font-main);font-size:13px;color:#1c1917;transition:border-color .15s;"
              oninput="document.getElementById('notif-titulo-count').textContent=this.value.length">
            <span style="font-size:11px;color:#a8a29e;text-align:right;"><span id="notif-titulo-count">0</span>/80</span>
          </div>

          <div style="display:flex;flex-direction:column;gap:5px;">
            <label for="notif-corpo-input" style="font-size:12px;font-weight:600;color:#44403c;">Mensagem <span style="color:#b91c1c;">*</span></label>
            <textarea id="notif-corpo-input" rows="3" maxlength="300"
              placeholder="Descreva os detalhes da notificação de forma clara e objetiva..."
              style="border:1px solid #e7ded2;border-radius:8px;padding:10px 14px;font-family:var(--font-main);font-size:13px;color:#1c1917;resize:vertical;line-height:1.5;transition:border-color .15s;"
              oninput="document.getElementById('notif-corpo-count').textContent=this.value.length"></textarea>
            <span style="font-size:11px;color:#a8a29e;text-align:right;"><span id="notif-corpo-count">0</span>/300</span>
          </div>
        </div>

        <div id="notif-preview-wrap" style="display:none;background:#fdfaf6;border:1.5px solid #ede4d8;border-radius:12px;padding:14px 16px;">
          <p style="font-size:11px;font-weight:700;color:#a8a29e;text-transform:uppercase;letter-spacing:.4px;margin:0 0 8px;">Pré-visualização</p>
          <div style="display:flex;gap:10px;align-items:flex-start;">
            <div id="preview-icon" style="width:36px;height:36px;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0;background:#fff7ed;color:#d97706;font-size:16px;">📋</div>
            <div>
              <div id="preview-titulo" style="font-size:13px;font-weight:700;color:#1c1917;margin-bottom:3px;"></div>
              <div id="preview-corpo" style="font-size:12px;color:#57534e;line-height:1.45;"></div>
            </div>
          </div>
        </div>

        <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;padding-top:4px;border-top:1px solid #f5ede2;">
          <label style="display:flex;align-items:center;gap:7px;font-size:12.5px;color:#57534e;cursor:pointer;">
            <input type="checkbox" id="notif-registrar-log" checked style="accent-color:#d97706;width:14px;height:14px;">
            Registrar no log de auditoria
          </label>
          <div style="display:flex;gap:8px;">
            <button type="button" id="btn-cancelar-notif"
              style="padding:9px 18px;border-radius:9999px;border:1px solid #e7ded2;background:#fff;font-family:var(--font-main);font-size:13px;font-weight:600;color:#57534e;cursor:pointer;transition:all .15s;">
              Cancelar
            </button>
            <button type="button" id="btn-enviar-notif"
              style="padding:9px 22px;border-radius:9999px;border:none;background:#d97706;font-family:var(--font-main);font-size:13px;font-weight:700;color:#fff;cursor:pointer;transition:all .15s;display:flex;align-items:center;gap:7px;">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
              Enviar notificação
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="toast-notice" id="app-toast" role="status" aria-live="polite">
    <span class="toast-icon-wrap" aria-hidden="true"></span>
    <span id="toast-message">Operação realizada com sucesso!</span>
  </div>

  <!-- Banco MySQL & Scripts -->
  <script src="js/db.js" defer></script>
  <script src="js/db-queries.js" defer></script>
  <script src="js/auth.js"></script>
  <script src="js/usuarios.js"></script>
  <script src="js/perfil-cidadao-detalhe.js"></script>
</body>
</html>
