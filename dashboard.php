<?php
/**
 * Passe Livre Suzano — Dashboard do Gestor
 * Proteção server-side: apenas admins/gestores podem acessar.
 */
require_once __DIR__ . '/includes/auth_guard.php';
$gestorUserRaw = auth_require_admin();
$gestorUser    = auth_user_safe($gestorUserRaw);
$activePage    = 'dashboard';

// Dados reais do banco para preenchimento inicial server-side
$pendingCount   = 0;
$analyzingCount = 0;
$approvedCount  = 0;
$rejectedCount  = 0;
$totalUsers     = 0;
try {
    $db = get_db();
    $stmt = $db->query("SELECT status, COUNT(*) AS total FROM solicitacoes GROUP BY status");
    while ($r = $stmt->fetch()) {
        if ($r['status'] === 'pending')   $pendingCount   = (int)$r['total'];
        if ($r['status'] === 'analyzing') $analyzingCount = (int)$r['total'];
        if ($r['status'] === 'approved')  $approvedCount  = (int)$r['total'];
        if ($r['status'] === 'rejected')  $rejectedCount  = (int)$r['total'];
    }
    $totalSols  = $pendingCount + $analyzingCount + $approvedCount + $rejectedCount;
    $totalUsers = (int)$db->query("SELECT COUNT(*) FROM usuarios WHERE status = 'ativo'")->fetchColumn();

    // Solicitações recentes (5 mais novas)
    $stmtRecent = $db->query("SELECT * FROM solicitacoes ORDER BY created_at DESC LIMIT 5");
    $solRecentes = $stmtRecent->fetchAll();
} catch (\Throwable $e) {
    $totalSols   = 0;
    $solRecentes = [];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard — Painel do Gestor | Passe Livre</title>
  <meta name="description" content="Painel de controle e monitoramento de solicitações de acessibilidade, triagem de ocorrências e indicadores de mobilidade de Suzano.">
  <link rel="stylesheet" href="assets/fonts/fonts.css">
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="css/gestor.css">
  <link rel="stylesheet" href="css/dashboard.css">
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
            <div class="gestor-breadcrumb-text"><span>Passe Livre</span> / <span>Dashboard</span></div>
            <h1 class="gestor-main-heading">Dashboard</h1>
          </div>
        </div>
        <div class="gestor-header-search-wrap">
          <img src="assets/icons/search.svg" alt="" aria-hidden="true">
          <input type="search" placeholder="Buscar por ID, cidadão..." aria-label="Buscar ocorrências e cidadãos">
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
            <img src="assets/icons/arrow-right.svg" alt="" style="width:12px;height:12px;transform:rotate(90deg);opacity:0.5;margin-left:4px;">
          </div>
        </div>
      </header>

      <main id="main-content" class="gestor-page-body">

        <!-- Banner de Atenção Crítica -->
        <section class="dashboard-critical-banner" aria-label="Alerta de Solicitações Críticas">
          <svg class="critical-watermark-icon" viewBox="0 0 24 24" fill="none" stroke="#d97706" stroke-width="1.5">
            <path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/>
            <line x1="12" y1="9" x2="12" y2="13"/>
            <line x1="12" y1="17" x2="12.01" y2="17"/>
          </svg>
          <div class="critical-banner-left">
            <div class="critical-icon-badge">
              <img src="assets/icons/warning.svg" alt="Atenção" aria-hidden="true">
            </div>
            <div class="critical-text-content">
              <div class="critical-tag-row">
                <span class="badge-critical-tag">ATENÇÃO CRÍTICA</span>
                <span class="sla-limit-text">• Acordo de Nível de Serviço (SLA 48h)</span>
              </div>
              <h2 class="critical-banner-title">
                <?= $pendingCount ?> solicitaç<?= $pendingCount === 1 ? 'ão aguarda' : 'ões aguardando' ?> análise prioritária
              </h2>
              <p class="critical-banner-sub">
                Chamados com risco iminente de mobilidade urbana em vias de alto fluxo de Suzano.
              </p>
            </div>
          </div>
          <a href="solicitacoes.php?status=pending" class="btn-critical-action">
            <span>Analisar agora</span>
            <img src="assets/icons/arrow-right.svg" alt="" style="width:14px;height:14px;filter:brightness(0) invert(1);">
          </a>
        </section>

        <!-- Grid de KPIs -->
        <section class="dashboard-kpi-grid" aria-label="Indicadores Operacionais">
          <div class="kpi-card">
            <div class="kpi-header-row">
              <span class="kpi-label">Total<br>Solicitações</span>
              <div class="kpi-icon-wrap gray"><img src="assets/icons/file-text.svg" alt="" aria-hidden="true"></div>
            </div>
            <div class="kpi-number" id="kpi-total"><?= number_format($totalSols, 0, ',', '.') ?></div>
            <div class="kpi-trend-tag green"><img src="assets/icons/bar-chart.svg" alt="" style="width:11px;height:11px;"><span id="kpi-total-trend">Carregando...</span></div>
          </div>
          <div class="kpi-card">
            <div class="kpi-header-row">
              <span class="kpi-label">Pendentes</span>
              <div class="kpi-icon-wrap amber"><img src="assets/icons/clock.svg" alt="" aria-hidden="true"></div>
            </div>
            <div class="kpi-number" id="kpi-pending"><?= $pendingCount ?></div>
            <div class="kpi-trend-tag green"><span id="kpi-pending-trend">Atualizado agora</span></div>
          </div>
          <div class="kpi-card">
            <div class="kpi-header-row">
              <span class="kpi-label">Em Análise</span>
              <div class="kpi-icon-wrap blue"><img src="assets/icons/users.svg" alt="" aria-hidden="true"></div>
            </div>
            <div class="kpi-number" id="kpi-analyzing"><?= $analyzingCount ?></div>
            <div class="kpi-trend-tag blue"><span>Em tramitação</span></div>
          </div>
          <div class="kpi-card">
            <div class="kpi-header-row">
              <span class="kpi-label">Aprovadas</span>
              <div class="kpi-icon-wrap green"><img src="assets/icons/check.svg" alt="" aria-hidden="true"></div>
            </div>
            <div class="kpi-number" id="kpi-approved"><?= number_format($approvedCount, 0, ',', '.') ?></div>
            <div class="kpi-trend-tag green"><span>Resolvidas</span></div>
          </div>
          <div class="kpi-card">
            <div class="kpi-header-row">
              <span class="kpi-label">Recusadas</span>
              <div class="kpi-icon-wrap red"><img src="assets/icons/ban.svg" alt="" aria-hidden="true"></div>
            </div>
            <div class="kpi-number" id="kpi-rejected" style="color:#b91c1c;"><?= $rejectedCount ?></div>
            <div class="kpi-trend-tag green"><span>Inconsistências</span></div>
          </div>
          <div class="kpi-card">
            <div class="kpi-header-row">
              <span class="kpi-label">Usuários Ativos</span>
              <div class="kpi-icon-wrap warm"><img src="assets/icons/users.svg" alt="" aria-hidden="true"></div>
            </div>
            <div class="kpi-number" id="kpi-users"><?= number_format($totalUsers, 0, ',', '.') ?></div>
            <div class="kpi-trend-tag warm"><span>Cadastrados ativos</span></div>
          </div>
        </section>

        <!-- Gráfico e Distribuição -->
        <div class="dashboard-middle-grid">
          <section class="gestor-section-card" aria-labelledby="heading-chart-evolution">
            <div class="chart-header-row">
              <div class="chart-titles-wrap">
                <h3 class="chart-card-title" id="heading-chart-evolution">Evolução das Solicitações</h3>
                <span class="chart-card-sub">Acompanhamento mensal comparativo de entradas vs conclusões.</span>
              </div>
              <div class="period-pills-wrap" role="tablist" aria-label="Intervalo de tempo do gráfico">
                <button type="button" class="period-pill-btn" data-period="7d">7 dias</button>
                <button type="button" class="period-pill-btn" data-period="30d">30 dias</button>
                <button type="button" class="period-pill-btn active" data-period="6m">6 meses</button>
                <button type="button" class="period-pill-btn" data-period="1y">1 ano</button>
              </div>
            </div>
            <div class="chart-legend-row">
              <span class="legend-item"><span class="legend-dot yellow"></span>Recebidas</span>
              <span class="legend-item"><span class="legend-dot green"></span>Aprovadas</span>
              <span class="legend-item"><span class="legend-dot red"></span>Recusadas</span>
            </div>
            <div class="bar-chart-container" id="bar-chart-container" aria-label="Gráfico de barras mensal"></div>
            <div class="chart-footer-row">
              <span class="efficiency-text">Indicador de Eficiência de Resolução: <strong id="efficiency-text">Carregando...</strong></span>
              <a href="relatorios.php" class="link-detailed-report">Ver relatório detalhado &rarr;</a>
            </div>
          </section>

          <div class="dashboard-side-col">
            <section class="gestor-section-card" aria-labelledby="heading-status-dist">
              <div class="section-card-header" style="border:none;padding-bottom:0;">
                <h3 class="section-title" id="heading-status-dist" style="font-size:13.5px;">Distribuição por Status</h3>
                <img src="assets/icons/target.svg" alt="" style="width:15px;height:15px;opacity:0.6;" onerror="this.src='assets/icons/spark.svg'">
              </div>
              <div class="status-stacked-bar" id="stacked-bar" role="progressbar" aria-label="Distribuição percentual por status">
                <?php if ($totalSols > 0): ?>
                  <?php $pApproved  = round($approvedCount  / $totalSols * 100); ?>
                  <?php $pRejected  = round($rejectedCount  / $totalSols * 100); ?>
                  <?php $pAnalyzing = round($analyzingCount / $totalSols * 100); ?>
                  <?php $pPending   = 100 - $pApproved - $pRejected - $pAnalyzing; ?>
                  <div class="stacked-segment green"  style="width:<?= $pApproved ?>%;"  title="Aprovadas: <?= $pApproved ?>%"></div>
                  <div class="stacked-segment red"    style="width:<?= $pRejected ?>%;"  title="Recusadas: <?= $pRejected ?>%"></div>
                  <div class="stacked-segment blue"   style="width:<?= $pAnalyzing ?>%;" title="Em Análise: <?= $pAnalyzing ?>%"></div>
                  <div class="stacked-segment yellow" style="width:<?= $pPending ?>%;"   title="Pendentes: <?= $pPending ?>%"></div>
                <?php else: ?>
                  <div class="stacked-segment gray" style="width:100%;"></div>
                <?php endif; ?>
              </div>
              <div class="status-pills-grid">
                <?php if ($totalSols > 0): ?>
                  <span class="status-pill-item green"><span>• Aprovadas</span><strong><?= $pApproved ?>%</strong></span>
                  <span class="status-pill-item red"><span>• Recusadas</span><strong><?= $pRejected ?>%</strong></span>
                  <span class="status-pill-item blue"><span>• Em Análise</span><strong><?= $pAnalyzing ?>%</strong></span>
                  <span class="status-pill-item yellow"><span>• Pendentes</span><strong><?= $pPending ?>%</strong></span>
                <?php else: ?>
                  <span class="status-pill-item gray"><span>• Sem dados</span><strong>—</strong></span>
                <?php endif; ?>
              </div>
            </section>

            <section class="gestor-section-card" aria-labelledby="heading-regions">
              <div class="section-card-header" style="border:none;padding-bottom:0;">
                <div>
                  <h3 class="section-title" id="heading-regions" style="font-size:13.5px;">Solicitações por Região</h3>
                  <span style="font-size:10.5px;color:#a8a29e;">Suzano &amp; Entorno</span>
                </div>
              </div>
              <div class="regions-list-wrap" id="regions-list">
                <div style="font-size:12px;color:#78716c;padding:12px 0;">Carregando dados do banco...</div>
              </div>
            </section>
          </div>
        </div>

        <!-- Solicitações Recentes + Ações Rápidas -->
        <div class="dashboard-bottom-grid" id="solicitacoes">
          <section class="gestor-section-card" aria-labelledby="heading-recent-requests">
            <div class="section-card-header">
              <div>
                <h3 class="section-title" id="heading-recent-requests">Solicitações Recentes</h3>
                <span style="font-size:11px;color:#78716c;">Acompanhamento e triagem de ocorrências submetidas por cidadãos.</span>
              </div>
              <div class="table-actions-header-group">
                <button type="button" class="btn-header-table-action" id="btn-filter-recent">
                  <img src="assets/icons/sliders.svg" alt="" aria-hidden="true"><span>Filtrar</span>
                </button>
                <button type="button" class="btn-header-table-action" id="btn-export-recent">
                  <img src="assets/icons/download.svg" alt="" aria-hidden="true"><span>Exportar</span>
                </button>
              </div>
            </div>
            <div style="overflow-x:auto;">
              <table class="recent-requests-table">
                <thead>
                  <tr>
                    <th>ID</th><th>CIDADÃO / USUÁRIO</th><th>TIPO DE OCORRÊNCIA</th>
                    <th>LOCAL / ENDEREÇO</th><th>DATA DE ENVIO</th><th>STATUS</th><th>AÇÃO</th>
                  </tr>
                </thead>
                <tbody id="recent-requests-tbody">
                  <?php if (empty($solRecentes)): ?>
                  <tr><td colspan="7" style="text-align:center;padding:24px;color:#78716c;">Carregando solicitações...</td></tr>
                  <?php else: ?>
                  <?php foreach ($solRecentes as $sol): ?>
                  <?php
                    $statusMap = [
                      'pending'   => ['label' => 'Pendente',   'css' => 'pending'],
                      'analyzing' => ['label' => 'Em Análise', 'css' => 'analyzing'],
                      'approved'  => ['label' => 'Aprovada',   'css' => 'approved'],
                      'rejected'  => ['label' => 'Recusada',   'css' => 'rejected'],
                    ];
                    $st = $statusMap[$sol['status']] ?? ['label' => $sol['status_name'] ?? $sol['status'], 'css' => 'pending'];
                    $dataBR = isset($sol['created_at']) ? date('d/m/Y', strtotime($sol['created_at'])) : '';
                    $initials = implode('', array_map(fn($w) => strtoupper($w[0]), array_filter(explode(' ', $sol['citizen_name']))));
                    $initials = substr($initials, 0, 2);
                  ?>
                  <tr data-id="<?= (int)$sol['id'] ?>" data-citizen="<?= htmlspecialchars($sol['citizen_name']) ?>" data-type="<?= htmlspecialchars($sol['type_name'] ?? $sol['type_key']) ?>" data-location="<?= htmlspecialchars($sol['address']) ?>" data-date="<?= htmlspecialchars($dataBR) ?>" data-status="<?= htmlspecialchars($st['label']) ?>">
                    <td><span class="request-id-tag">#<?= (int)$sol['id'] ?></span></td>
                    <td>
                      <div class="citizen-cell">
                        <div class="citizen-avatar"><?= htmlspecialchars($initials) ?></div>
                        <span class="citizen-name"><?= htmlspecialchars($sol['citizen_name']) ?></span>
                      </div>
                    </td>
                    <td>
                      <div class="occurrence-type-cell">
                        <img src="assets/icons/file-text.svg" alt="" onerror="this.src='assets/icons/pin.svg'">
                        <span><?= htmlspecialchars($sol['type_name'] ?? $sol['type_key']) ?></span>
                      </div>
                    </td>
                    <td>
                      <div class="location-cell">
                        <img src="assets/icons/pin.svg" alt="">
                        <span><?= htmlspecialchars(mb_substr($sol['address'], 0, 40)) ?></span>
                      </div>
                    </td>
                    <td><?= htmlspecialchars($dataBR) ?></td>
                    <td><span class="status-badge-pill <?= $st['css'] ?>"><?= htmlspecialchars($st['label']) ?></span></td>
                    <td>
                      <a href="solicitacao-detalhe.php?id=<?= (int)$sol['id'] ?>" class="btn-view-request">Visualizar</a>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
            <div class="table-pagination-footer">
              <span id="pagination-summary">Exibindo <?= count($solRecentes) ?> registro(s) recentes</span>
              <a href="solicitacoes.php" class="btn-header-table-action" style="text-decoration:none;">Ver todas →</a>
            </div>
          </section>

          <section class="gestor-section-card" aria-labelledby="heading-quick-actions">
            <div class="section-card-header" style="border:none;padding-bottom:0;">
              <div>
                <h3 class="section-title" id="heading-quick-actions">Ações Rápidas</h3>
                <span style="font-size:11px;color:#78716c;">Atalhos operacionais frequentes</span>
              </div>
            </div>
            <button type="button" class="btn-new-request-primary" id="btn-new-request">
              <img src="assets/icons/plus.svg" alt="" aria-hidden="true">
              <span>+ Nova Solicitação &rarr;</span>
            </button>
            <div class="quick-actions-list">
              <a href="solicitacoes.php?status=pending" class="quick-action-link-item">
                <div class="quick-action-left">
                  <img src="assets/icons/file-text.svg" alt="">
                  <span>Ver solicitações pendentes</span>
                  <span class="quick-badge-count"><?= $pendingCount ?></span>
                </div>
                <img src="assets/icons/arrow-right.svg" alt="" class="quick-action-chevron">
              </a>
              <a href="relatorios.php" class="quick-action-link-item">
                <div class="quick-action-left">
                  <img src="assets/icons/bar-chart.svg" alt="">
                  <span>Gerar relatório</span>
                </div>
                <img src="assets/icons/arrow-right.svg" alt="" class="quick-action-chevron">
              </a>
              <a href="perfil-gestor.php" class="quick-action-link-item">
                <div class="quick-action-left">
                  <img src="assets/icons/users.svg" alt="">
                  <span>Gerenciar usuários</span>
                </div>
                <img src="assets/icons/arrow-right.svg" alt="" class="quick-action-chevron">
              </a>
              <a href="locais-cadastrados.php" class="quick-action-link-item">
                <div class="quick-action-left">
                  <img src="assets/icons/pin.svg" alt="">
                  <span>Gerenciar locais</span>
                </div>
                <img src="assets/icons/arrow-right.svg" alt="" class="quick-action-chevron">
              </a>
            </div>
            <div class="fiscalizacao-info-box">
              <div class="fiscalizacao-title-row">
                <img src="assets/icons/support.svg" alt="" onerror="this.src='assets/icons/warning.svg'">
                <span>Canal de Fiscalização</span>
              </div>
              <p class="fiscalizacao-text">
                Dúvidas sobre parâmetros NBR 9050 em calçadas? Acesse os manuais normativos e legislações vigentes.
              </p>
            </div>
          </section>
        </div>

      </main>

      <footer class="site-footer" role="contentinfo" style="margin-top:auto;padding:16px 32px;background:#fdfaf6;border-top:1px solid #ede4d8;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;font-size:11px;color:#78716c;">
        <div>&copy; 2026 Passe Livre. Todos os direitos reservados. Projeto SENAI Suzano.</div>
        <div style="display:flex;gap:14px;">
          <a href="direitos.php#termos" style="color:#78716c;text-decoration:none;">Termos de uso</a>
          <span>&bull;</span>
          <a href="direitos.php#privacidade" style="color:#78716c;text-decoration:none;">Privacidade</a>
          <span>&bull;</span>
          <a href="ajuda.php#suporte" style="color:#78716c;text-decoration:none;">Suporte</a>
        </div>
      </footer>
    </div>
  </div>

  <!-- Modal Solicitação / Triagem -->
  <div class="modal-overlay" id="modal-view-request" role="dialog" aria-modal="true" aria-labelledby="modal-request-title">
    <div class="modal-window">
      <div class="modal-header">
        <div style="display:flex;align-items:center;gap:10px;">
          <h3 id="modal-request-title"><span id="modal-req-title">Detalhe da Solicitação</span> <span id="modal-req-id" style="color:#b45309;font-size:14px;"></span></h3>
          <span class="status-badge-pill" id="modal-req-status">Pendente</span>
        </div>
        <button type="button" class="btn-modal-close" aria-label="Fechar">&times;</button>
      </div>
      <div class="modal-body">
        <div style="font-size:13px;color:#57534e;display:flex;flex-direction:column;gap:6px;">
          <div><strong>Cidadão:</strong> <span id="modal-req-citizen"></span></div>
          <div><strong>Localização:</strong> <span id="modal-req-location"></span></div>
          <div><strong>Data:</strong> <span id="modal-req-date"></span></div>
        </div>
        <div class="form-group-item">
          <label class="form-group-label" for="triage-notes">Parecer Técnico / Despacho</label>
          <textarea id="triage-notes" class="form-control-input" placeholder="Observações da triagem técnica..."></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn-card-restore" onclick="triageAction('recusar')">Recusar / Arquivar</button>
        <div>
          <button type="button" class="btn-card-restore" onclick="triageAction('reanalise')">Solicitar Diligência</button>
          <button type="button" class="btn-card-save" onclick="triageAction('aprovar')">Aprovar Solicitação</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal Nova Solicitação / Ocorrência -->
  <div class="modal-overlay" id="modal-new-request" role="dialog" aria-modal="true" aria-labelledby="modal-new-req-title">
    <div class="modal-window">
      <div class="modal-header">
        <h3 id="modal-new-req-title">Nova Solicitação / Ocorrência</h3>
        <button type="button" class="btn-modal-close" aria-label="Fechar">&times;</button>
      </div>
      <form id="form-new-request">
        <div class="modal-body">
          <div class="form-group-item">
            <label class="form-group-label" for="new-req-citizen">Nome do Cidadão *</label>
            <input type="text" id="new-req-citizen" class="form-control-input" placeholder="Ex: Maria Santos" required style="width:100%;height:38px;padding:8px 12px;border:1px solid #d6cbbe;border-radius:8px;font-size:13px;box-sizing:border-box;">
          </div>
          <div class="form-group-item">
            <label class="form-group-label" for="new-req-type">Tipo de Ocorrência *</label>
            <select id="new-req-type" class="form-select-styled" style="width:100%;height:38px;padding:6px 12px;border:1px solid #d6cbbe;border-radius:8px;font-size:13px;box-sizing:border-box;background:#fff;">
              <option value="Sinalização e Semáforo">Semáforo Sonoro Inoperante</option>
              <option value="Calçada e Rebaixo">Guia sem rebaixo para cadeirantes</option>
              <option value="Piso Tátil">Ausência de piso tátil direcional</option>
              <option value="Obstáculo Físico">Degrau ou obstáculo sem rampa</option>
              <option value="Vaga PcD">Vaga PcD obstruída</option>
              <option value="Transporte">Ônibus sem plataforma elevatória</option>
              <option value="Outro">Outro</option>
            </select>
          </div>
          <div class="form-group-item">
            <label class="form-group-label" for="new-req-address">Endereço / Local *</label>
            <input type="text" id="new-req-address" class="form-control-input" placeholder="Ex: Rua General Francisco Glicério, 120 - Centro" required style="width:100%;height:38px;padding:8px 12px;border:1px solid #d6cbbe;border-radius:8px;font-size:13px;box-sizing:border-box;">
          </div>
        </div>
        <div class="modal-footer" style="justify-content:flex-end;">
          <button type="button" class="btn-card-restore btn-modal-close">Cancelar</button>
          <button type="submit" class="btn-card-save">Cadastrar Solicitação</button>
        </div>
      </form>
    </div>
  </div>

    <script src="js/db.js"></script>
  <script src="js/db-queries.js"></script>
  <script src="js/auth.js"></script>
  <script src="js/dashboard.js"></script>
</body>
</html>
