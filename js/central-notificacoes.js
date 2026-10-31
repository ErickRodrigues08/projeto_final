/**
 * Passe Livre Suzano — Central de Notificações do Cidadão (JS)
 * central-notificacoes.js
 *
 * Exibe, filtra e gerencia as notificações do cidadão autenticado diretamente no MySQL.
 * Segurança: Isolamento estrito por usuário via api/notificacoes.php e token de sessão.
 */

document.addEventListener('DOMContentLoaded', async () => {
  'use strict';

  // 1. Validação de Autenticação
  // A página já é protegida no servidor; o perfil local só é exigido se existir,
  // pois a sessão também pode estar apenas no cookie httpOnly.
  const localUser = window.PasseLivreAuth?.getUser?.();
  if (localUser && window.PasseLivreAuth && !window.PasseLivreAuth.requireAuth()) {
    return;
  }

  // Elementos do DOM
  const feedContainer   = document.getElementById('notif-feed-container');
  const searchInput     = document.getElementById('notif-search-input');
  const filterChips     = document.querySelectorAll('.filter-chip');
  const btnMarkAllRead  = document.getElementById('btn-mark-all-read');
  const btnRefresh      = document.getElementById('btn-refresh-feed');

  // KPIs e Contadores
  const kpiUnread       = document.getElementById('kpi-unread-count');
  const kpiTotal        = document.getElementById('kpi-total-count');
  const chipTodas       = document.getElementById('chip-count-todas');
  const chipNaoLidas    = document.getElementById('chip-count-nao-lidas');

  // Modal
  const modalOverlay    = document.getElementById('modal-notif-detail');
  const modalCloseBtn   = document.getElementById('btn-modal-close');
  const modalDismissBtn = document.getElementById('btn-modal-dismiss');
  const modalType       = document.getElementById('modal-notif-type');
  const modalTitle      = document.getElementById('modal-notif-title');
  const modalSender     = document.getElementById('modal-notif-sender');
  const modalDate       = document.getElementById('modal-notif-date');
  const modalStatus     = document.getElementById('modal-notif-status');
  const modalContent    = document.getElementById('modal-notif-content');
  const modalLinkBtn    = document.getElementById('modal-notif-link-btn');

  // Toast
  const toastEl         = document.getElementById('app-toast');
  const toastMsgEl      = document.getElementById('toast-message');

  // Estado Local
  let allNotifications = [];
  let currentFilter    = 'todas';
  let currentSearch    = '';
  let isLoading        = false;

  // Ícones SVG por Tipo
  const TYPE_ICONS = {
    rota: `<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="6" cy="19" r="3"/><path d="M9 19h8.5a3.5 3.5 0 0 0 0-7h-11a3.5 3.5 0 0 1 0-7H15"/><circle cx="18" cy="5" r="3"/></svg>`,
    alerta: `<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>`,
    alert: `<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>`,
    warning: `<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>`,
    info: `<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>`,
    sistema: `<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>`,
    gestor: `<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12 19.79 19.79 0 0 1 1.61 3.4 2 2 0 0 1 3.6 1.22h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 8.8a16 16 0 0 0 6 6l.86-.86a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 21.59 16"/></svg>`,
    conquista: `<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>`
  };

  const TYPE_LABELS = {
    rota: 'Rota Acessível',
    alerta: 'Alerta de Via',
    alert: 'Alerta de Via',
    warning: 'Aviso Importante',
    info: 'Informação Oficial',
    sistema: 'Sistema & Conta',
    gestor: 'Despacho da Gestão',
    conquista: 'Conquista Cidadã'
  };

  function getToken() {
    return localStorage.getItem('passeLivre_token') || localStorage.getItem('passeLivreToken') || '';
  }

  function showToast(msg, isError = false) {
    if (!toastEl || !toastMsgEl) return;
    toastMsgEl.textContent = msg;
    toastEl.style.backgroundColor = isError ? '#991b1b' : '#15803d';
    toastEl.classList.add('visible');
    setTimeout(() => { toastEl.classList.remove('visible'); }, 3500);
  }

  function escapeHtml(text) {
    if (!text) return '';
    return String(text)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function formatDateTime(dateStr) {
    if (!dateStr) return 'Data recente';
    try {
      const d = new Date(dateStr.replace(' ', 'T'));
      if (isNaN(d.getTime())) return dateStr;
      return d.toLocaleDateString('pt-BR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
      });
    } catch (_) {
      return dateStr;
    }
  }

  function formatRelativeAgo(dateStr) {
    if (!dateStr) return '';
    try {
      const d = new Date(dateStr.replace(' ', 'T'));
      const mins = Math.floor((Date.now() - d.getTime()) / 60000);
      if (mins < 1) return 'Agora mesmo';
      if (mins < 60) return `Há ${mins} min`;
      const hours = Math.floor(mins / 60);
      if (hours < 24) return `Há ${hours}h`;
      const days = Math.floor(hours / 24);
      if (days === 1) return 'Ontem';
      if (days < 7) return `Há ${days} dias`;
      return d.toLocaleDateString('pt-BR');
    } catch (_) {
      return '';
    }
  }

  // -------------------------------------------------------------------------
  // 2. CARREGAR NOTIFICAÇÕES VIA API (MYSQL)
  // -------------------------------------------------------------------------
  async function loadNotifications() {
    if (isLoading) return;
    isLoading = true;
    feedContainer.setAttribute('aria-busy', 'true');

    try {
      const token = getToken();
      const res = await fetch('api/notificacoes.php?action=my_notifications', {
        credentials: 'same-origin',
        headers: token ? { 'X-Session-Token': token } : {}
      });

      if (!res.ok) {
        if (res.status === 401) {
          window.location.href = 'login.php';
          return;
        }
        throw new Error(`Erro HTTP ${res.status}`);
      }

      const data = await res.json();
      if (data && data.success) {
        allNotifications = (data.rows || []).map(r => ({
          id: parseInt(r.id, 10),
          user_id: parseInt(r.user_id, 10),
          sender_id: r.sender_id ? parseInt(r.sender_id, 10) : null,
          sender_name: r.sender_name || 'Gestão Municipal',
          title: r.title || 'Notificação',
          body: r.body || r.message || '',
          type: (r.type || 'info').toLowerCase(),
          is_read: Boolean(Number(r.is_read)),
          link: r.link || null,
          created_at: r.created_at
        }));

        updateKpisAndBadges();
        renderFeed();
      } else {
        throw new Error(data.error || 'Falha ao processar notificações.');
      }
    } catch (err) {
      console.warn('[central-notificacoes] Erro ao carregar da API:', err);
      // Fallback para PLDB se disponível
      if (window.PLDB && window.PLDB.notificacoes) {
        const u = window.PasseLivreAuth?.getUser?.();
        if (u && u.id) {
          const rows = window.PLDB.notificacoes.findByUser(u.id);
          allNotifications = (rows || []).map(r => ({
            id: parseInt(r.id, 10),
            user_id: parseInt(r.user_id, 10),
            sender_id: r.sender_id ? parseInt(r.sender_id, 10) : null,
            sender_name: r.sender_name || 'Gestão Municipal',
            title: r.title || 'Notificação',
            body: r.body || r.message || '',
            type: (r.type || 'info').toLowerCase(),
            is_read: Boolean(Number(r.is_read)),
            link: r.link || null,
            created_at: r.created_at
          }));
          updateKpisAndBadges();
          renderFeed();
        }
      } else {
        feedContainer.innerHTML = `
          <div class="central-empty-state">
            <div class="empty-icon-wrap" style="color:#dc2626;background:#fef2f2;">⚠️</div>
            <div class="empty-title">Não foi possível carregar as notificações</div>
            <div class="empty-desc">${escapeHtml(err.message || 'Verifique sua conexão ou tente novamente.')}</div>
            <button type="button" class="btn-empty-reset" id="btn-retry">Tentar novamente</button>
          </div>
        `;
        document.getElementById('btn-retry')?.addEventListener('click', loadNotifications);
      }
    } finally {
      isLoading = false;
      feedContainer.setAttribute('aria-busy', 'false');
    }
  }

  // -------------------------------------------------------------------------
  // 3. ATUALIZAÇÃO DE KPIS E CONTADORES
  // -------------------------------------------------------------------------
  function updateKpisAndBadges() {
    const total  = allNotifications.length;
    const unread = allNotifications.filter(n => !n.is_read).length;

    if (kpiTotal)     kpiTotal.textContent     = String(total);
    if (kpiUnread)    kpiUnread.textContent    = String(unread);
    if (chipTodas)    chipTodas.textContent    = String(total);
    if (chipNaoLidas) chipNaoLidas.textContent = String(unread);

    // Sincroniza badges globais do header
    window.PasseLivreAuth?.updateNotifBadge?.();
    window.PasseLivreNotif?.atualizarBadge?.();
  }

  // -------------------------------------------------------------------------
  // 4. RENDERIZAÇÃO DO FEED
  // -------------------------------------------------------------------------
  function renderFeed() {
    // Filtro por tipo/leitura
    let filtered = allNotifications;

    if (currentFilter === 'nao-lidas') {
      filtered = filtered.filter(n => !n.is_read);
    } else if (currentFilter === 'alerta') {
      filtered = filtered.filter(n => n.type === 'alerta' || n.type === 'alert' || n.type === 'warning');
    } else if (currentFilter !== 'todas') {
      filtered = filtered.filter(n => n.type === currentFilter);
    }

    // Busca textual
    if (currentSearch.trim()) {
      const q = currentSearch.toLowerCase().trim();
      filtered = filtered.filter(n =>
        n.title.toLowerCase().includes(q) ||
        n.body.toLowerCase().includes(q) ||
        n.sender_name.toLowerCase().includes(q)
      );
    }

    // Estado vazio
    if (filtered.length === 0) {
      const isFiltered = currentFilter !== 'todas' || currentSearch.trim() !== '';
      feedContainer.innerHTML = `
        <div class="central-empty-state" role="status">
          <div class="empty-icon-wrap">
            <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
              <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
              <line x1="2" y1="2" x2="22" y2="22"/>
            </svg>
          </div>
          <h2 class="empty-title">Nenhuma notificação encontrada</h2>
          <p class="empty-desc">
            ${isFiltered
              ? 'Nenhum comunicado corresponde aos filtros selecionados. Tente redefinir sua busca.'
              : 'Você não possui notificações pendentes no momento. Quando o gestor ou o sistema enviar avisos, eles aparecerão aqui.'}
          </p>
          ${isFiltered ? `<button type="button" class="btn-empty-reset" id="btn-clear-filters">Limpar filtros e exibir todas</button>` : ''}
        </div>
      `;

      document.getElementById('btn-clear-filters')?.addEventListener('click', () => {
        currentFilter = 'todas';
        currentSearch = '';
        if (searchInput) searchInput.value = '';
        filterChips.forEach(c => c.classList.toggle('active', c.getAttribute('data-filter') === 'todas'));
        renderFeed();
      });
      return;
    }

    // Renderiza cards ordenados (mais recentes primeiro)
    feedContainer.innerHTML = filtered.map(n => {
      const isUnread     = !n.is_read;
      const typeKey      = n.type || 'info';
      const iconSvg      = TYPE_ICONS[typeKey] || TYPE_ICONS.info;
      const typeLabel    = TYPE_LABELS[typeKey] || 'Informação';
      const formattedDate = formatDateTime(n.created_at);
      const relativeAgo  = formatRelativeAgo(n.created_at);

      return `
        <article 
          class="notif-card tipo-${escapeHtml(typeKey)} ${isUnread ? 'unread' : 'read'}" 
          id="notif-card-${n.id}"
          tabindex="0"
          aria-label="${isUnread ? 'Nova notificação: ' : ''}${escapeHtml(n.title)}"
        >
          <div class="notif-card-icon" aria-hidden="true">
            ${iconSvg}
          </div>

          <div class="notif-card-body">
            <div class="notif-card-header">
              <div class="notif-header-tags">
                <span class="notif-pill-tag">${escapeHtml(typeLabel)}</span>
                ${isUnread ? '<span class="notif-unread-dot" title="Não lida"></span>' : ''}
              </div>
              <time class="notif-card-time" datetime="${escapeHtml(n.created_at || '')}" title="${escapeHtml(formattedDate)}">
                ${escapeHtml(relativeAgo || formattedDate)}
              </time>
            </div>

            <h3 class="notif-card-title">${escapeHtml(n.title)}</h3>
            <div class="notif-card-text">${escapeHtml(n.body)}</div>

            <div class="notif-card-footer">
              <div class="notif-sender-info">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                  <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                  <circle cx="12" cy="7" r="4"/>
                </svg>
                <span>Enviado por: <strong>${escapeHtml(n.sender_name)}</strong> &bull; ${escapeHtml(formattedDate)}</span>
              </div>

              <div class="notif-card-actions">
                ${isUnread ? `
                  <button type="button" class="btn-card-action btn-card-action-primary" onclick="window.CentralNotif.marcarLida(${n.id})">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                      <polyline points="20 6 9 17 4 12"/>
                    </svg>
                    <span>Marcar como lida</span>
                  </button>
                ` : `
                  <span style="font-size:12px;color:#16a34a;display:inline-flex;align-items:center;gap:4px;font-weight:600;padding:4px 8px;">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                      <polyline points="20 6 9 17 4 12"/>
                    </svg>
                    Lida
                  </span>
                `}

                <button type="button" class="btn-card-action" onclick="window.CentralNotif.abrirDetalhes(${n.id})">
                  <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                    <circle cx="12" cy="12" r="3"/>
                  </svg>
                  <span>Abrir</span>
                </button>

                ${n.link ? `
                  <a href="${escapeHtml(n.link)}" class="btn-card-action" style="color:var(--brand-orange);" onclick="window.CentralNotif.marcarLida(${n.id})">
                    <span>Acessar</span>
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                      <polyline points="9 18 15 12 9 6"/>
                    </svg>
                  </a>
                ` : ''}

                <button type="button" class="btn-card-action btn-card-action-delete" title="Excluir notificação" onclick="window.CentralNotif.excluir(${n.id})">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <polyline points="3 6 5 6 21 6"/>
                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                  </svg>
                </button>
              </div>
            </div>
          </div>
        </article>
      `;
    }).join('');
  }

  // -------------------------------------------------------------------------
  // 5. AÇÕES DE NOTIFICAÇÃO (LIDA, TODAS LIDAS, ABRIR, EXCLUIR)
  // -------------------------------------------------------------------------

  async function marcarLida(id) {
    const notif = allNotifications.find(n => n.id === id);
    if (!notif) return;

    notif.is_read = true;
    updateKpisAndBadges();
    renderFeed();

    try {
      const token = getToken();
      await fetch(`api/notificacoes.php?action=mark_read&id=${encodeURIComponent(id)}`, {
        method: 'POST',
        credentials: 'same-origin',
        headers: token ? { 'X-Session-Token': token } : {}
      });
      showToast('Notificação marcada como lida.');
    } catch (e) {
      console.warn('[central-notificacoes] Falha ao marcar lida:', e);
    }
  }

  async function marcarTodasComoLidas() {
    const unreadCount = allNotifications.filter(n => !n.is_read).length;
    if (unreadCount === 0) {
      showToast('Todas as suas notificações já estão lidas!');
      return;
    }

    allNotifications.forEach(n => { n.is_read = true; });
    updateKpisAndBadges();
    renderFeed();

    try {
      const token = getToken();
      await fetch('api/notificacoes.php?action=mark_all_read', {
        method: 'POST',
        credentials: 'same-origin',
        headers: token ? { 'X-Session-Token': token } : {}
      });
      showToast('Todas as notificações foram marcadas como lidas.');
    } catch (e) {
      console.warn('[central-notificacoes] Falha ao marcar todas como lidas:', e);
    }
  }

  async function abrirDetalhes(id) {
    const notif = allNotifications.find(n => n.id === id);
    if (!notif) return;

    // Se estiver não lida, marca no MySQL e atualiza
    if (!notif.is_read) {
      marcarLida(id);
    }

    // Preenche o Modal
    const typeLabel = TYPE_LABELS[notif.type] || 'Comunicado';
    modalType.textContent = typeLabel;
    modalTitle.textContent = notif.title;
    modalSender.textContent = notif.sender_name || 'Gestão Municipal';
    modalDate.textContent = formatDateTime(notif.created_at);
    modalStatus.textContent = 'Lida';
    modalContent.textContent = notif.body;

    if (notif.link) {
      modalLinkBtn.href = notif.link;
      modalLinkBtn.style.display = 'inline-flex';
    } else {
      modalLinkBtn.style.display = 'none';
    }

    modalOverlay.classList.add('open');
    modalOverlay.setAttribute('aria-hidden', 'false');
    modalDismissBtn?.focus();
  }

  function fecharModal() {
    modalOverlay.classList.remove('open');
    modalOverlay.setAttribute('aria-hidden', 'true');
  }

  async function excluirNotificacao(id) {
    if (!confirm('Deseja realmente remover esta notificação da sua central?')) {
      return;
    }

    allNotifications = allNotifications.filter(n => n.id !== id);
    updateKpisAndBadges();
    renderFeed();

    try {
      const token = getToken();
      await fetch(`api/notificacoes.php?action=delete&id=${encodeURIComponent(id)}`, {
        method: 'POST',
        credentials: 'same-origin',
        headers: token ? { 'X-Session-Token': token } : {}
      });
      showToast('Notificação excluída com sucesso.');
    } catch (e) {
      console.warn('[central-notificacoes] Erro ao excluir:', e);
    }
  }

  // -------------------------------------------------------------------------
  // 6. EVENTOS DE INTERFACE E FILTROS
  // -------------------------------------------------------------------------

  // Chips de filtro
  filterChips.forEach(chip => {
    chip.addEventListener('click', () => {
      filterChips.forEach(c => c.classList.remove('active'));
      chip.classList.add('active');
      currentFilter = chip.getAttribute('data-filter') || 'todas';
      renderFeed();
    });
  });

  // Busca em tempo real
  if (searchInput) {
    searchInput.addEventListener('input', (e) => {
      currentSearch = e.target.value;
      renderFeed();
    });
  }

  // Marcar todas como lidas
  btnMarkAllRead?.addEventListener('click', marcarTodasComoLidas);

  // Botão de atualizar lista
  btnRefresh?.addEventListener('click', async () => {
    showToast('Atualizando notificações do MySQL...');
    await loadNotifications();
  });

  // Fechar modal
  modalCloseBtn?.addEventListener('click', fecharModal);
  modalDismissBtn?.addEventListener('click', fecharModal);
  modalOverlay?.addEventListener('click', (e) => {
    if (e.target === modalOverlay) fecharModal();
  });
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && modalOverlay.classList.contains('open')) {
      fecharModal();
    }
  });

  // Exposição global para chamadas inline seguras
  window.CentralNotif = {
    marcarLida,
    marcarTodasComoLidas,
    abrirDetalhes,
    excluir: excluirNotificacao,
    recarregar: loadNotifications
  };

  // Carregamento inicial do MySQL
  await loadNotifications();
});
