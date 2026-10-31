<?php
/**
 * Passe Livre Suzano — Central de Notificações do Cidadão
 * Integração direta com api/notificacoes.php e isolamento estrito no MySQL.
 */
require_once __DIR__ . '/includes/auth_guard.php';
$authUser = auth_require();
$userData = auth_user_safe($authUser);

$db = get_db();
$userId = (int)$authUser['id'];

// Contadores reais do cidadão logado
$unreadCount = (int)$db->query("SELECT COUNT(*) FROM notificacoes WHERE user_id = {$userId} AND is_read = 0")->fetchColumn();
$totalCount  = (int)$db->query("SELECT COUNT(*) FROM notificacoes WHERE user_id = {$userId}")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Central de Notificações — Passe Livre Suzano</title>
  <meta name="description" content="Central de notificações, alertas de mobilidade e despachos da Prefeitura de Suzano para o cidadão.">
  <link rel="stylesheet" href="assets/fonts/fonts.css">
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="css/central-notificacoes.css">
  <link rel="icon" type="image/x-icon" href="favicon.ico">
  <link rel="icon" type="image/png" sizes="32x32" href="assets/icons/favicon-32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="assets/icons/favicon-16.png">
  <link rel="apple-touch-icon" href="assets/icons/apple-touch-icon.png">
</head>
<body class="central-notificacoes-page">
  <a href="#main-content" class="skip-link">Pular para o conteúdo principal</a>

  <!-- Header -->
  <header class="site-header" role="banner">
    <div class="header-left">
      <a href="index.php" class="brand-logo" title="Voltar para a Página Inicial">
        <img src="assets/icons/logo.png" alt="Passe Livre Suzano" width="160" height="38" onerror="this.src='assets/icons/logo.svg'">
      </a>
      <div class="header-search-wrap">
        <img src="assets/icons/search.svg" class="search-icon" alt="" aria-hidden="true">
        <input type="search" id="global-search-input" class="header-search-input" placeholder="Buscar vias, pontos ou linhas..." aria-label="Buscar">
      </div>
    </div>

    <nav class="header-nav" aria-label="Navegação Principal">
      <a href="explorar.php" class="nav-link">
        <img src="assets/icons/globe.svg" alt="" aria-hidden="true">
        <span>Explorar Mundo</span>
      </a>
      <a href="configuracao-rota.php" class="nav-link">
        <img src="assets/icons/routes.svg" alt="" aria-hidden="true">
        <span>Rotas</span>
      </a>
      <a href="lugares-encontrados.php" class="nav-link">
        <img src="assets/icons/support.svg" alt="" aria-hidden="true">
        <span>Pontos de Apoio</span>
      </a>
      <a href="direitos.php" class="nav-link">
        <img src="assets/icons/shield.svg" alt="" aria-hidden="true">
        <span>Direitos</span>
      </a>
    </nav>

    <div class="header-right">
      <a href="central-notificacoes.php" class="btn-icon-notify active" id="btn-notifications" aria-label="Notificações">
        <img src="assets/icons/bell.svg" alt="" aria-hidden="true">
        <?php if ($unreadCount > 0): ?>
          <span class="notify-badge-dot" aria-hidden="true"></span>
        <?php endif; ?>
      </a>

      <div class="user-profile-menu-container">
        <button type="button" class="user-profile-btn" id="user-profile-btn" aria-expanded="false" aria-haspopup="true" aria-label="Menu do usuário <?= htmlspecialchars($userData['name']) ?>">
          <img src="<?= htmlspecialchars($userData['avatar'] ?: 'assets/images/avatar.jpg') ?>" alt="Foto de <?= htmlspecialchars($userData['name']) ?>" class="user-avatar-img" id="header-user-avatar" onerror="this.src='assets/images/avatar.jpg'">
          <div class="user-info">
            <span class="user-name" id="header-user-name"><?= htmlspecialchars($userData['name']) ?></span>
            <span class="user-role" id="header-user-role"><?= htmlspecialchars($userData['role']) ?></span>
          </div>
        </button>

        <div class="user-dropdown-menu" id="user-dropdown-menu" role="menu">
          <div class="dropdown-user-header">
            <div class="dd-name"><?= htmlspecialchars($userData['name']) ?></div>
            <div class="dd-email"><?= htmlspecialchars($userData['email']) ?></div>
          </div>
          <a href="editar-perfil.php" class="dropdown-item" role="menuitem">
            <img src="assets/icons/user.svg" width="15" height="15" alt="">
            <span>Meu Perfil</span>
          </a>
          <a href="adaptar-busca.php" class="dropdown-item" role="menuitem">
            <img src="assets/icons/settings.svg" width="15" height="15" alt="">
            <span>Preferências</span>
          </a>
          <div class="dropdown-divider"></div>
          <button type="button" class="dropdown-item danger" id="btn-dd-logout" role="menuitem">
            <img src="assets/icons/logout.svg" width="15" height="15" alt="">
            <span>Sair da conta</span>
          </button>
        </div>
      </div>
    </div>
  </header>

  <!-- Conteúdo Principal -->
  <main class="central-main" id="main-content">

    <nav class="central-breadcrumb" aria-label="Caminho de navegação">
      <a href="index.php">Início</a>
      <span class="crumb-sep">/</span>
      <a href="explorar.php">Portal do Cidadão</a>
      <span class="crumb-sep">/</span>
      <span class="crumb-curr">Central de Notificações</span>
    </nav>

    <section class="central-hero-card" aria-labelledby="central-heading">
      <div class="central-hero-content">
        <div class="hero-tag-pill">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
            <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
          </svg>
          <span>COMUNICADOS &amp; ALERTAS OFICIAIS</span>
        </div>
        <h1 class="central-hero-title" id="central-heading">Central de Notificações</h1>
        <p class="central-hero-desc">
          Acompanhe em tempo real despachos de requerimentos, alertas de acessibilidade viária, rotas certificadas e comunicados da gestão de Suzano direcionados a você.
        </p>
      </div>

      <div class="central-kpis" aria-label="Indicadores de Notificações">
        <div class="kpi-card kpi-unread">
          <div class="kpi-num" id="kpi-unread-count"><?= $unreadCount ?></div>
          <div class="kpi-label">Não lidas</div>
        </div>
        <div class="kpi-card">
          <div class="kpi-num" id="kpi-total-count"><?= $totalCount ?></div>
          <div class="kpi-label">Total</div>
        </div>
      </div>
    </section>

    <!-- Toolbar de Filtros e Busca -->
    <section class="central-toolbar" aria-label="Filtros e ferramentas de busca">
      <div class="toolbar-top-row">
        <div class="search-box-wrap">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <circle cx="11" cy="11" r="8"/>
            <line x1="21" y1="21" x2="16.65" y2="16.65"/>
          </svg>
          <input 
            type="search" 
            id="notif-search-input" 
            class="search-input" 
            placeholder="Pesquisar por assunto ou mensagem..." 
            aria-label="Pesquisar notificações"
          >
        </div>

        <div class="toolbar-actions">
          <button type="button" class="btn-toolbar btn-toolbar-primary" id="btn-mark-all-read" title="Marcar todas como lidas no banco">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <polyline points="20 6 9 17 4 12"/>
            </svg>
            <span>Marcar todas como lidas</span>
          </button>

          <button type="button" class="btn-toolbar btn-toolbar-refresh" id="btn-refresh-feed" title="Atualizar notificações do servidor" aria-label="Atualizar lista">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <polyline points="23 4 23 10 17 10"/>
              <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/>
            </svg>
          </button>
        </div>
      </div>

      <div class="central-filter-chips" role="toolbar" aria-label="Filtrar categorias">
        <button type="button" class="filter-chip active" data-filter="todas">
          <span>Todas</span>
          <span class="filter-chip-badge" id="chip-count-todas"><?= $totalCount ?></span>
        </button>
        <button type="button" class="filter-chip" data-filter="nao-lidas">
          <span>Não lidas</span>
          <span class="filter-chip-badge badge-unread" id="chip-count-nao-lidas"><?= $unreadCount ?></span>
        </button>
        <button type="button" class="filter-chip" data-filter="alerta">
          <span>⚠ Alertas &amp; Obras</span>
        </button>
        <button type="button" class="filter-chip" data-filter="gestor">
          <span>📋 Gestão &amp; Despachos</span>
        </button>
        <button type="button" class="filter-chip" data-filter="rota">
          <span>🗺 Rotas &amp; Mobilidade</span>
        </button>
      </div>
    </section>

    <!-- Feed de Notificações -->
    <section class="notif-feed" id="notif-feed-container" role="feed" aria-busy="false" aria-label="Lista de notificações">
      <div style="padding:40px;text-align:center;color:#78716c;">
        Carregando notificações do banco de dados...
      </div>
    </section>

  </main>

  <!-- Modal Detalhe -->
  <div class="notif-modal-overlay" id="modal-notif-detail" role="dialog" aria-modal="true" aria-labelledby="modal-notif-title" aria-hidden="true" style="display:none;" hidden>
    <div class="notif-modal-box">
      <div class="notif-modal-header">
        <div class="notif-modal-header-left">
          <div class="hero-tag-pill" id="modal-notif-type" style="margin-bottom:0;">
            Tipo
          </div>
          <h2 class="notif-modal-title" id="modal-notif-title">Detalhe do Comunicado</h2>
        </div>
        <button type="button" class="btn-modal-close" id="btn-modal-close" aria-label="Fechar">&times;</button>
      </div>

      <div class="notif-modal-body">
        <div class="modal-meta-grid">
          <div class="modal-meta-item">
            <span class="modal-meta-label">Emissor</span>
            <span class="modal-meta-val" id="modal-notif-sender">—</span>
          </div>
          <div class="modal-meta-item">
            <span class="modal-meta-label">Data</span>
            <span class="modal-meta-val" id="modal-notif-date">—</span>
          </div>
          <div class="modal-meta-item">
            <span class="modal-meta-label">Situação</span>
            <span class="modal-meta-val" id="modal-notif-status">—</span>
          </div>
        </div>

        <div class="modal-full-content" id="modal-notif-content"></div>
      </div>

      <div class="notif-modal-footer">
        <a href="#" class="btn-toolbar btn-toolbar-primary" id="modal-notif-link-btn" style="display:none; text-decoration:none;">
          <span>Acessar Recurso</span>
        </a>
        <button type="button" class="btn-toolbar" id="btn-modal-dismiss" style="background:#ede4d8;color:#44403c;">
          Fechar
        </button>
      </div>
    </div>
  </div>

  <div class="toast-notice" id="app-toast">
    <span id="toast-message">Operação concluída com sucesso!</span>
  </div>

  <footer class="site-footer" style="margin-top:auto;padding:24px 20px;text-align:center;font-size:12px;color:#78716c;border-top:1px solid #ede4d8;background:#ffffff;">
    <p>&copy; 2026 Passe Livre Suzano &bull; Plataforma de Mobilidade Urbana Inclusiva &bull; SENAI Suzano</p>
  </footer>

    <script src="js/db.js"></script>
  <script src="js/db-queries.js"></script>
  <script src="js/auth.js"></script>
  <script src="js/central-notificacoes.js"></script>
</body>
</html>
