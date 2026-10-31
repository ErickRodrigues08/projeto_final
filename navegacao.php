<?php
/**
 * Passe Livre Suzano — Navegação em Tempo Real
 * Proteção server-side: usuário autenticado.
 */
require_once __DIR__ . '/includes/auth_guard.php';
require_once __DIR__ . '/includes/acessibilidade.php';
$authUser = auth_require();
$userData = auth_user_safe($authUser);

/*
 * Rota selecionada em configuracao-rota.php. O id chega pela URL, mas os dados
 * que o JavaScript usa vêm do banco — nunca de localStorage, nunca de uma
 * coordenada fixa e nunca de uma rota de outro usuário.
 */
$db = get_db();
$rotaSolicitada = (int) ($_GET['rota_id'] ?? 0);
$rotaRow = null;
if ($rotaSolicitada > 0) {
    $stmtRota = $db->prepare("
        SELECT id, user_id, origin_text, destination_text, origin_lat, origin_lng,
               dest_lat, dest_lng, route_type, distance_km, duration_min, status
        FROM rotas
        WHERE id = ?
        LIMIT 1
    ");
    $stmtRota->execute([$rotaSolicitada]);
    $rotaRow = $stmtRota->fetch() ?: null;
    if ($rotaRow && (int) ($rotaRow['user_id'] ?? 0) !== (int) $authUser['id']) {
        $rotaRow = null;
    }
}

$pcdRaw = is_array($authUser['pcd_categories'] ?? null)
    ? $authUser['pcd_categories']
    : (json_decode((string) ($authUser['pcd_categories'] ?? ''), true) ?: []);
$perfilPadrao = array_intersect(pl_pcd_keys($pcdRaw), ['motora', 'mobilidade'])
    ? 'wheelchair'
    : 'foot-walking';

$navCoord = static function ($lat, $lng): ?array {
    if (!is_numeric($lat) || !is_numeric($lng)) {
        return null;
    }
    $latF = (float) $lat;
    $lngF = (float) $lng;
    if ($latF < -35.0 || $latF > 6.0 || $lngF < -75.0 || $lngF > -30.0) {
        return null;
    }
    return [round($latF, 6), round($lngF, 6)];
};

$navDetalhes = [];
$navCoords   = [];
$navPassos   = [];
if ($rotaRow) {
    $stmtOpcao = $db->prepare("SELECT * FROM rota_opcoes WHERE rota_id = ? ORDER BY id ASC LIMIT 1");
    $stmtOpcao->execute([(int) $rotaRow['id']]);
    $opcao = $stmtOpcao->fetch() ?: null;
    if ($opcao) {
        $det = json_decode((string) ($opcao['detalhes'] ?? ''), true);
        $navDetalhes = is_array($det) ? $det : [];
    }

    if (is_array($navDetalhes['geometria'] ?? null)) {
        foreach ($navDetalhes['geometria'] as $par) {
            if (!is_array($par) || count($par) < 2) {
                continue;
            }
            $ponto = $navCoord($par[0], $par[1]);
            if ($ponto !== null) {
                $navCoords[] = $ponto;
            }
            if (count($navCoords) >= 20000) {
                break;
            }
        }
    }
    if (count($navCoords) < 2) {
        $navCoords = [];
    }

    $stmtPassos = $db->prepare("
        SELECT ordem, instrucao, icone, distancia_m
        FROM rota_passos
        WHERE rota_id = ?
        ORDER BY ordem ASC
        LIMIT 200
    ");
    $stmtPassos->execute([(int) $rotaRow['id']]);
    foreach ($stmtPassos->fetchAll() as $passo) {
        $instrucao = trim((string) ($passo['instrucao'] ?? ''));
        if ($instrucao === '') {
            continue;
        }
        $navPassos[] = [
            'ordem'       => (int) ($passo['ordem'] ?? 0),
            'instrucao'   => mb_substr($instrucao, 0, 300),
            'icone'       => (string) ($passo['icone'] ?? ''),
            'distancia_m' => max(0, (int) ($passo['distancia_m'] ?? 0)),
        ];
    }
}

$navPerfil = $perfilPadrao;
$navPerfilOrigem = $perfilPadrao === 'wheelchair' ? 'perfil_pcd' : 'padrao';
if ($rotaRow) {
    $perfilDet = (string) ($navDetalhes['profile'] ?? '');
    if (in_array($perfilDet, ['foot-walking', 'wheelchair'], true)) {
        $navPerfil = $perfilDet;
        $navPerfilOrigem = 'rota_salva';
    } elseif (($rotaRow['route_type'] ?? '') === 'cadeira_de_rodas') {
        $navPerfil = 'wheelchair';
        $navPerfilOrigem = 'rota_salva';
    } elseif (($rotaRow['route_type'] ?? '') === 'a_pe') {
        $navPerfil = 'foot-walking';
        $navPerfilOrigem = 'rota_salva';
    }
}

$navProvedor = (string) ($navDetalhes['provider'] ?? '');
if (!in_array($navProvedor, ['ors', 'osrm'], true)) {
    $navProvedor = '';
}
$navProvedorRotulo = $navProvedor === 'ors'
    ? 'OpenRouteService'
    : ($navProvedor === 'osrm' ? 'OSRM (OpenStreetMap)' : '');

$navDistanceM = null;
if (is_numeric($navDetalhes['distance_m'] ?? null)) {
    $navDistanceM = max(0, (int) round((float) $navDetalhes['distance_m']));
} elseif ($rotaRow && is_numeric($rotaRow['distance_km'] ?? null)) {
    $navDistanceM = max(0, (int) round((float) $rotaRow['distance_km'] * 1000));
}

$navDurationS = null;
if (is_numeric($navDetalhes['duration_s'] ?? null)) {
    $navDurationS = max(0, (int) round((float) $navDetalhes['duration_s']));
} elseif ($rotaRow && is_numeric($rotaRow['duration_min'] ?? null)) {
    $navDurationS = max(0, (int) $rotaRow['duration_min'] * 60);
}
$navDurationMin = $navDurationS !== null
    ? ($navDurationS > 0 ? max(1, (int) ceil($navDurationS / 60)) : 0)
    : null;

$navOrigem = $rotaRow ? $navCoord($rotaRow['origin_lat'] ?? null, $rotaRow['origin_lng'] ?? null) : null;
$navDestino = $rotaRow ? $navCoord($rotaRow['dest_lat'] ?? null, $rotaRow['dest_lng'] ?? null) : null;
if ($navOrigem === null && $navCoords) {
    $navOrigem = $navCoords[0];
}
if ($navDestino === null && $navCoords) {
    $navDestino = $navCoords[count($navCoords) - 1];
}

$navRouteContext = [
    'versao'       => 1,
    'rota_id'      => $rotaRow ? (int) $rotaRow['id'] : 0,
    'rota_solicitada' => $rotaSolicitada,
    'rota_encontrada' => $rotaRow !== null,
    'origem' => [
        'texto' => $rotaRow ? (string) ($rotaRow['origin_text'] ?? '') : '',
        'lat'   => $navOrigem ? $navOrigem[0] : null,
        'lng'   => $navOrigem ? $navOrigem[1] : null,
    ],
    'destino' => [
        'texto' => $rotaRow ? (string) ($rotaRow['destination_text'] ?? '') : '',
        'lat'   => $navDestino ? $navDestino[0] : null,
        'lng'   => $navDestino ? $navDestino[1] : null,
    ],
    'perfil'          => $navPerfil,
    'perfil_origem'   => $navPerfilOrigem,
    'perfil_padrao'   => $perfilPadrao,
    'perfil_rotulo'   => $navPerfil === 'wheelchair' ? 'cadeira de rodas' : 'a pé',
    'provedor'        => $navProvedor,
    'provedor_rotulo' => $navProvedorRotulo,
    'distancia_m'     => $navDistanceM,
    'duracao_s'       => $navDurationS,
    'duracao_min'     => $navDurationMin,
    'coords'          => $navCoords,
    'passos'          => $navPassos,
    'acessibilidade_verificada' => false,
    'acessibilidade_nota' => 'O trajeto vem do serviço de rotas; as condições reais de calçada, rampas e travessias não foram verificadas em campo.',
    'api' => [
        'rota'  => 'api/rota.php',
        'rotas' => 'api/rotas.php',
    ],
    'configuracao_url' => 'configuracao-rota.php',
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Navegação em Tempo Real — Passe Livre Suzano</title>
  <meta name="description" content="Navegação passo a passo em tempo real para o endereço pesquisado em qualquer município de São Paulo, com trajeto calculado a partir do seu posicionamento.">

  <!-- Fontes Oficiais: Domine e Plus Jakarta Sans -->
  <link rel="stylesheet" href="assets/fonts/fonts.css">

  <!-- Estilos Leaflet e Aplicação -->
  <link rel="stylesheet" href="css/leaflet.css">
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="<?= pl_asset_url('css/navegacao.css') ?>">
  <link rel="icon" type="image/x-icon" href="favicon.ico">
  <link rel="icon" type="image/png" sizes="32x32" href="assets/icons/favicon-32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="assets/icons/favicon-16.png">
  <link rel="apple-touch-icon" href="assets/icons/apple-touch-icon.png">
</head>
<body class="navigation-page-body">
  <a href="#main-content" class="skip-link">Pular para o conteúdo principal</a>

  <!-- =========================================================================
       1. CABEÇALHO DO SITE (SITE HEADER COMPACTO)
       ========================================================================= -->
  <header class="site-header" role="banner">
    <div class="header-left">
      <a href="index.php" class="brand-logo" title="Voltar para a Página Inicial">
        <img src="assets/icons/logo.png" alt="Passe Livre Suzano" width="160" height="32" onerror="this.src='assets/icons/logo.svg'">
      </a>
    </div>

    <!-- Navegação Central com Links para Outras Telas -->
    <nav class="header-nav" aria-label="Navegação Principal">
      <a href="explorar.php" class="nav-link nav-link-pill" title="Explorar o mapa e os locais da região pesquisada">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <circle cx="12" cy="12" r="10"></circle>
          <line x1="2" y1="12" x2="22" y2="12"></line>
          <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path>
        </svg>
        <span>Explorar Mundo</span>
      </a>

      <a href="configuracao-rota.php" class="nav-link active" aria-current="page" title="Configuração e detalhes da rota">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <circle cx="6" cy="19" r="3"></circle>
          <path d="M9 19h8.5a3.5 3.5 0 0 0 0-7h-11a3.5 3.5 0 0 1 0-7H15"></path>
          <circle cx="18" cy="5" r="3"></circle>
        </svg>
        <span>Rotas</span>
      </a>

      <a href="lugares-encontrados.php" class="nav-link" title="Pontos de apoio e acolhimento perto do endereço pesquisado">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
          <polyline points="9 22 9 12 15 12 15 22"></polyline>
        </svg>
        <span>Pontos de Apoio</span>
      </a>

      <a href="direitos.php" class="nav-link" title="Direitos, legislações e normas de acessibilidade">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
        </svg>
        <span>Direitos</span>
      </a>
    </nav>

    <!-- Lado Direito: Notificações & Perfil do Usuário -->
    <div class="header-right">
      <a href="notificacoes.php" class="btn-icon-notify" id="btn-notifications" aria-label="Notificações e Avisos de Acessibilidade" title="Avisos do Sistema">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
          <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
        </svg>
        <span class="notify-badge-dot" aria-hidden="true"></span>
      </a>

      <a href="editar-perfil.php" class="user-profile" id="user-profile-badge" aria-label="Perfil do usuário João Silva - Editar Perfil">
        <img src="assets/images/avatar.jpg" alt="Foto de perfil de João Silva" class="user-avatar-img" id="header-user-avatar">
        <div class="user-info">
          <span class="user-name" id="header-user-name">João Silva</span>
          <span class="user-role" id="header-user-role">Cidadão Conectado</span>
        </div>
      </a>
    </div>
  </header>

  <!-- =========================================================================
       2. BARRA DE NAVEGAÇÃO EM TEMPO REAL (SUBHEADER CONFORME PROTÓTIPO)
       ========================================================================= -->
  <section class="navigation-bar-header" aria-label="Status da Navegação em Tempo Real">
    <div class="nav-title-group">
      <div class="nav-arrow-icon-wrap" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="currentColor">
          <polygon points="3 11 22 2 13 21 11 13 3 11"/>
        </svg>
      </div>
      <h1 class="nav-screen-title">Navegação em Tempo Real</h1>
      <span class="badge-gps-active is-off" id="gps-status-badge" title="Estado real do GPS do dispositivo">
        <span class="gps-dot-pulse" aria-hidden="true"></span>
        <span id="gps-status-text">GPS aguardando sinal</span>
      </span>
    </div>

    <div class="security-lgpd-badge" title="Auditoria cidadã e proteção de dados garantida pela LGPD">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
      </svg>
      <span>Selo LGPD Ativo: Seus trajetos e dados de mobilidade são 100% anonimizados.</span>
    </div>
  </section>

  <!-- =========================================================================
       3. PALCO PRINCIPAL (MAPA LEAFLET DE TELA CHEIA INTERATIVO E ARRASTÁVEL)
       ========================================================================= -->
  <main class="navigation-stage-container" id="main-content" role="main">
    
    <!-- Elemento Canvas do Mapa Interativo (região da rota em cálculo) -->
    <div id="nav-map" aria-label="Mapa da região pesquisada com a rota ao vivo"></div>

    <!-- =======================================================================
         4. CAIXA DE NOTIFICAÇÃO DE MANOBRA (NOTIFICAÇÃO GOOGLE MAPS STYLE)
         ======================================================================= -->
    <aside class="turn-notification-box" role="region" aria-live="assertive" aria-label="Próxima instrução de manobra">
      <!-- Ícone Laranja de Manobra com Seta para Virar à Direita -->
      <div class="turn-icon-box" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="none" stroke="#ffffff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
          <path d="M7 17V9a4 4 0 0 1 4-4h6"></path>
          <polyline points="13 1 17 5 13 9"></polyline>
        </svg>
      </div>

      <!-- Textos da Manobra e Acessibilidade -->
      <div class="turn-content-column">
        <div class="turn-distance-row">
          <span class="turn-distance-text" id="turn-dist-val">Aguarde</span>
          <span class="turn-step-subtext">&bull; Próximo movimento</span>
        </div>
        <h2 class="turn-instruction-title" id="turn-instruction-main">Calculando o trajeto</h2>
        <div class="turn-badges-row">
          <span class="badge-turn-accessible">
            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
            <span>Acessibilidade não verificada</span>
          </span>
          <span class="badge-turn-neutral">
            <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="3" width="18" height="18" rx="2"/></svg>
            <span>Sem dados de calçada neste trajeto</span>
          </span>
        </div>
      </div>

      <!-- Botão de Alto-Falante para Repetir Instrução de Voz -->
      <button type="button" class="btn-turn-audio" id="btn-speak-instruction" title="Ouvir instrução de voz" aria-label="Ouvir instrução de manobra por áudio">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
          <polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"></polygon>
          <path d="M19.07 4.93a10 10 0 0 1 0 14.14M15.54 8.46a5 5 0 0 1 0 7.07"></path>
        </svg>
      </button>
    </aside>

    <!-- =======================================================================
         5. CONTROLES DO MAPA (SUPERIOR DIREITO)
         ======================================================================= -->
    <aside class="nav-map-controls" aria-label="Controles do mapa">
      <button type="button" class="btn-nav-control" id="btn-zoom-in" title="Aproximar mapa" aria-label="Aproximar">+</button>
      <button type="button" class="btn-nav-control" id="btn-zoom-out" title="Afastar mapa" aria-label="Afastar">&minus;</button>
      <div class="control-sep-line" aria-hidden="true"></div>
      <button type="button" class="btn-nav-control active-amber" id="btn-center-user" title="Centralizar na minha localização" aria-label="Centralizar na minha posição">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="12" cy="12" r="7"></circle>
          <line x1="12" y1="1" x2="12" y2="5"></line>
          <line x1="12" y1="19" x2="12" y2="23"></line>
          <line x1="1" y1="12" x2="5" y2="12"></line>
          <line x1="19" y1="12" x2="23" y2="12"></line>
        </svg>
      </button>
      <button type="button" class="btn-nav-control" id="btn-layers-toggle" title="Camadas de acessibilidade e satélite" aria-label="Camadas de acessibilidade">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
          <polygon points="12 2 2 7 12 12 22 7 12 2"></polygon>
          <polyline points="2 17 12 22 22 17"></polyline>
          <polyline points="2 12 12 17 22 12"></polyline>
        </svg>
      </button>
    </aside>

    <!-- =======================================================================
         6. CARD INFERIOR FLUTUANTE DE STATUS DE NAVEGAÇÃO
         ======================================================================= -->
    <aside class="navigation-bottom-card" id="navigation-bottom-card" aria-label="Painel de status da navegação">
      <div class="navigation-card-summary">
        <div class="card-top-status-row">
        <div class="route-active-tag-wrap">
          <span class="pill-active-route" id="route-active-pill">Sem rota carregada</span>
          <span class="route-dest-text" id="route-dest-text">Destino: não informado</span>
        </div>
        </div>

        <!-- Linha Principal das Métricas -->
        <div class="card-metrics-main-row">
          <span class="metric-big-time" id="metric-remaining-time">--</span>
          <span class="metric-label-small">restantes</span>
          <span class="metric-dot-sep">&bull;</span>
          <span class="metric-big-dist" id="metric-remaining-dist">--</span>
          <span class="metric-label-small">restantes</span>
          <span class="metric-dot-sep">&bull;</span>
          <div class="metric-eta-wrap">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <circle cx="12" cy="12" r="10"></circle>
              <polyline points="12 6 12 12 16 14"></polyline>
            </svg>
            <span id="metric-eta-time">Sem rota calculada</span>
          </div>
        </div>

        <div class="navigation-card-bottom-row">
        <button type="button" class="navigation-card-toggle" id="navigation-card-toggle" aria-expanded="false" aria-controls="navigation-card-details">
          <span>Ver detalhes e apoio</span>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <polyline points="6 9 12 15 18 9"></polyline>
          </svg>
        </button>
        <button type="button" class="btn-recalculate-route navigation-card-icon-action" id="btn-recalculate" title="Recalcular trajeto sem barreiras" aria-label="Recalcular rota">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <polyline points="23 4 23 10 17 10"></polyline>
            <polyline points="1 20 7 14"></polyline>
            <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path>
          </svg>
          <span>Recalcular Rota</span>
        </button>
        <button type="button" class="btn-end-navigation navigation-card-icon-action" id="btn-end-nav" title="Encerrar navegação e voltar para a configuração de rotas" aria-label="Encerrar navegação">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <line x1="18" y1="6" x2="6" y2="18"></line>
            <line x1="6" y1="18" x2="18" y2="6"></line>
          </svg>
          <span>Encerrar Navegação</span>
        </button>
        </div>
      </div>

      <div class="navigation-card-details" id="navigation-card-details" hidden>
        <div class="nav-actions-group">
          <!-- Botão Avaliar Local -->
          <a href="avaliar-local.php" class="btn-rate-location" id="btn-rate-location" title="Avaliar a acessibilidade deste local no mapa da região">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>
            </svg>
            <span>Avaliar Local</span>
          </a>

          <!-- Botão Recalcular Rota -->
          <button type="button" class="btn-recalculate-route" id="btn-recalculate-original" title="Recalcular trajeto sem barreiras" hidden aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <polyline points="23 4 23 10 17 10"></polyline>
              <polyline points="1 20 1 14 7 14"></polyline>
              <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path>
            </svg>
            <span>Recalcular Rota</span>
          </button>

          <!-- Botão Encerrar Navegação com X Vermelho -->
          <button type="button" class="btn-end-navigation" id="btn-end-nav-original" title="Encerrar navegação e voltar para a configuração de rotas" hidden aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <line x1="18" y1="6" x2="6" y2="18"></line>
              <line x1="6" y1="6" x2="18" y2="18"></line>
            </svg>
            <span>Encerrar Navegação</span>
          </button>
        </div>

      <div class="card-inner-divider" aria-hidden="true"></div>

      <!-- Linha Inferior: Badges de Condição e Link de Apoio Cidadão -->
      <div class="card-bottom-badges-row">
        <div class="badges-condition-left">
          <span class="badge-condition-pill badge-condition-level">
            <span>&bull;</span>
            <span>Condições da calçada não auditadas</span>
          </span>

          <span class="badge-condition-pill badge-condition-slope">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
              <line x1="12" y1="9" x2="12" y2="13"></line>
              <line x1="12" y1="17" x2="12.01" y2="17"></line>
            </svg>
            <span>Relatos de barreiras: consulte o mapa</span>
          </span>

          <button type="button" class="badge-voice-status" id="btn-toggle-voice" aria-pressed="true" title="Alternar narração de voz">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"></polygon>
              <path d="M19.07 4.93a10 10 0 0 1 0 14.14M15.54 8.46a5 5 0 0 1 0 7.07"></path>
            </svg>
            <span id="voice-status-text">Instruções por voz ativas</span>
          </button>
        </div>

        <a href="ajuda.php#suporte" class="link-citizen-support" title="Contatar a central de apoio ao cidadão PcD">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M3 18v-6a9 9 0 0 1 18 0v6"></path>
            <path d="M21 19a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3zM3 19a2 2 0 0 0 2 2h1a2 2 0 0 0 2-2v-3a2 2 0 0 0-2-2H3z"></path>
          </svg>
          <span>Solicitar Apoio ao Cidadão</span>
        </a>
      </div>
      </div>

    </aside>

  </main>

  <!-- =========================================================================
       7. RODAPÉ DA PÁGINA (SITE FOOTER)
       ========================================================================= -->
  <footer class="site-footer" role="contentinfo">
    <div class="footer-credits">
      &copy; 2026 Passe Livre. Todos os direitos reservados. Projeto desenvolvido por Samuel, Rodrigo, Erick, Gabrielly e Ana Júlia — SENAI, Suzano.
    </div>
    <div class="footer-links">
      <a href="ajuda.php" class="footer-link-item">Termos de Uso</a>
      <span>&bull;</span>
      <a href="ajuda.php" class="footer-link-item">Privacidade (LGPD)</a>
      <span>&bull;</span>
      <a href="ajuda.php" class="footer-link-item">Acessibilidade</a>
      <span>&bull;</span>
      <a href="ajuda.php" class="footer-link-item">Fale Conosco</a>
    </div>
  </footer>

  <!-- Modal de confirmação: encerrar navegação -->
  <div id="modal-end-nav" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.55); z-index:9999; align-items:center; justify-content:center;" role="dialog" aria-modal="true" aria-labelledby="modal-end-nav-title">
    <div style="background:#fff; border-radius:16px; padding:28px 32px; max-width:380px; width:90%; box-shadow:0 20px 60px rgba(0,0,0,.25); font-family:'Plus Jakarta Sans',system-ui,sans-serif;">
      <h3 id="modal-end-nav-title" style="font-size:16px; font-weight:700; color:#1c1917; margin:0 0 10px;">Encerrar navegação?</h3>
      <p style="font-size:13px; color:#78716c; margin:0 0 22px; line-height:1.5;">Você chegou ao seu destino ou deseja cancelar o trajeto atual? A rota ativa será encerrada.</p>
      <div style="display:flex; gap:10px; justify-content:flex-end;">
        <button id="btn-end-nav-cancel" type="button" style="padding:9px 20px; border-radius:9999px; border:1px solid #e7ded2; background:#fff; font-size:13px; font-weight:600; cursor:pointer; color:#57534e;">Continuar</button>
        <button id="btn-end-nav-confirm" type="button" style="padding:9px 20px; border-radius:9999px; border:none; background:#dc2626; font-size:13px; font-weight:700; cursor:pointer; color:#fff;">Encerrar</button>
      </div>
    </div>
  </div>

  <!-- Toast Flutuante de Alerta e Feedback -->
  <div class="toast-nav-msg" id="toast-nav-msg" role="status" aria-live="polite"></div>

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

  <!-- Rota selecionada (geometria, passos, perfil e métricas) vinda do banco -->
  <script id="nav-route-context" type="application/json">
    <?= json_encode($navRouteContext, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?>
  </script>

  <!-- Scripts: Leaflet e Lógica de Navegação em Tempo Real -->
  <script src="<?= pl_asset_url('js/leaflet.js') ?>"></script>
  <script src="<?= pl_asset_url('js/db.js') ?>"></script>
  <script src="<?= pl_asset_url('js/db-queries.js') ?>"></script>
  <script src="<?= pl_asset_url('js/auth.js') ?>"></script>
  <script src="<?= pl_asset_url('js/navegacao.js') ?>"></script>
</body>
</html>
