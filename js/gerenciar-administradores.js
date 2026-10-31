/**
 * Passe Livre Suzano — Tela Gerenciar Administradores
 *
 * Consome api/admins.php (list/grant/revoke). As decisões de quem pode ou não
 * alterar um acesso são do servidor; aqui o que aparece é apenas o resultado
 * já validado por ele (campos can_grant / can_revoke / is_primary / is_self).
 */
(function () {
  'use strict';

  const auth     = window.PasseLivreAuth || {};
  const apiFetch = auth.apiFetch;
  const showToast = auth.showToast || function (msg) { console.warn('[admins]', msg); };

  const csrfMeta = document.querySelector('meta[name="csrf-token"]');
  const CSRF_TOKEN = csrfMeta ? (csrfMeta.getAttribute('content') || '') : '';

  const ENDPOINT = 'api/admins.php';

  const LABEL_NOVO_NIVEL = { grant: 'Administrador', revoke: 'Usuário comum' };

  const state = {
    q: '',
    level: 'all',
    page: 1,
    limit: 10,
    rows: [],
    total: 0,
    totalPages: 1,
    currentUserId: 0,
    pending: null,
    writing: false
  };

  const $ = (id) => document.getElementById(id);

  const els = {
    tbody:        $('admins-tbody'),
    search:       $('admin-search'),
    level:        $('admin-level-filter'),
    refresh:      $('btn-admin-refresh'),
    results:      $('admins-results-count'),
    prev:         $('btn-admin-prev'),
    next:         $('btn-admin-next'),
    pageLabel:    $('admin-page-label'),
    denied:       $('admins-denied'),
    total:        $('adm-total'),
    admins:       $('adm-admins'),
    primary:      $('adm-primary'),
    citizens:     $('adm-citizens'),
    modal:        $('modal-admin-confirm'),
    modalTitle:   $('confirm-title'),
    modalBody:    $('confirm-body'),
    modalName:    $('confirm-user-name'),
    modalMail:    $('confirm-user-mail'),
    modalCode:    $('confirm-user-code'),
    modalLevel:   $('confirm-user-level'),
    modalNew:     $('confirm-user-newlevel'),
    modalWarning: $('confirm-warning-text'),
    btnCancel:    $('btn-confirm-cancel'),
    btnConfirm:   $('btn-confirm-admin')
  };

  // ---------------------------------------------------------------------------
  // Utilidades
  // ---------------------------------------------------------------------------
  function esc(value) {
    return String(value === null || value === undefined ? '' : value)
      .replace(/[&<>"']/g, (c) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
      })[c]);
  }

  function fmtDate(value) {
    if (!value) return '—';
    const d = new Date(String(value).replace(' ', 'T'));
    if (isNaN(d.getTime())) return String(value);
    return d.toLocaleDateString('pt-BR') + ' ' +
           d.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
  }

  function stateRow(text, isError) {
    els.tbody.innerHTML =
      '<tr class="admins-state-row' + (isError ? ' error' : '') + '">' +
      '<td colspan="5">' + esc(text) + '</td></tr>';
  }

  function setBusy(busy) {
    els.refresh.disabled = busy;
    els.prev.disabled = busy || state.page <= 1;
    els.next.disabled = busy || state.page >= state.totalPages;
  }

  // ---------------------------------------------------------------------------
  // Leitura (list)
  // ---------------------------------------------------------------------------
  function renderHead(data) {
    if (!data) return;
    const t = data.totais || {};
    if (els.total)    els.total.textContent    = t.usuarios ?? 0;
    if (els.admins)   els.admins.textContent    = t.administradores ?? 0;
    if (els.primary)  els.primary.textContent   = t.principais ?? 0;
    if (els.citizens) els.citizens.textContent  = t.cidadaos ?? 0;
  }

  function renderFooter() {
    const inicio = state.total === 0 ? 0 : (state.page - 1) * state.limit + 1;
    const fim    = Math.min(state.page * state.limit, state.total);
    let texto = state.total === 0
      ? 'Nenhuma conta encontrada'
      : `Exibindo ${inicio}–${fim} de ${state.total} conta(s)`;
    if (state.q)    texto += ` · busca "${state.q}"`;
    if (state.level !== 'all') texto += ' · filtro por nível';
    els.results.textContent = texto;
    els.pageLabel.textContent = `Página ${state.page} de ${state.totalPages}`;
  }

  function actionCell(row) {
    if (row.is_primary) {
      return '<span class="admins-protected-note">' +
             '<img src="assets/icons/lock.svg" alt="" aria-hidden="true" style="width:12px;height:12px;vertical-align:-1px;"> ' +
             'Conta protegida — nível não alterável</span>';
    }
    if (row.is_self) {
      return '<span class="admins-protected-note">Seu próprio acesso não pode ser alterado aqui</span>';
    }
    if (row.status !== 'ativo') {
      return '<span class="admins-protected-note">Conta inativa: reative a conta antes de conceder acesso</span>';
    }
    if (row.can_grant) {
      return '<button type="button" class="btn-grant-access" data-action="grant" data-id="' + row.id + '">' +
             '<img src="assets/icons/shield-check.svg" alt="" aria-hidden="true" style="width:13px;height:13px;filter:brightness(0) invert(1);">' +
             'Conceder acesso</button>';
    }
    if (row.can_revoke) {
      return '<button type="button" class="btn-revoke-access" data-action="revoke" data-id="' + row.id + '">' +
             'Remover acesso</button>';
    }
    return '<span class="admins-protected-note">Sem ações disponíveis</span>';
  }

  function renderRows() {
    if (!state.rows.length) {
      stateRow('Nenhuma conta corresponde à busca e ao filtro selecionados.');
      return;
    }

    els.tbody.innerHTML = state.rows.map((row) => {
      const badge = '<span class="level-badge ' + esc(row.level) + '">' + esc(row.level_label) + '</span>';
      const statusCls = row.status === 'ativo' ? 'ativo'
        : (row.status === 'pendente' ? 'pendente' : 'bloqueado');
      const desde = row.level === 'cidadao'
        ? '<span class="admins-role">—</span>'
        : '<span class="admins-role" title="Última alteração de acesso registrado no banco">' + esc(fmtDate(row.access_since)) + '</span>';

      return '<tr data-id="' + row.id + '">' +
        '<td><div class="admins-user-cell">' +
          '<span class="admins-avatar" aria-hidden="true">' + esc(row.initials) + '</span>' +
          '<span><span class="admins-user-name">' + esc(row.name) +
            (row.is_self ? ' <span class="admins-user-code">(você)</span>' : '') +
          '</span>' +
          '<span class="admins-user-mail">' + esc(row.email) + '</span>' +
          '<span class="admins-user-code">' + esc(row.user_code) + '</span></span>' +
        '</div></td>' +
        '<td><span class="admins-role">' + esc(row.role) + '</span></td>' +
        '<td>' + badge + '</td>' +
        '<td><span class="status-badge-pill ' + statusCls + '">' + esc(row.status) + '</span></td>' +
        '<td>' + desde + '<div style="margin-top:6px;">' + actionCell(row) + '</div></td>' +
      '</tr>';
    }).join('');
  }

  async function load() {
    if (!apiFetch) {
      stateRow('Scripts do painel não carregaram. Recarregue a página.', true);
      return;
    }

    const params = new URLSearchParams({
      action: 'list',
      q: state.q,
      level: state.level,
      page: String(state.page),
      limit: String(state.limit)
    });

    setBusy(true);
    try {
      const data = await apiFetch(ENDPOINT + '?' + params.toString(), {
        method: 'GET',
        credentials: 'same-origin'
      });

      els.denied.classList.remove('active');
      state.rows        = Array.isArray(data.rows) ? data.rows : [];
      state.total       = Number(data.total) || 0;
      state.totalPages  = Number(data.total_pages) || 1;
      state.currentUserId = Number(data.current_user_id) || 0;

      renderHead(data);
      renderRows();
      renderFooter();
    } catch (err) {
      if (err && err.status === 403 && err.data && err.data.code === 'admin_principal_exigido') {
        els.denied.classList.add('active');
        stateRow('Acesso restrito ao administrador principal.');
      } else {
        stateRow((err && err.message) || 'Não foi possível carregar as contas agora.', true);
      }
      if (err && err.status === 401 && auth.requireAdmin) auth.requireAdmin();
    } finally {
      setBusy(false);
    }
  }

  // ---------------------------------------------------------------------------
  // Escrita (grant / revoke) — sempre com POST + token CSRF
  // ---------------------------------------------------------------------------
  async function write(action, id) {
    const data = await apiFetch(ENDPOINT + '?action=' + action, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': CSRF_TOKEN
      },
      body: JSON.stringify({ id: id, csrf_token: CSRF_TOKEN })
    });
    return data;
  }

  function openConfirm(action, id) {
    const row = state.rows.find((r) => Number(r.id) === Number(id));
    if (!row) return;

    state.pending = { action: action, id: row.id, name: row.name };

    els.modalTitle.textContent = action === 'grant'
      ? 'Conceder acesso de administrador?'
      : 'Remover acesso de administrador?';

    els.modalBody.textContent = action === 'grant'
      ? 'A conta continua a mesma (mesmo e-mail, senha e perfil) e passa a entrar no Painel do Gestor no próximo login. Sessões abertas desta conta serão encerradas.'
      : 'A conta volta a ser usuário comum e perde o acesso ao Painel do Gestor na hora. As sessões abertas dela são encerradas imediatamente.';

    els.modalName.textContent = row.name;
    els.modalMail.textContent = row.email;
    els.modalCode.textContent = row.user_code || '—';
    els.modalLevel.textContent = row.level_label;
    els.modalNew.textContent   = LABEL_NOVO_NIVEL[action];

    els.modalWarning.textContent = action === 'grant'
      ? 'Conceda apenas a pessoas que realmente vão operar o painel. A alteração fica registrada no histórico de auditoria.'
      : 'A remoção é imediata e a pessoa não consegue mais entrar no painel. A alteração fica registrada no histórico de auditoria.';

    els.btnConfirm.classList.toggle('danger', action === 'revoke');
    els.btnConfirm.textContent = action === 'grant' ? 'Conceder acesso' : 'Remover acesso';
    els.btnConfirm.disabled = false;

    els.modal.classList.add('active');
    els.btnConfirm.focus();
  }

  function closeConfirm() {
    state.pending = null;
    els.modal.classList.remove('active');
  }

  async function confirmPending() {
    if (!state.pending || state.writing) return;

    const { action, id } = state.pending;
    state.writing = true;
    els.btnConfirm.disabled = true;
    els.btnConfirm.textContent = 'Aplicando...';

    try {
      const data = await write(action, id);
      closeConfirm();
      showToast((data && data.message) || 'Alteração aplicada.', 'success');
      await load();
    } catch (err) {
      els.btnConfirm.disabled = false;
      els.btnConfirm.textContent = action === 'grant' ? 'Conceder acesso' : 'Remover acesso';

      const code = err && err.data ? err.data.code : '';
      if (code === 'csrf_invalido') {
        showToast('Sessão de segurança inválida ou expirada. Recarregue a página e tente novamente.', 'error');
        closeConfirm();
        await load();
      } else if (err && err.status === 403 && code === 'admin_principal_exigido') {
        els.denied.classList.add('active');
        closeConfirm();
        await load();
      } else {
        showToast((err && err.message) || 'Não foi possível aplicar a alteração.', 'error');
        await load();
      }
    } finally {
      state.writing = false;
    }
  }

  // ---------------------------------------------------------------------------
  // Eventos
  // ---------------------------------------------------------------------------
  let searchTimer = null;
  els.search.addEventListener('input', () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
      state.q = els.search.value.trim();
      state.page = 1;
      load();
    }, 350);
  });

  els.level.addEventListener('change', () => {
    const allowed = ['all', 'principal', 'admin', 'cidadao'];
    state.level = allowed.includes(els.level.value) ? els.level.value : 'all';
    state.page = 1;
    load();
  });

  els.refresh.addEventListener('click', () => load());

  els.prev.addEventListener('click', () => {
    if (state.page > 1) { state.page -= 1; load(); }
  });

  els.next.addEventListener('click', () => {
    if (state.page < state.totalPages) { state.page += 1; load(); }
  });

  els.tbody.addEventListener('click', (event) => {
    const btn = event.target.closest('button[data-action]');
    if (!btn) return;
    openConfirm(btn.dataset.action, Number(btn.dataset.id));
  });

  els.btnConfirm.addEventListener('click', confirmPending);
  els.btnCancel.addEventListener('click', closeConfirm);

  els.modal.addEventListener('click', (event) => {
    if (event.target === els.modal) closeConfirm();
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && els.modal.classList.contains('active')) closeConfirm();
  });

  load();
})();
