/**
 * Passe Livre Suzano — Painel de Notificações do Cidadão (Drawer Lateral)
 * notificacoes-cidadao.js
 *
 * Integração completa com o banco de dados MySQL via api/notificacoes.php.
 * Injeta dinamicamente o drawer de notificações em qualquer tela do cidadão.
 * Carregado automaticamente pelo auth.js em todas as telas não-admin.
 */

(function () {
  'use strict';

  // Estado local em memória (alimentado pelo MySQL)
  let notificacoesList = [];
  let filtroAtivo = 'todas';
  let isFetching = false;

  // Helper de cálculo de tempo relativo
  function calcMinutesAgo(dateStr) {
    if (!dateStr) return 999999;
    const d = new Date(dateStr.replace(' ', 'T'));
    if (isNaN(d.getTime())) return 999999;
    return Math.floor((Date.now() - d.getTime()) / 60000);
  }

  function formatRelativeTime(dateStr) {
    if (!dateStr) return 'Recente';
    const mins = calcMinutesAgo(dateStr);
    if (mins < 1) return 'Agora';
    if (mins < 60) return `Há ${mins} min`;
    const hours = Math.floor(mins / 60);
    if (hours < 24) return `Há ${hours}h`;
    const days = Math.floor(hours / 24);
    if (days === 1) return 'Ontem';
    if (days < 7) return `Há ${days} dias`;
    try {
      const d = new Date(dateStr.replace(' ', 'T'));
      return d.toLocaleDateString('pt-BR');
    } catch (_) {
      return dateStr.slice(0, 10);
    }
  }

  function getToken() {
    return localStorage.getItem('passeLivre_token') || localStorage.getItem('passeLivreToken') || '';
  }

  // A sessão também pode existir apenas no cookie httpOnly (login/restauração server-side)
  function authHeaders() {
    const token = getToken();
    return token ? { 'X-Session-Token': token } : {};
  }

  // -------------------------------------------------------------------------
  // BUSCA DE NOTIFICAÇÕES NO MYSQL
  // -------------------------------------------------------------------------
  async function carregarDoServidor() {
    if (isFetching) return notificacoesList;
    isFetching = true;

    try {
      const res = await fetch('api/notificacoes.php?action=my_notifications', {
        credentials: 'same-origin',
        headers: authHeaders()
      });

      if (res.ok) {
        const data = await res.json();
        if (data && data.success && Array.isArray(data.rows)) {
          notificacoesList = data.rows.map(n => ({
            id: n.id,
            tipo: (n.type || 'info').toLowerCase(),
            titulo: n.title || 'Notificação',
            corpo: n.body || n.message || '',
            tempo: formatRelativeTime(n.created_at),
            timestampRelativo: calcMinutesAgo(n.created_at),
            lida: Boolean(Number(n.is_read)),
            link: n.link || null,
            created_at: n.created_at,
            sender_name: n.sender_name || 'Gestão Municipal'
          }));

          atualizarBadge();
          renderLista();
          isFetching = false;
          return notificacoesList;
        }
      }
    } catch (err) {
      console.warn('[notificacoes-cidadao] Falha ao consultar API, tentando PLDB local:', err);
    }

    // Fallback: PLDB direto se disponível
    if (window.PLDB && window.PLDB.notificacoes) {
      try {
        const user = window.PasseLivreAuth?.getUser?.();
        if (user && user.id) {
          const rows = window.PLDB.notificacoes.findByUser(user.id);
          if (Array.isArray(rows) && rows.length > 0) {
            notificacoesList = rows.map(n => ({
              id: n.id,
              tipo: (n.type || 'info').toLowerCase(),
              titulo: n.title || 'Notificação',
              corpo: n.body || n.message || '',
              tempo: formatRelativeTime(n.created_at),
              timestampRelativo: calcMinutesAgo(n.created_at),
              lida: Boolean(Number(n.is_read)),
              link: n.link || null,
              created_at: n.created_at,
              sender_name: n.sender_name || 'Gestão Municipal'
            }));
            atualizarBadge();
            renderLista();
          }
        }
      } catch (e) {
        console.warn('[notificacoes-cidadao] Fallback PLDB:', e);
      }
    }

    isFetching = false;
    return notificacoesList;
  }

  function getNotificacoes() {
    return notificacoesList;
  }

  function contarNaoLidas() {
    return notificacoesList.filter(n => !n.lida).length;
  }

  // -------------------------------------------------------------------------
  // MARCAÇÕES COMO LIDA NO MYSQL
  // -------------------------------------------------------------------------
  async function marcarLida(id) {
    const item = notificacoesList.find(n => String(n.id) === String(id));
    if (item) {
      item.lida = true;
    }
    atualizarBadge();
    renderLista();

    try {
      await fetch(`api/notificacoes.php?action=mark_read&id=${encodeURIComponent(id)}`, {
        method: 'POST',
        credentials: 'same-origin',
        headers: authHeaders()
      });
      if (window.PLDB && window.PLDB.notificacoes) {
        const user = window.PasseLivreAuth?.getUser?.();
        window.PLDB.notificacoes.markRead(id, user ? user.id : null);
      }
    } catch (e) {
      console.warn('[notificacoes-cidadao] Erro ao marcar lida:', e);
    }
  }

  async function marcarTodasLidas() {
    notificacoesList.forEach(n => { n.lida = true; });
    atualizarBadge();
    renderLista();

    try {
      await fetch('api/notificacoes.php?action=mark_all_read', {
        method: 'POST',
        credentials: 'same-origin',
        headers: authHeaders()
      });
      if (window.PLDB && window.PLDB.notificacoes) {
        const user = window.PasseLivreAuth?.getUser?.();
        if (user && user.id) {
          window.PLDB.notificacoes.markAllRead(user.id);
        }
      }
    } catch (e) {
      console.warn('[notificacoes-cidadao] Erro ao marcar todas como lidas:', e);
    }
  }

  // -------------------------------------------------------------------------
  // ÍCONES SVG POR TIPO
  // -------------------------------------------------------------------------
  const ICONES = {
    rota: `<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="6" cy="19" r="3"/><path d="M9 19h8.5a3.5 3.5 0 0 0 0-7h-11a3.5 3.5 0 0 1 0-7H15"/><circle cx="18" cy="5" r="3"/></svg>`,
    alerta: `<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>`,
    alert: `<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>`,
    warning: `<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>`,
    info: `<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>`,
    sistema: `<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>`,
    gestor: `<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12 19.79 19.79 0 0 1 1.61 3.4 2 2 0 0 1 3.6 1.22h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 8.8a16 16 0 0 0 6 6l.86-.86a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 21.59 16"/></svg>`,
    conquista: `<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>`
  };

  const TIPO_LABELS = {
    rota: 'Rota',
    alerta: 'Alerta',
    alert: 'Alerta',
    warning: 'Aviso',
    info: 'Informação',
    sistema: 'Sistema',
    gestor: 'Gestor',
    conquista: 'Conquista'
  };

  // -------------------------------------------------------------------------
  // CONSTRUÇÃO DO DRAWER
  // -------------------------------------------------------------------------
  function criarDrawer() {
    if (document.getElementById('notif-drawer')) return;

    // Injeta CSS se necessário
    if (!document.querySelector('link[href*="notificacoes-cidadao.css"]')) {
      const link = document.createElement('link');
      link.rel  = 'stylesheet';
      link.href = 'css/notificacoes-cidadao.css';
      document.head.appendChild(link);
    }

    const backdrop = document.createElement('div');
    backdrop.id        = 'notif-backdrop';
    backdrop.className = 'notif-backdrop';
    backdrop.setAttribute('aria-hidden', 'true');

    const drawer = document.createElement('div');
    drawer.id        = 'notif-drawer';
    drawer.className = 'notif-drawer';
    drawer.setAttribute('role', 'dialog');
    drawer.setAttribute('aria-modal', 'true');
    drawer.setAttribute('aria-label', 'Notificações');

    drawer.innerHTML = `
      <div class="notif-drawer-header">
        <div class="notif-drawer-title">
          <div class="notif-title-icon">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
              <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
            </svg>
          </div>
          <h2>Notificações <span class="notif-unread-badge" id="notif-unread-count" style="display:none;">0</span></h2>
        </div>
        <div class="notif-header-actions">
          <button type="button" class="btn-notif-mark-all" id="btn-mark-all-read">Marcar todas como lidas</button>
          <button type="button" class="btn-notif-close" id="btn-close-notif" aria-label="Fechar notificações">&times;</button>
        </div>
      </div>

      <div class="notif-filter-row" role="toolbar" aria-label="Filtrar notificações">
        <button type="button" class="notif-filter-btn active" data-filter="todas">Todas</button>
        <button type="button" class="notif-filter-btn" data-filter="nao-lidas">Não lidas</button>
        <button type="button" class="notif-filter-btn" data-filter="alerta">⚠ Alertas</button>
        <button type="button" class="notif-filter-btn" data-filter="rota">🗺 Rotas</button>
        <button type="button" class="notif-filter-btn" data-filter="gestor">📋 Gestor</button>
        <button type="button" class="notif-filter-btn" data-filter="info">ℹ Info</button>
      </div>

      <div class="notif-list" id="notif-list" role="feed" aria-label="Lista de notificações">
        <div style="padding:24px;text-align:center;color:#78716c;font-size:13px;">Carregando notificações...</div>
      </div>

      <div class="notif-drawer-footer">
        <a href="central-notificacoes.php" id="link-central-notificacoes">
          Ver central de notificações
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <polyline points="9 18 15 12 9 6"/>
          </svg>
        </a>
      </div>
    `;

    document.body.appendChild(backdrop);
    document.body.appendChild(drawer);

    // Eventos de filtro
    drawer.querySelectorAll('.notif-filter-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        drawer.querySelectorAll('.notif-filter-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        filtroAtivo = btn.getAttribute('data-filter');
        renderLista();
      });
    });

    // Marcar todas como lidas
    document.getElementById('btn-mark-all-read')?.addEventListener('click', async () => {
      await marcarTodasLidas();
      window.PasseLivreAuth?.showToast?.('Todas as notificações foram marcadas como lidas.');
    });

    // Fechar
    function fecharDrawer() {
      drawer.classList.remove('open');
      backdrop.classList.remove('open');
      document.body.style.overflow = '';
      document.getElementById('btn-notifications')?.focus();
    }

    document.getElementById('btn-close-notif')?.addEventListener('click', fecharDrawer);
    backdrop.addEventListener('click', fecharDrawer);
    document.addEventListener('keydown', e => {
      if (e.key === 'Escape' && drawer.classList.contains('open')) fecharDrawer();
    });

    // Abrir drawer
    window._notifDrawerAbrir = async function () {
      drawer.classList.add('open');
      backdrop.classList.add('open');
      document.body.style.overflow = 'hidden';
      document.getElementById('btn-close-notif')?.focus();
      await carregarDoServidor();
    };
  }

  // -------------------------------------------------------------------------
  // RENDERIZAÇÃO DA LISTA NO DRAWER
  // -------------------------------------------------------------------------
  function renderLista() {
    const lista = document.getElementById('notif-list');
    if (!lista) return;

    const todas = notificacoesList;
    const filtradas = filtroAtivo === 'todas'     ? todas
                    : filtroAtivo === 'nao-lidas' ? todas.filter(n => !n.lida)
                    : filtroAtivo === 'alerta'    ? todas.filter(n => n.tipo === 'alerta' || n.tipo === 'alert' || n.tipo === 'warning')
                    : todas.filter(n => n.tipo === filtroAtivo);

    // Atualiza badge de não lidas do drawer
    const naoLidas = todas.filter(n => !n.lida).length;
    const badge = document.getElementById('notif-unread-count');
    if (badge) {
      badge.textContent = naoLidas > 99 ? '99+' : String(naoLidas);
      badge.style.display = naoLidas > 0 ? '' : 'none';
    }

    if (filtradas.length === 0) {
      lista.innerHTML = `
        <div class="notif-empty">
          <svg width="52" height="52" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
            <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
            <line x1="2" y1="2" x2="22" y2="22"/>
          </svg>
          <h3>Nenhuma notificação</h3>
          <p>${filtroAtivo === 'nao-lidas' ? 'Você está em dia! Todas as notificações foram lidas.' : 'Sem notificações nesta categoria por enquanto.'}</p>
        </div>`;
      return;
    }

    // Agrupa por tempo
    const hoje       = filtradas.filter(n => n.timestampRelativo <= 480 || n.tempo === 'Agora');
    const ontem      = filtradas.filter(n => n.timestampRelativo > 480 && n.timestampRelativo <= 1440 && n.tempo !== 'Agora');
    const anteriores = filtradas.filter(n => n.timestampRelativo > 1440);

    function renderGrupo(titulo, grupo) {
      if (grupo.length === 0) return '';
      return `
        <div class="notif-date-separator">${titulo}</div>
        ${grupo.map(n => `
          <div
            class="notif-item${n.lida ? '' : ' unread'}"
            role="article"
            tabindex="0"
            data-id="${n.id}"
            aria-label="${n.lida ? '' : 'Nova: '}${n.titulo}"
            onclick="window.PasseLivreNotifUI.clicarItem(${n.id}, ${n.link ? `'${n.link}'` : 'null'})"
            onkeydown="if(event.key==='Enter'||event.key===' ')this.click()"
          >
            <div class="notif-icon-wrap tipo-${n.tipo}">
              ${ICONES[n.tipo] || ICONES.info}
            </div>
            <div class="notif-content">
              <div class="notif-item-title">${n.titulo}</div>
              <div class="notif-item-body">${n.corpo}</div>
              <div class="notif-item-meta">
                <span class="notif-item-time">${n.tempo}</span>
                <span class="notif-item-tag tipo-${n.tipo}">${TIPO_LABELS[n.tipo] || 'Info'}</span>
              </div>
            </div>
          </div>
        `).join('')}
      `;
    }

    lista.innerHTML =
      renderGrupo('Hoje', hoje) +
      renderGrupo('Ontem', ontem) +
      renderGrupo('Anteriores', anteriores);
  }

  // Clicar em item do drawer
  window.PasseLivreNotifUI = {
    async clicarItem(id, link) {
      await marcarLida(id);
      if (link) {
        const drawer = document.getElementById('notif-drawer');
        const backdrop = document.getElementById('notif-backdrop');
        if (drawer) drawer.classList.remove('open');
        if (backdrop) backdrop.classList.remove('open');
        document.body.style.overflow = '';
        setTimeout(() => { window.location.href = link; }, 150);
      }
    }
  };

  // -------------------------------------------------------------------------
  // BADGES NOS CABEÇALHOS
  // -------------------------------------------------------------------------
  function atualizarBadge() {
    const count = contarNaoLidas();

    // Botão de notificação no header (#btn-notifications)
    const btnNotif = document.getElementById('btn-notifications');
    if (btnNotif) {
      btnNotif.querySelector('.notify-count-badge')?.remove();
      btnNotif.querySelector('.notify-badge-dot')?.remove();

      if (count > 0) {
        const badge = document.createElement('span');
        badge.className   = 'notify-count-badge';
        badge.textContent = count > 99 ? '99+' : String(count);
        badge.setAttribute('aria-label', `${count} notificações não lidas`);
        btnNotif.appendChild(badge);
      }
    }

    // Outros elementos com classe de badge
    document.querySelectorAll('.notif-badge-count, .notif-count-bubble, .menu-dot-indicator').forEach(el => {
      if (el.classList.contains('menu-dot-indicator')) {
        el.style.display = count > 0 ? '' : 'none';
      } else {
        el.textContent = count > 0 ? (count > 99 ? '99+' : String(count)) : '';
        el.style.display = count > 0 ? '' : 'none';
      }
    });

    const drawerCount = document.getElementById('notif-unread-count');
    if (drawerCount) {
      drawerCount.textContent = count > 99 ? '99+' : String(count);
      drawerCount.style.display = count > 0 ? '' : 'none';
    }
  }

  // -------------------------------------------------------------------------
  // INICIALIZAÇÃO
  // -------------------------------------------------------------------------
  function init() {
    const btnNotif = document.getElementById('btn-notifications');
    if (btnNotif) {
      btnNotif.addEventListener('click', e => {
        e.preventDefault();
        if (!document.getElementById('notif-drawer')) criarDrawer();
        window._notifDrawerAbrir?.();
      });
    }

    // Carrega dados iniciais do MySQL
    carregarDoServidor();
  }

  // Exposição global
  window.PasseLivreNotif = {
    carregar: getNotificacoes,
    carregarDoServidor,
    contarNaoLidas,
    marcarLida,
    marcarTodasLidas,
    atualizarBadge
  };

  window.PasseLivreNotifInit = init;

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

})();
