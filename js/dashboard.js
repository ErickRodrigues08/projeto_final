/**
 * Passe Livre Suzano — Painel do Gestor (Dashboard)
 * Integração SQL: KPIs, gráfico mensal e tabela de solicitações recentes via PLDB
 */

document.addEventListener('DOMContentLoaded', () => {

  // =========================================================================
  // 0. TOAST
  // =========================================================================
  let toastTimer = null;
  window.showToast = function (message, type) {
    if (window.PasseLivreAuth && window.PasseLivreAuth.showToast) {
      window.PasseLivreAuth.showToast(message, type); return;
    }
    const toast   = document.getElementById('app-toast');
    const toastMsg = document.getElementById('toast-message');
    if (!toast || !toastMsg) return;
    toastMsg.textContent = message;
    toast.classList.add('visible');
    if (toastTimer) clearTimeout(toastTimer);
    toastTimer = setTimeout(() => toast.classList.remove('visible'), 3600);
  };

  // =========================================================================
  // 1. LOGOUT
  // =========================================================================
  document.getElementById('btn-logout')?.addEventListener('click', () => {
    if (window.PasseLivreAuth) window.PasseLivreAuth.clearUser();
    window.location.href = 'index.php';
  });

  // =========================================================================
  // 2. CARREGA KPIs DO BANCO SQL
  // =========================================================================
  function loadKPIs() {
    if (!window.PLDB) return;
    try {
      const counts = window.PLDB.solicitacoes.countByStatus();
      const map    = {};
      counts.forEach(r => { map[r.status] = r.total; });

      const total    = window.PLDB.solicitacoes.count();
      const pending  = map['pending']   || 0;
      const analyz   = map['analyzing'] || 0;
      const approved = map['approved']  || 0;
      const rejected = map['rejected']  || 0;
      const users    = window.PLDB.users.count();

      // Atualiza elementos KPI na tela
      _setKPI('kpi-total',    total);
      _setKPI('kpi-pending',  pending);
      _setKPI('kpi-analyz',   analyz);
      _setKPI('kpi-approved', approved);
      _setKPI('kpi-rejected', rejected);
      _setKPI('kpi-users',    users);

      // Badge da sidebar
      if (window.PasseLivreAuth) window.PasseLivreAuth.setGestorPendingCount(pending);
      document.querySelectorAll('.menu-badge-counter').forEach(b => {
        b.textContent = String(pending);
        b.style.display = pending > 0 ? '' : 'none';
      });

      console.log(`[Dashboard SQL] KPIs: total=${total}, pending=${pending}, aprovados=${approved}`);
    } catch (e) {
      console.warn('[Dashboard] Falha ao carregar KPIs via SQL:', e);
    }
  }

  function _setKPI(id, value) {
    const el = document.getElementById(id);
    if (el) el.textContent = Number(value).toLocaleString('pt-BR');
  }

  // =========================================================================
  // 3. GRÁFICO DE BARRAS — dados do banco SQL (últimos 6 meses)
  // =========================================================================
  const chartDataPeriods = {
    '7d':  { label: 'últimos 7 dias',           months: null, useSQL: false },
    '30d': { label: 'últimos 30 dias',          months: null, useSQL: false },
    '6m':  { label: 'últimos 6 meses',          months: 6,   useSQL: true  },
    '1y':  { label: 'último ano (2025-2026)',   months: 12,  useSQL: true  }
  };

  // Dados de fallback para 7d / 30d
  const fallbackBars = {
    '7d':  { months: ['Seg','Ter','Qua','Qui','Sex','Sáb','Dom'], bars: [{y:45,g:38,r:8},{y:60,g:50,r:10},{y:75,g:65,r:12},{y:85,g:70,r:14},{y:110,g:95,r:18},{y:50,g:40,r:6},{y:95,g:80,r:15}] },
    '30d': { months: ['Sem 1','Sem 2','Sem 3','Sem 4'],           bars: [{y:70,g:60,r:12},{y:90,g:78,r:15},{y:115,g:98,r:20},{y:130,g:112,r:22}] }
  };

  const periodButtons  = document.querySelectorAll('.period-pill-btn');
  const chartContainer = document.querySelector('.bar-chart-container');

  function renderChart(periodKey) {
    if (!chartContainer) return;
    chartContainer.innerHTML = '';

    const cfg = chartDataPeriods[periodKey] || chartDataPeriods['6m'];

    // Tenta SQL para períodos mensais
    if (cfg.useSQL && window.PLDB) {
      try {
        const sqlData = window.PLDB.solicitacoes.monthlyChart(cfg.months);
        if (sqlData && sqlData.length) {
          sqlData.forEach(m => {
            const yellow = Math.min(m.total,    135);
            const green  = Math.min(m.approved, 135);
            const red    = Math.min(m.rejected, 135);
            _appendBar(chartContainer, m.label, yellow, green, red, m.total, m.approved, m.rejected);
          });
          return;
        }
      } catch (_) {}
    }

    // Fallback
    const fb = fallbackBars[periodKey];
    if (fb) {
      fb.months.forEach((m, idx) => {
        const b = fb.bars[idx] || {y:60,g:50,r:10};
        _appendBar(chartContainer, m, b.y, b.g, b.r, b.y, b.g, b.r);
      });
    }
  }

  function _appendBar(container, label, yH, gH, rH, yVal, gVal, rVal) {
    const col = document.createElement('div');
    col.className = 'chart-month-col';
    col.innerHTML = `
      <div class="bars-group" title="${label}: Recebidas: ${yVal}, Aprovadas: ${gVal}, Recusadas: ${rVal}">
        <div class="bar-item yellow" style="height:${yH}px;" data-val="${yVal}"></div>
        <div class="bar-item green"  style="height:${gH}px;" data-val="${gVal}"></div>
        <div class="bar-item red"    style="height:${rH}px;" data-val="${rVal}"></div>
      </div>
      <span class="month-label">${label}</span>`;
    container.appendChild(col);
  }

  periodButtons.forEach(btn => {
    btn.addEventListener('click', () => {
      periodButtons.forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      const period = btn.getAttribute('data-period') || '6m';
      renderChart(period);
      showToast(`Visualizando solicitações: ${chartDataPeriods[period]?.label || period}`);
    });
  });

  // =========================================================================
  // 4. TABELA DE SOLICITAÇÕES RECENTES — SELECT SQL
  // =========================================================================
  function loadRecentTable() {
    if (!window.PLDB) return;
    try {
      const rows  = window.PLDB.solicitacoes.findAll();
      const tbody = document.querySelector('.recent-requests-table tbody');
      if (!tbody || !rows.length) return;

      tbody.innerHTML = '';
      rows.slice(0, 10).forEach(sol => {
        const initials = (sol.citizen_name || '').split(' ').slice(0,2).map(n => n[0]).join('').toUpperCase();
        const statusMap = { pending: 'pending', analyzing: 'analyzing', approved: 'approved', rejected: 'rejected' };
        const statusLabel = { pending: 'Pendente', analyzing: 'Em Análise', approved: 'Aprovada', rejected: 'Recusada' };
        const dateStr = sol.created_at ? sol.created_at.slice(0, 10).split('-').reverse().join('/') : '-';

        const tr = document.createElement('tr');
        tr.setAttribute('data-id',       sol.id);
        tr.setAttribute('data-citizen',  sol.citizen_name);
        tr.setAttribute('data-type',     sol.type_name || sol.type_key);
        tr.setAttribute('data-location', sol.address);
        tr.setAttribute('data-date',     dateStr);
        tr.setAttribute('data-status',   statusLabel[sol.status] || sol.status);
        tr.innerHTML = `
          <td><span class="request-id-tag">#${sol.id}</span></td>
          <td>
            <div class="citizen-cell">
              <div class="citizen-avatar">${initials}</div>
              <span class="citizen-name">${_esc(sol.citizen_name)}</span>
            </div>
          </td>
          <td>
            <div class="occurrence-type-cell">
              <img src="${sol.type_icon || 'assets/icons/pin.svg'}" alt="" onerror="this.src='assets/icons/pin.svg'">
              <span>${_esc(sol.type_name || sol.type_key)}</span>
            </div>
          </td>
          <td>
            <div class="location-cell">
              <img src="assets/icons/pin.svg" alt="">
              <span>${_esc(sol.address)}</span>
            </div>
          </td>
          <td>${dateStr}</td>
          <td><span class="status-badge-pill ${statusMap[sol.status] || 'pending'}">${statusLabel[sol.status] || sol.status}</span></td>
          <td><button type="button" class="btn-view-request">Visualizar</button></td>`;
        tbody.appendChild(tr);
      });

      const totalLabel = document.querySelector('.table-pagination-footer span');
      if (totalLabel) totalLabel.textContent = `Exibindo ${Math.min(rows.length, 10)} de ${rows.length} registros totais`;
      console.log(`[Dashboard SQL] ${rows.length} solicitações carregadas na tabela.`);
    } catch (e) {
      console.warn('[Dashboard] Falha ao carregar tabela via SQL:', e);
    }
  }

  // =========================================================================
  // 5. FILTRO DA TABELA
  // =========================================================================
  let currentFilter = 'all';

  function applyTableFilter() {
    const rows = document.querySelectorAll('.recent-requests-table tbody tr');
    let visible = 0;
    rows.forEach(row => {
      const status = row.getAttribute('data-status') || '';
      const show   = currentFilter === 'all' || status === currentFilter;
      row.style.display = show ? '' : 'none';
      if (show) visible++;
    });
    const totalLabel = document.querySelector('.table-pagination-footer span');
    if (totalLabel) {
      const suffix = currentFilter === 'all' ? 'registros totais' : `com status "${currentFilter}"`;
      totalLabel.textContent = `Exibindo ${visible} ${suffix}`;
    }
  }

  // =========================================================================
  // 6. MODAL — Visualizar Solicitação
  // =========================================================================
  let currentTriageRow = null;

  function openRequestModal(id, citizen, type, location, date, status) {
    const modal = document.getElementById('modal-view-request');
    if (!modal) return;
    currentTriageRow = document.querySelector(`.recent-requests-table tbody tr[data-id="${id}"]`);
    document.getElementById('modal-req-id').textContent       = `#${id}`;
    document.getElementById('modal-req-title').textContent    = type;
    document.getElementById('modal-req-citizen').textContent  = citizen;
    document.getElementById('modal-req-location').textContent = location;
    document.getElementById('modal-req-date').textContent     = date;
    const statusEl = document.getElementById('modal-req-status');
    if (statusEl) {
      statusEl.textContent = status;
      statusEl.className   = 'status-badge-pill ' +
        (status === 'Pendente' ? 'pending' : status === 'Em Análise' ? 'analyzing' : 'approved');
    }
    modal.classList.add('active');
  }

  document.getElementById('btn-analyze-critical')?.addEventListener('click', () => {
    openRequestModal('1', 'Mariana Souza', 'Semáforo Sonoro Inoperante', 'Av. Armando Salles, 840', '03/09/2026', 'Pendente');
  });

  document.addEventListener('click', e => {
    const btn = e.target.closest('.btn-view-request');
    if (!btn) return;
    e.preventDefault();
    const row = btn.closest('tr');
    if (!row) return;
    openRequestModal(
      row.getAttribute('data-id')       || '1',
      row.getAttribute('data-citizen')  || '',
      row.getAttribute('data-type')     || '',
      row.getAttribute('data-location') || '',
      row.getAttribute('data-date')     || '',
      row.getAttribute('data-status')   || 'Pendente'
    );
  });

  // =========================================================================
  // 7. TRIAGEM — UPDATE SQL
  // =========================================================================
  window.triageAction = function (actionType) {
    const modal = document.getElementById('modal-view-request');
    if (modal) modal.classList.remove('active');

    const statusMap  = { aprovar: 'approved', reanalise: 'analyzing', recusar: 'rejected' };
    const labelMap   = { aprovar: 'Aprovada', reanalise: 'Em Análise', recusar: 'Recusada' };
    const msgMap = {
      aprovar:   'Solicitação deferida e encaminhada à Secretaria de Manutenção Urbana!',
      reanalise: 'Diligência pericial aberta. Cidadão notificado.',
      recusar:   'Solicitação arquivada conforme parecer técnico.'
    };

    const newStatus = statusMap[actionType];
    const newLabel  = labelMap[actionType];

    // UPDATE SQL
    if (currentTriageRow && newStatus && window.PLDB) {
      const id = parseInt(currentTriageRow.getAttribute('data-id') || '0', 10);
      if (id > 0) {
        try {
          window.PLDB.solicitacoes.updateStatus(id, newStatus, newLabel);
          console.log(`[Dashboard SQL] Solicitação #${id} → ${newStatus}`);
        } catch (e) {
          console.warn('[Dashboard] UPDATE SQL falhou:', e);
        }
      }
      // Atualiza DOM
      currentTriageRow.setAttribute('data-status', newLabel);
      const badge = currentTriageRow.querySelector('.status-badge-pill');
      if (badge) {
        badge.textContent  = newLabel;
        badge.className    = 'status-badge-pill ' +
          (newStatus === 'approved' ? 'approved' : newStatus === 'analyzing' ? 'analyzing' : newStatus === 'rejected' ? 'rejected' : 'pending');
      }
      applyTableFilter();

      // Recalcula KPIs e badge de pendentes via SQL
      loadKPIs();
      if (window.PLDB) {
        const p = window.PLDB.solicitacoes.countPending();
        if (window.PasseLivreAuth) window.PasseLivreAuth.setGestorPendingCount(p);
      }
    }
    currentTriageRow = null;
    showToast(msgMap[actionType] || 'Ação realizada.');
  };

  // =========================================================================
  // 8. NOVA SOLICITAÇÃO — INSERT SQL
  // =========================================================================
  document.getElementById('btn-new-request')?.addEventListener('click', () => {
    document.getElementById('modal-new-request')?.classList.add('active');
  });

  document.getElementById('form-new-request')?.addEventListener('submit', e => {
    e.preventDefault();
    const citizen = document.getElementById('new-req-citizen')?.value.trim();
    const type    = document.getElementById('new-req-type')?.value;
    const address = document.getElementById('new-req-address')?.value.trim();
    if (!citizen || !address) { showToast('Preencha os campos obrigatórios.', 'error'); return; }

    let newId = 'new';
    if (window.PLDB) {
      try {
        const created = window.PLDB.solicitacoes.create({
          citizen_name: citizen, type_key: type || 'outro', type_name: type || 'Outro',
          title: type || 'Nova Ocorrência', address: address, status: 'pending'
        });
        if (created) { newId = created.id; loadRecentTable(); }
      } catch (e) {
        console.warn('[Dashboard] INSERT SQL solicitação falhou:', e);
      }
    }

    document.getElementById('modal-new-request')?.classList.remove('active');
    document.getElementById('form-new-request')?.reset();
    showToast(`Solicitação #${newId} cadastrada no banco com sucesso!`);
  });

  // =========================================================================
  // 9. FILTRAR TABELA
  // =========================================================================
  document.getElementById('btn-filter-recent')?.addEventListener('click', () => {
    const existing = document.getElementById('filter-dropdown-menu');
    if (existing) { existing.remove(); return; }
    const menu = document.createElement('div');
    menu.id = 'filter-dropdown-menu';
    menu.style.cssText = 'position:absolute;background:#fff;border:1px solid #e7ded2;border-radius:10px;box-shadow:0 8px 24px rgba(0,0,0,.12);padding:6px;z-index:200;min-width:180px;font-size:13px;';
    [['Todos os status','all'],['Pendentes','Pendente'],['Em Análise','Em Análise'],['Aprovadas','Aprovada'],['Recusadas','Recusada']].forEach(([label, val]) => {
      const item = document.createElement('button');
      item.type = 'button'; item.textContent = label;
      item.style.cssText = `display:block;width:100%;text-align:left;padding:7px 12px;border:none;background:${currentFilter===val?'#fff7ed':'transparent'};border-radius:6px;cursor:pointer;color:#292524;`;
      item.addEventListener('click', () => { currentFilter = val; applyTableFilter(); menu.remove(); showToast(val==='all'?'Exibindo todas as solicitações.':`Filtro: ${label}`); });
      menu.appendChild(item);
    });
    const btn = document.getElementById('btn-filter-recent');
    btn.style.position = 'relative';
    btn.appendChild(menu);
    setTimeout(() => {
      document.addEventListener('click', function h(e) { if (!menu.contains(e.target) && e.target !== btn) { menu.remove(); document.removeEventListener('click', h); } });
    }, 50);
  });

  // =========================================================================
  // 10. EXPORTAR CSV
  // =========================================================================
  document.getElementById('btn-export-recent')?.addEventListener('click', () => {
    const rows  = document.querySelectorAll('.recent-requests-table tbody tr');
    const lines = [['ID','Cidadão','Tipo','Local','Data','Status']];
    rows.forEach(row => {
      if (row.style.display === 'none') return;
      lines.push([row.getAttribute('data-id')||'', row.getAttribute('data-citizen')||'', row.getAttribute('data-type')||'',
                  row.getAttribute('data-location')||'', row.getAttribute('data-date')||'', row.getAttribute('data-status')||'']);
    });
    const csv  = lines.map(l => l.map(v => `"${v}"`).join(',')).join('\n');
    const blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' });
    const url  = URL.createObjectURL(blob);
    const a    = document.createElement('a');
    a.href = url; a.download = 'Solicitacoes_PasseLivre_Suzano.csv';
    document.body.appendChild(a); a.click(); document.body.removeChild(a);
    URL.revokeObjectURL(url);
    showToast('Download concluído: Solicitacoes_PasseLivre_Suzano.csv');
  });

  // =========================================================================
  // 11. MODAIS GENÉRICOS
  // =========================================================================
  document.querySelectorAll('.btn-modal-close, .btn-modal-cancel').forEach(btn => {
    btn.addEventListener('click', () => { btn.closest('.modal-overlay')?.classList.remove('active'); });
  });
  window.addEventListener('click', e => {
    if (e.target.classList.contains('modal-overlay')) e.target.classList.remove('active');
  });

  document.querySelector('.link-detailed-report')?.addEventListener('click', e => {
    e.preventDefault(); showToast('Gerando relatório analítico via banco SQL!');
    setTimeout(() => window.location.href = 'relatorios.php', 500);
  });

  // =========================================================================
  // INICIALIZA — aguarda banco SQL
  // =========================================================================
  function init() {
    loadKPIs();
    loadRecentTable();
    renderChart('6m');
  }

  if (window.PasseLivreDBReady) {
    window.PasseLivreDBReady.then(init);
  } else {
    init(); // fallback imediato
  }

  // =========================================================================
  // HELPER
  // =========================================================================
  function _esc(str) {
    if (!str) return '';
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }
});
