<?php
/**
 * Passe Livre Suzano — Perfil do Cidadão / Gestão de Usuários (Gestor)
 * Proteção server-side: apenas admins/gestores.
 * Conexão direta com banco MySQL via PDO.
 */
require_once __DIR__ . '/includes/auth_guard.php';
$gestorUserRaw = auth_require_admin();
$gestorUser    = auth_user_safe($gestorUserRaw);
$activePage    = 'perfil-gestor';

require_once __DIR__ . '/api/_db_restore.php';
$db = get_db();

// Consulta usuários reais no MySQL
$stmt = $db->query("SELECT * FROM usuarios ORDER BY name ASC");
$usuariosDbRows = $stmt->fetchAll();

$mappedUsers = [];
foreach ($usuariosDbRows as $u) {
    $pcd = [];
    if (!empty($u['pcd_categories'])) {
        $pcd = is_array($u['pcd_categories']) ? $u['pcd_categories'] : (json_decode($u['pcd_categories'], true) ?: []);
    }
    $st = strtolower($u['status'] ?? 'ativo');
    $statusTitle = $st === 'ativo' ? 'Ativo' : ($st === 'bloqueado' ? 'Bloqueado' : 'Pendente');
    $birthDate = $u['birth_date'] ?? null;
    $idade = $birthDate ? ((int)date('Y') - (int)date('Y', strtotime($birthDate))) : 34;

    $mappedUsers[] = [
        'id'           => $u['user_code'] ?: ('USR-' . str_pad($u['id'], 3, '0', STR_PAD_LEFT)),
        'dbId'         => (int)$u['id'],
        'nome'         => $u['name'],
        'nomeExibicao' => $u['name'],
        'avatar'       => $u['avatar'] ?: 'assets/images/avatar.jpg',
        'email'        => $u['email'],
        'telefone'     => $u['phone'] ?: '—',
        'cpf'          => '***.***.***-**',
        'nascimento'   => $birthDate,
        'idade'        => $idade,
        'genero'       => $u['gender'] ?: 'Não informado',
        'estadoCivil'  => '—',
        'endereco'     => $u['address'] ?: 'Suzano - SP',
        'bairro'       => $u['bairro'] ?: 'Centro',
        'cidade'       => $u['city'] ?: 'Suzano - SP',
        'cep'          => '08674-000',
        'deficiencia'  => !empty($pcd) ? $pcd[0] : 'Física/Motora',
        'todasDeficiencias' => $pcd,
        'cid'          => 'G80.0',
        'locomocao'    => in_array('motora', $pcd) ? 'Cadeira de Rodas' : 'Autônomo',
        'laudoValido'  => true,
        'laudoArquivo' => null,
        'status'       => $statusTitle,
        'solicitacoes' => 2,
        'avaliacoes'   => 4,
        'membroDesde'  => substr($u['created_at'] ?? '2026-01-01', 0, 10),
        'ultimoAcesso' => 'Hoje'
    ];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Perfil do Cidadão — Painel do Gestor | Passe Livre Suzano</title>
  <meta name="description" content="Gerenciamento de perfis de cidadãos, validação de laudos PcD e acompanhamento de usuários cadastrados no Passe Livre Suzano.">
  <link rel="stylesheet" href="assets/fonts/fonts.css">
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="css/gestor.css">
  <link rel="stylesheet" href="css/perfil-gestor.css">
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
              <span>Passe Livre</span> / <span>Perfil do Cidadão</span>
            </div>
            <h1 class="gestor-main-heading">Perfis dos Cidadãos Cadastrados</h1>
          </div>
        </div>

        <div class="gestor-header-search-wrap">
          <img src="assets/icons/search.svg" alt="" aria-hidden="true">
          <input type="search" id="header-search-quick" placeholder="Busca rápida por nome ou ID..." aria-label="Busca rápida de usuário">
        </div>

        <div style="display:flex;align-items:center;gap:18px;">
          <button class="btn-icon-notify" id="btn-notify-top" title="Notificações" onclick="window.location.href='notificacoes.php'">
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
      <main class="gestor-page-body" id="main-content">

        <!-- KPIs resumidos do MySQL -->
        <section
          id="kpis-usuarios"
          aria-label="Resumo de Usuários"
          style="display:flex;flex-direction:column;gap:14px;"
        >
          <!-- Renderizado dinamicamente por perfil-gestor.js -->
        </section>

        <!-- Barra de busca + botão exportar -->
        <div class="usuarios-toolbar">
          <div class="usuarios-search-wrap">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
            </svg>
            <input
              type="search"
              id="busca-usuarios"
              class="usuarios-search-input"
              placeholder="Buscar por nome, e-mail, CPF ou ID..."
              aria-label="Buscar usuário por nome, e-mail, CPF ou ID"
              autocomplete="off"
            >
          </div>
          <button type="button" class="btn-novo-usuario" id="btn-exportar-lista">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
              <polyline points="7 10 12 15 17 10"/>
              <line x1="12" y1="15" x2="12" y2="3"/>
            </svg>
            Exportar lista
          </button>
        </div>

        <!-- Bloco de filtros -->
        <section class="filtros-bloco" aria-label="Filtros de usuários">
          <div class="filtros-header">
            <span class="filtros-titulo">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <line x1="4" y1="6" x2="20" y2="6"/><line x1="7" y1="12" x2="17" y2="12"/><line x1="10" y1="18" x2="14" y2="18"/>
              </svg>
              Filtros
            </span>
            <button type="button" class="btn-limpar-filtros" id="btn-limpar-filtros">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
              </svg>
              Limpar filtros
            </button>
          </div>

          <div class="filtros-grid">
            <div class="filtro-grupo">
              <label for="filtro-deficiencia" class="filtro-label">Tipo de Deficiência</label>
              <select id="filtro-deficiencia" class="filtro-select" aria-label="Filtrar por tipo de deficiência">
                <option value="">Todas</option>
                <option value="Física/Motora">Física / Motora</option>
                <option value="Visual">Visual</option>
                <option value="Auditiva">Auditiva</option>
                <option value="Intelectual/TEA">Intelectual / TEA</option>
                <option value="Mobilidade Reduzida">Mobilidade Reduzida</option>
              </select>
            </div>

            <div class="filtro-grupo">
              <label for="filtro-genero" class="filtro-label">Gênero</label>
              <select id="filtro-genero" class="filtro-select" aria-label="Filtrar por gênero">
                <option value="">Todos</option>
                <option value="Masculino">Masculino</option>
                <option value="Feminino">Feminino</option>
                <option value="Outro">Outro</option>
              </select>
            </div>

            <div class="filtro-grupo">
              <label for="filtro-status" class="filtro-label">Status da Conta</label>
              <select id="filtro-status" class="filtro-select" aria-label="Filtrar por status">
                <option value="">Todos</option>
                <option value="Ativo">Ativo</option>
                <option value="Pendente">Pendente</option>
                <option value="Bloqueado">Bloqueado</option>
              </select>
            </div>

            <div class="filtro-grupo">
              <label for="filtro-laudo" class="filtro-label">Laudo Médico</label>
              <select id="filtro-laudo" class="filtro-select" aria-label="Filtrar por situação do laudo">
                <option value="">Todos</option>
                <option value="valido">Válido</option>
                <option value="invalido">Vencido / Ausente</option>
              </select>
            </div>

            <div class="filtro-grupo">
              <label class="filtro-label">Faixa Etária</label>
              <div class="filtro-range-wrap">
                <input type="number" id="filtro-idade-min" class="filtro-range-input" placeholder="Mín" min="0" max="120" aria-label="Idade mínima">
                <span class="filtro-range-sep">–</span>
                <input type="number" id="filtro-idade-max" class="filtro-range-input" placeholder="Máx" min="0" max="120" aria-label="Idade máxima">
              </div>
            </div>

            <div class="filtro-grupo">
              <label for="filtro-cidade" class="filtro-label">Cidade / Bairro</label>
              <select id="filtro-cidade" class="filtro-select" aria-label="Filtrar por bairro">
                <option value="">Todos</option>
                <option value="Centro">Centro</option>
                <option value="Boa Vista">Boa Vista</option>
                <option value="Jd. Imperador">Jd. Imperador</option>
                <option value="Palmeiras">Palmeiras</option>
                <option value="Parque Suzano">Parque Suzano</option>
                <option value="Jd. Americana">Jd. Americana</option>
              </select>
            </div>
          </div>

          <div class="filtros-ativos-row" id="filtros-ativos-row" role="list" aria-label="Filtros ativos"></div>
        </section>

        <!-- Barra de resultado + ordenação -->
        <div class="resultado-bar">
          <p class="resultado-count" id="resultado-count" aria-live="polite" aria-atomic="true">
            Carregando usuários do MySQL...
          </p>
          <div class="ordenar-wrap">
            <label for="ordenar-por" style="white-space:nowrap;">Ordenar por:</label>
            <select id="ordenar-por" class="ordenar-select" aria-label="Ordenar lista de usuários">
              <option value="nome-az">Nome (A–Z)</option>
              <option value="nome-za">Nome (Z–A)</option>
              <option value="idade-asc">Idade (menor)</option>
              <option value="idade-desc">Idade (maior)</option>
              <option value="solicitacoes-desc">Mais solicitações</option>
              <option value="membro-recente">Membro mais recente</option>
            </select>
          </div>
        </div>

        <!-- Grid de cards de usuários -->
        <section
          class="usuarios-grid"
          id="usuarios-grid"
          aria-label="Lista de usuários cadastrados"
          aria-live="polite"
        >
          <!-- Renderizado dinamicamente por perfil-gestor.js -->
        </section>

        <!-- Paginação -->
        <div class="paginacao-wrap" id="paginacao-wrap"></div>

      </main>

      <!-- Rodapé -->
      <footer class="site-footer" role="contentinfo" style="margin-top:auto;padding:16px 32px;background:#fdfaf6;border-top:1px solid #ede4d8;font-size:11px;color:#78716c;">
        <div>
          &copy; 2026 Passe Livre Suzano. Todos os direitos reservados. Projeto SENAI Suzano.
        </div>
      </footer>
    </div>
  </div>

  <div class="toast-notice" id="app-toast" role="status" aria-live="polite">
    <span class="toast-icon-wrap" aria-hidden="true"></span>
    <span id="toast-message">Operação realizada com sucesso!</span>
  </div>

  <!-- Usuários reais pré-carregados do MySQL -->
  <script>
    window.USUARIOS_DB = <?= json_encode($mappedUsers, JSON_UNESCAPED_UNICODE) ?>;
  </script>
    <script src="js/db.js"></script>
  <script src="js/db-queries.js"></script>
  <script src="js/auth.js"></script>
  <script src="js/perfil-gestor.js"></script>
</body>
</html>
