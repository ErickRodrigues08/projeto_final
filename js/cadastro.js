/**
 * Passe Livre Suzano — Cadastro de Cidadão
 */
document.addEventListener('DOMContentLoaded', () => {

  function showToast(msg, type) {
    if (typeof window.showToast === 'function') return window.showToast(msg, type);
    const t = document.getElementById('app-toast');
    const m = document.getElementById('toast-message');
    if (t && m) { m.textContent = msg; t.classList.add('visible'); setTimeout(() => t.classList.remove('visible'), 3600); }
  }

  // =========================================================================
  // 1. Alternância de Visibilidade das Senhas
  // =========================================================================
  document.querySelectorAll('.btn-toggle-eye').forEach(btn => {
    btn.addEventListener('click', () => {
      const input = document.getElementById(btn.getAttribute('data-target'));
      if (!input) return;
      const isPass = input.type === 'password';
      input.type = isPass ? 'text' : 'password';
      const img = btn.querySelector('img');
      if (img) {
        img.src = isPass ? 'assets/icons/eye-off.svg' : 'assets/icons/eye.svg';
        img.alt = isPass ? 'Ocultar senha' : 'Visualizar senha';
        btn.setAttribute('title',      isPass ? 'Ocultar senha' : 'Visualizar senha');
        btn.setAttribute('aria-label', isPass ? 'Ocultar senha' : 'Visualizar senha');
      }
    });
  });

  // =========================================================================
  // 2. Máscara e limite do campo telefone — máx. 15 chars: (11) 99999-9999
  // =========================================================================
  const telefoneEl = document.getElementById('cad-telefone');
  if (telefoneEl) {
    telefoneEl.setAttribute('maxlength', '15');

    telefoneEl.addEventListener('input', () => {
      let v = telefoneEl.value.replace(/\D/g, '');
      if (v.length > 11) v = v.slice(0, 11); // máx. 11 dígitos numéricos

      if (v.length === 0) {
        telefoneEl.value = '';
      } else if (v.length <= 2) {
        telefoneEl.value = '(' + v;
      } else if (v.length <= 6) {
        telefoneEl.value = '(' + v.slice(0, 2) + ') ' + v.slice(2);
      } else if (v.length <= 10) {
        telefoneEl.value = '(' + v.slice(0, 2) + ') ' + v.slice(2, 6) + '-' + v.slice(6);
      } else {
        // 11 dígitos: celular (xx) 9xxxx-xxxx
        telefoneEl.value = '(' + v.slice(0, 2) + ') ' + v.slice(2, 7) + '-' + v.slice(7);
      }
    });
  }

  // =========================================================================
  // 3. Seleção de Opções de PCD
  // =========================================================================
  document.querySelectorAll('.pcd-option-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      const selected = btn.classList.toggle('selected');
      btn.setAttribute('aria-pressed', String(selected));
      if (selected) showToast(`Perfil selecionado: ${btn.getAttribute('data-category')}`);
    });
  });

  // =========================================================================
  // 4. Estado / Município — API IBGE (localidades)
  // =========================================================================
  const estadoEl    = document.getElementById('cad-estado');
  const municipioEl = document.getElementById('cad-municipio');
  const cidadeEl    = document.getElementById('cad-cidade'); // hidden

  if (estadoEl && municipioEl) {
    let municipiosController = null;

    estadoEl.addEventListener('change', async () => {
      const uf = estadoEl.value;

      if (municipiosController) municipiosController.abort();
      // Limpa e desabilita município enquanto carrega
      municipioEl.innerHTML = '<option value="">Carregando municípios...</option>';
      municipioEl.disabled = true;
      if (cidadeEl) cidadeEl.value = '';

      if (!uf) {
        municipioEl.innerHTML = '<option value="">Selecione um estado primeiro</option>';
        return;
      }

      const controller = new AbortController();
      municipiosController = controller;

      try {
        const url  = `https://servicodados.ibge.gov.br/api/v1/localidades/estados/${uf}/municipios?orderBy=nome`;
        const resp = await fetch(url, { signal: controller.signal });
        if (!resp.ok) throw new Error('Falha IBGE');
        const municipios = await resp.json();
        if (controller.signal.aborted || estadoEl.value !== uf) return;
        if (!Array.isArray(municipios) || municipios.length === 0) {
          throw new Error('Resposta inválida ao carregar municípios');
        }

        municipioEl.innerHTML = '<option value="">Selecione o município</option>';
        municipios.forEach(m => {
          const opt  = document.createElement('option');
          opt.value  = m.nome;
          opt.textContent = m.nome;
          // Pré-seleciona Suzano quando SP for o estado escolhido
          if (m.nome === 'Suzano' && uf === 'SP') opt.selected = true;
          municipioEl.appendChild(opt);
        });

        municipioEl.disabled = false;
        // Atualiza campo oculto cidade ao trocar o município
        _syncCidade();
      } catch (err) {
        if (err.name === 'AbortError' || estadoEl.value !== uf) return;
        console.warn('[Cadastro] Falha ao carregar municípios do IBGE:', err);
        municipioEl.innerHTML = '<option value="">Erro ao carregar — tente novamente</option>';
        municipioEl.disabled = true;
        showToast('Não foi possível carregar os municípios. Verifique sua conexão.', 'error');
      }
    });

    municipioEl.addEventListener('change', _syncCidade);

    function _syncCidade() {
      if (!cidadeEl) return;
      const mun = municipioEl.value;
      const uf  = estadoEl.value;
      cidadeEl.value = mun && uf ? `${mun} - ${uf}` : (mun || '');
    }

    // Dispara change inicial para carregar municípios do SP (pré-selecionado)
    if (estadoEl.value) {
      estadoEl.dispatchEvent(new Event('change'));
    }
  }

  // =========================================================================
  // 4.1 Combobox customizado do município — painel sempre abre para baixo.
  //     O <select> original permanece no DOM como fonte de valor/validação.
  // =========================================================================
  (function enhanceMunicipioCombobox() {
    const select = document.getElementById('cad-municipio');
    if (!select) return;

    select.classList.add('pl-combobox-native');
    select.required = false; // a validação continua no submit (seção 5)

    const root = document.createElement('div');
    root.className = 'pl-combobox';

    const trigger = document.createElement('button');
    trigger.type = 'button';
    trigger.className = 'pl-combobox-trigger form-input-cadastro';
    trigger.setAttribute('aria-haspopup', 'listbox');
    trigger.setAttribute('aria-expanded', 'false');
    trigger.setAttribute('aria-controls', 'pl-municipio-list');
    trigger.setAttribute('aria-required', 'true');
    trigger.setAttribute('aria-label', 'Município');
    trigger.disabled = select.disabled;
    trigger.innerHTML = '<span class="pl-combobox-value is-placeholder"></span><span class="pl-combobox-arrow" aria-hidden="true"></span>';

    const panel = document.createElement('div');
    panel.className = 'pl-combobox-panel';
    panel.hidden = true;

    const search = document.createElement('input');
    search.type = 'text';
    search.className = 'pl-combobox-search';
    search.placeholder = 'Buscar município...';
    search.setAttribute('aria-label', 'Buscar município');
    search.setAttribute('role', 'combobox');
    search.setAttribute('aria-controls', 'pl-municipio-list');
    search.setAttribute('aria-expanded', 'false');
    search.setAttribute('aria-autocomplete', 'list');
    search.autocomplete = 'off';

    const list = document.createElement('ul');
    list.className = 'pl-combobox-list';
    list.id = 'pl-municipio-list';
    list.setAttribute('role', 'listbox');
    list.setAttribute('aria-label', 'Municípios');

    panel.append(search, list);
    root.append(trigger, panel);
    select.insertAdjacentElement('afterend', root);

    const valueEl = trigger.querySelector('.pl-combobox-value');
    let allOptions = [];
    let visibleOptions = [];
    let activeIndex = -1;

    function readOptions() {
      allOptions = Array.from(select.options).map(o => ({
        value: o.value,
        label: o.textContent.trim()
      }));
    }

    function syncTrigger() {
      const opt = select.options[select.selectedIndex];
      const label = opt ? opt.textContent.trim() : '';
      valueEl.textContent = label || 'Selecione o município';
      valueEl.classList.toggle('is-placeholder', !select.value);
      trigger.disabled = select.disabled;
      if (select.disabled) close();
    }

    function render(filter = '') {
      const needle = filter.trim().toLowerCase();
      visibleOptions = allOptions.filter(o => needle === '' || o.label.toLowerCase().includes(needle));
      list.innerHTML = '';
      activeIndex = -1;
      search.removeAttribute('aria-activedescendant');

      if (visibleOptions.length === 0) {
        const empty = document.createElement('li');
        empty.className = 'pl-combobox-empty';
        empty.textContent = 'Nenhum município encontrado';
        list.appendChild(empty);
        return;
      }

      const frag = document.createDocumentFragment();
      visibleOptions.forEach((opt, i) => {
        const li = document.createElement('li');
        li.className = 'pl-combobox-option';
        li.id = `pl-municipio-opt-${i}`;
        li.setAttribute('role', 'option');
        li.setAttribute('aria-selected', String(opt.value === select.value));
        li.dataset.value = opt.value;
        li.dataset.index = String(i);
        li.textContent = opt.label;
        frag.appendChild(li);
      });
      list.appendChild(frag);
    }

    function setActive(index) {
      const items = Array.from(list.querySelectorAll('.pl-combobox-option'));
      items.forEach(el => el.classList.remove('is-active'));
      if (index < 0 || index >= items.length) {
        activeIndex = -1;
        search.removeAttribute('aria-activedescendant');
        return;
      }
      activeIndex = index;
      const el = items[index];
      el.classList.add('is-active');
      search.setAttribute('aria-activedescendant', el.id);
      el.scrollIntoView({ block: 'nearest' });
    }

    function open() {
      if (select.disabled) return;
      readOptions();
      panel.hidden = false;
      root.classList.add('is-open');
      trigger.setAttribute('aria-expanded', 'true');
      search.setAttribute('aria-expanded', 'true');
      search.value = '';
      render();
      setActive(allOptions.findIndex(o => o.value === select.value));
      search.focus({ preventScroll: true });
    }

    function close(returnFocus = false) {
      if (panel.hidden) return;
      panel.hidden = true;
      root.classList.remove('is-open');
      trigger.setAttribute('aria-expanded', 'false');
      search.setAttribute('aria-expanded', 'false');
      search.removeAttribute('aria-activedescendant');
      if (returnFocus) trigger.focus({ preventScroll: true });
    }

    function choose(value) {
      if (select.value !== value) {
        select.value = value;
        select.dispatchEvent(new Event('change', { bubbles: true }));
      }
      close(true);
    }

    trigger.addEventListener('click', () => {
      if (panel.hidden) open(); else close(true);
    });

    trigger.addEventListener('keydown', e => {
      if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
        e.preventDefault();
        if (panel.hidden) open();
      }
    });

    search.addEventListener('input', () => {
      render(search.value);
      setActive(0);
    });

    search.addEventListener('keydown', e => {
      const count = visibleOptions.length;
      if (e.key === 'ArrowDown') {
        e.preventDefault();
        if (count) setActive(Math.min(count - 1, activeIndex + 1));
      } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        if (count) setActive(Math.max(0, activeIndex - 1));
      } else if (e.key === 'Enter') {
        e.preventDefault();
        const idx = activeIndex >= 0 ? activeIndex : 0;
        if (visibleOptions[idx]) choose(visibleOptions[idx].value);
      } else if (e.key === 'Escape') {
        e.preventDefault();
        close(true);
      } else if (e.key === 'Tab') {
        close();
      }
    });

    list.addEventListener('click', e => {
      const item = e.target.closest('.pl-combobox-option');
      if (item) choose(item.dataset.value);
    });

    list.addEventListener('mousemove', e => {
      const item = e.target.closest('.pl-combobox-option');
      if (!item) return;
      const idx = Number(item.dataset.index);
      if (idx !== activeIndex) setActive(idx);
    });

    select.addEventListener('change', syncTrigger);

    document.addEventListener('click', e => {
      if (!root.contains(e.target)) close();
    });

    document.addEventListener('keydown', e => {
      if (e.key === 'Escape' && !panel.hidden) close(true);
    });

    const labelEl = document.querySelector('label[for="cad-municipio"]');
    if (labelEl) {
      labelEl.addEventListener('click', e => {
        e.preventDefault();
        if (select.disabled) return;
        trigger.focus({ preventScroll: true });
        if (panel.hidden) open();
      });
    }

    // O select é preenchido via innerHTML pelo código do IBGE; espelha as opções.
    new MutationObserver(() => {
      syncTrigger();
      if (!panel.hidden) render(search.value);
    }).observe(select, { childList: true, subtree: true, attributes: true, attributeFilter: ['disabled'] });

    syncTrigger();
  })();

  // =========================================================================
  // 4.2 Modal de Termos de Uso e Política de Privacidade
  //     Conteúdo reaproveitado de termos-de-uso.php e privacidade.php.
  // =========================================================================
  (function initTermosModal() {
    const modal = document.getElementById('pl-termos-modal');
    if (!modal) return;

    const closeBtn         = document.getElementById('pl-termos-close');
    const okBtn            = document.getElementById('pl-termos-ok');
    const bodyEl           = document.getElementById('pl-termos-body');
    const loadingEl        = document.getElementById('pl-termos-loading');
    const sectionTermos    = document.getElementById('pl-termos-section-termos');
    const sectionPrivacidade = document.getElementById('pl-termos-section-privacidade');
    const docTermos        = document.getElementById('pl-termos-doc-termos');
    const docPrivacidade   = document.getElementById('pl-termos-doc-privacidade');

    let docsPromise = null;
    let docsLoaded  = false;
    let lastFocused = null;
    let pendingSection = 'termos';

    const sectionFor = name => (name === 'privacidade' ? sectionPrivacidade : sectionTermos);

    function extractArticle(html) {
      const doc = new DOMParser().parseFromString(html, 'text/html');
      const article = doc.querySelector('.legis-card');
      if (!article) throw new Error('Conteúdo não encontrado');

      // Ajusta a hierarquia de títulos (modal h2 > seção h3 > documento h4)
      article.querySelectorAll('h2').forEach(h2 => {
        const h4 = doc.createElement('h4');
        h4.innerHTML = h2.innerHTML;
        h2.replaceWith(h4);
      });

      // Remove estilos inline para o modal aplicar o padrão visual do cadastro
      article.querySelectorAll('[style]').forEach(el => el.removeAttribute('style'));
      return article.innerHTML;
    }

    function fetchPage(url) {
      return fetch(url, { headers: { Accept: 'text/html' } }).then(resp => {
        if (!resp.ok) throw new Error(`Falha ao carregar ${url}`);
        return resp.text();
      });
    }

    function loadDocs() {
      if (docsPromise) return docsPromise;

      loadingEl.hidden = false;
      docsPromise = Promise.all([
        fetchPage('termos-de-uso.php'),
        fetchPage('privacidade.php')
      ]).then(([termosHtml, privacidadeHtml]) => {
        docTermos.innerHTML = extractArticle(termosHtml);
        docPrivacidade.innerHTML = extractArticle(privacidadeHtml);
        bindInternalLinks();
        loadingEl.hidden = true;
        sectionTermos.hidden = false;
        sectionPrivacidade.hidden = false;
        docsLoaded = true;
        if (modal.classList.contains('is-open')) scrollToSection(pendingSection);
      }).catch(() => {
        loadingEl.textContent = 'Não foi possível carregar os termos agora. Tente novamente em instantes.';
        docsPromise = null;
      });

      return docsPromise;
    }

    function scrollToSection(name) {
      const target = sectionFor(name);
      if (!target || target.hidden) return;
      const paddingTop = parseFloat(getComputedStyle(bodyEl).paddingTop) || 0;
      bodyEl.scrollTop = Math.max(0, target.offsetTop - paddingTop);
    }

    function bindInternalLinks() {
      modal.querySelectorAll('.pl-termos-doc a[href]').forEach(link => {
        const href = link.getAttribute('href');
        if (href === 'termos-de-uso.php' || href === 'privacidade.php') {
          link.addEventListener('click', e => {
            e.preventDefault();
            scrollToSection(href === 'privacidade.php' ? 'privacidade' : 'termos');
          });
        }
      });
    }

    function focusableItems() {
      return Array.from(modal.querySelectorAll('button:not([disabled]), a[href], [tabindex]:not([tabindex="-1"])'))
        .filter(el => el.offsetParent !== null);
    }

    function openModal(sectionName, triggerEl) {
      pendingSection = sectionName === 'privacidade' ? 'privacidade' : 'termos';
      lastFocused = triggerEl || document.activeElement;

      loadDocs();

      modal.classList.add('is-open');
      modal.setAttribute('aria-hidden', 'false');
      document.body.classList.add('pl-termos-open');

      if (docsLoaded) scrollToSection(pendingSection);
      else bodyEl.scrollTop = 0;

      // Força o recálculo de estilo para o modal já estar visível ao receber foco
      void modal.offsetWidth;
      const focusClose = () => {
        if (modal.classList.contains('is-open') && !modal.contains(document.activeElement)) {
          closeBtn.focus({ preventScroll: true });
        }
      };
      focusClose();
      requestAnimationFrame(focusClose);
      setTimeout(focusClose, 0);
    }

    function closeModal() {
      if (!modal.classList.contains('is-open')) return;

      modal.classList.remove('is-open');
      modal.setAttribute('aria-hidden', 'true');
      document.body.classList.remove('pl-termos-open');

      if (lastFocused && typeof lastFocused.focus === 'function') {
        lastFocused.focus({ preventScroll: true });
      }
    }

    document.querySelectorAll('.pl-termos-link').forEach(link => {
      link.addEventListener('click', e => {
        e.preventDefault();
        e.stopPropagation();
        openModal(link.getAttribute('data-termos-section'), link);
      });
    });

    closeBtn.addEventListener('click', closeModal);
    if (okBtn) okBtn.addEventListener('click', closeModal);

    modal.addEventListener('click', e => {
      if (e.target === modal || e.target.classList.contains('pl-termos-overlay')) closeModal();
    });

    document.addEventListener('keydown', e => {
      if (e.key === 'Escape' && modal.classList.contains('is-open')) {
        e.preventDefault();
        closeModal();
      }
    });

    // Mantém o foco dentro do modal enquanto ele estiver aberto
    modal.addEventListener('keydown', e => {
      if (e.key !== 'Tab') return;
      const items = focusableItems();
      if (!items.length) return;

      const first = items[0];
      const last  = items[items.length - 1];

      if (e.shiftKey && document.activeElement === first) {
        e.preventDefault();
        last.focus();
      } else if (!e.shiftKey && document.activeElement === last) {
        e.preventDefault();
        first.focus();
      }
    });

    // Pré-carrega para o clique abrir imediatamente com o conteúdo pronto
    loadDocs();
  })();

  // =========================================================================
  // 5. Submissão do Formulário
  // =========================================================================
  const formCadastro = document.getElementById('form-cadastro');
  if (!formCadastro) return;

  formCadastro.addEventListener('submit', async e => {
    e.preventDefault();

    const nomeEl      = document.getElementById('cad-nome');
    const emailEl     = document.getElementById('cad-email');
    const senhaEl     = document.getElementById('cad-senha');
    const confirmEl   = document.getElementById('cad-confirm-senha');
    const termosEl    = document.getElementById('cad-termos');
    const cidadeHid   = document.getElementById('cad-cidade');  // hidden
    const munEl       = document.getElementById('cad-municipio');
    const estadoSel   = document.getElementById('cad-estado');

    const nome         = nomeEl?.value.trim();
    const email        = emailEl?.value.trim();
    const telefone     = telefoneEl?.value.trim() || '';
    const senha        = senhaEl?.value;
    const confirmSenha = confirmEl?.value;
    const cidade       = cidadeHid?.value.trim() || '';
    const termos       = termosEl?.checked;

    // Validações
    if (!nome)                              { showToast('Por favor, informe seu nome completo.', 'error');     nomeEl?.focus();    return; }
    if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                                              showToast('Por favor, informe um e-mail válido.', 'error');      emailEl?.focus();   return; }
    if (telefone && telefone.replace(/\D/g,'').length > 11) {
                                              showToast('Número de telefone inválido.', 'error');               telefoneEl?.focus(); return; }
    if (!senha || senha.length < 6)         { showToast('A senha deve ter no mínimo 6 caracteres.', 'error'); senhaEl?.focus();   return; }
    if (senha !== confirmSenha)             { showToast('As senhas não coincidem.', 'error');                  confirmEl?.focus(); return; }
    if (estadoSel && !estadoSel.value)      { showToast('Por favor, selecione o estado.', 'error');           estadoSel?.focus(); return; }
    if (munEl && (munEl.disabled || !munEl.value || cidadeHid?.value !== `${munEl.value} - ${estadoSel?.value}`)) {
                                              showToast('Por favor, selecione novamente um município válido para o estado.', 'error'); munEl.focus(); return; }
    if (!termos)                            { showToast('É necessário aceitar os termos de uso.', 'error');   termosEl?.focus();  return; }

    const selectedPcd = [];
    document.querySelectorAll('.pcd-option-btn.selected').forEach(b => selectedPcd.push(b.getAttribute('data-category')));

    try {
      const resp = await fetch('api/auth.php?action=register', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          name:           nome,
          email:          email,
          phone:          telefone,
          password:       senha,
          city:           cidade || (munEl?.value ? `${munEl.value} - ${estadoSel?.value}` : 'Suzano - SP'),
          pcd_categories: selectedPcd,
          termos:         true
        })
      });
      const data = await resp.json();

      if (data.success) {
        if (data.demo_code) sessionStorage.setItem('passeLivre_verify_code', String(data.demo_code));
        console.log('[Cadastro] Código OTP gerado. Aguardando verificação de e-mail.');
      } else {
        showToast(data.error || 'Falha ao realizar cadastro.', 'error');
        emailEl?.focus();
        if (data.code === 'email_ja_cadastrado') {
          // Nenhum código foi enviado nem contador iniciado: o login já está no rodapé do formulário.
          document.querySelector('.cadastro-login-link')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
        return;
      }
    } catch (err) {
      showToast('Erro de conexão. Verifique sua internet e tente novamente.', 'error');
      return;
    }

    sessionStorage.setItem('passeLivre_verify_email', email);
    sessionStorage.setItem('passeLivre_verify_flow', 'cadastro');

    // Redireciona imediatamente para a tela de verificação sem delay desnecessário
    window.location.href = `verificar-codigo.php?email=${encodeURIComponent(email)}&flow=cadastro`;
  });
});