/**
 * Passe Livre Suzano — Modal de relato cidadão com localização
 * Endereço manual, consulta de CEP (ViaCEP via proxy interno), GPS e mapa Leaflet.
 */
(function () {
  'use strict';

  /* Visão do estado de São Paulo: o ponto exato vem do CEP, do endereço, do GPS
     ou do clique no mapa. Abrir em Suzano deslocaria o relato de outra cidade. */
  const VISAO_ESTADO = { lat: -22.0, lng: -48.0, zoom: 7 };
  const OSM_URL = 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png';
  const OSM_ATTRIBUTION = '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>';
  const REVERSE_MIN_INTERVAL = 3500;

  const UF_BY_STATE = {
    'acre': 'AC', 'alagoas': 'AL', 'amapá': 'AP', 'amapa': 'AP', 'amazonas': 'AM',
    'bahia': 'BA', 'ceará': 'CE', 'ceara': 'CE', 'distrito federal': 'DF',
    'espírito santo': 'ES', 'espirito santo': 'ES', 'goiás': 'GO', 'goias': 'GO',
    'maranhão': 'MA', 'maranhao': 'MA', 'mato grosso': 'MT', 'mato grosso do sul': 'MS',
    'minas gerais': 'MG', 'pará': 'PA', 'para': 'PA', 'paraíba': 'PB', 'paraiba': 'PB',
    'paraná': 'PR', 'parana': 'PR', 'pernambuco': 'PE', 'piauí': 'PI', 'piaui': 'PI',
    'rio de janeiro': 'RJ', 'rio grande do norte': 'RN', 'rio grande do sul': 'RS',
    'rondônia': 'RO', 'rondonia': 'RO', 'roraima': 'RR', 'santa catarina': 'SC',
    'são paulo': 'SP', 'sao paulo': 'SP', 'sergipe': 'SE', 'tocantins': 'TO'
  };

  const TYPE_KEYS = {
    'Barreira Arquitetônica': 'barreira',
    'Nova Melhoria': 'melhoria',
    'Semáforo Sonoro Inoperante': 'semaforo',
    'Elevador em Manutenção': 'elevador',
    'Piso Podotátil Obstruído': 'piso'
  };

  const el = (id) => document.getElementById(id);

  const refs = {
    openBtn: el('btn-abrir-relato'),
    modal: el('modal-relato'),
    closeBtn: el('modal-close'),
    cancelBtn: el('btn-cancelar-relato'),
    form: el('form-relato'),
    tipo: el('relato-tipo'),
    endereco: el('relato-endereco'),
    cep: el('relato-cep'),
    bairro: el('relato-bairro'),
    cidade: el('relato-cidade'),
    estado: el('relato-estado'),
    buscarCep: el('btn-buscar-cep'),
    gpsBtn: el('btn-usar-gps'),
    mapBtn: el('btn-abrir-mapa'),
    status: el('relato-status'),
    summary: el('relato-location-summary'),
    summaryTitle: el('relato-location-summary-title'),
    summaryAddress: el('relato-location-address'),
    summaryCoords: el('relato-location-coords'),
    confirmBtn: el('btn-confirmar-localizacao'),
    mapWrap: el('relato-map-wrap'),
    mapEl: el('relato-map'),
    centerBtn: el('btn-mapa-centralizar'),
    latInput: el('relato-latitude'),
    lngInput: el('relato-longitude'),
    descricao: el('relato-descricao'),
    submitBtn: el('btn-confirmar-relato'),
    toast: el('app-toast'),
    toastMsg: el('toast-message'),
    searchInput: el('global-search-input')
  };

  if (!refs.modal || !refs.form) return;

  const state = {
    lat: null,
    lng: null,
    accuracy: null,
    source: null,
    confirmed: false,
    gpsLoading: false,
    submitting: false,
    cepTimer: null,
    cepController: null,
    cepLastQuery: '',
    reverseTimer: null,
    reverseController: null,
    reverseLastAt: 0,
    reverseSeq: 0,
    auto: { address: null, bairro: null, cidade: null, estado: null, cep: null }
  };

  let map = null;
  let marker = null;
  let accuracyCircle = null;
  let toastTimer = null;
  let lastFocus = null;

  // -------------------------------------------------------------------------
  // Modal
  // -------------------------------------------------------------------------
  function openModal() {
    lastFocus = document.activeElement;
    refs.modal.classList.add('active');
    document.body.style.overflow = 'hidden';
    window.setTimeout(() => {
      if (refs.tipo) refs.tipo.focus();
    }, 60);
  }

  function closeModal() {
    refs.modal.classList.remove('active');
    document.body.style.overflow = '';
    if (lastFocus && typeof lastFocus.focus === 'function') lastFocus.focus();
  }

  refs.openBtn?.addEventListener('click', openModal);
  refs.closeBtn?.addEventListener('click', closeModal);
  refs.cancelBtn?.addEventListener('click', closeModal);
  refs.modal.addEventListener('click', (event) => {
    if (event.target === refs.modal) closeModal();
  });
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && refs.modal.classList.contains('active')) closeModal();
  });

  // -------------------------------------------------------------------------
  // Mensagens e campos
  // -------------------------------------------------------------------------
  function setStatus(message, kind) {
    if (!refs.status) return;
    refs.status.textContent = message || '';
    refs.status.className = 'relato-location-status' + (kind ? ' is-' + kind : '');
    refs.status.hidden = !message;
  }

  function showToast(message) {
    if (!refs.toast) return;
    if (refs.toastMsg) refs.toastMsg.textContent = message;
    refs.toast.style.display = 'flex';
    if (toastTimer) clearTimeout(toastTimer);
    toastTimer = window.setTimeout(() => {
      refs.toast.style.display = 'none';
    }, 4200);
  }

  function digitsOnly(value) {
    return String(value || '').replace(/\D+/g, '');
  }

  function formatCep(value) {
    const digits = digitsOnly(value).slice(0, 8);
    return digits.length > 5 ? digits.slice(0, 5) + '-' + digits.slice(5) : digits;
  }

  function toUf(value) {
    const raw = String(value || '').trim();
    if (!raw) return '';
    if (/^[A-Za-z]{2}$/.test(raw)) return raw.toUpperCase();
    return UF_BY_STATE[raw.toLowerCase()] || '';
  }

  function fillIfEditable(input, key, value) {
    const clean = String(value || '').trim();
    if (!input || !clean) return;
    const current = input.value.trim();
    if (current === '' || current === state.auto[key]) {
      input.value = clean;
      state.auto[key] = clean;
    }
  }

  function bindManualField(input, key) {
    if (!input) return;
    input.addEventListener('input', () => {
      state.auto[key] = null;
    });
  }

  bindManualField(refs.endereco, 'address');
  bindManualField(refs.bairro, 'bairro');
  bindManualField(refs.cidade, 'cidade');
  bindManualField(refs.estado, 'estado');

  refs.cep?.addEventListener('input', () => {
    refs.cep.value = formatCep(refs.cep.value);
    state.auto.cep = null;
    window.clearTimeout(state.cepTimer);
    if (digitsOnly(refs.cep.value).length === 8) {
      state.cepTimer = window.setTimeout(() => lookupCep(false), 700);
    }
  });

  refs.cep?.addEventListener('blur', () => {
    if (digitsOnly(refs.cep.value).length === 8) lookupCep(true);
  });

  refs.estado?.addEventListener('blur', () => {
    refs.estado.value = toUf(refs.estado.value);
  });

  // -------------------------------------------------------------------------
  // CEP (ViaCEP via api/cep.php)
  // -------------------------------------------------------------------------
  async function lookupCep(force) {
    const digits = digitsOnly(refs.cep?.value);
    if (digits.length !== 8) {
      if (force && digits.length > 0) {
        setStatus('Informe um CEP com 8 dígitos ou deixe o campo em branco.', 'error');
      }
      return;
    }
    if (!force && state.cepLastQuery === digits) return;

    if (state.cepController) state.cepController.abort();
    state.cepController = new AbortController();
    state.cepLastQuery = digits;

    const btn = refs.buscarCep;
    const restore = setButtonLoading(btn, true, 'Buscando...');
    setStatus('Buscando CEP...', 'loading');

    const timeout = window.setTimeout(() => state.cepController.abort(), 9000);

    try {
      const response = await fetch('api/cep.php?cep=' + encodeURIComponent(digits), {
        headers: { Accept: 'application/json' },
        signal: state.cepController.signal
      });
      const data = await response.json().catch(() => ({}));

      if (!response.ok || !data.success) {
        if (data.not_found) {
          setStatus(data.error || 'CEP não encontrado. Preencha o endereço manualmente.', 'error');
        } else {
          setStatus(data.error || 'Não foi possível consultar o CEP agora. Preencha o endereço manualmente.', 'error');
        }
        return;
      }

      fillIfEditable(refs.endereco, 'address', data.street || '');
      fillIfEditable(refs.bairro, 'bairro', data.suburb || '');
      fillIfEditable(refs.cidade, 'cidade', data.city || '');
      fillIfEditable(refs.estado, 'estado', data.state || '');
      fillIfEditable(refs.cep, 'cep', data.cep || formatCep(digits));

      const cityUf = [data.city, data.state].filter(Boolean).join('/');
      setStatus(
        'CEP encontrado' + (cityUf ? ' — ' + cityUf : '') +
        '. O CEP identifica a rua; use o mapa para marcar o ponto exato.',
        'success'
      );
      renderSummary();
    } catch (error) {
      if (error.name !== 'AbortError') {
        setStatus('Não foi possível consultar o CEP agora. Preencha o endereço manualmente.', 'error');
      }
    } finally {
      window.clearTimeout(timeout);
      restore();
    }
  }

  refs.buscarCep?.addEventListener('click', () => lookupCep(true));

  // -------------------------------------------------------------------------
  // Mapa
  // -------------------------------------------------------------------------
  function createMarkerIcon() {
    return L.divIcon({
      className: 'pl-loc-div-icon',
      html: '<svg xmlns="http://www.w3.org/2000/svg" width="36" height="46" viewBox="0 0 36 46" aria-hidden="true">'
        + '<path d="M18 44C18 44 33 27.4 33 15.5A15 15 0 1 0 3 15.5C3 27.4 18 44 18 44Z" fill="#d97706" stroke="#ffffff" stroke-width="2.5"/>'
        + '<circle cx="18" cy="15.5" r="5.5" fill="#ffffff"/>'
        + '</svg>',
      iconSize: [36, 46],
      iconAnchor: [18, 44]
    });
  }

  function ensureMap() {
    if (map) return map;
    if (typeof L === 'undefined') {
      setStatus('O mapa não pôde ser carregado. Informe o endereço ou o CEP.', 'error');
      return null;
    }
    map = L.map(refs.mapEl, { scrollWheelZoom: false, minZoom: 5 }).setView(
      [VISAO_ESTADO.lat, VISAO_ESTADO.lng],
      VISAO_ESTADO.zoom
    );
    L.tileLayer(OSM_URL, { maxZoom: 19, attribution: OSM_ATTRIBUTION }).addTo(map);

    map.on('click', (event) => {
      const { lat, lng } = event.latlng;
      setPoint(lat, lng, { source: 'manual' });
      setStatus('Ponto ajustado no mapa. Confira o endereço e confirme a localização.', 'info');
    });
    return map;
  }

  /* Posição real mais recente que o app conhece (GPS da busca), ou nenhuma. */
  function ultimaPosicao() {
    const lat = parseFloat(sessionStorage.getItem('passeLivre_gps_lat'));
    const lng = parseFloat(sessionStorage.getItem('passeLivre_gps_lng'));
    if (Number.isFinite(lat) && Number.isFinite(lng) && lat >= -35 && lat <= 6 && lng >= -75 && lng <= -30) {
      return { lat, lng, zoom: 14 };
    }
    return VISAO_ESTADO;
  }

  function openMap() {
    if (!refs.mapWrap) return;
    refs.mapWrap.hidden = false;
    refs.mapBtn?.setAttribute('aria-expanded', 'true');

    const instance = ensureMap();
    if (!instance) return;

    window.requestAnimationFrame(() => {
      instance.invalidateSize();
      const p = state.lat !== null ? { lat: state.lat, lng: state.lng, zoom: Math.max(instance.getZoom(), 16) } : ultimaPosicao();
      instance.setView([p.lat, p.lng], p.zoom);
    });

    refs.mapWrap.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }

  function closeMap() {
    if (!refs.mapWrap) return;
    refs.mapWrap.hidden = true;
    refs.mapBtn?.setAttribute('aria-expanded', 'false');
  }

  refs.mapBtn?.addEventListener('click', () => {
    if (refs.mapWrap?.hidden) {
      openMap();
    } else {
      closeMap();
    }
  });

  refs.centerBtn?.addEventListener('click', () => {
    if (!map) return;
    const p = state.lat !== null ? { lat: state.lat, lng: state.lng, zoom: Math.max(map.getZoom(), 16) } : ultimaPosicao();
    map.setView([p.lat, p.lng], p.zoom);
  });

  function updateMarker(skipMove) {
    if (!map || state.lat === null) return;

    if (!marker) {
      marker = L.marker([state.lat, state.lng], {
        draggable: true,
        icon: createMarkerIcon(),
        keyboard: false,
        title: 'Ponto do ocorrido'
      }).addTo(map);

      marker.on('dragstart', () => {
        setStatus('Arraste o marcador até o ponto exato do ocorrido.', 'info');
      });
      marker.on('dragend', () => {
        const point = marker.getLatLng();
        setPoint(point.lat, point.lng, { source: 'manual', skipMarkerMove: true });
        setStatus('Ponto ajustado no mapa. Confira o endereço e confirme a localização.', 'info');
      });
    } else if (!skipMove) {
      marker.setLatLng([state.lat, state.lng]);
    }

    if (state.accuracy && state.source === 'gps') {
      if (!accuracyCircle) {
        accuracyCircle = L.circle([state.lat, state.lng], {
          radius: state.accuracy,
          color: '#ea580c',
          weight: 1,
          fillColor: '#f59e0b',
          fillOpacity: 0.12
        }).addTo(map);
      } else {
        accuracyCircle.setLatLng([state.lat, state.lng]);
        accuracyCircle.setRadius(state.accuracy);
      }
    } else if (accuracyCircle) {
      accuracyCircle.remove();
      accuracyCircle = null;
    }
  }

  // -------------------------------------------------------------------------
  // Ponto selecionado (GPS ou mapa)
  // -------------------------------------------------------------------------
  function setPoint(lat, lng, options) {
    const opts = options || {};
    if (!Number.isFinite(lat) || !Number.isFinite(lng)) return;

    state.lat = Math.round(lat * 1000000) / 1000000;
    state.lng = Math.round(lng * 1000000) / 1000000;
    state.accuracy = Number.isFinite(opts.accuracy) ? Math.round(opts.accuracy) : null;
    state.source = opts.source || 'manual';
    state.confirmed = false;

    if (refs.latInput) refs.latInput.value = '';
    if (refs.lngInput) refs.lngInput.value = '';

    refs.summary.hidden = false;
    if (refs.confirmBtn) {
      refs.confirmBtn.disabled = false;
      refs.confirmBtn.textContent = 'Confirmar localização';
    }

    if (opts.openMap) openMap();
    if (!opts.skipMarkerMove) updateMarker(false);
    else updateMarker(true);

    renderSummary();
    scheduleReverseGeocode(state.lat, state.lng);
  }

  function renderSummary() {
    if (!refs.summary || state.lat === null) return;

    const addressText = refs.endereco?.value.trim();
    const uf = refs.estado?.value.trim();
    const cityUf = [refs.bairro?.value.trim(), refs.cidade?.value.trim()]
      .filter(Boolean)
      .join(', ');
    const placeText = cityUf + (cityUf && uf ? ' - ' + uf : (!cityUf && uf ? uf : ''));

    refs.summaryAddress.textContent = addressText
      || (placeText || 'Endereço aproximado não disponível');

    refs.summaryCoords.textContent =
      'Lat ' + state.lat.toFixed(6) + ' · Lng ' + state.lng.toFixed(6) +
      (state.accuracy ? ' · precisão ±' + state.accuracy + ' m' : '');

    if (refs.summaryTitle) {
      refs.summaryTitle.textContent = state.confirmed
        ? 'Localização confirmada'
        : 'Localização identificada';
    }
  }

  function confirmPoint() {
    if (state.lat === null) return;
    state.confirmed = true;
    if (refs.latInput) refs.latInput.value = state.lat.toFixed(6);
    if (refs.lngInput) refs.lngInput.value = state.lng.toFixed(6);
    if (refs.confirmBtn) {
      refs.confirmBtn.disabled = true;
      refs.confirmBtn.textContent = 'Localização confirmada';
    }
    renderSummary();
    setStatus('Localização confirmada! Agora descreva o problema e envie o relato.', 'success');
  }

  refs.confirmBtn?.addEventListener('click', confirmPoint);

  // -------------------------------------------------------------------------
  // GPS
  // -------------------------------------------------------------------------
  function setButtonLoading(button, loading, loadingLabel) {
    if (!button) return () => {};
    const label = button.querySelector('span') || button;
    const original = label.textContent;
    button.disabled = loading;
    button.classList.toggle('is-loading', loading);
    button.setAttribute('aria-busy', loading ? 'true' : 'false');
    if (loading && loadingLabel) label.textContent = loadingLabel;
    return () => {
      button.disabled = false;
      button.classList.remove('is-loading');
      button.setAttribute('aria-busy', 'false');
      label.textContent = original;
    };
  }

  function requestGeolocation() {
    if (state.gpsLoading) return;

    if (!('geolocation' in navigator) || !navigator.geolocation
        || typeof navigator.geolocation.getCurrentPosition !== 'function') {
      setStatus('Seu navegador ou dispositivo não oferece localização. Informe o endereço ou o CEP.', 'error');
      return;
    }

    if (!window.isSecureContext) {
      setStatus('A localização precisa de uma conexão segura (HTTPS). Informe o endereço, o CEP ou use o mapa.', 'error');
      return;
    }

    state.gpsLoading = true;
    const restore = setButtonLoading(refs.gpsBtn, true, 'Buscando localização...');
    setStatus('Buscando sua localização...', 'loading');

    let settled = false;
    const finish = () => {
      settled = true;
      window.clearTimeout(watchdog);
      state.gpsLoading = false;
      restore();
    };
    const watchdog = window.setTimeout(() => {
      if (settled) return;
      finish();
      setStatus('Não foi possível obter sua localização a tempo. Você pode informar o endereço ou o CEP.', 'error');
    }, 25000);

    navigator.geolocation.getCurrentPosition(
      (position) => {
        if (settled) return;
        finish();
        const coords = position.coords || {};
        if (!Number.isFinite(coords.latitude) || !Number.isFinite(coords.longitude)) {
          setStatus('Não foi possível obter sua localização. Informe o endereço ou o CEP.', 'error');
          return;
        }
        setPoint(coords.latitude, coords.longitude, {
          source: 'gps',
          accuracy: Number.isFinite(coords.accuracy) ? coords.accuracy : null,
          openMap: true
        });
        setStatus('Localização encontrada! Confira o ponto no mapa e clique em "Confirmar localização".', 'success');
      },
      (error) => {
        if (settled) return;
        finish();
        let message = 'Não foi possível obter sua localização. Você pode informar o endereço ou o CEP manualmente.';
        if (error.code === 1) {
          message = 'Permissão de localização negada. Você pode informar o endereço, o CEP ou escolher o ponto no mapa.';
        } else if (error.code === 2) {
          message = 'Localização indisponível no momento. Verifique o GPS do aparelho ou informe o endereço/CEP.';
        } else if (error.code === 3) {
          message = 'O GPS demorou para responder. Tente novamente ou informe o endereço/CEP.';
        }
        setStatus(message, 'error');
      },
      { enableHighAccuracy: true, timeout: 12000, maximumAge: 60000 }
    );
  }

  refs.gpsBtn?.addEventListener('click', requestGeolocation);

  // -------------------------------------------------------------------------
  // Reverse geocoding (proxy interno, com debounce e intervalo mínimo)
  // -------------------------------------------------------------------------
  function scheduleReverseGeocode(lat, lng) {
    window.clearTimeout(state.reverseTimer);
    const elapsed = Date.now() - state.reverseLastAt;
    const wait = Math.max(450, REVERSE_MIN_INTERVAL - elapsed);
    state.reverseTimer = window.setTimeout(() => runReverseGeocode(lat, lng), wait);
  }

  async function runReverseGeocode(lat, lng) {
    if (state.reverseController) state.reverseController.abort();
    state.reverseController = new AbortController();
    const controller = state.reverseController;
    const seq = ++state.reverseSeq;

    const timeout = window.setTimeout(() => controller.abort(), 9000);
    setStatus('Buscando o endereço aproximado do ponto...', 'loading');

    try {
      const response = await fetch(
        'api/geocode.php?action=reverse&lat=' + encodeURIComponent(lat) + '&lng=' + encodeURIComponent(lng),
        { headers: { Accept: 'application/json' }, signal: controller.signal }
      );
      const data = await response.json().catch(() => ({}));

      if (seq !== state.reverseSeq) return;
      state.reverseLastAt = Date.now();

      const item = Array.isArray(data.results) ? data.results[0] : null;
      if (!response.ok || !data.success || !item) {
        setStatus('Localização obtida, mas não foi possível encontrar o endereço aproximado. Preencha o endereço manualmente.', 'warning');
        return;
      }

      const parts = item.parts || {};
      const street = [parts.road, parts.house_number].filter(Boolean).join(', ');
      fillIfEditable(refs.endereco, 'address', street || item.name || '');
      fillIfEditable(refs.bairro, 'bairro', parts.suburb || '');
      fillIfEditable(refs.cidade, 'cidade', parts.city || '');
      fillIfEditable(refs.estado, 'estado', toUf(parts.state));
      fillIfEditable(refs.cep, 'cep', parts.postcode ? formatCep(parts.postcode) : '');

      renderSummary();
      setStatus('Localização encontrada! Confira o endereço e o ponto no mapa; ajuste se precisar e confirme.', 'success');
    } catch (error) {
      if (error.name !== 'AbortError' && seq === state.reverseSeq) {
        setStatus('Não foi possível obter o endereço aproximado. Você pode preenchê-lo manualmente.', 'warning');
      }
    } finally {
      window.clearTimeout(timeout);
    }
  }

  // -------------------------------------------------------------------------
  // Envio do relato
  // -------------------------------------------------------------------------
  function resetLocationState() {
    state.lat = null;
    state.lng = null;
    state.accuracy = null;
    state.source = null;
    state.confirmed = false;
    state.cepLastQuery = '';
    state.auto = { address: null, bairro: null, cidade: null, estado: null, cep: null };

    if (refs.latInput) refs.latInput.value = '';
    if (refs.lngInput) refs.lngInput.value = '';
    if (refs.summary) refs.summary.hidden = true;
    if (refs.confirmBtn) {
      refs.confirmBtn.disabled = true;
      refs.confirmBtn.textContent = 'Confirmar localização';
    }
    setStatus('', '');
    closeMap();

    if (marker) {
      marker.remove();
      marker = null;
    }
    if (accuracyCircle) {
      accuracyCircle.remove();
      accuracyCircle = null;
    }
  }

  function composeAddress(hasCep) {
    const address = refs.endereco?.value.trim() || '';
    if (address) return address;

    const parts = [refs.bairro?.value.trim(), refs.cidade?.value.trim()].filter(Boolean);
    if (parts.length) {
      const uf = refs.estado?.value.trim();
      return parts.join(', ') + (uf ? ' - ' + uf : '');
    }
    if (hasCep) return 'CEP ' + formatCep(refs.cep.value);
    return 'Coordenadas ' + state.lat.toFixed(6) + ', ' + state.lng.toFixed(6);
  }

  refs.form.addEventListener('submit', async (event) => {
    event.preventDefault();
    if (state.submitting) return;

    const tipo = refs.tipo?.value || '';
    const descricao = refs.descricao?.value.trim() || '';
    const cepDigits = digitsOnly(refs.cep?.value);
    const hasCep = cepDigits.length === 8;
    const hasCoords = state.lat !== null;
    const estado = toUf(refs.estado?.value);
    const address = refs.endereco?.value.trim() || '';

    if (!tipo || !descricao) return;

    if (cepDigits.length > 0 && !hasCep) {
      setStatus('Informe um CEP com 8 dígitos ou deixe o campo em branco.', 'error');
      refs.cep?.focus();
      return;
    }

    if (!address && !hasCep && !hasCoords) {
      setStatus('Informe o endereço, o CEP ou use sua localização atual antes de enviar.', 'error');
      refs.endereco?.focus();
      return;
    }

    if (estado === '' && (refs.estado?.value.trim() || '')) {
      setStatus('Informe a UF com 2 letras (ex.: SP).', 'error');
      refs.estado?.focus();
      return;
    }

    if (hasCoords && !state.confirmed) {
      openMap();
      setStatus('Confirme a localização escolhida no mapa antes de enviar o relato.', 'error');
      refs.confirmBtn?.focus();
      return;
    }

    const enderecoFinal = composeAddress(hasCep);
    const user = window.PasseLivreAuth && typeof window.PasseLivreAuth.getUser === 'function'
      ? window.PasseLivreAuth.getUser()
      : null;

    const payload = {
      citizen_name: (user && user.name) ? user.name : 'Cidadão Anônimo',
      type_key: TYPE_KEYS[tipo] || 'outro',
      type_name: tipo,
      title: (tipo + ': ' + enderecoFinal).slice(0, 200),
      address: enderecoFinal,
      cep: hasCep ? cepDigits : '',
      bairro: refs.bairro?.value.trim() || '',
      cidade: refs.cidade?.value.trim() || '',
      estado: estado,
      latitude: hasCoords ? Number(state.lat.toFixed(6)) : null,
      longitude: hasCoords ? Number(state.lng.toFixed(6)) : null,
      description: descricao,
      priority: 'media'
    };

    state.submitting = true;
    if (refs.submitBtn) {
      refs.submitBtn.disabled = true;
      refs.submitBtn.setAttribute('aria-busy', 'true');
      refs.submitBtn.textContent = 'Enviando...';
    }
    setStatus('Enviando relato...', 'loading');

    try {
      const response = await fetch('api/solicitacoes.php?action=create', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify(payload)
      });
      const data = await response.json().catch(() => ({}));

      if (!response.ok || !data.success) {
        throw new Error(data.error || 'Não foi possível enviar o relato. Tente novamente.');
      }

      refs.form.reset();
      resetLocationState();
      closeModal();
      showToast('Relato enviado para auditoria do SENAI com sucesso!');
    } catch (error) {
      setStatus(
        error.message || 'Não foi possível enviar o relato agora. Verifique sua conexão e tente novamente.',
        'error'
      );
    } finally {
      state.submitting = false;
      if (refs.submitBtn) {
        refs.submitBtn.disabled = false;
        refs.submitBtn.setAttribute('aria-busy', 'false');
        refs.submitBtn.textContent = 'Enviar para Auditoria';
      }
    }
  });

  // -------------------------------------------------------------------------
  // Busca do cabeçalho (comportamento existente)
  // -------------------------------------------------------------------------
  if (refs.searchInput) {
    refs.searchInput.addEventListener('keydown', (event) => {
      const term = refs.searchInput.value.trim();
      if (event.key === 'Enter' && term !== '') {
        window.location.href = 'lugares-encontrados.php?q=' + encodeURIComponent(term);
      }
    });
  }
})();
