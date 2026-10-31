<?php
/**
 * Passe Livre Suzano — Explorar Mundo (Mapa e Busca Acessível)
 * Integração PHP / MySQL
 */
require_once __DIR__ . '/includes/auth_guard.php';
$authUser = auth_get_user();
$userData = auth_user_safe($authUser);

$headerName   = $userData['name'] ?? '';
$headerRole   = $userData['role'] ?? '';
$headerAvatar = $userData['avatar'] ?? '';
if ($headerName === '')   $headerName = 'Visitante';
if ($headerRole === '')   $headerRole = 'Visitante';
if ($headerAvatar === '') $headerAvatar = 'assets/images/avatar.jpg';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Passe Livre — Navegação Inclusiva Suzano</title>
  <meta name="description" content="Busca inteligente adaptada para sua mobilidade e autonomia, com rotas verificadas e infraestrutura certificada em Suzano e região.">
  
  <!-- Local Styles -->
  <link rel="stylesheet" href="assets/fonts/fonts.css">
  <link rel="stylesheet" href="css/leaflet.css">
  <link rel="stylesheet" href="css/style.css">
  <link rel="icon" type="image/x-icon" href="favicon.ico">
  <link rel="icon" type="image/png" sizes="32x32" href="assets/icons/favicon-32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="assets/icons/favicon-16.png">
  <link rel="apple-touch-icon" href="assets/icons/apple-touch-icon.png">
</head>
<body class="explore-page">
  <a href="#main-content" class="skip-link">Pular para o conteúdo principal</a>

  <!-- =========================================================================
       1. CABEÇALHO PRINCIPAL (HEADER)
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
      <a href="explorar.php" class="nav-link active" aria-current="page">
        <img src="assets/icons/globe.svg" alt="" aria-hidden="true">
        <span>Explorar Mundo</span>
      </a>
      <a href="configuracao-rota.php" class="nav-link" title="Consultar e configurar rotas acessíveis">
        <img src="assets/icons/routes.svg" alt="" aria-hidden="true">
        <span>Rotas</span>
      </a>
      <a href="lugares-encontrados.php" class="nav-link" title="Pontos de apoio em Suzano">
        <img src="assets/icons/support.svg" alt="" aria-hidden="true">
        <span>Pontos de Apoio</span>
      </a>
      <a href="direitos.php" class="nav-link" title="Direitos e legislações PcD">
        <img src="assets/icons/shield.svg" alt="" aria-hidden="true">
        <span>Direitos</span>
      </a>
    </nav>

    <div class="header-right">
      <button class="btn-icon-notify" id="btn-notifications" aria-label="Notificações e Avisos de Acessibilidade" title="Avisos do Sistema">
        <img src="assets/icons/bell.svg" alt="" aria-hidden="true">
      </button>

      <a href="editar-perfil.php" class="user-profile" id="user-profile-badge" aria-label="Perfil do usuário <?= $headerName ?> - Editar Perfil">
        <img src="<?= $headerAvatar ?>" alt="Foto de perfil de <?= $headerName ?>" class="user-avatar-img" id="header-user-avatar">
        <div class="user-info">
          <span class="user-name" id="header-user-name"><?= $headerName ?></span>
          <span class="user-role" id="header-user-role"><?= $headerRole ?></span>
        </div>
      </a>
    </div>
  </header>

  <!-- =========================================================================
       2. SUBHEADER: BREADCRUMBS & BARRA DE FILTROS
       ========================================================================= -->
  <section class="subheader-container" aria-label="Filtros e Localização na Página">
    <div class="breadcrumb-row">
      <div class="breadcrumb-left">
        <img src="assets/icons/home.svg" alt="" aria-hidden="true">
        <a href="index.php" class="breadcrumb-link">Início</a>
        <span class="breadcrumb-separator">/</span>
        <a href="explorar.php" class="breadcrumb-link">Portal do Cidadão</a>
        <span class="breadcrumb-separator">/</span>
        <span class="breadcrumb-current">Explorar Mundo</span>
      </div>

      <div class="security-badge">
        <img src="assets/icons/lock.svg" alt="" aria-hidden="true">
        <span>Ambiente Seguro • Dados Protegidos (LGPD)</span>
      </div>
    </div>

    <!-- Filtros em formato de pílulas (Pills) -->
    <div class="filters-scroll-track" role="toolbar" aria-label="Filtrar locais por categoria de acessibilidade">
      <a href="adaptar-busca.php" class="filter-chip" style="background: #fff7ed; border-color: #fed7aa; color: #ea580c; text-decoration: none; font-weight: 700;" title="Personalizar opções de acessibilidade da busca">
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

      <button class="filter-chip" data-filter="parking" aria-pressed="false">
        <img src="assets/icons/pcd.svg" alt="" aria-hidden="true">
        <span>Vagas PcD</span>
      </button>
    </div>
  </section>

  <!-- =========================================================================
       3. PALCO PRINCIPAL: MAPA INTERATIVO + CARD CENTRAL
       ========================================================================= -->
  <main class="main-stage" id="main-content">
    
    <!-- Elemento do Mapa Leaflet -->
    <div id="map-background" aria-label="Mapa da cidade de Suzano e arredores"></div>

    <!-- Controles Flutuantes do Mapa (Esquerda) -->
    <div class="map-controls-panel" aria-label="Controles de navegação no mapa">
      <div class="zoom-widget">
        <button class="map-control-btn" id="btn-zoom-in" aria-label="Aumentar zoom do mapa" title="Aproximar">
          <img src="assets/icons/plus.svg" alt="" aria-hidden="true">
        </button>
        <div class="zoom-divider" aria-hidden="true"></div>
        <button class="map-control-btn" id="btn-zoom-out" aria-label="Diminuir zoom do mapa" title="Afastar">
          <img src="assets/icons/minus.svg" alt="" aria-hidden="true">
        </button>
      </div>

      <button class="btn-gps-target" id="btn-recenter-map" aria-label="Centralizar o mapa na minha localização" title="Centralizar na minha localização (ou em Suzano)">
        <img src="assets/icons/target.svg" alt="" aria-hidden="true">
      </button>
    </div>

    <!-- CARD CENTRAL FLUTUANTE -->
    <section class="hero-search-card" aria-labelledby="card-title-heading">
      
      <!-- Badge Superior -->
      <div class="card-badge-wrap">
        <div class="card-badge">
          <img src="assets/icons/badge.svg" alt="" aria-hidden="true">
          <span>Navegação Inclusiva Suzano</span>
        </div>
      </div>

      <!-- Título Principal & Descrição -->
      <h1 class="card-title" id="card-title-heading">Encontre o lugar ideal para você</h1>
      <p class="card-description">
        Busca inteligente adaptada para sua mobilidade e autonomia, com rotas verificadas e infraestrutura certificada.
      </p>

      <!-- Formulário de Busca e Rota -->
      <form class="search-form" id="route-search-form" action="lugares-encontrados.php" method="GET">
        
        <!-- Campo: Ponto de Partida -->
        <div class="field-group">
          <div class="field-header">
            <label for="input-origin" class="field-label-main">
              <img src="assets/icons/pin.svg" alt="" aria-hidden="true">
              <span>Onde você está?</span>
            </label>
            <span class="field-tag-type">Ponto de Partida</span>
          </div>

          <div class="input-with-icon">
            <img src="assets/icons/pin.svg" class="input-icon-lead" alt="" aria-hidden="true">
            <input 
              type="text" 
              id="input-origin" 
              class="custom-input" 
              placeholder="Digite o local de partida ou use o GPS"
              autocomplete="off"
              required
            >
            <input type="hidden" id="input-origin-lat" name="lat" value="">
            <input type="hidden" id="input-origin-lng" name="lng" value="">
            <input type="hidden" id="input-origin-address" name="origem" value="">
          </div>

          <!-- Botão de GPS -->
          <div class="origin-action-row">
            <button type="button" class="btn-gps-inline" id="btn-use-gps" aria-describedby="location-status">
              <img src="assets/icons/gps.svg" alt="" aria-hidden="true">
              <span>Usar minha localização (GPS Ativo)</span>
            </button>
          </div>

          <!-- Resumo da localização escolhida -->
          <div class="location-summary" id="location-summary" hidden>
            <div class="location-summary-header">
              <span class="location-summary-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0Z"></path>
                  <circle cx="12" cy="10" r="3"></circle>
                </svg>
              </span>
              <div class="location-summary-text">
                <p class="location-summary-title" id="location-summary-title">Localização selecionada</p>
                <p class="location-summary-status" id="location-status" role="status" aria-live="polite"></p>
              </div>
            </div>

            <dl class="location-fields">
              <div class="location-field">
                <dt>Logradouro</dt>
                <dd id="loc-street">—</dd>
              </div>
              <div class="location-field">
                <dt>Bairro</dt>
                <dd id="loc-suburb">—</dd>
              </div>
              <div class="location-field">
                <dt>Cidade/UF</dt>
                <dd id="loc-city">—</dd>
              </div>
              <div class="location-field">
                <dt>CEP</dt>
                <dd id="loc-postcode">—</dd>
              </div>
              <div class="location-field">
                <dt>Coordenadas</dt>
                <dd id="loc-coords">—</dd>
              </div>
            </dl>

            <div class="location-summary-actions">
              <button type="button" class="btn-loc-map" id="btn-adjust-map">
                Ajustar no mapa
              </button>
              <button type="button" class="btn-loc-confirm" id="btn-confirm-location" disabled>
                Confirmar localização
              </button>
            </div>
          </div>
        </div>

        <!-- Campo: Destino Acessível -->
        <div class="field-group" style="position: relative;">
          <div class="field-header">
            <label for="input-destination" class="field-label-main">
              <img src="assets/icons/search.svg" alt="" aria-hidden="true">
              <span>O que você procura?</span>
            </label>
            <span class="field-tag-type">Destino Acessível</span>
          </div>

          <div class="input-with-icon">
            <img src="assets/icons/search.svg" class="input-icon-lead" alt="" aria-hidden="true">
            <input 
              type="text" 
              id="input-destination" 
              name="q"
              class="custom-input" 
              placeholder="Ex.: shopping acessível para cadeirantes"
              autocomplete="off"
            >
          </div>

          <!-- Dropdown Autocomplete Dinâmico -->
          <div class="autocomplete-dropdown" id="dest-autocomplete"></div>
        </div>

        <!-- Sugestões Rápidas de Pesquisa -->
        <div class="suggestions-box">
          <div class="suggestions-title">
            <img src="assets/icons/spark.svg" alt="" aria-hidden="true">
            <span>Sugestões rápidas de pesquisa por acessibilidade:</span>
          </div>

          <div class="suggestions-chips-wrap">
            <button type="button" class="chip-suggestion" data-query="Shopping mais próximo acessível para cadeirantes">
              <img src="assets/icons/shopping.svg" alt="" aria-hidden="true">
              <span>Shopping mais próximo acessível para cadeirantes</span>
            </button>

            <button type="button" class="chip-suggestion" data-query="Restaurante acessível perto de mim">
              <img src="assets/icons/food.svg" alt="" aria-hidden="true">
              <span>Restaurante acessível perto de mim</span>
            </button>

            <button type="button" class="chip-suggestion" data-query="Hospital com entrada sem escadas">
              <img src="assets/icons/health.svg" alt="" aria-hidden="true">
              <span>Hospital com entrada sem escadas</span>
            </button>

            <button type="button" class="chip-suggestion" data-query="Cinema acessível para deficientes auditivos">
              <img src="assets/icons/cinema.svg" alt="" aria-hidden="true">
              <span>Cinema acessível para deficientes auditivos</span>
            </button>

            <button type="button" class="chip-suggestion" data-query="Parque com rota acessível">
              <img src="assets/icons/park.svg" alt="" aria-hidden="true">
              <span>Parque com rota acessível</span>
            </button>
          </div>
        </div>

        <!-- Botão Principal de Submissão -->
        <button type="submit" class="btn-submit-search" id="btn-search-action">
          <img src="assets/icons/search.svg" alt="" aria-hidden="true">
          <span>Buscar lugares acessíveis</span>
          <img src="assets/icons/arrow-right.svg" class="arrow-icon" alt="" aria-hidden="true">
        </button>
      </form>

      <!-- Rodapé do Card -->
      <div class="card-bottom-row">
        <div class="inspected-points-tag">
          <img src="assets/icons/check.svg" alt="" aria-hidden="true">
          <span>Mais de 180 pontos vistoriados em Suzano</span>
        </div>

        <a href="#duvidas" class="help-routes-link" id="link-faq-routes">Dúvidas sobre rotas?</a>
      </div>
    </section>

    <!-- BARRA DO MODO DE AJUSTE NO MAPA (tela cheia) -->
    <div class="map-picker-bar" id="map-picker-bar" role="group" aria-labelledby="picker-title" hidden>
      <div class="map-picker-info">
        <p class="map-picker-title" id="picker-title">Ajuste sua localização</p>
        <p class="map-picker-status" id="picker-status" role="status" aria-live="polite">
          Arraste o marcador ou toque no mapa para escolher o ponto exato.
        </p>
        <p class="map-picker-address" id="picker-address"></p>
        <p class="map-picker-coords" id="picker-coords"></p>
      </div>

      <div class="map-picker-actions">
        <button type="button" class="btn-picker-secondary" id="btn-picker-gps">
          <img src="assets/icons/gps.svg" alt="" aria-hidden="true">
          <span>Usar GPS</span>
        </button>
        <button type="button" class="btn-picker-confirm" id="btn-picker-confirm" disabled>
          Confirmar
        </button>
        <button type="button" class="btn-picker-close" id="btn-picker-close">
          Voltar
        </button>
      </div>
    </div>
  </main>

  <!-- =========================================================================
       4. RODAPÉ DA PÁGINA (FOOTER)
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

  <!-- Modal Dúvidas sobre Rotas -->
  <div class="modal-overlay" id="modal-faq" role="dialog" aria-modal="true" aria-labelledby="modal-title">
    <div class="modal-box">
      <div class="modal-header">
        <h3 id="modal-title">Como funcionam as rotas acessíveis?</h3>
        <button class="btn-close-modal" id="btn-close-faq" aria-label="Fechar janela">&times;</button>
      </div>
      <div class="modal-content">
        <p><strong>Certificação NBR 9050 em Suzano:</strong></p>
        <p style="margin-top: 8px;">Nossos trajetos avaliam inclinação de calçadas, rebaixamento de guias, piso tátil direcional e de alerta, semáforos sonoros e portas automáticas.</p>
        <p style="margin-top: 10px;">Todas as informações são cadastradas e vistoriadas pela equipe com suporte aos alunos do SENAI Suzano e comunidade local.</p>
      </div>
    </div>
  </div>

  <!-- Toast de Notificação -->
  <div class="toast-notice" id="app-toast">
    <img src="assets/icons/check.svg" width="18" height="18" alt="">
    <span id="toast-message">Localização atualizada com sucesso!</span>
  </div>

    <!-- Dados do usuário injetados para uso pelo JS -->
  <script id="php-user-data" type="application/json">
    <?= json_encode($userData ?: [
      'id'       => 0,
      'name'     => 'Cidadão',
      'email'    => '',
      'role'     => 'Cidadão Conectado',
      'avatar'   => 'assets/images/avatar.jpg',
      'initials' => 'C',
      'is_admin' => 0,
    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>
  </script>

  <!-- Main Script -->
    <!-- ============================================================
       BANCO DE DADOS MYSQL 8.0+ (Conector PHP PDO / REST)
       Deve ser carregado ANTES de auth.js e dos scripts da tela.
       ============================================================ -->
  <script src="js/db.js" defer></script>
  <script src="js/db-queries.js" defer></script>
  <script src="js/mock-data.js" defer></script>
  <script src="js/auth.js"></script>
  <script src="js/leaflet.js"></script>
  <script src="<?= pl_asset_url('js/main.js') ?>"></script>
</body>
</html>
