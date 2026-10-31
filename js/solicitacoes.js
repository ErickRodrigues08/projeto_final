/**
 * Passe Livre Suzano — Gestão de Solicitações
 *
 * Fonte de verdade única: api/solicitacoes.php (PDO → MySQL). A tela não usa
 * PLDB/sql_bridge: antes, uma falha do bridge virava tabela vazia e a triagem
 * existia só como toast, sem nunca gravar status no banco.
 */

document.addEventListener('DOMContentLoaded', () => {

  const container    = document.getElementById('solicitacoes-list-container');
  const searchInput  = document.getElementById('search-solicitacoes-input');
  const btnClear     = document.getElementById('btn-clear-search');
  const statusPills  = Array.from(document.querySelectorAll('.status-pill-filter-btn'));
  const typeFilter   = document.getElementById('filter-occurrence-type');
  const regionFilter = document.getElementById('filter-region-neighborhood');
  const dateFrom     = document.getElementById('date-from');
  const dateTo       = document.getElementById('date-to');
  const btnReset     = document.getElementById('btn-reset-solicitacoes-filters');
  const countText    = document.getElementById('count-found-text');
  const btnExport    = document.getElementById('btn-export-solicitacoes');

  if (!container) return;

  const STATUS_LABELS  = { pending: 'Pendente', analyzing: 'Em Análise', approved: 'Aprovada', rejected: 'Recusada' };
  const PRIORITY_LABELS = { urgente: 'Urgente', alta: 'Alta', media: 'Média', baixa: 'Baixa' };

  const state = {
    status: 'all',
    page: 1,
    limit: 15,
    totalRecords: 0,
    totalPages: 1,
    rows: [],
    requestId: 0,
    busy: false,
    triageId: null
  };

  // =========================================================================
  // 1. CHAMADA À API
  // =========================================================================
  async function api(action, { query = {}, body = null, method = 'POST' } = {}) {
    const url = new URL(`api/solicitacoes.php`, window.location.origin + window.location.pathname.replace(/[^/]*$/, ''));
    url.searchParams.set('action', action);
    Object.entries(query).forEach(([k, v]) => {
      if (v !== null && v !== undefined && v !== '' && v !== 'all') url.searchParams.set(k, v);
    });

    const res = await fetch(url.toString(), {
      method,
      credentials: 'same-origin',
      headers: body !== null ? { 'Content-Type': 'application/json' } : {},
      body: body !== null ? JSON.stringify(body) : null
    });

    let payload = null;
    try {
      payload = await res.json();
    } catch (_) {
      throw { httpStatus: res.status, message: 'O servidor respondeu algo que não é JSON válido.' };
    }
    if (!res.ok || !payload || payload.success !== true) {
      throw {
        httpStatus: res.status,
        message: (payload && payload.error) || `A operação falhou (HTTP ${res.status}).`
      };
    }
    return payload;
  }

  function filtrosAtuais() {
    return {
      status: state.status,
      type: typeFilter ? typeFilter.value : 'all',
      region: regionFilter ? regionFilter.value : 'all',
      q: searchInput ? searchInput.value.trim() : '',
      date_from: dateFrom ? dateFrom.value : '',
      date_to: dateTo ? dateTo.value : ''
    };
  }

  // =========================================================================
  // 2. LISTAGEM
  // =========================================================================
  function renderShell() {
    container.innerHTML = `
      <section class="solicitacoes-table-card">
        <div class="sol-batch-bar" id="sol-batch-bar" hidden>
          <span id="sol-batch-count">Nenhuma solicitação selecionada</span>
          <div class="sol-batch-actions">
            <button type="button" class="btn-batch" id="btn-batch-approve" data-batch="approve">Aprovar selecionadas</button>
            <button type="button" class="btn-batch btn-batch-reject" id="btn-batch-reject" data-batch="reject">Recusar selecionadas</button>
            <button type="button" class="btn-batch btn-batch-clear" id="btn-batch-clear">Limpar seleção</button>
          </div>
        </div>
        <div class="solicitacoes-table-wrap">
          <table class="solicitacoes-table">
            <caption class="sr-only">Solicitações de acessibilidade retornadas do banco de dados</caption>
            <thead>
              <tr>
                <th scope="col"><input type="checkbox" class="custom-table-checkbox" id="checkbox-select-all" aria-label="Selecionar todas as solicitações desta página"></th>
                <th scope="col">Protocolo</th>
                <th scope="col">Solicitante</th>
                <th scope="col">Tipo</th>
                <th scope="col">Local</th>
                <th scope="col">Abertura</th>
                <th scope="col">Status</th>
                <th scope="col">Prioridade</th>
                <th scope="col">Ações</th>
              </tr>
            </thead>
            <tbody id="solicitacoes-tbody"></tbody>
          </table>
          <div class="sol-state" id="sol-list-state" role="status" aria-live="polite"></div>
        </div>
        <div class="solicitacoes-pagination-bar" id="sol-pagination-bar">
          <div class="pagination-rows-selector">
            <label for="sol-rows-per-page">Linhas por página</label>
            <select id="sol-rows-per-page" class="rows-select-control">
              <option value="15">15</option>
              <option value="30">30</option>
              <option value="50">50</option>
            </select>
          </div>
          <span id="sol-range-info"></span>
          <nav aria-label="Paginação das solicitações"><ul class="pagination-nav-list" id="sol-pagination"></ul></nav>
        </div>
      </section>`;

    document.getElementById('sol-rows-per-page').addEventListener('change', (e) => {
      state.limit = Math.max(1, Math.min(100, parseInt(e.target.value, 10) || 15));
      state.page = 1;
      loadList();
    });
    document.getElementById('btn-batch-clear').addEventListener('click', limparSelecao);
    document.getElementById('btn-batch-approve').addEventListener('click', (e) => acaoLote('approve', e.currentTarget));
    document.getElementById('btn-batch-reject').addEventListener('click', (e) => acaoLote('reject', e.currentTarget));
    document.getElementById('checkbox-select-all').addEventListener('change', (e) => {
      document.querySelectorAll('.row-checkbox').forEach(cb => { cb.checked = e.target.checked; });
      atualizarBarraLote();
    });
  }

  function setEstado(html) {
    const box = document.getElementById('sol-list-state');
    const tbody = document.getElementById('solicitacoes-tbody');
    if (!box) return;
    box.innerHTML = html || '';
    box.hidden = !html;
    if (tbody) tbody.innerHTML = '';
    const bar = document.getElementById('sol-pagination-bar');
    if (bar) bar.hidden = Boolean(html);
  }

  async function loadList() {
    const reqId = ++state.requestId;
    state.busy = true;
    container.setAttribute('aria-busy', 'true');
    setEstado('<p class="sol-loading">Consultando o banco de dados…</p>');

    try {
      const payload = await api('list', { query: { ...filtrosAtuais(), page: state.page, limit: state.limit }, method: 'GET' });
      if (reqId !== state.requestId) return; // resposta de filtro anterior descartada

      state.rows = Array.isArray(payload.rows) ? payload.rows : [];
      state.totalRecords = Number(payload.total_records) || 0;
      state.totalPages = Math.max(1, Number(payload.total_pages) || 1);
      state.page = Number(payload.page) || state.page;

      desenharTabela();
      desenharPaginacao();
      atualizarContador();
      limparSelecao();
    } catch (err) {
      if (reqId !== state.requestId) return;
      desenharErroListagem(err);
    } finally {
      if (reqId === state.requestId) {
        state.busy = false;
        container.setAttribute('aria-busy', 'false');
      }
    }
  }

  function desenharErroListagem(err) {
    const status = err && err.httpStatus;
    let msg = err && err.message ? err.message : 'Falha desconhecida ao consultar o banco.';
    let hint = 'Verifique a conexão com o MySQL e tente novamente.';

    if (status === 401 || status === 403) {
      msg = status === 401
        ? 'Sessão não encontrada ou expirada.'
        : 'Esta tela exige privilégios de gestor.';
      hint = status === 401 ? '<a href="login.php">Fazer login novamente</a>' : 'Volte ao painel com uma conta autorizada.';
    }

    setEstado(`
      <div class="sol-state-error" role="alert">
        <strong>Não foi possível carregar as solicitações.</strong>
        <p>${_esc(msg)}</p>
        <p class="sol-state-hint">${hint}</p>
        <button type="button" class="btn-table-view-details" id="btn-retry-list">Tentar novamente</button>
      </div>`);
    document.getElementById('btn-retry-list')?.addEventListener('click', () => loadList());
    if (countText) countText.textContent = 'Erro ao carregar';
  }

  function desenharTabela() {
    const tbody = document.getElementById('solicitacoes-tbody');
    if (!tbody) return;

    if (!state.rows.length) {
      const filtros = filtrosAtuais();
      const temFiltro = Object.entries(filtros).some(([k, v]) => k !== 'status' ? v !== 'all' && v !== '' : v !== 'all');
      setEstado(`
        <div class="sol-state-empty">
          <strong>${temFiltro ? 'Nenhuma solicitação corresponde aos filtros.' : 'Ainda não há solicitações no banco.'}</strong>
          <p>${temFiltro ? 'Ajuste os critérios ou ' : ''}${temFiltro ? '<button type="button" class="link-like" id="btn-empty-reset">redefina os filtros</button>.' : 'Novos relatos de cidadãos aparecerão aqui.'}</p>
        </div>`);
      const r = document.getElementById('btn-empty-reset');
      if (r) r.addEventListener('click', resetFilters);
      atualizarContador();
      return;
    }

    setEstado('');
    tbody.innerHTML = '';
    state.rows.forEach(sol => {
      const tr = document.createElement('tr');
      tr.setAttribute('data-id', sol.id);
      tr.className = 'sol-row';

      const nome = sol.citizen_name || 'Não informado';
      const iniciais = nome.split(' ').filter(Boolean).slice(0, 2).map(n => n[0]).join('').toUpperCase() || '—';
      const aberto = _dataHora(sol.created_at);
      const local = sol.address || 'Endereço não informado';
      const regiaoTxt = sol.region_name || sol.region || '';

      tr.innerHTML = `
        <td><input type="checkbox" class="custom-table-checkbox row-checkbox" value="${Number(sol.id) || 0}" aria-label="Selecionar solicitação ${_esc(sol.protocol || sol.id)}"></td>
        <td class="protocol-cell">
          <div class="protocol-id-number">#${_esc(String(sol.id ?? '—'))}</div>
          <div class="protocol-code-sub">${_esc(sol.protocol || '')}</div>
        </td>
        <td>
          <div class="solicitante-cell">
            <div class="solicitante-avatar" aria-hidden="true">${_esc(iniciais)}</div>
            <div class="solicitante-info">
              <div class="solicitante-name">${_esc(nome)}</div>
              <div class="solicitante-email">${_esc(sol.pcd_category || sol.citizen_email || '')}</div>
            </div>
          </div>
        </td>
        <td><div class="occurrence-type-item"><span class="occurrence-type-icon" aria-hidden="true">•</span><span>${_esc(sol.type_name || sol.type_key || '—')}</span></div></td>
        <td class="location-desc-cell">
          <div class="location-main-address">${_esc(local)}</div>
          <div class="location-sub-complement">${_esc(regiaoTxt)}</div>
        </td>
        <td class="submission-date-cell"><div class="submission-date-val">${_esc(aberto.data)}</div><div class="submission-time-val">${_esc(aberto.hora)}</div></td>
        <td><span class="status-pill-table ${_esc(sol.status || 'pending')}">${_esc(STATUS_LABELS[sol.status] || sol.status || 'Pendente')}</span></td>
        <td><span class="status-pill-table ${_esc(sol.priority || 'media')}">${_esc(PRIORITY_LABELS[sol.priority] || sol.priority || '—')}</span></td>
        <td>
          <div class="sol-row-actions">
            <button type="button" class="btn-table-view-details btn-triage-row" data-id="${Number(sol.id) || 0}" data-protocol="${_esc(sol.protocol || '')}">Triagem</button>
            <button type="button" class="btn-table-view-details btn-table-view-details--ghost btn-view-row" data-id="${Number(sol.id) || 0}">Ver</button>
          </div>
        </td>`;
      tbody.appendChild(tr);
    });

    tbody.querySelectorAll('.btn-view-row').forEach(btn => {
      btn.addEventListener('click', () => {
        window.location.href = `solicitacao-detalhe.php?id=${encodeURIComponent(btn.dataset.id)}`;
      });
    });
    tbody.querySelectorAll('.btn-triage-row').forEach(btn => {
      btn.addEventListener('click', () => abrirModalTriagem(btn.dataset.id, btn.dataset.protocol));
    });
    tbody.querySelectorAll('.row-checkbox').forEach(cb => cb.addEventListener('change', atualizarBarraLote));
  }

  function desenharPaginacao() {
    const ul = document.getElementById('sol-pagination');
    const range = document.getElementById('sol-range-info');
    if (!ul) return;

    const primeiro = state.totalRecords ? (state.page - 1) * state.limit + 1 : 0;
    const ultimo = Math.min(state.totalRecords, state.page * state.limit);
    if (range) range.textContent = `${primeiro}–${ultimo} de ${state.totalRecords}`;

    ul.innerHTML = '';
    const addBtn = (label, page, { disabled = false, active = false, aria = label } = {}) => {
      const li = document.createElement('li');
      const b = document.createElement('button');
      b.type = 'button';
      b.className = `page-nav-btn${active ? ' active' : ''}`;
      b.textContent = label;
      b.disabled = disabled;
      b.setAttribute('aria-label', aria);
      if (active) b.setAttribute('aria-current', 'page');
      b.addEventListener('click', () => { state.page = page; loadList(); });
      li.appendChild(b);
      ul.appendChild(li);
    };

    addBtn('‹', state.page - 1, { disabled: state.page <= 1, aria: 'Página anterior' });
    const paginas = _janelaPaginas(state.page, state.totalPages);
    paginas.forEach(p => {
      if (p === '…') {
        const li = document.createElement('li');
        li.innerHTML = '<span class="page-nav-btn dots" aria-hidden="true">…</span>';
        ul.appendChild(li);
      } else {
        addBtn(String(p), p, { active: p === state.page, aria: `Página ${p}` });
      }
    });
    addBtn('›', state.page + 1, { disabled: state.page >= state.totalPages, aria: 'Próxima página' });
  }

  function atualizarContador() {
    if (!countText) return;
    countText.textContent = state.totalRecords
      ? `${state.totalRecords} solicitaç${state.totalRecords === 1 ? 'ão' : 'ões'}`
      : 'Nenhuma solicitação';
  }

  // =========================================================================
  // 3. KPIs NAS PÍLULAS DE STATUS
  // =========================================================================
  async function carregarKpis() {
    try {
      const payload = await api('kpis', { method: 'GET' });
      const c = payload.status_counts || {};
      const mapa = { all: c.total, pending: c.pending, analyzing: c.analyzing, approved: c.approved, rejected: c.rejected };
      Object.entries(mapa).forEach(([k, v]) => {
        const el = document.getElementById(`count-${k}`);
        if (el) el.textContent = String(Number(v) || 0);
      });
    } catch (err) {
      // Os contadores são apoio visual: a lista já mostrou o erro real se houver.
      ['all', 'pending', 'analyzing', 'approved', 'rejected'].forEach(k => {
        const el = document.getElementById(`count-${k}`);
        if (el && el.textContent === '—') el.textContent = '?';
      });
    }
  }

  // =========================================================================
  // 4. SELEÇÃO EM LOTE → batch_action
  // =========================================================================
  function idsSelecionadas() {
    return Array.from(document.querySelectorAll('.row-checkbox:checked')).map(cb => parseInt(cb.value, 10)).filter(Boolean);
  }

  function atualizarBarraLote() {
    const bar = document.getElementById('sol-batch-bar');
    const sel = idsSelecionadas();
    const label = document.getElementById('sol-batch-count');
    if (!bar) return;
    bar.hidden = sel.length === 0;
    if (label) label.textContent = `${sel.length} solicitaç${sel.length === 1 ? 'ão' : 'ões'} selecionada${sel.length === 1 ? '' : 's'}`;
    const all = document.getElementById('checkbox-select-all');
    const total = document.querySelectorAll('.row-checkbox').length;
    if (all) {
      all.checked = total > 0 && sel.length === total;
      all.indeterminate = sel.length > 0 && sel.length < total;
    }
  }

  function limparSelecao() {
    document.querySelectorAll('.row-checkbox').forEach(cb => { cb.checked = false; });
    const all = document.getElementById('checkbox-select-all');
    if (all) { all.checked = false; all.indeterminate = false; }
    atualizarBarraLote();
  }

  async function acaoLote(batchAction, btn) {
    const ids = idsSelecionadas();
    if (!ids.length) return;
    if (batchAction === 'reject' && !confirm(`Recusar ${ids.length} solicitaç${ids.length === 1 ? 'ão' : 'ões'} selecionada${ids.length === 1 ? '' : 's'}?`)) {
      return;
    }

    const botoes = document.querySelectorAll('#sol-batch-bar button');
    botoes.forEach(b => { b.disabled = true; });
    if (btn) btn.textContent = 'Enviando…';

    try {
      const payload = await api('batch_action', { body: { ids, batch_action: batchAction } });
      showToast(payload.message || 'Status atualizado no banco.', 'success');
      await Promise.all([loadList(), carregarKpis()]);
    } catch (err) {
      showToast('Falha na ação em lote: ' + (err.message || 'erro desconhecido.'), 'error');
      botoes.forEach(b => { b.disabled = false; });
      if (btn) btn.textContent = batchAction === 'approve' ? 'Aprovar selecionadas' : 'Recusar selecionadas';
    }
  }

  // =========================================================================
  // 5. TRIAGEM INDIVIDUAL → update_status
  // =========================================================================
  function abrirModalTriagem(id, protocol) {
    const modal = document.getElementById('modal-triagem');
    if (!modal) return;
    state.triageId = parseInt(id, 10) || null;
    modal.dataset.protocol = protocol || '';
    const alvo = document.getElementById('triagem-target');
    if (alvo) alvo.textContent = protocol ? `Protocolo ${protocol} (ID #${id})` : `Solicitação #${id}`;
    modal.hidden = false;
    modal.classList.add('active');
    document.getElementById('triagem-notes')?.focus();
  }

  function fecharModalTriagem() {
    const modal = document.getElementById('modal-triagem');
    if (!modal) return;
    modal.hidden = true;
    modal.classList.remove('active');
    state.triageId = null;
  }

  window.triageSolicitacao = async function (actionType) {
    const statusMap = { aprovar: 'approved', reanalise: 'analyzing', recusar: 'rejected' };
    const newStatus = statusMap[actionType];
    const id = state.triageId;
    const btn = document.querySelector(`.btn-triage-submit[data-action="${actionType}"]`);

    if (!newStatus) return;
    if (!id) {
      showToast('Selecione uma solicitação antes de registrar o parecer.', 'error');
      return;
    }
    if (actionType === 'recusar' && !confirm('Confirmar o indeferimento desta solicitação?')) return;

    const notes = document.getElementById('triagem-notes')?.value?.trim() || '';
    if (btn) { btn.disabled = true; btn.dataset.label = btn.textContent; btn.textContent = 'Gravando…'; }

    try {
      const payload = await api('update_status', { body: { id, status: newStatus, notes } });
      showToast(payload.message || 'Status gravado no banco.', 'success');
      fecharModalTriagem();
      await Promise.all([loadList(), carregarKpis()]);
    } catch (err) {
      showToast('O status não foi alterado: ' + (err.message || 'falha na comunicação.'), 'error');
      if (btn) { btn.disabled = false; btn.textContent = btn.dataset.label || 'Salvar'; }
    }
  };

  window.fecharModalTriagem = fecharModalTriagem;

  // =========================================================================
  // 6. EXPORTAÇÃO CSV (dados reais do filtro)
  // =========================================================================
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && !document.getElementById('modal-triagem')?.hidden) fecharModalTriagem();
  });

  btnExport?.addEventListener('click', async (e) => {
    const btn = e.currentTarget;
    const original = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span>Gerando arquivo…</span>';

    try {
      const filtros = filtrosAtuais();
      const primeira = await api('list', { query: { ...filtros, page: 1, limit: 100 }, method: 'GET' });
      let rows = Array.isArray(primeira.rows) ? primeira.rows.slice() : [];
      const totalPages = Math.max(1, Number(primeira.total_pages) || 1);
      const teto = 10; // 10 × 100 registros: acima disso a exportação deixa de ser "conjunto filtrado"
      const paginas = Math.min(totalPages, teto);

      for (let p = 2; p <= paginas; p++) {
        const next = await api('list', { query: { ...filtros, page: p, limit: 100 }, method: 'GET' });
        rows = rows.concat(Array.isArray(next.rows) ? next.rows : []);
      }

      if (!rows.length) {
        showToast('Nada para exportar: o filtro atual não retornou solicitações.', 'error');
        return;
      }

      const cabecalho = ['ID', 'Protocolo', 'Solicitante', 'Tipo', 'Endereço', 'Região', 'Status', 'Prioridade', 'Abertura'];
      const linhas = [cabecalho].concat(rows.map(r => [
        r.id, r.protocol, r.citizen_name, r.type_name || r.type_key, r.address,
        r.region_name || r.region, STATUS_LABELS[r.status] || r.status,
        PRIORITY_LABELS[r.priority] || r.priority, r.created_at || ''
      ]));
      const csv = linhas.map(cols => cols.map(v => `"${String(v ?? '').replace(/"/g, '""')}"`).join(',')).join('\r\n');

      const blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' });
      const url = URL.createObjectURL(blob);
      const a = Object.assign(document.createElement('a'), {
        href: url,
        download: `solicitacoes_passe_livre_${new Date().toISOString().slice(0, 10)}.csv`
      });
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
      URL.revokeObjectURL(url);

      const total = Number(primeira.total_records) || rows.length;
      const escopo = 'o conjunto filtrado';
      const aviso = total > rows.length
        ? ` Exportadas ${rows.length} de ${total} do ${escopo}; refina o período para reduzir o arquivo.`
        : ` ${rows.length} registros de ${escopo} exportados.`;
      showToast('Download concluído:' + aviso, 'success');
    } catch (err) {
      showToast('Exportação não gerou arquivo: ' + (err.message || 'falha ao consultar o banco.'), 'error');
    } finally {
      btn.disabled = false;
      btn.innerHTML = original;
    }
  });

  // =========================================================================
  // 7. FILTROS
  // =========================================================================
  let debounceTimer = null;
  function agendarBusca() {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => { state.page = 1; loadList(); }, 350);
  }

  searchInput?.addEventListener('input', agendarBusca);
  searchInput?.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') { e.preventDefault(); clearTimeout(debounceTimer); state.page = 1; loadList(); }
  });
  typeFilter?.addEventListener('change', () => { state.page = 1; loadList(); });
  regionFilter?.addEventListener('change', () => { state.page = 1; loadList(); });
  [dateFrom, dateTo].forEach(inp => inp?.addEventListener('change', () => {
    if (dateFrom?.value && dateTo?.value && dateFrom.value > dateTo.value) {
      showToast('A data inicial está depois da data final.', 'error');
      return;
    }
    state.page = 1;
    loadList();
  }));

  btnClear?.addEventListener('click', () => {
    if (searchInput) { searchInput.value = ''; searchInput.focus(); }
    state.page = 1;
    loadList();
  });

  statusPills.forEach(pill => {
    pill.setAttribute('aria-pressed', pill.classList.contains('active') ? 'true' : 'false');
    pill.addEventListener('click', () => {
      statusPills.forEach(p => {
        p.classList.remove('active');
        p.setAttribute('aria-pressed', 'false');
      });
      pill.classList.add('active');
      pill.setAttribute('aria-pressed', 'true');
      state.status = pill.getAttribute('data-status') || 'all';
      state.page = 1;
      loadList();
    });
  });

  function resetFilters() {
    if (searchInput) searchInput.value = '';
    if (typeFilter) typeFilter.value = 'all';
    if (regionFilter) regionFilter.value = 'all';
    if (dateFrom) dateFrom.value = '';
    if (dateTo) dateTo.value = '';
    state.status = 'all';
    state.page = 1;
    statusPills.forEach(p => {
      const isAll = p.getAttribute('data-status') === 'all';
      p.classList.toggle('active', isAll);
      p.setAttribute('aria-pressed', isAll ? 'true' : 'false');
    });
    loadList();
  }

  btnReset?.addEventListener('click', resetFilters);

  // =========================================================================
  // 8. UTILITÁRIOS
  // =========================================================================
  function _janelaPaginas(atual, total) {
    if (total <= 7) return Array.from({ length: total }, (_, i) => i + 1);
    const set = new Set([1, total, atual, atual - 1, atual + 1]);
    const lista = Array.from(set).filter(p => p >= 1 && p <= total).sort((a, b) => a - b);
    const out = [];
    lista.forEach((p, i) => {
      if (i > 0 && p - lista[i - 1] > 1) out.push('…');
      out.push(p);
    });
    return out;
  }

  function _dataHora(iso) {
    if (!iso) return { data: '—', hora: '' };
    const d = new Date(String(iso).replace(' ', 'T'));
    if (Number.isNaN(d.getTime())) return { data: String(iso).slice(0, 10), hora: String(iso).slice(11, 16) };
    return {
      data: d.toLocaleDateString('pt-BR'),
      hora: d.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })
    };
  }

  function _esc(str) {
    if (str === null || str === undefined) return '';
    return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

  function showToast(msg, type) {
    if (typeof window.showToast === 'function') { window.showToast(msg, type); return; }
    const t = document.getElementById('app-toast'), m = document.getElementById('toast-message');
    if (t && m) { m.textContent = msg; t.classList.add('visible'); setTimeout(() => t.classList.remove('visible'), 4200); }
  }

  // =========================================================================
  // INICIALIZA
  // =========================================================================
  document.getElementById('modal-triagem-close')?.addEventListener('click', fecharModalTriagem);
  document.querySelectorAll('.btn-triage-submit').forEach(b => {
    b.addEventListener('click', () => window.triageSolicitacao(b.dataset.action));
  });

  renderShell();
  loadList();
  carregarKpis();
});
