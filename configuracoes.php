<?php
/**
 * Passe Livre Suzano — Configurações Gerais da Plataforma (Painel do Gestor)
 * Proteção server-side: apenas admins/gestores.
 * Conexão direta com banco MySQL via PDO.
 */
require_once __DIR__ . '/includes/auth_guard.php';
$gestorUserRaw = auth_require_admin();
$gestorUser    = auth_user_safe($gestorUserRaw);
$activePage    = 'configuracoes';

// Estatísticas reais do MySQL
require_once __DIR__ . '/api/_db_restore.php';
$db = get_db();
$countUsers  = (int) $db->query("SELECT COUNT(*) FROM usuarios")->fetchColumn();
$countLocais = (int) $db->query("SELECT COUNT(*) FROM locais")->fetchColumn();
$countSols   = (int) $db->query("SELECT COUNT(*) FROM solicitacoes")->fetchColumn();
$countAvals  = (int) $db->query("SELECT COUNT(*) FROM avaliacoes")->fetchColumn();
$countNotifs = (int) $db->query("SELECT COUNT(*) FROM notificacoes")->fetchColumn();
$countAudit  = (int) $db->query("SELECT COUNT(*) FROM auditoria")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Configurações da Plataforma — Painel do Gestor | Passe Livre Suzano</title>
  <meta name="description" content="Parâmetros do sistema, integrações governamentais, conformidade LGPD e permissões institucionais do Passe Livre Suzano.">
  <link rel="stylesheet" href="assets/fonts/fonts.css">
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="css/gestor.css">
  <link rel="stylesheet" href="css/configuracoes.css">
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

    <!-- Área Principal de Conteúdo -->
    <div class="gestor-content-area">
      <!-- Barra Superior -->
      <header class="gestor-top-bar">
        <div class="gestor-title-crumb-wrap">
          <button class="btn-gestor-menu-toggle" id="btn-gestor-sidebar-toggle" aria-label="Abrir menu lateral" aria-expanded="false">&#9776;</button>
          <div>
            <div class="gestor-breadcrumb-text">
              <span>Painel do Gestor</span> &gt; <span>Configurações</span>
            </div>
            <h1 class="gestor-main-heading">Configuração Geral da Plataforma</h1>
          </div>
        </div>

        <div class="gestor-header-search-wrap">
          <img src="assets/icons/search.svg" alt="" aria-hidden="true">
          <input type="search" placeholder="Buscar por termo ou chave..." aria-label="Buscar configurações">
        </div>

        <div style="display: flex; align-items: center; gap: 18px;">
          <div style="display: inline-flex; align-items: center; gap: 6px; background: #ecfdf5; border: 1px solid #a7f3d0; border-radius: 9999px; padding: 4px 12px; font-size: 11px; font-weight: 700; color: #065f46;">
            <span style="width: 7px; height: 7px; border-radius: 50%; background: #10b981;"></span>
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

      <!-- Corpo da Página -->
      <main id="main-content" class="gestor-page-body">
        
        <!-- Navegação em Abas Superiores -->
        <nav class="gestor-tabs-nav" aria-label="Abas de Configurações">
          <button class="tab-nav-btn active" data-tab="geral">
            <img src="assets/icons/home.svg" alt="" aria-hidden="true" onerror="this.src='assets/icons/settings.svg'">
            <span>Geral &amp; Município</span>
          </button>

          <button class="tab-nav-btn" data-tab="regras">
            <img src="assets/icons/clipboard-check.svg" alt="" aria-hidden="true">
            <span>Regras de Concessão PCD</span>
          </button>

          <button class="tab-nav-btn" data-tab="integracoes">
            <img src="assets/icons/spark.svg" alt="" aria-hidden="true">
            <span>Integrações (CADÚNICO / CNIS / SUS)</span>
          </button>

          <button class="tab-nav-btn" data-tab="seguranca">
            <img src="assets/icons/shield.svg" alt="" aria-hidden="true">
            <span>Segurança &amp; LGPD</span>
          </button>

          <button class="tab-nav-btn" data-tab="backup">
            <img src="assets/icons/sliders.svg" alt="" aria-hidden="true">
            <span>Backup e Perfil de Acesso</span>
          </button>
        </nav>

        <!-- ===================================================================
             ABA 1: GERAL & MUNICÍPIO (TELA PRINCIPAL)
             =================================================================== -->
        <div class="config-tab-pane active" id="tab-geral">
          
          <!-- Banner: Parâmetros Oficiais de Operação Municipal -->
          <section class="config-banner-callout" aria-label="Parâmetros Oficiais">
            <div class="banner-callout-left">
              <div class="banner-callout-icon">
                <img src="assets/icons/sliders.svg" alt="" aria-hidden="true">
              </div>
              <div class="banner-callout-text-wrap">
                <h2 class="banner-callout-title">Parâmetros Oficiais de Operação Municipal</h2>
                <p class="banner-callout-desc">
                  Defina as diretrizes municipais do Passe Livre Suzano em conformidade com as diretrizes e regras de negócio governamentais (LEI Nº 13.146, LEI Nº 8.742 e decretos locais).
                </p>
              </div>
            </div>

            <button class="btn-export-pdf" id="btn-export-params-pdf" title="Exportar documentação em formato PDF/A">
              <img src="assets/icons/pdf.svg" alt="" aria-hidden="true">
              <span>Exportar Parâmetros Oficiais em PDF/A...</span>
            </button>
          </section>

          <!-- Grid 2 Colunas: Parâmetros do Município + Regras e Prazos (SLA) -->
          <div class="config-grid-2col">
            
            <!-- Coluna Esquerda: Parâmetros do Município -->
            <section class="gestor-section-card" aria-labelledby="heading-param-municipio">
              <div class="section-card-header">
                <div class="section-card-header-left">
                  <img src="assets/icons/settings.svg" alt="" aria-hidden="true">
                  <h3 class="section-title" id="heading-param-municipio">Parâmetros do Município</h3>
                </div>
                <span class="section-badge-valid">Lei Federal 13.146/15</span>
              </div>

              <form class="config-form-body" id="form-municipal-params" onsubmit="return false;">
                <!-- Campo 1: Unidade Gestora Oficial -->
                <div class="form-group-item">
                  <label class="form-group-label" for="input-unidade-gestora">Unidade Gestora Oficial</label>
                  <input type="text" id="input-unidade-gestora" class="form-control-input" value="Secretaria Municipal de Mobilidade Urbana de Suzano">
                  <span class="form-field-hint">Vínculo e Atendimento: R. Baruel, 126 - Suzano - SP</span>
                </div>

                <!-- Campo 2: Decreto Regulamentador -->
                <div class="form-group-item">
                  <label class="form-group-label" for="input-decreto">Decreto Regulamentador</label>
                  <div class="input-with-action-wrap">
                    <input type="text" id="input-decreto" class="form-control-input" value="Decreto Municipal nº 9.845/2023 - Gratuidade no Transporte Coletivo PCD">
                    <button type="button" class="input-right-action-btn" title="Consultar decreto no diário oficial" onclick="showToast('Consultando Decreto Municipal nº 9.845/2023 no Diário Oficial de Suzano')">
                      <img src="assets/icons/search.svg" alt="Buscar">
                    </button>
                  </div>
                </div>

                <!-- Campo 3 e 4: Laudos Temporários e Permanentes -->
                <div class="form-fields-row-2col">
                  <div class="form-group-item">
                    <label class="form-group-label" for="select-laudo-temp">Laudos Temporários (Renovação)</label>
                    <select id="select-laudo-temp" class="form-select-styled">
                      <option value="presencial" selected>Apenas Presencial</option>
                      <option value="hibrido">Híbrido com Teleperícia</option>
                      <option value="digital">Digital Gov.br</option>
                    </select>
                  </div>

                  <div class="form-group-item">
                    <label class="form-group-label" for="select-laudo-perm">Laudos Permanentes</label>
                    <select id="select-laudo-perm" class="form-select-styled">
                      <option value="vitalicio" selected>Não Terminado (Vitalício)</option>
                      <option value="5anos">Revisão a cada 5 anos</option>
                      <option value="10anos">Revisão a cada 10 anos</option>
                    </select>
                  </div>
                </div>

                <!-- Aviso Legal -->
                <div class="config-alert-note">
                  <img src="assets/icons/warning.svg" alt="" aria-hidden="true">
                  <span>Alterações neste módulo afetam auditorias e regras de validação.</span>
                </div>

                <!-- Botões de Ação do Card -->
                <div class="config-card-actions">
                  <button type="button" class="btn-card-restore" id="btn-restore-params">Restaurar Padrões</button>
                  <button type="button" class="btn-card-save" id="btn-save-params">
                    <img src="assets/icons/check.svg" alt="" style="width: 14px; height: 14px; filter: brightness(0) invert(1);">
                    <span>Salvar Alterações</span>
                  </button>
                </div>
              </form>
            </section>

            <!-- Coluna Direita: Regras e Prazos de Atendimento (SLA) -->
            <section class="gestor-section-card" aria-labelledby="heading-sla-rules">
              <div class="section-card-header">
                <div class="section-card-header-left">
                  <img src="assets/icons/clock.svg" alt="" aria-hidden="true">
                  <h3 class="section-title" id="heading-sla-rules">Regras e Prazos de Atendimento (SLA)</h3>
                </div>
                <span class="badge-sla-green">Eficiência Operacional 98.4%</span>
              </div>

              <div class="sla-rules-list">
                <!-- SLA Prioritárias -->
                <div class="sla-rule-item">
                  <div class="sla-rule-info">
                    <div class="sla-rule-title-row">
                      <img src="assets/icons/warning.svg" alt="" style="width: 14px; height: 14px;">
                      <span class="sla-rule-title">Solicitações Prioritárias</span>
                    </div>
                    <p class="sla-rule-desc">
                      Laudos críticos com mais de 2 deficiências prioritárias sem necessidade de reanálise.
                    </p>
                  </div>
                  <div class="sla-target-badge-box">
                    <span class="sla-target-number">48 horas</span>
                    <span class="sla-target-label">tempo limite legal</span>
                  </div>
                </div>

                <!-- SLA Regulares -->
                <div class="sla-rule-item">
                  <div class="sla-rule-info">
                    <div class="sla-rule-title-row">
                      <img src="assets/icons/file-text.svg" alt="" style="width: 14px; height: 14px;">
                      <span class="sla-rule-title">Solicitações Regulares / Comuns</span>
                    </div>
                    <p class="sla-rule-desc">
                      PCD sem prioridade legal ou prorrogações ordinárias com laudo revalidado.
                    </p>
                  </div>
                  <div class="sla-target-badge-box">
                    <span class="sla-target-number">5 dias úteis</span>
                    <span class="sla-target-label">tempo padrão de análise</span>
                  </div>
                </div>

                <!-- Toggles e Opções -->
                <div class="sla-controls-box">
                  <div class="sla-toggle-row">
                    <div class="sla-toggle-text">
                      <span class="sla-toggle-title">Critério de Concessão Automática</span>
                      <span class="sla-toggle-subtext">Aprovação imediata para cadastros com CID revalidado pelo SUS</span>
                    </div>
                    <label class="switch-control">
                      <input type="checkbox" checked id="toggle-concessao-automatica" onchange="showToast('Critério de concessão automática atualizado!')">
                      <span class="switch-slider"></span>
                    </label>
                  </div>

                  <div class="sla-toggle-row">
                    <label class="custom-checkbox-label">
                      <input type="checkbox" checked id="checkbox-app-notifications" onchange="showToast('Notificações no App Cidadão configuradas!')">
                      <span>Notificações no App Cidadão</span>
                    </label>
                  </div>

                  <!-- Escalonamento -->
                  <div class="sla-escalation-row">
                    <span class="sla-escalation-text">Tolerância máxima de SLA em atraso para o encaminhamento:</span>
                    <button type="button" class="btn-link-escalate" id="btn-configure-escalation">Configurar Escalonamento</button>
                  </div>
                </div>
              </div>
            </section>

          </div>

          <!-- Seção 3: Conexões e Chaves de Integração Externa -->
          <section class="gestor-section-card" aria-labelledby="heading-integrations">
            <div class="section-card-header">
              <div class="section-card-header-left">
                <img src="assets/icons/spark.svg" alt="" aria-hidden="true">
                <h3 class="section-title" id="heading-integrations">Conexões e Chaves de Integração Externa</h3>
              </div>
              <button class="btn-export-pdf" id="btn-manage-connections" style="padding: 4px 14px;">
                <img src="assets/icons/sliders.svg" alt="" style="width: 13px; height: 13px;">
                <span>Gerir Conexões</span>
              </button>
            </div>

            <div class="integrations-grid" id="integrations-grid">
              <!-- Conector 1: CPTM & EMTU -->
              <div class="integration-card-box" data-integration-card>
                <div class="integration-header-row">
                  <div class="integration-icon-wrap amber">
                    <img src="assets/icons/routes.svg" alt="CPTM" onerror="this.src='assets/icons/map-icon.svg'">
                  </div>
                  <span class="status-pill-badge green">
                    <span class="status-pill-dot"></span>
                    Conectado
                  </span>
                </div>

                <div class="integration-title-wrap">
                  <h4 class="integration-service-name">CPTM &amp; EMTU Intermunicipal</h4>
                  <p class="integration-service-desc">
                    Diretório de interoperabilidade de São Paulo para integração tarifária intermunicipal.
                  </p>
                </div>

                <div class="integration-metrics-footer">
                  <span>Latência: <strong>12ms</strong></span>
                  <span>Acessos hoje: <strong>4.120</strong></span>
                </div>
              </div>

              <!-- Conector 2: CadÚnico & SUS -->
              <div class="integration-card-box" data-integration-card>
                <div class="integration-header-row">
                  <div class="integration-icon-wrap blue">
                    <img src="assets/icons/health.svg" alt="SUS" onerror="this.src='assets/icons/verified.svg'">
                  </div>
                  <span class="status-pill-badge blue">
                    <span class="status-pill-dot"></span>
                    Ativo
                  </span>
                </div>

                <div class="integration-title-wrap">
                  <h4 class="integration-service-name">Base CadÚnico &amp; SUS Municipal</h4>
                  <p class="integration-service-desc">
                    Cruzamento de renda per capita até 1/2 salário mínimo e validação de prontuário eletrônico.
                  </p>
                </div>

                <div class="integration-metrics-footer">
                  <span>Última sinc: <strong>Há 4 min</strong></span>
                  <span>Registros: <strong>18.234</strong></span>
                </div>
              </div>

              <!-- Conector 3: Provedor SMS & Push -->
              <div class="integration-card-box" data-integration-card>
                <div class="integration-header-row">
                  <div class="integration-icon-wrap emerald">
                    <img src="assets/icons/mail.svg" alt="SMS">
                  </div>
                  <span class="status-pill-badge green">
                    <span class="status-pill-dot"></span>
                    Operacional
                  </span>
                </div>

                <div class="integration-title-wrap">
                  <h4 class="integration-service-name">Provedor SMS &amp; Push Notification</h4>
                  <p class="integration-service-desc">
                    Envio de códigos de verificação OTP e comunicados oficiais aos usuários.
                  </p>
                </div>

                <div class="integration-metrics-footer">
                  <span>Saldo: <strong>84.200 créditos</strong></span>
                  <span>Disparos: <strong>Sucesso</strong></span>
                </div>
              </div>
            </div>
          </section>

          <!-- Seção 4: Gestão de Permissões e Equipe (RBAC) -->
          <section class="gestor-section-card" aria-labelledby="heading-rbac-team">
            <div class="section-card-header">
              <div class="section-card-header-left">
                <img src="assets/icons/users.svg" alt="" aria-hidden="true">
                <div>
                  <h3 class="section-title" id="heading-rbac-team">Gestão de Permissões e Equipe</h3>
                  <span style="font-size: 11px; color: #78716c;">Controle de acessos baseados em papéis (RBAC) com trilha de auditoria criptografada.</span>
                </div>
              </div>
              <div style="display:flex;align-items:center;gap:8px;">
                <?php if ((int)($gestorUser['is_primary_admin'] ?? 0) === 1): ?>
                  <a class="btn-export-pdf" href="gerenciar-administradores.php" id="btn-go-admin-managers"
                     style="text-decoration:none;" title="Conceder ou remover acesso administrativo">
                    <img src="assets/icons/shield.svg" alt="" style="width: 13px; height: 13px;">
                    <span>Gerenciar Administradores</span>
                  </a>
                <?php endif; ?>
                <button class="btn-export-pdf" id="btn-invite-member" style="background: #fff7ed; border-color: #fed7aa; color: #c2410c;">
                  <img src="assets/icons/plus.svg" alt="" style="width: 13px; height: 13px;">
                  <span>Convidar Novo Membro</span>
                </button>
              </div>
            </div>

            <div class="rbac-table-container">
              <table class="rbac-table">
                <thead>
                  <tr>
                    <th>USUÁRIO / E-MAIL</th>
                    <th>FUNÇÃO INSTITUCIONAL</th>
                    <th class="center">LEITURA</th>
                    <th class="center">EDIÇÃO</th>
                    <th class="center">HOMOLOGAÇÃO</th>
                    <th>AÇÕES</th>
                  </tr>
                </thead>
                <tbody id="rbac-table-body">
                  <!-- Linha 1: Gestor Logado -->
                  <tr>
                    <td>
                      <div class="team-user-identity">
                        <div class="team-avatar-circle amber"><?= htmlspecialchars($gestorUser['initials']) ?></div>
                        <div class="team-user-details">
                          <span class="team-user-name"><?= htmlspecialchars($gestorUser['name']) ?></span>
                          <span class="team-user-email"><?= htmlspecialchars($gestorUser['email']) ?></span>
                        </div>
                      </div>
                    </td>
                    <td>
                      <span class="role-tag-badge admin">Administrador Pleno</span>
                    </td>
                    <td class="center">
                      <label class="custom-checkbox-label">
                        <input type="checkbox" checked disabled>
                      </label>
                    </td>
                    <td class="center">
                      <label class="custom-checkbox-label">
                        <input type="checkbox" checked disabled>
                      </label>
                    </td>
                    <td class="center">
                      <label class="custom-checkbox-label">
                        <input type="checkbox" checked disabled>
                      </label>
                    </td>
                    <td>
                      <span class="action-text-total">Acesso Total</span>
                    </td>
                  </tr>

                  <!-- Linha 2: Rodrigo Mendes -->
                  <tr>
                    <td>
                      <div class="team-user-identity">
                        <div class="team-avatar-circle slate">RM</div>
                        <div class="team-user-details">
                          <span class="team-user-name">Rodrigo Mendes</span>
                          <span class="team-user-email">rodrigo.mendes@mobilidade.suzano.sp.gov.br</span>
                        </div>
                      </div>
                    </td>
                    <td>
                      <span class="role-tag-badge analyst">Analista de Trânsito</span>
                    </td>
                    <td class="center">
                      <label class="custom-checkbox-label">
                        <input type="checkbox" checked onchange="showToast('Permissão de leitura de Rodrigo Mendes alterada')">
                      </label>
                    </td>
                    <td class="center">
                      <label class="custom-checkbox-label">
                        <input type="checkbox" checked onchange="showToast('Permissão de edição de Rodrigo Mendes alterada')">
                      </label>
                    </td>
                    <td class="center">
                      <label class="custom-checkbox-label">
                        <input type="checkbox" onchange="showToast('Permissão de homologação de Rodrigo Mendes alterada')">
                      </label>
                    </td>
                    <td>
                      <button class="btn-table-action-icon" title="Editar permissões" onclick="showToast('Abrindo perfil de acesso de Rodrigo Mendes')">
                        <img src="assets/icons/edit.svg" alt="Editar">
                      </button>
                    </td>
                  </tr>

                  <!-- Linha 3: Dra. Ana Lúcia Ferreira -->
                  <tr>
                    <td>
                      <div class="team-user-identity">
                        <div class="team-avatar-circle purple">AF</div>
                        <div class="team-user-details">
                          <span class="team-user-name">Dra. Ana Lúcia Ferreira</span>
                          <span class="team-user-email">ana.ferreira@medicina.suzano.sp.gov.br — CRM/SP 128.412</span>
                        </div>
                      </div>
                    </td>
                    <td>
                      <span class="role-tag-badge doctor">Médica Perita Homologadora</span>
                    </td>
                    <td class="center">
                      <label class="custom-checkbox-label">
                        <input type="checkbox" checked onchange="showToast('Permissão de leitura da Dra. Ana Lúcia alterada')">
                      </label>
                    </td>
                    <td class="center">
                      <label class="custom-checkbox-label">
                        <input type="checkbox" onchange="showToast('Permissão de edição da Dra. Ana Lúcia alterada')">
                      </label>
                    </td>
                    <td class="center">
                      <label class="custom-checkbox-label">
                        <input type="checkbox" checked onchange="showToast('Permissão de homologação da Dra. Ana Lúcia alterada')">
                      </label>
                    </td>
                    <td>
                      <button class="btn-table-action-icon" title="Editar permissões" onclick="showToast('Abrindo perfil de acesso da Dra. Ana Lúcia Ferreira')">
                        <img src="assets/icons/edit.svg" alt="Editar">
                      </button>
                    </td>
                  </tr>

                  <!-- Linha 4: DTI - Suporte -->
                  <tr>
                    <td>
                      <div class="team-user-identity">
                        <div class="team-avatar-circle slate" style="background: #475569;">DS</div>
                        <div class="team-user-details">
                          <span class="team-user-name">DTI — Suporte</span>
                          <span class="team-user-email">suporte@prodesp.sp.gov.br</span>
                        </div>
                      </div>
                    </td>
                    <td>
                      <span class="role-tag-badge support">Provedor DTI e Suporte</span>
                    </td>
                    <td class="center">
                      <label class="custom-checkbox-label">
                        <input type="checkbox" checked onchange="showToast('Permissão de leitura do DTI alterada')">
                      </label>
                    </td>
                    <td class="center">
                      <label class="custom-checkbox-label">
                        <input type="checkbox" onchange="showToast('Permissão de edição do DTI alterada')">
                      </label>
                    </td>
                    <td class="center">
                      <label class="custom-checkbox-label">
                        <input type="checkbox" onchange="showToast('Permissão de homologação do DTI alterada')">
                      </label>
                    </td>
                    <td>
                      <button class="btn-table-action-icon" title="Editar permissões" onclick="showToast('Abrindo perfil de acesso do DTI')">
                        <img src="assets/icons/edit.svg" alt="Editar">
                      </button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </section>

        </div>

        <!-- ===================================================================
             ABA 2: REGRAS DE CONCESSÃO PCD
             =================================================================== -->
        <div class="config-tab-pane" id="tab-regras">
          <section class="gestor-section-card">
            <div class="section-card-header">
              <div class="section-card-header-left">
                <img src="assets/icons/clipboard-check.svg" alt="" aria-hidden="true">
                <h3 class="section-title">Parâmetros e Diretrizes de Concessão PCD</h3>
              </div>
              <span class="section-badge-valid">Módulo Ativo</span>
            </div>
            <div style="font-size: 12.5px; color: #57534e; line-height: 1.6; display: flex; flex-direction: column; gap: 12px;">
              <p>Critérios e diretrizes médicas periciais aplicadas no processo de validação de laudos e concessão do benefício Passe Livre no município de Suzano.</p>
              <div class="form-fields-row-2col">
                <div class="form-group-item">
                  <label class="form-group-label">Validade Mínima de Laudos Temporários</label>
                  <input type="text" class="form-control-input" value="12 meses (renovável por perícia médica)">
                </div>
                <div class="form-group-item">
                  <label class="form-group-label">Direito a Acompanhante Obrigatório</label>
                  <select class="form-select-styled">
                    <option selected>Conforme indicação médica expressa em laudo</option>
                    <option>Sempre concedido para CIDs de severidade grau 3</option>
                  </select>
                </div>
              </div>
            </div>
          </section>
        </div>

        <!-- ===================================================================
             ABA 3: INTEGRAÇÕES (CADÚNICO / CNIS / SUS)
             =================================================================== -->
        <div class="config-tab-pane" id="tab-integracoes">
          <section class="gestor-section-card">
            <div class="section-card-header">
              <div class="section-card-header-left">
                <img src="assets/icons/spark.svg" alt="" aria-hidden="true">
                <h3 class="section-title">Chaves de API e Conectores Governamentais</h3>
              </div>
              <span class="badge-sla-green">3 Conectores em Produção</span>
            </div>
            <div style="font-size: 12.5px; color: #57534e; line-height: 1.6; display: flex; flex-direction: column; gap: 12px;">
              <p>Gerenciamento de credenciais mTLS, certificados A3 Prodesp e endpoints W3C REST para interoperabilidade municipal.</p>
              <div class="form-group-item">
                <label class="form-group-label">Endpoint de Integração CadÚnico Suzano</label>
                <input type="text" class="form-control-input" value="https://api.suzano.sp.gov.br/v2/cadunico/cidadao/sync" readonly>
              </div>
            </div>
          </section>
        </div>

        <!-- ===================================================================
             ABA 4: SEGURANÇA & LGPD
             =================================================================== -->
        <div class="config-tab-pane" id="tab-seguranca">
          <section class="gestor-section-card">
            <div class="section-card-header">
              <div class="section-card-header-left">
                <img src="assets/icons/shield.svg" alt="" aria-hidden="true">
                <h3 class="section-title">Políticas de Segurança e Proteção de Dados (LGPD)</h3>
              </div>
              <button class="btn-export-pdf" onclick="window.location.href='auditoria.php'">
                <img src="assets/icons/history.svg" alt="">
                <span>Ir para Logs de Auditoria</span>
              </button>
            </div>
            <div style="font-size: 12.5px; color: #57534e; line-height: 1.6; display: flex; flex-direction: column; gap: 12px;">
              <p>Configurações de retenção obrigatória de 5 anos, anonimização de CPFs em relatórios e assinaturas digitais ICP-Brasil.</p>
            </div>
          </section>
        </div>

        <!-- ===================================================================
             ABA 5: BACKUP E PERFIL DE ACESSO
             =================================================================== -->
        <div class="config-tab-pane" id="tab-backup">
          <section class="gestor-section-card">
            <div class="section-card-header">
              <div class="section-card-header-left">
                <img src="assets/icons/sliders.svg" alt="" aria-hidden="true">
                <h3 class="section-title">Backups Criptografados e Snapshots</h3>
              </div>
              <span class="badge-sla-green">Último backup: Hoje 03:00 BRT</span>
            </div>
            <div style="font-size: 12.5px; color: #57534e; line-height: 1.6; display: flex; flex-direction: column; gap: 12px;">
              <p>Rotina de backup automatizado a cada 6 horas com espelhamento imutável em servidor governamental Prodesp.</p>
            </div>
          </section>

          <!-- CARD: BANCO DE DADOS MYSQL FÍSICO -->
          <section class="config-section-card" id="section-banco-sql" aria-labelledby="heading-banco-sql" style="background:#fff;border:1px solid #ede4d8;border-radius:14px;padding:22px;margin-top:20px;">
            <div class="section-card-header" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
              <div class="section-card-header-left" style="display:flex;align-items:center;gap:10px;">
                <img src="assets/icons/bar-chart.svg" alt="" aria-hidden="true">
                <h3 class="section-title" id="heading-banco-sql" style="margin:0;font-size:16px;font-weight:700;color:#1c1917;">Banco de Dados MySQL Oficial</h3>
              </div>
              <span class="badge-sla-green" style="background:#ecfdf5;color:#065f46;border:1px solid #a7f3d0;padding:4px 10px;border-radius:6px;font-size:11px;font-weight:700;">MySQL 8.0+ / MariaDB Conectado</span>
            </div>

            <!-- Estatísticas em tempo real do banco vindas do MySQL -->
            <div id="db-stats-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:10px;margin-bottom:18px;">
              <div class="db-stat-card" style="background:#faf7f2;border:1px solid #ede4d8;border-radius:10px;padding:12px 14px;text-align:center;">
                <div id="db-stat-users" style="font-size:22px;font-weight:800;color:#c2693f;"><?= $countUsers ?></div>
                <div style="font-size:11px;color:#78716c;margin-top:2px;">Usuários</div>
              </div>
              <div class="db-stat-card" style="background:#faf7f2;border:1px solid #ede4d8;border-radius:10px;padding:12px 14px;text-align:center;">
                <div id="db-stat-locais" style="font-size:22px;font-weight:800;color:#c2693f;"><?= $countLocais ?></div>
                <div style="font-size:11px;color:#78716c;margin-top:2px;">Locais</div>
              </div>
              <div class="db-stat-card" style="background:#faf7f2;border:1px solid #ede4d8;border-radius:10px;padding:12px 14px;text-align:center;">
                <div id="db-stat-sols" style="font-size:22px;font-weight:800;color:#c2693f;"><?= $countSols ?></div>
                <div style="font-size:11px;color:#78716c;margin-top:2px;">Solicitações</div>
              </div>
              <div class="db-stat-card" style="background:#faf7f2;border:1px solid #ede4d8;border-radius:10px;padding:12px 14px;text-align:center;">
                <div id="db-stat-avals" style="font-size:22px;font-weight:800;color:#c2693f;"><?= $countAvals ?></div>
                <div style="font-size:11px;color:#78716c;margin-top:2px;">Avaliações</div>
              </div>
              <div class="db-stat-card" style="background:#faf7f2;border:1px solid #ede4d8;border-radius:10px;padding:12px 14px;text-align:center;">
                <div id="db-stat-notifs" style="font-size:22px;font-weight:800;color:#c2693f;"><?= $countNotifs ?></div>
                <div style="font-size:11px;color:#78716c;margin-top:2px;">Notificações</div>
              </div>
              <div class="db-stat-card" style="background:#faf7f2;border:1px solid #ede4d8;border-radius:10px;padding:12px 14px;text-align:center;">
                <div id="db-stat-audit" style="font-size:22px;font-weight:800;color:#c2693f;"><?= $countAudit ?></div>
                <div style="font-size:11px;color:#78716c;margin-top:2px;">Logs Auditoria</div>
              </div>
            </div>

            <!-- Arquivo fonte -->
            <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px;padding:12px 16px;margin-bottom:16px;display:flex;align-items:center;gap:10px;">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2.2" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
              <div>
                <div style="font-size:12.5px;font-weight:700;color:#15803d;">Banco de Dados Ativo: <code style="background:#dcfce7;padding:2px 6px;border-radius:4px;font-size:11.5px;">MySQL 8.0+ / MariaDB (passe_livre_suzano)</code></div>
                <div style="font-size:11.5px;color:#166534;margin-top:2px;">Conexão nativa via PHP PDO com prepared statements. Todas as alterações são persistidas no MySQL em tempo real. Schema mestre em <code>banco_mysql.sql</code>.</div>
              </div>
            </div>

            <!-- Botões de ação -->
            <div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;">
              <button type="button" id="btn-download-sql" class="btn-export-pdf"
                style="background:#1e3a5f;border-color:#1e3a5f;color:#fff;display:flex;align-items:center;gap:8px;padding:8px 18px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;border:none;"
                title="Exporta o dump SQL atualizado com todas as tabelas e dados do MySQL">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                Exportar Dump MySQL (.sql)
              </button>

              <button type="button" id="btn-view-schema" class="btn-export-pdf"
                style="background:#fff;border:1px solid #ede4d8;color:#44403c;display:flex;align-items:center;gap:8px;padding:8px 18px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;"
                title="Visualiza o schema SQL completo do banco">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="9" y1="21" x2="9" y2="9"/></svg>
                Ver Schema MySQL
              </button>

              <button type="button" id="btn-reload-sql" class="btn-export-pdf"
                style="background:#fff;border:1px solid #ede4d8;color:#44403c;display:flex;align-items:center;gap:8px;padding:8px 18px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;"
                title="Sincroniza e recarrega os dados diretamente do MySQL">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-4"/></svg>
                Sincronizar com MySQL
              </button>
            </div>

          </section>

          <!-- Modal: Schema SQL -->
          <div class="modal-overlay" id="modal-schema-sql" role="dialog" aria-modal="true" aria-labelledby="title-schema-sql">
            <div class="modal-window" style="max-width:780px;width:95%;">
              <div class="modal-header">
                <h3 id="title-schema-sql" style="display:flex;align-items:center;gap:8px;">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="9" y1="21" x2="9" y2="9"/></svg>
                  Schema do Banco de Dados — banco_mysql.sql
                </h3>
                <button class="btn-modal-close" title="Fechar" aria-label="Fechar">&times;</button>
              </div>
              <div class="modal-body" style="padding:0;">
                <pre id="schema-sql-content" style="margin:0;padding:20px;background:#1e1e1e;color:#d4d4d4;font-size:12px;line-height:1.6;overflow:auto;max-height:500px;border-radius:0 0 12px 12px;white-space:pre-wrap;word-break:break-word;"></pre>
              </div>
            </div>
          </div>
        </div>

      </main>

      <!-- Rodapé do Gestor -->
      <footer class="site-footer" role="contentinfo" style="margin-top: auto; padding: 16px 32px; background: #fdfaf6; border-top: 1px solid #ede4d8; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; font-size: 11px; color: #78716c;">
        <div>
          &copy; 2026 Passe Livre Suzano. Todos os direitos reservados. Projeto Desenvolvido em Conformidade com a Lei Brasileira de Inclusão (Lei nº 13.146/15).
        </div>
        <div style="display: flex; gap: 14px;">
          <a href="termos-de-uso.php" style="color: #78716c; text-decoration: none;">Termos de Uso</a>
          <span>&bull;</span>
          <a href="privacidade.php" style="color: #78716c; text-decoration: none;">Privacidade</a>
          <span>&bull;</span>
          <a href="ajuda.php" style="color: #78716c; text-decoration: none;">Suporte</a>
        </div>
      </footer>
    </div>
  </div>

  <!-- Modal: Convidar Novo Membro -->
  <div class="modal-overlay" id="modal-invite-member" role="dialog" aria-modal="true" aria-labelledby="title-modal-invite">
    <div class="modal-window">
      <div class="modal-header">
        <h3 id="title-modal-invite">Convidar Novo Membro da Equipe</h3>
        <button class="btn-modal-close" title="Fechar">&times;</button>
      </div>
      <form id="form-invite-member">
        <div class="modal-body">
          <div class="form-group-item">
            <label class="form-group-label" for="invite-name">Nome Completo *</label>
            <input type="text" id="invite-name" class="form-control-input" placeholder="Ex: Mariana Silveira" required>
          </div>
          <div class="form-group-item">
            <label class="form-group-label" for="invite-email">E-mail Institucional (@suzano.sp.gov.br) *</label>
            <input type="email" id="invite-email" class="form-control-input" placeholder="mariana.silveira@suzano.sp.gov.br" required>
          </div>
          <div class="form-group-item">
            <label class="form-group-label" for="invite-role">Função Institucional</label>
            <select id="invite-role" class="form-select-styled">
              <option value="Analista de Atendimento">Analista de Atendimento</option>
              <option value="Médico Perito Municipal">Médico Perito Municipal</option>
              <option value="Supervisor de Trânsito">Supervisor de Trânsito</option>
              <option value="Auditor de Conformidade">Auditor de Conformidade</option>
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn-card-restore btn-modal-cancel">Cancelar</button>
          <button type="submit" class="btn-card-save">Enviar Convite</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Modal: Gerir Conexões -->
  <div class="modal-overlay" id="modal-connections" role="dialog" aria-modal="true">
    <div class="modal-window">
      <div class="modal-header">
        <h3>Gerenciador de Conectores e APIs</h3>
        <button class="btn-modal-close" title="Fechar">&times;</button>
      </div>
      <div class="modal-body">
        <p style="font-size: 12px; color: #57534e;">Conectores cadastrados no banco de dados e seus endpoints:</p>
        <div id="connections-details" style="background: #faf7f2; border: 1px solid #ede4d8; border-radius: 10px; padding: 12px; font-size: 11.5px; color: #1c1917;">
          Carregando conectores…
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn-card-restore btn-modal-cancel">Fechar</button>
        <button type="button" class="btn-card-save" id="btn-renew-handshake">Reativar Todos os Conectores</button>
      </div>
    </div>
  </div>

  <!-- Modal: Escalonamento SLA -->
  <div class="modal-overlay" id="modal-escalation" role="dialog" aria-modal="true">
    <div class="modal-window">
      <div class="modal-header">
        <h3>Configurar Escalonamento de SLA</h3>
        <button class="btn-modal-close" title="Fechar">&times;</button>
      </div>
      <div class="modal-body">
        <div class="form-group-item">
          <label class="form-group-label">Aviso prévio de expiração de prazo</label>
          <select class="form-select-styled" id="select-escalation-aviso">
            <option value="12">12 horas antes do vencimento</option>
            <option value="24" selected>24 horas antes do vencimento</option>
            <option value="48">48 horas antes do vencimento</option>
          </select>
        </div>
        <div class="form-group-item">
          <label class="form-group-label">Escalonar para a chefia de perícia após:</label>
          <select class="form-select-styled" id="select-escalation-atraso">
            <option value="4" selected>4 horas de atraso</option>
            <option value="8">8 horas de atraso</option>
            <option value="24">24 horas de atraso</option>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn-card-restore btn-modal-cancel">Cancelar</button>
        <button type="button" class="btn-card-save" id="btn-save-escalation">Salvar Escalonamento</button>
      </div>
    </div>
  </div>

  <!-- Toast de Notificação -->
  <div class="toast-notice" id="app-toast">
    <img src="assets/icons/check.svg" width="18" height="18" alt="">
    <span id="toast-message">Operação realizada com sucesso!</span>
  </div>

    <script src="js/db.js"></script>
  <script src="js/db-queries.js"></script>
  <script src="js/auth.js"></script>
  <script src="js/configuracoes.js"></script>
</body>
</html>
