<?php
/**
 * Passe Livre Suzano — Lugares Encontrados (Resultados de Busca e Acessibilidade)
 * Integração dinâmica PHP / MySQL
 */
require_once __DIR__ . '/api/_db_restore.php';
require_once __DIR__ . '/includes/acessibilidade.php';
require_once __DIR__ . '/includes/compatibilidade.php';
require_once __DIR__ . '/includes/geo_centro.php';

$authUser = get_auth_user();
$userNeeds       = pl_compat_user_needs($authUser, get_db());
$userNeedLabels  = pl_pcd_labels($userNeeds);
$appliedCriteria = pl_compat_applied_criteria($userNeeds);

$userName   = $authUser ? trim((string)($authUser['name'] ?? '')) : '';
$userEmail  = $authUser ? (string)($authUser['email'] ?? '') : '';
$userRole   = $authUser ? trim((string)($authUser['role'] ?? '')) : '';
$userAvatar = $authUser ? trim((string)($authUser['avatar'] ?? '')) : '';
if ($userAvatar === '' || $userAvatar === 'null') {
    $userAvatar = 'assets/images/avatar.jpg';
}
$userRole = $userRole !== '' ? $userRole : ($authUser ? 'Cidadão' : 'Visitante');

$profileName   = htmlspecialchars($userName !== '' ? $userName : 'Visitante', ENT_QUOTES, 'UTF-8');
$profileEmail  = htmlspecialchars($userEmail, ENT_QUOTES, 'UTF-8');
$profileRole   = htmlspecialchars($userRole, ENT_QUOTES, 'UTF-8');
$profileAvatar = htmlspecialchars($userAvatar, ENT_QUOTES, 'UTF-8');

$needLabelsEsc = array_map(static fn($l) => htmlspecialchars((string)$l, ENT_QUOTES, 'UTF-8'), $userNeedLabels);
$criteriaEsc   = array_map(static fn($l) => htmlspecialchars((string)$l, ENT_QUOTES, 'UTF-8'), $appliedCriteria);

$profileHeading = 'Perfil: ' . $profileName;
if ($needLabelsEsc) {
    $profileHeading .= ' (' . implode(' &bull; ', $needLabelsEsc) . ')';
}
$profileCriteria = $criteriaEsc ? implode(' &bull; ', $criteriaEsc) : '';

$phpUserJson = json_encode([
    'id'        => (int)($authUser['id'] ?? 0),
    'name'      => $userName,
    'email'     => $userEmail,
    'role'      => $userRole,
    'avatar'    => $userAvatar,
    'hasNeeds'  => !empty($userNeeds),
    'needs'     => $userNeeds,
    'needLabels' => $userNeedLabels,
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);

$_destQuery = trim($_GET['q'] ?? '');
if ($_destQuery !== '' && !mb_check_encoding($_destQuery, 'UTF-8')) {
    $_destQuery = mb_convert_encoding($_destQuery, 'UTF-8', 'ISO-8859-1');
}
$_destId    = isset($_GET['dest_id']) ? (int)$_GET['dest_id'] : 0;
$_destName  = htmlspecialchars(trim($_GET['dest_name'] ?? $_destQuery));

// Centro escolhido na tela de exploração: endereço geocodificado (dest_lat/lng)
// ou a posição confirmada no mapa/GPS (lat/lng). Nenhum dos dois tem substituto
// fixo — sem coordenadas a página busca perto de você depois de geocodificar o
// texto, e se nada resolver informa isso em vez de cair em Suzano.
$_origem = pl_geo_centro_valido($_GET['lat'] ?? null, $_GET['lng'] ?? null);
$_destino = pl_geo_centro_valido($_GET['dest_lat'] ?? null, $_GET['dest_lng'] ?? null);

$_destMunicipio = mb_substr(trim((string)($_GET['dest_municipio'] ?? '')), 0, 120);
$_destEndereco  = mb_substr(trim((string)($_GET['dest_endereco'] ?? '')), 0, 200);

$_raioKm = isset($_GET['raio_km']) || isset($_GET['radius'])
    ? pl_geo_raio_km($_GET['raio_km'] ?? $_GET['radius'])
    : null;

$_origemTxt = trim((string)($_GET['origem'] ?? ''));
if (mb_strlen($_origemTxt) > 160) {
    $_origemTxt = mb_substr($_origemTxt, 0, 160);
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Lugares Encontrados — Navegação Inclusiva | Passe Livre Suzano</title>
  <meta name="description" content="Resultados georreferenciados de lugares e rotas com informações de acessibilidade no estado de São Paulo.">
  
  <!-- Fontes Locais e Estilos -->
  <link rel="stylesheet" href="assets/fonts/fonts.css">
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="css/lugares-encontrados.css">
  <!-- Leaflet.js Mapa Interativo OpenStreetMap gratuito -->
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="">
  <style>
    #leaflet-map{width:100%;height:100%;min-height:500px;z-index:1;border-radius:18px}
    .map-canvas-container{display:block!important;position:relative!important;}
    .pl-popup .leaflet-popup-content-wrapper{border-radius:14px;box-shadow:0 8px 24px rgba(0,0,0,.12);border:1px solid #e7ded2;padding:0}
    .pl-popup .leaflet-popup-content{margin:14px 16px;font-family:"Plus Jakarta Sans",sans-serif}
    .pl-popup .leaflet-popup-tip{background:#fff}
    .pl-popup-destino .leaflet-popup-content-wrapper{border:2px solid #ea580c}
    .place-card.map-highlighted{outline:2.5px solid #ea580c;outline-offset:2px;box-shadow:0 0 0 4px rgba(234,88,12,.15)!important}
    .map-loading-overlay{position:absolute;inset:0;background:rgba(245,238,227,.88);display:flex;align-items:center;justify-content:center;z-index:10;border-radius:20px;flex-direction:column;gap:12px}
    .map-loading-spinner{width:40px;height:40px;border:4px solid #e7ded2;border-top-color:#ea580c;border-radius:50%;animation:plSpin .8s linear infinite}
    @keyframes plSpin{to{transform:rotate(360deg)}}
    .leaflet-control-attribution{font-size:9px!important}
  </style>
  <link rel="icon" type="image/x-icon" href="favicon.ico">
  <link rel="icon" type="image/png" sizes="32x32" href="assets/icons/favicon-32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="assets/icons/favicon-16.png">
  <link rel="apple-touch-icon" href="assets/icons/apple-touch-icon.png">
</head>
<body class="results-page-body" 
  data-dest-id="<?= $_destId ?>" 
  data-dest-name="<?= $_destName ?>" 
  data-dest-query="<?= htmlspecialchars($_destQuery) ?>" 
  data-lat="<?= $_origem['ok'] ? number_format($_origem['lat'], 6, '.', '') : '' ?>" 
  data-lng="<?= $_origem['ok'] ? number_format($_origem['lng'], 6, '.', '') : '' ?>"
  data-dest-lat="<?= $_destino['ok'] ? number_format($_destino['lat'], 6, '.', '') : '' ?>"
  data-dest-lng="<?= $_destino['ok'] ? number_format($_destino['lng'], 6, '.', '') : '' ?>"
  data-dest-municipio="<?= htmlspecialchars($_destMunicipio, ENT_QUOTES, 'UTF-8') ?>"
  data-dest-endereco="<?= htmlspecialchars($_destEndereco, ENT_QUOTES, 'UTF-8') ?>"
  data-raio="<?= $_raioKm === null ? '' : htmlspecialchars((string)$_raioKm, ENT_QUOTES, 'UTF-8') ?>"
  data-has-needs="<?= !empty($userNeeds) ? '1' : '0' ?>"
  data-origem="<?= htmlspecialchars($_origemTxt, ENT_QUOTES, 'UTF-8') ?>">
  <a href="#main-content" class="skip-link">Pular para o conteúdo principal</a>

  <!-- =========================================================================
       0. CABEÇALHO PRINCIPAL (HEADER)
       ========================================================================= -->
  <header class="site-header" role="banner">
    <div class="header-left">
      <a href="index.php" class="brand-logo" title="Voltar para a Página Inicial">
        <img src="assets/icons/logo.png" alt="Passe Livre Logo" width="160" height="38">
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

    <nav class="header-nav" aria-label="Navegação Principal">
      <a href="explorar.php" class="nav-link" title="Explorar o mapa por endereço">
        <img src="assets/icons/globe.svg" alt="" aria-hidden="true">
        <span>Explorar Mundo</span>
      </a>
      <a href="configuracao-rota.php" class="nav-link" title="Consultar e configurar rotas acessíveis">
        <img src="assets/icons/routes.svg" alt="" aria-hidden="true">
        <span>Rotas</span>
      </a>
      <a href="lugares-encontrados.php" class="nav-link active" aria-current="page" title="Pontos de apoio na região pesquisada">
        <img src="assets/icons/support.svg" alt="" aria-hidden="true">
        <span>Pontos de Apoio</span>
      </a>
      <a href="direitos.php" class="nav-link" title="Direitos e legislações PcD">
        <img src="assets/icons/shield.svg" alt="" aria-hidden="true">
        <span>Direitos</span>
      </a>
    </nav>

    <div class="header-right">
      <a href="notificacoes.php" class="btn-icon-notify" id="btn-notifications" aria-label="Notificações" title="Avisos do Sistema">
        <img src="assets/icons/bell.svg" alt="" aria-hidden="true">
        <span class="notify-badge-dot" aria-hidden="true"></span>
      </a>

      <div class="user-profile-menu-container">
        <button type="button" class="user-profile-btn" id="user-profile-btn" aria-expanded="false" aria-haspopup="true" aria-label="Menu do usuário">
          <img src="<?= $profileAvatar ?>" alt="Foto de perfil de <?= $profileName ?>" class="user-avatar-img" id="header-user-avatar">
          <div class="user-info">
            <span class="user-name" id="header-user-name"><?= $profileName ?></span>
            <span class="user-role" id="header-user-role"><?= $profileRole ?></span>
          </div>
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#78716c" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="margin-left: 2px;">
            <polyline points="6 9 12 15 18 9"></polyline>
          </svg>
        </button>

        <div class="user-dropdown-menu" id="user-dropdown-menu" role="menu">
          <div class="dropdown-user-header">
            <div class="dd-name" id="dd-user-name"><?= $profileName ?></div>
            <div class="dd-email" id="dd-user-email"><?= $profileEmail ?></div>
          </div>
          <a href="editar-perfil.php" class="dropdown-item" role="menuitem">
            <img src="assets/icons/user.svg" width="15" height="15" alt="">
            <span>Meu Perfil</span>
          </a>
          <a href="personalizar-busca.php" class="dropdown-item" role="menuitem">
            <img src="assets/icons/sliders.svg" width="15" height="15" alt="">
            <span>Preferências de Busca</span>
          </a>
          <a href="ajuda.php" class="dropdown-item" role="menuitem">
            <img src="assets/icons/help.svg" width="15" height="15" alt="" onerror="this.src='assets/icons/support.svg'">
            <span>Ajuda e Suporte</span>
          </a>
          <a href="index.php" class="dropdown-item danger" id="btn-logout-results" role="menuitem">
            <img src="assets/icons/logout.svg" width="15" height="15" alt="">
            <span>Sair da conta</span>
          </a>
        </div>
      </div>
    </div>
  </header>

  <!-- =========================================================================
       1. SUBHEADER: BREADCRUMBS & BARRA DE FILTROS (CONFORME SCREENSHOT)
       ========================================================================= -->
  <section class="results-subheader" aria-label="Localização na Página e Filtros">
    <div class="results-breadcrumb-row">
      <div class="results-breadcrumb-left">
        <img src="assets/icons/home.svg" alt="" aria-hidden="true">
        <a href="index.php" class="results-breadcrumb-link">Início</a>
        <span class="results-breadcrumb-separator">/</span>
        <a href="explorar.php" class="results-breadcrumb-link">Portal do Cidadão</a>
        <span class="results-breadcrumb-separator">/</span>
        <span class="results-breadcrumb-current">Lugares Encontrados</span>
      </div>

      <div class="results-security-badge">
        <img src="assets/icons/lock.svg" alt="" aria-hidden="true">
        <span>Ambiente Seguro • Dados Protegidos (LGPD)</span>
      </div>
    </div>

    <!-- Filtros em formato de pílulas (Pills) -->
    <div class="filters-scroll-track" role="toolbar" aria-label="Filtrar locais por categoria de acessibilidade">
      <a href="adaptar-busca.php" class="filter-chip" style="background: #fff7ed; border-color: #fed7aa; color: #ea580c; text-decoration: none; font-weight: 700;" title="Ajustar necessidades de acessibilidade">
        <img src="assets/icons/sliders.svg" alt="" style="width: 14px; height: 14px;">
        <span>Adaptar Busca</span>
      </a>

      <a href="personalizar-busca.php" class="filter-chip" style="background: #ffffff; border-color: #e7ded2; color: #57534e; text-decoration: none; font-weight: 600;" title="Personalizar raio, recursos e nível auditado">
        <img src="assets/icons/sliders.svg" alt="" style="width: 14px; height: 14px; opacity: 0.7;">
        <span>Personalizar Busca</span>
      </a>

      <button class="filter-chip active" data-filter="all" aria-pressed="true">
        <span>Todos</span>
      </button>

      <button class="filter-chip" data-filter="total" aria-pressed="false">
        <span class="dot-indicator dot-accessible" aria-hidden="true"></span>
        <span>Totalmente Acessíveis</span>
      </button>

      <button class="filter-chip" data-filter="partial" aria-pressed="false">
        <img src="assets/icons/warning.svg" alt="" aria-hidden="true">
        <span>Acessibilidade Parcial</span>
      </button>

      <button class="filter-chip" data-filter="none" aria-pressed="false">
        <img src="assets/icons/ban.svg" alt="" aria-hidden="true">
        <span>Não Acessíveis</span>
      </button>

      <button class="filter-chip" data-filter="food" aria-pressed="false">
        <img src="assets/icons/food.svg" alt="" aria-hidden="true">
        <span>Alimentação</span>
      </button>

      <button class="filter-chip" data-filter="health" aria-pressed="false">
        <img src="assets/icons/health.svg" alt="" aria-hidden="true">
        <span>Saúde &amp; Farmácias</span>
      </button>

      <button class="filter-chip" data-filter="education" aria-pressed="false">
        <img src="assets/icons/education.svg" alt="" aria-hidden="true">
        <span>Educação</span>
      </button>

      <button class="filter-chip" data-filter="restroom" aria-pressed="false">
        <img src="assets/icons/restroom.svg" alt="" aria-hidden="true">
        <span>Banheiros Acessíveis</span>
      </button>
    </div>
  </section>

  <!-- =========================================================================
       2. CORPO PRINCIPAL: 2 COLUNAS (LISTA DE LUGARES + MAPA INTERATIVO)
       ========================================================================= -->
  <main class="results-page-layout" id="main-content">
    
    <!-- COLUNA DA ESQUERDA: LISTA DE LUGARES -->
    <section class="results-list-column" aria-label="Lugares encontrados">
      
      <!-- Cabeçalho de Resultados -->
      <div class="results-list-header">
        <div>
          <h1 class="results-list-title">Lugares encontrados</h1>
          <p class="results-list-subtitle" id="places-count-label">Carregando locais acessíveis...</p>
          <p id="search-term-display" style="display:none; font-size:12px; color:#b45309; margin-top:2px;"></p>
          <p id="origin-display" style="display:none; font-size:12px; color:#57534e; margin-top:2px;"></p>
        </div>

        <button type="button" class="btn-sort-filter" id="btn-sort-filter" title="Ordenar resultados" aria-pressed="false">
          <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <line x1="4" y1="21" x2="4" y2="14"></line>
            <line x1="4" y1="10" x2="4" y2="3"></line>
            <line x1="12" y1="21" x2="12" y2="12"></line>
            <line x1="12" y1="8" x2="12" y2="3"></line>
            <line x1="20" y1="21" x2="20" y2="16"></line>
            <line x1="20" y1="12" x2="20" y2="3"></line>
            <line x1="1" y1="14" x2="7" y2="14"></line>
            <line x1="9" y1="8" x2="15" y2="8"></line>
            <line x1="17" y1="16" x2="23" y2="16"></line>
          </svg>
          <span>Mais compatíveis</span>
        </button>
      </div>

      <!-- Card do Perfil do Usuário e Critérios Aplicados -->
      <div class="profile-criteria-card">
        <div class="profile-avatar-badge" aria-hidden="true">
          <img src="assets/icons/user.svg" alt="">
        </div>
        <div class="profile-info-group">
          <div class="profile-user-name"><?= $profileHeading ?></div>
          <?php if ($profileCriteria): ?>
            <div class="profile-criteria-tags">Critérios considerados: <?= $profileCriteria ?></div>
          <?php else: ?>
            <div class="profile-criteria-tags">
              Você ainda não cadastrou suas necessidades de acessibilidade.
              <a href="editar-perfil.php" style="color:#b45309;font-weight:700;text-decoration:underline;">Editar perfil</a>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Lista de resultados (preenchida via API em js/lugares-encontrados.js) -->
      <div id="places-results-list"></div>

    </section>

    <!-- COLUNA DA DIREITA: MAPA INTERATIVO ESTILIZADO -->
    <section class="results-map-column" aria-label="Mapa da região pesquisada">
      
      <!-- Legenda Flutuante (Superior Esquerda) -->
      <div class="map-floating-legend" role="status" aria-label="Níveis de Acessibilidade">
        <div class="legend-item">
          <span class="legend-dot alta"></span>
          <span>Alta</span>
        </div>
        <div class="legend-item">
          <span class="legend-dot parcial"></span>
          <span>Parcial</span>
        </div>
        <div class="legend-item">
          <span class="legend-dot baixa"></span>
          <span>Baixa/Obras</span>
        </div>
      </div>

      <!-- Ferramentas do Mapa (Superior Direita) -->
      <div class="map-floating-tools" aria-label="Ferramentas do mapa">
        <button type="button" class="btn-map-tool" id="btn-map-layers" title="Alternar camadas" aria-label="Camadas do mapa">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <polygon points="12 2 2 7 12 12 22 7 12 2"></polygon>
            <polyline points="2 17 12 22 22 17"></polyline>
            <polyline points="2 12 12 17 22 12"></polyline>
          </svg>
        </button>

        <button type="button" class="btn-map-tool" id="btn-map-recenter" title="Centralizar na minha localização" aria-label="Minha localização">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10"></circle>
            <circle cx="12" cy="12" r="3"></circle>
            <line x1="12" y1="2" x2="12" y2="6"></line>
            <line x1="12" y1="18" x2="12" y2="22"></line>
            <line x1="2" y1="12" x2="6" y2="12"></line>
            <line x1="18" y1="12" x2="22" y2="12"></line>
          </svg>
        </button>

        <button type="button" class="btn-map-tool" id="btn-map-reset" title="Restaurar visão original" aria-label="Restaurar mapa">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="23 4 23 10 17 10"></polyline>
            <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path>
          </svg>
        </button>
      </div>

      <!-- Mapa Interativo Leaflet (OpenStreetMap, gratuito, sem chave de API) -->
      <div class="map-canvas-container" id="map-interactive-viewport" style="position:relative;">
        <div class="map-loading-overlay" id="map-loading">
          <div class="map-loading-spinner"></div>
          <span style="font-size:12px;color:#78716c;font-family:sans-serif;">Carregando mapa...</span>
        </div>
        <div id="leaflet-map"></div>
        <svg id="suzano-results-map" style="display:none;" viewBox="0 0 1 1"></svg>
      </div>

      <!-- Card Flutuante de Rota Calculada (Inferior Esquerda) -->
      <div class="map-floating-route-card" role="region" aria-label="Destino selecionado no mapa">
        <div class="route-badge-icon" aria-hidden="true">A</div>
        <div class="route-info-text">
          <span class="route-info-title">Nenhum destino selecionado</span>
          <span class="route-info-desc">Escolha um marcador no mapa para ver o local</span>
        </div>
      </div>

      <!-- Controles de Zoom (Inferior Direita) -->
      <div class="map-floating-zoom" role="group" aria-label="Zoom do mapa">
        <button type="button" class="btn-zoom-action" id="btn-zoom-plus" aria-label="Aproximar">+</button>
        <div class="zoom-divider-line"></div>
        <button type="button" class="btn-zoom-action" id="btn-zoom-minus" aria-label="Afastar">&minus;</button>
      </div>

    </section>
  </main>

  <!-- =========================================================================
       3. RODAPÉ DA PÁGINA (FOOTER)
       ========================================================================= -->
  <footer class="site-footer" role="contentinfo">
    <div class="footer-credits">
      &copy; 2026 Passe Livre. Todos os direitos reservados. Projeto desenvolvido por Samuel, Rodrigo, Erick, Gabrielly e Ana Júlia — SENAI, Suzano.
    </div>
    <div class="footer-links">
      <a href="direitos.php#termos" class="footer-link-item">Termos de Uso</a>
      <span>&bull;</span>
      <a href="direitos.php#privacidade" class="footer-link-item">Privacidade (LGPD)</a>
      <span>&bull;</span>
      <a href="direitos.php#acessibilidade" class="footer-link-item">Acessibilidade</a>
      <span>&bull;</span>
      <a href="ajuda.php" class="footer-link-item">Central de Ajuda</a>
    </div>
  </footer>

  <!-- Modal Detalhes do Local -->
  <div class="modal-overlay" id="modal-place-details" role="dialog" aria-modal="true" aria-labelledby="modal-place-title">
    <div class="modal-box">
      <div class="modal-header">
        <h3 id="modal-place-title">Shopping Suzano &amp; Poupatempo</h3>
        <button class="btn-close-modal" id="btn-close-details" aria-label="Fechar janela">&times;</button>
      </div>
      <div class="modal-content" id="modal-place-body">
        <p><strong>Certificação Municipal de Acessibilidade 2026:</strong></p>
        <ul style="margin-top: 10px; margin-left: 20px; line-height: 1.8;">
          <li>Rampas frontais com inclinação padrão NBR 9050 (menos de 8,33%)</li>
          <li>Elevadores com aviso por voz e botões em Braille</li>
          <li>Vagas especiais demarcadas e cobertas próximas às entradas A e B</li>
          <li>Sanitários familiares e acessíveis em todos os pisos</li>
          <li>Piso tátil direcional a partir da parada de ônibus</li>
        </ul>
        <div style="margin-top: 16px; display: flex; justify-content: flex-end;">
          <button type="button" class="btn-card-directions" id="btn-modal-route" data-place-id="3" style="flex: none; padding: 8px 20px;">
            <span>Ativar Rota Acessível</span>
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Toast de Notificação -->
  <div class="toast-notice" id="app-toast">
    <img src="assets/icons/check.svg" width="18" height="18" alt="">
    <span id="toast-message">Rota acessível ativada!</span>
  </div>

  <script id="php-user-data" type="application/json"><?= $phpUserJson ?></script>

  <script src="js/db.js" defer></script>
  <script src="js/db-queries.js" defer></script>
  <script src="js/mock-data.js" defer></script>
  <script src="js/auth.js"></script>
  <script src="js/lugares-encontrados.js"></script>
  <!-- Leaflet CSS+JS inline para garantir carregamento correto -->
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
  <script>
  (function() {
    var BRASIL_BOUNDS = L.latLngBounds(L.latLng(-33.75, -73.99), L.latLng(5.27, -28.85));
    var MAPA_PADRAO = { lat: -22.6000, lng: -46.8000, zoom: 7 }; // visão do estado enquanto não há centro
    var map = null, markers = null, osmLayer = null, camadaExtra = null;
    var pedido = 0, centroVigente = null, osmUltimo = null;

    var STATUS_COLOR = { verified:"#059669", full:"#059669", partial:"#f59e0b", unverified:"#a8a29e", critical:"#ef4444" };
    var STATUS_LABEL = {
      verified:"Alta acessibilidade", full:"Alta acessibilidade", partial:"Acessibilidade parcial",
      unverified:"Não verificado", critical:"Acessibilidade crítica"
    };

    function $id(i) { return document.getElementById(i); }
    function esconderLoading() { var l = $id("map-loading"); if (l) l.style.display = "none"; }

    function pinIcon(color) {
      var s = "<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"28\" height=\"36\" viewBox=\"0 0 28 36\">"
        + "<circle cx=\"14\" cy=\"14\" r=\"12\" fill=\"" + color + "\" stroke=\"#fff\" stroke-width=\"2\"/>"
        + "<circle cx=\"14\" cy=\"14\" r=\"4\" fill=\"#fff\"/>"
        + "<line x1=\"14\" y1=\"26\" x2=\"14\" y2=\"34\" stroke=\"" + color + "\" stroke-width=\"2\" stroke-linecap=\"round\"/>"
        + "</svg>";
      return L.divIcon({html:s,className:"",iconSize:[28,36],iconAnchor:[14,36],popupAnchor:[0,-36]});
    }

    function destIcon() {
      var s = "<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"38\" height=\"48\" viewBox=\"0 0 38 48\">"
        + "<circle cx=\"19\" cy=\"19\" r=\"17\" fill=\"#ea580c\" stroke=\"#fff\" stroke-width=\"3\"/>"
        + "<path d=\"M19 9 L21 15.5 L28 15.5 L22.5 19.5 L24.5 26 L19 22 L13.5 26 L15.5 19.5 L10 15.5 L17 15.5 Z\" fill=\"#fff\"/>"
        + "<line x1=\"19\" y1=\"36\" x2=\"19\" y2=\"45\" stroke=\"#ea580c\" stroke-width=\"3\" stroke-linecap=\"round\"/>"
        + "</svg>";
      return L.divIcon({html:s,className:"",iconSize:[38,48],iconAnchor:[19,48],popupAnchor:[0,-48]});
    }

    function popup(loc, isDest) {
      var cor   = STATUS_COLOR[loc.access_status] || "#78716c";
      var label = loc.access_text || STATUS_LABEL[loc.access_status] || "";
      var stars = loc.rating ? "\u2605".repeat(Math.round(parseFloat(loc.rating))) : "";
      var dist  = loc.distancia_km ? " &bull; " + parseFloat(loc.distancia_km).toFixed(1).replace(".", ",") + " km" : "";
      var badge = isDest ? "<div style=\"background:#fff7ed;border:1px solid #fed7aa;color:#c2410c;font-size:10px;font-weight:700;padding:2px 8px;border-radius:20px;margin-bottom:6px;display:inline-block;\">Destino</div><br>" : "";
      return "<div style=\"font-family:sans-serif;min-width:170px;max-width:230px;\">"
        + badge
        + "<div style=\"font-size:11px;color:" + cor + ";font-weight:700;\">" + label + "</div>"
        + "<div style=\"font-size:13px;font-weight:800;color:#1c1917;margin:3px 0;\">" + (loc.name||"") + "</div>"
        + "<div style=\"font-size:11px;color:#78716c;margin-bottom:5px;\">" + (loc.address||"") + (loc.municipio ? " — " + loc.municipio + "/SP" : "") + "</div>"
        + "<div style=\"font-size:12px;color:#f59e0b;margin-bottom:8px;\">" + stars + dist + "</div>"
        + "<div style=\"display:flex;gap:5px;\">"
        + "<a href=\"detalhes-local.php?id=" + loc.id + "\" style=\"flex:1;text-align:center;background:#f5f0e8;color:#44403c;text-decoration:none;padding:5px 6px;border-radius:6px;font-size:10px;font-weight:700;border:1px solid #e7ded2;\">Detalhes</a>"
        + "<a href=\"configuracao-rota.php?dest_id=" + loc.id + "\" style=\"flex:1.2;text-align:center;background:#d97706;color:#fff;text-decoration:none;padding:5px 6px;border-radius:6px;font-size:10px;font-weight:700;\">Como chegar</a>"
        + "</div></div>";
    }

    function escHtml(s) {
      return String(s == null ? "" : s).replace(/[&<>"']/g, function (c) {
        return { "&": "&amp;", "<": "&lt;", ">": "&gt;", "\"": "&quot;", "'": "&#39;" }[c];
      });
    }

    function osmIcon() {
      var s = "<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"22\" height=\"22\" viewBox=\"0 0 22 22\">"
        + "<circle cx=\"11\" cy=\"11\" r=\"8\" fill=\"#64748b\" fill-opacity=\"0.85\" stroke=\"#fff\" stroke-width=\"2\" stroke-dasharray=\"3 2\"/>"
        + "</svg>";
      return L.divIcon({ html: s, className: "", iconSize: [22, 22], iconAnchor: [11, 11], popupAnchor: [0, -11] });
    }

    /**
     * Camada colaborativa do OpenStreetMap. Fica em um layerGroup próprio para
     * não se misturar com os locais cadastrados, e o popup deixa explícito que o
     * dado é aberto e não passou por vistoria.
     */
    function desenhaOsm(det) {
      osmUltimo = det || null;
      if (!map || !osmLayer) return;
      osmLayer.clearLayers();
      if (!det || det.estado !== "ok" || !Array.isArray(det.pontos)) return;

      det.pontos.forEach(function (pt) {
        var lat = parseFloat(pt.lat), lng = parseFloat(pt.lng);
        if (!isFinite(lat) || !isFinite(lng)) return;
        var d = isFinite(pt.distancia_km)
          ? " &bull; " + pt.distancia_km.toFixed(1).replace(".", ",") + " km" : "";
        var mk = L.marker([lat, lng], { icon: osmIcon(), title: pt.nome || "Ponto do OpenStreetMap" });
        mk.bindPopup(
          "<div style=\"font-family:sans-serif;min-width:170px;max-width:230px;\">"
          + "<div style=\"background:#f1f5f9;border:1px solid #cbd5e1;color:#334155;font-size:10px;font-weight:700;padding:2px 8px;border-radius:20px;margin-bottom:6px;display:inline-block;\">Dado aberto &mdash; não verificado</div><br>"
          + "<div style=\"font-size:11px;color:#475569;font-weight:700;\">" + escHtml(pt.tipo || "Ponto de acessibilidade") + "</div>"
          + "<div style=\"font-size:13px;font-weight:800;color:#1c1917;margin:3px 0;\">" + escHtml(pt.nome || "Ponto sem nome") + "</div>"
          + "<div style=\"font-size:12px;color:#78716c;margin-bottom:8px;\">OpenStreetMap" + d + "</div>"
          + "<a href=\"configuracao-rota.php?dest_lat=" + lat + "&dest_lng=" + lng
          + "&dest_nome=" + encodeURIComponent(pt.nome || "Ponto do OpenStreetMap")
          + "\" style=\"display:block;text-align:center;background:#d97706;color:#fff;text-decoration:none;padding:5px 6px;border-radius:6px;font-size:10px;font-weight:700;\">Como chegar</a>"
          + "</div>",
          { maxWidth: 250, className: "pl-popup" }
        );
        mk.addTo(osmLayer);
      });
    }

    function avisoMapa(texto) {
      var box = $id("map-aviso-regiao");
      if (!box) {
        box = document.createElement("div");
        box.id = "map-aviso-regiao";
        box.setAttribute("role", "status");
        box.style.cssText = "position:absolute;left:12px;bottom:12px;z-index:9;max-width:74%;"
          + "background:rgba(255,255,255,.95);border:1px solid #e7ded2;border-radius:12px;"
          + "padding:8px 12px;font-size:11px;line-height:1.45;color:#44403c;font-family:sans-serif;"
          + "box-shadow:0 4px 14px rgba(0,0,0,.08)";
        var alvo = $id("map-interactive-viewport");
        if (alvo) alvo.appendChild(box);
      }
      if (texto) { box.textContent = texto; box.style.display = "block"; }
      else { box.textContent = ""; box.style.display = "none"; }
    }

    // Um pedido por centro: resposta atrasada de uma região anterior não pode
    // desenhar marcadores novos sobre o mapa de outra cidade.
    function carregarMarcadores(ctx) {
      if (!map) return;
      centroVigente = ctx || null;
      var temCentro = !!(ctx && isFinite(ctx.lat) && isFinite(ctx.lng));
      var token = ++pedido;
      var loading = $id("map-loading");
      if (loading) loading.style.display = "";

      if (!temCentro) {
        if (loading) loading.style.display = "none";
        markers.clearLayers();
        if (osmLayer) osmLayer.clearLayers();
        map.setView([MAPA_PADRAO.lat, MAPA_PADRAO.lng], MAPA_PADRAO.zoom);
        avisoMapa("Defina um endereço, um ponto no mapa ou permita sua localização para ver os locais nesta região.");
        return;
      }

      // Centro novo: os pontos abertos antigos perdem o sentido e somem até a
      // próxima resposta da camada OpenStreetMap.
      if (osmLayer) osmLayer.clearLayers();
      avisoMapa("");
      map.setView([ctx.lat, ctx.lng], Math.max(13, map.getZoom() > 16 ? 14 : map.getZoom()));

      var p = new URLSearchParams({ lat: String(ctx.lat), lng: String(ctx.lng), raio_km: String(ctx.raio_km || 10) });
      if (ctx.dest_id > 0) p.set("dest_id", String(ctx.dest_id));
      else if (ctx.q) p.set("q", String(ctx.q));

      fetch("api/map-places.php?" + p.toString(), { credentials: "same-origin" })
        .then(function(r){ return r.json(); })
        .then(function(data) {
          if (token !== pedido) return;
          if (loading) loading.style.display = "none";
          if (!data || !data.success) return;

          markers.clearLayers();
          var pontos = [];

          var o = data.origem || data.centro;
          if (o && o.lat) {
            var oHtml = "<div style=\"background:#2563eb;color:#fff;border-radius:50%;width:16px;height:16px;border:2px solid #fff;\"></div>";
            L.marker([parseFloat(o.lat), parseFloat(o.lng)], {
              icon: L.divIcon({html: oHtml, className:"", iconSize:[16,16], iconAnchor:[8,8]}),
              title: "Centro da busca"
            }).addTo(markers);
          }

          (data.locais||[]).forEach(function(loc){
            if (!loc.lat || !loc.lng) return;
            var lat = parseFloat(loc.lat), lng = parseFloat(loc.lng);
            var mk = L.marker([lat, lng], {icon:pinIcon(STATUS_COLOR[loc.access_status] || "#78716c"), title:loc.name});
            mk.bindPopup(popup(loc,false), {maxWidth:250, className:"pl-popup"});
            mk.addTo(markers);
            pontos.push([lat, lng]);
          });

          var dest = data.destino;
          if (dest && dest.lat) {
            var dm = L.marker([parseFloat(dest.lat), parseFloat(dest.lng)], {icon:destIcon(), zIndexOffset:2000});
            dm.bindPopup(popup(dest,true), {maxWidth:250, className:"pl-popup pl-popup-destino"});
            dm.addTo(markers);
            map.setView([parseFloat(dest.lat), parseFloat(dest.lng)], 16);
            setTimeout(function(){ dm.openPopup(); }, 700);
            var rc = document.querySelector(".map-floating-route-card");
            if (rc) {
              var ti = rc.querySelector(".route-info-title");
              var di = rc.querySelector(".route-info-desc");
              if (ti) ti.textContent = dest.name;
              if (di) di.textContent = dest.address || "Local selecionado";
            }
          } else if (pontos.length > 1) {
            map.fitBounds(L.latLngBounds(pontos).pad(0.2), { maxWidth: 420, maxZoom: 16 });
          }

          var vazio = data.sem_resultado;
          if (vazio && vazio.motivo === "nenhum_local_no_raio") {
            var perto = vazio.mais_proximo;
            avisoMapa("Nenhum local cadastrado em até " + String(vazio.raio_km || ctx.raio_km || 10).replace(".", ",") + " km"
              + (perto ? ". O mais próximo é " + perto.name + (perto.municipio ? " (" + perto.municipio + ")" : "")
                  + ", a " + String(perto.distancia_km).replace(".", ",") + " km." : "."));
          } else if (vazio && vazio.motivo === "nenhum_local_cadastrado") {
            avisoMapa("Ainda não há locais com coordenadas cadastrados na base.");
          }

          map.invalidateSize();
        })
        .catch(function(err){
          console.error("[PlMap] Erro:", err);
          if (token === pedido && loading) loading.style.display = "none";
        });
    }

    function plInitMap() {
      var container = $id("leaflet-map");
      if (!container) { esconderLoading(); return; }
      if (typeof L === "undefined") { esconderLoading(); return; }
      if (container.offsetHeight < 50) container.style.height = "500px";

      map = L.map("leaflet-map", {
        center: [MAPA_PADRAO.lat, MAPA_PADRAO.lng],
        zoom: MAPA_PADRAO.zoom,
        zoomControl: false,
        scrollWheelZoom: true,
        minZoom: 5,
        maxZoom: 19,
        maxBounds: BRASIL_BOUNDS,
        maxBoundsViscosity: 1.0
      });

      var camadaBase = L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
        attribution: "&copy; <a href=\"https://www.openstreetmap.org/copyright\">OpenStreetMap</a> contributors",
        maxZoom: 19
      }).addTo(map);

      osmLayer = L.layerGroup().addTo(map);
      markers = L.layerGroup().addTo(map);

      var bp = $id("btn-zoom-plus"), bm = $id("btn-zoom-minus"), br = $id("btn-map-recenter"), bs = $id("btn-map-reset");
      if (bp) bp.onclick = function(){ map.zoomIn(); };
      if (bm) bm.onclick = function(){ map.zoomOut(); };
      // Recentralizar e redefinir usam o centro vigente da busca (a localização
      // própria é pedida pelo handler de js/lugares-encontrados.js).
      if (br) br.onclick = function(){ if (centroVigente && isFinite(centroVigente.lat)) map.setView([centroVigente.lat, centroVigente.lng], 14); };
      if (bs) bs.onclick = function(){ if (centroVigente && isFinite(centroVigente.lat)) map.setView([centroVigente.lat, centroVigente.lng], 14); };

      var layerDefs = [
        {n:"Padrao",url:"https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"},
        {n:"Carto",url:"https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png"},
        {n:"Satelite",url:"https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}"}
      ];
      var layerIdx = 0;
      var bl = $id("btn-map-layers");
      if (bl) bl.onclick = function(){
        layerIdx = (layerIdx+1) % layerDefs.length;
        if (camadaExtra) { map.removeLayer(camadaExtra); if (layerIdx === 0) { camadaBase.addTo(map); return; } }
        camadaBase.remove();
        camadaExtra = L.tileLayer(layerDefs[layerIdx].url, {maxZoom:19}).addTo(map);
        camadaExtra.bringToBack();
      };

      setTimeout(function(){ map.invalidateSize(); }, 400);

      if (window.plBuscarCentro && window.plBuscarCentro.ouvir) {
        window.plBuscarCentro.ouvir(carregarMarcadores);
      } else {
        esconderLoading();
        avisoMapa("Não foi possível preparar o mapa com a busca atual.");
      }
      document.addEventListener("pl:osm", function (e) { desenhaOsm(e.detail); });
      desenhaOsm(osmUltimo);
    }

    // Seguranca: remove loading apos 12s independente do resultado (a busca pode
    // estar esperando a geocodificacao do endereco pesquisado).
    setTimeout(esconderLoading, 12000);

    if (document.readyState === "loading") {
      document.addEventListener("DOMContentLoaded", plInitMap);
    } else {
      plInitMap();
    }
  }());
  </script>
</body>
</html>
