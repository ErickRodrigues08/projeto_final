<?php
/**
 * Passe Livre Suzano — Nenhum resultado encontrado
 * Integração PHP / MySQL
 */
require_once __DIR__ . '/includes/auth_guard.php';
$authUser = auth_get_user();
$userData = auth_user_safe($authUser);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Nenhum resultado encontrado — Passe Livre Suzano</title>
  <meta name="description" content="Nenhum local atendeu aos filtros na região pesquisada. Ajuste o raio, troque o endereço ou reduza os recursos exigidos.">
  
  <!-- Fontes: Domine e Plus Jakarta Sans -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="assets/fonts/fonts.css">
  
  <!-- Estilos Globais e da Página -->
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="css/sem-resultados.css">
  <link rel="icon" type="image/x-icon" href="favicon.ico">
  <link rel="icon" type="image/png" sizes="32x32" href="assets/icons/favicon-32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="assets/icons/favicon-16.png">
  <link rel="apple-touch-icon" href="assets/icons/apple-touch-icon.png">
</head>
<body class="no-results-page">
  <a href="#main-content" class="skip-link">Pular para o conteúdo principal</a>

  <!-- =========================================================================
       1. CABEÇALHO PRINCIPAL (HEADER)
       ========================================================================= -->
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

    <!-- Lado Direito: Notificações & Perfil do Usuário com Dropdown -->
    <div class="header-right">
      <a href="notificacoes.php" class="btn-icon-notify" id="btn-notifications" aria-label="Notificações e Avisos de Acessibilidade" title="Avisos do Sistema">
        <img src="assets/icons/bell.svg" alt="" aria-hidden="true">
        <span class="notify-badge-dot" aria-hidden="true"></span>
      </a>

      <div class="user-profile-menu-container">
        <button type="button" class="user-profile-btn" id="user-profile-btn" aria-expanded="false" aria-haspopup="true" aria-label="Menu do usuário João Silva">
          <img src="assets/images/avatar.jpg" alt="Foto de perfil de João Silva" class="user-avatar-img" id="header-user-avatar">
          <div class="user-info">
            <span class="user-name" id="header-user-name">João Silva</span>
            <span class="user-role" id="header-user-role">Cidadão Conectado</span>
          </div>
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#78716c" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="margin-left: 2px;">
            <polyline points="6 9 12 15 18 9"></polyline>
          </svg>
        </button>

        <!-- Menu Suspenso de Ações Rápidas & Autenticação -->
        <div class="user-dropdown-menu" id="user-dropdown-menu" role="menu">
          <div class="dropdown-user-header">
            <div class="dd-name" id="dd-user-name">João Silva</div>
            <div class="dd-email" id="dd-user-email">joao.silva@gmail.com</div>
            <div class="dd-google-badge" id="dd-google-badge">
              <img src="assets/icons/google.svg" width="12" height="12" alt="">
              <span>Conta Google Conectada</span>
            </div>
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

          <a href="index.php" class="dropdown-item danger" id="btn-dd-logout" role="menuitem">
            <img src="assets/icons/logout.svg" width="15" height="15" alt="">
            <span>Sair da conta</span>
          </a>
        </div>
      </div>
    </div>
  </header>

  <!-- =========================================================================
       2. PALCO PRINCIPAL (CARD CENTRAL FLUTUANTE)
       ========================================================================= -->
  <main class="main-stage" id="main-content">
    <section class="no-results-card" aria-labelledby="heading-no-results">
      
      <!-- Visual Superior: Círculo com Ícone & Mini Badge + Pílula -->
      <div class="no-results-visual">
        <div class="no-results-icon-wrap" aria-hidden="true">
          <div class="icon-main-circle">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
              <circle cx="11" cy="11" r="8"></circle>
              <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
              <line x1="8" y1="8" x2="14" y2="14"></line>
            </svg>
          </div>
          <div class="icon-sub-badge" title="Acessibilidade">
            <svg viewBox="0 0 24 24" fill="currentColor">
              <circle cx="14.5" cy="4" r="2.5"/>
              <path d="M14.2 8.5c-.8 0-1.5.5-1.8 1.2l-1.3 3.1c-.2.5-.7.8-1.2.8h-2c-.6 0-1 .4-1 1s.4 1 1 1h2.2c1.3 0 2.4-.8 2.8-2l.9-2.2 2.2 1.4c.5.3.8.8.8 1.4v3.8c0 .6.4 1 1 1s1-.4 1-1v-4c0-1.2-.6-2.3-1.6-2.9l-2.4-1.5.4-1.1c.1-.5.7-.8 1.2-.8h1.8c.6 0 1-.4 1-1s-.4-1-1-1h-2z"/>
              <path d="M12.5 13.5c-.4-.5-.9-.9-1.5-1.1-.3.5-.7 1-1.2 1.4 1.3 1.3 2.1 3.1 2.1 5.2 0 4-3.2 7.2-7.2 7.2-2.1 0-4-.9-5.3-2.3-.4-.4-.4-1.1 0-1.5.4-.4 1.1-.4 1.5 0C2.9 23.4 4.7 24 6.7 24c3 0 5.4-2.4 5.4-5.4 0-1.8-.9-3.4-2.3-4.4.2-.2.5-.4.7-.7z" transform="translate(1, -2)"/>
            </svg>
          </div>
        </div>

        <div class="status-pill-notice" role="status">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
            <line x1="12" y1="9" x2="12" y2="13"></line>
            <line x1="12" y1="17" x2="12.01" y2="17"></line>
          </svg>
          <span>Nenhum resultado encontrado</span>
        </div>
      </div>

      <!-- Título Principal e Descrição -->
      <h1 class="card-title" id="heading-no-results">
        Não encontramos um lugar que atenda a todos os seus critérios.
      </h1>
      <p class="card-desc">
        Os filtros selecionados no momento são muito restritivos para o raio pesquisado. Podemos ampliar sua busca para encontrar opções próximas com excelente acessibilidade.
      </p>

      <!-- Filtros Ativos Aplicados -->
      <section class="active-filters-section" aria-label="Filtros ativos aplicados na busca">
        <div class="active-filters-header">
          <span class="active-filters-label">FILTROS ATIVOS APLICADOS:</span>
          <button type="button" class="btn-clear-all-filters" id="btn-clear-all-filters" title="Limpar todos os filtros ativos">
            Limpar todos
          </button>
        </div>

        <div class="active-chips-track" id="active-chips-track" role="list">
          <div class="filter-active-chip" data-filter="raio" role="listitem">
            <svg class="chip-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <circle cx="12" cy="12" r="10"></circle>
              <polygon points="16.24 7.76 14.12 14.12 7.76 16.24 9.88 9.88 16.24 7.76"></polygon>
            </svg>
            <span>Raio de 1 km</span>
            <button type="button" class="btn-remove-chip" aria-label="Remover filtro Raio de 1 km" title="Remover filtro">&times;</button>
          </div>

          <div class="filter-active-chip" data-filter="alta_acessibilidade" role="listitem">
            <img src="assets/icons/pcd.png" class="chip-icon" alt="" aria-hidden="true">
            <span>Alta Acessibilidade</span>
            <button type="button" class="btn-remove-chip" aria-label="Remover filtro Alta Acessibilidade" title="Remover filtro">&times;</button>
          </div>

          <div class="filter-active-chip" data-filter="audiodescricao" role="listitem">
            <svg class="chip-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path d="M3 18v-6a9 9 0 0 1 18 0v6"></path>
              <path d="M21 19a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3zM3 19a2 2 0 0 0 2 2h1a2 2 0 0 0 2-2v-3a2 2 0 0 0-2-2H3z"></path>
            </svg>
            <span>Com audiodescrição</span>
            <button type="button" class="btn-remove-chip" aria-label="Remover filtro Com audiodescrição" title="Remover filtro">&times;</button>
          </div>
        </div>
      </section>

      <!-- Sugestões Rápidas para Desbloquear Locais (Grid 2x2) -->
      <section class="suggestions-section" aria-label="Sugestões para desbloquear locais">
        <h2 class="suggestions-title">Sugestões rápidas para desbloquear locais:</h2>
        
        <div class="suggestions-grid" role="group">
          <!-- Sugestão 1: Ampliar distância -->
          <button type="button" class="suggestion-card" id="sugg-expand-radius" title="Ampliar distância de busca para 5 km">
            <div class="suggestion-icon-circle" aria-hidden="true">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="4" y1="12" x2="20" y2="12"></line>
                <polyline points="7 9 4 12 7 15"></polyline>
                <polyline points="17 9 20 12 17 15"></polyline>
              </svg>
            </div>
            <div class="suggestion-texts">
              <strong class="suggestion-heading">Ampliar distância de busca</strong>
              <span class="suggestion-sub">Buscar em até 3 km ou 5 km do ponto pesquisado.</span>
            </div>
          </button>

          <!-- Sugestão 2: Aceitar acessibilidade parcial -->
          <button type="button" class="suggestion-card" id="sugg-accept-partial" title="Aceitar estabelecimentos com acessibilidade parcial">
            <div class="suggestion-icon-circle" aria-hidden="true">
              <svg viewBox="0 0 24 24" fill="currentColor">
                <circle cx="12" cy="4" r="2.2"/>
                <path d="M14 8.5c-.3-.3-.7-.5-1.2-.5h-2.5c-.8 0-1.5.5-1.8 1.2l-1.5 3.5c-.2.5 0 1.1.5 1.3.5.2 1.1 0 1.3-.5l1.2-2.8v4.3l-2.2 4.4c-.3.5-.1 1.1.4 1.4.5.3 1.1.1 1.4-.4l2.4-4.8c.2-.3.3-.7.3-1v-4.5l1.2 1.5v6.4c0 .6.4 1 1 1s1-.4 1-1v-7c0-.4-.2-.8-.5-1.1L14 8.5z"/>
                <path d="M17.5 10c-.3 0-.6.2-.8.5l-2.2 6.5h-.1c-.5 0-.9.4-.9.9s.4.9.9.9h1c.4 0 .8-.3.9-.7l2.1-6.4c.2-.5-.1-1.1-.6-1.5-.1-.1-.2-.2-.4-.2z"/>
              </svg>
            </div>
            <div class="suggestion-texts">
              <strong class="suggestion-heading">Aceitar acessibilidade parcial</strong>
              <span class="suggestion-sub">Incluir rampas auditadas e apoio presencial.</span>
            </div>
          </button>

          <!-- Sugestão 3: Remover filtros avançados -->
          <button type="button" class="suggestion-card" id="sugg-remove-advanced" title="Remover filtros avançados e manter apenas essenciais">
            <div class="suggestion-icon-circle" aria-hidden="true">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
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
            </div>
            <div class="suggestion-texts">
              <strong class="suggestion-heading">Remover filtros avançados</strong>
              <span class="suggestion-sub">Manter apenas os requisitos essenciais.</span>
            </div>
          </button>

          <!-- Sugestão 4: Ver todos os locais -->
          <button type="button" class="suggestion-card" id="sugg-view-all" title="Ver todos os locais mapeados sem filtros">
            <div class="suggestion-icon-circle" aria-hidden="true">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <polygon points="1 6 1 22 8 18 16 22 23 18 23 2 16 6 8 2 1 6"></polygon>
                <line x1="8" y1="2" x2="8" y2="18"></line>
                <line x1="16" y1="6" x2="16" y2="22"></line>
              </svg>
            </div>
            <div class="suggestion-texts">
              <strong class="suggestion-heading">Ver todos os locais mapeados</strong>
              <span class="suggestion-sub">Explorar o mapa geral sem restrições.</span>
            </div>
          </button>
        </div>
      </section>

      <!-- Rodapé de Ações do Card (Botões Inferiores) -->
      <div class="card-bottom-actions">
        <button type="button" class="btn-action-secondary" id="btn-view-nearest" title="Consultar os estabelecimentos mais próximos de sua localização">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
            <circle cx="12" cy="10" r="3"></circle>
          </svg>
          <span>Ver locais mais próximos</span>
        </button>

        <button type="button" class="btn-action-primary" id="btn-adjust-filters" title="Personalizar e ajustar opções de acessibilidade">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <line x1="4" y1="6" x2="20" y2="6"></line>
            <line x1="7" y1="12" x2="17" y2="12"></line>
            <line x1="10" y1="18" x2="14" y2="18"></line>
          </svg>
          <span>Ajustar filtros e buscar novamente</span>
        </button>
      </div>

    </section>
  </main>

  <!-- =========================================================================
       3. RODAPÉ DA PÁGINA (FOOTER CONFORME SCREENSHOT)
       ========================================================================= -->
  <footer class="site-footer" role="contentinfo">
    <p class="footer-copy">
      &copy; 2026 Passe Livre. Todos os direitos reservados. Projeto desenvolvido por Samuel, Rodrigo, Erick, Gabrielly e Ana Júlia — SENAI, Suzano.
    </p>
    <div class="footer-links">
      <a href="ajuda.php#termos">Termos de Uso</a>
      <a href="ajuda.php#privacidade">Privacidade (LGPD)</a>
      <a href="ajuda.php#acessibilidade">Acessibilidade</a>
      <a href="index.php#contato">Fale Conosco</a>
    </div>
  </footer>

  <!-- Toast Notificação Feedback -->
  <div class="toast-notice" id="app-toast" role="status" aria-live="polite">
    <img src="assets/icons/check.svg" alt="" aria-hidden="true">
    <span id="toast-message">Filtro atualizado com sucesso!</span>
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

  <!-- Scripts -->
    <!-- ============================================================
       BANCO DE DADOS MYSQL 8.0+ (Conector PHP PDO / REST)
       Deve ser carregado ANTES de auth.js e dos scripts da tela.
       ============================================================ -->
  <script src="js/db.js" defer></script>
  <script src="js/db-queries.js" defer></script>
  <script src="js/mock-data.js" defer></script>
  <script src="js/auth.js"></script>
  <script src="js/sem-resultados.js"></script>
</body>
</html>
