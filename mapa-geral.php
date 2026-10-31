<?php
/**
 * Passe Livre Suzano — Mapa Geral de Acessibilidade
 * Integração PHP / MySQL
 */
require_once __DIR__ . '/includes/auth_guard.php';
require_once __DIR__ . '/api/_db_restore.php';
$authUser = auth_get_user();
$userData = auth_user_safe($authUser);

/* Números do painel: contagem real da base de locais. As estatísticas fixas
   ("184 pontos", "72%", "62%") não tinham lastro no banco e prometiam uma
   cobertura auditada que não existe. */
$plStats = ['total' => 0, 'verified' => 0, 'partial' => 0, 'other' => 0, 'municipios' => 0];
try {
    $plDb = get_db();
    $plLinha = $plDb->query("
        SELECT COUNT(*) AS total,
               SUM(access_status = 'verified')  AS verified,
               SUM(access_status = 'partial')   AS partial,
               SUM(access_status NOT IN ('verified','partial')) AS other,
               COUNT(DISTINCT NULLIF(municipio,'')) AS municipios
        FROM locais WHERE is_archived = 0
    ")->fetch();
    if ($plLinha) {
        $plStats = [
            'total'      => (int)$plLinha['total'],
            'verified'   => (int)$plLinha['verified'],
            'partial'    => (int)$plLinha['partial'],
            'other'      => (int)$plLinha['other'],
            'municipios' => (int)$plLinha['municipios'],
        ];
    }
} catch (Throwable $e) {
    /* Sem banco o painel mostra zero; nada é inventado. */
}
$plPct = fn(int $n) => $plStats['total'] > 0 ? (int)round($n * 100 / $plStats['total']) : 0;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Mapa Geral de Acessibilidade — Passe Livre</title>
  <meta name="description" content="Mapa interativo de acessibilidade centrado no endereço pesquisado, com rotas, relatos de barreiras e locais cadastrados na região.">
  
  <!-- Fontes Oficiais: Domine (Serif) e Plus Jakarta Sans -->
  <link rel="stylesheet" href="assets/fonts/fonts.css">
  
  <!-- Estilos do Mapa e da Aplicação -->
  <link rel="stylesheet" href="css/leaflet.css">
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="css/mapa-geral.css">
  <link rel="icon" type="image/x-icon" href="favicon.ico">
  <link rel="icon" type="image/png" sizes="32x32" href="assets/icons/favicon-32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="assets/icons/favicon-16.png">
  <link rel="apple-touch-icon" href="assets/icons/apple-touch-icon.png">
</head>
<body>
  <a href="#interactive-map" class="skip-link">Pular para o mapa interativo</a>

  <!-- =========================================================================
       1. CABEÇALHO SUPERIOR (SITE HEADER)
       ========================================================================= -->
  <header class="site-header" role="banner">
    <div class="header-left">
      <a href="index.php" class="brand-logo" title="Passe Livre — Início">
        <img src="assets/icons/logo.png" alt="Passe Livre" width="160" height="32">
      </a>

      <!-- Campo de Busca no Topo -->
      <div class="header-search-wrap">
        <img src="assets/icons/search.svg" class="search-icon" alt="" aria-hidden="true">
        <input 
          type="search" 
          id="global-search-input" 
          class="header-search-input" 
          placeholder="Buscar vias, pontos ou linhas..." 
          aria-label="Buscar vias, pontos ou linhas no sistema"
          autocomplete="off"
        >
        <div class="search-autocomplete-dropdown" id="search-dropdown"></div>
      </div>
    </div>

    <!-- Navegação Principal -->
    <nav class="header-nav" aria-label="Navegação Principal">
      <a href="explorar.php" class="nav-link" title="Explorar o mapa da região pesquisada">
        <img src="assets/icons/globe.svg" alt="" aria-hidden="true">
        <span>Explorar Mundo</span>
      </a>
      <a href="mapa-geral.php" class="nav-link active" aria-current="page" title="Traçar e consultar rotas acessíveis">
        <img src="assets/icons/routes.svg" alt="" aria-hidden="true">
        <span>Rotas</span>
      </a>
      <a href="lugares-encontrados.php" class="nav-link" title="Pontos de apoio e locais cadastrados">
        <img src="assets/icons/support.svg" alt="" aria-hidden="true">
        <span>Pontos de Apoio</span>
      </a>
      <a href="direitos.php" class="nav-link" title="Cartilha de direitos e acessibilidade">
        <img src="assets/icons/shield.svg" alt="" aria-hidden="true">
        <span>Direitos</span>
      </a>
    </nav>

    <!-- Notificações e Perfil do Usuário -->
    <div class="header-right">
      <a href="notificacoes.php" class="btn-icon-notify" id="btn-notifications" aria-label="Notificações e Avisos de Acessibilidade" title="Avisos do Sistema">
        <img src="assets/icons/bell.svg" alt="" aria-hidden="true">
      </a>

      <a href="editar-perfil.php" class="user-profile" id="user-profile-badge" aria-label="Perfil do usuário João Silva - Cidadão Conectado">
        <img src="assets/images/avatar.jpg" alt="Foto de perfil de João Silva" class="user-avatar-img" id="header-user-avatar">
        <div class="user-info">
          <span class="user-name" id="header-user-name">João Silva</span>
          <span class="user-role" id="header-user-role">Cidadão Conectado</span>
        </div>
      </a>
    </div>
  </header>

  <!-- =========================================================================
       2. SUBHEADER: TÍTULO "MAPA GERAL DE ACESSIBILIDADE" + FILTROS
       ========================================================================= -->
  <section class="subheader-toolbar" aria-label="Filtros e Categoria de Acessibilidade">
    <!-- Título do Mapa -->
    <div class="subheader-title-group">
      <div class="subheader-map-icon" aria-hidden="true">
        <img src="assets/icons/map-folded.svg" alt="">
      </div>
      <h1 class="subheader-title">Mapa Geral de Acessibilidade</h1>
    </div>

    <!-- Pílulas de Filtros de Acessibilidade -->
    <div class="filter-pills-row" role="toolbar" aria-label="Filtrar pontos por tipo de acessibilidade">
      <button class="filter-pill active" data-filter="all" aria-pressed="true">
        <img src="assets/icons/check.svg" alt="" aria-hidden="true">
        <span>Todos</span>
      </button>

      <button class="filter-pill" data-filter="cadeirante" aria-pressed="false">
        <img src="assets/icons/wheelchair.svg" alt="" aria-hidden="true">
        <span>Cadeirante</span>
      </button>

      <button class="filter-pill" data-filter="visual" aria-pressed="false">
        <img src="assets/icons/blind-cane.svg" alt="" aria-hidden="true">
        <span>Deficiência Visual</span>
      </button>

      <button class="filter-pill" data-filter="auditivo" aria-pressed="false">
        <img src="assets/icons/hearing-aid.svg" alt="" aria-hidden="true">
        <span>Deficiência Auditiva</span>
      </button>

      <button class="filter-pill" data-filter="banheiro" aria-pressed="false">
        <img src="assets/icons/restroom.svg" alt="" aria-hidden="true">
        <span>Banheiros Adaptados</span>
      </button>

      <button class="filter-pill" data-filter="vaga" aria-pressed="false">
        <img src="assets/icons/parking-pcd.svg" alt="" aria-hidden="true">
        <span>Vagas PcD</span>
      </button>

      <button class="filter-pill" data-filter="entrada" aria-pressed="false">
        <img src="assets/icons/accessible-entrance.svg" alt="" aria-hidden="true">
        <span>Entradas Acessíveis</span>
      </button>

      <button class="filter-pill" data-filter="obstaculo" aria-pressed="false">
        <img src="assets/icons/warning.svg" alt="" aria-hidden="true">
        <span>Obstáculos Ativos</span>
      </button>
    </div>
  </section>

  <!-- =========================================================================
       3. PALCO DO MAPA INTERATIVO (LEAFLET + PAINÉIS FLUTUANTES)
       ========================================================================= -->
  <main id="main-content" class="map-stage-container" role="main">
    
    <!-- Elemento Canvas do Mapa Real (região definida pela busca) -->
    <div id="interactive-map" aria-label="Mapa interativo de acessibilidade da região em foco"></div>

    <!-- PAINEL FLUTUANTE ESQUERDO: LEGENDA TÉCNICA -->
    <aside class="floating-legend-panel" aria-label="Legenda Técnica de Conformidade NBR 9050">
      <div class="legend-header">
        <div class="legend-title-wrap">
          <img src="assets/icons/shield-check.svg" alt="" aria-hidden="true">
          <h2 class="legend-title">Legenda Técnica</h2>
        </div>
        <span class="legend-nbr-badge">NBR 9050</span>
      </div>

      <!-- Grau de Conformidade -->
      <div>
        <div class="legend-section-label">GRAU DE CONFORMIDADE</div>
        <div class="legend-items-list">
          <!-- Alta Acessibilidade -->
          <div class="legend-item">
            <span class="legend-dot-indicator dot-high" aria-hidden="true"></span>
            <div class="legend-item-content">
              <span class="legend-item-title">Alta acessibilidade</span>
              <p class="legend-item-desc">Atende plenamente aos critérios de largura, rampas &le; 8,33%, piso tátil e sanitários.</p>
            </div>
          </div>

          <!-- Acessibilidade Parcial -->
          <div class="legend-item">
            <span class="legend-dot-indicator dot-partial" aria-hidden="true"></span>
            <div class="legend-item-content">
              <span class="legend-item-title">Acessibilidade parcial</span>
              <p class="legend-item-desc">Uso assistido ou rota com restrições pontuais (ex: desníveis &lt; 2cm, sinalização incompleta).</p>
            </div>
          </div>

          <!-- Baixa Acessibilidade / Barreiras -->
          <div class="legend-item">
            <span class="legend-dot-indicator dot-low" aria-hidden="true"></span>
            <div class="legend-item-content">
              <span class="legend-item-title">Baixa acessibilidade / Barreiras</span>
              <p class="legend-item-desc">Degraus sem rampa, ausência de rota acessível ou obstáculos físicos intransponíveis.</p>
            </div>
          </div>

          <!-- Não Avaliado / Auditoria -->
          <div class="legend-item">
            <span class="legend-dot-indicator dot-audit" aria-hidden="true"></span>
            <div class="legend-item-content">
              <span class="legend-item-title">Não avaliado / Auditoria</span>
              <p class="legend-item-desc">Local em fila de inspeção técnica voluntária ou cidadã.</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Recursos Mapeados -->
      <div>
        <div class="legend-section-label">RECURSOS MAPEADOS</div>
        <div class="legend-resources-grid">
          <div class="resource-chip">
            <img src="assets/icons/ramp.svg" alt="" aria-hidden="true">
            <span>Rampas &amp; Vias</span>
          </div>
          <div class="resource-chip">
            <img src="assets/icons/tactile-paving.svg" alt="" aria-hidden="true">
            <span>Piso Podotátil</span>
          </div>
          <div class="resource-chip">
            <img src="assets/icons/audio-signal.svg" alt="" aria-hidden="true">
            <span>Sinal Sonoro / Libras</span>
          </div>
          <div class="resource-chip">
            <img src="assets/icons/restroom-pcd.svg" alt="" aria-hidden="true">
            <span>Sanitário PcD</span>
          </div>
          <div class="resource-chip">
            <img src="assets/icons/parking-pcd.svg" alt="" aria-hidden="true">
            <span>Vagas Especiais</span>
          </div>
          <div class="resource-chip">
            <img src="assets/icons/active-block.svg" alt="" aria-hidden="true">
            <span>Bloqueio Ativo</span>
          </div>
        </div>
      </div>

      <!-- Botão Reportar Nova Barreira -->
      <button type="button" class="btn-report-barrier" id="btn-report-barrier">
        <img src="assets/icons/flag-alert.svg" alt="" aria-hidden="true">
        <span>Reportar nova barreira</span>
      </button>

      <!-- Botão Encontrar Local -->
      <a href="explorar.php" class="btn-report-barrier" id="btn-find-location">
        <img src="assets/icons/search.svg" alt="" aria-hidden="true">
        <span>Encontrar Local</span>
      </a>
    </aside>

    <!-- PAINEL FLUTUANTE DIREITO: SUZANO EM DADOS -->
    <aside class="floating-stats-panel" aria-label="Estatísticas e Auditorias de Suzano">
      <div class="stats-header">
        <div class="stats-title-wrap">
          <img src="assets/icons/bar-chart.svg" alt="" aria-hidden="true">
          <h2 class="stats-title">Suzano em Dados</h2>
        </div>
        <span class="stats-live-dot" title="Cadastros e relatos monitorados pela plataforma"></span>
      </div>

      <!-- Card 1: locais realmente cadastrados -->
      <div class="stat-card-row">
        <div class="stat-icon-circle" aria-hidden="true">
          <img src="assets/icons/clipboard-check.svg" alt="">
        </div>
        <div class="stat-info-text">
          <span class="stat-number" id="stat-counter-audited"><?= (int)$plStats['total'] ?></span>
          <span class="stat-label">locais na base (<?= (int)$plStats['municipios'] ?> município(s))</span>
        </div>
      </div>

      <!-- Card 2: share realmente verificado -->
      <div class="stat-card-row">
        <div class="stat-icon-circle" aria-hidden="true">
          <img src="assets/icons/footsteps.svg" alt="">
        </div>
        <div class="stat-info-text">
          <span class="stat-number amber"><?= $plPct($plStats['verified']) ?>%</span>
          <span class="stat-label">dos cadastros com vistoria concluída</span>
        </div>
      </div>

      <!-- Barra de Status Geral das Vias -->
      <div class="status-bar-section">
        <div class="status-bar-labels">
          <span class="status-label-left">Situação dos locais cadastrados</span>
          <span class="status-label-right"><?= $plPct($plStats['verified']) ?>% verificados</span>
        </div>
        <div class="status-progress-track" aria-label="Distribuição dos locais cadastrados: <?= $plStats['verified'] ?> verificados, <?= $plStats['partial'] ?> parciais, <?= $plStats['other'] ?> sem verificação">
          <div class="status-seg-green" style="width:<?= $plPct($plStats['verified']) ?>%"></div>
          <div class="status-seg-yellow" style="width:<?= $plPct($plStats['partial']) ?>%"></div>
          <div class="status-seg-red" style="width:<?= $plPct($plStats['other']) ?>%"></div>
        </div>
      </div>

      <!-- Aviso Acadêmico SENAI Suzano -->
      <div class="academic-notice-box">
        <img src="assets/icons/academic-cap.svg" alt="" aria-hidden="true">
        <p class="academic-notice-text">
          Auditorias de campo conduzidas pela equipe discente do SENAI Suzano em conformidade com as diretrizes do Passe Livre e Plano de Mobilidade Municipal.
        </p>
      </div>

      <!-- Botão Download de Relatório -->
      <a href="relatorios.php" class="btn-download-report" id="btn-download-pdf">
        <img src="assets/icons/download.svg" alt="" aria-hidden="true">
        <span>Ver relatórios e análises</span>
      </a>
    </aside>

    <!-- CONTROLES FLUTUANTES DO MAPA (CANTO INFERIOR DIREITO) -->
    <div class="map-floating-controls" aria-label="Controles de zoom e centralização">
      <button class="map-control-btn" id="btn-zoom-in" aria-label="Aumentar zoom do mapa" title="Aproximar">
        <img src="assets/icons/plus.svg" alt="" aria-hidden="true">
      </button>
      <button class="map-control-btn" id="btn-zoom-out" aria-label="Diminuir zoom do mapa" title="Afastar">
        <img src="assets/icons/minus.svg" alt="" aria-hidden="true">
      </button>
      <button class="map-control-btn btn-recenter-target" id="btn-recenter-map" aria-label="Centralizar na região da busca" title="Usar minha localização">
        <img src="assets/icons/target.svg" alt="" aria-hidden="true">
      </button>
    </div>
  </main>

  <!-- =========================================================================
       4. RODAPÉ DA PÁGINA (SITE FOOTER)
       ========================================================================= -->
  <footer class="site-footer" role="contentinfo">
    <div class="footer-credits">
      &copy; 2026 Passe Livre. Todos os direitos reservados. Projeto desenvolvido por: Samuel, Rodrigo, Erick, Gabrielly e Ana Júlia — SENAI, Suzano.
    </div>
    <div class="footer-links">
      <a href="ajuda.php#termos" class="footer-link-item">Termos de Uso</a>
      <span style="color: #d6cbbe;">&bull;</span>
      <a href="ajuda.php#privacidade" class="footer-link-item">Privacidade (LGPD)</a>
      <span style="color: #d6cbbe;">&bull;</span>
      <a href="ajuda.php#acessibilidade" class="footer-link-item">Acessibilidade</a>
      <span style="color: #d6cbbe;">&bull;</span>
      <a href="ajuda.php#contato" class="footer-link-item">Fale Conosco</a>
    </div>
  </footer>

  <!-- =========================================================================
       5. MODAL INTERATIVO: REPORTAR NOVA BARREIRA
       ========================================================================= -->
  <div class="modal-backdrop" id="modal-report-barrier" role="dialog" aria-modal="true" aria-labelledby="modal-barrier-title">
    <div class="modal-card">
      <div class="modal-header">
        <h3 class="modal-title" id="modal-barrier-title">Reportar Nova Barreira Viária</h3>
        <button type="button" class="modal-btn-close" id="btn-close-barrier-modal" aria-label="Fechar janela">&times;</button>
      </div>

      <form id="form-report-barrier">
        <div class="modal-body">
          <div class="modal-field">
            <label for="barrier-address" class="modal-label">Endereço ou Local da Ocorrência *</label>
            <input type="text" id="barrier-address" class="modal-input" placeholder="Ex: Rua General Francisco Glicério, 400 - Centro" required>
          </div>

          <div class="modal-field">
            <label for="barrier-category" class="modal-label">Tipo de Obstáculo / Barreira *</label>
            <select id="barrier-category" class="modal-select" required>
              <option value="Degrau sem rampa de acesso">Degrau sem rampa de acesso</option>
              <option value="Calçada quebrada / Buraco ou Obra">Calçada quebrada / Buraco ou Obra</option>
              <option value="Ausência ou descontinuidade de piso tátil">Ausência ou descontinuidade de piso tátil</option>
              <option value="Guia sem rebaixo para cadeirantes">Guia sem rebaixo para cadeirantes</option>
              <option value="Vaga PcD ocupada ou obstruída">Vaga PcD ocupada ou obstruída</option>
              <option value="Semáforo sem aviso sonoro">Semáforo sem aviso sonoro</option>
              <option value="Outro obstáculo físico">Outro obstáculo físico</option>
            </select>
          </div>

          <div class="modal-field">
            <label for="barrier-notes" class="modal-label">Descrição detalhada e pontos de referência</label>
            <textarea id="barrier-notes" class="modal-textarea" placeholder="Descreva as condições da via para que a comissão técnica do SENAI possa vistoriar..."></textarea>
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn-secondary" id="btn-cancel-barrier">Cancelar</button>
          <button type="submit" class="btn-primary">Registrar Ocorrência</button>
        </div>
      </form>
    </div>
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
  <script src="js/leaflet.js"></script>
    <!-- ============================================================
       BANCO DE DADOS MYSQL 8.0+ (Conector PHP PDO / REST)
       Deve ser carregado ANTES de auth.js e dos scripts da tela.
       ============================================================ -->
  <script src="js/db.js" defer></script>
  <script src="js/db-queries.js" defer></script>
  <script src="js/mock-data.js" defer></script>
  <script src="js/auth.js"></script>
  <script src="js/mapa-geral.js"></script>
</body>
</html>
