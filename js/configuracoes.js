/**
 * Passe Livre Suzano — Painel do Gestor (Configurações Gerais da Plataforma)
 */

document.addEventListener('DOMContentLoaded', () => {
  // 1. Navegação de Abas
  const tabButtons = document.querySelectorAll('.tab-nav-btn');
  const tabPanes = document.querySelectorAll('.config-tab-pane');

  tabButtons.forEach(btn => {
    btn.addEventListener('click', () => {
      const targetTab = btn.getAttribute('data-tab');
      
      tabButtons.forEach(b => b.classList.remove('active'));
      btn.classList.add('active');

      tabPanes.forEach(pane => {
        if (pane.id === `tab-${targetTab}`) {
          pane.classList.add('active');
        } else {
          pane.classList.remove('active');
        }
      });

      if (targetTab === 'seguranca') {
        showToast('Módulo de Segurança & LGPD selecionado.');
      } else if (targetTab === 'integracoes') {
        showToast('Visualizando status das conexões governamentais e APIs.');
      } else if (targetTab === 'regras') {
        showToast('Diretrizes de concessão e laudos periciais.');
      } else if (targetTab === 'backup') {
        showToast('Módulo de snapshots, espelhamento e backups em nuvem.');
      } else {
        showToast('Parâmetros gerais e municipais de Suzano.');
      }
    });
  });

  // =========================================================================
  // 2. ESTADO CARREGADO DA API (api/configuracoes.php)
  // =========================================================================
  const cfgState = {
    configs: {},
    integracoes: [],
    integracaoLogs: [],
    permissoes: [],
    equipe: [],
    currentUserId: 0
  };

  async function cfgApi(action, payload, method) {
    const opts = { method: method || (payload ? 'POST' : 'GET'), credentials: 'same-origin', headers: {} };
    if (payload) {
      opts.headers['Content-Type'] = 'application/json';
      opts.body = JSON.stringify(payload);
    }
    const res = await fetch('api/configuracoes.php?action=' + encodeURIComponent(action), opts);
    let json = null;
    try { json = await res.json(); } catch (_) {}
    if (!res.ok || !json || json.success === false) {
      throw new Error((json && (json.message || json.error)) || ('HTTP ' + res.status));
    }
    return json;
  }

  function cfgVal(id) {
    const el = document.getElementById(id);
    return el ? el.value : '';
  }

  function cfgChecked(id) {
    const el = document.getElementById(id);
    return !!(el && el.checked);
  }

  async function loadSettings() {
    try {
      const json = await cfgApi('get');
      cfgState.configs         = json.configs || {};
      cfgState.integracoes     = json.integracoes || [];
      cfgState.integracaoLogs  = json.integracao_logs || [];
      cfgState.permissoes      = json.permissoes || [];
      cfgState.equipe          = json.equipe || [];
      cfgState.currentUserId   = parseInt(json.current_user_id || 0, 10);

      fillParamsForm();
      renderIntegrations();
      renderRbacTable();
    } catch (e) {
      showToast('Falha ao carregar configurações: ' + e.message, 'error');
      const tbody = document.getElementById('rbac-table-body');
      if (tbody) tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;color:#b91c1c;padding:20px;">Não foi possível carregar a equipe a partir do banco de dados.</td></tr>';
    }
  }

  // =========================================================================
  // 3. PARÂMETROS DO MUNICÍPIO E SLA
  // =========================================================================
  function fillParamsForm() {
    const cfg = cfgState.configs;
    const setVal = (id, value) => {
      const el = document.getElementById(id);
      if (el && value !== undefined && value !== null && value !== '') el.value = value;
    };

    setVal('input-unidade-gestora', cfg.unidade_gestora);
    setVal('input-decreto',        cfg.decreto_municipal);
    setVal('select-laudo-temp',    cfg.laudo_temporalidade);
    setVal('select-laudo-perm',    cfg.laudo_permissao);
    setVal('select-escalation-aviso',  cfg.escalonamento_aviso_horas);
    setVal('select-escalation-atraso', cfg.escalonamento_atraso_horas);

    if (cfg.concessao_automatica !== undefined) {
      const chk = document.getElementById('toggle-concessao-automatica');
      if (chk) chk.checked = cfg.concessao_automatica === '1';
    }
    if (cfg.notificar_app_cidadao !== undefined) {
      const chk = document.getElementById('checkbox-app-notifications');
      if (chk) chk.checked = cfg.notificar_app_cidadao === '1';
    }
  }

  async function saveParams(payload, successMessage) {
    const json = await cfgApi('save_params', { configs: payload });
    Object.assign(cfgState.configs, payload);
    showToast(successMessage || json.message || 'Parâmetros salvos no banco de dados.');
  }

  const btnSaveParams = document.getElementById('btn-save-params');
  const btnRestoreParams = document.getElementById('btn-restore-params');

  if (btnSaveParams) {
    btnSaveParams.addEventListener('click', async (e) => {
      e.preventDefault();
      btnSaveParams.disabled = true;
      try {
        await saveParams({
          unidade_gestora:       cfgVal('input-unidade-gestora'),
          decreto_municipal:     cfgVal('input-decreto'),
          laudo_temporalidade:   cfgVal('select-laudo-temp'),
          laudo_permissao:       cfgVal('select-laudo-perm'),
          concessao_automatica:  cfgChecked('toggle-concessao-automatica') ? '1' : '0',
          notificar_app_cidadao: cfgChecked('checkbox-app-notifications') ? '1' : '0'
        }, 'Parâmetros do Município salvos no banco de dados.');
      } catch (err) {
        showToast('Erro ao salvar parâmetros: ' + err.message, 'error');
      } finally {
        btnSaveParams.disabled = false;
      }
    });
  }

  if (btnRestoreParams) {
    btnRestoreParams.addEventListener('click', async () => {
      if (!confirm('Deseja restaurar as configurações municipais para o padrão legal de Suzano?')) return;

      const defaults = {
        unidade_gestora:     'Secretaria Municipal de Mobilidade Urbana de Suzano',
        decreto_municipal:   'Decreto Municipal nº 9.845/2023 - Gratuidade no Transporte Coletivo PCD',
        laudo_temporalidade: 'presencial',
        laudo_permissao:     'vitalicio'
      };

      ['input-unidade-gestora','input-decreto'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.value = defaults[id === 'input-unidade-gestora' ? 'unidade_gestora' : 'decreto_municipal'];
      });
      const selTemp = document.getElementById('select-laudo-temp');
      const selPerm = document.getElementById('select-laudo-perm');
      if (selTemp) selTemp.value = defaults.laudo_temporalidade;
      if (selPerm) selPerm.value = defaults.laudo_permissao;

      try {
        await saveParams(defaults, 'Parâmetros restaurados para os valores padrão.');
      } catch (err) {
        showToast('Erro ao restaurar parâmetros: ' + err.message, 'error');
      }
    });
  }

  // =========================================================================
  // 4. EXPORTAÇÃO DOS PARÂMETROS (documento para impressão / PDF do navegador)
  // =========================================================================
  const btnExportPdf = document.getElementById('btn-export-params-pdf');
  if (btnExportPdf) {
    btnExportPdf.addEventListener('click', () => {
      const esc = (s) => String(s == null ? '' : s)
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
      const rows = [
        ['Unidade Gestora Oficial', cfgVal('input-unidade-gestora')],
        ['Decreto Regulamentador', cfgVal('input-decreto')],
        ['Laudos Temporários', document.getElementById('select-laudo-temp')?.selectedOptions?.[0]?.textContent || ''],
        ['Laudos Permanentes', document.getElementById('select-laudo-perm')?.selectedOptions?.[0]?.textContent || ''],
        ['Concessão Automática', cfgChecked('toggle-concessao-automatica') ? 'Habilitada' : 'Desabilitada'],
        ['Notificações no App', cfgChecked('checkbox-app-notifications') ? 'Habilitadas' : 'Desabilitadas'],
        ['Escalonamento (aviso)', cfgVal('select-escalation-aviso') + ' horas antes do vencimento'],
        ['Escalonamento (atraso)', cfgVal('select-escalation-atraso') + ' horas de atraso']
      ];

      const win = window.open('', '_blank');
      if (!win) { showToast('Permita pop-ups para exportar o documento.', 'error'); return; }

      win.document.write(
        '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8">' +
        '<title>Parâmetros Oficiais — Passe Livre Suzano</title>' +
        '<style>body{font-family:Arial,Helvetica,sans-serif;padding:32px;color:#1c1917}' +
        'h1{font-size:18px;margin:0 0 4px}p.sub{font-size:12px;color:#57534e;margin:0 0 24px}' +
        'table{width:100%;border-collapse:collapse;font-size:13px}' +
        'th,td{border:1px solid #d6d3d1;padding:9px 12px;text-align:left}' +
        'th{background:#f5f5f4;width:34%}</style></head><body>' +
        '<h1>Parâmetros Oficiais de Operação Municipal</h1>' +
        '<p class="sub">Passe Livre Suzano — emitido em ' + esc(new Date().toLocaleString('pt-BR')) + '</p>' +
        '<table>' + rows.map(r => '<tr><th>' + esc(r[0]) + '</th><td>' + esc(r[1]) + '</td></tr>').join('') + '</table>' +
        '<script>window.onload=function(){window.print();};<\/script>' +
        '</body></html>'
      );
      win.document.close();
      showToast('Documento aberto — use "Imprimir" e escolha "Salvar como PDF".');
    });
  }

  // =========================================================================
  // 5. CONEXÕES E CHAVES EXTERNAS (dados reais da tabela `integracoes`)
  // =========================================================================
  const statusMap = {
    ativo:    { label: 'Ativo',    css: 'green' },
    instavel: { label: 'Instável', css: 'amber' },
    inativo:  { label: 'Inativo',  css: 'red' }
  };

  function lastLogFor(integrationId) {
    return cfgState.integracaoLogs.find(l => parseInt(l.integracao_id, 10) === parseInt(integrationId, 10)) || null;
  }

  function integrationFooter(integration) {
    const log = lastLogFor(integration.id);
    if (!log) {
      return '<span>Sem chamadas registradas</span><span>Status HTTP: <strong>—</strong></span>';
    }
    return '<span>Latência: <strong>' + (log.tempo_ms != null ? log.tempo_ms + 'ms' : '—') + '</strong></span>' +
           '<span>Último HTTP: <strong>' + (log.status_http != null ? log.status_http : '—') + '</strong></span>';
  }

  function renderIntegrations() {
    const cards = document.querySelectorAll('[data-integration-card]');
    cards.forEach((card, index) => {
      const integration = cfgState.integracoes[index];
      if (!integration) return;

      const nameEl = card.querySelector('.integration-service-name');
      const descEl = card.querySelector('.integration-service-desc');
      const badge  = card.querySelector('.status-pill-badge');
      const footer = card.querySelector('.integration-metrics-footer');

      if (nameEl) nameEl.textContent = integration.nome;
      if (descEl) {
        descEl.textContent = (integration.tipo ? integration.tipo + ' — ' : '') +
          (integration.endpoint_url || 'Endpoint não configurado');
      }

      const st = statusMap[integration.status] || { label: integration.status || 'Desconhecido', css: 'blue' };
      if (badge) {
        badge.className = 'status-pill-badge ' + st.css;
        badge.innerHTML = '<span class="status-pill-dot"></span>' + st.label;
      }
      if (footer) footer.innerHTML = integrationFooter(integration);

      card.dataset.integrationId = integration.id;
      card.title = 'Clique para alterar o status desta conexão';
    });
  }

  async function toggleIntegration(integration) {
    const nextStatus = integration.status === 'ativo' ? 'inativo' : 'ativo';
    if (!confirm('Alterar a integração "' + integration.nome + '" para "' + nextStatus + '"?')) return;
    try {
      await cfgApi('toggle_integration', { id: integration.id, status: nextStatus });
      integration.status = nextStatus;
      renderIntegrations();
      renderConnectionsModal();
      showToast('Integração "' + integration.nome + '" agora está ' + nextStatus + '.');
    } catch (err) {
      showToast('Erro ao alterar integração: ' + err.message, 'error');
    }
  }

  document.querySelectorAll('[data-integration-card]').forEach(card => {
    card.addEventListener('click', (e) => {
      if (e.target.closest('button')) return;
      const id = parseInt(card.dataset.integrationId || '0', 10);
      const integration = cfgState.integracoes.find(i => parseInt(i.id, 10) === id);
      if (integration) toggleIntegration(integration);
    });
  });

  const btnManageConnections = document.getElementById('btn-manage-connections');
  if (btnManageConnections) {
    btnManageConnections.addEventListener('click', () => {
      renderConnectionsModal();
      openModal('modal-connections');
    });
  }

  function renderConnectionsModal() {
    const box = document.getElementById('connections-details');
    if (!box) return;
    if (!cfgState.integracoes.length) {
      box.textContent = 'Nenhum conector cadastrado.';
      return;
    }
    box.innerHTML = cfgState.integracoes.map(i => {
      const st = statusMap[i.status] || { label: i.status, css: 'blue' };
      const log = lastLogFor(i.id);
      return '<div style="padding:8px 0;border-bottom:1px solid #ede4d8;">' +
        '<strong>' + escapeHtml(i.nome) + '</strong> — <span style="color:' +
          (st.css === 'green' ? '#065f46' : (st.css === 'amber' ? '#92400e' : '#991b1b')) + '">' + st.label + '</span><br>' +
        '<span style="font-size:11px;word-break:break-all;">' + escapeHtml(i.endpoint_url || '—') + '</span>' +
        (log ? '<br><span style="font-size:11px;color:#78716c;">Última chamada: HTTP ' + escapeHtml(log.status_http) +
          ' · ' + escapeHtml(log.tempo_ms) + 'ms</span>' : '') +
        '</div>';
    }).join('');
  }

  document.getElementById('btn-renew-handshake')?.addEventListener('click', async () => {
    const inativos = cfgState.integracoes.filter(i => i.status !== 'ativo');
    if (!inativos.length) {
      showToast('Todos os conectores já estão ativos.');
      return;
    }
    try {
      for (const integration of inativos) {
        await cfgApi('toggle_integration', { id: integration.id, status: 'ativo' });
        integration.status = 'ativo';
      }
      renderIntegrations();
      renderConnectionsModal();
      showToast(inativos.length + ' conector(es) reativado(s) com sucesso.');
    } catch (err) {
      showToast('Erro ao reativar conectores: ' + err.message, 'error');
    }
  });

  // =========================================================================
  // 6. GESTÃO DE MEMBROS E EQUIPE (RBAC a partir do banco)
  // =========================================================================
  const papelBadge = { gestor: 'admin', fiscal: 'doctor', atendente: 'analyst' };
  const papelLabel = { gestor: 'Gestor Municipal', fiscal: 'Fiscal de Acessibilidade', atendente: 'Atendimento' };
  const avatarColors = ['amber', 'slate', 'purple', 'cyan'];

  function permissionFor(papel, modulo) {
    return cfgState.permissoes.find(p => p.papel === papel && p.modulo === modulo) || null;
  }

  function renderRbacTable() {
    const tbody = document.getElementById('rbac-table-body');
    if (!tbody) return;

    if (!cfgState.equipe.length) {
      tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;color:#78716c;padding:20px;">Nenhum membro da equipe cadastrado.</td></tr>';
      return;
    }

    tbody.innerHTML = cfgState.equipe.map((u, index) => {
      const papel  = u.papel || 'fiscal';
      const perm   = permissionFor(papel, 'solicitacoes');
      const canRead   = !!(perm && parseInt(perm.pode_ler, 10) === 1);
      const canEdit   = !!(perm && parseInt(perm.pode_editar, 10) === 1);
      const canApprove= !!(perm && parseInt(perm.pode_despachar, 10) === 1);
      const isSelf = parseInt(u.id, 10) === cfgState.currentUserId;
      const initials = String(u.name || '?').split(' ').filter(Boolean).slice(0, 2)
        .map(n => n[0]).join('').toUpperCase();
      const color = avatarColors[index % avatarColors.length];
      const badgeClass = papelBadge[papel] || 'support';
      const label = papelLabel[papel] || papel;

      const checkbox = (checked, field) =>
        '<label class="custom-checkbox-label"><input type="checkbox" ' + (checked ? 'checked ' : '') +
        (isSelf ? 'disabled ' : '') + 'data-papel="' + escapeHtml(papel) + '" data-module="solicitacoes" data-field="' +
        field + '" data-user="' + escapeHtml(u.id) + '"></label>';

      return '<tr>' +
        '<td><div class="team-user-identity">' +
          '<div class="team-avatar-circle ' + color + '">' + escapeHtml(initials) + '</div>' +
          '<div class="team-user-details">' +
            '<span class="team-user-name">' + escapeHtml(u.name) + (isSelf ? ' (você)' : '') + '</span>' +
            '<span class="team-user-email">' + escapeHtml(u.email) + '</span>' +
          '</div></div></td>' +
        '<td><span class="role-tag-badge ' + badgeClass + '">' + escapeHtml(u.role || label) + '</span>' +
          '<div style="font-size:10.5px;color:#78716c;margin-top:2px;">' + escapeHtml(label) + ' · ' +
          escapeHtml(u.departamento || '—') + '</div></td>' +
        '<td class="center">' + checkbox(canRead, 'pode_ler') + '</td>' +
        '<td class="center">' + checkbox(canEdit, 'pode_editar') + '</td>' +
        '<td class="center">' + checkbox(canApprove, 'pode_despachar') + '</td>' +
        '<td><span style="font-size:11px;color:#78716c;">Por papel</span></td>' +
      '</tr>';
    }).join('');

    tbody.querySelectorAll('input[type="checkbox"][data-papel]').forEach(input => {
      input.addEventListener('change', () => onPermissionChange(input));
    });
  }

  async function onPermissionChange(input) {
    const papel  = input.dataset.papel;
    const modulo = input.dataset.module;
    const field  = input.dataset.field;
    let perm = permissionFor(papel, modulo);
    if (!perm) {
      perm = { papel: papel, modulo: modulo, pode_ler: 0, pode_criar: 0, pode_editar: 0, pode_excluir: 0, pode_despachar: 0 };
      cfgState.permissoes.push(perm);
    }

    const previous = perm[field];
    perm[field] = input.checked ? 1 : 0;

    try {
      await cfgApi('save_permissions', { permissoes: cfgState.permissoes });
      showToast('Permissões do papel "' + (papelLabel[papel] || papel) + '" atualizadas no banco.');
    } catch (err) {
      perm[field] = previous;
      input.checked = !input.checked;
      showToast('Erro ao salvar permissões: ' + err.message, 'error');
    }
  }

  const btnInviteMember = document.getElementById('btn-invite-member');
  if (btnInviteMember) {
    btnInviteMember.addEventListener('click', () => openModal('modal-invite-member'));
  }

  const cargoToPapel = {
    'Analista de Atendimento': 'atendente',
    'Médico Perito Municipal': 'fiscal',
    'Supervisor de Trânsito': 'fiscal',
    'Auditor de Conformidade': 'fiscal'
  };

  const formInvite = document.getElementById('form-invite-member');
  if (formInvite) {
    formInvite.addEventListener('submit', async (e) => {
      e.preventDefault();
      const nome  = document.getElementById('invite-name')?.value.trim();
      const email = document.getElementById('invite-email')?.value.trim();
      const cargo = document.getElementById('invite-role')?.value || 'Analista de Atendimento';

      if (!nome || !email) {
        showToast('Preencha todos os campos obrigatórios.', 'error');
        return;
      }

      const submitBtn = formInvite.querySelector('button[type="submit"]');
      if (submitBtn) submitBtn.disabled = true;

      try {
        const json = await cfgApi('invite_member', {
          name: nome,
          email: email,
          papel: cargoToPapel[cargo] || 'fiscal',
          cargo: cargo,
          departamento: 'Secretaria Municipal de Mobilidade'
        });

        closeModal('modal-invite-member');
        formInvite.reset();
        await loadSettings();
        showToast(json.temp_password
          ? nome + ' cadastrado. Senha temporária: ' + json.temp_password
          : (json.message || 'Membro adicionado à equipe.'));
      } catch (err) {
        showToast('Erro ao convidar membro: ' + err.message, 'error');
      } finally {
        if (submitBtn) submitBtn.disabled = false;
      }
    });
  }

  // =========================================================================
  // 7. ESCALONAMENTO DE SLA
  // =========================================================================
  const btnEscalate = document.getElementById('btn-configure-escalation');
  if (btnEscalate) {
    btnEscalate.addEventListener('click', () => openModal('modal-escalation'));
  }

  document.getElementById('btn-save-escalation')?.addEventListener('click', async () => {
    try {
      await saveParams({
        escalonamento_aviso_horas:  cfgVal('select-escalation-aviso'),
        escalonamento_atraso_horas: cfgVal('select-escalation-atraso')
      }, 'Regras de escalonamento salvas no banco de dados.');
      closeModal('modal-escalation');
    } catch (err) {
      showToast('Erro ao salvar escalonamento: ' + err.message, 'error');
    }
  });

  // Toggles de SLA salvam imediatamente
  document.getElementById('toggle-concessao-automatica')?.addEventListener('change', async (e) => {
    try {
      await saveParams({ concessao_automatica: e.target.checked ? '1' : '0' }, 'Critério de concessão automática atualizado.');
    } catch (err) {
      e.target.checked = !e.target.checked;
      showToast('Erro ao salvar: ' + err.message, 'error');
    }
  });

  document.getElementById('checkbox-app-notifications')?.addEventListener('change', async (e) => {
    try {
      await saveParams({ notificar_app_cidadao: e.target.checked ? '1' : '0' }, 'Notificações no App Cidadão atualizadas.');
    } catch (err) {
      e.target.checked = !e.target.checked;
      showToast('Erro ao salvar: ' + err.message, 'error');
    }
  });

  function escapeHtml(str) {
    return String(str == null ? '' : str)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

  loadSettings();

  // 7. Modais Genericos
  function openModal(id) {
    const modal = document.getElementById(id);
    if (modal) modal.classList.add('active');
  }

  function closeModal(id) {
    const modal = document.getElementById(id);
    if (modal) modal.classList.remove('active');
  }

  document.querySelectorAll('.btn-modal-close, .btn-modal-cancel').forEach(btn => {
    btn.addEventListener('click', () => {
      const modal = btn.closest('.modal-overlay');
      if (modal) modal.classList.remove('active');
    });
  });

  window.addEventListener('click', (e) => {
    if (e.target.classList.contains('modal-overlay')) {
      e.target.classList.remove('active');
    }
  });

  // 8. Toast Feedback
  let toastTimer = null;
  function showToast(message, type) {
    if (window.PasseLivreAuth && window.PasseLivreAuth.showToast) {
      window.PasseLivreAuth.showToast(message, type); return;
    }
    const toast   = document.getElementById('app-toast');
    const toastMsg = document.getElementById('toast-message');
    if (!toast || !toastMsg) return;
    toastMsg.textContent = message;
    toast.classList.add('visible');
    if (toastTimer) clearTimeout(toastTimer);
    toastTimer = setTimeout(() => { toast.classList.remove('visible'); }, 3800);
  }
  window.showToast = showToast;

  // =========================================================================
  // 9. CARD DE BANCO DE DADOS SQL FÍSICO
  // =========================================================================

  /** Preenche estatísticas em tempo real do banco */
  function loadDBStats() {
    if (!window.PasseLivreDBReady) return;
    window.PasseLivreDBReady.then(() => {
      if (!window.PasseLivreDB) return;
      try {
        const db   = window.PasseLivreDB;
        const stat = (sql) => { const r = db.queryOne(sql); return r ? Object.values(r)[0] : 0; };
        const set  = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = val; };
        set('db-stat-users',  stat('SELECT COUNT(*) FROM users'));
        set('db-stat-locais', stat('SELECT COUNT(*) FROM locais'));
        set('db-stat-sols',   stat('SELECT COUNT(*) FROM solicitacoes'));
        set('db-stat-avals',  stat('SELECT COUNT(*) FROM avaliacoes'));
        set('db-stat-notifs', stat('SELECT COUNT(*) FROM notificacoes'));
        set('db-stat-audit',  stat('SELECT COUNT(*) FROM auditoria'));
      } catch (e) { console.warn('[Configs] Stats do banco:', e); }
    });
  }

  loadDBStats();

  // Botão: Exportar Dump MySQL atualizado
  document.getElementById('btn-download-sql')?.addEventListener('click', () => {
    if (!window.PasseLivreDB) { showToast('Banco ainda não está pronto.', 'error'); return; }
    try {
      window.PasseLivreDB.downloadSQL(
        `banco_mysql_passelivre_${new Date().toISOString().slice(0,10)}.sql`
      );
      showToast('Dump MySQL gerado e exportado com todos os dados atuais!');
    } catch (e) {
      showToast('Erro ao exportar dump MySQL.', 'error');
    }
  });

  // Botão: Ver Schema SQL (faz fetch do banco_mysql.sql e exibe no modal)
  document.getElementById('btn-view-schema')?.addEventListener('click', async () => {
    const modal   = document.getElementById('modal-schema-sql');
    const content = document.getElementById('schema-sql-content');
    if (!modal || !content) return;
    content.textContent = 'Carregando banco_mysql.sql…';
    modal.classList.add('active');
    try {
      const res = await fetch('api/configuracoes.php?action=schema', { credentials: 'same-origin' });
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      content.textContent = await res.text();
    } catch (e) {
      content.textContent = '-- Erro ao carregar banco_mysql.sql\n-- ' + e.message;
    }
  });

  // Botão: Sincronizar com MySQL (recarrega os dados do banco)
  document.getElementById('btn-reload-sql')?.addEventListener('click', () => {
    showToast('Sincronizando estatísticas com o banco MySQL…');
    loadDBStats();
    setTimeout(() => window.location.reload(), 600);
  });

  // Fecha modal do schema ao clicar no X ou fora
  document.getElementById('modal-schema-sql')?.querySelector('.btn-modal-close')
    ?.addEventListener('click', () => document.getElementById('modal-schema-sql')?.classList.remove('active'));
  document.getElementById('modal-schema-sql')?.addEventListener('click', e => {
    if (e.target.id === 'modal-schema-sql') e.target.classList.remove('active');
  });
});
