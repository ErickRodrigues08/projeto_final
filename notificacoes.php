<?php
/**
 * Passe Livre Suzano — Central de Notificações do Gestor
 * Proteção server-side: apenas admins/gestores.
 * Conexão direta com banco MySQL via PDO.
 */
require_once __DIR__ . '/includes/auth_guard.php';
$gestorUserRaw = auth_require_admin();
$gestorUser    = auth_user_safe($gestorUserRaw);
$activePage    = 'notificacoes';
$adminId       = (int)($gestorUserRaw['id'] ?? 0);

require_once __DIR__ . '/api/_db_restore.php';
require_once __DIR__ . '/includes/notificacoes_admin.php';
require_once __DIR__ . '/includes/csrf.php';
$db = get_db();

// Estatísticas reais do histórico administrativo
$contagens = pl_notif_contagens_admin($db, $adminId);

// Notificações recentes (o histórico administrativo usa a leitura do gestor)
$notificacoes = pl_notif_feed_admin($db, $adminId, 25);

// Lista de cidadãos para envio
$stmtUsers = $db->query("SELECT id, name, email, user_code, role FROM usuarios ORDER BY name ASC LIMIT 100");
$cidadaos = $stmtUsers->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Central de Notificações — Painel do Gestor | Passe Livre Suzano</title>
  <meta name="description" content="Gerenciamento de comunicados, laudos e notificações do sistema Passe Livre Suzano.">
  <meta name="csrf-token" content="<?= htmlspecialchars(pl_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
  <link rel="stylesheet" href="assets/fonts/fonts.css">
  <link rel="stylesheet" href="<?= pl_asset_url('css/style.css') ?>">
  <link rel="stylesheet" href="<?= pl_asset_url('css/gestor.css') ?>">
  <link rel="stylesheet" href="<?= pl_asset_url('css/notificacoes.css') ?>">
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
      <!-- Top Bar -->
      <header class="gestor-top-bar">
        <div class="gestor-title-crumb-wrap">
          <button class="btn-gestor-menu-toggle" id="btn-gestor-sidebar-toggle" aria-label="Abrir menu lateral" aria-expanded="false">&#9776;</button>
          <div>
            <div class="gestor-breadcrumb-text">
              <span>Passe Livre</span> / <span style="color: #d97706; font-weight: 600;">Notificações</span>
            </div>
            <h1 class="gestor-main-heading">Central de Notificações e Comunicados</h1>
          </div>
        </div>

        <div class="gestor-header-search-wrap">
          <img src="assets/icons/search.svg" alt="" aria-hidden="true">
          <input type="search" id="notif-search-input" placeholder="Pesquisar mensagens e alertas..." aria-label="Pesquisar mensagens e alertas">
        </div>

        <div style="display: flex; align-items: center; gap: 14px;">
          <div style="display: flex; align-items: center; gap: 6px; background: #ecfdf5; border: 1px solid #a7f3d0; border-radius: 9999px; padding: 4px 12px; font-size: 11px; font-weight: 700; color: #065f46;">
            <span style="width: 7px; height: 7px; border-radius: 50%; background: #10b981; display: inline-block;"></span>
            <span>Suzano Online</span>
          </div>

          <button class="btn-icon-notify" id="btn-top-bell" title="Notificações">
            <img src="assets/icons/bell.svg" alt="">
          </button>

          <div class="gestor-header-profile-tag" style="cursor:pointer;" onclick="window.location.href='perfil-gestor.php'">
            <div class="gestor-avatar-badge-circle"><?= htmlspecialchars($gestorUser['initials']) ?></div>
            <div class="gestor-admin-tag-text">
              <?= pl_admin_header_profile($gestorUser) ?>
            </div>
          </div>
        </div>
      </header>

      <!-- Corpo da Página -->
      <main id="main-content" class="gestor-page-body">

        <!-- Barra de Filtros Rápidos -->
        <div class="notif-filter-bar">
          <div class="notif-filter-pills-group" role="toolbar" aria-label="Filtros de notificações">
            <button type="button" class="notif-pill-btn active" data-filter="all" aria-pressed="true">Todas <span class="notif-pill-count" data-count="total"><?= $contagens['total'] ?></span></button>
            <button type="button" class="notif-pill-btn" data-filter="urgente" aria-pressed="false">Urgentes <span class="notif-pill-count" data-count="urgentes"><?= $contagens['urgentes'] ?></span></button>
            <button type="button" class="notif-pill-btn" data-filter="unread" aria-pressed="false">Não lidas <span class="notif-pill-count" data-count="nao_lidas_admin"><?= $contagens['nao_lidas_admin'] ?></span></button>
          </div>

          <div class="notif-actions-right">
            <button type="button" class="btn-mark-all-read" id="btn-mark-all-read">Marcar todas como lidas</button>
            <button type="button" class="btn-new-broadcast" id="btn-new-broadcast" aria-haspopup="dialog">
              <img src="assets/icons/mail.svg" alt="" style="width: 14px; height: 14px; filter: brightness(0) invert(1);">
              <span>+ Nova Notificação</span>
            </button>
          </div>
        </div>

        <!-- Alerta Crítico -->
        <section class="critical-alert-card" aria-label="Alerta de Gestão">
          <div class="alert-left-content">
            <div class="alert-warning-icon-wrap" aria-hidden="true">⚠️</div>
            <div class="alert-text-col">
              <div class="alert-top-tag-row">
                <span class="alert-tag-red">Aviso Oficial de Gestão</span>
                <span style="color: #d6d3d1;">•</span>
                <span class="alert-system-origin">Sistema Municipal de Mobilidade</span>
              </div>
              <h2 class="alert-main-title">Disparo de Notificações para Cidadãos de Suzano</h2>
              <p class="alert-description-text">
                As mensagens enviadas por este painel são gravadas diretamente no banco de dados MySQL e entregues imediatamente no perfil do usuário no app/web.
              </p>
            </div>
          </div>
        </section>

        <!-- Indicadores compactos do histórico -->
        <section class="notif-metrics-row" aria-label="Métricas de mensagens">
          <div class="metric-row-item">
            <span>Total de Notificações</span>
            <span class="metric-highlight-val" data-count="total"><?= $contagens['total'] ?></span>
          </div>
          <div class="metric-row-item">
            <span>Não lidas por você</span>
            <span class="metric-highlight-val" data-count="nao_lidas_admin"><?= $contagens['nao_lidas_admin'] ?></span>
          </div>
          <div class="metric-row-item">
            <span>Não lidas por cidadãos</span>
            <span class="metric-highlight-val green" data-count="nao_lidas_cidadao"><?= $contagens['nao_lidas_cidadao'] ?></span>
          </div>
          <div class="metric-row-item">
            <span>Alertas Urgentes</span>
            <span class="metric-highlight-val" data-count="urgentes"><?= $contagens['urgentes'] ?></span>
          </div>
        </section>

        <!-- Histórico: elemento principal da página -->
        <div class="notif-feed-column">
          <div class="feed-header-bar">
            <span class="feed-title-label" id="feed-resumo">
              Histórico de Notificações no MySQL (<?= count($notificacoes) ?> de <?= $contagens['total'] ?> exibidas)
            </span>
          </div>

          <div class="notif-items-list" id="notifications-feed">
            <p class="feed-placeholder" id="feed-placeholder">Carregando o histórico do banco de dados…</p>
          </div>

          <p class="feed-limite-nota" hidden id="feed-mais-registros">
            Exibindo as <?= count($notificacoes) ?> mensagens mais recentes do banco.
          </p>
        </div>

      </main>

      <!-- Rodapé -->
      <footer class="site-footer" role="contentinfo" style="margin-top: auto; padding: 16px 32px; background: #fdfaf6; border-top: 1px solid #ede4d8; font-size: 11px; color: #78716c;">
        <div>
          &copy; 2026 Passe Livre Suzano. Todos os direitos reservados. Projeto SENAI Suzano.
        </div>
      </footer>
    </div>
  </div>

  <!-- MODAL: NOVA NOTIFICAÇÃO (abre sobre a própria página, fundo desfocado) -->
  <div class="notif-modal-overlay" id="notif-modal" role="dialog" aria-modal="true" aria-labelledby="heading-broadcast" hidden>
    <section class="notif-modal-card" aria-label="Enviar notificação oficial">
      <header class="broadcast-header-row">
        <div class="broadcast-icon-wrap" aria-hidden="true">
          <img src="assets/icons/mail.svg" alt="">
        </div>
        <div class="broadcast-heading-text">
          <h3 class="broadcast-title" id="heading-broadcast">Enviar Notificação Oficial</h3>
          <span class="broadcast-sub">Gravação em tempo real no MySQL</span>
        </div>
        <button type="button" class="notif-modal-close" id="btn-close-notif-modal" aria-label="Fechar janela de nova notificação">&times;</button>
      </header>

      <form id="form-send-broadcast" class="notif-modal-body" novalidate>
        <!-- Destinatário: cidadão específico -->
        <div class="broadcast-form-field">
          <label class="broadcast-field-label" for="broadcast-dest-select">Destinatário *</label>
          <select id="broadcast-dest-select" class="broadcast-select-field" required>
            <option value="" disabled selected>Selecione um cidadão destinatário...</option>
            <?php foreach ($cidadaos as $cid): ?>
              <option value="<?= (int)$cid['id'] ?>">
                <?= htmlspecialchars($cid['name']) ?> (<?= htmlspecialchars($cid['email']) ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- Tipo / Categoria -->
        <div class="broadcast-form-field">
          <label class="broadcast-field-label" for="broadcast-type-select">Tipo de Notificação</label>
          <select id="broadcast-type-select" class="broadcast-select-field">
            <option value="info">Informativo Geral</option>
            <option value="urgente">Urgente / Alerta Viário</option>
            <option value="laudo">Atualização de Laudo / Solicitação</option>
            <option value="sistema">Comunicado Oficial da Prefeitura</option>
          </select>
        </div>

        <!-- Título -->
        <div class="broadcast-form-field">
          <label class="broadcast-field-label" for="broadcast-title-input">Título da Notificação *</label>
          <input type="text" id="broadcast-title-input" class="broadcast-input-field" maxlength="200" placeholder="Ex: Seu laudo pericial foi aprovado!" required>
        </div>

        <!-- Mensagem -->
        <div class="broadcast-form-field">
          <div class="field-label-with-counter">
            <label class="broadcast-field-label" for="broadcast-body-textarea">Mensagem / Conteúdo *</label>
            <span class="char-counter-span"><span id="broadcast-body-count">0</span>/500</span>
          </div>
          <textarea id="broadcast-body-textarea" class="broadcast-textarea-field" maxlength="500" placeholder="Escreva o texto completo da notificação que aparecerá para o usuário que receberá a mensagem..." required></textarea>
        </div>

        <!-- Link Opcional -->
        <div class="broadcast-form-field">
          <label class="broadcast-field-label" for="broadcast-link-input">Link de Redirecionamento (Opcional)</label>
          <input type="text" id="broadcast-link-input" class="broadcast-input-field" maxlength="250" placeholder="Ex: solicitacao-detalhe.php?id=12">
        </div>

        <p class="broadcast-status" id="broadcast-status" role="alert" hidden></p>

        <div class="broadcast-actions-row">
          <button type="button" class="btn-modal-cancel" id="btn-cancel-broadcast">Cancelar</button>
          <button type="submit" class="btn-send-broadcast-submit" id="btn-submit-notif">
            <img src="assets/icons/mail.svg" alt="" style="width: 14px; height: 14px; filter: brightness(0) invert(1);">
            <span>Confirmar e Gravar no MySQL</span>
          </button>
        </div>
      </form>
    </section>
  </div>

  <!-- Toast -->
  <div class="toast-notice" id="app-toast" role="status" aria-live="polite">
    <span id="toast-message">Operação realizada com sucesso!</span>
  </div>

  <!-- Estado inicial do histórico: renderizado uma única vez por js/notificacoes.js -->
  <script type="application/json" id="notif-feed-inicial">
    <?= json_encode([
        'linhas'    => $notificacoes,
        'contagens' => $contagens,
        'limite'    => 25,
    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>
  </script>
  <script src="<?= pl_asset_url('js/auth.js') ?>"></script>
  <script src="<?= pl_asset_url('js/notificacoes.js') ?>"></script>
</body>
</html>
