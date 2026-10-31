/**
 * Passe Livre — Lógica da Central de Ajuda
 * (busca, filtro por categoria, acordeão de FAQ e canais de suporte)
 */

document.addEventListener('DOMContentLoaded', () => {
  const faqItems = Array.from(document.querySelectorAll('.help-faq-item'));
  const searchForm = document.getElementById('help-search-form');
  const searchInput = document.getElementById('help-search-input');
  const chips = document.querySelectorAll('.help-chip');
  const catCards = document.querySelectorAll('.help-cat-card');
  const filterBar = document.getElementById('help-filter-bar');
  const filterText = document.getElementById('help-filter-text');
  const filterClear = document.getElementById('help-filter-clear');
  const emptyBox = document.getElementById('help-empty');
  const emptyTerm = document.getElementById('help-empty-term');
  const faqSection = document.getElementById('faq');

  const catNames = {
    acesso: 'Acesso & Conta',
    beneficio: 'Rotas & Normas NBR 9050',
    uso: 'Fiscalização & Obstáculos',
    via: 'Locais & Pontos de Apoio'
  };

  const state = { query: '', category: '' };

  /* ------------------------------------------------------------------
     1. Acordeão (um item aberto por vez)
     ------------------------------------------------------------------ */
  function setOpen(item, open) {
    item.classList.toggle('open', open);
    const btn = item.querySelector('.help-faq-toggle');
    if (btn) btn.setAttribute('aria-expanded', String(open));
  }

  faqItems.forEach(item => {
    const btn = item.querySelector('.help-faq-toggle');
    btn.addEventListener('click', () => {
      const willOpen = !item.classList.contains('open');
      faqItems.forEach(other => setOpen(other, false));
      setOpen(item, willOpen);
    });
  });

  /* ------------------------------------------------------------------
     2. Busca + filtro por categoria
     ------------------------------------------------------------------ */
  function normalize(text) {
    return text
      .toLowerCase()
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '');
  }

  // Termos curtos (de, o, não...) são ignorados; palavras longas casam pelo radical
  function getStems(query) {
    return normalize(query)
      .split(/\s+/)
      .filter(term => term.length > 3)
      .map(term => (term.length >= 5 ? term.slice(0, 4) : term));
  }

  function applyFilters() {
    const stems = getStems(state.query);
    let visible = 0;

    faqItems.forEach(item => {
      const haystack = normalize(item.textContent + ' ' + (item.dataset.keywords || ''));
      const matchQuery = stems.every(stem => haystack.includes(stem));
      const matchCat = !state.category || item.dataset.cat === state.category;
      const show = matchQuery && matchCat;

      item.hidden = !show;
      if (show) visible++;
    });

    // Se restou só um resultado, já deixa aberto para agilizar a leitura
    const shown = faqItems.filter(item => !item.hidden);
    if ((stems.length || state.category) && shown.length && !shown.some(i => i.classList.contains('open'))) {
      setOpen(shown[0], true);
    }

    emptyBox.hidden = visible > 0;
    emptyTerm.textContent = state.query ? `"${state.query.trim()}"` : 'este filtro';

    // Barra de filtro ativo
    const labels = [];
    if (state.category) labels.push(`Categoria: ${catNames[state.category]}`);
    if (state.query.trim()) labels.push(`Busca: "${state.query.trim()}"`);
    filterBar.hidden = labels.length === 0;
    filterText.textContent = labels.join(' • ');

    catCards.forEach(card => card.classList.toggle('active', card.dataset.cat === state.category));
  }

  function scrollToFaq() {
    if (faqSection) faqSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }

  function clearFilters() {
    state.query = '';
    state.category = '';
    searchInput.value = '';
    applyFilters();
  }

  searchForm.addEventListener('submit', (e) => {
    e.preventDefault();
    state.query = searchInput.value;
    state.category = '';
    applyFilters();
    scrollToFaq();
  });

  // Filtra enquanto digita
  searchInput.addEventListener('input', () => {
    state.query = searchInput.value;
    applyFilters();
  });

  chips.forEach(chip => {
    chip.addEventListener('click', () => {
      searchInput.value = chip.dataset.query;
      state.query = chip.dataset.query;
      state.category = '';
      applyFilters();
      scrollToFaq();
    });
  });

  catCards.forEach(card => {
    card.addEventListener('click', () => {
      const same = state.category === card.dataset.cat;
      state.category = same ? '' : card.dataset.cat;
      state.query = '';
      searchInput.value = '';
      applyFilters();
      scrollToFaq();
    });
  });

  filterClear.addEventListener('click', clearFilters);

  const showAll = document.querySelector('[data-action="show-all"]');
  if (showAll) {
    showAll.addEventListener('click', (e) => {
      e.preventDefault();
      clearFilters();
      scrollToFaq();
    });
  }

  /* ------------------------------------------------------------------
     3. Rolagem suave para âncoras internas (ex.: #suporte)
     ------------------------------------------------------------------ */
  document.querySelectorAll('a[href^="#"]:not([data-action])').forEach(link => {
    const id = link.getAttribute('href').substring(1);
    if (!id) return;
    link.addEventListener('click', (e) => {
      const target = document.getElementById(id);
      if (target) {
        e.preventDefault();
        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
    });
  });

  /* ------------------------------------------------------------------
     4. Canais de suporte
     ------------------------------------------------------------------ */
  const btnChat = document.getElementById('btn-chat');
  const btnPhone = document.getElementById('btn-phone');

  if (btnChat) {
    btnChat.addEventListener('click', () => {
      showToast('Conectando você a um atendente do Passe Livre...');
    });
  }

  if (btnPhone) {
    btnPhone.addEventListener('click', () => {
      showToast('Central: 0800 728 0000 • SENAI Suzano: (11) 4748-0000');
    });
  }

  /* ------------------------------------------------------------------
     5. Toast Feedback (mesmo padrão da Home)
     ------------------------------------------------------------------ */
  let toastTimer = null;
  function showToast(message) {
    const toast = document.getElementById('app-toast');
    const toastMsg = document.getElementById('toast-message');
    if (!toast || !toastMsg) return;

    toastMsg.textContent = message;
    toast.classList.add('visible');

    if (toastTimer) clearTimeout(toastTimer);
    toastTimer = setTimeout(() => {
      toast.classList.remove('visible');
    }, 4000);
  }

  // Estado inicial
  applyFilters();
});

// Menu mobile (hambúrguer) do cabeçalho
document.addEventListener('DOMContentLoaded', () => {
  const navToggle = document.getElementById('home-nav-toggle');
  const navLinks = document.getElementById('home-nav-links');
  if (!navToggle || !navLinks) return;

  navToggle.addEventListener('click', () => {
    const isOpen = navLinks.classList.toggle('is-open');
    navToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
  });

  navLinks.querySelectorAll('a').forEach(link => {
    link.addEventListener('click', () => {
      navLinks.classList.remove('is-open');
      navToggle.setAttribute('aria-expanded', 'false');
    });
  });
});

/* ==========================================================================
   INTEGRAÇÃO SQL — Registra buscas na Central de Ajuda
   ========================================================================== */
document.addEventListener('DOMContentLoaded', async () => {
  if (!window.PasseLivreDBReady) return;
  try {
    await window.PasseLivreDBReady;
    if (!window.PLDB) return;

    // Registra acesso à ajuda na auditoria
    if (window.PLDBAuditLog) {
      await window.PLDBAuditLog('sistema', 'Central de Ajuda Acessada', 'Usuário acessou a central de ajuda', 'baixo');
    }

    // Registra buscas no FAQ como auditoria
    const searchForm = document.getElementById('help-search-form');
    if (searchForm) {
      searchForm.addEventListener('submit', async (e) => {
        const query = document.getElementById('help-search-input')?.value.trim();
        if (query && window.PLDBAuditLog) {
          await window.PLDBAuditLog('sistema', 'Busca na Central de Ajuda', `Termo pesquisado: "${query}"`, 'baixo');
        }
      });
    }
  } catch (e) {
    console.warn('[ajuda] SQL:', e);
  }
});
