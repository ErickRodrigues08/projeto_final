<?php
/**
 * Passe Livre Suzano — Alerta de Obstáculo (Cidadão)
 * Exibição de rota alternativa acessível e desvio inteligente.
 */
require_once __DIR__ . '/includes/auth_guard.php';
$userRaw  = auth_require();
$userData = auth_user_safe($userRaw);

// Pré-carrega ocorrência se ID fornecido
$ocorrencia = null;
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id > 0) {
    try {
        $db = get_db();
        $stmt = $db->prepare("SELECT * FROM ocorrencias WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $ocorrencia = $stmt->fetch();
    } catch (\Throwable $e) {}
}
if (!$ocorrencia) {
    // Busca ocorrência ativa mais urgente por padrão
    try {
        if (!isset($db)) $db = get_db();
        $ocorrencia = $db->query("SELECT * FROM ocorrencias WHERE status = 'ativa' ORDER BY CASE gravidade WHEN 'urgente' THEN 1 WHEN 'alta' THEN 2 ELSE 3 END ASC, id DESC LIMIT 1")->fetch();
    } catch (\Throwable $e) {}
}

$unreadCount = 0;
try {
    $stmtUnread = $db->prepare("SELECT COUNT(*) FROM notificacoes WHERE user_id = ? AND is_read = 0");
    $stmtUnread->execute([$userRaw['id']]);
    $unreadCount = (int)$stmtUnread->fetchColumn();
} catch (\Throwable $e) {}

/* O alerta só pode mostrar o que a ocorrência registra. Sem linha no banco,
   distance, desvio e tipo de barreira não podem ser inventados. */
$temOcorrencia = is_array($ocorrencia);
$alertaTitulo  = $temOcorrencia ? trim((string) $ocorrencia['titulo']) : '';
$alertaDesc    = $temOcorrencia ? trim((string) $ocorrencia['descricao']) : '';
$alertaLocal   = $temOcorrencia ? trim((string) $ocorrencia['endereco']) : '';
$alertaAutor   = $temOcorrencia ? trim((string) ($ocorrencia['reporter_nome'] ?? '')) : '';
$alertaCat     = $temOcorrencia ? trim((string) $ocorrencia['categoria']) : '';
$alertaGrav    = $temOcorrencia ? trim((string) $ocorrencia['gravidade']) : '';
$alertaDesvio  = $temOcorrencia ? trim((string) ($ocorrencia['rota_alternativa_sugerida'] ?? '')) : '';
$alertaLat     = $temOcorrencia && is_numeric($ocorrencia['lat'] ?? null) ? (float) $ocorrencia['lat'] : null;
$alertaLng     = $temOcorrencia && is_numeric($ocorrencia['lng'] ?? null) ? (float) $ocorrencia['lng'] : null;
$alertaTags    = array_values(array_filter([
    $alertaCat,
    $alertaGrav !== '' ? 'Gravidade informada: ' . $alertaGrav : '',
]));
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Alerta de Obstáculo — Passe Livre Suzano</title>
  <meta name="description" content="Informações sobre obstáculos e barreiras relatados na rota atual, com desvio sugerido e registro de reforço para qualquer município de São Paulo.">
  <link rel="stylesheet" href="assets/fonts/fonts.css">
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="css/alerta-obstaculo.css">
  <link rel="stylesheet" href="css/leaflet.css">
  <link rel="icon" type="image/x-icon" href="favicon.ico">
  <link rel="icon" type="image/png" sizes="32x32" href="assets/icons/favicon-32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="assets/icons/favicon-16.png">
  <link rel="apple-touch-icon" href="assets/icons/apple-touch-icon.png">
</head>
<body class="alerta-page">
  <a href="#main-content" class="skip-link">Pular para o conteúdo principal</a>

  <!-- Header -->
  <header class="site-header" role="banner">
    <div class="header-left">
      <a href="index.php" class="brand-logo" title="Voltar para a Página Inicial">
        <img src="assets/icons/logo.png" alt="Passe Livre Suzano" width="160" height="38" onerror="this.src='assets/icons/logo.svg'">
      </a>
      <div class="header-search-wrap">
        <img src="assets/icons/search.svg" class="search-icon" alt="" aria-hidden="true">
        <input type="search" id="global-search-input" class="header-search-input" placeholder="Buscar vias, pontos ou linhas..." aria-label="Buscar vias, pontos ou linhas">
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
      <a href="central-notificacoes.php" class="btn-icon-notify" aria-label="Notificações">
        <img src="assets/icons/bell.svg" alt="" aria-hidden="true">
        <?php if ($unreadCount > 0): ?>
          <span class="notify-badge-dot" aria-hidden="true"></span>
        <?php endif; ?>
      </a>

      <a href="editar-perfil.php" class="user-profile" id="user-profile-badge" aria-label="Perfil do usuário <?= htmlspecialchars($userData['name']) ?>">
        <img src="<?= htmlspecialchars($userData['avatar'] ?: 'assets/images/avatar.jpg') ?>" alt="Foto de <?= htmlspecialchars($userData['name']) ?>" class="user-avatar-img" id="header-user-avatar" onerror="this.src='assets/images/avatar.jpg'">
        <div class="user-info">
          <span class="user-name" id="header-user-name"><?= htmlspecialchars($userData['name']) ?></span>
          <span class="user-role" id="header-user-role"><?= htmlspecialchars($userData['role']) ?></span>
        </div>
      </a>
    </div>
  </header>

  <!-- Palco do Mapa -->
  <main class="obstacle-stage-container" id="main-content" role="main">
    
    <div id="obstacle-map" aria-label="Esquema ilustrativo do obstáculo relatado e do desvio sugerido; não representa as ruas da região pesquisada"></div>

    <!-- Controles do HUD -->
    <div class="map-floating-hud" role="toolbar" aria-label="Controles do mapa">
      <div class="map-zoom-group">
        <button type="button" class="btn-map-hud" id="btn-map-zoom-in" title="Aproximar mapa (+)" aria-label="Aproximar mapa">
          <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        </button>
        <button type="button" class="btn-map-hud" id="btn-map-zoom-out" title="Afastar mapa (−)" aria-label="Afastar mapa">
          <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><line x1="5" y1="12" x2="19" y2="12"/></svg>
        </button>
      </div>
      <button type="button" class="btn-map-hud btn-hud-recenter" id="btn-map-recenter" title="Centralizar ponto de alerta" aria-label="Centralizar alerta">
        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><circle cx="12" cy="12" r="7"/><line x1="12" y1="1" x2="12" y2="5"/><line x1="12" y1="19" x2="12" y2="23"/><line x1="1" y1="12" x2="5" y2="12"/><line x1="19" y1="12" x2="23" y2="12"/></svg>
        <span class="hud-btn-label">Centralizar</span>
      </button>
      <button type="button" class="btn-map-hud btn-hud-toggle-modal" id="btn-toggle-modal-view" title="Alternar visão do mapa completo" aria-label="Alternar visão do mapa">
        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
        <span class="hud-btn-label" id="lbl-toggle-modal">Ver Mapa Completo</span>
      </button>
    </div>

    <!-- Banner minimizado -->
    <div class="minimized-alert-banner" id="minimized-alert-banner" style="display: none;" role="region" aria-label="Resumo do alerta">
      <div class="minimized-alert-left">
        <span class="minimized-badge-alert">⚠️ ALERTA</span>
        <span class="minimized-alert-title"><?= htmlspecialchars($alertaTitulo !== '' ? $alertaTitulo : 'Nenhuma ocorrência ativa registrada') ?></span>
      </div>
      <div class="minimized-alert-actions">
        <button type="button" class="btn-mini-detour" id="btn-mini-detour-action">Desviar agora</button>
        <button type="button" class="btn-mini-expand" id="btn-expand-modal-action">Expandir Detalhes</button>
      </div>
    </div>

    <div class="map-feature-tooltip" id="map-feature-tooltip" role="tooltip" aria-hidden="true"></div>

    <!-- Modal Flutuante de Alerta -->
    <aside class="obstacle-alert-modal" role="dialog" aria-labelledby="modal-obstacle-title" aria-modal="true">
      <div class="modal-top-badges-row">
        <span class="badge-obstruction-alert">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
            <line x1="12" y1="9" x2="12" y2="13"></line>
            <line x1="12" y1="17" x2="12.01" y2="17"></line>
          </svg>
          <span>ALERTA DE OBSTRUÇÃO</span>
        </span>

        <span class="badge-user-distance" id="badge-obstacle-distance"
              data-lat="<?= $alertaLat !== null ? htmlspecialchars((string) $alertaLat) : '' ?>"
              data-lng="<?= $alertaLng !== null ? htmlspecialchars((string) $alertaLng) : '' ?>">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <line x1="22" y1="2" x2="11" y2="13"></line>
            <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
          </svg>
          <span id="obstacle-distance-text">Distância não medida</span>
        </span>
      </div>

      <h1 class="obstacle-main-title" id="modal-obstacle-title"><?= htmlspecialchars($alertaTitulo !== '' ? $alertaTitulo : 'Sem ocorrência ativa nesta rota') ?></h1>

      <p class="obstacle-main-desc">
        <?php if ($alertaDesc !== ''): ?>
          Relato registrado na plataforma: <strong><?= htmlspecialchars($alertaDesc) ?></strong>
        <?php else: ?>
          Nenhuma ocorrência com descrição registrada foi encontrada para este alerta.
        <?php endif; ?>
      </p>

      <div class="report-details-card">
        <div class="report-header-row">
          <div class="report-author-group">
            <div class="report-shield-icon-wrap" aria-hidden="true">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                <polyline points="9 12 11 14 15 10"></polyline>
              </svg>
            </div>
            <div class="report-text-wrap">
              <strong class="report-author-title">Reportado por <?= htmlspecialchars($alertaAutor !== '' ? $alertaAutor : 'não informado') ?></strong>
              <span class="report-cause-text">
                <strong class="cause-label">Local:</strong> <?= htmlspecialchars($alertaLocal !== '' ? $alertaLocal : 'endereço não informado no relato') ?>
              </span>
            </div>
          </div>
          <span class="report-active-dot" title="Ocorrência registrada no banco de dados" aria-hidden="true"></span>
        </div>

        <div class="report-inner-divider" aria-hidden="true"></div>

        <div class="report-tags-row">
          <?php if ($alertaTags): ?>
            <?php foreach ($alertaTags as $i => $tag): ?>
              <?php if ($i > 0): ?><span class="tag-dot-sep" aria-hidden="true">&bull;</span><?php endif; ?>
              <div class="report-tag-item">
                <span><?= htmlspecialchars($tag) ?></span>
              </div>
            <?php endforeach; ?>
          <?php else: ?>
            <div class="report-tag-item">
              <span>Categoria não informada no relato</span>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Box de Rota Recomendada -->
      <div class="recommended-detour-box">
        <div class="detour-box-header">
          <div class="detour-title-wrap">
            <span class="detour-dot-orange" aria-hidden="true"></span>
            <strong class="detour-title-text"><?= $alertaDesvio !== '' ? 'Desvio sugerido no cadastro' : 'Nenhum desvio verificado' ?></strong>
          </div>
          <span class="badge-detour-piso">Acessibilidade não verificada</span>
        </div>

        <p class="detour-info-text">
          <?= $alertaDesvio !== '' ? htmlspecialchars($alertaDesvio) : 'Ainda não existe desvio conferido para esta ocorrência. O traçado laranja do mapa é um esquema ilustrativo e não confirma ruas acessíveis.' ?>
        </p>

        <button type="button" class="btn-detour-now" id="btn-detour-action">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
            <line x1="12" y1="9" x2="12" y2="13"></line>
            <line x1="12" y1="17" x2="12.01" y2="17"></line>
          </svg>
          <span>Desviar agora</span>
        </button>
      </div>

      <button type="button" class="btn-continue-manual" id="btn-continue-manual">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
          <line x1="12" y1="9" x2="12" y2="13"></line>
          <line x1="12" y1="17" x2="12.01" y2="17"></line>
        </svg>
        <span>Continuar mesmo assim (Atenção: exige auxílio manual)</span>
      </button>

      <div class="obstacle-footer-actions">
        <button type="button" class="link-obstacle-action" id="btn-open-photos">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
            <circle cx="12" cy="12" r="3"></circle>
          </svg>
          <span>Ver fotos do local</span>
        </button>

        <button type="button" class="link-obstacle-action" id="btn-notify-central">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"></path>
            <line x1="4" y1="22" x2="4" y2="15"></line>
          </svg>
          <span>Reforçar este alerta</span>
        </button>
      </div>

    </aside>
  </main>

  <!-- Modal de Fotos -->
  <div class="photo-modal-overlay" id="photo-evidence-modal" role="dialog" aria-modal="true" aria-labelledby="photo-modal-heading">
    <div class="photo-modal-card">
      <div class="photo-modal-header">
        <h2 class="photo-modal-title" id="photo-modal-heading">Fotos do Obstáculo na Via</h2>
        <button type="button" class="btn-close-photo-modal" id="btn-close-photos" aria-label="Fechar fotos">&times;</button>
      </div>
      <div class="photo-modal-body">
        <img src="<?= htmlspecialchars($ocorrencia['foto_url'] ?? 'assets/images/semaforo-evidencia.jpg') ?>" alt="Foto da calçada bloqueada" class="photo-modal-img" onerror="this.src='assets/images/map-background.jpg'">
        <p class="photo-modal-caption">
          <strong>Registro fotográfico:</strong> <?= htmlspecialchars($ocorrencia['descricao'] ?? 'Obras de manutenção com desnível acentuado na via.') ?>
        </p>
      </div>
    </div>
  </div>

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

  <div class="toast-obstacle-msg" id="toast-obstacle-msg" role="status" aria-live="polite"></div>

  <?php if ($ocorrencia): ?>
  <script id="php-ocorrencia-data" type="application/json">
    <?= json_encode(['ocorrencia' => $ocorrencia, 'user' => $userData], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>
  </script>
  <?php endif; ?>

    <script src="js/leaflet.js"></script>
  <script src="js/db.js"></script>
  <script src="js/db-queries.js"></script>
  <script src="js/auth.js"></script>
  <script src="js/alerta-obstaculo.js"></script>
</body>
</html>
