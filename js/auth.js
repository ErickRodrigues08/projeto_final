/**
 * Passe Livre Suzano - Gerenciamento de Sessao, Seguranca RBAC e Header
 * Validacao REAL de autorizacao no Servidor PHP / MySQL 8.0+.
 */
(function () {
  'use strict';

  const STORAGE_KEY = 'passeLivreUser';
  const TOKEN_KEY   = 'passeLivre_token';

  function esc(str) {
    if (typeof str !== 'string') return '';
    return str
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#x27;');
  }

  function _loadScript(src) {
    if (document.querySelector('script[src="' + src + '"]')) return;
    const s  = document.createElement('script');
    s.src    = src;
    s.defer  = true;
    document.body.appendChild(s);
  }

  // -------------------------------------------------------------------------
  // AVATAR COM INICIAL — fallback deterministico para usuarios sem foto
  // Paleta de 12 cores deterministica (mesma cor para o mesmo nome sempre)
  // -------------------------------------------------------------------------
  const _PL_AVATAR_COLORS = [
    '#f59e8b','#6ee7b7','#93c5fd','#c4b5fd',
    '#f9a8d4','#67e8f9','#bbf7d0','#fca5a5',
    '#d8b4fe','#7dd3fc','#fdba74','#6ee7b7'
  ];
  // Nota: pasteis acima exigem texto escuro — ver .pl-avatar-initial no CSS

  function _plAvatarColor(name) {
    const s = (name || 'U').trim();
    let h = 0;
    for (let i = 0; i < s.length; i++) {
      h = (h * 31 + s.charCodeAt(i)) >>> 0;
    }
    return _PL_AVATAR_COLORS[h % _PL_AVATAR_COLORS.length];
  }

  function _plAvatarInitial(name) {
    const s = (name || 'U').trim();
    return String.fromCodePoint(s.codePointAt(0)).toUpperCase();
  }

  // Foto padrão de quem não enviou foto própria. É uma imagem real e deve
  // aparecer como imagem; o círculo com inicial só sobra se até este arquivo
  // falhar ao carregar (fallback de onerror).
  //
  // `?v=` é a mesma chave usada por pl_avatar_exibicao() no PHP: a foto padrão
  // tem URL fixa, então sem versão o navegador segue exibindo a cópia antiga que
  // está no cache dele mesmo depois de o arquivo ter sido trocado no servidor.
  const _PL_AVATAR_PADRAO_SEM_VERSAO = 'assets/images/avatar.jpg';
  const _PL_AVATAR_PADRAO = _PL_AVATAR_PADRAO_SEM_VERSAO + '?v=20261029';

  // Um valor só é exibido se for caminho local do projeto ou URL absoluta (Google).
  // `data:`, valores truncados e lixo viram a foto padrão em vez de imagem quebrada.
  function _plAvatarSrc(avatarSrc) {
    const s = (avatarSrc === null || avatarSrc === undefined) ? '' : String(avatarSrc).trim();
    if (s === '' || s === 'null' || s === 'undefined') return _PL_AVATAR_PADRAO;
    if (/^(?:api\/avatar\.php\?ref=[1-9]\d*-[a-f0-9]{64}|uploads\/[\w.|-]+|assets\/[\w./-]+(?:\?v=\d+)?|https?:\/\/\S+)$/.test(s)) return s;
    return _PL_AVATAR_PADRAO;
  }

  // Queda em dois tempos: foto própria → foto padrão → inicial com cor pastel.
  function _plAvatarFallback(el, name) {
    if (!el) return;
    const atual = el.getAttribute('src') || '';
    if (atual.indexOf(_PL_AVATAR_PADRAO) === -1) {
      el.onerror = function () { _plAvatarFallback(el, name); };
      el.src = _PL_AVATAR_PADRAO;
      return;
    }
    _plReplaceImgWithInitial(el, name || 'U');
  }
  window._plAvatarFallback = _plAvatarFallback;

  // Substitui <img> por <span> com inicial colorida, copiando classes, tamanho e ID
  function _plReplaceImgWithInitial(imgEl, name) {
    if (!imgEl || !imgEl.parentNode) return;
    var cs = window.getComputedStyle ? window.getComputedStyle(imgEl) : null;
    var w  = parseInt(imgEl.getAttribute('width') || 0, 10)
          || imgEl.offsetWidth
          || (cs ? parseInt(cs.width, 10) : 0)
          || 36;
    var h  = parseInt(imgEl.getAttribute('height') || 0, 10)
          || imgEl.offsetHeight
          || (cs ? parseInt(cs.height, 10) : 0)
          || 36;
    if (w < 8) w = 36;
    if (h < 8) h = 36;
    const fs  = Math.round(Math.min(w, h) * 0.44);
    const cor = _plAvatarColor(name);
    const ini = _plAvatarInitial(name);
    const span = document.createElement('span');
    span.className = imgEl.className;
    span.classList.add('pl-avatar-initial');
    if (imgEl.id) span.id = imgEl.id;
    span.setAttribute('aria-label', 'Avatar de ' + name);
    span.setAttribute('role', 'img');
    span.style.cssText = 'width:' + w + 'px;height:' + h + 'px;font-size:' + fs + 'px;background:' + cor + ';display:inline-flex;align-items:center;justify-content:center;border-radius:50%;';
    span.textContent = ini;
    imgEl.parentNode.replaceChild(span, imgEl);
  }

  // Aplica avatar correto a um elemento <img>
  function _plApplyAvatar(imgEl, user) {
    if (!imgEl) return;
    const name = (user && user.name) ? user.name : 'U';
    imgEl.src     = _plAvatarSrc(user && user.avatar);
    imgEl.alt     = 'Foto de perfil de ' + name;
    imgEl.onerror = function () { _plAvatarFallback(this, name); };
  }

  // Retorna HTML string (img ou span) para uso em innerHTML
  // extraClass permite reusar o mesmo avatar em outros tamanhos/estilos da página.
  function _plAvatarHtml(user, size, extraClass) {
    size  = size || 36;
    const extra = extraClass ? ' ' + extraClass : '';
    const sn    = esc((user && user.name) ? user.name : 'U');
    const sa    = esc(_plAvatarSrc(user && user.avatar));
    return '<img src="' + sa + '" alt="Foto de perfil de ' + sn + '" class="user-avatar-img' + extra + '"' +
           ' style="width:' + size + 'px;height:' + size + 'px;border-radius:50%;object-fit:cover;"' +
           ' onerror="if(window._plAvatarFallback)window._plAvatarFallback(this,\'' + sn + '\')">';
  }

  // Atualiza todos os avatares IMG da pagina
  function _plSyncAllAvatars(user) {
    if (!user) return;
    document.querySelectorAll('img.user-avatar-img').forEach(function (el) {
      _plApplyAvatar(el, user);
    });
  }

  // O navegador pode reter a última resposta dessa URL nesta sessão; `?v=` força a
  // releitura. Vale só para uploads/ e só para o DOM: o caminho gravado no banco
  // permanece sem query string.
  function _plBustCache(imgEl, versao) {
    const s = imgEl.getAttribute('src') || '';
    if (/^uploads\/[\w.|-]+$/i.test(s) && s.indexOf('?') === -1) {
      imgEl.setAttribute('src', s + '?v=' + versao);
    }
  }

  // Aplica a foto em todos os avatares já carregados na página (navbar, avatar
  // grande, dropdown). Elementos que hoje mostram o círculo com inicial voltam a
  // ser <img>, preservando id, classes e tamanho.
  function _plSetPageAvatar(user, versao) {
    if (!user) return;
    document.querySelectorAll('img.user-avatar-img, img.profile-large-avatar, .pl-avatar-initial')
      .forEach(function (el) {
        if (el.tagName === 'IMG') {
          _plApplyAvatar(el, user);
          if (versao) _plBustCache(el, versao);
          return;
        }
        const size  = parseInt(el.style.width, 10) || el.offsetWidth || 36;
        const extra = String(el.className || '').replace(/\b(pl-avatar-initial|user-avatar-img)\b/g, '').trim();
        const tmp   = document.createElement('div');
        tmp.innerHTML = _plAvatarHtml(user, size, extra);
        const novo = tmp.firstElementChild;
        if (!novo || !el.parentNode) return;
        if (el.id) novo.id = el.id;
        el.parentNode.replaceChild(novo, el);
        if (versao) _plBustCache(novo, versao);
      });
  }

  // Expoe para uso em onerror de elementos gerados por innerHTML
  window._plReplaceImgWithInitial = _plReplaceImgWithInitial;

  // -------------------------------------------------------------------------
  // 1. API DE SESSAO LOCAL + SERVIDOR
  // -------------------------------------------------------------------------
  function getUser() {
    try {
      const stored = localStorage.getItem(STORAGE_KEY);
      return stored ? JSON.parse(stored) : null;
    } catch (e) {
      return null;
    }
  }

  function setUser(user, token) {
    const safe = Object.assign({}, user);
    delete safe.password;
    delete safe.password_hash;
    try {
      localStorage.setItem(STORAGE_KEY, JSON.stringify(safe));
      if (token) localStorage.setItem(TOKEN_KEY, token);
    } catch (e) {
      console.warn('[PasseLivreAuth] Falha ao salvar sessao', e);
    }
    return safe;
  }

  function clearUser() {
    const token = localStorage.getItem(TOKEN_KEY) || localStorage.getItem('passeLivreToken') || '';
    const headers = token ? { 'X-Session-Token': token } : {};
    const logoutRequest = fetch('api/auth.php?action=logout', {
      method: 'POST',
      headers
    }).catch(error => {
      console.warn('[PasseLivreAuth] Falha ao encerrar sessao no servidor:', error);
    });
    try {
      localStorage.removeItem(STORAGE_KEY);
      localStorage.removeItem(TOKEN_KEY);
      localStorage.removeItem('passeLivreToken');
    } catch (_) {}
    return logoutRequest;
  }

  function isAdmin(user) {
    if (!user) return false;
    // Somente a flag `is_admin` vinda do servidor (usuarios.is_admin) concede acesso.
    // O texto de `role` e rotulagem institucional e nunca autoriza nada.
    return user.is_admin === 1 || user.is_admin === true;
  }

  function checkServerSession() {
    try {
      const xhr = new XMLHttpRequest();
      xhr.open('GET', 'api/auth.php?action=check', false);
      const token = localStorage.getItem(TOKEN_KEY);
      if (token) xhr.setRequestHeader('X-Session-Token', token);
      xhr.send();
      if (xhr.status === 200) {
        const resp = JSON.parse(xhr.responseText);
        if (resp.authenticated && resp.user) {
          setUser(resp.user, token);
          return resp;
        }
      }
    } catch (e) {
      console.warn('[PasseLivreAuth] Verificacao no servidor falhou:', e);
    }
    return { authenticated: false, user: null, is_admin: false };
  }

  // -------------------------------------------------------------------------
  // 2. PROTECAO DE ROTAS
  // -------------------------------------------------------------------------
  function requireAuth() {
    const localUser = getUser();
    if (!localUser) {
      window.location.replace('login.php?next=' + encodeURIComponent(window.location.pathname));
      return false;
    }
    const serverCheck = checkServerSession();
    if (!serverCheck.authenticated) {
      clearUser();
      window.location.replace('login.php?next=' + encodeURIComponent(window.location.pathname));
      return false;
    }
    return true;
  }

  function requireAdmin() {
    const localUser = getUser();
    if (!localUser) {
      window.location.replace('login.php?next=' + encodeURIComponent(window.location.pathname));
      return false;
    }
    const serverCheck = checkServerSession();
    if (!serverCheck.authenticated || !serverCheck.is_admin) {
      console.warn('[PasseLivreAuth] Tentativa de acesso sem privilegios de gestor bloqueada pelo servidor.');
      window.location.replace('adaptar-busca.php');
      return false;
    }
    return true;
  }

  // -------------------------------------------------------------------------
  // 3. TOAST GLOBAL
  // -------------------------------------------------------------------------
  let _toastTimer = null;

  function showToast(message, type) {
    let toast    = document.getElementById('app-toast');
    let toastMsg = document.getElementById('toast-message');

    if (!toast) {
      toast = document.createElement('div');
      toast.id        = 'app-toast';
      toast.className = 'toast-notice';
      toast.setAttribute('role', 'status');
      toast.setAttribute('aria-live', 'polite');
      toast.innerHTML = '<span class="toast-icon-wrap" aria-hidden="true"></span><span id="toast-message"></span>';
      document.body.appendChild(toast);
      toastMsg = document.getElementById('toast-message');
    }

    if (toastMsg) toastMsg.textContent = message;

    const iconWrap = toast.querySelector('.toast-icon-wrap');
    if (iconWrap) {
      if (type === 'error') {
        iconWrap.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>';
      } else if (type === 'warning') {
        iconWrap.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2.5"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>';
      } else {
        iconWrap.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>';
      }
    }

    toast.classList.add('visible');
    if (_toastTimer) clearTimeout(_toastTimer);
    _toastTimer = setTimeout(() => toast.classList.remove('visible'), 3600);
  }

  window.showToast = showToast;

  // -------------------------------------------------------------------------
  // 4. HEADER DA HOME (index.php)
  // -------------------------------------------------------------------------
  function renderHomeUser() {
    const authArea = document.getElementById('home-auth-area');
    if (!authArea) return;

    const check = checkServerSession();
    if (!check.authenticated || !check.user) return;
    const user = check.user;

    const safeName = esc(user.name || 'Usuario');

    const dashBtn = isAdmin(user)
      ? `<a href="dashboard.php" class="btn-home-dashboard" title="Acessar Painel do Gestor" style="font-size:13px;font-weight:700;color:#1e3a8a;text-decoration:none;padding:6px 14px;border-radius:9999px;border:1px solid #bfdbfe;background:#eff6ff;display:inline-flex;align-items:center;gap:6px;">
           <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
             <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/>
             <rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>
           </svg>
           <span>Painel do Gestor</span>
         </a>`
      : `<a href="adaptar-busca.php" class="btn-home-dashboard" title="Acessar Area do Cidadao" style="font-size:13px;font-weight:700;color:#065f46;text-decoration:none;padding:6px 14px;border-radius:9999px;border:1px solid #a7f3d0;background:#ecfdf5;display:inline-flex;align-items:center;gap:6px;">
           <span>Minha Area</span>
         </a>`;

    authArea.innerHTML = `
      ${dashBtn}
      <a href="editar-perfil.php" class="user-profile home-user-profile" title="Ver perfil" style="display:flex;align-items:center;gap:8px;text-decoration:none;">
        ${_plAvatarHtml(user, 36)}
        <div class="user-info" style="display:flex;flex-direction:column;">
          <span class="user-name" style="font-size:13px;font-weight:700;color:#1c1917;">${safeName}</span>
          <span class="user-role" style="font-size:11px;color:#78716c;">${esc(user.role || 'Cidadao')}</span>
        </div>
      </a>`;
  }

  // -------------------------------------------------------------------------
  // 5. HEADER DO CIDADAO
  // -------------------------------------------------------------------------
  function renderCitizenHeader() {
    const user = getUser();
    if (!user) return;

    _setEl('header-user-avatar', null, user.avatar || '', user.name);
    _setEl('header-user-name', user.name);
    _setEl('header-user-role', user.role);
    _setEl('dd-user-name',  user.name);
    _setEl('dd-user-email', user.email);

    const ddBadge = document.getElementById('dd-google-badge');
    if (ddBadge) ddBadge.style.display = user.isGoogleConnected ? '' : 'none';

    _setEl('sync-user-name', user.name);
    const syncCond = document.getElementById('sync-user-condition');
    if (syncCond && user.pcdCategories && user.pcdCategories.length > 0) {
      syncCond.textContent = user.pcdCategories.join(', ');
    }

    document.querySelectorAll('#user-dropdown-menu a[href="perfil-gestor.php"]').forEach(link => {
      link.setAttribute('href', 'editar-perfil.php');
    });
    document.querySelectorAll('#user-dropdown-menu a[href="configuracoes.php"]').forEach(link => {
      link.setAttribute('href', 'adaptar-busca.php');
      const span = link.querySelector('span');
      if (span) span.textContent = 'Preferencias de Acessibilidade';
    });

    const profileBadgeLink = document.getElementById('user-profile-badge');
    if (profileBadgeLink && profileBadgeLink.getAttribute('href') === 'perfil-gestor.php') {
      profileBadgeLink.setAttribute('href', 'editar-perfil.php');
    }

    document.querySelectorAll('a[href="notificacoes.php"]').forEach(link => {
      if (!link.closest('.gestor-sidebar')) {
        link.setAttribute('href', 'central-notificacoes.php');
      }
    });

    document.querySelectorAll('.user-profile:not([id]), .user-profile-btn').forEach(wrap => {
      const img  = wrap.querySelector('.user-avatar-img:not(#header-user-avatar)');
      const name = wrap.querySelector('.user-name:not(#header-user-name)');
      const role = wrap.querySelector('.user-role:not(#header-user-role)');
      if (img)  { _plApplyAvatar(img, user); }
      if (name) name.textContent = user.name || '';
      if (role) role.textContent = user.role || '';
    });

    const profileBtn   = document.getElementById('user-profile-btn');
    const dropdownMenu = document.getElementById('user-dropdown-menu');
    if (profileBtn && dropdownMenu) {
      profileBtn.addEventListener('click', e => {
        e.stopPropagation();
        if (e.target.closest('#header-user-avatar')) {
          window.location.href = 'editar-perfil.php';
          return;
        }
        const expanded = profileBtn.getAttribute('aria-expanded') === 'true';
        profileBtn.setAttribute('aria-expanded', String(!expanded));
        dropdownMenu.classList.toggle('show');
      });
      document.addEventListener('click', e => {
        if (!dropdownMenu.contains(e.target) && e.target !== profileBtn) {
          dropdownMenu.classList.remove('show');
          profileBtn.setAttribute('aria-expanded', 'false');
        }
      });
      document.addEventListener('keydown', e => {
        if (e.key === 'Escape') {
          dropdownMenu.classList.remove('show');
          profileBtn.setAttribute('aria-expanded', 'false');
        }
      });
    }

    ['btn-dd-logout', 'btn-logout-results'].forEach(id => {
      const btn = document.getElementById(id);
      if (btn) btn.addEventListener('click', e => {
        e.preventDefault();
        clearUser();
        showToast('Sessao encerrada.');
        setTimeout(() => { window.location.href = 'index.php'; }, 600);
      });
    });

    _updateNotifBadge();
    _plSyncAllAvatars(user);
  }

  function getToken() {
    return localStorage.getItem(TOKEN_KEY) || localStorage.getItem('passeLivreToken') || '';
  }

  async function apiFetch(url, options = {}) {
    const token = getToken();
    const headers = Object.assign({}, options.headers || {});
    if (token && !headers['X-Session-Token']) headers['X-Session-Token'] = token;
    if (!headers['Content-Type'] && options.body && typeof options.body === 'string') {
      headers['Content-Type'] = 'application/json';
    }
    const res = await fetch(url, Object.assign({}, options, { headers }));
    const data = await res.json().catch(() => ({}));
    if (!res.ok) {
      const err = new Error(data.error || `Erro na requisicao (${res.status})`);
      err.status = res.status;
      err.data = data;
      throw err;
    }
    return data;
  }

  function _updateNotifBadge() {
    const token = getToken();
    fetch('api/notificacoes.php?action=my_notifications', {
      credentials: 'same-origin',
      headers: token ? { 'X-Session-Token': token } : {}
    })
      .then(res => res.json())
      .then(data => {
        if (!data || !data.success) return;
        const unread = data.unread_count || 0;
        const badge  = document.querySelector('.menu-dot-indicator, .notif-unread-dot');
        if (badge) badge.style.display = unread > 0 ? '' : 'none';

        const countEls = document.querySelectorAll('.notif-count-badge, .notif-badge-count, .notif-unread-badge');
        countEls.forEach(el => {
          el.textContent = unread > 99 ? '99+' : String(unread);
          el.style.display = unread > 0 ? '' : 'none';
        });

        const btnNotif = document.getElementById('btn-notifications');
        if (btnNotif) {
          let b = btnNotif.querySelector('.notify-count-badge');
          if (unread > 0) {
            if (!b) {
              b = document.createElement('span');
              b.className = 'notify-count-badge';
              btnNotif.appendChild(b);
            }
            b.textContent = unread > 99 ? '99+' : String(unread);
            b.style.display = '';
          } else if (b) {
            b.remove();
          }
        }
      })
      .catch(() => {});
  }

  function _setEl(id, text, src, altName) {
    const el = document.getElementById(id);
    if (!el) return;
    if (src !== undefined) {
      _plApplyAvatar(el, { avatar: src, name: altName || '' });
    } else {
      el.textContent = text || '';
    }
  }

  // -------------------------------------------------------------------------
  // 6. PAINEL DO GESTOR
  // -------------------------------------------------------------------------
  async function updateGestorBadge() {
    try {
      const res = await fetch('api/solicitacoes.php?action=pending_count');
      const data = await res.json();
      const count = data.success ? data.count : 0;
      document.querySelectorAll('.menu-badge-counter').forEach(badge => {
        badge.textContent = String(count);
        badge.style.display = count > 0 ? '' : 'none';
      });
      localStorage.setItem('passeLivrePendingCount', String(count));
    } catch (e) {
      console.warn('[Badge Gestor]', e);
    }
  }

  function setGestorPendingCount(n) {
    localStorage.setItem('passeLivrePendingCount', String(n));
    updateGestorBadge();
  }

  function initGestorSidebar() {
    const sidebar = document.querySelector('.gestor-sidebar');
    const topBar  = document.querySelector('.gestor-top-bar');
    if (!sidebar) return;

    updateGestorBadge();

    let backdrop = document.querySelector('.gestor-sidebar-backdrop');
    if (!backdrop) {
      backdrop = document.createElement('div');
      backdrop.className = 'gestor-sidebar-backdrop';
      document.body.appendChild(backdrop);
    }

    let toggleBtn = document.getElementById('btn-gestor-menu-toggle');
    if (!toggleBtn && topBar) {
      toggleBtn = document.createElement('button');
      toggleBtn.type = 'button';
      toggleBtn.id   = 'btn-gestor-menu-toggle';
      toggleBtn.className = 'btn-gestor-menu-toggle';
      toggleBtn.setAttribute('aria-label', 'Alternar menu lateral');
      toggleBtn.setAttribute('aria-expanded', 'false');
      toggleBtn.setAttribute('aria-controls', 'gestor-sidebar');
      toggleBtn.innerHTML = `<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor"
           stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <line x1="3" y1="12" x2="21" y2="12"/>
          <line x1="3" y1="6"  x2="21" y2="6"/>
          <line x1="3" y1="18" x2="21" y2="18"/>
        </svg>`;
      topBar.insertBefore(toggleBtn, topBar.firstChild);
    }

    if (!sidebar.id) sidebar.id = 'gestor-sidebar';

    const toggleSidebar = (forceState) => {
      const open = forceState !== undefined ? forceState : !sidebar.classList.contains('open');
      sidebar.classList.toggle('open', open);
      backdrop.classList.toggle('open', open);
      if (toggleBtn) toggleBtn.setAttribute('aria-expanded', String(open));
      document.body.classList.toggle('sidebar-locked', open);
    };

    if (toggleBtn) toggleBtn.addEventListener('click', () => toggleSidebar());
    backdrop.addEventListener('click', () => toggleSidebar(false));
    document.addEventListener('keydown', e => {
      if (e.key === 'Escape' && sidebar.classList.contains('open')) toggleSidebar(false);
    });

    const btnLogout = document.getElementById('btn-logout');
    if (btnLogout) {
      btnLogout.removeAttribute('onclick');
      btnLogout.addEventListener('click', () => {
        clearUser();
        showToast('Sessao de gestor encerrada.');
        setTimeout(() => { window.location.href = 'index.php'; }, 600);
      });
    }
  }

  // -------------------------------------------------------------------------
  // EXPOSICAO GLOBAL
  // -------------------------------------------------------------------------
  window.PasseLivreAuth = {
    getUser,
    setUser,
    clearUser,
    isAdmin,
    requireAuth,
    requireAdmin,
    showToast,
    renderHomeUser,
    renderCitizenHeader,
    setGestorPendingCount,
    updateGestorBadge,
    getToken,
    apiFetch,
    updateNotifBadge: _updateNotifBadge,
    avatarHtml: _plAvatarHtml,
    applyAvatar: _plApplyAvatar,
    setPageAvatar: _plSetPageAvatar,
    avatarSrc: _plAvatarSrc,
    avatarPadrao: _PL_AVATAR_PADRAO
  };

  // -------------------------------------------------------------------------
  // AUTO-INICIALIZACAO
  // -------------------------------------------------------------------------
  document.addEventListener('DOMContentLoaded', () => {
    const body = document.body;

    if (body.classList.contains('home-page') || document.querySelector('.home-navbar')) {
      renderHomeUser();
      return;
    }

    const authClasses = ['login-page', 'cadastro-page', 'verify-page', 'reset-page'];
    if (authClasses.some(cls => body.classList.contains(cls))) return;

    if (document.querySelector('.gestor-sidebar')) {
      if (!requireAdmin()) return;
      initGestorSidebar();
      return;
    }

    if (!requireAuth()) return;
    renderCitizenHeader();
    _loadScript('js/notificacoes-cidadao.js');
  });
})();