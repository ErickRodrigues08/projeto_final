<?php
/**
 * Passe Livre Suzano — Avaliar Local / Fiscalização Cidadã
 * Integração direta com api/avaliacoes.php e MySQL.
 */
require_once __DIR__ . '/includes/auth_guard.php';
$authUser = auth_require();
$userData = auth_user_safe($authUser);

$localId   = (int)($_GET['id'] ?? ($_GET['local_id'] ?? 1));
$localCode = trim($_GET['code'] ?? '');

$db = get_db();
$local = null;
if ($localId > 0) {
    $stmt = $db->prepare("SELECT * FROM locais WHERE id = ? LIMIT 1");
    $stmt->execute([$localId]);
    $local = $stmt->fetch();
} elseif (!empty($localCode)) {
    $stmt = $db->prepare("SELECT * FROM locais WHERE local_code = ? LIMIT 1");
    $stmt->execute([$localCode]);
    $local = $stmt->fetch();
}
if (!$local) {
    $local = $db->query("SELECT * FROM locais WHERE is_archived = 0 ORDER BY id ASC LIMIT 1")->fetch() ?: [
        'id' => 1,
        'local_code' => 'LOC-001',
        'name' => 'Shopping Suzano & Poupatempo',
        'address' => 'Rua Sete de Setembro, 555',
        'bairro' => 'Jardim Iraí'
    ];
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
  <title>Avaliar Acessibilidade de Local — Passe Livre Suzano</title>
  <meta name="description" content="Avalie a acessibilidade de locais públicos e privados em Suzano conforme a norma ABNT NBR 9050.">
  <link rel="stylesheet" href="assets/fonts/fonts.css">
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="css/avaliar-local.css">
  <link rel="icon" type="image/x-icon" href="favicon.ico">
  <link rel="icon" type="image/png" sizes="32x32" href="assets/icons/favicon-32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="assets/icons/favicon-16.png">
  <link rel="apple-touch-icon" href="assets/icons/apple-touch-icon.png">
</head>
<body class="eval-page">
  <a href="#main-content" class="skip-link">Pular para o conteúdo principal</a>

  <!-- Header -->
  <header class="site-header" role="banner">
    <div class="header-left">
      <a href="index.php" class="brand-logo" title="Voltar para a Página Inicial">
        <img src="assets/icons/logo.png" alt="Passe Livre Suzano" width="160" height="38" onerror="this.src='assets/icons/logo.svg'">
      </a>
      <div class="header-search-wrap">
        <img src="assets/icons/search.svg" class="search-icon" alt="" aria-hidden="true">
        <input type="search" id="global-search-input" class="header-search-input" placeholder="Buscar vias, pontos ou linhas..." aria-label="Buscar">
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

  <!-- Palco Principal -->
  <main class="eval-page-main" id="main-content" role="main">
    <header class="eval-hero-header">
      <div class="badge-collab-tag" title="Fiscalização Colaborativa Cidadã">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
        </svg>
        <span>Fiscalização Colaborativa • Suzano / SP</span>
      </div>

      <h1 class="eval-hero-title">Ajude outras pessoas e a nossa comunidade</h1>
      <p class="eval-hero-subtitle">
        Sua avaliação ajuda a manter o mapa de acessibilidade de Suzano sempre atualizado e confiável para pessoas com deficiência e mobilidade reduzida.
      </p>
    </header>

    <form class="eval-card-container" id="form-eval-location" autocomplete="off" data-local-id="<?= (int)$local['id'] ?>" data-local-code="<?= htmlspecialchars($local['local_code'] ?? '') ?>">

      <!-- PASSO 1 -->
      <section class="eval-step-section" aria-labelledby="step-1-title">
        <div class="step-header-row">
          <div class="step-title-group">
            <span class="step-number-badge" aria-hidden="true">1</span>
            <h2 class="step-title-text" id="step-1-title">Qual local você está avaliando?</h2>
          </div>
          <span class="step-meta-right">Passo 1 de 4</span>
        </div>

        <div class="eval-location-box">
          <div class="location-box-top">
            <div class="location-name-wrap">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                <circle cx="12" cy="10" r="3"></circle>
              </svg>
              <span class="location-name-text" id="eval-venue-title"><?= htmlspecialchars($local['name']) ?></span>
            </div>

            <span class="badge-official-point">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                <polyline points="9 12 11 14 15 10"></polyline>
              </svg>
              <span><?= htmlspecialchars($local['access_status_label'] ?? 'Ponto Oficial') ?></span>
            </span>
          </div>

          <div class="location-address-row">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <circle cx="12" cy="10" r="3"></circle>
              <path d="M12 21.7C17.3 17 20 13 20 10a8 8 0 1 0-16 0c0 3 2.7 7 8 11.7z"></path>
            </svg>
            <span id="eval-venue-address"><?= htmlspecialchars($local['address'] . ($local['bairro'] ? ' - ' . $local['bairro'] : '') . ', Suzano - SP') ?></span>
          </div>
        </div>
      </section>

      <div class="eval-step-divider" aria-hidden="true"></div>

      <!-- PASSO 2 -->
      <section class="eval-step-section" aria-labelledby="step-2-title">
        <div class="step-header-row">
          <div class="step-title-group">
            <span class="step-number-badge" aria-hidden="true">2</span>
            <h2 class="step-title-text" id="step-2-title">Como estava a acessibilidade deste local?</h2>
          </div>
          <div class="step-meta-audit-info" title="Os critérios desta avaliação seguem a norma ABNT NBR 9050; sua nota registra o que você observou no local">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <circle cx="12" cy="12" r="10"></circle>
              <line x1="12" y1="16" x2="12" y2="12"></line>
              <line x1="12" y1="8" x2="12.01" y2="8"></line>
            </svg>
            <span>Critérios da NBR 9050</span>
          </div>
        </div>

        <p class="step-subtitle-desc">
          Marque o status real observado em sua visita recente aos pontos do estabelecimento.
        </p>

        <div class="criteria-grid">
          <!-- Entrada -->
          <div class="criteria-card" data-criterion="entrada">
            <div class="criteria-card-header">
              <div class="criteria-icon-box" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M3 21h18"></path><path d="M5 21V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v16"></path><circle cx="15" cy="12" r="1"></circle>
                </svg>
              </div>
              <div class="criteria-text-column">
                <h3 class="criteria-card-title">Entrada e Portas</h3>
                <p class="criteria-card-desc">Sem degraus / portas amplas com abertura facilitada</p>
              </div>
            </div>
            <div class="criteria-options-row" role="radiogroup" aria-label="Status de Entrada e Portas">
              <button type="button" class="btn-criteria-pill active-orange" data-value="disponivel" role="radio" aria-checked="true">Disponível</button>
              <button type="button" class="btn-criteria-pill" data-value="parcial" role="radio" aria-checked="false">Parcial</button>
              <button type="button" class="btn-criteria-pill" data-value="inacessivel" role="radio" aria-checked="false">Inacessível</button>
              <button type="button" class="btn-criteria-pill" data-value="nao-se-aplica" role="radio" aria-checked="false">Não se aplica</button>
            </div>
          </div>

          <!-- Rampas -->
          <div class="criteria-card" data-criterion="rampas">
            <div class="criteria-card-header">
              <div class="criteria-icon-box" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <line x1="3" y1="21" x2="21" y2="21"></line><line x1="3" y1="21" x2="21" y2="9"></line><line x1="21" y1="9" x2="21" y2="21"></line>
                </svg>
              </div>
              <div class="criteria-text-column">
                <h3 class="criteria-card-title">Rampas e Acessos</h3>
                <p class="criteria-card-desc">Inclinação suave conforme NBR 9050 e corrimão duplo</p>
              </div>
            </div>
            <div class="criteria-options-row" role="radiogroup" aria-label="Status de Rampas">
              <button type="button" class="btn-criteria-pill" data-value="disponivel" role="radio" aria-checked="false">Disponível</button>
              <button type="button" class="btn-criteria-pill active-orange" data-value="parcial" role="radio" aria-checked="true">Parcial</button>
              <button type="button" class="btn-criteria-pill" data-value="inacessivel" role="radio" aria-checked="false">Inacessível</button>
              <button type="button" class="btn-criteria-pill" data-value="nao-se-aplica" role="radio" aria-checked="false">Não se aplica</button>
            </div>
          </div>

          <!-- Elevadores -->
          <div class="criteria-card" data-criterion="elevadores">
            <div class="criteria-card-header">
              <div class="criteria-icon-box" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <rect x="4" y="2" width="16" height="20" rx="3"></rect><polyline points="9 8 12 5 15 8"></polyline><polyline points="9 16 12 19 15 16"></polyline>
                </svg>
              </div>
              <div class="criteria-text-column">
                <h3 class="criteria-card-title">Elevadores</h3>
                <p class="criteria-card-desc">Sinalização sonora, voz e botões em Braille</p>
              </div>
            </div>
            <div class="criteria-options-row" role="radiogroup" aria-label="Status de Elevadores">
              <button type="button" class="btn-criteria-pill active-orange" data-value="disponivel" role="radio" aria-checked="true">Disponível</button>
              <button type="button" class="btn-criteria-pill" data-value="parcial" role="radio" aria-checked="false">Parcial</button>
              <button type="button" class="btn-criteria-pill" data-value="inacessivel" role="radio" aria-checked="false">Inacessível</button>
              <button type="button" class="btn-criteria-pill" data-value="nao-se-aplica" role="radio" aria-checked="false">Não se aplica</button>
            </div>
          </div>

          <!-- Banheiros -->
          <div class="criteria-card" data-criterion="banheiros">
            <div class="criteria-card-header">
              <div class="criteria-icon-box" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <circle cx="12" cy="4" r="2"></circle><path d="M9 20v-6H7l4-8 4 8h-2v6"></path>
                </svg>
              </div>
              <div class="criteria-text-column">
                <h3 class="criteria-card-title">Banheiros Adaptados</h3>
                <p class="criteria-card-desc">Barras de apoio fixadas e espaço de giro para cadeira de rodas</p>
              </div>
            </div>
            <div class="criteria-options-row" role="radiogroup" aria-label="Status de Banheiros">
              <button type="button" class="btn-criteria-pill active-orange" data-value="disponivel" role="radio" aria-checked="true">Disponível</button>
              <button type="button" class="btn-criteria-pill" data-value="parcial" role="radio" aria-checked="false">Parcial</button>
              <button type="button" class="btn-criteria-pill" data-value="inacessivel" role="radio" aria-checked="false">Inacessível</button>
              <button type="button" class="btn-criteria-pill" data-value="nao-se-aplica" role="radio" aria-checked="false">Não se aplica</button>
            </div>
          </div>

          <!-- Calçadas -->
          <div class="criteria-card" data-criterion="calcadas">
            <div class="criteria-card-header">
              <div class="criteria-icon-box" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <circle cx="12" cy="5" r="2"></circle><path d="M10 22v-7l-2-2 3-5 3 4v10"></path>
                </svg>
              </div>
              <div class="criteria-text-column">
                <h3 class="criteria-card-title">Calçadas do Entorno</h3>
                <p class="criteria-card-desc">Piso tátil direcional/alerta contínuo e piso sem desníveis</p>
              </div>
            </div>
            <div class="criteria-options-row" role="radiogroup" aria-label="Status de Calçadas">
              <button type="button" class="btn-criteria-pill active-orange" data-value="disponivel" role="radio" aria-checked="true">Disponível</button>
              <button type="button" class="btn-criteria-pill" data-value="parcial" role="radio" aria-checked="false">Parcial</button>
              <button type="button" class="btn-criteria-pill" data-value="inacessivel" role="radio" aria-checked="false">Inacessível</button>
              <button type="button" class="btn-criteria-pill" data-value="nao-se-aplica" role="radio" aria-checked="false">Não se aplica</button>
            </div>
          </div>

          <!-- Estacionamento -->
          <div class="criteria-card" data-criterion="estacionamento">
            <div class="criteria-card-header">
              <div class="criteria-icon-box" aria-hidden="true">
                <span style="font-weight:800;font-size:18px;">P</span>
              </div>
              <div class="criteria-text-column">
                <h3 class="criteria-card-title">Estacionamento / Vagas PcD</h3>
                <p class="criteria-card-desc">Sinalização nítida e proximidade da entrada</p>
              </div>
            </div>
            <div class="criteria-options-row" role="radiogroup" aria-label="Status de Estacionamento">
              <button type="button" class="btn-criteria-pill active-orange" data-value="disponivel" role="radio" aria-checked="true">Disponível</button>
              <button type="button" class="btn-criteria-pill" data-value="parcial" role="radio" aria-checked="false">Parcial</button>
              <button type="button" class="btn-criteria-pill" data-value="inacessivel" role="radio" aria-checked="false">Inacessível</button>
              <button type="button" class="btn-criteria-pill" data-value="nao-se-aplica" role="radio" aria-checked="false">Não se aplica</button>
            </div>
          </div>
        </div>

        <div class="review-general-box">
          <div class="review-rating-row">
            <span class="review-rating-label" id="review-rating-label">Sua nota geral para este local</span>
            <div class="review-stars-input" role="radiogroup" aria-labelledby="review-rating-label">
              <?php for ($star = 1; $star <= 5; $star++): ?>
                <button type="button" class="btn-star-input active" data-value="<?= $star ?>" role="radio" aria-checked="<?= $star === 5 ? 'true' : 'false' ?>" aria-label="<?= $star ?> estrela<?= $star > 1 ? 's' : '' ?>">★</button>
              <?php endfor; ?>
            </div>
            <input type="hidden" id="review-rating-value" name="rating" value="5">
          </div>

          <label class="review-comment-label" for="review-comment">Comentário da sua avaliação (opcional)</label>
          <textarea
            id="review-comment"
            class="barrier-textarea"
            maxlength="1000"
            rows="3"
            placeholder="Conte como foi sua experiência de acessibilidade neste local..."
            aria-label="Comentário da avaliação"
          ></textarea>

          <div class="barrier-box-footer">
            <span class="barrier-footer-hint">Seu comentário ficará visível para a comunidade.</span>
            <span class="barrier-char-counter"><span id="review-comment-chars-count">0</span> / 1000 caracteres</span>
          </div>
        </div>
      </section>

      <div class="eval-step-divider" aria-hidden="true"></div>

      <!-- PASSO 3 -->
      <section class="eval-step-section" aria-labelledby="step-3-title">
        <div class="step-header-row">
          <div class="step-title-group">
            <span class="step-number-badge" aria-hidden="true">3</span>
            <h2 class="step-title-text" id="step-3-title">Relatar uma barreira ou obstáculo temporário</h2>
          </div>
          <label class="toggle-switch-wrap" title="Ativar ou desativar o relato de barreira temporária">
            <input type="checkbox" id="toggle-barrier" checked aria-label="Ativar relato de barreira temporária">
            <span class="toggle-slider" aria-hidden="true"></span>
          </label>
        </div>

        <div class="barrier-reporting-box" id="barrier-box">
          <div class="barrier-box-header">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
              <line x1="12" y1="9" x2="12" y2="13"></line>
              <line x1="12" y1="17" x2="12.01" y2="17"></line>
            </svg>
            <span>Descreva a restrição encontrada no momento</span>
          </div>

          <textarea 
            id="barrier-description" 
            class="barrier-textarea" 
            maxlength="500" 
            rows="3" 
            placeholder="Descreva detalhes como obras, desníveis, degraus ou elevadores inoperantes..."
            aria-label="Descrição da barreira"
          ></textarea>

          <div class="barrier-box-footer">
            <span class="barrier-footer-hint">Seja específico para orientar os cidadãos em trânsito imediato.</span>
            <span class="barrier-char-counter"><span id="barrier-chars-count">0</span> / 500 caracteres</span>
          </div>
        </div>
      </section>

      <div class="eval-step-divider" aria-hidden="true"></div>

      <!-- PASSO 4 -->
      <section class="eval-step-section" aria-labelledby="step-4-title">
        <div class="step-header-row">
          <div class="step-title-group">
            <span class="step-number-badge" aria-hidden="true">4</span>
            <h2 class="step-title-text" id="step-4-title">Adicionar fotos comprobatórias</h2>
          </div>
          <span class="badge-senai-validation" title="Validação técnica pelo SENAI Suzano">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
              <polyline points="9 12 11 14 15 10"></polyline>
            </svg>
            <span>Validação por docentes e alunos do SENAI Suzano</span>
          </span>
        </div>

        <div class="photo-dropzone-box" id="photo-dropzone" tabindex="0" role="button" aria-label="Área para arrastar fotos ou clicar para selecionar">
          <div class="dropzone-camera-icon-wrap" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path>
              <circle cx="12" cy="13" r="4"></circle>
            </svg>
          </div>

          <p class="dropzone-primary-text">
            Arraste suas fotos aqui ou <span class="dropzone-browse-link" id="btn-browse-photos">clique para selecionar</span>
          </p>
          <p class="dropzone-secondary-text">
            Envie imagens claras das rampas, pisos em braille, entradas ou calçadas (JPG/PNG até 10MB).
          </p>

          <input type="file" id="file-input-photos" accept="image/png, image/jpeg, image/webp" multiple hidden>
        </div>

        <div class="uploaded-photos-container" id="uploaded-photos-list"></div>
      </section>

      <div class="eval-submit-section">
        <button type="submit" class="btn-submit-review" id="btn-submit-form">
          <svg viewBox="0 0 24 24" fill="currentColor" stroke="none" aria-hidden="true">
            <polygon points="5 3 19 12 5 21 5 3"></polygon>
          </svg>
          <span>Enviar contribuição e atualizar mapa</span>
        </button>

        <div class="submit-security-note">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
          </svg>
          <span>Seus dados são protegidos de acordo com a LGPD e auditados pelo SENAI Suzano.</span>
        </div>
      </div>

    </form>
  </main>

  <!-- Modal Sucesso -->
  <div class="eval-success-modal-overlay" id="success-modal" role="dialog" aria-modal="true" aria-labelledby="modal-success-title" style="display:none;" hidden>
    <div class="eval-success-modal-card">
      <div class="modal-icon-success">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
          <polyline points="20 6 9 17 4 12"></polyline>
        </svg>
      </div>

      <h2 class="modal-success-title" id="modal-success-title">Contribuição Enviada!</h2>
      <p class="modal-success-desc">
        Sua avaliação de <strong><?= htmlspecialchars($local['name']) ?></strong> foi registrada com sucesso no banco de dados e já está disponível para a comunidade.
      </p>

      <div class="modal-success-actions">
        <a href="detalhes-local.php?id=<?= (int)$local['id'] ?>" class="btn-modal-primary" id="btn-modal-return-nav">
          <span>Ver Detalhes do Local</span>
        </a>
        <a href="lugares-encontrados.php" class="btn-modal-secondary" id="btn-modal-explore">
          <span>Ver Mais Locais em Suzano</span>
        </a>
      </div>
    </div>
  </div>

  <footer class="site-footer" role="contentinfo">
    <div class="footer-credits">
      &copy; 2026 Passe Livre Suzano. Todos os direitos reservados. Projeto SENAI Suzano.
    </div>
    <nav class="footer-nav-links" aria-label="Links institucionais">
      <a href="termos-de-uso.php" class="footer-link-item">Termos de Uso</a>
      <span aria-hidden="true">&bull;</span>
      <a href="privacidade.php" class="footer-link-item">Privacidade (LGPD)</a>
      <span aria-hidden="true">&bull;</span>
      <a href="acessibilidade.php" class="footer-link-item">Acessibilidade</a>
      <span aria-hidden="true">&bull;</span>
      <a href="fale-conosco.php" class="footer-link-item">Fale Conosco</a>
    </nav>
  </footer>

    <script src="js/db.js"></script>
  <script src="js/db-queries.js"></script>
  <script src="js/auth.js"></script>
  <script src="js/avaliar-local.js"></script>
</body>
</html>
