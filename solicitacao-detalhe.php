<?php
/**
 * Passe Livre Suzano — Dossiê Individual da Solicitação & do Cidadão
 * Proteção server-side: restrito a administradores e gestores municipais.
 * Integração total com MySQL (solicitacoes, usuarios, ordens_servico, auditoria).
 */
require_once __DIR__ . '/includes/auth_guard.php';
require_once __DIR__ . '/includes/geo_centro.php';
$gestorUserRaw = auth_require_admin();
$gestorUser    = auth_user_safe($gestorUserRaw);
$activePage    = 'solicitacoes';

$db = get_db();

// 1. Identifica a solicitação solicitada ou obtém a mais recente
$id       = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$protocol = isset($_GET['protocol']) ? trim($_GET['protocol']) : '';

$solicitacao   = null;
$ordensServico = [];
$cidadao       = null;
$fila          = [];
$totalSols     = 0;
$currentPos    = 1;
$prevId        = null;
$nextId        = null;

try {
    // Total de solicitações cadastradas
    $totalSols = (int)$db->query("SELECT COUNT(*) FROM solicitacoes")->fetchColumn();

    // Se nenhum ID/protocolo foi passado, seleciona a última registrada
    if ($id <= 0 && empty($protocol)) {
        $firstId = $db->query("SELECT id FROM solicitacoes ORDER BY id DESC LIMIT 1")->fetchColumn();
        if ($firstId) {
            $id = (int)$firstId;
        }
    }

    // Busca a solicitação no banco
    if ($id > 0) {
        $stmt = $db->prepare("SELECT * FROM solicitacoes WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $solicitacao = $stmt->fetch();
    } elseif (!empty($protocol)) {
        $stmt = $db->prepare("SELECT * FROM solicitacoes WHERE protocol = ? LIMIT 1");
        $stmt->execute([$protocol]);
        $solicitacao = $stmt->fetch();
        if ($solicitacao) {
            $id = (int)$solicitacao['id'];
        }
    }

    if ($solicitacao) {
        // Badges do cidadão na solicitação
        if (!empty($solicitacao['badges']) && is_string($solicitacao['badges'])) {
            $solicitacao['badges'] = json_decode($solicitacao['badges'], true) ?: [];
        } elseif (!is_array($solicitacao['badges'])) {
            $solicitacao['badges'] = [];
        }

        // Ordens de serviço associadas
        $stmtOs = $db->prepare("
            SELECT os.*, u.name AS gestor_nome 
            FROM ordens_servico os 
            LEFT JOIN usuarios u ON u.id = os.gestor_id 
            WHERE os.solicitacao_id = ? 
            ORDER BY os.data_despacho DESC
        ");
        $stmtOs->execute([$solicitacao['id']]);
        $ordensServico = $stmtOs->fetchAll();

        // Dados do cidadão solicitante
        if (!empty($solicitacao['citizen_id'])) {
            $stmtCid = $db->prepare("SELECT * FROM usuarios WHERE id = ? LIMIT 1");
            $stmtCid->execute([$solicitacao['citizen_id']]);
            $cidadao = $stmtCid->fetch();
            if ($cidadao && !empty($cidadao['pcd_categories']) && is_string($cidadao['pcd_categories'])) {
                $cidadao['pcd_categories'] = json_decode($cidadao['pcd_categories'], true) ?: [];
            }
        }

        // Pager: Anterior e Próxima solicitação
        $stmtPrev = $db->prepare("SELECT id FROM solicitacoes WHERE id < ? ORDER BY id DESC LIMIT 1");
        $stmtPrev->execute([$id]);
        $prevId = $stmtPrev->fetchColumn();

        $stmtNext = $db->prepare("SELECT id FROM solicitacoes WHERE id > ? ORDER BY id ASC LIMIT 1");
        $stmtNext->execute([$id]);
        $nextId = $stmtNext->fetchColumn();

        // Posição no ranking
        $stmtPos = $db->prepare("SELECT COUNT(*) FROM solicitacoes WHERE id <= ?");
        $stmtPos->execute([$id]);
        $currentPos = (int)$stmtPos->fetchColumn();
    }

    // Fila rápida de solicitações (as 8 mais recentes)
    $stmtQueue = $db->query("
        SELECT id, protocol, title, citizen_name, citizen_avatar, status, status_name, address 
        FROM solicitacoes 
        ORDER BY id DESC 
        LIMIT 8
    ");
    $fila = $stmtQueue->fetchAll();

} catch (\Throwable $e) {
    error_log('[solicitacao-detalhe.php] Erro PDO: ' . $e->getMessage());
}

// Helpers de formatação
$statusClassMap = [
    'pending'   => 'pending',
    'analyzing' => 'analyzing',
    'approved'  => 'approved',
    'rejected'  => 'rejected'
];
$statusNameMap = [
    'pending'   => 'Pendente de Triagem',
    'analyzing' => 'Em Análise Técnica',
    'approved'  => 'Aprovada / O.S. Expedida',
    'rejected'  => 'Indeferida'
];

$solStatus     = $solicitacao['status'] ?? 'pending';
$solStatusText = $solicitacao['status_name'] ?? ($statusNameMap[$solStatus] ?? 'Pendente');
$solStatusCls  = $statusClassMap[$solStatus] ?? 'pending';
$solPriority   = $solicitacao['priority'] ?? 'media';
$solDataFormat = !empty($solicitacao['created_at']) ? date('d/m/Y \à\s H:i', strtotime($solicitacao['created_at'])) : 'Data não informada';

// Localização real da solicitação: sem coordenadas no banco não existe ponto no mapa
$solGeo      = pl_geo_centro_valido($solicitacao['latitude'] ?? null, $solicitacao['longitude'] ?? null);
$solTemGeo   = (bool)$solGeo['ok'];
$solLat      = $solGeo['lat'];
$solLng      = $solGeo['lng'];
$solCoordsTxt = $solTemGeo
    ? number_format($solLat, 5, '.', '') . ', ' . number_format($solLng, 5, '.', '')
    : 'não registradas nesta solicitação';

$solCidade = trim((string)($solicitacao['cidade'] ?? ''));
$solUf     = trim((string)($solicitacao['estado'] ?? ''));
$solUfTxt  = $solCidade !== ''
    ? $solCidade . ($solUf !== '' ? ' - ' . $solUf : '')
    : 'município não informado';

// Cores e iniciais do cidadão
$cidName     = $solicitacao['citizen_name'] ?? ($cidadao['name'] ?? 'Cidadão');
$cidEmail    = trim((string)($solicitacao['citizen_email'] ?? ($cidadao['email'] ?? '')));
$cidPhone    = trim((string)($solicitacao['citizen_phone'] ?? ($cidadao['phone'] ?? '')));
$cidBairro   = trim((string)($cidadao['bairro'] ?? ''));
$cidAvatar   = !empty($solicitacao['citizen_avatar']) ? $solicitacao['citizen_avatar'] : (!empty($cidadao['avatar']) ? $cidadao['avatar'] : 'assets/images/mariana-souza.jpg');
$cidPcdLabel = trim((string)($solicitacao['pcd_category'] ?? ($cidadao['pcd_category'] ?? '')));
$cidCidade   = trim((string)($cidadao['city'] ?? ''));
if ($cidCidade === '') {
    $cidCidade = $solCidade !== '' ? $solCidade . ($solUf !== '' ? ' - ' . $solUf : '') : 'cidade não informada';
}
$cidVerificacaoTxt = $cidadao
    ? (!empty($cidadao['email_verified']) ? 'E-mail verificado' : 'E-mail não verificado')
    : 'Conta não localizada no banco';
$cidStatusTxt = $cidadao && trim((string)($cidadao['status'] ?? '')) !== ''
    ? 'Cadastro ' . trim((string)$cidadao['status'])
    : 'Situação cadastral não verificada';

$cidInitials = 'CD';
$parts = preg_split('/\s+/', trim($cidName));
if (count($parts) >= 2) {
    $cidInitials = strtoupper(mb_substr($parts[0], 0, 1) . mb_substr($parts[count($parts)-1], 0, 1));
} elseif (!empty($parts[0])) {
    $cidInitials = strtoupper(mb_substr($parts[0], 0, 2));
}

// Entorno medido a partir das coordenadas reais desta solicitação (500 m)
$solsProximas   = null;
$locaisProx     = 0;
$locaisProxOk   = 0;
$onibusProx     = [];
if ($solTemGeo && !empty($solicitacao['id'])) {
    try {
        $stmtProx = $db->prepare("
            SELECT latitude, longitude
            FROM solicitacoes
            WHERE id <> ? AND latitude IS NOT NULL AND longitude IS NOT NULL
            LIMIT 1000
        ");
        $stmtProx->execute([$solicitacao['id']]);
        $solsProximas = 0;
        foreach ($stmtProx->fetchAll() as $outra) {
            $g = pl_geo_centro_valido($outra['latitude'], $outra['longitude']);
            if ($g['ok'] && pl_geo_distancia_km($solLat, $solLng, $g['lat'], $g['lng']) <= 0.5) {
                $solsProximas++;
            }
        }

        $deltaLat = 0.5 / 111.0;
        $deltaLng = 0.5 / (111.0 * max(0.2, cos(deg2rad($solLat))));
        $stmtEntorno = $db->prepare("
            SELECT lat, lng, access_status, bus_lines
            FROM locais
            WHERE is_archived = 0 AND lat BETWEEN ? AND ? AND lng BETWEEN ? AND ?
            LIMIT 200
        ");
        $stmtEntorno->execute([$solLat - $deltaLat, $solLat + $deltaLat, $solLng - $deltaLng, $solLng + $deltaLng]);
        $linhasVistas = [];
        foreach ($stmtEntorno->fetchAll() as $localProx) {
            if (pl_geo_distancia_km($solLat, $solLng, (float)$localProx['lat'], (float)$localProx['lng']) > 0.5) {
                continue;
            }
            $locaisProx++;
            if (in_array((string)$localProx['access_status'], ['verified', 'full'], true)) {
                $locaisProxOk++;
            }
            foreach (preg_split('/[,;\n]+/', (string)$localProx['bus_lines']) as $linha) {
                $linha = trim($linha, " \t\n\r\0\x0B'\"");
                if ($linha !== '' && !isset($linhasVistas[$linha])) {
                    $linhasVistas[$linha] = true;
                    $onibusProx[] = $linha;
                }
            }
        }
    } catch (\Throwable $e) {
        error_log('[solicitacao-detalhe.php] Entorno: ' . $e->getMessage());
    }
}

$onibusTxt = $solTemGeo
    ? ($onibusProx !== [] ? implode(', ', array_slice($onibusProx, 0, 4)) : 'Nenhum cadastro de linha no raio de 500 m')
    : 'Sem coordenadas: entorno não localizado';
$locaisTxt = $solTemGeo
    ? $locaisProx . ' cadastrados no raio de 500 m (' . $locaisProxOk . ' com acessibilidade verificada)'
    : 'Sem coordenadas: entorno não localizado';

// Histórico real do cidadão na tabela solicitacoes (por id, senão por e-mail)
$cidTotal = 0;
$cidAprov = 0;
$cidDesde = null;
$cidTempoH = null;
$cidOrdens = 0;
$cidRefCol = null;
$cidRefVal = null;
if (!empty($solicitacao['citizen_id'])) {
    $cidRefCol = 'citizen_id';
    $cidRefVal = (int)$solicitacao['citizen_id'];
} elseif ($cidEmail !== '') {
    $cidRefCol = 'citizen_email';
    $cidRefVal = $cidEmail;
}
if ($cidRefCol !== null) {
    try {
        $stmtHist = $db->prepare("
            SELECT COUNT(*) AS total,
                   SUM(status = 'approved') AS aprovadas,
                   MIN(created_at) AS desde
            FROM solicitacoes
            WHERE {$cidRefCol} = ?
        ");
        $stmtHist->execute([$cidRefVal]);
        $hist = $stmtHist->fetch();
        $cidTotal  = (int)($hist['total'] ?? 0);
        $cidAprov  = (int)($hist['aprovadas'] ?? 0);
        $cidDesde  = $hist['desde'] ?? null;

        // Nome da coluna vem de whitelist interna, nunca do usuário
        $stmtOs = $db->prepare("
            SELECT COUNT(*) AS os, AVG(TIMESTAMPDIFF(HOUR, s.created_at, os.data_despacho)) as h
            FROM ordens_servico os
            JOIN solicitacoes s ON s.id = os.solicitacao_id
            WHERE s.{$cidRefCol} = ?
        ");
        $stmtOs->execute([$cidRefVal]);
        $os = $stmtOs->fetch();
        $cidOrdens  = (int)($os['os'] ?? 0);
        $cidTempoH  = isset($os['h']) && $os['h'] !== null ? (float)$os['h'] : null;
    } catch (\Throwable $e) {
        error_log('[solicitacao-detalhe.php] Histórico do cidadão: ' . $e->getMessage());
    }
}
$cidPct      = $cidTotal > 0 ? round($cidAprov * 100 / $cidTotal) : null;
$cidDesdeTxt = $cidDesde ? 'desde ' . date('m/Y', strtotime($cidDesde)) : 'sem histórico no banco';
$cidTempoTxt = $cidTempoH !== null
    ? ($cidTempoH >= 24 ? round($cidTempoH / 24, 1) . ' dias' : round($cidTempoH) . ' horas')
    : 'Sem despacho registrado';

// Órgãos e fiscais já registrados em ordens de serviço anteriores
$orgaos = [];
$fiscais = [];
try {
    $stmtRef = $db->query("SELECT DISTINCT orgao_responsavel, fiscal_responsavel FROM ordens_servico");
    foreach ($stmtRef->fetchAll() as $ref) {
        $o = trim((string)($ref['orgao_responsavel'] ?? ''));
        $f = trim((string)($ref['fiscal_responsavel'] ?? ''));
        if ($o !== '' && !in_array($o, $orgaos, true))   { $orgaos[]  = $o; }
        if ($f !== '' && !in_array($f, $fiscais, true))  { $fiscais[] = $f; }
    }
} catch (\Throwable $e) {
    error_log('[solicitacao-detalhe.php] Órgãos e fiscais: ' . $e->getMessage());
}
$gestorFiscal = trim((string)($gestorUser['name'] ?? ''));
if ($gestorFiscal !== '' && !in_array($gestorFiscal, $fiscais, true)) {
    $fiscais[] = $gestorFiscal;
}
$osMaisRecente = $ordensServico[0] ?? null;
$orgaoSel   = trim((string)($osMaisRecente['orgao_responsavel'] ?? ''));
$fiscalSel  = trim((string)($osMaisRecente['fiscal_responsavel'] ?? ''));
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dossiê Individual do Cidadão — Solicitação <?= $solicitacao ? htmlspecialchars($solicitacao['protocol']) : 'Detalhe' ?> | Painel do Gestor Passe Livre</title>
  <meta name="description" content="Dossiê individual da solicitação de acessibilidade viária, com o endereço e as coordenadas informados pelo cidadão.">

  <!-- Fontes Locais -->
  <link rel="stylesheet" href="assets/fonts/fonts.css">

  <!-- Estilos Globais e do Painel do Gestor -->
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="css/gestor.css">
  <link rel="stylesheet" href="css/solicitacoes.css">
  <link rel="stylesheet" href="css/solicitacao-detalhe.css">
  <link rel="stylesheet" href="css/leaflet.css">
  <link rel="icon" type="image/x-icon" href="favicon.ico">
  <link rel="icon" type="image/png" sizes="32x32" href="assets/icons/favicon-32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="assets/icons/favicon-16.png">
  <link rel="apple-touch-icon" href="assets/icons/apple-touch-icon.png">
</head>
<body>
  <a href="#main-content" class="skip-link">Pular para o conteúdo principal</a>

  <div class="gestor-layout-wrapper">
    
    <!-- Sidebar Gestor Unificada -->
    <?php require __DIR__ . '/includes/sidebar_gestor.php'; ?>

    <!-- Área Principal de Conteúdo -->
    <div class="gestor-content-area">
      
      <!-- Top Bar com Breadcrumbs e Perfil -->
      <header class="gestor-top-bar">
        <div class="gestor-title-crumb-wrap">
          <button class="btn-gestor-menu-toggle" id="btn-gestor-sidebar-toggle" aria-label="Abrir menu lateral" aria-expanded="false">&#9776;</button>
          <div>
            <div class="gestor-breadcrumb-text">
              <a href="dashboard.php" style="color: inherit; text-decoration: none;">Passe Livre</a> / 
              <a href="solicitacoes.php" style="color: inherit; text-decoration: none;">Solicitações</a> / 
              <span style="color: #ea580c; font-weight: 700;">Dossiê Individual: <?= $solicitacao ? htmlspecialchars($solicitacao['protocol']) : 'Não encontrada' ?></span>
            </div>
            <h1 class="gestor-main-heading">Visualização Individual da Ocorrência</h1>
          </div>
        </div>

        <div class="gestor-header-search-wrap">
          <img src="assets/icons/search.svg" alt="" aria-hidden="true">
          <input type="search" id="input-search-dossier" placeholder="Buscar por protocolo, cidadão ou logradouro..." aria-label="Buscar ocorrências">
        </div>

        <div style="display: flex; align-items: center; gap: 18px;">
          <button class="btn-icon-notify" id="btn-notify-top" title="Notificações" onclick="window.location.href='notificacoes.php'">
            <img src="assets/icons/bell.svg" alt="">
          </button>

          <div class="gestor-header-profile-tag" style="cursor: pointer;" onclick="window.location.href='perfil-gestor.php'">
            <div class="gestor-avatar-badge-circle"><?= htmlspecialchars($gestorUser['initials']) ?></div>
            <div class="gestor-admin-tag-text">
              <?= pl_admin_header_profile($gestorUser) ?>
            </div>
          </div>
        </div>
      </header>

      <!-- Corpo Principal -->
      <main id="main-content" class="gestor-page-body">
        
        <?php if (!$solicitacao): ?>
          <div style="background:#ffffff;border:1px solid #ede4d8;border-radius:16px;padding:64px 32px;text-align:center;max-width:700px;margin:40px auto;box-shadow:0 2px 10px rgba(0,0,0,0.03);">
            <div style="font-size:48px;margin-bottom:16px;">🔍</div>
            <h2 style="font-size:22px;color:#1c1917;margin-bottom:8px;">Solicitação não encontrada</h2>
            <p style="color:#78716c;font-size:14px;margin-bottom:24px;">O número de identificação ou protocolo solicitado não existe ou foi removido do banco de dados.</p>
            <a href="solicitacoes.php" class="btn-dossier-action btn-dossier-success" style="display:inline-flex;text-decoration:none;padding:10px 24px;">
              &larr; Retornar à Lista de Solicitações
            </a>
          </div>
        <?php else: ?>

        <div class="dossier-page-layout">

          <!-- ---------------------------------------------------------------
               A. BARRA SUPERIOR DE AÇÕES & NAVEGAÇÃO ENTRE USUÁRIOS
               --------------------------------------------------------------- -->
          <section class="dossier-action-bar" aria-label="Ações e navegação entre solicitações">
            <div class="action-bar-left">
              <a href="solicitacoes.php" class="btn-back-to-list" title="Retornar à tabela de ocorrências">
                <span>&larr;</span>
                <span>Voltar para Lista de Solicitações</span>
              </a>

              <div class="user-quick-pager">
                <button type="button" class="btn-pager-nav" id="btn-prev-solicitacao" title="Solicitação anterior" <?= !$prevId ? 'disabled style="opacity:0.4;cursor:not-allowed;"' : 'onclick="window.location.href=\'solicitacao-detalhe.php?id=' . $prevId . '\'"' ?>>&larr;</button>
                <span class="pager-counter-text" id="pager-counter-display">Solicitação <?= $currentPos ?> de <?= max(1, $totalSols) ?></span>
                <button type="button" class="btn-pager-nav" id="btn-next-solicitacao" title="Próxima solicitação" <?= !$nextId ? 'disabled style="opacity:0.4;cursor:not-allowed;"' : 'onclick="window.location.href=\'solicitacao-detalhe.php?id=' . $nextId . '\'"' ?>>&rarr;</button>
              </div>
            </div>

            <div class="action-bar-right">
              <button type="button" class="btn-dossier-action btn-dossier-print" id="btn-print-dossier" onclick="window.print()" title="Imprimir ou salvar dossiê em PDF">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <polyline points="6 9 6 2 18 2 18 9"></polyline>
                  <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
                  <rect x="6" y="14" width="12" height="8"></rect>
                </svg>
                <span>Imprimir Dossiê</span>
              </button>

              <button type="button" class="btn-dossier-action btn-dossier-danger" id="btn-action-reject" onclick="triageSolicitacao('recusar')" title="Indeferir ocorrência">
                <span>Indeferir</span>
              </button>

              <button type="button" class="btn-dossier-action btn-dossier-amber" id="btn-action-complement" onclick="triageSolicitacao('reanalise')" title="Solicitar diligência ou mais fotos ao cidadão">
                <span>Pedir Complementação</span>
              </button>

              <button type="button" class="btn-dossier-action btn-dossier-success" id="btn-action-approve" onclick="triageSolicitacao('aprovar')" title="Aprovar e emitir ordem de serviço">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                  <polyline points="20 6 9 17 4 12"></polyline>
                </svg>
                <span>Aprovar &amp; Emitir O.S.</span>
              </button>
            </div>
          </section>

          <!-- ---------------------------------------------------------------
               B. GRID DE DUAS COLUNAS
               --------------------------------------------------------------- -->
          <div class="dossier-main-grid">

            <!-- COLUNA DA ESQUERDA (PERFIL DO CIDADÃO + DETALHES + DESPACHO) -->
            <div class="dossier-col">

              <!-- 1. CARD: PERFIL INDIVIDUAL DO CIDADÃO SOLICITANTE -->
              <article class="user-profile-hero-card" aria-label="Perfil do Cidadão Solicitante">
                <div class="user-hero-top-row">
                  <div class="user-avatar-large-wrap">
                    <img src="<?= htmlspecialchars($cidAvatar) ?>" alt="Foto de <?= htmlspecialchars($cidName) ?>" class="user-avatar-large-img" id="user-display-avatar" onerror="this.src='assets/images/avatar.jpg'">
                    <div class="avatar-verified-badge" title="<?= htmlspecialchars($cidVerificacaoTxt) ?>">
                      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                        <polyline points="20 6 9 17 4 12"></polyline>
                      </svg>
                    </div>
                  </div>

                  <div class="user-meta-heading-col">
                    <div style="display: flex; align-items: center; justify-content: space-between;">
                      <h2 class="user-full-name" id="user-display-name"><?= htmlspecialchars($cidName) ?></h2>
                      <span style="font-size: 11px; color: #78716c; font-weight: 500;" id="user-display-age"><?php if ($cidBairro !== ''): ?><?= htmlspecialchars($cidBairro) ?> &bull; <?php endif; ?><?= htmlspecialchars($cidCidade) ?></span>
                    </div>

                    <div class="user-tags-badges-row">
                      <span class="tag-citizen-verified">
                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                          <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                        </svg>
                        <span><?= htmlspecialchars($cidVerificacaoTxt) ?></span>
                      </span>

                      <span class="tag-pcd-condition" id="user-display-pcd">
                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                          <circle cx="12" cy="12" r="10"></circle>
                          <line x1="12" y1="8" x2="12" y2="12"></line>
                          <line x1="12" y1="16" x2="12.01" y2="16"></line>
                        </svg>
                        <span id="user-pcd-label"><?= $cidPcdLabel !== '' ? htmlspecialchars($cidPcdLabel) : 'Categoria PcD não informada' ?></span>
                      </span>

                      <span class="tag-community-gold">
                        <svg width="11" height="11" viewBox="0 0 24 24" fill="currentColor">
                          <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>
                        </svg>
                        <span><?= htmlspecialchars($cidStatusTxt) ?></span>
                      </span>
                    </div>
                  </div>
                </div>

                <!-- 4 Caixas de Métricas do Cidadão (calculadas do banco) -->
                <div class="user-metrics-quad-grid">
                  <div class="user-metric-box">
                    <span class="user-metric-label">Total Solicitado</span>
                    <span class="user-metric-value" id="user-metric-total"><?= $cidTotal > 0 ? $cidTotal . ' demanda(s)' : 'Nenhum registro' ?></span>
                    <span class="user-metric-sub"><?= htmlspecialchars($cidDesdeTxt) ?></span>
                  </div>

                  <div class="user-metric-box">
                    <span class="user-metric-label">Atendidas</span>
                    <span class="user-metric-value" style="color: #166534;" id="user-metric-approved"><?= $cidAprov > 0 ? $cidAprov . ' aprovada(s)' : 'Nenhuma aprovada' ?></span>
                    <span class="user-metric-sub"><?= $cidPct !== null ? $cidPct . '% das solicitações do cadastro' : 'Sem base para percentual' ?></span>
                  </div>

                  <div class="user-metric-box">
                    <span class="user-metric-label">Tempo Médio</span>
                    <span class="user-metric-value" style="color: #92400e;" id="user-metric-time"><?= htmlspecialchars($cidTempoTxt) ?></span>
                    <span class="user-metric-sub">Do registro à ordem de serviço</span>
                  </div>

                  <div class="user-metric-box">
                    <span class="user-metric-label">Ordens de Serviço</span>
                    <span class="user-metric-value" style="color: #ea580c;" id="user-metric-score"><?= $cidOrdens > 0 ? $cidOrdens . ' expedida(s)' : 'Nenhuma expedida' ?></span>
                    <span class="user-metric-sub">Registradas para este cidadão</span>
                  </div>
                </div>

                <!-- Contato e Dados LGPD -->
                <div class="user-contact-details-box">
                  <div class="contact-item-group">
                    <span class="contact-label">E-mail Cadastrado</span>
                    <div class="contact-value-row">
                      <span id="user-display-email"><?= $cidEmail !== '' ? htmlspecialchars($cidEmail) : 'Não informado no cadastro' ?></span>
                      <button type="button" class="btn-copy-chip" onclick="copyContact('email')" title="Copiar e-mail">Copiar</button>
                    </div>
                  </div>

                  <div class="contact-item-group">
                    <span class="contact-label">Telefone / WhatsApp</span>
                    <div class="contact-value-row">
                      <span id="user-display-phone"><?= $cidPhone !== '' ? htmlspecialchars($cidPhone) : 'Não informado no cadastro' ?></span>
                      <button type="button" class="btn-copy-chip" onclick="copyContact('phone')" title="Copiar telefone">Copiar</button>
                    </div>
                  </div>

                  <div class="contact-item-group">
                    <span class="contact-label">Bairro de Residência</span>
                    <div class="contact-value-row">
                      <span id="user-display-neighborhood"><?= $cidBairro !== '' ? htmlspecialchars($cidBairro) . ' — ' . htmlspecialchars($cidCidade) : 'Bairro não informado no cadastro' ?></span>
                    </div>
                  </div>

                  <div class="contact-item-group">
                    <span class="contact-label">UBS de Referência PcD</span>
                    <div class="contact-value-row">
                      <span id="user-display-ubs">Não informada no cadastro</span>
                    </div>
                  </div>
                </div>
              </article>

              <!-- 2. CARD: DETALHES DA OCORRÊNCIA / SOLICITAÇÃO ATUAL -->
              <article class="request-details-dossier-card" aria-label="Detalhes da Solicitação">
                <div class="card-section-title-row">
                  <div class="card-title-with-icon">
                    <div class="card-title-icon-badge">
                      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="8" x2="12" y2="12"></line>
                        <line x1="12" y1="16" x2="12.01" y2="16"></line>
                      </svg>
                    </div>
                    <div>
                      <h3 class="card-section-h3" id="req-display-title"><?= htmlspecialchars($solicitacao['title']) ?></h3>
                      <span style="font-size: 11px; color: #78716c;" id="req-display-protocol">Protocolo #<?= htmlspecialchars($solicitacao['protocol']) ?> &bull; ID #<?= $solicitacao['id'] ?></span>
                    </div>
                  </div>

                  <span class="badge-risk-critical" id="req-display-risk" style="<?= $solPriority === 'urgente' || $solPriority === 'alta' ? 'background:#fef2f2;color:#b91c1c;border:1px solid #fecaca;' : 'background:#fefce8;color:#a16207;border:1px solid #fef08a;' ?>">
                    <span style="width: 6px; height: 6px; border-radius: 50%; background: <?= $solPriority === 'urgente' || $solPriority === 'alta' ? '#dc2626' : '#eab308' ?>; display: inline-block;"></span>
                    <span>Prioridade: <?= htmlspecialchars(ucfirst($solPriority)) ?></span>
                  </span>
                </div>

                <!-- Dados da Ocorrência -->
                <div class="occurrence-info-grid">
                  <div class="occurrence-info-item">
                    <span class="occ-label">Localização Exata</span>
                    <span class="occ-val" id="req-display-address"><?= htmlspecialchars($solicitacao['address']) ?></span>
                  </div>

                  <div class="occurrence-info-item">
                    <span class="occ-label">Ponto de Referência</span>
                    <span class="occ-val" id="req-display-ref"><?= !empty($solicitacao['complement']) ? htmlspecialchars($solicitacao['complement']) : 'Não informado no registro' ?></span>
                  </div>

                  <div class="occurrence-info-item">
                    <span class="occ-label">Data e Hora de Envio</span>
                    <span class="occ-val" id="req-display-date"><?= $solDataFormat ?></span>
                  </div>

                  <div class="occurrence-info-item">
                    <span class="occ-label">Status da Triagem</span>
                    <span class="occ-val" id="req-display-status" style="font-weight:700;">
                      <span class="status-badge-pill <?= $solStatusCls ?>"><?= htmlspecialchars($solStatusText) ?></span>
                    </span>
                  </div>
                </div>

                <!-- Relato do Cidadão (Testemunho) -->
                <div class="citizen-testimony-box">
                  <div class="citizen-quote-header">
                    <span>DEPOIMENTO REGISTRADO PELO CIDADÃO:</span>
                    <span style="font-size: 10.5px; opacity: 0.8;">Texto enviado no formulário</span>
                  </div>
                  <p class="citizen-quote-text" id="req-display-quote">
                    &ldquo;<?= htmlspecialchars($solicitacao['description'] ?: 'Sem descrição detalhada registrada.') ?>&rdquo;
                  </p>

                  <?php $audioUrl = trim((string)($solicitacao['audio_url'] ?? '')); ?>
                  <?php if ($audioUrl !== ''): ?>
                    <div class="voice-memo-player-box">
                      <audio controls preload="none" src="<?= htmlspecialchars($audioUrl) ?>">Seu navegador não reproduz esta gravação.</audio>
                    </div>
                  <?php else: ?>
                    <p class="voice-memo-empty">Gravação de áudio não anexada a esta solicitação.</p>
                  <?php endif; ?>
                </div>

                <!-- Evidências Fotográficas do Cidadão -->
                <div class="evidence-photos-wrap">
                  <span class="evidence-header-label">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path>
                      <circle cx="12" cy="13" r="4"></circle>
                    </svg>
                    <span>Fotografias Anexadas pelo Solicitante:</span>
                  </span>

                  <div class="evidence-grid">
                    <?php $fotoAnexa = trim((string)($solicitacao['photo'] ?? '')); ?>
                    <?php if ($fotoAnexa !== ''): ?>
                      <div class="evidence-photo-card" id="evidence-photo-1" onclick="openLightbox('<?= htmlspecialchars($fotoAnexa) ?>', 'Imagem anexada no registro desta solicitação')" title="Clique para ampliar a imagem">
                        <img src="<?= htmlspecialchars($fotoAnexa) ?>" alt="Imagem anexada à solicitação" id="photo-evid-1" onerror="this.src='assets/images/map-background.jpg'">
                        <div class="photo-caption-bar">
                          <span>Anexo do envio</span>
                          <span style="color: #fde68a;">Foto 1</span>
                        </div>
                      </div>
                    <?php else: ?>
                      <div class="evidence-empty">Nenhuma fotografia anexada neste registro.</div>
                    <?php endif; ?>
                  </div>
                </div>

              </article>

              <!-- 3. CARD: DESPACHO E LINHA DO TEMPO DA FISCALIZAÇÃO -->
              <article class="dispatch-action-card" aria-label="Despacho da Junta Técnica">
                <div class="card-section-title-row">
                  <div class="card-title-with-icon">
                    <div class="card-title-icon-badge" style="background-color: #ecfdf5; color: #059669;">
                      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="9 11 12 14 22 4"></polyline>
                        <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path>
                      </svg>
                    </div>
                    <div>
                      <h3 class="card-section-h3">Triagem Técnica &amp; Despacho Oficial</h3>
                      <span style="font-size: 11px; color: #78716c;">Responsável: <?= htmlspecialchars($gestorUser['name']) ?> (<?= htmlspecialchars($gestorUser['role']) ?>)</span>
                    </div>
                  </div>
                </div>

                <!-- Linha do Tempo Visual do Workflow -->
                <div class="workflow-timeline">
                  <div class="timeline-step-node completed">
                    <div class="step-circle-icon">&check;</div>
                    <span class="step-label-title">Envio Cidadão</span>
                    <span class="step-label-time"><?= date('H:i', strtotime($solicitacao['created_at'] ?? 'now')) ?></span>
                  </div>

                  <div class="timeline-step-node completed">
                    <div class="step-circle-icon">&check;</div>
                    <span class="step-label-title">Registro na Plataforma</span>
                    <span class="step-label-time">Recebido</span>
                  </div>

                  <div class="timeline-step-node <?= $solStatus === 'pending' || $solStatus === 'analyzing' ? 'current' : 'completed' ?>">
                    <div class="step-circle-icon"><?= $solStatus === 'approved' || $solStatus === 'rejected' ? '&check;' : '3' ?></div>
                    <span class="step-label-title">Triagem Gestor</span>
                    <span class="step-label-time"><?= $solStatusText ?></span>
                  </div>

                  <div class="timeline-step-node <?= count($ordensServico) > 0 ? 'completed' : ($solStatus === 'approved' ? 'current' : '') ?>">
                    <div class="step-circle-icon"><?= count($ordensServico) > 0 ? '&check;' : '4' ?></div>
                    <span class="step-label-title">Ordem de Serviço</span>
                    <span class="step-label-time"><?= count($ordensServico) > 0 ? 'Expedida' : 'Pendente' ?></span>
                  </div>

                  <div class="timeline-step-node <?= $solStatus === 'approved' && count($ordensServico) > 0 ? 'completed' : '' ?>">
                    <div class="step-circle-icon">5</div>
                    <span class="step-label-title">Execução Obras</span>
                    <span class="step-label-time">Sem prazo registrado</span>
                  </div>
                </div>

                <?php if (count($ordensServico) > 0): ?>
                  <!-- Histórico de Ordens de Serviço já Expedidas -->
                  <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:12px;padding:14px;margin-bottom:18px;">
                    <div style="font-weight:700;color:#166534;font-size:13px;margin-bottom:8px;">Ordens de Serviço Emitidas:</div>
                    <?php foreach ($ordensServico as $os): ?>
                      <div style="font-size:12px;color:#14532d;padding:6px 0;border-bottom:1px dashed #bbf7d0;">
                        <strong><?= htmlspecialchars($os['numero_os']) ?></strong> &bull; <?= htmlspecialchars($os['orgao_responsavel']) ?> &bull; Fiscal: <?= htmlspecialchars($os['fiscal_responsavel']) ?> &bull; Data: <?= date('d/m/Y H:i', strtotime($os['data_despacho'])) ?>
                        <div style="color:#374151;margin-top:2px;font-style:italic;">&ldquo;<?= htmlspecialchars($os['parecer']) ?>&rdquo;</div>
                      </div>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>

                <!-- Formulário de Despacho Técnico -->
                <form id="form-triage-dispatch" onsubmit="return false;" class="triage-form-group">
                  <input type="hidden" id="solicitacao-id-hidden" value="<?= $solicitacao['id'] ?>">

                  <div class="form-row-pair">
                    <div class="form-item-col">
                      <label for="dispatch-department">Secretaria / Órgão Competente:</label>
                      <input type="text" id="dispatch-department" class="dossier-input" list="dl-orgaos"
                             placeholder="Secretaria ou órgão responsável pela intervenção"
                             value="<?= htmlspecialchars($orgaoSel) ?>">
                      <datalist id="dl-orgaos">
                        <?php foreach ($orgaos as $o): ?>
                          <option value="<?= htmlspecialchars($o) ?>"></option>
                        <?php endforeach; ?>
                      </datalist>
                    </div>

                    <div class="form-item-col">
                      <label for="dispatch-tech">Engenheiro / Fiscal Designado:</label>
                      <input type="text" id="dispatch-tech" class="dossier-input" list="dl-fiscais"
                             placeholder="Nome do fiscal responsável pelo despacho"
                             value="<?= htmlspecialchars($fiscalSel !== '' ? $fiscalSel : $gestorFiscal) ?>">
                      <datalist id="dl-fiscais">
                        <?php foreach ($fiscais as $f): ?>
                          <option value="<?= htmlspecialchars($f) ?>"></option>
                        <?php endforeach; ?>
                      </datalist>
                    </div>
                  </div>

                  <div class="form-row-pair">
                    <div class="form-item-col">
                      <label for="dispatch-priority">Prioridade da Ordem de Serviço:</label>
                      <select id="dispatch-priority" class="dossier-select">
                        <option value="urgente" <?= $solPriority === 'urgente' ? 'selected' : '' ?>>Urgência Máxima</option>
                        <option value="alta" <?= $solPriority === 'alta' ? 'selected' : '' ?>>Alta Prioridade</option>
                        <option value="media" <?= $solPriority === 'media' ? 'selected' : '' ?>>Normal / Rotina</option>
                      </select>
                    </div>

                    <div class="form-item-col">
                      <label for="dispatch-equipment">Equipamento / Recurso Exigido:</label>
                      <input type="text" id="dispatch-equipment" class="dossier-input" placeholder="Definido pela junta técnica no parecer">
                    </div>
                  </div>

                  <div class="form-item-col">
                    <label for="dispatch-notes">Parecer Pericial &amp; Instruções para a Equipe de Campo:</label>
                    <textarea id="dispatch-notes" rows="3" class="dossier-input" style="resize: vertical;" placeholder="Descreva o despacho técnico. Registre aqui apenas vistorias e constatações realmente realizadas."></textarea>
                    
                    <!-- Chips de Textos Pré-formatados -->
                    <div class="quick-templates-row">
                      <span style="font-size: 10px; color: #a8a29e;">Modelos rápidos:</span>
                      <button type="button" class="template-chip-btn" onclick="applyTemplate('troca')">Troca de Botoeira APS</button>
                      <button type="button" class="template-chip-btn" onclick="applyTemplate('vistoria')">Vistoria Presencial</button>
                      <button type="button" class="template-chip-btn" onclick="applyTemplate('rebaixamento')">Rebaixamento de Guia</button>
                    </div>
                  </div>

                  <div style="display: flex; align-items: center; justify-content: flex-end; gap: 10px; margin-top: 6px;">
                    <button type="button" class="btn-dossier-action btn-dossier-amber" onclick="triageSolicitacao('reanalise')">
                      Solicitar Complementação
                    </button>
                    <button type="button" class="btn-dossier-action btn-dossier-success" id="btn-save-dispatch" onclick="despacharOrdemServico()">
                      <span>Despachar Ordem de Serviço &rarr;</span>
                    </button>
                  </div>
                </form>

              </article>

            </div>

            <!-- COLUNA DA DIREITA (MAPA DE IMPACTO + FILA RÁPIDA DE USUÁRIOS) -->
            <div class="dossier-col">

              <!-- 1. CARD: CONTEXTO GEOGRÁFICO & IMPACTO URBANO -->
              <article class="map-context-card" aria-label="Contexto Geográfico">
                <div class="card-section-title-row" style="margin-bottom: 12px;">
                  <div class="card-title-with-icon">
                    <div class="card-title-icon-badge" style="background-color: #eff6ff; color: #2563eb;">
                      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polygon points="1 6 1 22 8 18 16 22 23 18 23 2 16 6 8 2 1 6"></polygon>
                      </svg>
                    </div>
                    <div>
                      <h3 class="card-section-h3" style="font-size: 15px;">Localização informada na solicitação</h3>
                      <span style="font-size: 10.5px; color: #78716c;" id="detail-coords">Coordenadas: <?= htmlspecialchars($solCoordsTxt) ?></span>
                    </div>
                  </div>
                </div>

                <!-- Visualizador de Mapa: sem coordenadas no banco não existe ponto a mostrar -->
                <div class="map-view-box">
                  <?php if ($solTemGeo): ?>
                    <div
                      id="sol-minimap"
                      data-lat="<?= htmlspecialchars((string)$solLat) ?>"
                      data-lng="<?= htmlspecialchars((string)$solLng) ?>"
                      aria-label="Mapa do ponto informado nesta solicitação"
                    ></div>
                  <?php else: ?>
                    <div class="map-no-coords">
                      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                        <line x1="12" y1="8" x2="12" y2="12"></line>
                        <line x1="12" y1="16" x2="12.01" y2="16"></line>
                      </svg>
                      <strong>Endereço sem coordenadas no cadastro</strong>
                      <span>A solicitação traz apenas o texto do endereço, então não é possível posicioná-la no mapa.</span>
                    </div>
                  <?php endif; ?>
                </div>

                <!-- Estatísticas calculadas a partir do banco -->
                <div class="map-impact-stats">
                  <div class="map-impact-line">
                    <span>Município da ocorrência:</span>
                    <strong id="detail-municipio"><?= htmlspecialchars($solUfTxt) ?></strong>
                  </div>

                  <div class="map-impact-line">
                    <span>Raio de Análise do Entorno:</span>
                    <strong id="detail-impact-radius"><?= $solTemGeo ? '500 m &bull; ' . (int)$solsProximas . ' outra(s) solicitação(ões)' : 'Não aplicado: sem coordenadas' ?></strong>
                  </div>

                  <div class="map-impact-line">
                    <span>Linhas de Ônibus no Entorno:</span>
                    <strong style="color: #ea580c;" id="detail-bus-lines"><?= htmlspecialchars($onibusTxt) ?></strong>
                  </div>

                  <div class="map-impact-line">
                    <span>Locais no Raio de 500 m:</span>
                    <strong id="detail-locais-count"><?= htmlspecialchars($locaisTxt) ?></strong>
                  </div>

                  <div class="map-impact-line">
                    <span>Fluxo Viário Médio da Via:</span>
                    <strong id="detail-traffic-flow">Sem medição nesta plataforma</strong>
                  </div>
                </div>

                <a href="mapa-gestor.php" class="btn-dossier-action btn-dossier-print" style="width: 100%; justify-content: center; margin-top: 14px; text-decoration: none;">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="11" cy="11" r="8"></circle>
                    <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                  </svg>
                  <span>Abrir no Mapa Interativo de Gestão</span>
                </a>
              </article>

              <!-- 2. CARD: FILA RÁPIDA DE USUÁRIOS E SOLICITAÇÕES -->
              <article class="quick-users-queue-card" aria-label="Fila de Solicitações">
                <div class="card-section-title-row" style="margin-bottom: 6px;">
                  <div>
                    <h3 class="card-section-h3" style="font-size: 15px;">Fila de Solicitações (<?= count($fila) ?>)</h3>
                    <p style="font-size: 11px; color: #78716c;">Alterne instantaneamente para visualizar outro cidadão</p>
                  </div>
                  <span style="font-size: 10.5px; background: #fff7ed; color: #ea580c; font-weight: 700; padding: 2px 7px; border-radius: 9999px;">
                    <?= $totalSols ?> cadastradas
                  </span>
                </div>

                <div class="queue-items-list" id="queue-users-container">
                  <?php foreach ($fila as $item): 
                    $isCur = ((int)$item['id'] === (int)$solicitacao['id']);
                    $itemStatusCls = $statusClassMap[$item['status']] ?? 'pending';
                    $itemInit = 'CD';
                    $iParts = preg_split('/\s+/', trim($item['citizen_name'] ?? ''));
                    if (count($iParts) >= 2) {
                        $itemInit = strtoupper(mb_substr($iParts[0],0,1) . mb_substr($iParts[count($iParts)-1],0,1));
                    } elseif (!empty($iParts[0])) {
                        $itemInit = strtoupper(mb_substr($iParts[0],0,2));
                    }
                  ?>
                    <div class="queue-user-item <?= $isCur ? 'active' : '' ?>" onclick="window.location.href='solicitacao-detalhe.php?id=<?= $item['id'] ?>'" style="cursor: pointer;">
                      <div class="queue-user-left">
                        <div class="queue-avatar-sm" style="background: <?= $isCur ? '#fde68a' : '#e0e7ff' ?>; color: <?= $isCur ? '#92400e' : '#3730a3' ?>;"><?= htmlspecialchars($itemInit) ?></div>
                        <div class="queue-meta-col">
                          <span class="queue-user-name"><?= htmlspecialchars($item['citizen_name']) ?> (#<?= htmlspecialchars($item['protocol']) ?>)</span>
                          <span class="queue-occurrence-title"><?= htmlspecialchars(mb_strimwidth($item['title'], 0, 32, '...')) ?></span>
                        </div>
                      </div>
                      <span class="queue-status-tag <?= $itemStatusCls ?>"><?= htmlspecialchars($item['status_name'] ?: ucfirst($item['status'])) ?></span>
                    </div>
                  <?php endforeach; ?>
                </div>
              </article>

            </div>

          </div>

        </div>
        <?php endif; ?>

      </main>

      <!-- Rodapé do Gestor -->
      <footer class="site-footer" role="contentinfo" style="margin-top: auto; padding: 16px 32px; background: #fdfaf6; border-top: 1px solid #ede4d8; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; font-size: 11px; color: #78716c;">
        <div>
          &copy; 2026 Passe Livre — Plataforma de Acessibilidade Urbana. Desenvolvido por SENAI Suzano.
        </div>
        <div style="display: flex; gap: 14px;">
          <a href="direitos.php#termos" style="color: #78716c; text-decoration: none;">Termos de Uso</a>
          <span>&bull;</span>
          <a href="direitos.php#privacidade" style="color: #78716c; text-decoration: none;">Privacidade (LGPD)</a>
          <span>&bull;</span>
          <a href="ajuda.php#suporte" style="color: #78716c; text-decoration: none;">Suporte</a>
        </div>
      </footer>

    </div>
  </div>

  <!-- Modal Lightbox para Ampliação de Fotos -->
  <div class="modal-overlay" id="modal-evidence-lightbox" role="dialog" aria-modal="true" aria-label="Visualizador de Evidência">
    <div class="modal-window" style="max-width: 720px; padding: 14px; background: #1c1917; color: #ffffff;">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
        <span id="lightbox-title-text" style="font-size: 13px; font-weight: 700; color: #fed7aa;">Evidência Fotográfica do Cidadão</span>
        <button type="button" class="btn-modal-close" id="btn-close-lightbox" onclick="closeLightbox()" style="color: #ffffff; background: rgba(255,255,255,0.15);">&times;</button>
      </div>
      <div style="max-height: 480px; overflow: hidden; border-radius: 10px; display: flex; align-items: center; justify-content: center; background: #000;">
        <img id="lightbox-img" src="assets/images/semaforo-evidencia.jpg" alt="Foto ampliada" style="width: 100%; height: auto; max-height: 460px; object-fit: contain;">
      </div>
      <p id="lightbox-caption-text" style="font-size: 11px; color: #d6d3d1; margin-top: 8px; text-align: center;">Evidência registrada no ponto informado</p>
    </div>
  </div>

  <!-- Toast de Notificação -->
  <div class="toast-notice" id="app-toast">
    <img src="assets/icons/check.svg" width="18" height="18" alt="" onerror="this.style.display='none'">
    <span id="toast-message">Ação realizada com sucesso!</span>
  </div>

  <!-- Scripts -->
  <script src="js/auth.js"></script>
  <?php if ($solTemGeo): ?>
  <script src="js/leaflet.js"></script>
  <?php endif; ?>
  <script src="js/solicitacao-detalhe.js"></script>
</body>
</html>
