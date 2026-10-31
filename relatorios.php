<?php
/**
 * Passe Livre Suzano — Relatórios e Análises (Gestor)
 * Proteção server-side: apenas admins/gestores.
 * Conexão direta com banco MySQL via PDO.
 */
require_once __DIR__ . '/includes/auth_guard.php';
$gestorUserRaw = auth_require_admin();
$gestorUser    = auth_user_safe($gestorUserRaw);
$activePage    = 'relatorios';

require_once __DIR__ . '/api/_db_restore.php';
$db = get_db();

$totalSolicitacoes = (int)$db->query("SELECT COUNT(*) FROM solicitacoes")->fetchColumn();
$deferidas         = (int)$db->query("SELECT COUNT(*) FROM solicitacoes WHERE status = 'approved'")->fetchColumn();
$taxaDeferimento   = $totalSolicitacoes > 0 ? round(($deferidas / $totalSolicitacoes) * 100, 1) : 0;
$totalLocais       = (int)$db->query("SELECT COUNT(*) FROM locais WHERE is_archived = 0")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Relatórios e Análises — Painel do Gestor | Passe Livre Suzano</title>
  <meta name="description" content="Métricas consolidadas, volume de solicitações, diagnósticos de acessibilidade e auditoria executiva de Suzano.">
  <link rel="stylesheet" href="assets/fonts/fonts.css">
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="css/gestor.css">
  <link rel="stylesheet" href="css/relatorios.css">
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
              <span>Passe Livre</span> &gt; <span>Relatórios</span>
            </div>
            <h1 class="gestor-main-heading">Relatórios e Análises</h1>
          </div>
        </div>

        <div class="gestor-header-search-wrap">
          <img src="assets/icons/search.svg" alt="" aria-hidden="true">
          <input type="search" placeholder="Buscar por ID, relatório..." aria-label="Buscar relatórios">
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
        
        <!-- 1. Barra de Filtro Geral do Período e Ações de Exportação -->
        <section class="relatorios-filter-bar-card" aria-label="Barra de filtros e ações de emissão">
          <div class="relatorios-filter-left">
            <span class="relatorios-filter-label">
              <img src="assets/icons/calendar.svg" alt="" aria-hidden="true" style="width: 14px; height: 14px; opacity: 0.7;">
              Filtro Geral:
            </span>

            <select class="relatorios-select" id="filter-period-select" aria-label="Selecione o período">
              <option value="30" selected>Últimos 30 dias</option>
              <option value="60">Últimos 60 dias</option>
              <option value="90">Últimos 90 dias</option>
              <option value="ano">Ano Completo</option>
            </select>

            <select class="relatorios-select" id="filter-modality-select" aria-label="Selecione a modalidade">
              <option value="all">Todas as Modalidades</option>
              <option value="onibus">Transporte Coletivo (Ônibus)</option>
              <option value="van">Transporte Especial (Van Acessível)</option>
              <option value="calcadas">Vias & Calçadas Acessíveis</option>
            </select>

            <select class="relatorios-select" id="filter-category-select" aria-label="Selecione a categoria">
              <option value="all">Todas as Categorias</option>
              <option value="solicitacoes">Solicitações de Gratuidade PCD</option>
              <option value="renovacao">Renovação de Carteirinha</option>
              <option value="barreiras">Ocorrências de Barreiras Urbanas</option>
            </select>
          </div>

          <div class="relatorios-filter-right">
            <button type="button" class="btn-relatorio-action" id="btn-export-quick" title="Exportar compilação rápida">
              <img src="assets/icons/download.svg" alt="" aria-hidden="true">
              Exportar PDF / Excel
            </button>

            <button type="button" class="btn-relatorio-primary" id="btn-new-report" title="Gerar relatório personalizado">
              <img src="assets/icons/plus.svg" alt="" aria-hidden="true">
              + Gerar Novo Relatório
            </button>
          </div>
        </section>

        <!-- 2. Grid de 4 Indicadores Executivos (KPIs) Reais do MySQL -->
        <section class="relatorios-kpi-grid" aria-label="Indicadores executivos consolidados">
          
          <article class="relatorios-kpi-card">
            <div class="relatorios-kpi-top">
              <span class="relatorios-kpi-label">Total de Solicitações</span>
              <div class="relatorios-kpi-icon-wrap warm">
                <img src="assets/icons/file-text.svg" alt="" aria-hidden="true">
              </div>
            </div>
            <div class="relatorios-kpi-number-row">
              <span class="relatorios-kpi-number" id="kpi-total-solicitacoes"><?= $totalSolicitacoes ?></span>
            </div>
            <div class="relatorios-kpi-subtext trend-green">
              <span>↗</span> Registradas no MySQL
            </div>
          </article>

          <article class="relatorios-kpi-card">
            <div class="relatorios-kpi-top">
              <span class="relatorios-kpi-label">Taxa de Deferimento</span>
              <div class="relatorios-kpi-icon-wrap green">
                <img src="assets/icons/check.svg" alt="" aria-hidden="true">
              </div>
            </div>
            <div class="relatorios-kpi-number-row">
              <span class="relatorios-kpi-number" id="kpi-taxa-deferimento"><?= $taxaDeferimento ?>%</span>
            </div>
            <div class="relatorios-kpi-subtext trend-green">
              <span>↗</span> <?= $deferidas ?> homologadas
            </div>
          </article>

          <article class="relatorios-kpi-card">
            <div class="relatorios-kpi-top">
              <span class="relatorios-kpi-label">Tempo Médio de Análise</span>
              <div class="relatorios-kpi-icon-wrap gold">
                <img src="assets/icons/clock.svg" alt="" aria-hidden="true">
              </div>
            </div>
            <div class="relatorios-kpi-number-row">
              <span class="relatorios-kpi-number" id="kpi-tempo-analise">2.4</span>
              <span class="relatorios-kpi-unit">dias</span>
            </div>
            <div class="relatorios-kpi-subtext trend-green">
              <span>↘</span> SLA municipal cumprido
            </div>
          </article>

          <article class="relatorios-kpi-card">
            <div class="relatorios-kpi-top">
              <span class="relatorios-kpi-label">Locais Catalogados</span>
              <div class="relatorios-kpi-icon-wrap purple">
                <img src="assets/icons/pin.svg" alt="" aria-hidden="true">
              </div>
            </div>
            <div class="relatorios-kpi-number-row">
              <span class="relatorios-kpi-number" id="kpi-indice-nps"><?= $totalLocais ?></span>
              <span class="relatorios-kpi-unit">pontos</span>
            </div>
            <div class="relatorios-kpi-subtext trend-amber">
              Inventário municipal auditado
            </div>
          </article>

        </section>

        <!-- 3. Seção de Gráficos e Análise Detalhada -->
        <section class="relatorios-analytics-grid" aria-label="Visualizações gráficas e analytics">
          
          <!-- Gráfico de Volume Mensal -->
          <article class="relatorios-chart-card">
            <div class="chart-card-header">
              <div class="chart-title-wrap">
                <h2 class="chart-main-title">Volume de Solicitações por Mês</h2>
                <span class="chart-subtitle">Acompanhamento das solicitações cadastradas e deferidas</span>
              </div>

              <div class="chart-legend-wrap">
                <div class="chart-legend-item">
                  <span class="legend-color-dot deferidas"></span>
                  <span>Deferidas</span>
                </div>
                <div class="chart-legend-item">
                  <span class="legend-color-dot analise"></span>
                  <span>Em Análise</span>
                </div>
                <div class="chart-legend-item">
                  <span class="legend-color-dot indeferidas"></span>
                  <span>Indeferidas</span>
                </div>
              </div>
            </div>

            <div class="monthly-bars-container">
              <div class="monthly-bar-column">
                <div class="bar-tooltip">Jan</div>
                <div class="monthly-bar-stack" style="height: 60%;">
                  <div class="bar-segment deferidas" style="height: 75%;"></div>
                  <div class="bar-segment analise" style="height: 15%;"></div>
                  <div class="bar-segment indeferidas" style="height: 10%;"></div>
                </div>
                <span class="monthly-bar-label">Jan</span>
              </div>

              <div class="monthly-bar-column">
                <div class="bar-tooltip">Fev</div>
                <div class="monthly-bar-stack" style="height: 70%;">
                  <div class="bar-segment deferidas" style="height: 80%;"></div>
                  <div class="bar-segment analise" style="height: 12%;"></div>
                  <div class="bar-segment indeferidas" style="height: 8%;"></div>
                </div>
                <span class="monthly-bar-label">Fev</span>
              </div>

              <div class="monthly-bar-column">
                <div class="bar-tooltip">Mar</div>
                <div class="monthly-bar-stack" style="height: 75%;">
                  <div class="bar-segment deferidas" style="height: 82%;"></div>
                  <div class="bar-segment analise" style="height: 11%;"></div>
                  <div class="bar-segment indeferidas" style="height: 7%;"></div>
                </div>
                <span class="monthly-bar-label">Mar</span>
              </div>

              <div class="monthly-bar-column">
                <div class="bar-tooltip">Abr</div>
                <div class="monthly-bar-stack" style="height: 72%;">
                  <div class="bar-segment deferidas" style="height: 80%;"></div>
                  <div class="bar-segment analise" style="height: 12%;"></div>
                  <div class="bar-segment indeferidas" style="height: 8%;"></div>
                </div>
                <span class="monthly-bar-label">Abr</span>
              </div>

              <div class="monthly-bar-column">
                <div class="bar-tooltip">Mai</div>
                <div class="monthly-bar-stack" style="height: 80%;">
                  <div class="bar-segment deferidas" style="height: 84%;"></div>
                  <div class="bar-segment analise" style="height: 10%;"></div>
                  <div class="bar-segment indeferidas" style="height: 6%;"></div>
                </div>
                <span class="monthly-bar-label">Mai</span>
              </div>

              <div class="monthly-bar-column">
                <div class="bar-tooltip">Jun</div>
                <div class="monthly-bar-stack" style="height: 78%;">
                  <div class="bar-segment deferidas" style="height: 83%;"></div>
                  <div class="bar-segment analise" style="height: 10%;"></div>
                  <div class="bar-segment indeferidas" style="height: 7%;"></div>
                </div>
                <span class="monthly-bar-label">Jun</span>
              </div>

              <div class="monthly-bar-column">
                <div class="bar-tooltip">Jul</div>
                <div class="monthly-bar-stack" style="height: 82%;">
                  <div class="bar-segment deferidas" style="height: 85%;"></div>
                  <div class="bar-segment analise" style="height: 9%;"></div>
                  <div class="bar-segment indeferidas" style="height: 6%;"></div>
                </div>
                <span class="monthly-bar-label">Jul</span>
              </div>

              <div class="monthly-bar-column">
                <div class="bar-tooltip">Ago</div>
                <div class="monthly-bar-stack" style="height: 85%;">
                  <div class="bar-segment deferidas" style="height: 86%;"></div>
                  <div class="bar-segment analise" style="height: 8%;"></div>
                  <div class="bar-segment indeferidas" style="height: 6%;"></div>
                </div>
                <span class="monthly-bar-label">Ago</span>
              </div>

              <div class="monthly-bar-column">
                <div class="bar-tooltip">Set</div>
                <div class="monthly-bar-stack" style="height: 90%;">
                  <div class="bar-segment deferidas" style="height: 88%;"></div>
                  <div class="bar-segment analise" style="height: 7%;"></div>
                  <div class="bar-segment indeferidas" style="height: 5%;"></div>
                </div>
                <span class="monthly-bar-label">Set</span>
              </div>

              <div class="monthly-bar-column">
                <div class="bar-tooltip">Out</div>
                <div class="monthly-bar-stack" style="height: 88%;">
                  <div class="bar-segment deferidas" style="height: 85%;"></div>
                  <div class="bar-segment analise" style="height: 9%;"></div>
                  <div class="bar-segment indeferidas" style="height: 6%;"></div>
                </div>
                <span class="monthly-bar-label">Out</span>
              </div>

              <div class="monthly-bar-column">
                <div class="bar-tooltip">Nov</div>
                <div class="monthly-bar-stack" style="height: 92%;">
                  <div class="bar-segment deferidas" style="height: 88%;"></div>
                  <div class="bar-segment analise" style="height: 7%;"></div>
                  <div class="bar-segment indeferidas" style="height: 5%;"></div>
                </div>
                <span class="monthly-bar-label">Nov</span>
              </div>

              <div class="monthly-bar-column">
                <div class="bar-tooltip">Dez</div>
                <div class="monthly-bar-stack" style="height: 95%;">
                  <div class="bar-segment deferidas" style="height: 89%;"></div>
                  <div class="bar-segment analise" style="height: 6%;"></div>
                  <div class="bar-segment indeferidas" style="height: 5%;"></div>
                </div>
                <span class="monthly-bar-label">Dez</span>
              </div>
            </div>

            <div class="chart-highlight-banner">
              <span class="chart-highlight-icon">💡</span>
              <div>
                <strong>Conclusão do Período:</strong> A integração dos dados no MySQL garante auditoria completa e acompanhamento do cumprimento dos prazos legais do Passe Livre Suzano.
              </div>
            </div>
          </article>

          <!-- Coluna Direita: Cards de Métricas -->
          <div class="relatorios-side-cards-column">
            
            <article class="relatorios-mini-card">
              <div class="mini-card-header">
                <h3 class="mini-card-title">Distribuição por Deficiência</h3>
                <span class="mini-card-subtitle">Perfil dos cidadãos solicitantes em Suzano</span>
              </div>

              <div class="deficiency-bars-list">
                <div class="deficiency-bar-item">
                  <div class="deficiency-item-header">
                    <span class="deficiency-name">Física / Motora</span>
                    <span class="deficiency-percent">48%</span>
                  </div>
                  <div class="deficiency-progress-track">
                    <div class="deficiency-progress-fill orange" style="width: 48%;"></div>
                  </div>
                </div>

                <div class="deficiency-bar-item">
                  <div class="deficiency-item-header">
                    <span class="deficiency-name">Visual</span>
                    <span class="deficiency-percent">24%</span>
                  </div>
                  <div class="deficiency-progress-track">
                    <div class="deficiency-progress-fill amber" style="width: 24%;"></div>
                  </div>
                </div>

                <div class="deficiency-bar-item">
                  <div class="deficiency-item-header">
                    <span class="deficiency-name">Auditiva</span>
                    <span class="deficiency-percent">15%</span>
                  </div>
                  <div class="deficiency-progress-track">
                    <div class="deficiency-progress-fill warm-brown" style="width: 15%;"></div>
                  </div>
                </div>

                <div class="deficiency-bar-item">
                  <div class="deficiency-item-header">
                    <span class="deficiency-name">Intelectual / TEA</span>
                    <span class="deficiency-percent">10%</span>
                  </div>
                  <div class="deficiency-progress-track">
                    <div class="deficiency-progress-fill gold" style="width: 10%;"></div>
                  </div>
                </div>

                <div class="deficiency-bar-item">
                  <div class="deficiency-item-header">
                    <span class="deficiency-name">Mobilidade Reduzida / Idosos</span>
                    <span class="deficiency-percent">3%</span>
                  </div>
                  <div class="deficiency-progress-track">
                    <div class="deficiency-progress-fill light" style="width: 3%;"></div>
                  </div>
                </div>
              </div>
            </article>

            <article class="relatorios-mini-card">
              <div class="mini-card-header">
                <h3 class="mini-card-title">Tempos de Resposta por Setor</h3>
                <span class="mini-card-subtitle">Média de dias para tramitação técnica</span>
              </div>

              <div class="sector-time-list">
                <div class="sector-time-item">
                  <div class="sector-item-left">
                    <span class="sector-name">Sec. de Mobilidade Urbana</span>
                    <span class="sector-efficiency-badge">★ Eficiência Alta</span>
                  </div>
                  <span class="sector-days-count">1.8 dias</span>
                </div>

                <div class="sector-time-item">
                  <div class="sector-item-left">
                    <span class="sector-name">Perícia Médica Municipal</span>
                    <span class="sector-efficiency-badge">Dentro da Meta</span>
                  </div>
                  <span class="sector-days-count">3.5 dias</span>
                </div>

                <div class="sector-time-item">
                  <div class="sector-item-left">
                    <span class="sector-name">Ouvidoria Geral</span>
                    <span class="sector-efficiency-badge">Destaque Ágil</span>
                  </div>
                  <span class="sector-days-count">1.1 dias</span>
                </div>
              </div>
            </article>

          </div>
        </section>

        <!-- 4. Tabela de Relatórios Gerados -->
        <section class="relatorios-table-card" aria-label="Tabela de relatórios emitidos recentemente">
          <div class="relatorios-table-top">
            <div class="relatorios-table-title-wrap">
              <h2 class="relatorios-table-title">RELATÓRIOS GERADOS RECENTEMENTE</h2>
              <span class="relatorios-table-subtitle">Histórico de relatórios consolidados e diagnósticos exportados</span>
            </div>

            <button type="button" class="btn-relatorio-action" onclick="filtrarRelatorios();">
              <img src="assets/icons/sliders.svg" alt="" aria-hidden="true">
              Filtrar Relatórios
            </button>
          </div>

          <div class="relatorios-table-wrap">
            <table class="relatorios-table">
              <thead>
                <tr>
                  <th>Nome do Relatório & Descrição</th>
                  <th>Período de Referência</th>
                  <th>Data de Emissão</th>
                  <th>Formato</th>
                  <th>Tamanho</th>
                  <th>Ações</th>
                </tr>
              </thead>
              <tbody id="relatorios-table-body">
                <!-- Preenchido pelo js/relatorios.js -->
              </tbody>
            </table>
          </div>

          <div class="relatorios-pagination-bar">
            <div id="relatorios-count-display">Exibindo relatórios gerados</div>
          </div>
        </section>

        <!-- Rodapé do Gestor -->
        <footer class="relatorios-page-footer">
          <div>
            &copy; 2026 Passe Livre Suzano. Todos os direitos reservados. Projeto SENAI Suzano.
          </div>
          <div class="relatorios-footer-links">
            <a href="termos-de-uso.php">Termos de Uso</a>
            <a href="privacidade.php">Privacidade</a>
            <a href="ajuda.php">Suporte</a>
          </div>
        </footer>

      </main>
    </div>
  </div>

  <!-- Modal: Gerar Novo Relatório -->
  <div class="relatorio-modal-backdrop" id="modal-novo-relatorio" role="dialog" aria-modal="true" aria-labelledby="modal-novo-title">
    <div class="relatorio-modal-box">
      <div class="relatorio-modal-header">
        <h3 class="relatorio-modal-title" id="modal-novo-title">Gerar Novo Relatório Consolidado</h3>
        <button type="button" class="btn-modal-close modal-overlay-close" aria-label="Fechar modal">✕</button>
      </div>

      <form id="form-novo-relatorio">
        <div class="relatorio-form-group">
          <label for="input-relatorio-titulo">Título do Relatório *</label>
          <input type="text" id="input-relatorio-titulo" placeholder="Ex: Diagnóstico Viário e Acessibilidade" required>
        </div>

        <div class="relatorio-form-row">
          <div class="relatorio-form-group">
            <label for="input-relatorio-modulo">Módulo de Dados *</label>
            <select id="input-relatorio-modulo" required>
              <option value="geral">Geral (Todos os Módulos)</option>
              <option value="solicitacoes">Solicitações & Prazos SLA</option>
              <option value="locais">Inventário de Locais & Vias</option>
              <option value="barreiras">Ocorrências & Barreiras</option>
            </select>
          </div>

          <div class="relatorio-form-group">
            <label for="input-relatorio-formato">Formato do Arquivo *</label>
            <select id="input-relatorio-formato" required>
              <option value="PDF">PDF (Documento Assinado)</option>
              <option value="XLSX">Excel / Planilha (XLSX)</option>
              <option value="CSV">Dados Brutos (CSV)</option>
            </select>
          </div>
        </div>

        <div class="relatorio-form-row">
          <div class="relatorio-form-group">
            <label for="input-relatorio-periodo">Período de Referência *</label>
            <select id="input-relatorio-periodo" required>
              <option value="Fevereiro 2026">Fevereiro 2026</option>
              <option value="Janeiro 2026">Janeiro 2026</option>
              <option value="Ano Base 2026">Ano Base 2026</option>
            </select>
          </div>

          <div class="relatorio-form-group">
            <label for="input-relatorio-tipo">Tipo de Agregação</label>
            <select id="input-relatorio-tipo">
              <option value="executivo">Sumário Executivo & Gráficos</option>
              <option value="analitico">Analítico Linha a Linha</option>
            </select>
          </div>
        </div>

        <div class="relatorio-form-group">
          <label for="input-relatorio-desc">Descrição / Observações Técnicas</label>
          <textarea id="input-relatorio-desc" rows="2" placeholder="Informações complementares sobre o escopo..."></textarea>
        </div>

        <div class="relatorio-modal-footer">
          <button type="button" class="btn-relatorio-action modal-overlay-close">Cancelar</button>
          <button type="submit" class="btn-relatorio-primary">Processar e Emitir Relatório</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Modal: Pré-visualização -->
  <div class="relatorio-modal-backdrop" id="modal-preview-relatorio" role="dialog" aria-modal="true" aria-labelledby="modal-preview-title">
    <div class="relatorio-modal-box">
      <div class="relatorio-modal-header">
        <h3 class="relatorio-modal-title" id="modal-preview-title">Ficha Técnica do Relatório</h3>
        <button type="button" class="btn-modal-close modal-overlay-close" aria-label="Fechar modal">✕</button>
      </div>

      <div id="modal-preview-body"></div>
    </div>
  </div>

  <div id="app-toast" class="toast-notice" role="status" aria-live="polite">
    <span id="toast-message">Notificação</span>
  </div>

    <script src="js/db.js"></script>
  <script src="js/db-queries.js"></script>
  <script src="js/auth.js"></script>
  <script src="js/relatorios.js"></script>
</body>
</html>
