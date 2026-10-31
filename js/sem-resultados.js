/**
 * Passe Livre Suzano — Script da Tela de Sem Resultados
 * Gerencia remoção de filtros, sugestões de desbloqueio, navegação e autenticação
 */

document.addEventListener('DOMContentLoaded', () => {
  // =========================================================================
  // 1. ESTADO DO USUÁRIO E PERFIL
  // =========================================================================
  const defaultProfile = {
    name: 'João Silva',
    email: 'joao.silva@gmail.com',
    role: 'Cidadão Conectado',
    avatar: 'assets/images/avatar.jpg',
    isGoogleConnected: true
  };

  function getCurrentUser() {
    try {
      const stored = localStorage.getItem('passeLivreUser');
      if (stored) {
        return Object.assign({}, defaultProfile, JSON.parse(stored));
      }
    } catch (e) {
      console.warn('Erro ao carregar usuário salvo', e);
    }
    return defaultProfile;
  }

  const currentUser = getCurrentUser();

  const headerAvatar = document.getElementById('header-user-avatar');
  const headerName = document.getElementById('header-user-name');
  const headerRole = document.getElementById('header-user-role');
  const ddName = document.getElementById('dd-user-name');
  const ddEmail = document.getElementById('dd-user-email');

  if (headerAvatar) headerAvatar.src = currentUser.avatar || 'assets/images/avatar.jpg';
  if (headerName) headerName.textContent = currentUser.name || 'João Silva';
  if (headerRole) headerRole.textContent = currentUser.role || 'Cidadão Conectado';
  if (ddName) ddName.textContent = currentUser.name || 'João Silva';
  if (ddEmail) ddEmail.textContent = currentUser.email || 'joao.silva@gmail.com';

  // Dropdown do perfil
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

  // =========================================================================
  // 2. SISTEMA DE TOAST NOTIFICAÇÃO
  // =========================================================================
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

  // =========================================================================
  // 3. REMOÇÃO DE CHIPS DE FILTRO ATIVO
  // =========================================================================
  const removeChipButtons = document.querySelectorAll('.btn-remove-chip');
  const chipsTrack = document.getElementById('active-chips-track');
  const btnClearAll = document.getElementById('btn-clear-all-filters');

  removeChipButtons.forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.stopPropagation();
      const chip = btn.closest('.filter-active-chip');
      if (chip) {
        chip.style.transition = 'all 0.2s ease';
        chip.style.opacity = '0';
        chip.style.transform = 'scale(0.8)';
        setTimeout(() => {
          chip.remove();
          checkRemainingChips();
        }, 200);
      }
    });
  });

  function checkRemainingChips() {
    const remainingChips = document.querySelectorAll('.filter-active-chip');
    if (remainingChips.length === 0) {
      showToast('Todos os filtros foram removidos. Recarregando locais...');
      setTimeout(() => {
        window.location.href = 'lugares-encontrados.php?filter=all';
      }, 700);
    } else {
      showToast('Filtro removido. Verificando novos locais disponíveis...');
    }
  }

  if (btnClearAll) {
    btnClearAll.addEventListener('click', () => {
      const remainingChips = document.querySelectorAll('.filter-active-chip');
      remainingChips.forEach(chip => {
        chip.style.transition = 'all 0.2s ease';
        chip.style.opacity = '0';
        chip.style.transform = 'scale(0.8)';
      });
      setTimeout(() => {
        showToast('Filtros limpos! Exibindo todos os locais mapeados...');
        setTimeout(() => {
          window.location.href = 'lugares-encontrados.php?filter=all';
        }, 600);
      }, 200);
    });
  }

  // =========================================================================
  // 4. SUGESTÕES RÁPIDAS PARA DESBLOQUEAR LOCAIS (GRID 2x2)
  // =========================================================================
  const cardExpandRadius = document.getElementById('sugg-expand-radius');
  const cardAcceptPartial = document.getElementById('sugg-accept-partial');
  const cardRemoveAdvanced = document.getElementById('sugg-remove-advanced');
  const cardViewAll = document.getElementById('sugg-view-all');

  if (cardExpandRadius) {
    cardExpandRadius.addEventListener('click', () => {
      showToast('Ampliando raio de busca para 5 km em Suzano...');
      setTimeout(() => {
        window.location.href = 'lugares-encontrados.php?radius=5km';
      }, 700);
    });
  }

  if (cardAcceptPartial) {
    cardAcceptPartial.addEventListener('click', () => {
      showToast('Incluindo estabelecimentos com acessibilidade parcial auditada...');
      setTimeout(() => {
        window.location.href = 'lugares-encontrados.php?filter=partial';
      }, 700);
    });
  }

  if (cardRemoveAdvanced) {
    cardRemoveAdvanced.addEventListener('click', () => {
      const chipAudio = document.querySelector('[data-filter="audiodescricao"]');
      if (chipAudio) {
        chipAudio.style.transition = 'all 0.2s ease';
        chipAudio.style.opacity = '0';
        chipAudio.style.transform = 'scale(0.8)';
        setTimeout(() => chipAudio.remove(), 200);
      }
      showToast('Filtros avançados removidos! Mantendo apenas requisitos essenciais.');
      setTimeout(() => {
        window.location.href = 'lugares-encontrados.php?filter=essential';
      }, 800);
    });
  }

  if (cardViewAll) {
    cardViewAll.addEventListener('click', () => {
      showToast('Abrindo visualização de todos os locais mapeados no sistema...');
      setTimeout(() => {
        window.location.href = 'lugares-encontrados.php?filter=all';
      }, 700);
    });
  }

  // =========================================================================
  // 5. BOTÕES DE AÇÃO PRINCIPAIS
  // =========================================================================
  const btnViewNearest = document.getElementById('btn-view-nearest');
  const btnAdjustFilters = document.getElementById('btn-adjust-filters');

  if (btnViewNearest) {
    btnViewNearest.addEventListener('click', () => {
      showToast('Calculando locais mais próximos da sua posição atual...');
      setTimeout(() => {
        window.location.href = 'lugares-encontrados.php?sort=distance';
      }, 600);
    });
  }

  if (btnAdjustFilters) {
    btnAdjustFilters.addEventListener('click', () => {
      window.location.href = 'adaptar-busca.php';
    });
  }

  // =========================================================================
  // 6. BUSCA GLOBAL NO CABEÇALHO
  // =========================================================================
  const globalSearchInput = document.getElementById('global-search-input');
  if (globalSearchInput) {
    globalSearchInput.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') {
        const query = globalSearchInput.value.trim();
        if (query) {
          showToast(`Buscando por "${query}"...`);
          setTimeout(() => {
            window.location.href = `lugares-encontrados.php?q=${encodeURIComponent(query)}`;
          }, 500);
        }
      }
    });
  }

  // =========================================================================
  // 7. PARÂMETROS DA URL
  // =========================================================================
  const urlParams = new URLSearchParams(window.location.search);
  const searchQ = urlParams.get('q');
  if (searchQ) {
    if (globalSearchInput) globalSearchInput.value = searchQ;
    const chipsTrack = document.getElementById('active-chips-track');
    if (chipsTrack) {
      const termChip = document.createElement('div');
      termChip.className = 'filter-active-chip';
      termChip.setAttribute('role', 'listitem');
      termChip.innerHTML = `
        <svg class="chip-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <circle cx="11" cy="11" r="8"></circle>
          <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
        </svg>
        <span>Termo: "${escapeHtml(searchQ)}"</span>
        <button type="button" class="btn-remove-chip" aria-label="Remover busca">&times;</button>
      `;
      chipsTrack.prepend(termChip);
      termChip.querySelector('.btn-remove-chip').addEventListener('click', (e) => {
        e.stopPropagation();
        termChip.remove();
        checkRemainingChips();
      });
    }
  }

  function escapeHtml(str) {
    return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }
});

/* ==========================================================================
   INTEGRAÇÃO SQL — sem-resultados.js
   Sugere locais do banco quando a busca não retorna resultados
   ========================================================================== */
document.addEventListener('DOMContentLoaded', async () => {
  if (!window.PasseLivreDBReady) return;
  try {
    await window.PasseLivreDBReady;
    if (!window.PLDB) return;

    // Carrega sugestões de locais verificados do banco
    const sugestoes = window.PLDB.locais.findAll({ access_status: 'verified' });
    const elSugList = document.getElementById('suggestions-list') || document.querySelector('.suggestions-grid');

    if (elSugList && sugestoes.length) {
      const frag = document.createDocumentFragment();
      sugestoes.slice(0, 4).forEach(l => {
        const card = document.createElement('a');
        card.href      = `detalhes-local.php?id=${encodeURIComponent(l.local_code || '')}`;
        card.className = 'suggestion-card';

        const strongEl = document.createElement('strong');
        strongEl.textContent = l.name || '';

        const spanEl = document.createElement('span');
        spanEl.textContent = l.address || '';

        card.appendChild(strongEl);
        card.appendChild(spanEl);
        frag.appendChild(card);
      });
      elSugList.innerHTML = '';
      elSugList.appendChild(frag);
      console.log(`[sem-resultados SQL] ${sugestoes.length} sugestões do banco carregadas.`);
    }
  } catch (e) {
    console.warn('[sem-resultados] SQL:', e);
  }
});
