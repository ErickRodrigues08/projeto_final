<?php
/**
 * Passe Livre Suzano — Gestão de Solicitações (Gestor)
 * Proteção server-side: apenas admins/gestores.
 */
require_once __DIR__ . '/includes/auth_guard.php';
$gestorUserRaw = auth_require_admin();
$gestorUser    = auth_user_safe($gestorUserRaw);
$activePage    = 'solicitacoes';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Gestão de Solicitações — Painel do Gestor | Passe Livre</title>
  <meta name="description" content="Gestão completa, triagem técnica e acompanhamento de solicitações de acessibilidade viária no município de Suzano.">
  <link rel="stylesheet" href="assets/fonts/fonts.css">
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="css/gestor.css">
  <link rel="stylesheet" href="css/solicitacoes.css">
  <link rel="icon" type="image/x-icon" href="favicon.ico">
  <link rel="icon" type="image/png" sizes="32x32" href="assets/icons/favicon-32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="assets/icons/favicon-16.png">
  <link rel="apple-touch-icon" href="assets/icons/apple-touch-icon.png">
</head>
<body>
  <a href="#main-content" class="skip-link">Pular para o conteúdo principal</a>
  <div class="gestor-layout-wrapper">
    <?php require __DIR__ . '/includes/sidebar_gestor.php'; ?>
    <div class="gestor-content-area">
      <header class="gestor-top-bar">
        <div class="gestor-title-crumb-wrap">
          <button class="btn-gestor-menu-toggle" id="btn-gestor-sidebar-toggle" aria-label="Abrir menu lateral" aria-expanded="false">&#9776;</button>
          <div>
            <div class="gestor-breadcrumb-text"><span>Passe Livre</span> / <span>Solicitações</span></div>
            <h1 class="gestor-main-heading">Gestão de Solicitações</h1>
          </div>
        </div>
        <div class="gestor-header-search-wrap">
          <img src="assets/icons/search.svg" alt="" aria-hidden="true">
          <input type="search" id="search-header-input" placeholder="Buscar por ID, cidadão..." aria-label="Buscar ocorrências">
        </div>
        <div style="display:flex;align-items:center;gap:18px;">
          <button class="btn-icon-notify" id="btn-notify-top" title="Notificações" onclick="window.location.href='notificacoes.php'">
            <img src="assets/icons/bell.svg" alt="">
          </button>
          <div class="gestor-header-profile-tag" style="cursor:pointer;" onclick="window.location.href='perfil-gestor.php'">
            <div class="gestor-avatar-badge-circle"><?= htmlspecialchars($gestorUser['initials']) ?></div>
            <div class="gestor-admin-tag-text">
              <?= pl_admin_header_profile($gestorUser) ?>
            </div>
          </div>
        </div>
      </header>

      <main id="main-content" class="gestor-page-body">

        <!-- Filtros e Busca -->
        <section class="solicitacoes-filters-card" aria-label="Filtros e busca de solicitações">
          <div class="filters-top-action-row">
            <div class="search-field-large-wrap">
              <img src="assets/icons/search.svg" alt="" class="search-icon-left" aria-hidden="true">
              <input type="text" id="search-solicitacoes-input" placeholder="Buscar por ID, protocolo, cidadão ou local..." aria-label="Buscar solicitações">
              <button type="button" class="btn-search-clear" id="btn-clear-search" title="Limpar busca">&times;</button>
            </div>
            <div class="badge-results-count" id="count-found-badge" role="status" aria-live="polite">
              <img src="assets/icons/spark.svg" alt="" aria-hidden="true">
              <span id="count-found-text">Carregando...</span>
            </div>
            <a href="solicitacao-detalhe.php" class="btn-export-reports" style="background:#fff7ed;border-color:#fed7aa;color:#ea580c;text-decoration:none;font-weight:700;">
              <img src="assets/icons/user.svg" alt="" style="width:14px;height:14px;">
              <span>Visualização Individual do Cidadão</span>
            </a>
            <button type="button" class="btn-export-reports" id="btn-export-solicitacoes">
              <img src="assets/icons/download.svg" alt="" aria-hidden="true">
              <span>Exportar Relatório</span>
            </button>
          </div>

          <!-- Pílulas de Status — contadores reais vindos de api/solicitacoes.php?action=kpis -->
          <div class="status-filter-pills-row" role="group" aria-label="Filtrar por status">
            <span class="status-row-label" id="status-pills-label">Status:</span>
            <button type="button" class="status-pill-filter-btn active" data-status="all" aria-pressed="true">
              <span>Todos</span><strong id="count-all">—</strong>
            </button>
            <button type="button" class="status-pill-filter-btn" data-status="pending" aria-pressed="false">
              <span class="status-dot-indicator yellow"></span><span>Pendente</span><strong id="count-pending">—</strong>
            </button>
            <button type="button" class="status-pill-filter-btn" data-status="analyzing" aria-pressed="false">
              <span class="status-dot-indicator blue"></span><span>Em Análise</span><strong id="count-analyzing">—</strong>
            </button>
            <button type="button" class="status-pill-filter-btn" data-status="approved" aria-pressed="false">
              <span class="status-dot-indicator green"></span><span>Aprovado</span><strong id="count-approved">—</strong>
            </button>
            <button type="button" class="status-pill-filter-btn" data-status="rejected" aria-pressed="false">
              <span class="status-dot-indicator red"></span><span>Recusado</span><strong id="count-rejected">—</strong>
            </button>
          </div>

          <!-- Filtros Secundários -->
          <div class="secondary-filters-row">
            <div class="filter-control-item">
              <label for="filter-occurrence-type">Tipo de Ocorrência</label>
              <select id="filter-occurrence-type" class="solicitacoes-select">
                <option value="all">Todos os tipos</option>
                <option value="semaforo">Semáforo Sonoro</option>
                <option value="rampa">Rampa / Acesso</option>
                <option value="piso_tatil">Piso Tátil</option>
                <option value="barreiras">Barreiras Físicas</option>
                <option value="transporte">Transporte Público</option>
                <option value="calcada">Calçada</option>
                <option value="outro">Outros</option>
              </select>
            </div>
            <div class="filter-control-item">
              <label for="filter-region-neighborhood">Região / Bairro</label>
              <select id="filter-region-neighborhood" class="solicitacoes-select">
                <option value="all">Todas as localidades</option>
                <option value="centro">Centro de Suzano</option>
                <option value="vila amorim">Vila Amorim</option>
                <option value="terminal">Terminal CPTM</option>
                <option value="jardim imperador">Jardim Imperador</option>
                <option value="norte">Região Norte</option>
                <option value="sul">Região Sul</option>
              </select>
            </div>
            <div class="filter-control-item">
              <label>Período de Solicitação</label>
              <div class="date-range-filter-box">
                <div class="date-input-wrap">
                  <img src="assets/icons/calendar.svg" alt="" aria-hidden="true">
                  <input type="date" id="date-from" aria-label="Data inicial">
                </div>
                <span class="date-separator">até</span>
                <div class="date-input-wrap">
                  <img src="assets/icons/calendar.svg" alt="" aria-hidden="true">
                  <input type="date" id="date-to" aria-label="Data final">
                </div>
              </div>
            </div>
            <button type="button" class="btn-reset-filters" id="btn-reset-solicitacoes-filters" title="Redefinir todos os filtros">
              <img src="assets/icons/crop.svg" alt="" aria-hidden="true">
              <span>Redefinir</span>
            </button>
          </div>
        </section>

        <!-- Lista de Solicitações — carregada por js/solicitacoes.js via api/solicitacoes.php -->
        <section class="solicitacoes-list-section" id="solicitacoes-list-container" aria-label="Lista de Solicitações de Acessibilidade" aria-busy="true">
          <div style="text-align:center;padding:40px;color:#78716c;">Consultando o banco de dados...</div>
        </section>

      </main>

      <footer class="site-footer" role="contentinfo" style="margin-top:auto;padding:16px 32px;background:#fdfaf6;border-top:1px solid #ede4d8;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;font-size:11px;color:#78716c;">
        <div>&copy; 2026 Passe Livre. Projeto SENAI Suzano.</div>
        <div style="display:flex;gap:14px;">
          <a href="direitos.php#termos" style="color:#78716c;text-decoration:none;">Termos de uso</a>
          <span>&bull;</span>
          <a href="direitos.php#privacidade" style="color:#78716c;text-decoration:none;">Privacidade</a>
        </div>
      </footer>
    </div>
  </div>

  <!-- Modal de Triagem — grava status e parecer via api/solicitacoes.php?action=update_status -->
  <div class="sol-modal" id="modal-triagem" role="dialog" aria-modal="true" aria-labelledby="triagem-title" hidden>
    <div class="sol-modal-box">
      <div class="sol-modal-head">
        <h2 id="triagem-title" class="sol-modal-title">Triagem da solicitação</h2>
        <button type="button" class="sol-modal-close" id="modal-triagem-close" aria-label="Fechar triagem">&times;</button>
      </div>
      <p class="sol-modal-target" id="triagem-target">—</p>
      <label for="triagem-notes" class="sol-modal-label">Parecer do gestor (registrado na auditoria)</label>
      <textarea id="triagem-notes" class="sol-modal-notes" rows="4" placeholder="Descreva a fundamentação técnica da decisão."></textarea>
      <div class="sol-modal-actions">
        <button type="button" class="btn-triage-submit btn-triage-approve" data-action="aprovar">Aprovar</button>
        <button type="button" class="btn-triage-submit btn-triage-analyze" data-action="reanalise">Reanalisar</button>
        <button type="button" class="btn-triage-submit btn-triage-reject" data-action="recusar">Recusar</button>
      </div>
      <p class="sol-modal-hint">O status só muda depois que o banco confirmar a gravação.</p>
    </div>
  </div>

  <div class="toast-notice" id="app-toast" role="status" aria-live="polite">
    <span class="toast-icon-wrap" aria-hidden="true"></span>
    <span id="toast-message"></span>
  </div>

  <script src="js/auth.js"></script>
  <script src="js/solicitacoes.js"></script>
</body>
</html>
