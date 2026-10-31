<?php
/**
 * Passe Livre Suzano — Sidebar Reutilizável do Painel do Gestor
 *
 * Parâmetros (defina antes de incluir):
 *   $activePage  — string com o nome da página ativa (ex: 'dashboard', 'solicitacoes')
 *   $gestorUser  — array com dados do gestor (saída de auth_user_safe())
 *
 * Uso:
 *   $activePage = 'dashboard';
 *   require __DIR__ . '/includes/sidebar_gestor.php';
 */

$activePage   = $activePage  ?? '';
$gestorUser   = $gestorUser  ?? [];
$gestorNome   = $gestorUser['name']     ?? 'Gestor';
$gestorAvatar = $gestorUser['avatar']   ?? 'assets/images/avatar-admin.jpg';
$gestorInit   = $gestorUser['initials'] ?? 'G';

// Busca o contador real de pendentes do banco para o badge
$pendingCount = 0;
try {
    $db = get_db();
    $stmt = $db->query("SELECT COUNT(*) FROM solicitacoes WHERE status = 'pending'");
    $pendingCount = (int)$stmt->fetchColumn();
} catch (\Throwable $e) {
    $pendingCount = 0;
}

function sidebarItem(string $href, string $icon, string $label, string $active, string $id = '', string $extra = ''): string {
    $isActive = $active === $id ? ' active" aria-current="page' : '';
    $idAttr   = $id ? " id=\"btn-sidebar-{$id}\"" : '';
    return "<li><a href=\"{$href}\" class=\"sidebar-menu-item{$isActive}\"{$idAttr}>
      <img src=\"assets/icons/{$icon}\" alt=\"\" aria-hidden=\"true\">
      <span>{$label}</span>{$extra}
    </a></li>";
}

$badgeCount = $pendingCount > 0 ? "<span class=\"menu-badge-counter\">{$pendingCount}</span>" : '';
$dotNotif   = "<span class=\"menu-dot-indicator\"></span>";
?>
<aside class="gestor-sidebar" role="navigation" aria-label="Menu do Painel do Gestor">
  <div class="sidebar-top-section">
    <div class="gestor-brand-header">
      <a href="index.php" title="Ir para a página inicial">
        <img src="assets/icons/logo.png" alt="Passe Livre">
      </a>
      <div class="gestor-badge-row">
        <span class="gestor-panel-tag">PAINEL DO GESTOR</span>
        <span class="gestor-version-tag">v2.6</span>
      </div>
    </div>

    <ul class="sidebar-menu">
      <?= sidebarItem('dashboard.php',           'dashboard.svg',  'Dashboard',             $activePage, 'dashboard') ?>
      <?= sidebarItem('solicitacoes.php',        'file-text.svg',  'Solicitações',           $activePage, 'solicitacoes', $badgeCount) ?>
      <?= sidebarItem('perfil-gestor.php',       'users.svg',      'Perfil do Cidadão',      $activePage, 'perfil-gestor') ?>
      <?= sidebarItem('locais-cadastrados.php',  'pin.svg',        'Locais cadastrados',     $activePage, 'locais-cadastrados') ?>
      <?= sidebarItem('mapa-gestor.php',         'map-icon.svg',   'Mapa e acessibilidade',  $activePage, 'mapa-gestor') ?>
      <?= sidebarItem('relatorios.php',          'bar-chart.svg',  'Relatórios',             $activePage, 'relatorios') ?>
      <?= sidebarItem('notificacoes.php',        'bell.svg',       'Notificações',           $activePage, 'notificacoes', $dotNotif) ?>
      <?= sidebarItem('auditoria.php',           'history.svg',    'Histórico / Auditoria',  $activePage, 'auditoria') ?>
      <?= sidebarItem('configuracoes.php',       'settings.svg',   'Configurações',          $activePage, 'configuracoes') ?>
      <?php // Visível apenas para o administrador principal; a barra e a API revalidam no banco. ?>
      <?php if ((int)($gestorUser['is_primary_admin'] ?? 0) === 1): ?>
        <?= sidebarItem('gerenciar-administradores.php', 'shield.svg', 'Gerenciar Administradores', $activePage, 'administradores') ?>
      <?php endif; ?>
    </ul>
  </div>

  <!-- Rodapé da Sidebar — dados reais do gestor logado -->
  <div class="sidebar-bottom-user">
    <div class="sidebar-user-info-wrap">
      <div class="sidebar-user-avatar-circle" aria-hidden="true">
        <img src="assets/icons/user.svg" alt="" style="width: 17px; height: 17px; opacity: 0.65;">
      </div>
      <div>
        <div class="sidebar-admin-name"><?= htmlspecialchars($gestorNome) ?></div>
        <div class="sidebar-admin-role"><?= pl_admin_header_role_label() ?></div>
      </div>
    </div>
    <button class="btn-sidebar-logout" title="Encerrar sessão" id="btn-logout">
      <img src="assets/icons/logout.svg" alt="Sair">
    </button>
  </div>
</aside>
