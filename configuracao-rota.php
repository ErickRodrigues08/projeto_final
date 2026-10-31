<?php
/**
 * Passe Livre Suzano — Configuração de Rota Acessível
 * Integração com Leaflet, rotas e banco MySQL.
 */
require_once __DIR__ . '/includes/auth_guard.php';
require_once __DIR__ . '/includes/acessibilidade.php';
require_once __DIR__ . '/includes/geo_centro.php';
$authUser = auth_require();
$userData = auth_user_safe($authUser);

$db = get_db();
$destId = (int)($_GET['dest_id'] ?? ($_GET['id'] ?? 0));
$destLocal = null;
if ($destId > 0) {
    $stmt = $db->prepare("SELECT * FROM locais WHERE id = ? AND is_archived = 0 LIMIT 1");
    $stmt->execute([$destId]);
    $destLocal = $stmt->fetch() ?: null;
}

/*
 * Sem linha no banco entra a coordenada passada pela busca ou pela camada
 * OpenStreetMap (?dest_lat/?dest_lng/?dest_nome). O destino nunca é "o primeiro
 * cadastro da tabela": isso empurraria um ponto de Suzano para uma rota de outra
 * cidade. Sem endereço real a página abre sem destino e avisa no painel.
 */
$destDeParametros = false;
if (!$destLocal) {
    $geo = pl_geo_centro_valido($_GET['dest_lat'] ?? null, $_GET['dest_lng'] ?? null);
    if ($geo['ok']) {
        $nomeParam  = trim((string)($_GET['dest_nome'] ?? ''));
        $muniParam  = trim((string)($_GET['dest_municipio'] ?? ''));
        $destLocal  = [
            'id'            => 0,
            'name'          => $nomeParam !== '' ? $nomeParam : 'Ponto escolhido no mapa',
            'address'       => $nomeParam !== '' ? $nomeParam : '',
            'municipio'     => $muniParam,
            'uf'            => 'SP',
            'lat'           => $geo['lat'],
            'lng'           => $geo['lng'],
            'access_status' => 'unverified',
            'access_text'   => 'Acessibilidade não verificada',
        ];
        $destDeParametros = true;
    } else {
        $destLocal = ['id' => 0, 'name' => '', 'address' => '', 'municipio' => '', 'uf' => 'SP',
                      'lat' => null, 'lng' => null, 'access_status' => 'unverified', 'access_text' => ''];
    }
}
$destExpl = ($destId > 0 && (int)($destLocal['id'] ?? 0) === $destId) || $destDeParametros ? 1 : 0;

$destLat = is_numeric($destLocal['lat'] ?? null) ? (float)$destLocal['lat'] : null;
$destLng = is_numeric($destLocal['lng'] ?? null) ? (float)$destLocal['lng'] : null;
$destHasCoords = $destLat !== null && $destLng !== null;

// Perfil de deslocamento: preferência explícita na URL ou deficiência motora do perfil.
$routeProfile = 'foot-walking';
$pcdRaw = is_array($authUser['pcd_categories'] ?? null)
    ? $authUser['pcd_categories']
    : (json_decode((string)($authUser['pcd_categories'] ?? ''), true) ?: []);
if (array_intersect(pl_pcd_keys($pcdRaw), ['motora', 'mobilidade'])) {
    $routeProfile = 'wheelchair';
}
$perfilParam = strtolower(trim((string)($_GET['perfil'] ?? '')));
if (in_array($perfilParam, ['wheelchair', 'foot-walking'], true)) {
    $routeProfile = $perfilParam;
}

/* Categorias e preferências já normalizadas pelo backend (includes/acessibilidade.php).
   O JavaScript consome esta lista em vez de manter uma segunda cópia das regras. */
$pcdKeys = pl_pcd_keys($pcdRaw);
$prefRaw = is_array($authUser['saved_prefs'] ?? null)
    ? $authUser['saved_prefs']
    : (json_decode((string) ($authUser['saved_prefs'] ?? ''), true) ?: []);
$prefKeys = pl_pref_keys($prefRaw);

$perfilOrigem = 'padrao';
if (array_intersect($pcdKeys, ['motora', 'mobilidade'])) {
    $perfilOrigem = 'perfil_pcd';
}
if (in_array($perfilParam, ['wheelchair', 'foot-walking'], true)) {
    $perfilOrigem = 'url';
}

/* Origem vinda de outra tela (seleção no mapa). GPS e a última posição da
   navegação vivem no sessionStorage do navegador e são resolvidos no JS; o PHP
   só transporta o que ele próprio validou. */
$origemParam = pl_geo_centro_valido(
    $_GET['origem_lat'] ?? ($_GET['origin_lat'] ?? null),
    $_GET['origem_lng'] ?? ($_GET['origin_lng'] ?? null)
);
$origemContexto = null;
if ($origemParam['ok']) {
    $origemContexto = [
        'lat'         => $origemParam['lat'],
        'lng'         => $origemParam['lng'],
        'nome'        => trim((string) ($_GET['origem_nome'] ?? ($_GET['origin_nome'] ?? ''))),
        'origem_dado' => 'parametros',
    ];
}

/*
 * Bloco único de contexto entregue ao JavaScript. Nada aqui é "conhecido" pelo
 * front-end por conta própria: destino resolvido no banco, perfil derivado do
 * cadastro e categorias normalizadas viajam explicitamente neste JSON.
 */
$routeContext = [
    'versao'         => 1,
    'perfil'         => $routeProfile,
    'perfil_origem'  => $perfilOrigem,
    'pcd_categorias' => $pcdKeys,
    'pcd_rotulos'    => array_map('pl_pcd_label', $pcdKeys),
    'preferencias'   => $prefKeys,
    'origem'         => $origemContexto,
    'destino'        => [
        'id'                     => (int) ($destLocal['id'] ?? 0),
        'nome'                   => (string) ($destLocal['name'] ?? ''),
        'endereco'               => (string) ($destLocal['address'] ?? ''),
        'municipio'              => (string) ($destLocal['municipio'] ?? ''),
        'uf'                     => (string) (($destLocal['uf'] ?? '') ?: 'SP'),
        'lat'                    => $destHasCoords ? $destLat : null,
        'lng'                    => $destHasCoords ? $destLng : null,
        'status_acessibilidade'  => (string) ($destLocal['access_status'] ?? 'unverified'),
        'texto_acessibilidade'   => (string) ($destLocal['access_text'] ?? ''),
        'origem_dado'            => $destId > 0 ? 'banco' : ($destDeParametros ? 'parametros' : 'ausente'),
    ],
    'api' => [
        'rota'          => 'api/rota.php',
        'osm_pontos'    => 'api/osm_pontos.php',
        'ocorrencias'   => 'api/ocorrencias.php',
        'salvar_rota'   => 'api/rotas.php',
        'geocode'       => 'api/geocode.php',
    ],
    'navegacao_url' => 'navegacao.php',
    'alternativas_max' => 3,
];

/* O mapa lê estes atributos em vez de adivinhar um destino: a coordenada vem do
   banco (ou da busca) e nunca de um ponto fixo de Suzano. */
$destAttrs = [
    'data-dest-id'   => (int) ($destLocal['id'] ?? 0),
    'data-dest-expl' => $destExpl,
    'data-dest-name' => (string) ($destLocal['name'] ?? ''),
    'data-dest-address' => (string) ($destLocal['address'] ?? ''),
    'data-dest-municipio' => (string) ($destLocal['municipio'] ?? ''),
    'data-dest-status' => (string) ($destLocal['access_status'] ?? 'unverified'),
    'data-dest-text' => (string) ($destLocal['access_text'] ?? ''),
    'data-dest-lat'  => $destHasCoords ? $destLat : '',
    'data-dest-lng'  => $destHasCoords ? $destLng : '',
    'data-profile'   => $routeProfile,
];
$destAttrHtml = '';
foreach ($destAttrs as $k => $v) {
    $destAttrHtml .= ' ' . $k . '="' . htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8') . '"';
}

/* Endereço do painel sempre acompanhado do município, para a lista, o mapa e o
   card escolhidos dizerem a mesma região. */
$destMunicipio = trim((string) ($destLocal['municipio'] ?? ''));
$destUf        = trim((string) ($destLocal['uf'] ?? 'SP')) ?: 'SP';
$destEnderecoExibido = trim((string) ($destLocal['address'] ?? ''));
if ($destMunicipio !== '') {
    $destEnderecoExibido .= ($destEnderecoExibido !== '' ? ' — ' : '') . $destMunicipio . ' - ' . $destUf;
}

$unreadCount = 0;
try {
    $stmtUnread = $db->prepare("SELECT COUNT(*) FROM notificacoes WHERE user_id = ? AND is_read = 0");
    $stmtUnread->execute([$authUser['id']]);
    $unreadCount = (int)$stmtUnread->fetchColumn();
} catch (\Throwable $e) {}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Configuração da Rota — Passe Livre Suzano</title>
  <meta name="description" content="Planejador de rotas a pé para o endereço pesquisado em qualquer município de São Paulo. A acessibilidade do trajeto só aparece como confirmada quando existe vistoria registrada.">
  <link rel="stylesheet" href="assets/fonts/fonts.css">
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="css/configuracao-rota.css">
  <link rel="stylesheet" href="css/leaflet.css">
  <link rel="icon" type="image/x-icon" href="favicon.ico">
  <link rel="icon" type="image/png" sizes="32x32" href="assets/icons/favicon-32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="assets/icons/favicon-16.png">
  <link rel="apple-touch-icon" href="assets/icons/apple-touch-icon.png">
</head>
<body class="route-page">
  <a href="#main-content" class="skip-link">Pular para o conteúdo principal</a>

  <!-- Header -->
  <header class="site-header" role="banner">
    <div class="header-left">
      <a href="index.php" class="brand-logo" title="Voltar para a Página Inicial">
        <img src="assets/icons/logo.png" alt="Passe Livre Suzano" width="160" height="38" onerror="this.src='assets/icons/logo.svg'">
      </a>
      <div class="header-search-wrap">
        <img src="assets/icons/search.svg" class="search-icon" alt="" aria-hidden="true">
        <input type="search" id="global-search-input" class="header-search-input" placeholder="Buscar vias, pontos ou linhas..." aria-label="Buscar vias">
      </div>
    </div>

    <nav class="header-nav" aria-label="Navegação Principal">
      <a href="explorar.php" class="nav-link">
        <img src="assets/icons/globe.svg" alt="" aria-hidden="true">
        <span>Explorar Mundo</span>
      </a>
      <a href="configuracao-rota.php" class="nav-link active" aria-current="page">
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
      <a href="central-notificacoes.php" class="btn-icon-notify" id="btn-notifications" aria-label="Notificações">
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
  <main class="route-stage-container" id="main-content" role="main">
    
    <div id="route-map" aria-label="Mapa interativo da rota acessível"<?= $destAttrHtml ?>></div>

    <div class="map-audit-badge" id="map-audit-badge" title="Percurso de pedestres calculado sobre dados abertos; a acessibilidade aparece como verificada apenas com vistoria registrada">
      <span class="audit-dot" aria-hidden="true"></span>
      <span>Percurso de pedestres • <?= $destMunicipio !== '' ? htmlspecialchars($destMunicipio . ' - ' . $destUf, ENT_QUOTES, 'UTF-8') : 'região pesquisada' ?></span>
    </div>

    <button type="button" class="btn-floating-toggle-panel" id="btn-toggle-route-panel" title="Alterar opções ou configurar rota">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round">
        <polyline points="15 18 9 12 15 6"></polyline>
      </svg>
      <span>Alterar opções de rota</span>
    </button>

    <button type="button" class="btn-floating-reopen-popup" id="btn-reopen-popup" title="Ver resumo da rota selecionada">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round">
        <circle cx="12" cy="12" r="10"></circle>
        <polygon points="16.24 7.76 14.12 14.12 7.76 16.24 9.88 9.88 16.24 7.76"></polygon>
      </svg>
      <span>Trajeto: <strong id="btn-reopen-popup-info">Aguardando cálculo</strong></span>
    </button>

    <!-- Overlay da Rota Escolhida -->
    <div class="route-chosen-overlay" id="route-chosen-overlay" role="dialog" aria-modal="true" aria-labelledby="chosen-route-title">
      <div class="route-chosen-card" id="route-chosen-card">
        <button type="button" class="btn-close-popup" id="btn-close-chosen-modal" title="Fechar visualização" aria-label="Fechar pop up">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round">
            <line x1="18" y1="6" x2="6" y2="18"></line>
            <line x1="6" y1="6" x2="18" y2="18"></line>
          </svg>
        </button>

        <div class="chosen-card-header">
          <div class="chosen-header-left">
            <div class="chosen-icon-box" aria-hidden="true">
              <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="6" cy="19" r="2.5"></circle>
                <path d="M8.5 19H15a4 4 0 0 0 4-4v-1a4 4 0 0 0-4-4H9a3 3 0 0 1-3-3V5"></path>
                <polyline points="3 8 6 5 9 8"></polyline>
              </svg>
            </div>
            <div class="chosen-title-group">
              <div class="chosen-title-row">
                <h2 class="chosen-main-title" id="chosen-route-title">Trajeto calculado</h2>
                <span class="chosen-badge-recommended" id="chosen-badge-status">
                  <span class="badge-dot" aria-hidden="true"></span>
                  <span id="chosen-badge-text">Roteamento</span>
                </span>
              </div>
              <p class="chosen-subtitle" id="chosen-route-subtitle">
                Origem: <span id="chosen-origin-text">Sua localização</span> | Destino: <span id="chosen-destination-text"><?= htmlspecialchars($destLocal['name']) ?></span>
              </p>
            </div>
          </div>

          <div class="chosen-header-right">
            <div class="chosen-guarantee-pill" id="chosen-guarantee-pill">
              <svg class="check-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <polyline points="20 6 9 17 4 12"></polyline>
              </svg>
              <span id="chosen-guarantee-text">Acessibilidade não verificada em campo</span>
            </div>
          </div>
        </div>

        <div class="chosen-stats-grid">
          <div class="chosen-stat-item">
            <div class="stat-icon-circle" aria-hidden="true">
              <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline>
              </svg>
            </div>
            <div class="stat-text-col">
              <span class="stat-label">Tempo Estimado</span>
              <strong class="stat-value" id="chosen-stat-time">—</strong>
            </div>
          </div>

          <div class="chosen-stat-item">
            <div class="stat-icon-circle" aria-hidden="true">
              <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path>
              </svg>
            </div>
            <div class="stat-text-col">
              <span class="stat-label">Distância Total</span>
              <strong class="stat-value" id="chosen-stat-dist">—</strong>
            </div>
          </div>

          <div class="chosen-stat-item">
            <div class="stat-icon-circle" aria-hidden="true">
              <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path><polyline points="9 12 11 14 15 10"></polyline>
              </svg>
            </div>
            <div class="stat-text-col">
              <span class="stat-label">Apoio Conectado</span>
              <strong class="stat-value" id="chosen-stat-support">Sem dados</strong>
            </div>
          </div>

          <div class="chosen-stat-item">
            <div class="stat-icon-circle" aria-hidden="true">
              <svg width="19" height="19" viewBox="0 0 24 24" fill="currentColor">
                <circle cx="14.5" cy="4" r="2.5"/>
                <path d="M14.2 8.5c-.8 0-1.5.5-1.8 1.2l-1.3 3.1c-.2.5-.7.8-1.2.8h-2c-.6 0-1 .4-1 1s.4 1 1 1h2.2c1.3 0 2.4-.8 2.8-2l.9-2.2 2.2 1.4c.5.3.8.8.8 1.4v3.8c0 .6.4 1 1 1s1-.4 1-1v-4c0-1.2-.6-2.3-1.6-2.9l-2.4-1.5.4-1.1c.1-.5.7-.8 1.2-.8h1.8c.6 0 1-.4 1-1s-.4-1-1-1h-2z"/>
              </svg>
            </div>
            <div class="stat-text-col">
              <span class="stat-label">Acessibilidade</span>
              <strong class="stat-value" id="chosen-stat-access">Não verificado</strong>
            </div>
          </div>
        </div>

        <div class="chosen-actions-row">
          <button type="button" class="btn-action-outline btn-report-obstacle" id="btn-report-obstacle" title="Reportar obstáculo">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
              <line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line>
            </svg>
            <span>Reportar obstáculo</span>
          </button>

          <button type="button" class="btn-action-outline btn-view-instructions" id="btn-view-instructions" title="Ver orientações passo a passo">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <line x1="8" y1="6" x2="21" y2="6"></line><line x1="8" y1="12" x2="21" y2="12"></line><line x1="8" y1="18" x2="21" y2="18"></line>
            </svg>
            <span>Instruções da Rota</span>
          </button>

          <button type="button" class="btn-action-primary btn-continue-nav" id="btn-continue-step-nav" title="Iniciar navegação acessível">
            <span>Iniciar Navegação</span>
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline>
            </svg>
          </button>
        </div>
      </div>
    </div>

    <!-- HUD de Navegação Ativa -->
    <div class="navigation-hud-banner" id="navigation-hud" role="region" aria-live="polite" aria-label="Orientação de Navegação">
      <div class="hud-icon-turn">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
          <line x1="12" y1="19" x2="12" y2="5"></line><polyline points="5 12 12 5 19 12"></polyline>
        </svg>
      </div>
      <div class="hud-text-wrap">
        <span class="hud-instruction-main" id="hud-instruction-text">Aguardando o cálculo do trajeto</span>
        <span class="hud-instruction-sub" id="hud-status-text">Condições de acessibilidade não verificadas neste percurso</span>
      </div>
      <button type="button" class="btn-hud-close" id="btn-stop-nav" aria-label="Encerrar navegação">Encerrar</button>
    </div>

    <!-- Controles do Mapa -->
    <aside class="map-controls-capsule" aria-label="Controles do mapa">
      <button type="button" class="btn-map-action" id="btn-zoom-in" title="Aproximar mapa" aria-label="Aproximar">+</button>
      <button type="button" class="btn-map-action" id="btn-zoom-out" title="Afastar mapa" aria-label="Afastar">&minus;</button>
      <div class="control-divider" aria-hidden="true"></div>
      <button type="button" class="btn-map-action" id="btn-recenter" title="Centralizar rota" aria-label="Centralizar rota">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="12" cy="12" r="7"></circle><line x1="12" y1="1" x2="12" y2="5"></line><line x1="12" y1="19" x2="12" y2="23"></line>
        </svg>
      </button>
      <button type="button" class="btn-map-action active-pcd" id="btn-toggle-pcd" title="Camadas de Acessibilidade" aria-label="Alternar camadas de acessibilidade">
        <svg viewBox="0 0 24 24" fill="currentColor">
          <circle cx="14.5" cy="4" r="2.5"/>
          <path d="M14.2 8.5c-.8 0-1.5.5-1.8 1.2l-1.3 3.1c-.2.5-.7.8-1.2.8h-2c-.6 0-1 .4-1 1s.4 1 1 1h2.2c1.3 0 2.4-.8 2.8-2l.9-2.2 2.2 1.4c.5.3.8.8.8 1.4v3.8c0 .6.4 1 1 1s1-.4 1-1v-4c0-1.2-.6-2.3-1.6-2.9l-2.4-1.5.4-1.1c.1-.5.7-.8 1.2-.8h1.8c.6 0 1-.4 1-1s-.4-1-1-1h-2z"/>
        </svg>
      </button>
    </aside>

    <!-- Legenda -->
    <aside class="map-legend-pill" aria-label="Legenda do trajeto">
      <div class="legend-item" id="legend-audited">
        <span class="legend-dot-orange" aria-hidden="true"></span>
        <span>Trajeto selecionado (calculado)</span>
      </div>
      <div class="legend-item" id="legend-risk">
        <span class="legend-line-dashed" aria-hidden="true"></span>
        <span>Atenção / Risco reportado</span>
      </div>
      <div class="legend-item" id="legend-tactile">
        <span class="legend-dot-brown" aria-hidden="true"></span>
        <span>Piso tátil / rampas (OSM)</span>
      </div>
    </aside>

    <!-- Painel de Configuração -->
    <aside class="route-card-panel" aria-label="Painel de Opções de Rota">
      <div class="card-header-row">
        <div class="card-title-group">
          <svg class="card-title-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <circle cx="6" cy="19" r="3"></circle><path d="M9 19h8.5a3.5 3.5 0 0 0 0-7h-11a3.5 3.5 0 0 1 0-7H15"></path><circle cx="18" cy="5" r="3"></circle>
          </svg>
          <h1 class="card-main-title">Configuração da Rota</h1>
        </div>
        <span class="card-badge-options" id="route-options-count">Aguardando cálculo</span>
      </div>

      <div class="route-locations-box">
        <div class="location-item-row" id="origin-box">
          <div class="location-icon-wrap" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle>
            </svg>
          </div>
          <div class="location-text-wrap">
            <span class="location-type-tag" id="origin-tag">DE (ORIGEM)</span>
            <strong class="location-title" id="origin-title">Sua localização</strong>
            <span class="location-address" id="origin-address">Confirme o ponto de partida no mapa</span>
          </div>
        </div>

        <div class="locations-divider" aria-hidden="true"></div>

        <div class="location-item-row" id="destination-box">
          <div class="location-icon-wrap" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path><line x1="3" y1="6" x2="21" y2="6"></line>
            </svg>
          </div>
          <div class="location-text-wrap">
            <span class="location-type-tag" id="destination-tag">PARA (DESTINO)</span>
            <strong class="location-title" id="destination-title"><?= htmlspecialchars($destLocal['name']) ?></strong>
            <span class="location-address" id="destination-address"><?= htmlspecialchars($destEnderecoExibido !== '' ? $destEnderecoExibido : 'Endereço não informado', ENT_QUOTES, 'UTF-8') ?></span>
          </div>
        </div>

        <button type="button" class="btn-swap-locations" id="btn-swap-locations" title="Inverter origem e destino" aria-label="Inverter origem e destino">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M7 16V4M7 4L3 8M7 4L11 8"></path><path d="M17 8V20M17 20L21 16M17 20L13 16"></path>
          </svg>
        </button>
      </div>

      <!-- Opção 1: Mais Acessível -->
      <article class="route-option-card" id="card-route-accessible" tabindex="0" role="button" aria-pressed="true" aria-label="Opção 1: percurso recomendado para pedestres">
        <div class="route-card-top-row">
          <span class="badge-recommended" id="selected-route-badge">AGUARDANDO CÁLCULO</span>
          <span class="badge-percentage-text" id="selected-route-audit">Acessibilidade não verificada</span>
        </div>

        <div class="route-title-row">
          <div class="route-title-left">
            <svg class="wheelchair-icon" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
              <circle cx="13.5" cy="3.5" r="1.5"/>
              <path d="M9.8 8.9 7 23h2.1l1.8-8 2.1 2v6h2v-7.5l-2.1-2 .6-3C14.8 12 16.8 13 19 13v-2c-1.9 0-3.5-1-4.3-2.4l-1-1.6c-.4-.6-1-1-1.7-1-.3 0-.5.1-.8.1L6 8.3V13h2V9.6l1.8-.7"/>
            </svg>
            <h2 class="route-heading" id="selected-route-heading">Trajeto calculado</h2>
          </div>
          <div class="route-checkmark-badge" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
              <polyline points="20 6 9 17 4 12"></polyline>
            </svg>
          </div>
        </div>

        <div class="route-metrics-row">
          <span class="metric-time" id="selected-route-time">A calcular</span>
          <span class="metric-sep">•</span>
          <span class="metric-dist" id="selected-route-dist">—</span>
          <span class="metric-sep">•</span>
          <span class="metric-tag-slope" id="selected-route-tag">Sem auditoria</span>
        </div>

        <div class="route-card-divider" aria-hidden="true"></div>

        <ul class="route-checklist" id="selected-route-checklist">
          <li class="route-checklist-item">
            <span>Aguardando o cálculo do trajeto pelo roteador</span>
          </li>
          <li class="route-checklist-item">
            <span>Distância e tempo aparecem depois do cálculo</span>
          </li>
          <li class="route-checklist-item">
            <span>Acessibilidade das calçadas: não verificada em campo</span>
          </li>
          <li class="route-checklist-item">
            <span>Você pode reportar obstáculos encontrados no percurso</span>
          </li>
        </ul>

        <div id="selected-route-actions" style="margin-top:14px;display:flex;justify-content:flex-end;"></div>
      </article>

      <!-- Opção 2: só aparece preenchida quando o roteador devolve uma segunda rota real -->
      <div class="alternative-route-card collapsed" id="card-route-alt-2" tabindex="0" role="button" aria-pressed="false" title="Selecionar esta alternativa" hidden>
        <div>
          <span class="alt-title">Opção 2</span>
          <span class="alt-metrics" style="display:block;margin-top:2px;">Sem alternativa calculada</span>
        </div>
        <span class="alt-action" style="color:#ea580c;font-size:11.5px;font-weight:700;">Escolher rota &rarr;</span>
      </div>

      <!-- Opção 3: idem — nunca uma linha de ônibus fictícia -->
      <div class="alternative-route-card collapsed" id="card-route-alt-3" tabindex="0" role="button" aria-pressed="false" title="Selecionar esta alternativa" hidden>
        <div>
          <span class="alt-title">Opção 3</span>
          <span class="alt-metrics" style="display:block;margin-top:2px;">Sem alternativa calculada</span>
        </div>
        <span class="alt-action" style="color:#ea580c;font-size:11.5px;font-weight:700;">Escolher rota &rarr;</span>
      </div>

      <div class="card-actions-wrap">
        <button type="button" class="btn-start-navigation" id="btn-start-navigation" aria-label="Iniciar navegação acessível assistida">
          <span>Iniciar navegação acessível</span>
          <svg class="arrow-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline>
          </svg>
        </button>

        <a href="detalhes-local.php?id=<?= (int)$destLocal['id'] ?>" class="btn-back-details" title="Voltar aos detalhes">
          &larr; Voltar aos detalhes
        </a>
      </div>
    </aside>

  </main>

  <footer class="site-footer" role="contentinfo">
    <p class="footer-copy">&copy; 2026 Passe Livre Suzano. Todos os direitos reservados. Projeto SENAI Suzano.</p>
    <div class="footer-links">
      <a href="termos-de-uso.php">Termos de Uso</a>
      <a href="privacidade.php">Privacidade (LGPD)</a>
      <a href="acessibilidade.php">Acessibilidade</a>
      <a href="fale-conosco.php">Fale Conosco</a>
    </div>
  </footer>

  <!-- Modal: Reportar Obstáculo -->
  <div class="modal-backdrop-custom" id="modal-report-obstacle" role="dialog" aria-modal="true" style="display:none;" hidden>
    <div class="modal-dialog-custom">
      <div class="modal-header-custom">
        <h3 class="modal-title-custom">Reportar Obstáculo no Trajeto</h3>
        <button type="button" class="btn-close-popup" id="btn-close-obstacle-modal">&times;</button>
      </div>
      <form id="form-report-obstacle">
        <div class="modal-body-custom">
          <div class="form-group-custom">
            <label for="obstacle-type">Tipo de Barreira</label>
            <select id="obstacle-type" class="form-select-custom" required>
              <option value="buraco">Calçada danificada / Buraco ou desnível</option>
              <option value="semaforo">Semáforo sonoro desligado ou com falha</option>
              <option value="rampa">Rampa de acesso bloqueada ou inexistente</option>
              <option value="piso-tatil">Piso podotátil interrompido ou quebrado</option>
              <option value="obra">Obra na via sem rota acessível alternativa</option>
            </select>
          </div>
          <div class="form-group-custom">
            <label for="obstacle-location">Local Aproximado</label>
            <input type="text" id="obstacle-location" class="form-input-custom" placeholder="Rua, número ou referência do trecho" required>
          </div>
          <div class="form-group-custom">
            <label for="obstacle-notes">Observações Adicionais</label>
            <textarea id="obstacle-notes" class="form-textarea-custom" rows="3"></textarea>
          </div>
        </div>
        <div class="modal-footer-custom">
          <button type="button" class="btn-action-outline" id="btn-cancel-obstacle">Cancelar</button>
          <button type="submit" class="btn-action-primary" style="background:#ea580c;">Enviar Alerta</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Modal: Instruções -->
  <div class="modal-backdrop-custom" id="modal-instructions-list" role="dialog" aria-modal="true" aria-hidden="true">
    <div class="modal-dialog-custom" style="max-width:580px;">
      <div class="modal-header-custom">
        <h3 class="modal-title-custom" id="instructions-modal-title">Instruções da Rota</h3>
        <button type="button" class="btn-close-popup" id="btn-close-instructions-modal" aria-label="Fechar instruções da rota">&times;</button>
      </div>
      <div class="modal-body-custom">
        <p class="instructions-route-summary" id="instructions-route-summary"></p>
        <p class="instructions-empty-state" id="instructions-empty-state" role="status" aria-live="polite" hidden></p>
        <ol class="instructions-step-list" id="route-instructions-list"></ol>
      </div>
      <div class="modal-footer-custom">
        <button type="button" class="btn-action-outline" id="btn-ok-instructions">Fechar</button>
        <button type="button" class="btn-action-primary" id="btn-modal-start-nav">Iniciar Navegação Agora &rarr;</button>
      </div>
    </div>
  </div>

  <div class="toast-feedback" id="toast-feedback" role="alert" aria-live="assertive"></div>

  <!-- Contexto real resolvido no servidor: destino do banco, perfil do cadastro
       e categorias normalizadas. O JSON_HEX_TAG impede qualquer fechamento de
       <script> a partir dos textos livres vindos da URL. -->
  <script id="route-context" type="application/json"><?= json_encode($routeContext, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
  <script src="<?= pl_asset_url('js/leaflet.js') ?>"></script>
  <script src="<?= pl_asset_url('js/db.js') ?>"></script>
  <script src="<?= pl_asset_url('js/db-queries.js') ?>"></script>
  <script src="<?= pl_asset_url('js/auth.js') ?>"></script>
  <script src="<?= pl_asset_url('js/configuracao-rota.js') ?>"></script>
</body>
</html>
