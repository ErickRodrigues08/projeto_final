<?php
/**
 * Passe Livre Suzano — Detalhes do Local
 * Integração direta com MySQL via PDO.
 * Renderiza dados reais e estado vazio elegante quando o local não existir.
 */
require_once __DIR__ . '/api/_db_restore.php';
require_once __DIR__ . '/includes/geo_centro.php';

/**
 * Foto de avaliação só é exibida quando o caminho bate com o formato gravado por
 * api/upload.php. Um valor qualquer do banco nunca vira HTML de imagem.
 */
function pl_foto_avaliacao_valida($url): bool
{
    return is_string($url)
        && preg_match('#^uploads/[0-9]{8}-[0-9a-f]{24}\.(?:jpg|jpeg|png|webp)$#', $url) === 1;
}

$local       = null;
$recursos    = [];
$avaliacoes  = [];
$ocorrencias = [];
$erroBanco   = null;

$id   = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$code = isset($_GET['code']) ? trim($_GET['code']) : '';
if (!$id && !empty($code) && is_numeric($code)) {
    $id = (int)$code;
}

try {
    $db = get_db();
    if ($id > 0) {
        $stmt = $db->prepare("SELECT * FROM locais WHERE id = ? AND is_archived = 0 LIMIT 1");
        $stmt->execute([$id]);
        $local = $stmt->fetch();
    } elseif (!empty($code)) {
        $stmt = $db->prepare("SELECT * FROM locais WHERE local_code = ? AND is_archived = 0 LIMIT 1");
        $stmt->execute([$code]);
        $local = $stmt->fetch();
    } else {
        // Sem ?id nem ?code não há o que abrir: cair no "primeiro local da
        // tabela" exibia um cadastro de Suzano para quem só acessou a página.
        $local = null;
    }

    if ($local) {
        $local['features'] = is_string($local['features']) ? json_decode($local['features'], true) : ($local['features'] ?: []);

        $stmtRec = $db->prepare("SELECT * FROM local_recursos WHERE local_id = ? ORDER BY id ASC");
        $stmtRec->execute([$local['id']]);
        $recursos = $stmtRec->fetchAll();

        $stmtAval = $db->prepare("
            SELECT a.*, u.name AS autor_atual, u.avatar AS user_avatar, u.role AS user_role,
                   u.pcd_categories AS user_pcd_categories
            FROM avaliacoes a 
            LEFT JOIN usuarios u ON u.id = a.user_id 
            WHERE a.local_id = ? 
            ORDER BY a.created_at DESC, a.id DESC 
            LIMIT 15
        ");
        $stmtAval->execute([$local['id']]);
        $avaliacoes = $stmtAval->fetchAll();
        foreach ($avaliacoes as &$av) {
            $av['photos'] = is_string($av['photos']) ? json_decode($av['photos'], true) : ($av['photos'] ?: []);
            $pcd = is_string($av['user_pcd_categories'] ?? '') ? json_decode($av['user_pcd_categories'], true) : ($av['user_pcd_categories'] ?? []);
            $av['condition_label'] = is_array($pcd) && !empty($pcd) ? implode(', ', array_map('strval', $pcd)) : '';
        }
        unset($av);

        $stmtOcorr = $db->prepare("SELECT * FROM ocorrencias WHERE local_id = ? AND status = 'ativa'");
        $stmtOcorr->execute([$local['id']]);
        $ocorrencias = $stmtOcorr->fetchAll();
    }
} catch (\Throwable $e) {
    // Uma falha de conexão não pode virar "local não encontrado": o visitante
    // precisa saber que a base está indisponível, e não que o cadastro sumiu.
    error_log('[detalhes-local] consulta ao MySQL falhou: ' . $e->getMessage());
    $erroBanco = 'Não foi possível consultar o banco de dados agora. Tente novamente em instantes.';
}

$currentUser = get_auth_user();

// Coordenadas reais do cadastro. Sem elas o mapa entra em estado "localização
// indisponível" em vez de centrar numa cidade genérica.
$geoLocal    = pl_geo_centro_valido($local['lat'] ?? null, $local['lng'] ?? null);
$temMapa     = (bool)($local && $geoLocal['ok']);
$mapaLat     = $geoLocal['lat'];
$mapaLng     = $geoLocal['lng'];

/* Linhas de transporte vêm do cadastro do próprio local. Sem esse registro o
   modal não pode inventar frota, frequência ou equipamentos de acessibilidade. */
$busLines = [];
if ($local) {
    foreach (preg_split('/[,;\n]+/', (string) ($local['bus_lines'] ?? '')) as $linha) {
        $linha = trim($linha);
        if ($linha !== '') {
            $busLines[] = $linha;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $local ? htmlspecialchars($local['name']) . ' — Detalhes do Local' : 'Local não encontrado' ?> | Passe Livre Suzano</title>
  <meta name="description" content="<?= $local ? htmlspecialchars(mb_substr($local['name'] . ' — ' . ($local['address'] ?? '') . '. ' . ($local['access_text'] ?? ''), 0, 150)) : 'Detalhes de acessibilidade de locais cadastrados no Passe Livre.' ?>">
  <link rel="stylesheet" href="assets/fonts/fonts.css">
  <link rel="stylesheet" href="css/leaflet.css">
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="css/detalhes-local.css">
  <link rel="icon" type="image/x-icon" href="favicon.ico">
  <link rel="icon" type="image/png" sizes="32x32" href="assets/icons/favicon-32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="assets/icons/favicon-16.png">
  <link rel="apple-touch-icon" href="assets/icons/apple-touch-icon.png">
</head>
<body class="place-details-page">
  <a href="#main-content" class="skip-link">Pular para o conteúdo principal</a>

  <!-- Header Global -->
  <header class="site-header" role="banner">
    <div class="header-left">
      <a href="index.php" class="brand-logo" title="Voltar para a Página Inicial">
        <img src="assets/icons/logo.png" alt="Passe Livre Suzano" width="160" height="38" onerror="this.src='assets/icons/logo.svg'">
      </a>

      <div class="header-search-wrap">
        <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <circle cx="11" cy="11" r="8"></circle>
          <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
        </svg>
        <input 
          type="search" 
          id="global-search-input" 
          class="header-search-input" 
          placeholder="Buscar vias, pontos ou linhas..." 
          aria-label="Buscar vias, pontos ou linhas em Suzano"
        >
      </div>
    </div>

    <nav class="header-nav" aria-label="Navegação Principal">
      <a href="explorar.php" class="nav-link active" title="Explorar o mapa e os locais da região pesquisada">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <circle cx="12" cy="12" r="10"></circle>
          <line x1="2" y1="12" x2="22" y2="12"></line>
          <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path>
        </svg>
        <span>Explorar Mundo</span>
      </a>

      <a href="configuracao-rota.php" class="nav-link" title="Consultar e configurar rotas acessíveis">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline>
        </svg>
        <span>Rotas</span>
      </a>

      <a href="lugares-encontrados.php" class="nav-link" title="Pontos de apoio e acolhimento perto do endereço pesquisado">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <circle cx="12" cy="12" r="10"></circle>
          <path d="M12 16v-4"></path>
          <path d="M12 8h.01"></path>
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

    <div class="header-right">
      <a href="central-notificacoes.php" class="btn-icon-notify" id="btn-notifications" aria-label="Notificações e Avisos de Acessibilidade" title="Avisos do Sistema">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
          <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
        </svg>
        <span class="notify-badge-dot" aria-hidden="true"></span>
      </a>

      <?php if ($currentUser): ?>
      <div class="user-profile-menu-container">
        <button type="button" class="user-profile-btn" id="user-profile-btn" aria-expanded="false" aria-haspopup="true">
          <img src="<?= htmlspecialchars($currentUser['avatar'] ?: 'assets/images/avatar.jpg') ?>" alt="Foto de perfil" class="user-avatar-img" id="header-user-avatar">
          <div class="user-info">
            <span class="user-name" id="header-user-name"><?= htmlspecialchars($currentUser['name']) ?></span>
            <span class="user-role" id="header-user-role"><?= htmlspecialchars($currentUser['role'] ?? 'Cidadão Conectado') ?></span>
          </div>
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#78716c" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="margin-left: 2px;">
            <polyline points="6 9 12 15 18 9"></polyline>
          </svg>
        </button>

        <div class="user-dropdown-menu" id="user-dropdown-menu" role="menu">
          <div class="dropdown-user-header">
            <div class="dd-name"><?= htmlspecialchars($currentUser['name']) ?></div>
            <div class="dd-email"><?= htmlspecialchars($currentUser['email']) ?></div>
          </div>
          <a href="editar-perfil.php" class="dropdown-item" role="menuitem">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
            <span>Meu Perfil &amp; Acessibilidade</span>
          </a>
          <a href="alerta-obstaculo.php" class="dropdown-item" role="menuitem">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path></svg>
            <span>Reportar Obstáculo</span>
          </a>
          <?php if (($currentUser['role'] ?? '') === 'admin'): ?>
          <a href="dashboard.php" class="dropdown-item" role="menuitem">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
            <span>Painel do Gestor</span>
          </a>
          <?php endif; ?>
          <a href="api/auth.php?action=logout" class="dropdown-item danger" role="menuitem">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
            <span>Desconectar Conta</span>
          </a>
        </div>
      </div>
      <?php else: ?>
      <div style="display:flex;gap:8px;">
        <a href="login.php" class="btn-hero-secondary" style="padding:6px 14px;font-size:12px;text-decoration:none;">Entrar</a>
        <a href="cadastro.php" class="btn-hero-primary" style="padding:6px 14px;font-size:12px;text-decoration:none;">Cadastrar</a>
      </div>
      <?php endif; ?>
    </div>
  </header>

  <?php if ($local): ?>
  <!-- Subheader -->
  <section class="subheader-container" aria-label="Navegação estrutural e certificação">
    <div class="subheader-inner">
      <nav class="breadcrumb-nav" aria-label="Trilha de navegação">
        <a href="index.php" class="breadcrumb-link" title="Voltar ao início">
          <svg class="breadcrumb-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
            <polyline points="9 22 9 12 15 12 15 22"></polyline>
          </svg>
          <span>Início</span>
        </a>
        <span class="breadcrumb-sep" aria-hidden="true">›</span>
        <a href="explorar.php" class="breadcrumb-link">Explorar Mundo</a>
        <span class="breadcrumb-sep" aria-hidden="true">›</span>
        <span class="breadcrumb-current" aria-current="page"><?= htmlspecialchars($local['name']) ?></span>
      </nav>

      <div class="security-badge" title="Dados pessoais tratados conforme a Lei Geral de Proteção de Dados">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
        </svg>
        <span>Dados protegidos pela LGPD</span>
      </div>
    </div>
  </section>

  <!-- Conteúdo Principal -->
  <main class="main-content-layout" id="main-content">
    <!-- Hero Place Card -->
    <section class="place-hero-card" aria-label="Informações Principais de <?= htmlspecialchars($local['name']) ?>">
      <div class="hero-info-col">
        <div class="hero-tags-row">
          <span class="tag-category-pill" id="place-category">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path>
              <line x1="3" y1="6" x2="21" y2="6"></line>
              <path d="M16 10a4 4 0 0 1-8 0"></path>
            </svg>
            <?= htmlspecialchars($local['category'] ?? 'Equipamento Público') ?>
          </span>

          <span class="tag-distance-pill">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
              <circle cx="12" cy="10" r="3"></circle>
            </svg>
            <span id="place-bairro"><?= htmlspecialchars($local['bairro'] ?? 'Suzano') ?></span>
          </span>
        </div>

        <div class="hero-rating-row">
          <span class="rating-badge" aria-label="Nota <?= number_format($local['rating'] ?? 5.0, 1, ',', '.') ?> de 5 estrelas">
            <span class="star-icon">★</span> <span id="place-rating"><?= number_format($local['rating'] ?? 5.0, 1, ',', '.') ?></span>
          </span>
          <span class="reviews-count-text" id="place-reviews">(<?= (int)($local['reviews_count'] ?? count($avaliacoes)) ?> avaliações da comunidade)</span>
        </div>

        <h1 class="place-hero-title" id="place-name"><?= htmlspecialchars($local['name']) ?></h1>

        <div class="place-hero-address">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
            <circle cx="12" cy="10" r="3"></circle>
          </svg>
          <span id="place-address"><?= htmlspecialchars($local['address'] ?? 'Suzano - SP') ?></span>
        </div>

        <div class="hero-stats-row">
          <div class="stat-box-item">
            <span class="stat-label">Status Acessibilidade</span>
            <span class="stat-value highlight" id="place-access-text"><?= htmlspecialchars($local['access_text'] ?? 'Acessibilidade Certificada') ?></span>
          </div>

          <div class="stat-box-item">
            <span class="stat-label">Validação NBR</span>
            <span class="stat-value">Certificada NBR 9050</span>
          </div>

          <div class="stat-box-item">
            <span class="stat-label">Última Auditoria</span>
            <span class="stat-value" id="place-last-audit"><?= htmlspecialchars($local['last_audit'] ?? '2026') ?></span>
          </div>
        </div>

        <div class="hero-actions-row">
          <button type="button" class="btn-hero-primary" id="btn-trace-route" onclick="window.location.href='configuracao-rota.php?dest_id=<?= (int)$local['id'] ?>'">
            <span>Traçar rota acessível</span>
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <line x1="5" y1="12" x2="19" y2="12"></line>
              <polyline points="12 5 19 12 12 19"></polyline>
            </svg>
          </button>

          <button type="button" class="btn-hero-secondary" id="btn-open-review-modal" onclick="window.location.href='avaliar-local.php?id=<?= $local['id'] ?>'">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>
            </svg>
            <span id="btn-rate-place">Avaliar local</span>
          </button>

          <button type="button" class="btn-hero-report" id="btn-report-problem">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"></path>
              <line x1="4" y1="22" x2="4" y2="15"></line>
            </svg>
            <span>Reportar barreira</span>
          </button>
        </div>
      </div>

      <div class="hero-photo-col">
        <div class="photo-wrapper" id="hero-photo-container" title="Imagem cadastrada para o local">
          <img 
            src="<?= htmlspecialchars($local['image'] ?: 'assets/images/shopping-suzano.jpg') ?>" 
            alt="Imagem cadastrada de <?= htmlspecialchars($local['name']) ?>" 
            class="photo-img" 
            id="place-hero-image"
            onerror="this.src='assets/images/map-background.jpg';"
          >
          <div class="photo-verified-badge">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path>
              <circle cx="12" cy="13" r="4"></circle>
            </svg>
            <span>Imagem do cadastro</span>
          </div>
        </div>
        <p class="photo-caption-text">A foto não comprova as condições de acessibilidade; vale a vistoria registrada no perfil do local.</p>
      </div>
    </section>

    <!-- Grid 2 Colunas -->
    <div class="details-two-col-grid">
      <div class="details-col-left">
        <!-- Localização no mapa — só com as coordenadas reais do cadastro -->
        <article class="place-location-card" aria-labelledby="location-heading">
          <div class="card-top-header-row">
            <div class="card-header-left">
              <svg class="header-icon-pcd" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                <circle cx="12" cy="10" r="3"></circle>
              </svg>
              <div>
                <h2 class="card-heading-title" id="location-heading">Localização</h2>
                <p class="card-heading-desc" id="place-coords">
                  <?php if ($temMapa): ?>
                    <?= htmlspecialchars(number_format($mapaLat, 6, ',', '.')) ?>, <?= htmlspecialchars(number_format($mapaLng, 6, ',', '.')) ?>
                  <?php else: ?>
                    Coordenadas não registradas no cadastro
                  <?php endif; ?>
                </p>
              </div>
            </div>
          </div>

          <?php if ($temMapa): ?>
            <div class="place-map-box">
              <div
                class="place-map"
                id="place-map"
                data-lat="<?= htmlspecialchars((string)$mapaLat) ?>"
                data-lng="<?= htmlspecialchars((string)$mapaLng) ?>"
                data-nome="<?= htmlspecialchars($local['name']) ?>"
                role="region"
                aria-label="Mapa da localização de <?= htmlspecialchars($local['name']) ?>"
              ></div>
              <div class="place-map-actions">
                <a class="btn-map-action" href="configuracao-rota.php?dest_id=<?= (int)$local['id'] ?>">
                  Traçar rota até aqui
                </a>
                <a class="btn-map-action btn-map-action--ghost" href="mapa-geral.php?lat=<?= htmlspecialchars((string)$mapaLat) ?>&amp;lng=<?= htmlspecialchars((string)$mapaLng) ?>&amp;dest_nome=<?= rawurlencode($local['name']) ?>">
                  Abrir no mapa ampliado
                </a>
              </div>
            </div>
            <p class="place-map-note">O marcador usa a coordenada cadastrada. A condição de acessibilidade do entorno não foi vistoriada por este mapa.</p>
          <?php else: ?>
            <div class="place-map-fallback" role="note">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <circle cx="12" cy="12" r="10"></circle><line x1="8" y1="12" x2="16" y2="12"></line>
              </svg>
              <div>
                <strong>Localização indisponível</strong>
                <p>Este cadastro não tem coordenadas confirmadas, então não há ponto a exibir no mapa. Peça a inclusão da posição por vistoria ou reporte.</p>
              </div>
            </div>
          <?php endif; ?>
        </article>

        <!-- Acessibilidade do Local -->
        <article class="accessibility-details-card" aria-labelledby="accessibility-heading">
          <div class="card-top-header-row">
            <div class="card-header-left">
              <svg class="header-icon-pcd" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <circle cx="12" cy="4" r="2"></circle>
                <path d="M16 8h-4.5a2.5 2.5 0 0 0-2.5 2.5V14"></path>
                <circle cx="9" cy="19" r="3"></circle>
                <path d="M12 19h5a2 2 0 0 0 2-2v-3"></path>
              </svg>
              <div>
                <h2 class="card-heading-title" id="accessibility-heading">Acessibilidade do local</h2>
                <p class="card-heading-desc">Auditado conforme norma municipal e ABNT NBR 9050</p>
              </div>
            </div>

            <div class="badge-audit-high">
              <span class="badge-audit-title" id="place-status-badge">
                <span class="dot-green-live" aria-hidden="true"></span>
                <?= htmlspecialchars(strtoupper($local['access_status'] ?? 'ALTA ACESSIBILIDADE')) ?>
              </span>
              <span class="badge-audit-subtitle">Conformidade auditada</span>
            </div>
          </div>

          <!-- Grid dos Recursos Auditados -->
          <div class="audited-features-grid" id="place-features-list" role="list">
            <?php if (!empty($local['features'])): ?>
              <?php foreach ($local['features'] as $feat): ?>
              <div class="feature-item-box" role="listitem">
                <div class="feature-content-left">
                  <div class="feature-icon-circle icon-green" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>
                  </div>
                  <div class="feature-texts">
                    <span class="feature-name"><?= htmlspecialchars($feat) ?></span>
                    <span class="feature-sub">Verificado in loco</span>
                  </div>
                </div>
                <span class="status-pill-badge status-available">Disponível</span>
              </div>
              <?php endforeach; ?>
            <?php else: ?>
              <div class="feature-item-box" role="listitem">
                <div class="feature-content-left">
                  <div class="feature-icon-circle icon-green"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg></div>
                  <div class="feature-texts"><span class="feature-name">Rampas de acesso regulamentares</span></div>
                </div>
                <span class="status-pill-badge status-available">Disponível</span>
              </div>
              <div class="feature-item-box" role="listitem">
                <div class="feature-content-left">
                  <div class="feature-icon-circle icon-green"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg></div>
                  <div class="feature-texts"><span class="feature-name">Sanitário acessível NBR 9050</span></div>
                </div>
                <span class="status-pill-badge status-available">Disponível</span>
              </div>
            <?php endif; ?>
          </div>

          <div class="nbr-notice-strip">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <circle cx="12" cy="12" r="10"></circle>
              <line x1="12" y1="16" x2="12" y2="12"></line>
              <line x1="12" y1="8" x2="12.01" y2="8"></line>
            </svg>
            <span>As condições são validadas de acordo com a norma ABNT NBR 9050:2020. Notou alguma inconformidade? Reporte uma barreira para acionar a fiscalização municipal.</span>
          </div>
        </article>

        <!-- Transporte Público Conectado -->
        <article class="bus-stop-card" aria-label="Transporte Público Conectado">
          <div class="bus-left-wrap">
            <div class="bus-icon-circle" aria-hidden="true">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="4" y="3" width="16" height="16" rx="2"></rect>
                <path d="M4 11h16"></path>
                <path d="M8 15h.01"></path>
                <path d="M16 15h.01"></path>
                <path d="M6 19v2"></path>
                <path d="M18 19v2"></path>
              </svg>
            </div>
            <div class="bus-texts-col">
              <span class="bus-title">Transporte público informado no cadastro</span>
              <span class="bus-sub">A acessibilidade das paradas próximas não foi vistoriada por esta plataforma.</span>
            </div>
          </div>

          <button type="button" class="btn-view-bus-lines" id="btn-view-bus-lines" onclick="document.getElementById('modal-bus-lines')?.classList.add('active');">
            <span>Ver linhas</span>
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <polyline points="9 18 15 12 9 6"></polyline>
            </svg>
          </button>
        </article>
      </div>

      <!-- Avaliações da Comunidade -->
      <div class="details-col-right">
        <article class="reviews-community-card" aria-labelledby="reviews-heading">
          <div class="reviews-header-row">
            <div class="reviews-header-left">
              <svg class="header-icon-review" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
              </svg>
              <div>
                <h2 class="card-heading-title" id="reviews-heading">Avaliações da comunidade</h2>
                <p class="card-heading-desc">Experiências vivenciadas por cidadãos PcD</p>
              </div>
            </div>

            <a href="avaliar-local.php?id=<?= $local['id'] ?>" class="btn-filter-reviews" style="text-decoration:none;">
              Avaliar
            </a>
          </div>

          <div class="senai-verified-banner" role="note">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
              <polyline points="9 12 11 14 15 10"></polyline>
            </svg>
            <div>
              <div class="senai-banner-heading">Informações verificadas pela comunidade e fiscalização</div>
              <div class="senai-banner-sub">Auditoria colaborativa com foco em inclusão e autonomia na mobilidade urbana.</div>
            </div>
          </div>

          <div class="reviews-list-container" role="feed" aria-label="Feed de avaliações de acessibilidade">
            <?php if (!empty($avaliacoes)): ?>
              <?php foreach ($avaliacoes as $av): ?>
              <?php
                $nomeAutor   = trim($av['autor_atual'] ?? '') ?: trim($av['user_name'] ?? '') ?: 'Cidadão de Suzano';
                $textoReview = trim($av['comment'] ?? '') ?: trim($av['barrier_desc'] ?? '');
                $notaAuthor  = max(1, min(5, (int)round((float)($av['rating'] ?? 5))));
                $dataReview  = !empty($av['created_at']) ? date('d/m/Y H:i', strtotime($av['created_at'])) : '';
                $fotosAv     = array_values(array_filter(
                    is_array($av['photos'] ?? null) ? $av['photos'] : [],
                    'pl_foto_avaliacao_valida'
                ));
              ?>
              <article class="review-item-card" role="article">
                <div class="review-top-row">
                  <div class="reviewer-profile-wrap">
                    <div class="reviewer-avatar-circle avatar-peach" aria-hidden="true">
                      <?= htmlspecialchars(mb_substr($nomeAutor, 0, 2)) ?>
                    </div>
                    <div class="reviewer-meta-text">
                      <span class="reviewer-name"><?= htmlspecialchars($nomeAutor) ?></span>
                      <span class="reviewer-condition"><?= htmlspecialchars(trim($av['condition_label'] ?? '') ?: 'Cidadão de Suzano') ?></span>
                    </div>
                  </div>

                  <div class="stars-rating-display" aria-label="<?= $notaAuthor ?> de 5 estrelas">
                    <?php for ($s = 1; $s <= 5; $s++): ?>
                      <span class="<?= $s <= $notaAuthor ? '' : 'star-empty' ?>">★</span>
                    <?php endfor; ?>
                  </div>
                </div>

                <p class="review-body-text">
                  <?php if ($textoReview !== ''): ?>
                    &ldquo;<?= htmlspecialchars($textoReview) ?>&rdquo;
                  <?php else: ?>
                    <em>Avaliação sem comentário.</em>
                  <?php endif; ?>
                </p>

                <?php if ($fotosAv): ?>
                <ul class="review-photos" aria-label="Fotos enviadas nesta avaliação">
                  <?php foreach ($fotosAv as $i => $fotoUrl): ?>
                  <?php $legenda = sprintf('Foto %d de %d enviada por %s na avaliação de %s', $i + 1, count($fotosAv), $nomeAutor, $local['name']); ?>
                  <li>
                    <button type="button"
                            class="review-photo-thumb"
                            data-full="<?= htmlspecialchars($fotoUrl) ?>"
                            data-alt="<?= htmlspecialchars($legenda) ?>"
                            aria-label="Ampliar <?= htmlspecialchars($legenda) ?>">
                      <img src="<?= htmlspecialchars($fotoUrl) ?>"
                           alt="<?= htmlspecialchars($legenda) ?>"
                           loading="lazy"
                           width="96" height="72">
                    </button>
                  </li>
                  <?php endforeach; ?>
                </ul>
                <?php endif; ?>

                <div class="review-footer-row">
                  <span>Avaliado em: <?= htmlspecialchars($dataReview) ?></span>
                  <span style="font-size:11px;color:#78716c;"><?= (int)($av['is_useful_count'] ?? 0) ?> acharam útil</span>
                </div>
              </article>
              <?php endforeach; ?>
            <?php else: ?>
              <div style="padding:28px 16px;text-align:center;color:#78716c;">
                <p style="font-size:13px;margin-bottom:8px;">Nenhuma avaliação registrada para este local ainda.</p>
                <a href="avaliar-local.php?id=<?= $local['id'] ?>" class="btn-hero-primary" style="display:inline-block;padding:7px 16px;font-size:12px;text-decoration:none;">
                  Seja o primeiro a avaliar
                </a>
              </div>
            <?php endif; ?>
          </div>
        </article>
      </div>
    </div>
  </main>

  <?php else: ?>
  <!-- Estado Vazio Amigável -->
  <main class="main-content-layout" id="main-content" style="padding:60px 20px;text-align:center;">
    <div style="max-width:520px;margin:0 auto;background:#fff;border:1px solid #ede4d8;border-radius:16px;padding:36px 24px;">
      <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#d97706" stroke-width="2" style="margin-bottom:14px;"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
      <h2 style="font-size:20px;color:#1c1917;margin-bottom:10px;"><?= $erroBanco ? 'Base de dados indisponível' : 'Local não encontrado' ?></h2>
      <p style="font-size:13px;color:#78716c;margin-bottom:24px;">
        <?= $erroBanco
          ? htmlspecialchars($erroBanco)
          : 'O ponto de interesse solicitado não foi localizado em nossa base cadastrada ou pode ter sido arquivado.' ?>
      </p>
      <div style="display:flex;gap:10px;justify-content:center;flex-wrap:wrap;">
        <a href="explorar.php" class="btn-hero-primary" style="display:inline-block;padding:10px 24px;text-decoration:none;">
          Voltar para Explorar Locais
        </a>
        <?php if ($erroBanco): ?>
        <a href="<?= htmlspecialchars($_SERVER['REQUEST_URI'] ?? 'explorar.php', ENT_QUOTES) ?>" class="btn-hero-secondary" style="display:inline-block;padding:10px 24px;text-decoration:none;">Tentar novamente</a>
        <?php endif; ?>
      </div>
    </div>
  </main>
  <?php endif; ?>

  <!-- Rodapé do Site -->
  <footer class="site-footer" role="contentinfo">
    <p class="footer-copy">
      &copy; 2026 Passe Livre Suzano. Todos os direitos reservados. Conformidade Lei Federal 13.146/15 e NBR 9050.
    </p>
    <div class="footer-links">
      <a href="termos-de-uso.php">Termos de Uso</a>
      <a href="privacidade.php">Privacidade (LGPD)</a>
      <a href="acessibilidade.php">Acessibilidade</a>
      <a href="fale-conosco.php">Fale Conosco</a>
    </div>
  </footer>

  <!-- Modal: Reportar Barreira -->
  <div class="modal-overlay" id="modal-report-problem" role="dialog" aria-modal="true" aria-labelledby="modal-report-title">
    <div class="modal-box-card">
      <button type="button" class="btn-close-modal" onclick="document.getElementById('modal-report-problem').classList.remove('active');" aria-label="Fechar">&times;</button>
      <div style="display:flex;align-items:center;gap:10px;margin-bottom:14px;">
        <div style="width:38px;height:38px;border-radius:10px;background-color:#fee2e2;display:flex;align-items:center;justify-content:center;color:#dc2626;">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"></path><line x1="4" y1="22" x2="4" y2="15"></line></svg>
        </div>
        <div>
          <h3 id="modal-report-title" style="font-size:18px;font-weight:700;color:#1c1917;">Fiscalização Cidadã</h3>
          <p style="font-size:11.5px;color:#78716c;">Reportar barreira arquitetônica em <?= htmlspecialchars($local['name'] ?? 'Suzano') ?></p>
        </div>
      </div>

      <form id="form-report-problem"
            data-local-id="<?= (int)($local['id'] ?? 0) ?>"
            data-nome="<?= htmlspecialchars($local['name'] ?? '') ?>"
            data-endereco="<?= htmlspecialchars($local['address'] ?? '') ?>"
            data-bairro="<?= htmlspecialchars($local['bairro'] ?? '') ?>"
            data-cidade="<?= htmlspecialchars($local['municipio'] ?? '') ?>"
            data-uf="<?= htmlspecialchars($local['uf'] ?? '') ?>"
            data-lat="<?= htmlspecialchars($temMapa ? (string)$mapaLat : '') ?>"
            data-lng="<?= htmlspecialchars($temMapa ? (string)$mapaLng : '') ?>">
        <fieldset class="report-fields">
          <legend class="sr-only">Dados do reporte de barreira</legend>
          <div style="margin-bottom:14px;">
            <label for="report-type" style="display:block;font-size:12px;font-weight:700;color:#44403c;margin-bottom:5px;">Tipo de barreira:</label>
            <select id="report-type" style="width:100%;padding:9px 12px;border:1px solid #e7e5e4;border-radius:10px;font-family:inherit;font-size:12.5px;background:#faf8f5;">
              <option value="rampa">Rampa danificada ou sem corrimão</option>
              <option value="elevador">Elevador inoperante ou sem som</option>
              <option value="piso">Piso tátil quebrado ou bloqueado</option>
              <option value="banheiro">Sanitário PcD impraticável ou trancado</option>
              <option value="calcada">Calçada esburacada ou obstruída</option>
              <option value="outro">Outro obstáculo</option>
            </select>
          </div>

          <div style="margin-bottom:16px;">
            <label for="report-description" style="display:block;font-size:12px;font-weight:700;color:#44403c;margin-bottom:5px;">Detalhes:</label>
            <textarea id="report-description" rows="3" placeholder="Descreva onde está o obstáculo..." style="width:100%;padding:10px 12px;border:1px solid #e7e5e4;border-radius:10px;font-family:inherit;font-size:12px;resize:vertical;" required></textarea>
          </div>

          <p class="report-geo-hint" id="report-geo-hint">
            <?php if ($temMapa): ?>
              O relato será vinculado às coordenadas cadastradas deste local.
            <?php else: ?>
              Este local não tem coordenadas no cadastro: o relato fica apenas com o endereço descrito.
            <?php endif; ?>
          </p>

          <p class="report-status" id="report-status" role="status" aria-live="polite"></p>

          <div style="display:flex;align-items:center;justify-content:flex-end;gap:10px;">
            <button type="button" class="btn-hero-secondary btn-report-cancel" style="padding:8px 16px;">Cancelar</button>
            <button type="submit" class="btn-hero-primary" id="btn-send-report" style="padding:9px 20px;background:linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);">
              <span>Enviar Reporte</span>
            </button>
          </div>
        </fieldset>
      </form>
    </div>
  </div>

  <!-- Confirmação do reporte — só abre depois que o banco responder -->
  <div class="modal-overlay" id="modal-report-success" role="dialog" aria-modal="true" aria-labelledby="report-success-title">
    <div class="modal-box-card" style="max-width:420px;">
      <h3 id="report-success-title" style="font-size:18px;font-weight:700;color:#166534;margin-bottom:8px;">Reporte registrado</h3>
      <p style="font-size:13px;color:#44403c;margin-bottom:6px;">Seu relato entrou na fila de triagem da prefeitura.</p>
      <p style="font-size:13px;color:#1c1917;margin-bottom:16px;">Protocolo: <strong id="report-protocol">—</strong></p>
      <div style="display:flex;justify-content:flex-end;gap:10px;">
        <a href="central-notificacoes.php" class="btn-hero-secondary" style="padding:8px 16px;text-decoration:none;">Ver notificações</a>
        <button type="button" class="btn-hero-primary btn-report-success-close" style="padding:9px 20px;"><span>Fechar</span></button>
      </div>
    </div>
  </div>

  <!-- Ampliação das fotos de avaliação -->
  <dialog class="photo-lightbox" id="photo-lightbox" aria-label="Foto da avaliação em tamanho maior">
    <div class="photo-lightbox-inner">
      <img src="" alt="" id="photo-lightbox-img">
      <div class="photo-lightbox-bar">
        <p class="photo-lightbox-caption" id="photo-lightbox-caption"></p>
        <button type="button" class="btn-hero-primary" id="photo-lightbox-close"><span>Fechar</span></button>
      </div>
    </div>
  </dialog>

  <!-- Modal: Linhas de Ônibus Conectadas -->
  <div class="modal-overlay" id="modal-bus-lines" role="dialog" aria-modal="true" aria-labelledby="modal-bus-title">
    <div class="modal-box-card" style="max-width:520px;">
      <button type="button" class="btn-close-modal" onclick="document.getElementById('modal-bus-lines').classList.remove('active');" aria-label="Fechar">&times;</button>
      <div style="display:flex;align-items:center;gap:10px;margin-bottom:16px;">
        <div style="width:38px;height:38px;border-radius:10px;background-color:#fed7aa;display:flex;align-items:center;justify-content:center;color:#c2410c;">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="3" width="16" height="16" rx="2"></rect><path d="M4 11h16"></path><path d="M8 15h.01"></path><path d="M16 15h.01"></path></svg>
        </div>
        <div>
          <h3 id="modal-bus-title" style="font-size:18px;font-weight:700;color:#1c1917;">Linhas de Transporte Conectadas</h3>
          <p style="font-size:11.5px;color:#78716c;">Informação declarada no cadastro deste local.</p>
        </div>
      </div>

      <div style="display:flex;flex-direction:column;gap:10px;margin-bottom:20px;">
        <?php if ($busLines): ?>
          <?php foreach ($busLines as $linha): ?>
            <div style="background-color:#faf7f2;border:1px solid #ede5d8;border-radius:12px;padding:12px 14px;display:flex;align-items:center;justify-content:space-between;gap:10px;">
              <strong style="font-size:12.5px;color:#1c1917;"><?= htmlspecialchars($linha, ENT_QUOTES, 'UTF-8') ?></strong>
              <span style="font-size:11px;color:#78716c;text-align:right;">Sem vistoria de acessibilidade registrada</span>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div style="background-color:#faf7f2;border:1px solid #ede5d8;border-radius:12px;padding:12px 14px;">
            <p style="font-size:12px;color:#57534e;margin:0;">Nenhuma linha de transporte consta no cadastro deste local. Informe as linhas ao relatar uma barreira na parada.</p>
          </div>
        <?php endif; ?>
      </div>

      <div style="display:flex;justify-content:flex-end;">
        <button type="button" class="btn-hero-primary" onclick="document.getElementById('modal-bus-lines').classList.remove('active');" style="padding:8px 22px;">
          <span>Entendido</span>
        </button>
      </div>
    </div>
  </div>

  <!-- Toast -->
  <div class="toast-notice" id="app-toast" role="status" aria-live="polite">
    <span class="toast-icon-wrap" aria-hidden="true"></span><span id="toast-message"></span>
  </div>

    <script src="js/leaflet.js"></script>
  <script src="js/auth.js"></script>
  <script src="js/detalhes-local.js"></script>
</body>
</html>
