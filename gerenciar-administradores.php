<?php
/**
 * Passe Livre Suzano — Gerenciar Administradores (Painel do Gestor)
 *
 * Proteção server-side: auth_require_primary_admin() lê `usuarios.is_admin` e
 * `usuarios.is_primary_admin` no banco a cada requisição. Nada aqui depende de
 * nome de usuário, e-mail fixo, parâmetro da URL ou variável do navegador.
 */
require_once __DIR__ . '/includes/auth_guard.php';
require_once __DIR__ . '/includes/csrf.php';

$gestorUserRaw = auth_require_primary_admin();
$gestorUser    = auth_user_safe($gestorUserRaw);
$activePage    = 'administradores';
$csrfToken     = pl_csrf_token();

$db = get_db();
$totais = $db->query("
    SELECT
      COUNT(*)                                   AS usuarios,
      SUM(is_admin = 1)                          AS administradores,
      SUM(is_admin = 1 AND is_primary_admin = 1) AS principais,
      SUM(is_admin = 0)                          AS cidadaos,
      SUM(status <> 'ativo')                     AS inativos
    FROM usuarios
")->fetch();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Gerenciar Administradores — Painel do Gestor | Passe Livre</title>
  <meta name="description" content="Concessão e remoção de acesso administrativo às contas reais do Passe Livre Suzano, com registro em auditoria.">
  <meta name="csrf-token" content="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
  <link rel="stylesheet" href="assets/fonts/fonts.css">
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="css/gestor.css">
  <link rel="stylesheet" href="css/gerenciar-administradores.css">
  <link rel="icon" type="image/x-icon" href="favicon.ico">
  <link rel="icon" type="image/png" sizes="32x32" href="assets/icons/favicon-32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="assets/icons/favicon-16.png">
  <link rel="apple-touch-icon" href="assets/icons/apple-touch-icon.png">
</head>
<body>
  <a href="#main-content" class="skip-link">Pular para o conteúdo principal</a>
  <div class="gestor-layout-wrapper">
    <?php require __DIR__ . '/includes/sidebar_gestor.php'; ?>
    <div class="gestor-content-area">
      <header class="gestor-top-bar">
        <div class="gestor-title-crumb-wrap">
          <button class="btn-gestor-menu-toggle" id="btn-gestor-sidebar-toggle" aria-label="Abrir menu lateral" aria-expanded="false">&#9776;</button>
          <div>
            <div class="gestor-breadcrumb-text"><span>Painel do Gestor</span> &gt; <span>Gerenciar Administradores</span></div>
            <h1 class="gestor-main-heading">Gerenciamento de Administradores</h1>
          </div>
        </div>

        <div style="display:flex;align-items:center;gap:18px;">
          <div style="display:inline-flex;align-items:center;gap:6px;background:#fff7ed;border:1px solid #fed7aa;border-radius:9999px;padding:4px 12px;font-size:11px;font-weight:700;color:#9a3412;">
            <img src="assets/icons/lock.svg" alt="" aria-hidden="true" style="width:12px;height:12px;">
            Área restrita ao administrador principal
          </div>
          <button class="btn-icon-notify" id="btn-notify-top" title="Notificações" onclick="window.location.href='notificacoes.php'">
            <img src="assets/icons/bell.svg" alt="">
          </button>
          <div class="gestor-header-profile-tag" style="cursor:pointer;" onclick="window.location.href='perfil-gestor.php'">
            <div class="gestor-avatar-badge-circle"><?= $gestorUser['initials'] ?></div>
            <div class="gestor-admin-tag-text">
              <?= pl_admin_header_profile($gestorUser) ?>
            </div>
          </div>
        </div>
      </header>

      <main id="main-content" class="gestor-page-body">

        <div class="admins-denied-card" id="admins-denied" role="alert">
          Seu acesso a esta tela foi encerrado: apenas o administrador principal gerencia níveis de acesso.
          As alterações abaixo não foram aplicadas.
        </div>

        <!-- 1. Como funciona este módulo -->
        <section class="admins-intro-card" aria-labelledby="heading-admins-intro">
          <div class="admins-intro-left">
            <div class="admins-intro-icon">
              <img src="assets/icons/shield-check.svg" alt="" aria-hidden="true">
            </div>
            <div>
              <h2 class="admins-intro-title" id="heading-admins-intro">Três níveis de acesso reais</h2>
              <p class="admins-intro-text">
                <strong>Usuário comum</strong> usa o Passe Livre normalmente.
                <strong>Administrador</strong> entra no Painel do Gestor.
                <strong>Administrador principal</strong> é a única conta que concede e remove acesso — e ela é protegida
                pela flag <code>is_primary_admin</code> no banco, não por nome ou e-mail.
                Conceder acesso não cria uma nova conta: a pessoa continua com o mesmo e-mail, senha e perfil,
                e o novo nível passa a valer no próximo login dela. Toda ação fica no Histórico / Auditoria.
              </p>
            </div>
          </div>
          <div class="admins-intro-chip">
            <img src="assets/icons/users.svg" alt="" aria-hidden="true" style="width:13px;height:13px;">
            Você está autenticado como administrador principal
          </div>
        </section>

        <!-- 2. Resumo dos níveis -->
        <section class="admins-summary-grid" aria-label="Resumo dos níveis de acesso">
          <div class="admins-summary-card">
            <span class="admins-summary-label">Contas cadastradas</span>
            <span class="admins-summary-value" id="adm-total"><?= (int)$totais['usuarios'] ?></span>
            <span class="admins-summary-note">Usuários reais da tabela de contas</span>
          </div>
          <div class="admins-summary-card">
            <span class="admins-summary-label">Administradores</span>
            <span class="admins-summary-value accent" id="adm-admins"><?= (int)$totais['administradores'] ?></span>
            <span class="admins-summary-note">Contas que entram no Painel do Gestor</span>
          </div>
          <div class="admins-summary-card">
            <span class="admins-summary-label">Administrador principal</span>
            <span class="admins-summary-value blue" id="adm-primary"><?= (int)$totais['principais'] ?></span>
            <span class="admins-summary-note">Conta protegida, não editável nesta tela</span>
          </div>
          <div class="admins-summary-card">
            <span class="admins-summary-label">Usuários comuns</span>
            <span class="admins-summary-value muted" id="adm-citizens"><?= (int)$totais['cidadaos'] ?></span>
            <span class="admins-summary-note"><?= (int)$totais['inativos'] ?> conta(s) inativa(s) sem acesso</span>
          </div>
        </section>

        <!-- 3. Busca e filtro -->
        <section class="gestor-section-card" style="margin-top:20px;" aria-labelledby="heading-admins-list">
          <div class="section-card-header">
            <div class="section-card-header-left">
              <img src="assets/icons/shield.svg" alt="" aria-hidden="true">
              <h2 class="section-title" id="heading-admins-list">Contas e níveis de acesso</h2>
            </div>
            <span class="badge-sha256-seal">
              <img src="assets/icons/lock.svg" alt="" style="width:12px;height:12px;">
              Gravação confirmada no banco
            </span>
          </div>

          <div class="admins-toolbar">
            <div class="admins-field">
              <label for="admin-search">Buscar por nome, e-mail ou código</label>
              <input type="search" class="admins-input" id="admin-search" placeholder="Ex.: Beatriz, beatriz@exemplo.com, USR-2026" autocomplete="off">
            </div>

            <div class="admins-field">
              <label for="admin-level-filter">Nível de acesso</label>
              <select class="admins-select" id="admin-level-filter">
                <option value="all" selected>Todos os níveis</option>
                <option value="principal">Administrador principal</option>
                <option value="admin">Administradores</option>
                <option value="cidadao">Usuários comuns</option>
              </select>
            </div>

            <button type="button" class="admins-btn" id="btn-admin-refresh">
              <img src="assets/icons/refresh-cw.svg" alt="" aria-hidden="true">
              Atualizar lista
            </button>
          </div>

          <div class="admins-table-wrap">
            <table class="admins-table">
              <thead>
                <tr>
                  <th scope="col">CONTA</th>
                  <th scope="col">RÓTULO INSTITUCIONAL</th>
                  <th scope="col">NÍVEL DE ACESSO</th>
                  <th scope="col">STATUS</th>
                  <th scope="col">AÇÕES PERMITIDAS</th>
                </tr>
              </thead>
              <tbody id="admins-tbody">
                <tr class="admins-state-row"><td colspan="5">Carregando contas do banco...</td></tr>
              </tbody>
            </table>
          </div>

          <div class="admins-pagination" id="admins-pagination">
            <span id="admins-results-count">Carregando...</span>
            <div class="admins-pagination-nav">
              <button type="button" class="admins-btn" id="btn-admin-prev">&#8592; Anterior</button>
              <span id="admin-page-label">Página 1</span>
              <button type="button" class="admins-btn" id="btn-admin-next">Próxima &#8594;</button>
            </div>
          </div>
        </section>

        <section class="retention-policy-card" aria-labelledby="heading-admins-policy" style="margin-top:20px;">
          <div class="retention-left-info">
            <h3 class="section-title" id="heading-admins-policy">Regras desta tela</h3>
            <p>
              Um administrador comum não tem acesso a nada aqui: a concessão de nível é exclusiva do administrador
              principal, e ninguém altera o próprio nível. O administrador principal é uma conta protegida e não
              pode ser rebaixada por esta interface. Requisições forjadas são recusadas no servidor antes de
              qualquer gravação, com verificação de token de segurança e método POST.
            </p>
          </div>
          <div class="crypto-seal-card">
            <img src="assets/icons/history.svg" alt="" aria-hidden="true">
            <div>
              <strong>Cada alteração gera um registro</strong>
              <span>Consulte em Histórico / Auditoria, módulo Administradores.</span>
            </div>
          </div>
        </section>

      </main>

      <footer class="site-footer" role="contentinfo" style="margin-top:auto;padding:16px 32px;background:#fdfaf6;border-top:1px solid #ede4d8;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;font-size:11px;color:#78716c;">
        <div>&copy; 2026 Passe Livre. Projeto SENAI Suzano.</div>
        <div style="display:flex;gap:14px;">
          <a href="termos-de-uso.php" style="color:#78716c;text-decoration:none;">Termos de Uso</a>
          <span>&bull;</span>
          <a href="privacidade.php" style="color:#78716c;text-decoration:none;">Privacidade (LGPD)</a>
          <span>&bull;</span>
          <a href="acessibilidade.php" style="color:#78716c;text-decoration:none;">Acessibilidade</a>
        </div>
      </footer>
    </div>
  </div>

  <!-- Modal de confirmação de alteração de acesso -->
  <div class="admin-confirm-overlay" id="modal-admin-confirm" role="dialog" aria-modal="true" aria-labelledby="confirm-title">
    <div class="admin-confirm-window">
      <h3 class="admin-confirm-title" id="confirm-title">Confirmar alteração de acesso</h3>
      <p class="admin-confirm-body" id="confirm-body"></p>
      <div class="admin-confirm-target">
        <div><strong>Conta:</strong> <span id="confirm-user-name"></span></div>
        <div><strong>E-mail:</strong> <span id="confirm-user-mail"></span></div>
        <div><strong>Código:</strong> <span id="confirm-user-code"></span></div>
        <div><strong>Nível atual:</strong> <span id="confirm-user-level"></span></div>
        <div><strong>Nível depois:</strong> <span id="confirm-user-newlevel"></span></div>
      </div>
      <div class="admin-confirm-warning">
        <img src="assets/icons/warning.svg" alt="" aria-hidden="true">
        <span id="confirm-warning-text">Esta alteração é registrada no histórico de auditoria e vale a partir do próximo login da conta.</span>
      </div>
      <div class="admin-confirm-actions">
        <button type="button" class="btn-confirm-cancel" id="btn-confirm-cancel">Cancelar</button>
        <button type="button" class="btn-confirm-save" id="btn-confirm-admin">Confirmar</button>
      </div>
    </div>
  </div>

  <div class="toast-notice" id="app-toast" role="status" aria-live="polite">
    <span id="toast-message"></span>
  </div>

  <script src="js/auth.js"></script>
  <script src="js/gerenciar-administradores.js"></script>
</body>
</html>
