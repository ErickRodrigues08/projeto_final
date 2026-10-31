<?php
/**
 * Passe Livre Suzano — Personalizar Busca (Cidadão)
 * Proteção server-side com autenticação.
 */
require_once __DIR__ . '/includes/auth_guard.php';
$userRaw  = auth_require();
$userData = auth_user_safe($userRaw);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Personalize sua busca — Passe Livre Suzano</title>
  <meta name="description" content="Refine os locais pelo raio de distância, nível auditado e recursos indispensáveis de acessibilidade na região pesquisada.">
  <link rel="stylesheet" href="assets/fonts/fonts.css">
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="css/personalizar-busca.css">
  <link rel="icon" type="image/x-icon" href="favicon.ico">
  <link rel="icon" type="image/png" sizes="32x32" href="assets/icons/favicon-32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="assets/icons/favicon-16.png">
  <link rel="apple-touch-icon" href="assets/icons/apple-touch-icon.png">
</head>
<body class="personalizar-page">
  <a href="#main-content" class="skip-link">Pular para o conteúdo principal</a>

  <!-- Header Principal -->
  <header class="site-header" role="banner">
    <div class="header-left">
      <a href="index.php" class="brand-logo" title="Voltar para a Página Inicial">
        <img src="assets/icons/logo.png" alt="Passe Livre Logo" width="160" height="38" onerror="this.src='assets/icons/logo.svg'">
      </a>

      <div class="header-search-wrap">
        <img src="assets/icons/search.svg" class="search-icon" alt="" aria-hidden="true">
        <input 
          type="search" 
          id="global-search-input" 
          class="header-search-input" 
          placeholder="Buscar vias, pontos ou linhas..." 
          aria-label="Buscar vias, pontos ou linhas no sistema"
        >
      </div>
    </div>

    <!-- Navegação Central -->
    <nav class="header-nav" aria-label="Navegação Principal">
      <a href="explorar.php" class="nav-link" title="Explorar o mapa da região pesquisada">
        <img src="assets/icons/globe.svg" alt="" aria-hidden="true">
        <span>Explorar Mundo</span>
      </a>
      <a href="configuracao-rota.php" class="nav-link" title="Consultar rotas acessíveis">
        <img src="assets/icons/routes.svg" alt="" aria-hidden="true">
        <span>Rotas</span>
      </a>
      <a href="lugares-encontrados.php" class="nav-link" title="Pontos de apoio e acolhimento perto do endereço pesquisado">
        <img src="assets/icons/support.svg" alt="" aria-hidden="true">
        <span>Pontos de Apoio</span>
      </a>
      <a href="direitos.php" class="nav-link" title="Direitos, legislações e normas de acessibilidade">
        <img src="assets/icons/shield.svg" alt="" aria-hidden="true">
        <span>Direitos</span>
      </a>
    </nav>

    <!-- Usuário / Notificações -->
    <div class="header-right">
      <a href="central-notificacoes.php" class="btn-icon-notify" id="btn-notifications" aria-label="Notificações e Avisos de Acessibilidade" title="Avisos do Sistema">
        <img src="assets/icons/bell.svg" alt="" aria-hidden="true">
        <span class="notify-badge-dot" aria-hidden="true"></span>
      </a>

      <div class="user-profile-menu-container">
        <button type="button" class="user-profile-btn" id="user-profile-btn" aria-expanded="false" aria-haspopup="true">
          <img src="<?= htmlspecialchars($userData['avatar']) ?>" alt="Foto de <?= htmlspecialchars($userData['name']) ?>" class="user-avatar-img" id="header-user-avatar" onerror="this.src='assets/images/avatar.jpg'">
          <div class="user-info">
            <span class="user-name" id="header-user-name"><?= htmlspecialchars($userData['name']) ?></span>
            <span class="user-role" id="header-user-role"><?= htmlspecialchars($userData['role']) ?></span>
          </div>
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#78716c" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="margin-left: 2px;">
            <polyline points="6 9 12 15 18 9"></polyline>
          </svg>
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
            <img src="assets/icons/sliders.svg" width="15" height="15" alt="">
            <span>Preferências de Busca</span>
          </a>
          <a href="ajuda.php" class="dropdown-item" role="menuitem">
            <img src="assets/icons/support.svg" width="15" height="15" alt="">
            <span>Ajuda e Suporte</span>
          </a>
          <a href="api/auth.php?action=logout" class="dropdown-item danger" role="menuitem">
            <img src="assets/icons/logout.svg" width="15" height="15" alt="">
            <span>Sair da conta</span>
          </a>
        </div>
      </div>
    </div>
  </header>

  <!-- Subheader -->
  <section class="subheader-container" aria-label="Navegação estrutural e filtros rápidos">
    <div class="breadcrumb-row">
      <div class="breadcrumb-left">
        <img src="assets/icons/home.svg" class="breadcrumb-icon" alt="" aria-hidden="true">
        <a href="index.php" class="breadcrumb-link">Início</a>
        <span class="breadcrumb-sep">/</span>
        <a href="explorar.php" class="breadcrumb-link">Portal do Cidadão</a>
        <span class="breadcrumb-sep">/</span>
        <span class="breadcrumb-current">Personalizar Busca</span>
      </div>

      <div class="security-badge">
        <img src="assets/icons/lock.svg" alt="" aria-hidden="true">
        <span>Ambiente Seguro • Dados Protegidos (LGPD)</span>
      </div>
    </div>

    <div class="filters-scroll-track" role="toolbar" aria-label="Filtros rápidos de acessibilidade">
      <button type="button" class="top-filter-pill active" title="Exibir todos os locais">
        <span>Todos</span>
      </button>
      <button type="button" class="top-filter-pill" title="Filtrar por locais totalmente acessíveis">
        <span class="dot-indicator dot-green" aria-hidden="true"></span>
        <span>Totalmente Acessíveis</span>
      </button>
      <button type="button" class="top-filter-pill" title="Filtrar por acessibilidade parcial">
        <img src="assets/icons/warning.svg" alt="" aria-hidden="true">
        <span>Acessibilidade Parcial</span>
      </button>
      <button type="button" class="top-filter-pill" title="Filtrar por não acessíveis">
        <img src="assets/icons/ban.svg" alt="" aria-hidden="true" onerror="this.src='assets/icons/warning.svg'">
        <span>Não Acessíveis</span>
      </button>
    </div>
  </section>

  <!-- Palco Principal -->
  <main class="main-stage" id="main-content">
    <div class="map-background-canvas" aria-hidden="true"></div>

    <!-- Controles do Mapa -->
    <div class="map-controls-panel" aria-label="Controles de navegação no mapa">
      <div class="zoom-widget">
        <button type="button" class="map-control-btn" id="btn-zoom-in" aria-label="Aumentar zoom" title="Aproximar">
          <img src="assets/icons/plus.svg" alt="" aria-hidden="true">
        </button>
        <div class="zoom-divider" aria-hidden="true"></div>
        <button type="button" class="map-control-btn" id="btn-zoom-out" aria-label="Diminuir zoom" title="Afastar">
          <img src="assets/icons/minus.svg" alt="" aria-hidden="true">
        </button>
      </div>

      <button type="button" class="btn-gps-target" id="btn-recenter-map" aria-label="Usar minha localização como centro da busca" title="Usar minha localização">
        <img src="assets/icons/target.svg" alt="" aria-hidden="true">
      </button>
    </div>

    <!-- CARD FLUTUANTE: PERSONALIZE SUA BUSCA -->
    <section class="customize-card" aria-labelledby="card-heading-customize">
      <div class="customize-card-header">
        <div class="header-title-box">
          <div class="header-icon-circle" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
              <line x1="4" y1="21" x2="4" y2="14"></line>
              <line x1="4" y1="10" x2="4" y2="3"></line>
              <line x1="12" y1="21" x2="12" y2="12"></line>
              <line x1="12" y1="8" x2="12" y2="3"></line>
              <line x1="20" y1="21" x2="20" y2="16"></line>
              <line x1="20" y1="12" x2="20" y2="3"></line>
            </svg>
          </div>
          <div class="header-headings">
            <div class="title-badge-row">
              <h1 class="customize-card-title" id="card-heading-customize">Personalize sua busca</h1>
              <span class="active-filters-badge" id="active-filters-badge">Filtros Ativos: 7</span>
            </div>
            <p class="customize-card-subtitle">
              Refine os locais pelo raio de distância, nível auditado e recursos indispensáveis.
            </p>
          </div>
        </div>

        <button type="button" class="btn-close-card" id="btn-close-card" onclick="window.location.href='explorar.php';" aria-label="Fechar" title="Fechar">
          &times;
        </button>
      </div>

      <!-- SEÇÃO 1: Distância / Raio -->
      <section class="filter-section" aria-labelledby="heading-sec-distance">
        <div class="section-label-row">
          <div class="section-title-wrap">
            <svg class="section-title-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <polygon points="3 11 22 2 13 21 11 13 3 11"></polygon>
            </svg>
            <h2 id="heading-sec-distance" style="font-size: 13px; font-weight: 700;">1. Distância / Raio</h2>
          </div>
          <span class="section-hint-text">A partir da sua posição</span>
        </div>

        <div class="distance-options-row" role="radiogroup" aria-label="Selecione o raio de busca">
          <button type="button" class="dist-pill-btn selected" data-dist="1km" role="radio" aria-checked="true">
            <svg class="dist-check-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
            <span>Até 1km</span>
          </button>
          <button type="button" class="dist-pill-btn" data-dist="3km" role="radio" aria-checked="false">
            <svg class="dist-check-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
            <span>Até 3 km</span>
          </button>
          <button type="button" class="dist-pill-btn" data-dist="5km" role="radio" aria-checked="false">
            <svg class="dist-check-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
            <span>Até 5 km</span>
          </button>
          <button type="button" class="dist-pill-btn" data-dist="10km" role="radio" aria-checked="false">
            <svg class="dist-check-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
            <span>Até 10 km</span>
          </button>
          <button type="button" class="dist-pill-btn" data-dist="cidade" role="radio" aria-checked="false">
            <svg class="dist-check-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
            <span>Toda a cidade</span>
          </button>
        </div>
      </section>

      <!-- SEÇÃO 2: Recursos Desejados -->
      <section class="filter-section" aria-labelledby="heading-sec-resources">
        <div class="section-label-row">
          <div class="section-title-wrap">
            <svg class="section-title-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <line x1="4" y1="21" x2="4" y2="14"></line>
              <line x1="4" y1="10" x2="4" y2="3"></line>
              <line x1="12" y1="21" x2="12" y2="12"></line>
              <line x1="12" y1="8" x2="12" y2="3"></line>
              <line x1="20" y1="21" x2="20" y2="16"></line>
              <line x1="20" y1="12" x2="20" y2="3"></line>
            </svg>
            <h2 id="heading-sec-resources" style="font-size: 13px; font-weight: 700;">2. Recursos Desejados</h2>
          </div>
          <span class="section-counter-tag" id="resources-count-label">6 de 10 marcados</span>
        </div>
        <p class="section-desc-help">
          Selecione as adaptações físicas, motoras e sensoriais necessárias:
        </p>

        <div class="resources-grid" role="group" aria-label="Opções de recursos de acessibilidade">
          <div class="resource-item checked" data-resource="entrada_nivel" tabindex="0" role="checkbox" aria-checked="true">
            <div class="custom-checkbox-dot"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"></polyline></svg></div>
            <span class="resource-label">Entrada sem escadas / em nível</span>
          </div>

          <div class="resource-item checked" data-resource="rampas_corrimao" tabindex="0" role="checkbox" aria-checked="true">
            <div class="custom-checkbox-dot"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"></polyline></svg></div>
            <span class="resource-label">Rampas com corrimão duplo</span>
          </div>

          <div class="resource-item checked" data-resource="elevador_sonoro" tabindex="0" role="checkbox" aria-checked="true">
            <div class="custom-checkbox-dot"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"></polyline></svg></div>
            <span class="resource-label">Elevador acessível / sonoro</span>
          </div>

          <div class="resource-item checked" data-resource="banheiro_adaptado" tabindex="0" role="checkbox" aria-checked="true">
            <div class="custom-checkbox-dot"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"></polyline></svg></div>
            <span class="resource-label">Banheiro acessível / adaptado</span>
          </div>

          <div class="resource-item checked" data-resource="vagas_pcd" tabindex="0" role="checkbox" aria-checked="true">
            <div class="custom-checkbox-dot"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"></polyline></svg></div>
            <span class="resource-label">Estacionamento / Vagas PcD</span>
          </div>

          <div class="resource-item checked" data-resource="piso_tatil" tabindex="0" role="checkbox" aria-checked="true">
            <div class="custom-checkbox-dot"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"></polyline></svg></div>
            <span class="resource-label">Piso tátil direcional e alerta</span>
          </div>

          <div class="resource-item" data-resource="libras" tabindex="0" role="checkbox" aria-checked="false">
            <div class="custom-checkbox-dot"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"></polyline></svg></div>
            <span class="resource-label">Intérprete / Central de Libras</span>
          </div>

          <div class="resource-item" data-resource="audiodescricao_braille" tabindex="0" role="checkbox" aria-checked="false">
            <div class="custom-checkbox-dot"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"></polyline></svg></div>
            <span class="resource-label">Audiodescrição e Braille</span>
          </div>

          <div class="resource-item checked" data-resource="espaco_cadeirante" tabindex="0" role="checkbox" aria-checked="true">
            <div class="custom-checkbox-dot"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"></polyline></svg></div>
            <span class="resource-label">Espaço cadeira de rodas</span>
          </div>

          <div class="resource-item" data-resource="sinalizacao_contraste" tabindex="0" role="checkbox" aria-checked="false">
            <div class="custom-checkbox-dot"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"></polyline></svg></div>
            <span class="resource-label">Sinalização alto contraste</span>
          </div>
        </div>
      </section>

      <!-- SEÇÃO 3: Nível de Acessibilidade Auditada -->
      <section class="filter-section" aria-labelledby="heading-sec-audit">
        <div class="section-label-row">
          <div class="section-title-wrap">
            <svg class="section-title-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
            </svg>
            <h2 id="heading-sec-audit" style="font-size: 13px; font-weight: 700;">3. Nível de Acessibilidade Auditada</h2>
          </div>
          <span class="section-hint-text">Conformidade Legal NBR 9050</span>
        </div>

        <div class="audit-levels-grid" role="radiogroup" aria-label="Selecione o nível de acessibilidade auditada">
          <div class="audit-card-option selected" data-level="alta" role="radio" aria-checked="true" tabindex="0">
            <span class="audit-indicator-dot dot-audit-green" aria-hidden="true"></span>
            <div class="audit-texts-wrap">
              <div class="audit-name-row">
                <strong class="audit-name">Alta acessibilidade</strong>
                <svg class="audit-check-badge" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"></polyline></svg>
              </div>
              <span class="audit-desc">Auditado NBR 9050 oficial</span>
            </div>
          </div>

          <div class="audit-card-option" data-level="parcial" role="radio" aria-checked="false" tabindex="0">
            <span class="audit-indicator-dot dot-audit-orange" aria-hidden="true"></span>
            <div class="audit-texts-wrap">
              <div class="audit-name-row">
                <strong class="audit-name">Parcial</strong>
                <svg class="audit-check-badge" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"></polyline></svg>
              </div>
              <span class="audit-desc">Algumas adaptações instaladas</span>
            </div>
          </div>

          <div class="audit-card-option" data-level="baixa" role="radio" aria-checked="false" tabindex="0">
            <span class="audit-indicator-dot dot-audit-red" aria-hidden="true"></span>
            <div class="audit-texts-wrap">
              <div class="audit-name-row">
                <strong class="audit-name">Baixa</strong>
                <svg class="audit-check-badge" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"></polyline></svg>
              </div>
              <span class="audit-desc">Barreiras físicas conhecidas</span>
            </div>
          </div>

          <div class="audit-card-option" data-level="nao_avaliado" role="radio" aria-checked="false" tabindex="0">
            <span class="audit-indicator-dot dot-audit-gray" aria-hidden="true"></span>
            <div class="audit-texts-wrap">
              <div class="audit-name-row">
                <strong class="audit-name">Não avaliado</strong>
                <svg class="audit-check-badge" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"></polyline></svg>
              </div>
              <span class="audit-desc">Dados colaborativos pendentes</span>
            </div>
          </div>
        </div>
      </section>

      <!-- Rodapé de Ações do Card -->
      <div class="customize-actions-row">
        <button type="button" class="btn-reset-filters" id="btn-reset-filters" title="Restaurar opções padrões de busca">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <polyline points="23 4 23 10 17 10"></polyline>
            <polyline points="1 20 1 14 7 14"></polyline>
            <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path>
          </svg>
          <span>Limpar filtros</span>
        </button>

        <button type="button" class="btn-apply-filters" id="btn-apply-filters" onclick="applyAndRedirect();" title="Aplicar filtros selecionados">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon>
          </svg>
          <span>Aplicar filtros e ver locais</span>
        </button>
      </div>

    </section>
  </main>

  <!-- Rodapé -->
  <footer class="site-footer" role="contentinfo">
    <p class="footer-copy">
      &copy; 2026 Passe Livre Suzano. Todos os direitos reservados. Projeto SENAI Suzano.
    </p>
    <div class="footer-links">
      <a href="termos-de-uso.php">Termos de Uso</a>
      <a href="privacidade.php">Privacidade (LGPD)</a>
      <a href="acessibilidade.php">Acessibilidade</a>
      <a href="fale-conosco.php">Fale Conosco</a>
    </div>
  </footer>

  <!-- Toast -->
  <div class="toast-notice" id="app-toast" role="status" aria-live="polite">
    <img src="assets/icons/check.svg" alt="" aria-hidden="true">
    <span id="toast-message">Filtros atualizados com sucesso!</span>
  </div>

    <script src="js/db.js"></script>
  <script src="js/db-queries.js"></script>
  <script src="js/auth.js"></script>
  <script src="js/personalizar-busca.js"></script>
  <script>
    function applyAndRedirect() {
      const activeDist = document.querySelector('.dist-pill-btn.selected')?.getAttribute('data-dist') || '1km';
      const activeAudit = document.querySelector('.audit-card-option.selected')?.getAttribute('data-level') || 'alta';
      const checkedResources = Array.from(document.querySelectorAll('.resource-item.checked')).map(el => el.getAttribute('data-resource'));
      
      const params = new URLSearchParams();
      params.set('radius', activeDist);
      params.set('status', activeAudit === 'alta' ? 'full' : (activeAudit === 'parcial' ? 'partial' : 'all'));
      if (checkedResources.length > 0) {
        params.set('recursos', checkedResources.join(','));
      }
      
      window.location.href = 'lugares-encontrados.php?' + params.toString();
    }
  </script>
</body>
</html>
