/**
 * Passe Livre Suzano — Script da Tela de Personalizar Busca
 * Controla seleção de raio, recursos indispensáveis, nível auditado e contadores dinâmicos
 */

document.addEventListener('DOMContentLoaded', () => {
  // =========================================================================
  // 1. ESTADO INICIAL DOS FILTROS
  // =========================================================================
  const distButtons = document.querySelectorAll('.dist-pill-btn');
  const resourceItems = document.querySelectorAll('.resource-item');
  const auditOptions = document.querySelectorAll('.audit-card-option');
  
  const activeFiltersBadge = document.getElementById('active-filters-badge');
  const resourcesCountLabel = document.getElementById('resources-count-label');
  const btnApplyFilters = document.getElementById('btn-apply-filters');
  const btnResetFilters = document.getElementById('btn-reset-filters');
  const btnCloseCard = document.getElementById('btn-close-card');

  let selectedDistance = '1km';
  let selectedAuditLevel = 'alta';

  // =========================================================================
  // 2. ATUALIZAÇÃO DINÂMICA DOS CONTADORES
  // =========================================================================
  function updateCounters() {
    const checkedResources = document.querySelectorAll('.resource-item.checked').length;
    
    // Atualiza label de recursos marcados (ex: "6 de 10 marcados")
    if (resourcesCountLabel) {
      resourcesCountLabel.textContent = `${checkedResources} de 10 marcados`;
    }

    // Total de filtros ativos: Recursos marcados + 1 (Nível Auditado)
    const totalActive = checkedResources + (selectedAuditLevel ? 1 : 0);

    if (activeFiltersBadge) {
      activeFiltersBadge.textContent = `Filtros Ativos: ${totalActive}`;
    }

    if (btnApplyFilters) {
      btnApplyFilters.innerHTML = `
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="width: 14px; height: 14px;">
          <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon>
        </svg>
        <span>Aplicar filtros (${totalActive} selecionados)</span>
      `;
    }
  }

  // =========================================================================
  // 3. SEÇÃO 1: DISTÂNCIA / RAIO
  // =========================================================================
  distButtons.forEach(btn => {
    btn.addEventListener('click', () => {
      distButtons.forEach(b => {
        b.classList.remove('selected');
        b.setAttribute('aria-checked', 'false');
      });
      btn.classList.add('selected');
      btn.setAttribute('aria-checked', 'true');
      selectedDistance = btn.getAttribute('data-dist') || '1km';
      showToast(`Raio definido: ${btn.textContent.trim()}`);
    });
  });

  // =========================================================================
  // 4. SEÇÃO 2: RECURSOS DESEJADOS (CHECKBOXES)
  // =========================================================================
  resourceItems.forEach(item => {
    item.addEventListener('click', () => {
      const isChecked = item.classList.contains('checked');
      if (isChecked) {
        item.classList.remove('checked');
        item.setAttribute('aria-checked', 'false');
      } else {
        item.classList.add('checked');
        item.setAttribute('aria-checked', 'true');
      }
      updateCounters();
    });

    // Acessibilidade via teclado (Espaço ou Enter)
    item.addEventListener('keydown', (e) => {
      if (e.key === ' ' || e.key === 'Enter') {
        e.preventDefault();
        item.click();
      }
    });
  });

  // =========================================================================
  // 5. SEÇÃO 3: NÍVEL DE ACESSIBILIDADE AUDITADA
  // =========================================================================
  auditOptions.forEach(card => {
    card.addEventListener('click', () => {
      auditOptions.forEach(c => {
        c.classList.remove('selected');
        c.setAttribute('aria-checked', 'false');
      });
      card.classList.add('selected');
      card.setAttribute('aria-checked', 'true');
      selectedAuditLevel = card.getAttribute('data-level') || 'alta';
      updateCounters();
    });

    card.addEventListener('keydown', (e) => {
      if (e.key === ' ' || e.key === 'Enter') {
        e.preventDefault();
        card.click();
      }
    });
  });

  // =========================================================================
  // 6. BOTÃO LIMPAR FILTROS
  // =========================================================================
  if (btnResetFilters) {
    btnResetFilters.addEventListener('click', () => {
      // Desmarca todos os recursos
      resourceItems.forEach(item => {
        item.classList.remove('checked');
        item.setAttribute('aria-checked', 'false');
      });

      // Reseta raio para 1km
      distButtons.forEach((b, idx) => {
        if (idx === 0) {
          b.classList.add('selected');
          b.setAttribute('aria-checked', 'true');
        } else {
          b.classList.remove('selected');
          b.setAttribute('aria-checked', 'false');
        }
      });
      selectedDistance = '1km';

      // Reseta auditado para alta
      auditOptions.forEach((c, idx) => {
        if (idx === 0) {
          c.classList.add('selected');
          c.setAttribute('aria-checked', 'true');
        } else {
          c.classList.remove('selected');
          c.setAttribute('aria-checked', 'false');
        }
      });
      selectedAuditLevel = 'alta';

      updateCounters();
      showToast('Filtros restaurados para o padrão.');
    });
  }

  // =========================================================================
  // 7. BOTÃO APLICAR FILTROS
  // =========================================================================
  if (btnApplyFilters) {
    btnApplyFilters.addEventListener('click', () => {
      const activeResources = Array.from(document.querySelectorAll('.resource-item.checked'))
        .map(i => i.getAttribute('data-resource'));

      const searchState = {
        radius: selectedDistance,
        resources: activeResources,
        auditLevel: selectedAuditLevel
      };

      try {
        sessionStorage.setItem('passeLivreCustomFilters', JSON.stringify(searchState));
      } catch (e) {
        console.warn('Erro ao salvar filtros', e);
      }

      showToast('Aplicando filtros personalizados de acessibilidade...');
      setTimeout(() => {
        window.location.href = `lugares-encontrados.php?dist=${encodeURIComponent(selectedDistance)}&level=${encodeURIComponent(selectedAuditLevel)}`;
      }, 600);
    });
  }

  // Fechar card
  if (btnCloseCard) {
    btnCloseCard.addEventListener('click', () => {
      window.location.href = 'explorar.php';
    });
  }

  // =========================================================================
  // 8. CONTROLES DO MAPA (ZOOM & GPS)
  // =========================================================================
  const btnZoomIn = document.getElementById('btn-zoom-in');
  const btnZoomOut = document.getElementById('btn-zoom-out');
  const btnRecenter = document.getElementById('btn-recenter-map');

  if (btnZoomIn) {
    // Não há mapa interativo nesta tela: o zoom aproximando = raio menor.
    btnZoomIn.addEventListener('click', () => moverRaio(-1));
  }

  if (btnZoomOut) {
    btnZoomOut.addEventListener('click', () => moverRaio(1));
  }

  function moverRaio(passo) {
    const botoes = Array.from(document.querySelectorAll('.dist-pill-btn[data-dist]'));
    if (!botoes.length) return;
    const atual = botoes.findIndex(b => b.classList.contains('selected'));
    const alvo = Math.max(0, Math.min(botoes.length - 1, (atual < 0 ? 0 : atual) + passo));
    if (alvo === atual) return;
    botoes[alvo].click();
  }

  if (btnRecenter) {
    btnRecenter.addEventListener('click', () => {
      if (!('geolocation' in navigator)) {
        showToast('Este navegador não permite obter sua localização.');
        return;
      }
      showToast('Obtendo sua localização...');
      navigator.geolocation.getCurrentPosition((pos) => {
        sessionStorage.setItem('passeLivre_gps_lat', String(pos.coords.latitude));
        sessionStorage.setItem('passeLivre_gps_lng', String(pos.coords.longitude));
        showToast('Localização salva. A busca será centrada neste ponto.');
      }, () => {
        showToast('Sem permissão de GPS. Informe o endereço na busca para escolher a região.');
      }, { enableHighAccuracy: true, timeout: 8000, maximumAge: 60000 });
    });
  }

  // =========================================================================
  // 9. MENU DE USUÁRIO & TOAST
  // =========================================================================
  const userProfileBtn = document.getElementById('user-profile-btn');
  const userDropdownMenu = document.getElementById('user-dropdown-menu');

  if (userProfileBtn && userDropdownMenu) {
    userProfileBtn.addEventListener('click', (e) => {
      e.stopPropagation();
      const isExpanded = userProfileBtn.getAttribute('aria-expanded') === 'true';
      userProfileBtn.setAttribute('aria-expanded', !isExpanded);
      userDropdownMenu.classList.toggle('show');
    });

    document.addEventListener('click', (e) => {
      if (!userDropdownMenu.contains(e.target) && !userProfileBtn.contains(e.target)) {
        userDropdownMenu.classList.remove('show');
        userProfileBtn.setAttribute('aria-expanded', 'false');
      }
    });
  }

  // Toast
  let toastTimeout = null;
  function showToast(message, duration = 3000) {
    const toast = document.getElementById('app-toast');
    const toastMsg = document.getElementById('toast-message');
    if (!toast || !toastMsg) return;

    toastMsg.textContent = message;
    toast.classList.add('visible');

    if (toastTimeout) clearTimeout(toastTimeout);
    toastTimeout = setTimeout(() => {
      toast.classList.remove('visible');
    }, duration);
  }

  // Busca do cabeçalho
  const globalSearchInput = document.getElementById('global-search-input');
  if (globalSearchInput) {
    globalSearchInput.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') {
        const query = globalSearchInput.value.trim();
        if (query) {
          window.location.href = `lugares-encontrados.php?q=${encodeURIComponent(query)}`;
        }
      }
    });
  }

  // Inicializa os contadores
  updateCounters();
});

/* ==========================================================================
   INTEGRAÇÃO SQL — Salva preferências de busca no banco
   ========================================================================== */
document.addEventListener('DOMContentLoaded', async () => {
  if (!window.PasseLivreDBReady) return;
  try {
    await window.PasseLivreDBReady;
    if (!window.PLDB) return;

    // Pré-carrega preferências salvas do banco
    const user   = window.PasseLivreAuth?.getUser();
    const dbUser = user ? window.PLDB.users.findByEmail(user.email) : null;
    if (dbUser) {
      let prefs = [];
      try { prefs = JSON.parse(dbUser.saved_prefs || '[]'); } catch (_) {}
      if (prefs.length) {
        console.log(`[personalizar-busca SQL] Preferências carregadas: ${prefs.join(', ')}`);
      }
    }

    // Botão "Aplicar Filtros" — salva preferências no banco
    document.getElementById('btn-apply-filters')?.addEventListener('click', async () => {
      if (!window.PLDB || !dbUser) return;

      const selectedResources = Array.from(document.querySelectorAll('.resource-item.checked'))
        .map(el => el.getAttribute('data-resource') || el.textContent.trim());
      const selectedDist = document.querySelector('.dist-pill-btn.active')?.getAttribute('data-dist') || '1km';
      const selectedAudit = document.querySelector('.audit-card-option.selected')?.getAttribute('data-level') || 'alta';

      const prefs = [...selectedResources, `distancia_${selectedDist}`, `auditoria_${selectedAudit}`];

      window.PLDB.users.update(dbUser.id, { saved_prefs: prefs });
      sessionStorage.setItem('passeLivre_custom_prefs', JSON.stringify(prefs));
      console.log('[personalizar-busca SQL] Preferências salvas no banco:', prefs);
    });
  } catch (e) {
    console.warn('[personalizar-busca] SQL:', e);
  }
});
