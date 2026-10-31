/**
 * Passe Livre Suzano — Detalhes do Local
 *
 * O PHP desta página já consulta o MySQL via PDO e renderiza o cadastro. O JS
 * não refaz essa leitura por PLDB/sql_bridge: faria a tela depender de duas
 * fontes para o mesmo registro. Aqui o JS cuida do mapa, das fotos e do reporte.
 */

document.addEventListener('DOMContentLoaded', () => {

  const form = document.getElementById('form-report-problem');

  // =========================================================================
  // 1. MAPA DO DESTINO — só com lat/lng reais do cadastro
  // =========================================================================
  const mapEl = document.getElementById('place-map');
  if (mapEl && typeof L !== 'undefined') {
    const lat = parseFloat(mapEl.dataset.lat);
    const lng = parseFloat(mapEl.dataset.lng);
    const nome = mapEl.dataset.nome || 'Local pesquisado';

    if (Number.isFinite(lat) && Number.isFinite(lng)) {
      try {
        const map = L.map(mapEl, {
          center: [lat, lng],
          zoom: 16,
          scrollWheelZoom: false,
          keyboard: true
        });

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
          maxZoom: 19,
          attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(map);

        L.marker([lat, lng]).addTo(map).bindPopup(nome).openPopup();

        // Os controles nativos do Leaflet ganham rótulos em português para quem
        // navega por teclado ou leitor de tela.
        const zoomIn = mapEl.querySelector('.leaflet-control-zoom-in');
        const zoomOut = mapEl.querySelector('.leaflet-control-zoom-out');
        if (zoomIn) zoomIn.setAttribute('aria-label', 'Aproximar o mapa');
        if (zoomOut) zoomOut.setAttribute('aria-label', 'Afastar o mapa');

        // O mapa é acessível também sem mouse: ao focar, habilita zoom por teclado.
        mapEl.addEventListener('focusin', () => map.keyboard.enable());
        mapEl.addEventListener('keydown', (e) => {
          if (e.key === '+' || e.key === '=') { e.preventDefault(); map.zoomIn(); }
          if (e.key === '-' || e.key === '_') { e.preventDefault(); map.zoomOut(); }
        });
      } catch (err) {
        console.error('[detalhes-local] falha ao desenhar o mapa:', err);
        mapEl.outerHTML = '<div class="place-map-fallback" role="alert"><strong>Não foi possível carregar o mapa.</strong><p>As coordenadas do cadastro continuam válidas: ' + lat.toFixed(6) + ', ' + lng.toFixed(6) + '</p></div>';
      }
    }
  }

  // =========================================================================
  // 2. FOTOS DAS AVALIAÇÕES
  // =========================================================================
  const lightbox = document.getElementById('photo-lightbox');
  const lbImg = document.getElementById('photo-lightbox-img');
  const lbCaption = document.getElementById('photo-lightbox-caption');
  let ultimoFoco = null;

  document.querySelectorAll('.review-photo-thumb').forEach(btn => {
    btn.addEventListener('click', () => {
      const url = btn.dataset.full || '';
      // Revalida no cliente: um valor fora do formato gravado por api/upload.php
      // não vira src de imagem.
      if (!/^uploads\/[0-9]{8}-[0-9a-f]{24}\.(?:jpg|jpeg|png|webp)$/.test(url)) {
        showToast('A foto desta avaliação não está disponível.', 'error');
        return;
      }
      ultimoFoco = btn;
      lbImg.src = url;
      lbImg.alt = btn.dataset.alt || 'Foto enviada na avaliação';
      lbCaption.textContent = btn.dataset.alt || '';
      if (lightbox.showModal) lightbox.showModal();
      else lightbox.setAttribute('open', '');
      document.getElementById('photo-lightbox-close')?.focus();
    });
  });

  document.getElementById('photo-lightbox-close')?.addEventListener('click', fecharLightbox);
  lightbox?.addEventListener('click', (e) => { if (e.target === lightbox) fecharLightbox(); });
  lightbox?.addEventListener('close', () => { if (ultimoFoco) ultimoFoco.focus(); });

  lbImg?.addEventListener('error', () => {
    lbImg.alt = 'Imagem indisponível';
    lbCaption.textContent = 'A imagem não pôde ser carregada do armazenamento.';
  });

  function fecharLightbox() {
    if (!lightbox) return;
    if (lightbox.close) lightbox.close();
    else lightbox.removeAttribute('open');
  }

  // =========================================================================
  // 3. REPORTAR BARREIRA — gravação única em api/solicitacoes.php?action=create
  // =========================================================================
  if (form) {
    const btnSend = document.getElementById('btn-send-report');
    const statusEl = document.getElementById('report-status');
    let enviando = false;

    function liberar() {
      enviando = false;
      if (btnSend) { btnSend.disabled = false; btnSend.innerHTML = btnSend.dataset.label || '<span>Enviar Reporte</span>'; }
    }

    function setStatus(msg, tipo) {
      if (!statusEl) return;
      statusEl.textContent = msg;
      statusEl.dataset.tipo = tipo || '';
    }

    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      if (enviando) return;

      const descricao = document.getElementById('report-description')?.value.trim() || '';
      if (descricao.length < 10) {
        setStatus('Descreva o problema com pelo menos 10 caracteres para o time de triagem.', 'error');
        document.getElementById('report-description')?.focus();
        return;
      }

      const typeEl = document.getElementById('report-type');
      const lat = form.dataset.lat ? parseFloat(form.dataset.lat) : null;
      const lng = form.dataset.lng ? parseFloat(form.dataset.lng) : null;
      const user = window.PasseLivreAuth?.getUser?.();
      const bairro = form.dataset.bairro || '';
      const cidade = form.dataset.cidade || '';

      const payload = {
        citizen_name: user?.name || 'Cidadão Anônimo',
        citizen_email: user?.email || '',
        type_key: typeEl?.value || 'outro',
        type_name: typeEl && typeEl.selectedIndex >= 0 ? typeEl.options[typeEl.selectedIndex].text : 'Outro obstáculo',
        title: `Barreira em ${form.dataset.nome || 'local sem nome'}`,
        address: form.dataset.endereco || '',
        bairro: bairro,
        cidade: cidade,
        estado: form.dataset.uf || '',
        latitude: Number.isFinite(lat) ? lat : null,
        longitude: Number.isFinite(lng) ? lng : null,
        region: _slug(bairro || cidade),
        region_name: bairro || cidade || 'Não informada',
        description: descricao,
        priority: 'media'
      };

      enviando = true;
      setStatus('Registrando o reporte no banco de dados…', 'info');
      if (btnSend) { btnSend.disabled = true; btnSend.dataset.label = btnSend.innerHTML; btnSend.innerHTML = '<span>Enviando…</span>'; }

      let data = null;
      let res = null;
      try {
        res = await fetch('api/solicitacoes.php?action=create', {
          method: 'POST',
          credentials: 'same-origin',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload)
        });
        data = await res.json().catch(() => null);
      } catch (err) {
        console.error('[detalhes-local] reporte sem resposta do servidor:', err);
        setStatus('O reporte não foi gravado: o servidor não respondeu. Seu texto continua preenchido — tente novamente.', 'error');
        liberar();
        return;
      }

      if (!res.ok || !data || data.success !== true) {
        const motivo = data && data.error ? data.error : `falha no servidor (HTTP ${res ? res.status : 'sem resposta'}).`;
        if (res && (res.status === 401 || res.status === 403)) {
          setStatus('Faça login para registrar o reporte. Nada foi enviado.', 'error');
        } else {
          setStatus('O reporte não foi gravado: ' + motivo, 'error');
        }
        liberar();
        return;
      }

      const protocolo = data.protocol || '';
      document.getElementById('report-protocol').textContent = protocolo || 'não informado';
      document.getElementById('modal-report-problem')?.classList.remove('active');
      document.getElementById('modal-report-success')?.classList.add('active');
      document.getElementById('modal-report-success')?.querySelector('.btn-report-success-close')?.focus();
      form.reset();
      setStatus('', 'info');
      showToast(`Reporte registrado${protocolo ? ' sob o protocolo ' + protocolo : ''}.`, 'success');
      liberar();
    });
  }

  document.querySelectorAll('.btn-report-cancel').forEach(b => {
    b.addEventListener('click', () => document.getElementById('modal-report-problem')?.classList.remove('active'));
  });
  document.querySelector('.btn-report-success-close')?.addEventListener('click', () => {
    document.getElementById('modal-report-success')?.classList.remove('active');
    document.getElementById('btn-report-problem')?.focus();
  });

  document.getElementById('btn-report-problem')?.addEventListener('click', () => {
    document.getElementById('modal-report-problem')?.classList.add('active');
    document.getElementById('report-description')?.focus();
  });

  // =========================================================================
  // 4. BUSCA GLOBAL
  // =========================================================================
  const globalSearchInput = document.getElementById('global-search-input');
  if (globalSearchInput) {
    globalSearchInput.addEventListener('keydown', e => {
      if (e.key === 'Enter' && e.target.value.trim()) {
        window.location.href = `lugares-encontrados.php?q=${encodeURIComponent(e.target.value.trim())}`;
      }
    });
  }

  // =========================================================================
  // 5. DROPDOWN DO PERFIL
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
        userProfileBtn.setAttribute('aria-expanded', 'false');
      }
    });
  }

  document.getElementById('btn-logout')?.addEventListener('click', () => {
    if (window.PasseLivreAuth) window.PasseLivreAuth.clearUser();
    setTimeout(() => window.location.href = 'index.php', 600);
  });

  // =========================================================================
  // UTILITÁRIOS
  // =========================================================================
  function _slug(txt) {
    return String(txt || '')
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .toLowerCase()
      .replace(/[^a-z0-9]+/g, ' ')
      .trim()
      .slice(0, 80);
  }

  function showToast(msg, type) {
    if (typeof window.showToast === 'function') { window.showToast(msg, type); return; }
    const t = document.getElementById('app-toast'), m = document.getElementById('toast-message');
    if (t && m) { m.textContent = msg; t.classList.add('visible'); setTimeout(() => t.classList.remove('visible'), 3600); }
  }

  window.showToast = showToast;
});
