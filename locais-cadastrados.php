<?php
/**
 * Passe Livre Suzano — Locais Cadastrados (Gestor)
 * Proteção server-side: apenas admins/gestores.
 * Conexão direta com banco MySQL via PDO.
 */
require_once __DIR__ . '/includes/auth_guard.php';
$gestorUserRaw = auth_require_admin();
$gestorUser    = auth_user_safe($gestorUserRaw);
$activePage    = 'locais-cadastrados';

// Estatísticas reais do MySQL
require_once __DIR__ . '/api/_db_restore.php';
$db = get_db();
$totalLocais = (int)$db->query("SELECT COUNT(*) FROM locais WHERE is_archived = 0")->fetchColumn();
$fullLocais  = (int)$db->query("SELECT COUNT(*) FROM locais WHERE is_archived = 0 AND access_status IN ('full','verified')")->fetchColumn();
$partLocais  = (int)$db->query("SELECT COUNT(*) FROM locais WHERE is_archived = 0 AND access_status = 'partial'")->fetchColumn();
$critLocais  = (int)$db->query("SELECT COUNT(*) FROM locais WHERE is_archived = 0 AND (access_status = 'critical' OR incidents_count > 0)")->fetchColumn();

$pctFull = $totalLocais > 0 ? round(($fullLocais / $totalLocais) * 100, 1) : 0;
$pctPart = $totalLocais > 0 ? round(($partLocais / $totalLocais) * 100, 1) : 0;
$pctCrit = $totalLocais > 0 ? round(($critLocais / $totalLocais) * 100, 1) : 0;

/* Cobertura real da base. O índice de "integração viária" antigo era um texto
   fixo de Suzano: nenhum cálculo existe por trás dele. */
$stmtCobertura = $db->query("
    SELECT IFNULL(NULLIF(TRIM(municipio), ''), 'Sem município informado') AS municipio,
           COUNT(*) AS total,
           SUM(access_status IN ('full', 'verified')) AS verificados
    FROM locais
    WHERE is_archived = 0
    GROUP BY municipio
    ORDER BY total DESC, municipio ASC
");
$cobertura = $stmtCobertura ? $stmtCobertura->fetchAll() : [];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Locais Cadastrados — Painel do Gestor | Passe Livre Suzano</title>
  <meta name="description" content="Gerenciamento do inventário de locais cadastrados e das classificações de acessibilidade registradas pela equipe.">
  <link rel="stylesheet" href="assets/fonts/fonts.css">
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="css/gestor.css">
  <link rel="stylesheet" href="css/locais-cadastrados.css">
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
              <span>Passe Livre</span> &gt; <span>Locais Cadastrados</span>
            </div>
            <h1 class="gestor-main-heading">Gestão de Locais & Pontos Urbanos</h1>
          </div>
        </div>

        <div class="gestor-header-search-wrap">
          <img src="assets/icons/search.svg" alt="" aria-hidden="true">
          <input type="search" placeholder="Buscar por ID, logradouro..." aria-label="Buscar ocorrências ou logradouro">
        </div>

        <div style="display: flex; align-items: center; gap: 14px;">
          <div class="top-status-online-pill">
            <span class="top-status-dot"></span>
            <span>Suzano Online</span>
          </div>

          <button class="btn-top-bell" id="btn-notify-top" title="Notificações" onclick="window.location.href='notificacoes.php'">
            <img src="assets/icons/bell.svg" alt="Notificações">
            <span class="bell-orange-dot"></span>
          </button>

          <div class="gestor-header-profile-tag" style="cursor:pointer;" onclick="window.location.href='perfil-gestor.php'">
            <div class="gestor-avatar-badge-circle"><?= htmlspecialchars($gestorUser['initials']) ?></div>
            <div class="gestor-admin-tag-text">
              <?= pl_admin_header_profile($gestorUser) ?>
            </div>
          </div>
        </div>
      </header>

      <!-- Conteúdo Interno da Página -->
      <main id="main-content" class="gestor-body-container" style="display: flex; flex-direction: column; gap: 20px; padding: 22px 28px;">
        
        <!-- 1. Hero do Módulo -->
        <section class="locais-hero-bar" aria-label="Apresentação do Módulo de Locais">
          <div class="locais-hero-left">
            <div class="locais-pin-icon-wrap" aria-hidden="true">
              <img src="assets/icons/pin.svg" alt="">
            </div>
            <div class="locais-hero-title-wrap">
              <div class="locais-title-row">
                <h2 class="locais-main-title">Locais Cadastrados</h2>
                <span class="badge-cadastros-count" id="badge-total-locais"><?= $totalLocais ?> cadastros</span>
              </div>
              <p class="locais-hero-desc">
                Mapeamento e gestão de pontos urbanos, edifícios públicos, pontos de ônibus e rotas com parâmetros de acessibilidade em Suzano.
              </p>
            </div>
          </div>

          <div class="locais-hero-actions">
            <a href="mapa-gestor.php" class="btn-locais-action" title="Visualizar inventário no mapa">
              <img src="assets/icons/map-icon.svg" alt="" aria-hidden="true">
              Alternar para Mapa
            </a>

            <button type="button" class="btn-locais-action" id="btn-export-report" title="Exportar dados consolidados">
              <img src="assets/icons/download.svg" alt="" aria-hidden="true">
              Exportar Relatório
            </button>

            <button type="button" class="btn-locais-primary" id="btn-add-new-local" title="Cadastrar novo ponto de interesse">
              <img src="assets/icons/plus.svg" alt="" aria-hidden="true">
              + Adicionar Novo Local
            </button>
          </div>
        </section>

        <!-- 2. Grid de 4 KPIs Dinâmicos do MySQL -->
        <section class="locais-kpi-grid" aria-label="Indicadores gerais de locais">
          
          <!-- KPI 1: Total -->
          <article class="locais-kpi-card">
            <div class="locais-kpi-top">
              <span class="locais-kpi-label">Total de Locais</span>
              <div class="locais-kpi-icon-wrap warm">
                <img src="assets/icons/home.svg" alt="" aria-hidden="true">
              </div>
            </div>
            <div class="locais-kpi-number-row">
              <span class="locais-kpi-number" id="kpi-total-locais"><?= $totalLocais ?></span>
            </div>
            <div class="locais-kpi-subtext green-trend">
              <span>↗</span> Base ativa no MySQL
            </div>
          </article>

          <!-- KPI 2: 100% Acessíveis -->
          <article class="locais-kpi-card">
            <div class="locais-kpi-top">
              <span class="locais-kpi-label">100% Acessíveis</span>
              <div class="locais-kpi-icon-wrap green">
                <img src="assets/icons/check.svg" alt="" aria-hidden="true">
              </div>
            </div>
            <div class="locais-kpi-number-row">
              <span class="locais-kpi-number" id="kpi-full-locais"><?= $fullLocais ?></span>
              <span class="locais-kpi-pill-percent green" id="kpi-pct-full"><?= $pctFull ?>%</span>
            </div>
            <div class="locais-kpi-subtext">
              Rampas, piso tátil e elevadores ativos
            </div>
          </article>

          <!-- KPI 3: Parcialmente Acessíveis -->
          <article class="locais-kpi-card accent-amber">
            <div class="locais-kpi-top">
              <span class="locais-kpi-label">Parcialmente Acessíveis</span>
              <div class="locais-kpi-icon-wrap warm">
                <img src="assets/icons/pcd.png" alt="" aria-hidden="true" style="width: 16px; height: 16px;">
              </div>
            </div>
            <div class="locais-kpi-number-row">
              <span class="locais-kpi-number" id="kpi-part-locais"><?= $partLocais ?></span>
              <span class="locais-kpi-pill-percent amber" id="kpi-pct-part"><?= $pctPart ?>%</span>
            </div>
            <div class="locais-kpi-subtext">
              Requer adequação ou sinal sonoro
            </div>
          </article>

          <!-- KPI 4: Críticos / Ocorrências -->
          <article class="locais-kpi-card accent-red">
            <div class="locais-kpi-top">
              <span class="locais-kpi-label">Problemas / Críticos</span>
              <div class="locais-kpi-icon-wrap red">
                <img src="assets/icons/warning.svg" alt="" aria-hidden="true">
              </div>
            </div>
            <div class="locais-kpi-number-row">
              <span class="locais-kpi-number" id="kpi-crit-locais"><?= $critLocais ?></span>
              <span class="locais-kpi-pill-percent red" id="kpi-pct-crit"><?= $pctCrit ?>%</span>
            </div>
            <div class="locais-kpi-subtext">
              Barreiras físicas ou vistorias pendentes
            </div>
          </article>

        </section>

        <!-- 3. Card de Filtros e Busca -->
        <section class="locais-filters-card" aria-label="Filtros e ordenação da tabela de locais">
          
          <div class="locais-filters-top-row">
            <div class="locais-search-input-wrap">
              <img src="assets/icons/search.svg" class="locais-search-icon" alt="" aria-hidden="true">
              <input type="search" id="locais-search-input" placeholder="Buscar por nome do local, bairro, rua ou CEP..." aria-label="Buscar local">
            </div>

            <select class="locais-category-select" id="locais-category-select" aria-label="Filtrar por categoria">
              <option value="all">Todas as Categorias</option>
              <option value="publico">Prédios Públicos</option>
              <option value="terminal">Terminais & Estações</option>
              <option value="saude">Saúde & Hospitais</option>
              <option value="educacao">Educação & Escolas</option>
              <option value="parque">Vias Públicas & Parques</option>
              <option value="via">Vias Públicas</option>
            </select>

            <button type="button" class="btn-locais-filters-adv" id="btn-advanced-filters">
              <img src="assets/icons/sliders.svg" alt="" aria-hidden="true" style="width: 14px; height: 14px;">
              Filtros Avançados
            </button>

            <div class="view-mode-toggle-group">
              <button type="button" class="view-toggle-btn active" title="Visualização em Lista/Tabela">
                <img src="assets/icons/file-text.svg" alt=""> Tabela
              </button>
              <a href="mapa-gestor.php" class="view-toggle-btn" title="Visualização no Mapa Interativo">
                <img src="assets/icons/map-icon.svg" alt=""> Ver no Mapa
              </a>
            </div>
          </div>

          <!-- Painel Retrátil de Filtros Avançados -->
          <div class="advanced-filters-panel" id="advanced-filters-panel">
            <label class="adv-filter-item">
              <input type="checkbox" checked> Rampa NBR 9050
            </label>
            <label class="adv-filter-item">
              <input type="checkbox" checked> Piso Tátil Direcional/Alerta
            </label>
            <label class="adv-filter-item">
              <input type="checkbox"> Elevador / Plataforma Acessível
            </label>
            <label class="adv-filter-item">
              <input type="checkbox"> Banheiro Adaptado PCD
            </label>
            <label class="adv-filter-item">
              <input type="checkbox"> Vagas Reservadas
            </label>
          </div>

          <!-- Linha Inferior: Pílulas de Status e Ordenação -->
          <div class="locais-filters-bottom-row">
            <div class="locais-status-pills-wrap">
              <span class="locais-status-label">Status:</span>

              <button type="button" class="locais-pill-btn active" data-status="all">
                Todos os Locais <span style="opacity: 0.7;"><?= $totalLocais ?></span>
              </button>

              <button type="button" class="locais-pill-btn" data-status="full">
                <span class="locais-dot green"></span>
                Totalmente Acessível <span style="opacity: 0.7;"><?= $fullLocais ?></span>
              </button>

              <button type="button" class="locais-pill-btn" data-status="partial">
                <span class="locais-dot amber"></span>
                Acesso Parcial <span style="opacity: 0.7;"><?= $partLocais ?></span>
              </button>

              <button type="button" class="locais-pill-btn" data-status="critical">
                <span class="locais-dot red"></span>
                Com Ocorrência / Crítico <span style="opacity: 0.7;"><?= $critLocais ?></span>
              </button>
            </div>

            <div>
              <select class="locais-sort-select" id="locais-sort-select" aria-label="Ordenar resultados">
                <option value="relevance">Ordenado por: Relevância</option>
                <option value="rating">Maior Avaliação</option>
                <option value="rating_asc">Menor Avaliação</option>
                <option value="incidents">Mais Ocorrências</option>
                <option value="name">Nome (A-Z)</option>
              </select>
            </div>
          </div>

        </section>

        <!-- 4. Tabela de Locais Cadastrados -->
        <section class="locais-table-card" aria-label="Tabela de locais municipais cadastrados">
          <div class="locais-table-wrap">
            <table class="locais-table">
              <thead>
                <tr>
                  <th>Nome do Local & Bairro</th>
                  <th>Categoria</th>
                  <th>Endereço Completo</th>
                  <th>Nível de Acessibilidade</th>
                  <th>Avaliação Cidadã</th>
                  <th>Ocorrências</th>
                  <th>Ações</th>
                </tr>
              </thead>
              <tbody id="locais-table-body">
                <!-- Preenchido dinamicamente pelo js/locais-cadastrados.js via API MySQL -->
              </tbody>
            </table>
          </div>

          <!-- Barra de Paginação -->
          <div class="locais-pagination-bar">
            <div id="locais-count-display">Carregando locais do MySQL...</div>

            <nav aria-label="Paginação da tabela">
              <ul class="locais-page-nav-list">
                <li><button type="button" class="locais-page-btn" onclick="showToast('Primeira página.')">&lt;</button></li>
                <li><button type="button" class="locais-page-btn active-circle">1</button></li>
                <li><button type="button" class="locais-page-btn" onclick="showToast('Página 1 ativa.')">&gt;</button></li>
              </ul>
            </nav>
          </div>
        </section>

        <!-- 5. Card de Rotas Acessíveis Conectadas -->
        <section class="connected-routes-banner" aria-label="Conectividade de Rotas Acessíveis">
          <div class="connected-routes-left">
            <div class="routes-icon-badge" aria-hidden="true">
              <img src="assets/icons/routes.svg" alt="">
            </div>
            <div class="connected-routes-texts">
              <div class="connected-routes-title">Rotas Acessíveis Conectadas</div>
              <div class="connected-routes-desc">
                Rotas táteis contínuas interligando pontos de interesse aos corredores de transporte público municipais.
              </div>
            </div>
          </div>

          <button type="button" class="btn-generate-matrix" id="btn-generate-matrix">
            Gerar Matriz de Conectividade
          </button>
        </section>

        <!-- 6. Rodapé do Gestor -->
        <footer class="locais-page-footer">
          <div>
            &copy; 2026 Passe Livre Suzano. Todos os direitos reservados. Projeto SENAI Suzano.
          </div>
          <div class="locais-footer-links">
            <a href="termos-de-uso.php">Termos de Uso</a>
            <a href="privacidade.php">Privacidade</a>
            <a href="ajuda.php">Suporte</a>
          </div>
        </footer>

      </main>
    </div>
  </div>

  <!-- Modal: Adicionar Novo Local -->
  <div class="locais-modal-backdrop" id="modal-novo-local" role="dialog" aria-modal="true" aria-labelledby="modal-novo-title">
    <div class="locais-modal-box">
      <div class="locais-modal-header">
        <h3 class="locais-modal-title" id="modal-novo-title">Cadastrar Novo Local ou Ponto Urbano</h3>
        <button type="button" class="btn-modal-close modal-overlay-close" aria-label="Fechar modal">✕</button>
      </div>

      <form id="form-novo-local">
        <div class="locais-form-group">
          <label for="input-novo-nome">Nome do Local ou Equipamento Público *</label>
          <input type="text" id="input-novo-nome" placeholder="Ex: UBS Jardim Colorado, Terminal Norte..." required>
        </div>

        <div class="locais-form-row">
          <div class="locais-form-group">
            <label for="input-novo-categoria">Categoria *</label>
            <select id="input-novo-categoria" required>
              <option value="publico">Prédios Públicos</option>
              <option value="terminal">Terminais & Estações</option>
              <option value="saude">Saúde & Hospitais</option>
              <option value="educacao">Educação & Escolas</option>
              <option value="parque">Vias Públicas & Parques</option>
              <option value="via">Vias Públicas</option>
            </select>
          </div>

          <div class="locais-form-group">
            <label for="input-novo-nivel">Acessibilidade Inicial *</label>
            <select id="input-novo-nivel" required>
              <option value="unverified" selected>Não verificado (aguardando vistoria)</option>
              <option value="full">100% Acessível (NBR 9050)</option>
              <option value="partial">Parcial (Com restrições)</option>
              <option value="critical">Crítico (Demanda obras)</option>
            </select>
          </div>
        </div>

        <div class="locais-form-group">
          <label for="input-novo-endereco">Endereço Completo *</label>
          <input type="text" id="input-novo-endereco" placeholder="Ex: Av. Governador Mário Covas Jr., 120" required>
        </div>

        <div class="locais-form-row">
          <div class="locais-form-group">
            <label for="input-novo-bairro">Bairro *</label>
            <input type="text" id="input-novo-bairro" placeholder="Ex: Centro, Vila Amorim..." required>
          </div>

          <div class="locais-form-group">
            <label for="input-novo-cep">CEP</label>
            <input type="text" id="input-novo-cep" placeholder="08600-000">
          </div>
        </div>

        <div class="locais-form-group">
          <label>Recursos Existentes no Ponto</label>
          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px; font-size: 11.5px; color: #44403c; margin-top: 4px;">
            <label><input type="checkbox" checked> Rampa suave</label>
            <label><input type="checkbox" checked> Piso tátil direcional</label>
            <label><input type="checkbox"> Banheiro adaptado PCD</label>
            <label><input type="checkbox"> Vaga PCD demarcada</label>
            <label><input type="checkbox"> Elevador / Plataforma</label>
            <label><input type="checkbox"> Sinalização em Braille</label>
          </div>
        </div>

        <div class="locais-modal-footer">
          <button type="button" class="btn-locais-action modal-overlay-close">Cancelar</button>
          <button type="submit" class="btn-locais-primary">Salvar e Publicar Cadastro</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Modal: Detalhes e Laudo do Local -->
  <div class="locais-modal-backdrop" id="modal-detalhes-local" role="dialog" aria-modal="true" aria-labelledby="modal-detalhes-title">
    <div class="locais-modal-box">
      <div class="locais-modal-header">
        <h3 class="locais-modal-title" id="modal-detalhes-title">Ficha Técnica do Local</h3>
        <button type="button" class="btn-modal-close modal-overlay-close" aria-label="Fechar modal">✕</button>
      </div>

      <div id="modal-detalhes-body">
        <!-- Conteúdo inserido via JS -->
      </div>
    </div>
  </div>

  <!-- Modal: Matriz de Conectividade de Rotas -->
  <div class="locais-modal-backdrop" id="modal-matriz-conectividade" role="dialog" aria-modal="true" aria-labelledby="modal-matriz-title">
    <div class="locais-modal-box" style="max-width: 640px;">
      <div class="locais-modal-header">
        <h3 class="locais-modal-title" id="modal-matriz-title">Matriz de Conectividade Viária e Acessibilidade</h3>
        <button type="button" class="btn-modal-close modal-overlay-close" aria-label="Fechar modal">✕</button>
      </div>

      <div>
        <div style="background: #fffbeb; border: 1px solid #fde68a; border-radius: 12px; padding: 14px 18px; margin-bottom: 16px;">
          <div style="font-weight: 800; font-size: 13px; color: #92400e; margin-bottom: 4px;">Cobertura da base por município</div>
          <p style="font-size: 11.5px; color: #78716c; margin: 0; line-height: 1.45;">
            Contagem direta da tabela <code>locais</code> (registros não arquivados). Ligações entre locais, corredores de ônibus e frequência de frota não são calculadas por esta plataforma.
          </p>
        </div>

        <div style="display: flex; flex-direction: column; gap: 10px; font-size: 12px;">
          <?php if ($cobertura): ?>
            <?php foreach ($cobertura as $cba): ?>
              <div style="display: flex; justify-content: space-between; padding: 10px 14px; background: #faf7f2; border-radius: 8px;">
                <span><?= htmlspecialchars($cba['municipio'], ENT_QUOTES, 'UTF-8') ?></span>
                <strong style="color: <?= (int)$cba['verificados'] > 0 ? '#059669' : '#d97706'; ?>">
                  <?= (int)$cba['total'] ?> local<?= (int)$cba['total'] === 1 ? '' : 'ais' ?> · <?= (int)$cba['verificados'] ?> verificado<?= (int)$cba['verificados'] === 1 ? '' : 's' ?>
                </strong>
              </div>
            <?php endforeach; ?>
          <?php else: ?>
            <div style="display: flex; justify-content: space-between; padding: 10px 14px; background: #faf7f2; border-radius: 8px;">
              <span>Nenhum local cadastrado ainda</span>
            </div>
          <?php endif; ?>
        </div>

        <div class="locais-modal-footer">
          <button type="button" class="btn-locais-action modal-overlay-close">Fechar</button>
          <button type="button" class="btn-locais-primary" onclick="showToast('O relatório em PDF desta matriz ainda não está disponível.');">
            Baixar Relatório
          </button>
        </div>
      </div>
    </div>
  </div>

  <div id="app-toast" class="app-toast-alert" role="status" aria-live="polite">
    <span id="toast-message">Notificação</span>
  </div>

    <script src="js/db.js"></script>
  <script src="js/db-queries.js"></script>
  <script src="js/auth.js"></script>
  <script src="js/locais-cadastrados.js"></script>
</body>
</html>
