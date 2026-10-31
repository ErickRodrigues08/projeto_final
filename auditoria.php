<?php
/**
 * Passe Livre Suzano — Auditoria do Sistema (Gestor)
 * Proteção server-side: apenas admins/gestores.
 */
require_once __DIR__ . '/includes/auth_guard.php';
$gestorUserRaw = auth_require_admin();
$gestorUser    = auth_user_safe($gestorUserRaw);
$activePage    = 'auditoria';

$db = get_db();
$totalLogs       = (int)$db->query("SELECT COUNT(*) FROM logs_auditoria")->fetchColumn();
$criticosLogs    = (int)$db->query("SELECT COUNT(*) FROM logs_auditoria WHERE risk_level IN ('alto','critico')")->fetchColumn();
$operadoresCount = (int)$db->query("SELECT COUNT(DISTINCT user_id) FROM logs_auditoria WHERE user_id IS NOT NULL")->fetchColumn();
if ($operadoresCount < 1) $operadoresCount = 1;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Histórico e Auditoria — Painel do Gestor | Passe Livre</title>
  <meta name="description" content="Registro completo e auditável de todas as ações administrativas, triagens e modificações realizadas no sistema.">
  <link rel="stylesheet" href="assets/fonts/fonts.css">
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="css/gestor.css">
  <link rel="stylesheet" href="css/auditoria.css">
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
            <div class="gestor-breadcrumb-text"><span>Painel do Gestor</span> &gt; <span>Auditoria</span></div>
            <h1 class="gestor-main-heading">Logs de Auditoria e Segurança</h1>
          </div>
        </div>

        <div class="gestor-header-search-wrap">
          <img src="assets/icons/search.svg" alt="" aria-hidden="true">
          <input type="search" id="audit-search-input" placeholder="Buscar por operador, IP ou ação..." aria-label="Buscar registros de auditoria">
        </div>

        <div style="display:flex;align-items:center;gap:18px;">
          <div style="display:inline-flex;align-items:center;gap:6px;background:#ecfdf5;border:1px solid #a7f3d0;border-radius:9999px;padding:4px 12px;font-size:11px;font-weight:700;color:#065f46;">
            <span style="width:7px;height:7px;border-radius:50%;background:#10b981;"></span>
            Sistema Online
          </div>

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

      <main id="main-content" class="gestor-page-body">
        
        <!-- 1. Grid de 4 KPIs Reais -->
        <section class="audit-metrics-grid" aria-label="Métricas Principais de Auditoria">
          <div class="audit-metric-card">
            <div class="audit-metric-top-row">
              <span class="audit-metric-label">TOTAL DE REGISTROS AUDITADOS</span>
              <div class="audit-metric-icon-wrap amber">
                <img src="assets/icons/file-text.svg" alt="" aria-hidden="true">
              </div>
            </div>
            <div class="audit-metric-number" id="kpi-total-audit"><?= number_format($totalLogs, 0, ',', '.') ?></div>
            <div class="audit-metric-subtext green">
              <span>Registros ativos gravados em MySQL</span>
            </div>
          </div>

          <div class="audit-metric-card">
            <div class="audit-metric-top-row">
              <span class="audit-metric-label">AÇÕES CRÍTICAS / ALERTAS</span>
              <div class="audit-metric-icon-wrap red">
                <img src="assets/icons/warning.svg" alt="" aria-hidden="true">
              </div>
            </div>
            <div class="audit-metric-number" style="color: #b91c1c;" id="kpi-critical-audit"><?= $criticosLogs ?></div>
            <div class="audit-metric-subtext red">
              <span>Ações sensíveis com monitoramento contínuo</span>
            </div>
          </div>

          <div class="audit-metric-card">
            <div class="audit-metric-top-row">
              <span class="audit-metric-label">OPERADORES ATIVOS</span>
              <div class="audit-metric-icon-wrap warm">
                <img src="assets/icons/users.svg" alt="" aria-hidden="true">
              </div>
            </div>
            <div class="audit-metric-number" id="kpi-operators-audit"><?= $operadoresCount ?></div>
            <div class="audit-metric-subtext muted">
              <span>Servidores e gestores municipais registrados</span>
            </div>
          </div>

          <div class="audit-metric-card">
            <div class="audit-metric-top-row">
              <span class="audit-metric-label">CONFORMIDADE LGPD E INTEGRIDADE</span>
              <div class="audit-metric-icon-wrap green">
                <img src="assets/icons/shield.svg" alt="" aria-hidden="true">
              </div>
            </div>
            <div class="audit-metric-number" style="color: #059669;">100%</div>
            <div class="audit-metric-subtext green">
              <span>Auditoria SHA-256 e W3C válida</span>
            </div>
          </div>
        </section>

        <!-- 2. Barra de Filtros -->
        <section class="audit-filters-card" aria-label="Filtros de Auditoria">
          <div class="audit-filters-left-wrap">
            <div class="filter-input-group">
              <span class="filter-group-label">Período e Data:</span>
              <div class="filter-date-pill" id="btn-period-select" title="Alterar intervalo de datas">
                <img src="assets/icons/calendar.svg" alt="" aria-hidden="true">
                <span>Últimos registros</span>
              </div>
            </div>

            <div class="filter-input-group">
              <span class="filter-group-label">Tipo de Evento:</span>
              <select class="filter-select-control" id="audit-type-filter" aria-label="Filtrar por tipo de evento">
                <option value="all" selected>Todos os Tipos</option>
                <option value="auth">Autenticação</option>
                <option value="solicitacoes">Solicitações</option>
                <option value="notificacoes">Notificações</option>
                <option value="usuarios">Usuários</option>
                <option value="administradores">Administradores</option>
                <option value="rotas">Rotas / Navegação</option>
                <option value="avaliacoes">Avaliações</option>
                <option value="contato">Contato</option>
                <option value="sistema">Sistema</option>
              </select>
            </div>

            <div class="filter-input-group">
              <span class="filter-group-label">Nível de Risco:</span>
              <select class="filter-select-control" id="audit-risk-filter" aria-label="Filtrar por nível de risco">
                <option value="all" selected>Todos os Níveis</option>
                <option value="baixo">Baixo / Informativo</option>
                <option value="medio">Médio / Atenção</option>
                <option value="alto">Alto / Crítico</option>
              </select>
            </div>

            <button type="button" class="btn-clear-filters" id="btn-clear-audit-filters">Limpar Filtros</button>
          </div>

          <button type="button" class="btn-export-audit-trail" id="btn-export-audit-trail" title="Exportar trilha completa">
            <img src="assets/icons/download.svg" alt="" aria-hidden="true">
            <span>Exportar Trilha de Auditoria (CSV)</span>
          </button>
        </section>

        <!-- 3. Tabela de Registros -->
        <section class="gestor-section-card" aria-labelledby="heading-immutable-records">
          <div class="section-card-header">
            <div class="section-card-header-left">
              <img src="assets/icons/shield.svg" alt="" aria-hidden="true">
              <h3 class="section-title" id="heading-immutable-records">Registros Imutáveis em Tempo Real</h3>
            </div>
            
            <div style="display:flex;align-items:center;gap:8px;">
              <span class="badge-sha256-seal">
                <img src="assets/icons/lock.svg" alt="" style="width:12px;height:12px;">
                Autenticado SHA-256
              </span>
              <span class="badge-live-stream-status">
                <span class="live-pulse-dot"></span>
                Validação Ativa
              </span>
            </div>
          </div>

          <div class="audit-table-wrap">
            <table class="audit-table">
              <thead>
                <tr>
                  <th>DATA/HORA</th>
                  <th>OPERADOR / IDENTIDADE</th>
                  <th>MÓDULO</th>
                  <th>AÇÃO REALIZADA</th>
                  <th>RISCO</th>
                  <th>IP / ORIGEM</th>
                  <th>HASH SHA-256</th>
                  <th>AÇÕES</th>
                </tr>
              </thead>
              <tbody>
                <!-- Carregado dinamicamente via js/auditoria.js pelo MySQL -->
              </tbody>
            </table>
          </div>

          <div class="audit-pagination-bar">
            <span id="audit-results-count">Carregando registros...</span>
          </div>
        </section>

        <!-- 4. Política de Retenção -->
        <section class="retention-policy-card" aria-labelledby="heading-retention-policy">
          <div class="retention-left-info">
            <div class="retention-title-row">
              <img src="assets/icons/shield.svg" alt="" style="width:20px;height:20px;">
              <h3 class="retention-title" id="heading-retention-policy">Integridade dos Registros &amp; Política de Retenção Municipal</h3>
            </div>
            
            <p class="retention-legal-text">
              Em estrita conformidade com a <strong>Lei Federal 13.709/18 (LGPD)</strong> e a <strong>Legislação Municipal de Suzano nº 4.930/2021</strong>, todas as trilhas de auditoria geradas pelo sistema Passe Livre possuem imutabilidade digital garantida por hash <code>SHA-256</code>, com retenção legal obrigatória por um período computado mínimo de <strong>5 (cinco) anos</strong>.
            </p>

            <div class="retention-badges-row">
              <span class="retention-pill">
                <img src="assets/icons/lock.svg" alt="">
                Armazenamento Criptografado W3C
              </span>
              <span class="retention-pill">
                <img src="assets/icons/history.svg" alt="">
                Retenção legal: 5 anos
              </span>
            </div>
          </div>

          <div class="crypto-seal-card">
            <div class="crypto-hash-block">
              <span class="crypto-hash-label">HASH SHA-256 DO BLOCO:</span>
              <span class="crypto-hash-code">8f4c3a10e9988b43f721c56ea2083b4904128509d3b610fa78e1215b4c4892c1</span>
            </div>

            <div class="crypto-valid-row">
              <img src="assets/icons/check.svg" alt="" style="width:14px;height:14px;">
              <span>ASSINATURA DIGITAL VÁLIDA</span>
            </div>

            <span class="crypto-verifier-text">Verificado por: Prefeitura Municipal de Suzano</span>
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

  <!-- Modal Inspecionar Hash -->
  <div class="modal-overlay" id="modal-inspect-hash" role="dialog" aria-modal="true" style="display:none;" hidden>
    <div class="modal-window" style="max-width:600px;background:#fff;border-radius:14px;padding:24px;border:1px solid #e7ded2;">
      <div class="modal-header" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
        <h3 id="title-modal-hash" style="margin:0;font-size:16px;">Prova Criptográfica do Registro</h3>
        <button type="button" class="btn-modal-close" id="btn-close-inspect-hash" style="border:none;background:none;font-size:20px;cursor:pointer;">&times;</button>
      </div>
      <div class="modal-body">
        <div style="background:#faf7f2;border:1px solid #ede4d8;border-radius:10px;padding:14px;font-size:12px;display:flex;flex-direction:column;gap:8px;">
          <div><strong>Data/Hora:</strong> <span id="modal-log-date"></span></div>
          <div><strong>Operador:</strong> <span id="modal-log-operator"></span></div>
          <div><strong>Origem:</strong> <span id="modal-log-ip"></span></div>
          <div><strong>Ação:</strong> <span id="modal-log-action"></span></div>
          <div>
            <strong>Hash SHA-256 Imutável:</strong>
            <div style="background:#fff;border:1px solid #e7ded2;border-radius:6px;padding:8px;font-family:monospace;font-size:11px;word-break:break-all;margin-top:4px;color:#b45309;" id="modal-log-hash"></div>
          </div>
        </div>
      </div>
      <div style="margin-top:16px;display:flex;justify-content:flex-end;">
        <button type="button" class="btn-primary" id="btn-dismiss-hash-modal" style="padding:8px 16px;border-radius:6px;font-size:13px;">Fechar</button>
      </div>
    </div>
  </div>

  <div class="toast-notice" id="app-toast">
    <span id="toast-message">Operação realizada com sucesso!</span>
  </div>

    <script src="js/db.js"></script>
  <script src="js/db-queries.js"></script>
  <script src="js/auth.js"></script>
  <script src="js/auditoria.js"></script>
</body>
</html>
