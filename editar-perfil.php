<?php
/**
 * Passe Livre Suzano — Editar Perfil do Cidadão
 * Proteção server-side com autenticação obrigatória.
 * Conexão direta com banco MySQL via PDO.
 */
require_once __DIR__ . '/includes/auth_guard.php';
require_once __DIR__ . '/includes/acessibilidade.php';
$userRaw  = auth_require();
$userData = auth_user_safe($userRaw);

// Categorias PcD: aceita as chaves do perfil e os rótulos usados no cadastro.
// Cadastros antigos podem ter a categoria apenas em usuario_necessidades.
$pcdRaw = is_array($userRaw['pcd_categories'] ?? null)
    ? $userRaw['pcd_categories']
    : (json_decode((string)($userRaw['pcd_categories'] ?? ''), true) ?: []);
$pcdCategories = pl_pcd_keys($pcdRaw);

if (!$pcdCategories) {
    try {
        $stmtNec = get_db()->prepare("SELECT categoria_pcd FROM usuario_necessidades WHERE user_id = ?");
        $stmtNec->execute([(int)$userRaw['id']]);
        $pcdCategories = pl_pcd_keys($stmtNec->fetchAll(PDO::FETCH_COLUMN));
    } catch (\Throwable $e) {
        $pcdCategories = [];
    }
}

// Preferências urbanas salvas (rótulos antigos são convertidos para as chaves do formulário).
$savedPrefs = is_array($userRaw['saved_prefs'] ?? null)
    ? $userRaw['saved_prefs']
    : (json_decode((string)($userRaw['saved_prefs'] ?? ''), true) ?: []);
$savedPrefs = pl_pref_keys($savedPrefs);

$phone   = $userRaw['phone'] ?? '';
$city    = $userRaw['city'] ?? 'Suzano - SP';
$bairro  = $userRaw['bairro'] ?? '';
$desc    = trim((string)($userRaw['description'] ?? ''));
$hasA11yInfo = !empty($pcdCategories) || $desc !== '';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Editar Perfil — Passe Livre Suzano</title>
  <meta name="description" content="Gerencie seus dados pessoais e preferências de acessibilidade para uma Suzano mais inclusiva no Passe Livre.">
  <link rel="stylesheet" href="assets/fonts/fonts.css">
  <link rel="stylesheet" href="<?= pl_asset_url('css/style.css') ?>">
  <link rel="stylesheet" href="<?= pl_asset_url('css/perfil.css') ?>">
  <link rel="icon" type="image/x-icon" href="favicon.ico">
  <link rel="icon" type="image/png" sizes="32x32" href="assets/icons/favicon-32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="assets/icons/favicon-16.png">
  <link rel="apple-touch-icon" href="assets/icons/apple-touch-icon.png">
</head>
<body style="background-color:#faf7f2;">
  <a href="#main-content" class="skip-link">Pular para o conteúdo principal</a>

  <!-- Header Global -->
  <header class="site-header" role="banner">
    <div class="header-left">
      <a href="index.php" class="brand-logo" title="Voltar ao início do Passe Livre">
        <img src="assets/icons/logo.png" alt="Passe Livre Logo" width="160" height="38" onerror="this.src='assets/icons/logo.svg'">
      </a>
      <div style="display:flex;align-items:center;gap:6px;background:#ecfdf5;border:1px solid #a7f3d0;border-radius:9999px;padding:4px 12px;font-size:11px;font-weight:700;color:#065f46;">
        <span style="width:7px;height:7px;border-radius:50%;background:#10b981;display:inline-block;"></span>
        <span>Suzano Online • Integração Ativa</span>
      </div>
      <div class="header-search-wrap">
        <img src="assets/icons/search.svg" class="search-icon" alt="" aria-hidden="true">
        <input type="search" class="header-search-input" placeholder="Buscar serviços, itinerários ou protocolos..." aria-label="Buscar">
      </div>
    </div>
    <div class="header-right">
      <a href="central-notificacoes.php" class="btn-icon-notify" id="btn-notify-profile" aria-label="Notificações">
        <img src="assets/icons/bell.svg" alt="" aria-hidden="true">
      </a>
      <a href="explorar.php" class="user-profile" title="Voltar ao portal do cidadão">
        <img src="<?= htmlspecialchars($userData['avatar']) ?>" alt="Foto de <?= htmlspecialchars($userData['name']) ?>" class="user-avatar-img" id="header-user-avatar" onerror="this.src='assets/images/avatar.jpg'">
        <div class="user-info">
          <span class="user-name" id="header-user-name"><?= htmlspecialchars($userData['name']) ?></span>
          <span class="user-role" id="header-user-role"><?= htmlspecialchars($userData['role']) ?></span>
        </div>
      </a>
    </div>
  </header>

  <!-- Breadcrumbs -->
  <div style="background-color: #faf6ef; border-bottom: 1px solid #ede4d8; padding: 8px 28px;">
    <div style="display: flex; align-items: center; gap: 6px; font-size: 11.5px; color: #78716c; max-width: 920px; margin: 0 auto;">
      <img src="assets/icons/home.svg" alt="" style="width: 13px; height: 13px; opacity: 0.7;">
      <a href="index.php" style="color: #78716c; text-decoration: none;">Início</a>
      <span style="color: #d6d3d1;">/</span>
      <a href="explorar.php" style="color: #78716c; text-decoration: none;">Portal do Cidadão</a>
      <span style="color: #d6d3d1;">/</span>
      <span style="color: #b45309; font-weight: 600;">Editar Perfil</span>
    </div>
  </div>

  <!-- Conteúdo Principal -->
  <main class="page-container" id="main-content">
    <div class="page-nav-header">
      <a href="explorar.php" class="back-link">
        <span>&larr;</span> Voltar para o Portal do Cidadão
      </a>

      <div class="title-row">
        <h1 class="page-main-title">Editar Perfil</h1>
        <div class="verified-account-badge">
          <span class="verified-dot"></span>
          <span>Conta Verificada no SUZANO DIGITAL</span>
        </div>
      </div>

      <p class="page-subtitle">
        Gerencie seus dados pessoais e preferências de acessibilidade para uma Suzano mais inclusiva.
      </p>
    </div>

    <!-- SEÇÃO 1: FOTO DE IDENTIFICAÇÃO -->
    <section class="profile-card" aria-labelledby="heading-photo">
      <div class="photo-flex-layout">
        <div class="photo-left-group">
          <div class="photo-avatar-container">
            <img src="<?= htmlspecialchars($userData['avatar']) ?>" alt="Foto de perfil de <?= htmlspecialchars($userData['name']) ?>" class="profile-large-avatar" id="avatar-preview" onerror="this.src='assets/images/avatar.jpg'">
            <label for="input-file-avatar" class="avatar-edit-badge" title="Alterar foto">
              <img src="assets/icons/edit.svg" alt="">
            </label>
            <input type="file" id="input-file-avatar" accept="image/*" style="display: none;">
          </div>

          <div class="photo-info-group">
            <h2 class="photo-title" id="heading-photo">Foto de Identificação</h2>
            <p class="photo-guidelines">
              JPG, PNG ou WebP até 5MB. Recomendamos foto nítida e recente com iluminação frontal.
            </p>
            <div class="photo-buttons-row">
              <button type="button" class="btn-photo-amber" id="btn-adjust-framing">
                <img src="assets/icons/crop.svg" alt="" style="width: 13px; height: 13px; filter: brightness(0) invert(1);">
                <span>Ajustar enquadramento</span>
              </button>
              <button type="button" class="btn-photo-remove" id="btn-remove-photo">
                <img src="assets/icons/trash.svg" alt="" style="width: 12px; height: 12px;">
                <span>Remover foto</span>
              </button>
            </div>
          </div>
        </div>

        <div class="photo-optim-badge" id="badge-optim-enquadramento" role="button" tabindex="0" title="Clique para ajustar o enquadramento">
          <div class="optim-icon-wrap">
            <img src="assets/icons/crop.svg" alt="">
          </div>
          <div class="optim-text-group">
            <div class="optim-status-top">
              <span>Ajuste de enquadramento</span>
              <span class="optim-status-pill" id="optim-status-tag">Pronto</span>
            </div>
            <p class="optim-desc">Foto otimizada para os validadores do Passe Livre</p>
          </div>
        </div>
      </div>
    </section>

    <!-- FORMULÁRIO GERAL -->
    <form id="profile-edit-form" onsubmit="return false;">
      <input type="hidden" id="input-profile-id" value="<?= (int)$userData['id'] ?>">

      <!-- SEÇÃO 2: INFORMAÇÕES PESSOAIS -->
      <section class="profile-card" aria-labelledby="heading-personal-info" style="margin-bottom: 20px;">
        <div class="profile-card-header">
          <div class="card-heading-group">
            <img src="assets/icons/user.svg" alt="" aria-hidden="true">
            <h2 class="card-heading-title" id="heading-personal-info">Informações Pessoais</h2>
          </div>
          <span class="card-header-note">Dados sincronizados com o Cartão Passe Livre</span>
        </div>

        <div class="form-grid-2col">
          <!-- Nome Completo -->
          <div class="form-group-item">
            <label for="input-fullname" class="form-field-label">Nome Completo *</label>
            <div class="input-box-relative">
              <input 
                type="text" 
                id="input-fullname" 
                name="name"
                class="input-field-custom has-icon-right" 
                value="<?= htmlspecialchars($userData['name']) ?>" 
                required
              >
              <input type="hidden" id="input-profile-name" value="<?= htmlspecialchars($userData['name']) ?>">
              <img src="assets/icons/check.svg" class="input-icon-trailing" alt="Válido" style="color: #10b981;">
            </div>
            <span class="form-field-helper">Nome idêntico ao emitido no documento oficial com foto.</span>
          </div>

          <!-- Nome de Usuário / Código -->
          <div class="form-group-item">
            <label for="input-username" class="form-field-label">Código de Usuário</label>
            <div class="input-box-relative">
              <input 
                type="text" 
                id="input-username" 
                class="input-field-custom" 
                value="<?= htmlspecialchars($userRaw['user_code'] ?? 'USR-' . $userData['id']) ?>" 
                readonly
              >
            </div>
            <span class="form-field-helper">Identificador único dentro da rede do Passe Livre Suzano.</span>
          </div>

          <!-- E-mail Principal -->
          <div class="form-group-item">
            <label for="input-email" class="form-field-label">E-mail Principal *</label>
            <div class="input-box-relative">
              <input 
                type="email" 
                id="input-email" 
                name="email"
                class="input-field-custom has-badge-right" 
                value="<?= htmlspecialchars($userData['email']) ?>" 
                required
              >
              <input type="hidden" id="input-profile-email" value="<?= htmlspecialchars($userData['email']) ?>">
              <div class="input-badge-trailing">
                <img src="assets/icons/check.svg" style="width: 10px; height: 10px;" alt="">
                <span>Verificado</span>
              </div>
            </div>
            <span class="form-field-helper">Receberá comprovantes de recarga e avisos de renovação.</span>
          </div>

          <!-- Telefone / Celular -->
          <div class="form-group-item">
            <label for="input-phone" class="form-field-label">Telefone / Celular (WhatsApp)</label>
            <div class="input-box-relative">
              <input 
                type="tel" 
                id="input-phone" 
                name="phone"
                class="input-field-custom" 
                value="<?= htmlspecialchars($phone) ?>" 
                placeholder="(11) 98765-4321"
              >
              <input type="hidden" id="input-profile-phone" value="<?= htmlspecialchars($phone) ?>">
            </div>
            <span class="form-field-helper">Utilizado para confirmação em duas etapas e SMS de urgência.</span>
          </div>

          <!-- Cidade / Bairro -->
          <div class="form-group-item">
            <label for="input-city" class="form-field-label">Cidade</label>
            <div class="input-box-relative">
              <input 
                type="text" 
                id="input-city" 
                name="city"
                class="input-field-custom" 
                value="<?= htmlspecialchars($city) ?>" 
              >
              <input type="hidden" id="input-profile-city" value="<?= htmlspecialchars($city) ?>">
            </div>
          </div>

          <!-- Bairro Residencial -->
          <div class="form-group-item">
            <label for="input-address" class="form-field-label">Bairro em Suzano</label>
            <div class="input-box-relative">
              <input 
                type="text" 
                id="input-address" 
                name="bairro"
                class="input-field-custom has-icon-right" 
                value="<?= htmlspecialchars($bairro ?: 'Centro, Suzano - SP') ?>"
              >
              <img src="assets/icons/pin.svg" class="input-icon-trailing" alt="" style="width: 15px; height: 15px;">
            </div>
          </div>

          <div class="address-approved-box">
            <img src="assets/icons/check.svg" alt="" style="width: 15px; height: 15px;">
            <span>Comprovante aprovado junto ao cadastro unificado municipal.</span>
          </div>
        </div>
      </section>

      <!-- SEÇÃO 3: MINHA ACESSIBILIDADE -->
      <section class="profile-card" aria-labelledby="heading-accessibility" style="margin-bottom: 20px;">
        <div class="profile-card-header">
          <div class="card-heading-group">
            <img src="assets/icons/pcd.svg" alt="" aria-hidden="true">
            <h2 class="card-heading-title" id="heading-accessibility">Minha Acessibilidade <span style="font-size: 11px; font-weight: 500; color: #a8a29e;">(Opcional)</span></h2>
          </div>
        </div>

        <div class="info-callout-card">
          <img src="assets/icons/badge.svg" alt="" aria-hidden="true">
          <span>Essas informações são opcionais e ajudam o Passe Livre a sugerir rotas, paradas e itinerários adaptados ao seu deslocamento diário por Suzano.</span>
        </div>

        <div class="pills-selection-grid" role="group" aria-label="Tipos de acessibilidade declarada">
          <?php
          $allCats = [
            'motora'       => 'Deficiência física / motora',
            'visual'       => 'Deficiência visual',
            'auditiva'     => 'Deficiência auditiva',
            'intelectual'  => 'Deficiência intelectual',
            'psicossocial' => 'Deficiência psicossocial',
            'mobilidade'   => 'Mobilidade reduzida',
            'outra'        => 'Outra',
            'nao-informar' => 'Prefiro não informar'
          ];
          foreach ($allCats as $catKey => $catLabel):
            $sel = in_array($catKey, $pcdCategories) ? 'selected' : '';
          ?>
          <button type="button" class="pill-option-btn <?= $sel ?>" data-value="<?= $catKey ?>">
            <span class="pill-checkbox-indicator"></span>
            <span><?= htmlspecialchars($catLabel) ?></span>
          </button>
          <?php endforeach; ?>
        </div>

        <p class="form-field-helper" id="accessibility-empty-state" <?= $hasA11yInfo ? 'hidden' : '' ?>>
          Nenhuma informação de acessibilidade cadastrada ainda. Selecione as opções acima ou descreva sua necessidade abaixo.
        </p>

        <div class="form-group-item" style="margin-top: 14px;">
          <label for="textarea-needs" class="form-field-label">Descreva alguma necessidade específica (opcional)</label>
          <textarea id="textarea-needs" class="textarea-custom" placeholder="Ex.: utilizo cadeira de rodas e preciso de acesso contínuo sem degraus nas travessias."><?= htmlspecialchars($desc) ?></textarea>
          <span class="form-field-helper" style="font-size: 10.5px;">Esse relato nos auxilia a alertar motoristas e fiscais da SPTrans/Suzano para o uso prioritário do elevador do ônibus.</span>
        </div>
      </section>

      <!-- SEÇÃO 4: PREFERÊNCIAS DE ACESSIBILIDADE URBANA -->
      <section class="profile-card" aria-labelledby="heading-urban-prefs" style="margin-bottom: 20px;">
        <div class="profile-card-header">
          <div class="card-heading-group">
            <img src="assets/icons/routes.svg" alt="" aria-hidden="true">
            <h2 class="card-heading-title" id="heading-urban-prefs">Preferências de Acessibilidade Urbana</h2>
          </div>
        </div>

        <p style="font-size: 12px; color: #78716c; margin-top: -6px;">
          Selecione os recursos essenciais para o planejamento inteligente de seus trajetos nos ônibus e terminais municipais.
        </p>

        <div class="preferences-grid-2col" role="group" aria-label="Recursos urbanos desejados">
          <div class="preference-pill-check <?= in_array('escadas', $savedPrefs, true) ? 'active' : '' ?>" data-pref="escadas">
            <span class="pill-checkbox-indicator"></span>
            <span>Prefiro rotas sem escadas</span>
          </div>

          <div class="preference-pill-check <?= in_array('rampas', $savedPrefs, true) ? 'active' : '' ?>" data-pref="rampas">
            <span class="pill-checkbox-indicator"></span>
            <span>Preciso de rampas de acesso</span>
          </div>

          <div class="preference-pill-check <?= in_array('elevadores', $savedPrefs, true) ? 'active' : '' ?>" data-pref="elevadores">
            <span class="pill-checkbox-indicator"></span>
            <span>Preciso de elevadores em estações e passarelas</span>
          </div>

          <div class="preference-pill-check <?= in_array('banheiros', $savedPrefs, true) ? 'active' : '' ?>" data-pref="banheiros">
            <span class="pill-checkbox-indicator"></span>
            <span>Preciso de banheiros acessíveis</span>
          </div>

          <div class="preference-pill-check <?= in_array('tatil', $savedPrefs, true) ? 'active' : '' ?>" data-pref="tatil">
            <span class="pill-checkbox-indicator"></span>
            <span>Preciso de piso tátil</span>
          </div>

          <div class="preference-pill-check <?= in_array('visual', $savedPrefs, true) ? 'active' : '' ?>" data-pref="visual">
            <span class="pill-checkbox-indicator"></span>
            <span>Recursos para deficiência visual</span>
          </div>

          <div class="preference-pill-check <?= in_array('auditiva', $savedPrefs, true) ? 'active' : '' ?>" data-pref="auditiva">
            <span class="pill-checkbox-indicator"></span>
            <span>Recursos para deficiência auditiva</span>
          </div>

          <div class="preference-pill-check <?= in_array('vagas', $savedPrefs, true) ? 'active' : '' ?>" data-pref="vagas">
            <span class="pill-checkbox-indicator"></span>
            <span>Estacionamento acessível</span>
          </div>

          <div class="preference-pill-check <?= in_array('cadeira-integral', $savedPrefs, true) ? 'active' : '' ?> pref-fullwidth-item" data-pref="cadeira-integral">
            <span class="pill-checkbox-indicator"></span>
            <span>Acessibilidade integral para cadeira de rodas (embarque em nível ou plataforma elevatória)</span>
          </div>
        </div>
      </section>

      <!-- SEÇÃO 5: PRIVACIDADE E TRATAMENTO DE DADOS -->
      <section class="profile-card" aria-labelledby="heading-privacy" style="margin-bottom: 20px;">
        <div class="profile-card-header">
          <div class="card-heading-group">
            <img src="assets/icons/shield.svg" alt="" aria-hidden="true">
            <h2 class="card-heading-title" id="heading-privacy">Privacidade e Tratamento de Dados</h2>
          </div>
        </div>

        <div class="privacy-item-row">
          <div class="privacy-checkbox-custom checked" id="check-privacy-routes" tabindex="0" role="checkbox" aria-checked="true"></div>
          <div class="privacy-text-col">
            <label class="privacy-item-title" for="check-privacy-routes">Usar minhas preferências para personalizar rotas e recomendações urbanas</label>
            <span class="privacy-item-desc">Permite que o aplicativo filtre trajetos inacessíveis automaticamente para seu itinerário.</span>
          </div>
        </div>

        <div class="privacy-item-row">
          <div class="privacy-checkbox-custom" id="check-privacy-stats" tabindex="0" role="checkbox" aria-checked="false"></div>
          <div class="privacy-text-col">
            <label class="privacy-item-title" for="check-privacy-stats">Permitir que minhas informações de acessibilidade anônimas sejam utilizadas para melhorias nas linhas municipais</label>
            <span class="privacy-item-desc">Dados estatísticos agregados enviados à Secretaria de Transportes para planejamento de novas calçadas e ônibus adaptados.</span>
          </div>
        </div>

        <div class="privacy-footer-note">
          <div style="display: flex; align-items: center; gap: 6px;">
            <img src="assets/icons/lock.svg" alt="" style="width: 12px; height: 12px;">
            <span>Seus dados pessoais sensíveis são protegidos sob a LGPD e nunca serão exibidos publicamente sem sua autorização expressa.</span>
          </div>
          <a href="privacidade.php" class="privacy-link">Saiba mais sobre privacidade e segurança dos dados ↗</a>
        </div>
      </section>

      <!-- BARRA DE AÇÃO INFERIOR -->
      <div class="bottom-action-bar">
        <div class="last-sync-tag">
          <img src="assets/icons/clock.svg" alt="" style="width: 14px; height: 14px; opacity: 0.7;">
          <span role="status">Sincronizado com o banco MySQL</span>
        </div>

        <div class="action-buttons-wrap">
          <button type="button" class="btn-logout-action" id="btn-profile-logout">
            <img src="assets/icons/logout.svg" alt="" aria-hidden="true">
            <span>Sair da conta</span>
          </button>
        </div>
      </div>
    </form>
  </main>

  <!-- Rodapé -->
  <footer class="site-footer" role="contentinfo">
    <div class="footer-credits">
      &copy; 2026 Passe Livre Suzano. Todos os direitos reservados. Projeto SENAI Suzano.
    </div>
    <div class="footer-links">
      <a href="termos-de-uso.php" class="footer-link-item">Termos de Uso</a>
      <span>&bull;</span>
      <a href="privacidade.php" class="footer-link-item">Privacidade (LGPD)</a>
      <span>&bull;</span>
      <a href="acessibilidade.php" class="footer-link-item">Acessibilidade</a>
      <span>&bull;</span>
      <a href="fale-conosco.php" class="footer-link-item">Fale Conosco</a>
    </div>
  </footer>

  <!-- MODAL DE AJUSTE DE ENQUADRAMENTO -->
  <div class="crop-modal-overlay" id="crop-modal" aria-hidden="true" role="dialog" aria-labelledby="crop-modal-title">
    <div class="crop-modal-card">
      <div class="crop-modal-header">
        <div class="crop-header-title-wrap">
          <div class="crop-header-icon">
            <img src="assets/icons/crop.svg" alt="" aria-hidden="true">
          </div>
          <div>
            <h3 class="crop-modal-title" id="crop-modal-title">Ajuste de Enquadramento</h3>
            <p class="crop-modal-subtitle">Posicione e ajuste o enquadramento do seu rosto para o validador biométrico</p>
          </div>
        </div>
        <button type="button" class="btn-crop-close" id="btn-close-crop-modal" aria-label="Fechar janela de enquadramento">&times;</button>
      </div>

      <div class="crop-modal-body">
        <div class="crop-workspace-area">
          <div class="crop-viewport" id="crop-viewport" title="Arraste para reposicionar ou use o scroll para zoom">
            <canvas id="crop-canvas" width="280" height="280"></canvas>
            <div class="crop-mask-circle">
              <div class="crop-reticle-h"></div>
              <div class="crop-reticle-v"></div>
            </div>
            <span class="crop-drag-hint">Arraste para reposicionar</span>
          </div>

          <!-- Prévia ao vivo -->
          <div class="crop-side-preview">
            <span class="crop-preview-label">No Passe Livre</span>
            <div class="crop-preview-circle">
              <canvas id="crop-mini-preview" width="64" height="64"></canvas>
            </div>
            <span class="crop-preview-badge">Biometria OK</span>
          </div>
        </div>

        <!-- Barra de ferramentas e Zoom -->
        <div class="crop-controls-wrapper">
          <div class="crop-zoom-group">
            <button type="button" class="btn-crop-zoom" id="btn-zoom-out" title="Diminuir zoom">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"/></svg>
            </button>
            <input type="range" id="crop-zoom-range" min="1" max="3" step="0.01" value="1" aria-label="Zoom da foto">
            <button type="button" class="btn-crop-zoom" id="btn-zoom-in" title="Aumentar zoom">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            </button>
            <span class="crop-zoom-value" id="crop-zoom-label">100%</span>
          </div>

          <div class="crop-actions-group">
            <button type="button" class="btn-crop-tool" id="btn-crop-rotate" title="Girar 90°">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.5 2v6h-6M21.34 15.57a10 10 0 1 1-.57-8.38l5.67-5.67"/></svg>
              <span>Girar</span>
            </button>
            <button type="button" class="btn-crop-tool" id="btn-crop-reset" title="Centralizar e redefinir zoom">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"/><path d="M3 21v-5h5"/></svg>
              <span>Centralizar</span>
            </button>
          </div>
        </div>

        <div class="crop-guidance-box">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#b45309" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
          <span>Mantenha os olhos e rosto centralizados para validação facial automática nos ônibus.</span>
        </div>
      </div>

      <div class="crop-modal-footer">
        <button type="button" class="btn-crop-cancel" id="btn-cancel-crop">Cancelar</button>
        <button type="button" class="btn-crop-apply" id="btn-apply-crop">
          <img src="assets/icons/check.svg" alt="" style="width: 14px; height: 14px; filter: brightness(0) invert(1);">
          <span>Confirmar Enquadramento</span>
        </button>
      </div>
    </div>
  </div>

  <!-- Toast -->
  <div class="toast-notice" id="app-toast">
    <img src="assets/icons/check.svg" width="18" height="18" alt="">
    <span id="toast-message">Alterações salvas com sucesso!</span>
  </div>

    <script src="<?= pl_asset_url('js/db.js') ?>"></script>
  <script src="<?= pl_asset_url('js/db-queries.js') ?>"></script>
  <script src="<?= pl_asset_url('js/auth.js') ?>"></script>
  <script src="<?= pl_asset_url('js/perfil.js') ?>"></script>
</body>
</html>
