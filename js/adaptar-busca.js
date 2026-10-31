/**
 * Passe Livre Suzano — Adaptar Busca por Perfil de Necessidade
 * Integração SQL: UPDATE saved_prefs via PLDB.users.update()
 */

document.addEventListener('DOMContentLoaded', () => {

  // =========================================================================
  // 1. ESTADO + PERSISTÊNCIA
  // =========================================================================
  function getCurrentUser() {
    // Fonte de verdade: usuário autenticado injetado pelo PHP (sessão real)
    try {
      const el = document.getElementById('php-user-data');
      const phpUser = el ? JSON.parse(el.textContent || 'null') : null;
      if (phpUser) {
        return {
          id:                 phpUser.id,
          name:               phpUser.name,
          email:              phpUser.email,
          role:               phpUser.role,
          avatar:             phpUser.avatar,
          condition:          phpUser.condition || '',
          isGoogleConnected:  false,
          pcdCategories:      Array.isArray(phpUser.pcdCategories) ? phpUser.pcdCategories : [],
          savedPreferences:   Array.isArray(phpUser.profileNeeds) ? phpUser.profileNeeds : []
        };
      }
    } catch (_) {}
    return { id:null, name:'', email:'', role:'Cidadão', avatar:'assets/images/avatar.jpg', isGoogleConnected:false, savedPreferences:[] };
  }

  async function saveCurrentUser(user) {
    localStorage.setItem('passeLivreUser', JSON.stringify(user));
    updateUIWithUser(user);

    // UPDATE SQL
    if (user.email && window.PasseLivreDBReady) {
      try {
        await window.PasseLivreDBReady;
        if (window.PLDB) {
          const dbUser = window.PLDB.users.findByEmail(user.email);
          if (dbUser) {
            window.PLDB.users.update(dbUser.id, {
              saved_prefs:    user.savedPreferences  || [],
              name:           user.name,
              is_google:      user.isGoogleConnected ? 1 : 0
            });
            console.log(`[AdaptarBusca SQL] UPDATE saved_prefs para ${user.name} OK`);
          }
        }
      } catch (e) {
        console.warn('[AdaptarBusca] UPDATE SQL falhou:', e);
      }
    }
  }

  let currentUser = getCurrentUser();

  // =========================================================================
  // 2. ATUALIZA UI COM DADOS DO USUÁRIO
  // =========================================================================
  function updateUIWithUser(user) {
    const ids = {
      'header-user-avatar': null, 'header-user-name': user.name, 'header-user-role': user.role,
      'sync-user-name': user.name, 'sync-user-condition': user.condition || 'Preferências salvas',
      'dd-user-name': user.name, 'dd-user-email': user.email
    };
    Object.entries(ids).forEach(([id, val]) => {
      const el = document.getElementById(id);
      if (!el) return;
      if (id === 'header-user-avatar') { el.src = user.avatar || 'assets/images/avatar.jpg'; el.onerror = function(){ this.src='assets/images/avatar.jpg'; }; }
      else if (val !== null) el.textContent = val;
    });
    const badge = document.getElementById('dd-google-badge');
    if (badge) badge.style.display = user.isGoogleConnected ? 'inline-flex' : 'none';
    const btnGoogleToggle = document.getElementById('btn-dd-google-toggle');
    if (btnGoogleToggle) btnGoogleToggle.textContent = user.isGoogleConnected ? 'Desconectar conta Google' : 'Conectar com Google';
  }

  updateUIWithUser(currentUser);

  // =========================================================================
  // 3. DROPDOWN DO PERFIL
  // =========================================================================
  const userProfileBtn   = document.getElementById('user-profile-btn');
  const userDropdownMenu = document.getElementById('user-dropdown-menu');
  if (userProfileBtn && userDropdownMenu) {
    userProfileBtn.addEventListener('click', e => {
      e.stopPropagation();
      const exp = userProfileBtn.getAttribute('aria-expanded') === 'true';
      userProfileBtn.setAttribute('aria-expanded', String(!exp));
      userDropdownMenu.classList.toggle('show');
    });
    document.addEventListener('click', e => {
      if (!userDropdownMenu.contains(e.target) && !userProfileBtn.contains(e.target)) {
        userDropdownMenu.classList.remove('show');
        userProfileBtn.setAttribute('aria-expanded','false');
      }
    });
  }

  // =========================================================================
  // 4. CARDS DE NECESSIDADE
  // =========================================================================
  const needCards      = document.querySelectorAll('.need-card');
  const syncToggle     = document.getElementById('toggle-sync-profile');
  const syncStatusText = document.getElementById('sync-status-text');

  function toggleCard(card, force) {
    const sel = force !== undefined ? force : !card.classList.contains('selected');
    card.classList.toggle('selected', sel);
    card.setAttribute('aria-checked', String(sel));
  }

  needCards.forEach(card => {
    card.addEventListener('click', () => { toggleCard(card); checkSyncConsistency(); });
    card.addEventListener('keydown', e => { if (e.key===' '||e.key==='Enter') { e.preventDefault(); toggleCard(card); checkSyncConsistency(); } });
  });

  function applyProfilePreferences(toast = true) {
    const saved = currentUser.savedPreferences || [];
    needCards.forEach(card => { toggleCard(card, saved.includes(card.getAttribute('data-need'))); });
    if (toast) showToast('Preferências sincronizadas com o seu perfil!');
  }

  function checkSyncConsistency() {
    if (!syncToggle) return;
    const current = Array.from(document.querySelectorAll('.need-card.selected')).map(c => c.getAttribute('data-need'));
    const saved   = currentUser.savedPreferences || [];
    const match   = current.length === saved.length && current.every(i => saved.includes(i));
    // Alteração manual enquanto sincronizado desativa o modo automático, sem religá-lo sozinho
    if (!match && syncToggle.checked) { syncToggle.checked = false; if (syncStatusText) syncStatusText.textContent = 'Personalizado'; }
  }

  if (syncToggle) {
    syncToggle.addEventListener('change', () => {
      if (syncToggle.checked) { if (syncStatusText) syncStatusText.textContent = 'Sincronizado'; applyProfilePreferences(); }
      else { if (syncStatusText) syncStatusText.textContent = 'Personalizado'; showToast('Modo de seleção livre ativado.'); }
    });

    // Sincroniza as deficiências do perfil assim que a tela carrega
    if (syncToggle.checked) applyProfilePreferences(false);
  }

  // =========================================================================
  // 5. TOGGLE OLHO DE SENHA
  // =========================================================================
  document.querySelectorAll('.btn-toggle-eye').forEach(btn => {
    btn.addEventListener('click', e => {
      e.preventDefault();
      const input = document.getElementById(btn.getAttribute('data-target'));
      if (!input) return;
      const isPass = input.getAttribute('type') === 'password';
      input.setAttribute('type', isPass ? 'text' : 'password');
      const img = btn.querySelector('img');
      if (img) { img.src = isPass ? 'assets/icons/eye-off.svg' : 'assets/icons/eye.svg'; img.alt = isPass ? 'Ocultar senha' : 'Visualizar senha'; }
    });
  });

  // =========================================================================
  // 6. MODAIS DE AUTENTICAÇÃO (login in-page)
  // =========================================================================
  const authModal = document.getElementById('auth-modal');
  const googleOAuthDialog = document.getElementById('google-oauth-dialog');

  function openAuthModal()  { if (authModal) { authModal.classList.add('active'); document.body.style.overflow='hidden'; setTimeout(()=>document.getElementById('modal-email')?.focus(),100); } }
  function closeAuthModal() { if (authModal) { authModal.classList.remove('active'); document.body.style.overflow=''; } }

  document.getElementById('btn-close-modal')?.addEventListener('click', closeAuthModal);
  authModal?.addEventListener('click', e => { if (e.target===authModal) closeAuthModal(); });
  document.getElementById('btn-open-login-modal')?.addEventListener('click', () => { userDropdownMenu?.classList.remove('show'); openAuthModal(); });

  document.getElementById('btn-close-google-dialog')?.addEventListener('click', () => googleOAuthDialog?.classList.remove('active'));
  googleOAuthDialog?.addEventListener('click', e => { if (e.target===googleOAuthDialog) googleOAuthDialog.classList.remove('active'); });

  document.getElementById('btn-modal-google')?.addEventListener('click', () => { closeAuthModal(); googleOAuthDialog?.classList.add('active'); });

  document.querySelectorAll('.google-account-item').forEach(item => {
    item.addEventListener('click', async () => {
      const email = item.getAttribute('data-email'), name = item.getAttribute('data-name'), avatar = item.getAttribute('data-avatar');
      currentUser = { name, email, role:'Cidadão Conectado', avatar: avatar||'assets/images/avatar.jpg', isGoogleConnected:true, savedPreferences:['cadeirante','mobilidade_reduzida'] };
      await saveCurrentUser(currentUser);
      googleOAuthDialog?.classList.remove('active');
      showToast(`Conectado via Google como ${name}!`);
      if (syncToggle) { syncToggle.checked=true; if(syncStatusText) syncStatusText.textContent='Sincronizado'; }
      applyProfilePreferences();
    });
  });

  document.getElementById('form-modal-login')?.addEventListener('submit', async e => {
    e.preventDefault();
    const email = document.getElementById('modal-email')?.value.trim();
    const pass  = document.getElementById('modal-password')?.value;
    if (!email||!email.includes('@')) { alert('E-mail inválido.'); return; }
    if (!pass||pass.length<6) { alert('Senha deve ter mínimo 6 caracteres.'); return; }
    currentUser = { name: email.split('@')[0], email, role:'Cidadão Conectado', avatar:'assets/images/avatar.jpg', isGoogleConnected:false, savedPreferences:[] };
    // Tenta carregar do SQL
    if (window.PLDB) { try { const u=window.PLDB.users.findByEmail(email); if(u){currentUser.name=u.name; let p=[]; try{p=JSON.parse(u.saved_prefs||'[]');}catch(_){}; currentUser.savedPreferences=p; } } catch(_){} }
    await saveCurrentUser(currentUser);
    closeAuthModal();
    showToast(`Bem-vindo(a), ${currentUser.name}!`);
    applyProfilePreferences();
  });

  document.getElementById('btn-dd-logout')?.addEventListener('click', () => {
    userDropdownMenu?.classList.remove('show');
    if (window.PasseLivreAuth) window.PasseLivreAuth.clearUser();
    else localStorage.removeItem('passeLivreUser');
    showToast('Você encerrou a sessão.');
    setTimeout(() => window.location.href='index.php', 600);
  });

  document.getElementById('btn-dd-google-toggle')?.addEventListener('click', () => {
    userDropdownMenu?.classList.remove('show');
    if (currentUser.isGoogleConnected) {
      currentUser.isGoogleConnected = false;
      saveCurrentUser(currentUser);
      showToast('Conta Google desconectada.');
    } else {
      googleOAuthDialog?.classList.add('active');
    }
  });

  // =========================================================================
  // 7. CONTINUAR → SALVA NO SQL + REDIRECIONA
  // =========================================================================
  document.getElementById('btn-continue')?.addEventListener('click', async () => {
    const selected = Array.from(document.querySelectorAll('.need-card.selected')).map(c => c.getAttribute('data-need'));
    if (!selected.length) {
      if (!confirm('Nenhuma necessidade selecionada. Ver resultados gerais?')) return;
    }

    sessionStorage.setItem('passeLivreSearchNeeds', JSON.stringify(selected));

    // UPDATE SQL + sessionUser
    if (syncToggle?.checked) {
      currentUser.savedPreferences = selected;
      await saveCurrentUser(currentUser);
    }

    showToast('Calculando rotas acessíveis em Suzano...');
    setTimeout(() => {
      window.location.href = selected.length
        ? `lugares-encontrados.php?needs=${encodeURIComponent(selected.join(','))}`
        : 'lugares-encontrados.php';
    }, 700);
  });

  // Busca global
  document.getElementById('global-search-input')?.addEventListener('keydown', e => {
    if (e.key==='Enter' && e.target.value.trim()) {
      window.location.href = `lugares-encontrados.php?q=${encodeURIComponent(e.target.value.trim())}`;
    }
  });

  // =========================================================================
  // TOAST
  // =========================================================================
  function showToast(msg) {
    if (typeof window.showToast==='function') { window.showToast(msg); return; }
    const t=document.getElementById('app-toast'), m=document.getElementById('toast-message');
    if(t&&m){m.textContent=msg;t.classList.add('visible');setTimeout(()=>t.classList.remove('visible'),3800);}
  }
  window.showAppToast = showToast;
});
