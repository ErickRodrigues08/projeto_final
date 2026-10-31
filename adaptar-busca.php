<?php
/**
 * Passe Livre Suzano — Área do Cidadão / Adaptar Busca
 * Proteção server-side: usuário autenticado.
 * Se não logado, redireciona para login.php.
 */
require_once __DIR__ . '/includes/auth_guard.php';
require_once __DIR__ . '/includes/acessibilidade.php';
$userRaw  = auth_require();
$userData = auth_user_safe($userRaw);

// Pré-carrega preferências de busca do usuário
$preferencias = [];
try {
    $db = get_db();
    $stmtPref = $db->prepare("SELECT * FROM usuario_preferencias_busca WHERE user_id = ? LIMIT 1");
    $stmtPref->execute([$userRaw['id']]);
    $preferencias = $stmtPref->fetch() ?: [];
    $prefRecursos = is_string($preferencias['recursos'] ?? null) ? json_decode($preferencias['recursos'], true) : [];
} catch (\Throwable $e) {
    $prefRecursos = [];
}

// Categorias PcD: o cadastro grava rótulos e o Meu Perfil grava chaves curtas.
$pcdRaw = is_array($userRaw['pcd_categories'] ?? null)
    ? $userRaw['pcd_categories']
    : (json_decode((string)($userRaw['pcd_categories'] ?? ''), true) ?: []);
$pcdKeys = pl_pcd_keys($pcdRaw);

// Fallback: cadastros antigos podem ter as categorias apenas em usuario_necessidades
if (!$pcdKeys) {
    try {
        $stmtNec = get_db()->prepare("SELECT categoria_pcd FROM usuario_necessidades WHERE user_id = ?");
        $stmtNec->execute([$userRaw['id']]);
        $pcdKeys = pl_pcd_keys($stmtNec->fetchAll(PDO::FETCH_COLUMN));
    } catch (\Throwable $e) {}
}
$pcdCategories = pl_pcd_labels($pcdKeys);

// Converte as chaves do perfil para os IDs das opções desta tela
$pcdToNeeds = [
    'motora'       => ['cadeirante'],
    'visual'       => ['deficiencia_visual'],
    'auditiva'     => ['deficiencia_auditiva'],
    'intelectual'  => ['necessidades_cognitivas'],
    'psicossocial' => ['necessidades_cognitivas'],
    'mobilidade'   => ['mobilidade_reduzida'],
    'outra'        => ['acessibilidade_geral'],
];
$profileNeeds = [];
foreach ($pcdKeys as $pcdKey) {
    foreach ($pcdToNeeds[$pcdKey] ?? [] as $need) {
        $profileNeeds[$need] = true;
    }
}
$profileNeeds = array_keys($profileNeeds);

$needSelected = static fn(string $need): bool => in_array($need, $profileNeeds, true);

// Resumo do perfil exibido na tela inicial: descrição livre ou categorias salvas.
$profileSummary = trim((string)($userRaw['description'] ?? ''));
if ($profileSummary === '') {
    $profileLabels  = pl_pcd_labels($pcdCategories);
    $profileSummary = $profileLabels
        ? implode(', ', $profileLabels)
        : 'Nenhuma informação de acessibilidade cadastrada';
}

// Notificações não lidas
$unreadCount = 0;
try {
    $stmtUnread = $db->prepare("SELECT COUNT(*) FROM notificacoes WHERE user_id = ? AND is_read = 0");
    $stmtUnread->execute([$userRaw['id']]);
    $unreadCount = (int)$stmtUnread->fetchColumn();
} catch (\Throwable $e) {}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Como podemos adaptar sua busca? — Passe Livre Suzano</title>
  <meta name="description" content="Selecione suas necessidades para filtrar estabelecimentos e caminhos compatíveis com elas na região pesquisada.">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="assets/fonts/fonts.css">
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="css/adaptar-busca.css">
  <link rel="icon" type="image/x-icon" href="favicon.ico">
  <link rel="icon" type="image/png" sizes="32x32" href="assets/icons/favicon-32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="assets/icons/favicon-16.png">
  <link rel="apple-touch-icon" href="assets/icons/apple-touch-icon.png">
</head>
<body class="adapt-page">
  <a href="#main-content" class="skip-link">Pular para o conteúdo principal</a>

  <!-- Header -->
  <header class="site-header" role="banner">
    <div class="header-left">
      <a href="index.php" class="brand-logo" title="Voltar para a Página Inicial">
        <img src="assets/icons/logo.png" alt="Passe Livre Logo" width="160" height="38" onerror="this.src='assets/icons/logo.svg'">
      </a>
      <div class="header-search-wrap">
        <img src="assets/icons/search.svg" class="search-icon" alt="" aria-hidden="true">
        <input type="search" id="global-search-input" class="header-search-input" placeholder="Buscar vias, pontos ou linhas..." aria-label="Buscar vias, pontos ou linhas no sistema">
      </div>
    </div>

    <nav class="header-nav" aria-label="Navegação Principal">
      <a href="explorar.php" class="nav-link" title="Explorar o mapa">
        <img src="assets/icons/globe.svg" alt="" aria-hidden="true">
        <span>Explorar Mundo</span>
      </a>
      <a href="configuracao-rota.php" class="nav-link" title="Consultar rotas acessíveis">
        <img src="assets/icons/routes.svg" alt="" aria-hidden="true">
        <span>Rotas</span>
      </a>
      <a href="lugares-encontrados.php" class="nav-link" title="Pontos de apoio e acolhimento">
        <img src="assets/icons/support.svg" alt="" aria-hidden="true">
        <span>Pontos de Apoio</span>
      </a>
      <a href="direitos.php" class="nav-link" title="Direitos e normas de acessibilidade">
        <img src="assets/icons/shield.svg" alt="" aria-hidden="true">
        <span>Direitos</span>
      </a>
    </nav>

    <!-- Área do usuário -->
    <div class="header-right">
      <a href="central-notificacoes.php" class="btn-icon-notify" id="btn-notifications" aria-label="Notificações" title="Ver notificações">
        <img src="assets/icons/bell.svg" alt="" aria-hidden="true">
        <?php if ($unreadCount > 0): ?>
          <span class="notify-badge-dot" aria-hidden="true"></span>
        <?php endif; ?>
      </a>

      <div class="user-profile-menu-container">
        <button type="button" class="user-profile-btn" id="user-profile-btn" aria-expanded="false" aria-haspopup="true" aria-label="Menu do usuário <?= htmlspecialchars($userData['name']) ?>">
          <img src="<?= htmlspecialchars($userData['avatar'] ?: 'assets/images/avatar.jpg') ?>" alt="Foto de <?= htmlspecialchars($userData['name']) ?>" class="user-avatar-img" id="header-user-avatar" onerror="this.src='assets/images/avatar.jpg'">
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
            <div class="dd-name" id="dd-user-name"><?= htmlspecialchars($userData['name']) ?></div>
            <div class="dd-email" id="dd-user-email"><?= htmlspecialchars($userData['email']) ?></div>
          </div>
          <a href="editar-perfil.php" class="dropdown-item" role="menuitem">
            <img src="assets/icons/user.svg" width="15" height="15" alt="">
            <span>Meu Perfil</span>
          </a>
          <a href="personalizar-busca.php" class="dropdown-item" role="menuitem">
            <img src="assets/icons/sliders.svg" width="15" height="15" alt="">
            <span>Personalizar Parâmetros</span>
          </a>
          <a href="ajuda.php" class="dropdown-item" role="menuitem">
            <img src="assets/icons/help.svg" width="15" height="15" alt="">
            <span>Ajuda e Suporte</span>
          </a>
          <a href="central-notificacoes.php" class="dropdown-item" role="menuitem">
            <img src="assets/icons/bell.svg" width="15" height="15" alt="">
            <span>Notificações</span>
          </a>
          <div class="dropdown-divider"></div>
          <button type="button" class="dropdown-item danger" id="btn-dd-logout" role="menuitem">
            <img src="assets/icons/logout.svg" width="15" height="15" alt="">
            <span>Sair da conta</span>
          </button>
        </div>
      </div>
    </div>
  </header>

  <!-- Subheader Breadcrumbs -->
  <section class="subheader-container" aria-label="Navegação estrutural e certificação">
    <div class="breadcrumb-row">
      <img src="assets/icons/home.svg" class="breadcrumb-icon" alt="" aria-hidden="true">
      <a href="index.php" class="breadcrumb-link">Início</a>
      <span class="breadcrumb-sep">/</span>
      <a href="explorar.php" class="breadcrumb-link">Portal do Cidadão</a>
      <span class="breadcrumb-sep">/</span>
      <span class="breadcrumb-current">Adaptar Busca</span>
    </div>

    <div class="security-badge">
      <img src="assets/icons/lock.svg" alt="" aria-hidden="true">
      <span>Ambiente Seguro • Dados Protegidos (LGPD)</span>
    </div>
  </section>

  <!-- Palco Principal -->
  <main class="main-stage" id="main-content">
    <section class="adapt-card" aria-labelledby="heading-adaptar-busca">
      
      <div class="card-handle-pill" aria-hidden="true"></div>

      <div class="card-top-tags">
        <div class="badge-pill badge-pill-left">
          <img src="assets/icons/sliders.svg" alt="" aria-hidden="true">
          <span>Personalização de Acessibilidade</span>
        </div>

        <div class="badge-pill badge-pill-right">
          <img src="assets/icons/lightbulb.svg" alt="" aria-hidden="true">
          <span>Combine mais de uma opção</span>
        </div>
      </div>

      <h1 class="card-main-title" id="heading-adaptar-busca">Como podemos adaptar sua busca?</h1>
      <p class="card-main-desc">
        Selecione suas necessidades para filtrarmos os estabelecimentos e caminhos compatíveis com elas na região que você pesquisar.
      </p>

      <!-- Faixa de sincronização de perfil -->
      <div class="profile-sync-bar" id="profile-sync-bar">
        <div class="sync-left">
          <div class="sync-avatar-icon">
            <img src="assets/icons/user.svg" alt="" aria-hidden="true">
          </div>
          <div class="sync-text-wrap">
            <span class="sync-title">Usar preferências salvas no meu perfil</span>
            <span class="sync-subtitle">
              Perfil ativo: <strong id="sync-user-name"><?= htmlspecialchars($userData['name']) ?></strong> (<span id="sync-user-condition"><?= htmlspecialchars($profileSummary) ?></span>)
            </span>
          </div>
        </div>

        <div class="sync-right">
          <label class="switch-toggle" for="toggle-sync-profile" title="Sincronizar preferências com perfil">
            <input type="checkbox" id="toggle-sync-profile" checked aria-label="Sincronizar preferências com perfil">
            <span class="switch-slider"></span>
          </label>
          <span class="sync-status-label" id="sync-status-text">Sincronizado</span>
        </div>
      </div>

      <!-- Grid com as 6 Opções de Acessibilidade -->
      <div class="needs-grid" role="group" aria-label="Selecione as opções de acessibilidade">
        
        <!-- Opção 1: Cadeirante -->
        <div class="need-card <?= $needSelected('cadeirante') ? 'selected' : '' ?>" data-need="cadeirante" tabindex="0" role="checkbox" aria-checked="<?= $needSelected('cadeirante') ? 'true' : 'false' ?>" aria-label="Opção Cadeirante">
          <div class="need-header">
            <div class="need-title-wrap">
              <div class="need-icon-box">
                <img src="assets/icons/pcd.png" alt="" aria-hidden="true">
              </div>
              <span class="need-title">Cadeirante</span>
              <span class="need-badge-active">Ativo</span>
            </div>
            <div class="need-check-indicator">
              <img src="assets/icons/check-circle.svg" class="check-circle-icon" alt="Selecionado">
              <span class="uncheck-circle" aria-hidden="true"></span>
            </div>
          </div>
          <p class="need-desc">Rotas sem desnível abrupto, rampas suaves e portas automáticas.</p>
        </div>

        <!-- Opção 2: Mobilidade reduzida -->
        <div class="need-card <?= $needSelected('mobilidade_reduzida') ? 'selected' : '' ?>" data-need="mobilidade_reduzida" tabindex="0" role="checkbox" aria-checked="<?= $needSelected('mobilidade_reduzida') ? 'true' : 'false' ?>" aria-label="Opção Mobilidade reduzida">
          <div class="need-header">
            <div class="need-title-wrap">
              <div class="need-icon-box">
                <img src="assets/icons/mobilidade.red.png" alt="" aria-hidden="true">
              </div>
              <span class="need-title">Mobilidade reduzida</span>
              <span class="need-badge-active">Ativo</span>
            </div>
            <div class="need-check-indicator">
              <img src="assets/icons/check-circle.svg" class="check-circle-icon" alt="Selecionado">
              <span class="uncheck-circle" aria-hidden="true"></span>
            </div>
          </div>
          <p class="need-desc">Evitar escadarias longas, priorizar elevadores e apoios de corrimão.</p>
        </div>

        <!-- Opção 3: Deficiência visual -->
        <div class="need-card <?= $needSelected('deficiencia_visual') ? 'selected' : '' ?>" data-need="deficiencia_visual" tabindex="0" role="checkbox" aria-checked="<?= $needSelected('deficiencia_visual') ? 'true' : 'false' ?>" aria-label="Opção Deficiência visual">
          <div class="need-header">
            <div class="need-title-wrap">
              <div class="need-icon-box">
                <img src="assets/icons/eye.svg" alt="" aria-hidden="true">
              </div>
              <span class="need-title">Deficiência visual</span>
              <span class="need-badge-active">Ativo</span>
            </div>
            <div class="need-check-indicator">
              <img src="assets/icons/check-circle.svg" class="check-circle-icon" alt="Selecionado">
              <span class="uncheck-circle" aria-hidden="true"></span>
            </div>
          </div>
          <p class="need-desc">Rotas com piso tátil direcional/alerta, semáforos sonoros e leitor.</p>
        </div>

        <!-- Opção 4: Deficiência auditiva -->
        <div class="need-card <?= $needSelected('deficiencia_auditiva') ? 'selected' : '' ?>" data-need="deficiencia_auditiva" tabindex="0" role="checkbox" aria-checked="<?= $needSelected('deficiencia_auditiva') ? 'true' : 'false' ?>" aria-label="Opção Deficiência auditiva">
          <div class="need-header">
            <div class="need-title-wrap">
              <div class="need-icon-box">
                <img src="assets/icons/hearing-impairment.svg" alt="" aria-hidden="true" onerror="this.src='assets/icons/support.svg'">
              </div>
              <span class="need-title">Deficiência auditiva</span>
              <span class="need-badge-active">Ativo</span>
            </div>
            <div class="need-check-indicator">
              <img src="assets/icons/check-circle.svg" class="check-circle-icon" alt="Selecionado">
              <span class="uncheck-circle" aria-hidden="true"></span>
            </div>
          </div>
          <p class="need-desc">Sinalização visual destacada, atendentes capacitados ou intérprete de Libras.</p>
        </div>

        <!-- Opção 5: Necessidades cognitivas -->
        <div class="need-card <?= $needSelected('necessidades_cognitivas') ? 'selected' : '' ?>" data-need="necessidades_cognitivas" tabindex="0" role="checkbox" aria-checked="<?= $needSelected('necessidades_cognitivas') ? 'true' : 'false' ?>" aria-label="Opção Necessidades cognitivas">
          <div class="need-header">
            <div class="need-title-wrap">
              <div class="need-icon-box">
                <img src="assets/icons/cognitive.svg" alt="" aria-hidden="true" onerror="this.src='assets/icons/lightbulb.svg'">
              </div>
              <span class="need-title">Necessidades cognitivas</span>
              <span class="need-badge-active">Ativo</span>
            </div>
            <div class="need-check-indicator">
              <img src="assets/icons/check-circle.svg" class="check-circle-icon" alt="Selecionado">
              <span class="uncheck-circle" aria-hidden="true"></span>
            </div>
          </div>
          <p class="need-desc">Ambientes calmos, baixo estímulo sensorial, sinalização intuitiva e amigável.</p>
        </div>

        <!-- Opção 6: Acessibilidade geral -->
        <div class="need-card <?= $needSelected('acessibilidade_geral') ? 'selected' : '' ?>" data-need="acessibilidade_geral" tabindex="0" role="checkbox" aria-checked="<?= $needSelected('acessibilidade_geral') ? 'true' : 'false' ?>" aria-label="Opção Acessibilidade geral">
          <div class="need-header">
            <div class="need-title-wrap">
              <div class="need-icon-box">
                <img src="assets/icons/accessibility-general.svg" alt="" aria-hidden="true" onerror="this.src='assets/icons/shield.svg'">
              </div>
              <span class="need-title">Acessibilidade geral</span>
              <span class="need-badge-active">Ativo</span>
            </div>
            <div class="need-check-indicator">
              <img src="assets/icons/check-circle.svg" class="check-circle-icon" alt="Selecionado">
              <span class="uncheck-circle" aria-hidden="true"></span>
            </div>
          </div>
          <p class="need-desc">Requisitos arquitetônicos universais conforme norma NBR 9050.</p>
        </div>

      </div>

      <!-- Faixa Informativa de Tempo Real -->
      <div class="realtime-notice-strip">
        <img src="assets/icons/clock-route.svg" alt="" aria-hidden="true" onerror="this.src='assets/icons/routes.svg'">
        <span>Rotas calculadas a partir de dados abertos de mapas (OpenStreetMap).</span>
      </div>

      <!-- Rodapé de Ações do Card -->
      <div class="card-action-row">
        <a href="explorar.php" class="btn-return-map" title="Voltar para a visualização do mapa da região pesquisada">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <line x1="19" y1="12" x2="5" y2="12"></line>
            <polyline points="12 19 5 12 12 5"></polyline>
          </svg>
          <span>Voltar ao mapa</span>
        </a>

        <button type="button" class="btn-continue-results" id="btn-continue" title="Aplicar necessidades e ver locais encontrados">
          <span>Continuar para resultados</span>
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <line x1="5" y1="12" x2="19" y2="12"></line>
            <polyline points="12 5 19 12 12 19"></polyline>
          </svg>
        </button>
      </div>

    </section>
  </main>

  <!-- Rodapé Institucional -->
  <footer class="site-footer" role="contentinfo">
    <p class="footer-copy">
      &copy; 2026 Passe Livre Suzano. Todos os direitos reservados. Desenvolvido com carinho pelos alunos do SENAI Suzano.
    </p>
    <div class="footer-links">
      <a href="termos-de-uso.php">Termos de Uso</a>
      <a href="privacidade.php">Privacidade (LGPD)</a>
      <a href="acessibilidade.php">Acessibilidade</a>
      <a href="fale-conosco.php">Fale Conosco</a>
    </div>
  </footer>

  <div class="toast-notice" id="app-toast" role="status" aria-live="polite">
    <span class="toast-icon-wrap" aria-hidden="true"></span>
    <span id="toast-message">Preferências salvas com sucesso!</span>
  </div>

  <!-- Dados do usuário injetados para uso pelo JS -->
  <script id="php-user-data" type="application/json">
    <?= json_encode([
      'id'           => $userData['id'],
      'name'         => $userData['name'],
      'email'        => $userData['email'],
      'role'         => $userData['role'],
      'avatar'       => $userData['avatar'],
      'initials'     => $userData['initials'],
      'is_admin'     => $userData['is_admin'],
      'condition'    => $profileSummary,
      'pcdCategories'=> $pcdCategories,
      'profileNeeds' => $profileNeeds,
      'raio_km'      => $preferencias['raio_km'] ?? 5.0,
      'nivel_acess'  => $preferencias['nivel_acessibilidade'] ?? 'todos',
      'recursos'     => $prefRecursos ?? [],
    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>
  </script>

    <script src="js/db.js"></script>
  <script src="js/db-queries.js"></script>
  <script src="js/auth.js"></script>
  <script src="js/adaptar-busca.js"></script>
</body>
</html>
