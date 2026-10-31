<?php
/**
 * Passe Livre Suzano — Mapa e Acessibilidade (Gestor)
 * Proteção server-side: apenas admins/gestores.
 * Conexão direta com banco MySQL via PDO.
 */
require_once __DIR__ . '/includes/auth_guard.php';
$gestorUserRaw = auth_require_admin();
$gestorUser    = auth_user_safe($gestorUserRaw);
$activePage    = 'mapa-gestor';

// Indicadores reais do MySQL
require_once __DIR__ . '/api/_db_restore.php';
$erroBanco   = null;
$countFull   = 0;
$countPart   = 0;
$countCrit   = 0;
try {
    $db = get_db();
    $countFull = (int)$db->query("SELECT COUNT(*) FROM locais WHERE is_archived = 0 AND access_status IN ('full','verified')")->fetchColumn();
    $countPart = (int)$db->query("SELECT COUNT(*) FROM locais WHERE is_archived = 0 AND access_status = 'partial'")->fetchColumn();
    $countCrit = (int)$db->query("SELECT COUNT(*) FROM ocorrencias WHERE status = 'ativa'")->fetchColumn();
} catch (\Throwable $e) {
    error_log('[mapa-gestor] consulta de contadores falhou: ' . $e->getMessage());
    $erroBanco = 'Não foi possível consultar o banco de dados agora. O mapa continua disponível, mas sem pontos até a conexão voltar.';
}

// Região pedida na URL. Sem um centro válido o painel abre na base histórica de
// Suzano e o JavaScript ajusta a vista aos pontos realmente cadastrados.
$centroSig   = pl_geo_centro_valido($_GET['lat'] ?? null, $_GET['lng'] ?? null);
$raioSig     = pl_geo_raio_km($_GET['raio_km'] ?? ($_GET['radius'] ?? null));
$municipioSig = pl_geo_municipio_normalizado($_GET['municipio'] ?? '');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Mapa e Acessibilidade — Painel do Gestor | Passe Livre Suzano</title>
  <meta name="description" content="Visualização geográfica e controle SIG dos locais acessíveis e ocorrências viárias em Suzano.">
  <link rel="stylesheet" href="assets/fonts/fonts.css">
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="css/gestor.css">
  <link rel="stylesheet" href="css/mapa-gestor.css">
  <link rel="stylesheet" href="css/leaflet.css">
  <link rel="icon" type="image/x-icon" href="favicon.ico">
  <link rel="icon" type="image/png" sizes="32x32" href="assets/icons/favicon-32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="assets/icons/favicon-16.png">
  <link rel="apple-touch-icon" href="assets/icons/apple-touch-icon.png">
</head>
<body>
  <a href="#main-content" class="skip-link">Pular para o conteúdo principal</a>

  <div class="gestor-layout-wrapper">
    <!-- Menu Lateral Oficial Unificado -->
    <?php require __DIR__ . '/includes/sidebar_gestor.php'; ?>

    <div class="gestor-content-area">
      <!-- Top Bar da Aplicação -->
      <header class="gestor-top-bar">
        <div class="gestor-title-crumb-wrap">
          <button class="btn-gestor-menu-toggle" id="btn-gestor-sidebar-toggle" aria-label="Abrir menu lateral" aria-expanded="false">&#9776;</button>
          <div>
            <div class="gestor-breadcrumb-text">
              <span>Passe Livre</span> / <span>Mapa e Acessibilidade</span>
            </div>
            <h1 class="gestor-main-heading">Dashboard Georreferenciado</h1>
          </div>
        </div>

        <div class="gestor-header-search-wrap">
          <img src="assets/icons/search.svg" alt="" aria-hidden="true">
          <input type="search" id="input-map-search" placeholder="Buscar no mapa por local, rua, bairro ou ID..." aria-label="Buscar no mapa por local, rua, bairro ou ID">
        </div>

        <div style="display: flex; align-items: center; gap: 18px;">
          <button class="btn-icon-notify" id="btn-notify-top" title="Notificações" onclick="window.location.href='notificacoes.php'">
            <img src="assets/icons/bell.svg" alt="">
          </button>

          <div class="gestor-header-profile-tag" style="cursor: pointer;" onclick="window.location.href='perfil-gestor.php'">
            <div class="gestor-avatar-badge-circle"><?= htmlspecialchars($gestorUser['initials']) ?></div>
            <div class="gestor-admin-tag-text">
              <?= pl_admin_header_profile($gestorUser) ?>
            </div>
          </div>
        </div>
      </header>

      <!-- Corpo Principal -->
      <main id="main-content" class="gestor-page-body" style="display: flex; flex-direction: column; gap: 18px;">

        <!-- Subheader: Filtros e Camadas -->
        <section class="map-page-subheader" aria-label="Controles do Mapa e Indicadores">
          <div class="map-header-top-row">
            <div class="map-title-group">
              <div class="map-title-inline">
                <h2 class="map-title-main">Mapa Interativo de Acessibilidade Urbana</h2>
                <span class="badge-sig-suzano">Base SIG Suzano 2026</span>
              </div>
              <p class="map-description-sub">Visualização georreferenciada de rotas acessíveis, ocorrências ativas e infraestrutura viária de Suzano.</p>
            </div>

            <div class="map-top-buttons-group">
              <button type="button" class="btn-gis-outline" id="btn-export-gis">
                <img src="assets/icons/download.svg" alt="" style="width: 14px; height: 14px;">
                <span>Exportar GeoJSON do que está em tela</span>
              </button>

              <button type="button" class="btn-report-point" id="btn-report-point" aria-pressed="false">
                <span style="font-size: 14px; line-height: 1;">+</span>
                <span>Fixar ponto no mapa e reportar</span>
              </button>
            </div>
          </div>

          <!-- Linha de Status Viário (Indicadores Reais do MySQL) -->
          <div class="map-status-row" role="group" aria-label="Filtrar pontos por condição de acessibilidade">
            <span>STATUS VIÁRIO:</span>

            <button type="button" class="pill-status-item pill-status-green" data-status="verde" aria-pressed="false">
              <span class="dot-status dot-green" aria-hidden="true"></span>
              <span>Totalmente Acessível (<?= $countFull ?>)</span>
            </button>

            <button type="button" class="pill-status-item pill-status-amber" data-status="amarelo" aria-pressed="false">
              <span class="dot-status dot-amber" aria-hidden="true"></span>
              <span>Acessibilidade Parcial (<?= $countPart ?>)</span>
            </button>

            <button type="button" class="pill-status-item pill-status-red" data-status="vermelho" aria-pressed="false">
              <span class="dot-status dot-red" aria-hidden="true"></span>
              <span>Barreiras Ativas (<?= $countCrit ?>)</span>
            </button>

            <button type="button" class="pill-status-item pill-status-clear" id="btn-status-clear">
              <span>Mostrar tudo</span>
            </button>
          </div>

          <!-- Linha de Camadas Reais do Mapa -->
          <div class="map-layers-row">
            <span id="layers-row-label">CAMADAS E RECURSOS:</span>

            <div class="map-layer-controls" role="group" aria-labelledby="layers-row-label">
              <button type="button" class="layer-tag-chip" data-layer="locais" aria-pressed="true">
                <span aria-hidden="true">📍</span>
                <span>Locais cadastrados</span>
              </button>

              <button type="button" class="layer-tag-chip" data-layer="ocorrencias" aria-pressed="true">
                <span aria-hidden="true">⚠️</span>
                <span>Ocorrências ativas</span>
              </button>

              <button type="button" class="layer-tag-chip layer-tag-filtro" data-recurso="piso_tatil" aria-pressed="false" title="Mostrar apenas locais com piso tátil registrado no cadastro">
                <span aria-hidden="true">🦯</span>
                <span>Piso tátil</span>
              </button>

              <button type="button" class="layer-tag-chip layer-tag-filtro" data-recurso="sinalizacao_sonora" aria-pressed="false" title="Mostrar apenas locais com sinalização sonora registrada no cadastro">
                <span aria-hidden="true">📢</span>
                <span>Semáforo sonoro</span>
              </button>

              <button type="button" class="layer-tag-chip layer-tag-filtro" data-recurso="rampa" aria-pressed="false" title="Mostrar apenas locais com rampa, guia rebaixada ou elevador registrados no cadastro">
                <span aria-hidden="true">♿</span>
                <span>Rampa ou elevador</span>
              </button>
            </div>

            <button type="button" class="btn-gis-outline btn-sm" id="btn-filtro-limpar">
              <span>Limpar filtros de recurso</span>
            </button>
          </div>

          <p class="map-provenance-note" id="map-provenance-note">
            Pontos vindos das tabelas <code>locais</code> e <code>ocorrencias</code>. A classificação de
            acessibilidade é a registrada no cadastro: <strong>não equivale a vistoria em campo</strong>
            feita por este painel.
          </p>
        </section>

        <!-- Viewport Principal do Mapa (Leaflet sobre base real) -->
        <section class="map-viewport-stage" aria-label="Mapa georreferenciado com os pontos do banco">
          <div class="gestor-leaflet-map" id="gestor-map"
               role="region" tabindex="0"
               aria-label="Mapa real dos locais cadastrados e ocorrências ativas. Setas deslocam o mapa, mais e menos fazem zoom."
               data-lat="<?= $centroSig['ok'] ? htmlspecialchars((string) $centroSig['lat']) : '' ?>"
               data-lng="<?= $centroSig['ok'] ? htmlspecialchars((string) $centroSig['lng']) : '' ?>"
               data-raio="<?= htmlspecialchars((string) $raioSig) ?>"
               data-municipio="<?= htmlspecialchars((string) ($municipioSig ?? '')) ?>"
               data-erro-banco="<?= htmlspecialchars((string) ($erroBanco ?? '')) ?>"></div>

          <div class="map-state-banner" id="map-state" role="status" aria-live="polite" hidden></div>

          <p class="map-capture-hint" id="map-capture-hint" role="status" aria-live="assertive" hidden>
            Capturando coordenada: clique no mapa para fixar o ponto do reporte. Escape cancela.
          </p>

          <div class="map-view-switcher" role="group" aria-label="Base de visualização do mapa">
            <button type="button" class="btn-view-mode active" data-mode="vetor" aria-pressed="true">
              <span aria-hidden="true">🗺️</span>
              <span>Rua</span>
            </button>
            <button type="button" class="btn-view-mode" data-mode="satelite" aria-pressed="false">
              <span aria-hidden="true">🛰️</span>
              <span>Satélite</span>
            </button>
            <button type="button" class="btn-view-mode" data-mode="densidade" aria-pressed="false">
              <span aria-hidden="true">⚠️</span>
              <span>Densidade de ocorrências</span>
            </button>
          </div>

          <div class="floating-legend-box" id="floating-legend" aria-label="Legenda dos pontos">
            <div class="legend-title">❖ Legenda</div>
            <div class="legend-item-row">
              <span class="legend-dot-indicator legend-dot-green" aria-hidden="true"></span>
              <span>Cadastro: acessibilidade total</span>
            </div>
            <div class="legend-item-row">
              <span class="legend-dot-indicator legend-dot-amber" aria-hidden="true"></span>
              <span>Cadastro: acessibilidade parcial</span>
            </div>
            <div class="legend-item-row">
              <span class="legend-dot-indicator legend-dot-grey" aria-hidden="true"></span>
              <span>Cadastro: não verificado</span>
            </div>
            <div class="legend-item-row">
              <span class="legend-dot-indicator legend-dot-red" aria-hidden="true"></span>
              <span>Ocorrência ativa: relato, sem vistoria</span>
            </div>
            <div class="legend-item-row">
              <span class="legend-dot-indicator legend-dot-blue" aria-hidden="true"></span>
              <span>Coordenada fixada para o reporte</span>
            </div>
            <p class="legend-counts" id="legend-counts">Carregando pontos do banco…</p>
          </div>

          <div class="floating-map-controls" aria-label="Controles do Mapa">
            <div class="controls-zoom-pill">
              <button type="button" class="btn-map-icon-pill" id="btn-zoom-in" aria-label="Aproximar o mapa" title="Aproximar (+)">
                <span aria-hidden="true">+</span>
              </button>
              <button type="button" class="btn-map-icon-pill" id="btn-zoom-out" aria-label="Afastar o mapa" title="Afastar (−)">
                <span aria-hidden="true">−</span>
              </button>
            </div>

            <button type="button" class="btn-map-icon-pill" id="btn-recenter" aria-label="Enquadrar o mapa nos pontos carregados" title="Enquadrar os pontos">
              <span aria-hidden="true">⤢</span>
            </button>
          </div>

          <article class="local-detail-card" id="local-detail-card" aria-label="Ponto selecionado no mapa" hidden>
            <div class="local-card-header">
              <span class="terminal-category-tag" id="local-card-category">—</span>
              <div class="local-card-header-actions">
                <button type="button" class="btn-photo-action-circle" id="btn-copy-local-link" title="Copiar link deste ponto">
                  <span aria-hidden="true">🔗</span>
                  <span class="sr-only">Copiar link deste ponto</span>
                </button>
                <button type="button" class="btn-photo-action-circle" id="btn-close-local-card" title="Fechar painel do ponto">
                  <span aria-hidden="true">×</span>
                  <span class="sr-only">Fechar painel do ponto</span>
                </button>
              </div>
            </div>

            <h3 class="terminal-card-title" id="local-card-title">Nenhum ponto selecionado</h3>
            <p class="terminal-card-address"><span id="local-card-address">—</span></p>
            <p class="local-card-provenance" id="local-card-provenance"></p>
            <ul class="local-card-recursos" id="local-card-recursos"></ul>

            <div class="local-card-actions">
              <button type="button" class="btn-view-terminal-full" id="btn-view-local-full">Ver ficha completa do local</button>
              <button type="button" class="btn-gis-outline" id="btn-print-local-report">Imprimir ficha</button>
            </div>

            <p class="local-card-feedback" id="local-card-feedback" role="status" aria-live="polite"></p>
          </article>
        </section>

      </main>

      <footer class="site-footer" role="contentinfo" style="margin-top: auto; padding: 16px 32px; background: #fdfaf6; border-top: 1px solid #ede4d8; font-size: 11px; color: #78716c;">
        <div>
          &copy; 2026 Passe Livre Suzano. Todos os direitos reservados. Projeto SENAI Suzano.
        </div>
      </footer>

    </div>
  </div>

  <div class="toast-notice" id="app-toast" role="status" aria-live="polite">
    <img src="assets/icons/check.svg" width="18" height="18" alt="">
    <span id="toast-message"></span>
  </div>

  <script src="js/leaflet.js"></script>
  <script src="js/auth.js"></script>
  <script src="js/mapa-gestor.js"></script>
</body>
</html>
