/**
 * Passe Livre — Explorar (explorar.php)
 *
 * Mapa interativo Leaflet/OpenStreetMap + localização:
 *  - GPS real via navigator.geolocation.getCurrentPosition, somente após clique.
 *  - Marcador arrastável e clique no mapa para escolher o ponto.
 *  - Geocodificação reversa pelo proxy interno (api/geocode.php), com debounce.
 *  - Endereço aproximado preenchido no campo de origem, sem inventar dados.
 */

document.addEventListener('DOMContentLoaded', () => {
  const mapElement = document.getElementById('map-background');

  /* Visão do estado de São Paulo: é para onde o mapa volta quando ainda não
     existe nenhuma posição real. Abrir sempre em Suzano faria qualquer outra
     cidade parecer fora da área atendida. */
  const VISAO_ESTADO = { lat: -22.0, lng: -48.0, zoom: 7 };
  const BRAZIL_BOUNDS = [[-33.75, -73.99], [5.27, -28.85]];
  const DASH = '—';
  const SS_LAT = 'passeLivre_gps_lat';
  const SS_LNG = 'passeLivre_gps_lng';
  const SS_ADDRESS = 'passeLivre_gps_address';

  /* Mesma sanidade de includes/geo_centro.php. */
  function parValido(lat, lng) {
    return Number.isFinite(lat) && Number.isFinite(lng)
      && lat >= -35 && lat <= 6 && lng >= -75 && lng <= -30;
  }

  function kmEntre(a, b) {
    const R = 6371, rad = Math.PI / 180;
    const dLat = (b[0] - a[0]) * rad, dLng = (b[1] - a[1]) * rad;
    const h = Math.sin(dLat / 2) ** 2
      + Math.cos(a[0] * rad) * Math.cos(b[0] * rad) * Math.sin(dLng / 2) ** 2;
    return 2 * R * Math.asin(Math.min(1, Math.sqrt(h)));
  }

  /* Última posição real confirmada (GPS ou endereço) manda na visão inicial. */
  function visaoInicial() {
    const lat = parseFloat(sessionStorage.getItem(SS_LAT));
    const lng = parseFloat(sessionStorage.getItem(SS_LNG));
    if (parValido(lat, lng)) return { lat, lng, zoom: 14 };
    return VISAO_ESTADO;
  }

  // =========================================================================
  // BASE DE LOCAIS (para autocomplete)
  // =========================================================================
  const accessiblePlaces = [
    {
      id: 1, name: 'Suzano Shopping', type: 'shopping', category: 'total',
      address: 'R. Sete de Setembro, 555 - Parque Suzano',
      badge: 'Totalmente Acessível',
      features: ['Rampas de acesso', 'Elevadores adaptados', 'Vagas PcD cobertas', 'Sanitários acessíveis']
    },
    {
      id: 2, name: 'Parque Municipal Max Feffer', type: 'park', category: 'total',
      address: 'Av. Senador Roberto Simonsen, 90 - Jd. Imperador',
      badge: 'Totalmente Acessível',
      features: ['Pistas pavimentadas', 'Piso tátil', 'Sanitários adaptados', 'Estacionamento exclusivo']
    },
    {
      id: 3, name: 'Estação CPTM Suzano (Linha 11-Coral)', type: 'transport', category: 'partial',
      address: 'R. Prudente de Moraes, s/n - Centro',
      badge: 'Acessibilidade Parcial',
      features: ['Elevadores para plataformas', 'Piso tátil direcional']
    },
    {
      id: 4, name: 'Hospital Santa Maria de Suzano', type: 'health', category: 'total',
      address: 'R. General Francisco Glicério, 1401 - Centro',
      badge: 'Saúde Acessível',
      features: ['Entrada sem degraus', 'Portas automáticas largas']
    },
    {
      id: 5, name: 'Restaurante & Gastronomia Inclusiva Suzano', type: 'food', category: 'total',
      address: 'R. Benjamin Constant, 820 - Centro',
      badge: 'Totalmente Acessível',
      features: ['Mesas para cadeirantes', 'Cardápio em Braille']
    },
    {
      id: 6, name: 'Teatro Municipal Dr. Armando de Ré', type: 'cinema', category: 'total',
      address: 'R. General Francisco Glicério, 1354 - Centro',
      badge: 'Cultura Acessível',
      features: ['Assentos para PcD', 'Intérprete de Libras em eventos']
    },
    {
      id: 7, name: 'Itaquá Park Shopping', type: 'shopping', category: 'total',
      address: 'Estrada Gov. Mário Covas Júnior, Itaquaquecetuba',
      badge: 'Totalmente Acessível',
      features: ['Infraestrutura completa para mobilidade reduzida']
    }
  ];
  // Exposto para o bloco de integração SQL (segundo DOMContentLoaded), que
  // completa as sugestões com os locais realmente cadastrados, com coordenadas.
  window.PasseLivreSugestoes = accessiblePlaces;

  // =========================================================================
  // TOAST (delega ao global; fallback se auth.js ainda não carregou)
  // =========================================================================
  function showToast(msg, type) {
    if (typeof window.showToast === 'function') {
      window.showToast(msg, type);
    } else {
      const t = document.getElementById('app-toast');
      const m = document.getElementById('toast-message');
      if (t && m) { m.textContent = msg; t.classList.add('visible'); setTimeout(() => t.classList.remove('visible'), 3800); }
    }
  }

  // =========================================================================
  // 1. MAPA INTERATIVO + LOCALIZAÇÃO
  // =========================================================================
  const originInput     = document.getElementById('input-origin');
  const originLatInput  = document.getElementById('input-origin-lat');
  const originLngInput  = document.getElementById('input-origin-lng');
  const originAddrInput = document.getElementById('input-origin-address');
  const locationSummary = document.getElementById('location-summary');
  const locationStatus  = document.getElementById('location-status');
  const summaryTitle    = document.getElementById('location-summary-title');
  const locStreet       = document.getElementById('loc-street');
  const locSuburb       = document.getElementById('loc-suburb');
  const locCity         = document.getElementById('loc-city');
  const locPostcode     = document.getElementById('loc-postcode');
  const locCoords       = document.getElementById('loc-coords');
  const btnConfirm      = document.getElementById('btn-confirm-location');
  const btnAdjust       = document.getElementById('btn-adjust-map');
  const pickerBar       = document.getElementById('map-picker-bar');
  const pickerStatus    = document.getElementById('picker-status');
  const pickerAddress   = document.getElementById('picker-address');
  const pickerCoords    = document.getElementById('picker-coords');
  const btnPickerConfirm = document.getElementById('btn-picker-confirm');
  const btnPickerGps    = document.getElementById('btn-picker-gps');
  const btnPickerClose  = document.getElementById('btn-picker-close');
  const btnGps          = document.getElementById('btn-use-gps');
  const btnZoomIn       = document.getElementById('btn-zoom-in');
  const btnZoomOut      = document.getElementById('btn-zoom-out');
  const btnRecenter     = document.getElementById('btn-recenter-map');

  const locationState = {
    lat: null,
    lng: null,
    accuracy: null,
    source: null,       // 'gps' | 'manual'
    gpsLat: null,
    gpsLng: null,
    gpsAccuracy: null,
    address: null,
    parts: null,
    confirmed: false
  };

  let exploreMap = null;
  let locationMarker = null;
  let accuracyCircle = null;
  let geocodeTimer = null;
  let geocodeSeq = 0;
  let geocodeAbort = null;
  let lastGeocodeAt = 0;
  let gpsPending = false;
  let gpsWatchdog = null;
  let gpsRequestSeq = 0;
  let suppressOriginInput = false;

  function hasPosition() {
    return locationState.lat !== null && locationState.lng !== null && isFinite(locationState.lat) && isFinite(locationState.lng);
  }

  function formatCoord(value) {
    return Number(value).toFixed(6);
  }

  function setPanelMessage(message) {
    if (locationStatus) locationStatus.textContent = message;
    if (pickerStatus) pickerStatus.textContent = message;
  }

  function showSummary(message) {
    if (!locationSummary) return;
    locationSummary.hidden = false;
    if (message) setPanelMessage(message);
  }

  function syncSummary() {
    const parts = locationState.parts || {};
    const street = parts.road
      ? [parts.road, parts.house_number].filter(Boolean).join(', ')
      : DASH;

    if (locStreet)   locStreet.textContent   = street;
    if (locSuburb)   locSuburb.textContent   = parts.suburb || DASH;
    if (locCity)     locCity.textContent     = parts.city
      ? [parts.city, parts.state].filter(Boolean).join(' - ')
      : (parts.state || DASH);
    if (locPostcode) locPostcode.textContent = parts.postcode || DASH;
    if (locCoords) {
      locCoords.textContent = hasPosition()
        ? `${formatCoord(locationState.lat)}, ${formatCoord(locationState.lng)}`
        : DASH;
    }

    if (summaryTitle) {
      summaryTitle.textContent = locationState.confirmed
        ? 'Localização confirmada'
        : (locationState.source === 'gps' ? 'Localização do GPS' : 'Localização escolhida no mapa');
    }

    if (pickerAddress) {
      pickerAddress.textContent = locationState.address
        || (hasPosition() ? `Ponto selecionado (${formatCoord(locationState.lat)}, ${formatCoord(locationState.lng)})` : '');
    }
    if (pickerCoords) {
      pickerCoords.textContent = hasPosition()
        ? `Lat ${formatCoord(locationState.lat)} · Lng ${formatCoord(locationState.lng)}` +
          (locationState.accuracy ? ` · precisão ±${Math.round(locationState.accuracy)} m` : '')
        : '';
    }
  }

  function updateConfirmButtons() {
    const ready = hasPosition() && !locationState.confirmed;
    if (btnConfirm) {
      btnConfirm.disabled = !ready;
      btnConfirm.textContent = locationState.confirmed ? 'Localização confirmada' : 'Confirmar localização';
    }
    if (btnPickerConfirm) {
      btnPickerConfirm.disabled = !ready;
      btnPickerConfirm.textContent = locationState.confirmed ? 'Confirmada' : 'Confirmar';
    }
  }

  function saveSessionLocation() {
    try {
      if (locationState.confirmed && hasPosition()) {
        sessionStorage.setItem(SS_LAT, formatCoord(locationState.lat));
        sessionStorage.setItem(SS_LNG, formatCoord(locationState.lng));
        sessionStorage.setItem(SS_ADDRESS, locationState.address || '');
      } else {
        sessionStorage.removeItem(SS_LAT);
        sessionStorage.removeItem(SS_LNG);
        sessionStorage.removeItem(SS_ADDRESS);
      }
    } catch (e) {
      /* sessionStorage indisponível (modo privado): ignora */
    }
  }

  function invalidateConfirmation() {
    if (!locationState.confirmed) return;
    locationState.confirmed = false;
    if (originLatInput)  originLatInput.value = '';
    if (originLngInput)  originLngInput.value = '';
    if (originAddrInput) originAddrInput.value = '';
    saveSessionLocation();
    updateConfirmButtons();
  }

  function fillOriginFromLocation() {
    if (!originInput) return;
    const text = locationState.address
      || (hasPosition()
        ? `Minha localização (${formatCoord(locationState.lat)}, ${formatCoord(locationState.lng)})`
        : '');
    suppressOriginInput = true;
    originInput.value = text;
    originInput.dispatchEvent(new Event('input', { bubbles: true }));
    originInput.dispatchEvent(new Event('change', { bubbles: true }));
    suppressOriginInput = false;
  }

  function syncConfirmedFields() {
    if (!locationState.confirmed || !hasPosition()) return;
    if (originLatInput)  originLatInput.value = formatCoord(locationState.lat);
    if (originLngInput)  originLngInput.value = formatCoord(locationState.lng);
    if (originAddrInput) originAddrInput.value = locationState.address || '';
    fillOriginFromLocation();
    saveSessionLocation();
  }

  function clearSelectedLocation() {
    cancelGpsRequest();
    clearTimeout(geocodeTimer);
    geocodeSeq++;
    if (geocodeAbort) {
      try { geocodeAbort.abort(); } catch (e) { /* ignora */ }
      geocodeAbort = null;
    }

    locationState.lat = null;
    locationState.lng = null;
    locationState.accuracy = null;
    locationState.source = null;
    locationState.address = null;
    locationState.parts = null;
    locationState.confirmed = false;

    if (originLatInput)  originLatInput.value = '';
    if (originLngInput)  originLngInput.value = '';
    if (originAddrInput) originAddrInput.value = '';

    if (exploreMap) {
      if (locationMarker) { exploreMap.removeLayer(locationMarker); locationMarker = null; }
      if (accuracyCircle) { exploreMap.removeLayer(accuracyCircle); accuracyCircle = null; }
    }

    if (locationSummary) locationSummary.hidden = true;
    updateConfirmButtons();
    saveSessionLocation();
  }

  function createMarkerIcon() {
    return L.divIcon({
      className: 'pl-loc-div-icon',
      html: '<svg xmlns="http://www.w3.org/2000/svg" width="36" height="46" viewBox="0 0 36 46" aria-hidden="true">'
        + '<path d="M18 44C18 44 33 27.4 33 15.5A15 15 0 1 0 3 15.5C3 27.4 18 44 18 44Z" fill="#d97706" stroke="#ffffff" stroke-width="2.5"/>'
        + '<circle cx="18" cy="15.5" r="5.5" fill="#ffffff"/>'
        + '</svg>',
      iconSize: [36, 46],
      iconAnchor: [18, 44],
      popupAnchor: [0, -40]
    });
  }

  function initExploreMap() {
    if (exploreMap || !mapElement) return exploreMap;
    if (typeof L === 'undefined') {
      console.error('[explorar] Leaflet não carregado; o mapa interativo está indisponível.');
      return null;
    }

    const v = visaoInicial();
    exploreMap = L.map(mapElement, {
      center: [v.lat, v.lng],
      zoom: v.zoom,
      zoomControl: false,
      scrollWheelZoom: false, // não prende a rolagem da página
      minZoom: 5,
      maxZoom: 19,
      maxBounds: L.latLngBounds(BRAZIL_BOUNDS[0], BRAZIL_BOUNDS[1]),
      maxBoundsViscosity: 1.0
    });

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a>',
      maxZoom: 19
    }).addTo(exploreMap);

    // Clique no mapa escolhe o ponto (a posição manual tem prioridade).
    exploreMap.on('click', (e) => {
      applyPosition(e.latlng.lat, e.latlng.lng, { source: 'manual', pan: false });
    });

    setTimeout(() => exploreMap.invalidateSize(), 250);
    return exploreMap;
  }

  function applyPosition(lat, lng, options = {}) {
    if (!parValido(lat, lng)) return;

    const source = options.source || locationState.source || 'manual';
    const changed = locationState.lat !== lat || locationState.lng !== lng;
    if (source === 'manual') cancelGpsRequest();
    geocodeSeq++;
    if (geocodeAbort) geocodeAbort.abort();

    if (changed || options.keepAddress !== true) invalidateConfirmation();

    locationState.lat = lat;
    locationState.lng = lng;
    locationState.source = source;

    if ('accuracy' in options) {
      locationState.accuracy = options.accuracy;
    } else if (source === 'manual') {
      locationState.accuracy = null;
    }

    if (options.keepAddress !== true) {
      locationState.address = null;
      locationState.parts = null;
    }

    showSummary();
    syncSummary();
    updateConfirmButtons();

    if (!exploreMap) initExploreMap();

    if (exploreMap) {
      const latlng = L.latLng(lat, lng);

      if (!locationMarker) {
        locationMarker = L.marker(latlng, {
          icon: createMarkerIcon(),
          draggable: true,
          autoPan: true,
          keyboard: true,
          title: 'Sua localização',
          alt: 'Marcador arrastável da sua localização'
        }).addTo(exploreMap);

        locationMarker.on('dragstart', () => {
          setPanelMessage('Arraste o marcador e solte no ponto exato.');
        });

        locationMarker.on('dragend', () => {
          const p = locationMarker.getLatLng();
          applyPosition(p.lat, p.lng, { source: 'manual', pan: false, skipMarkerMove: true });
        });
      } else if (!options.skipMarkerMove) {
        locationMarker.setLatLng(latlng);
      }

      if (locationState.accuracy && locationState.accuracy > 0) {
        if (!accuracyCircle) {
          accuracyCircle = L.circle(latlng, {
            radius: locationState.accuracy,
            color: '#2563eb',
            weight: 1,
            opacity: 0.6,
            fillColor: '#3b82f6',
            fillOpacity: 0.12,
            interactive: false
          }).addTo(exploreMap);
        } else {
          accuracyCircle.setLatLng(latlng).setRadius(locationState.accuracy);
        }
      } else if (accuracyCircle) {
        exploreMap.removeLayer(accuracyCircle);
        accuracyCircle = null;
      }

      if (options.pan) {
        exploreMap.setView(latlng, options.zoom || 17);
      }
    }

    if (source === 'gps') {
      setPanelMessage(locationState.accuracy
        ? `Localização encontrada (precisão ±${Math.round(locationState.accuracy)} m). Você pode ajustar o marcador para escolher o ponto exato.`
        : 'Localização encontrada! Você pode ajustar o marcador para escolher o ponto exato.');
    } else {
      setPanelMessage('Ponto selecionado no mapa. Arraste o marcador ou toque em outro ponto para ajustar.');
    }

    scheduleReverseGeocode(lat, lng);
  }

  // --- Geocodificação reversa (via proxy interno, com debounce) -------------
  function scheduleReverseGeocode(lat, lng) {
    clearTimeout(geocodeTimer);
    const elapsed = Date.now() - lastGeocodeAt;
    // Primeira consulta é imediata; as seguintes respeitam ~3,5 s entre chamadas
    const delay = Math.max(450, 3500 - elapsed);
    geocodeTimer = setTimeout(() => reverseGeocode(lat, lng), delay);
  }

  async function reverseGeocode(lat, lng) {
    const token = ++geocodeSeq;

    if (geocodeAbort) {
      try { geocodeAbort.abort(); } catch (e) { /* ignora */ }
    }
    const controller = new AbortController();
    geocodeAbort = controller;
    lastGeocodeAt = Date.now();

    let timedOut = false;
    const timeout = setTimeout(() => { timedOut = true; controller.abort(); }, 9000);

    setPanelMessage('Buscando o endereço aproximado…');

    let response = null;
    let data = null;
    try {
      response = await fetch(
        `api/geocode.php?action=reverse&lat=${encodeURIComponent(lat)}&lng=${encodeURIComponent(lng)}`,
        { headers: { Accept: 'application/json' }, signal: controller.signal, credentials: 'same-origin' }
      );
      data = await response.json().catch(() => null);
    } catch (err) {
      data = null;
    } finally {
      clearTimeout(timeout);
      if (geocodeAbort === controller) geocodeAbort = null;
    }

    // Resposta de uma posição antiga: descarta.
    if (token !== geocodeSeq) return;
    if (locationState.lat !== lat || locationState.lng !== lng) return;

    const result = response && response.ok && data && data.success === true && Array.isArray(data.results) ? data.results[0] : null;
    const parts = result && result.parts && typeof result.parts === 'object' && !Array.isArray(result.parts) ? result.parts : {};
    const text = value => typeof value === 'string' ? value.trim() : '';
    const street = [text(parts.road), text(parts.house_number)].filter(Boolean).join(', ');
    const address = [street, text(parts.suburb), text(parts.city), text(parts.state), text(parts.postcode)].filter(Boolean).join(', ')
      || text(result && result.address);
    const fromGps = locationState.source === 'gps' && gpsPending;

    locationState.address = address || null;
    locationState.parts = address ? parts : null;
    if (fromGps) locationState.confirmed = true;
    syncSummary();
    updateConfirmButtons();
    if (locationState.confirmed) syncConfirmedFields();
    else fillOriginFromLocation();
    if (fromGps) setGpsLoading(false);

    if (!address) {
      if (timedOut) {
        setPanelMessage('Coordenadas obtidas, mas o serviço de endereço demorou para responder. Você pode usar as coordenadas ou tentar novamente.');
      } else if (response && response.status === 429) {
        setPanelMessage('Coordenadas obtidas. Aguarde alguns segundos para identificar o endereço ou continue com as coordenadas.');
      } else if (!response) {
        setPanelMessage('Coordenadas obtidas, mas houve falha de rede ao identificar o endereço. Você pode continuar com as coordenadas ou ajustar o mapa.');
      } else if (!response.ok && response.status !== 404) {
        setPanelMessage('Coordenadas obtidas, mas o serviço de endereço está indisponível. Você pode continuar com as coordenadas ou ajustar o mapa.');
      } else {
        setPanelMessage('Coordenadas obtidas, mas não foi encontrado um endereço utilizável. Você pode continuar com as coordenadas ou ajustar o mapa.');
      }
      return;
    }

    setPanelMessage(locationState.source === 'gps' && locationState.accuracy > 150
      ? `Endereço aproximado (precisão ±${Math.round(locationState.accuracy)} m). Ajuste o marcador para escolher o ponto exato.`
      : 'Endereço aproximado atualizado. Ajuste o marcador se precisar escolher o ponto exato.');
    if (fromGps) showToast('Localização e endereço atualizados.');
  }

  function openMapFocus() {
    initExploreMap();
    if (!pickerBar) return;

    document.body.classList.add('map-focus');
    pickerBar.hidden = false;
    if (!pickerBar.hasAttribute('tabindex')) pickerBar.setAttribute('tabindex', '-1');
    syncSummary();

    if (exploreMap) {
      if (hasPosition()) {
        exploreMap.setView([locationState.lat, locationState.lng], Math.max(exploreMap.getZoom(), 16));
      } else {
        const v = visaoInicial();
        exploreMap.setView([v.lat, v.lng], v.zoom);
      }
    }

    setTimeout(() => {
      if (exploreMap) exploreMap.invalidateSize();
      pickerBar.focus({ preventScroll: true });
    }, 80);
  }

  function closeMapFocus(options = {}) {
    document.body.classList.remove('map-focus');
    if (pickerBar) pickerBar.hidden = true;
    setTimeout(() => { if (exploreMap) exploreMap.invalidateSize(); }, 60);

    const target = options.focusSelector
      ? document.querySelector(options.focusSelector)
      : (locationSummary && !locationSummary.hidden ? btnAdjust : btnGps);
    if (target && typeof target.focus === 'function') {
      target.focus({ preventScroll: true });
    }
  }

  function confirmLocation() {
    if (!hasPosition()) return;

    locationState.confirmed = true;
    syncConfirmedFields();
    updateConfirmButtons();
    syncSummary();
    setPanelMessage('Localização confirmada! Você pode buscar locais acessíveis a partir deste ponto.');

    if (document.body.classList.contains('map-focus')) {
      closeMapFocus({ focusSelector: '#btn-search-action' });
    }
    showToast('Localização confirmada com sucesso!');
  }

  // --- GPS ---------------------------------------------------------------
  function cancelGpsRequest() {
    gpsRequestSeq++;
    clearTimeout(gpsWatchdog);
    setGpsLoading(false);
  }

  function setGpsLoading(isLoading, label = 'Buscando localização…') {
    gpsPending = isLoading;
    [btnGps, btnPickerGps].forEach(btn => {
      if (!btn) return;
      btn.classList.toggle('is-loading', isLoading);
      btn.disabled = isLoading;
      btn.setAttribute('aria-busy', isLoading ? 'true' : 'false');
      const span = btn.querySelector('span');
      if (span) {
        if (!span.dataset.defaultLabel) span.dataset.defaultLabel = span.textContent.trim();
        span.textContent = isLoading ? label : span.dataset.defaultLabel;
      }
    });
  }

  // GPS indisponível/negado: abre o mapa para escolha manual.
  function offerManualSelection(message, toastMessage) {
    showSummary(message);
    updateConfirmButtons();
    openMapFocus();
    if (toastMessage) showToast(toastMessage, 'error');
  }

  function requestGeolocation(options = {}) {
    if (gpsPending) return;

    if (!window.isSecureContext) {
      offerManualSelection(
        'O navegador exige HTTPS com certificado confiável ou um contexto local seguro, como localhost, para usar o GPS. Escolha um ponto no mapa.',
        'GPS exige HTTPS ou localhost: escolha o ponto no mapa.'
      );
      return;
    }

    if (!navigator.geolocation || typeof navigator.geolocation.getCurrentPosition !== 'function') {
      offerManualSelection(
        'A geolocalização não está disponível neste navegador. Toque no mapa para escolher seu ponto manualmente.',
        'Geolocalização indisponível no navegador.'
      );
      return;
    }

    const policy = document.permissionsPolicy || document.featurePolicy;
    if (policy && typeof policy.allowsFeature === 'function' && !policy.allowsFeature('geolocation')) {
      offerManualSelection(
        'A política de permissões desta página bloqueia a localização. O responsável precisa habilitar a geolocalização; você pode escolher um ponto no mapa.',
        'Localização bloqueada pela política da página.'
      );
      return;
    }

    clearTimeout(geocodeTimer);
    geocodeSeq++;
    if (geocodeAbort) geocodeAbort.abort();
    showSummary('Solicitando permissão de localização…');
    setGpsLoading(true);

    let settled = false;
    const requestToken = ++gpsRequestSeq;
    clearTimeout(gpsWatchdog);
    gpsWatchdog = setTimeout(() => {
      if (settled || requestToken !== gpsRequestSeq) return;
      settled = true;
      setGpsLoading(false);
      offerManualSelection(
        'A localização está demorando para responder. Abrimos o mapa para você escolher o ponto manualmente.',
        'Tempo esgotado ao obter a localização.'
      );
    }, 25000);

    const onSuccess = (pos) => {
      if (settled || requestToken !== gpsRequestSeq) return;
      settled = true;
      clearTimeout(gpsWatchdog);

      const latitude = pos.coords.latitude;
      const longitude = pos.coords.longitude;
      const accuracy = pos.coords.accuracy;
      if (!Number.isFinite(latitude) || !Number.isFinite(longitude) || !Number.isFinite(accuracy)
          || Math.abs(latitude) > 90 || Math.abs(longitude) > 180 || accuracy < 0) {
        setGpsLoading(false);
        offerManualSelection('O dispositivo retornou uma localização inválida. Tente novamente ou escolha um ponto no mapa.', 'Localização inválida.');
        return;
      }
      if (!parValido(latitude, longitude)) {
        setGpsLoading(false);
        offerManualSelection('A localização está fora da área atendida no Brasil. Escolha um ponto no mapa.', 'Localização fora da área atendida.');
        return;
      }

      locationState.gpsLat = latitude;
      locationState.gpsLng = longitude;
      locationState.gpsAccuracy = accuracy;

      applyPosition(latitude, longitude, { source: 'gps', accuracy: accuracy, pan: true, zoom: 17 });

      setGpsLoading(true, 'Identificando endereço…');
      showSummary(`Coordenadas obtidas (precisão ±${Math.round(accuracy)} m). Identificando o endereço aproximado…`);
      if (options.focusAfter !== false && originInput) originInput.focus({ preventScroll: true });
    };

    const onError = (err) => {
      if (settled || requestToken !== gpsRequestSeq) return;
      settled = true;
      clearTimeout(gpsWatchdog);
      setGpsLoading(false);

      let message = 'Não foi possível obter sua localização. Você pode selecionar manualmente um ponto no mapa.';
      let toastMessage = 'Não foi possível obter sua localização.';
      const code = err && typeof err.code === 'number' ? err.code : null;

      if (code === 1) {
        message = 'Precisamos da sua permissão para acessar sua localização. Habilite o GPS nas configurações do navegador e tente novamente, ou selecione um ponto no mapa.';
        toastMessage = 'Permissão de localização negada.';
      } else if (code === 2) {
        message = 'GPS indisponível no dispositivo. Você pode selecionar manualmente um ponto no mapa.';
        toastMessage = 'GPS indisponível no dispositivo.';
      } else if (code === 3) {
        message = 'A localização demorou muito para ser obtida. Tente novamente ou selecione um ponto no mapa.';
        toastMessage = 'Tempo esgotado ao obter a localização.';
      }

      offerManualSelection(message, toastMessage);
    };

    try {
      navigator.geolocation.getCurrentPosition(onSuccess, onError, {
        enableHighAccuracy: true,
        timeout: 12000,
        maximumAge: 10000
      });
    } catch (e) {
      settled = true;
      clearTimeout(gpsWatchdog);
      setGpsLoading(false);
      offerManualSelection(
        'Não foi possível acessar o GPS. Toque no mapa para escolher seu ponto manualmente.',
        'Falha ao acessar o GPS.'
      );
    }
  }

  // --- Ligações da interface ---------------------------------------------
  initExploreMap();

  if (btnGps) {
    btnGps.addEventListener('click', () => requestGeolocation({ focusAfter: true }));
  }
  if (btnPickerGps) {
    btnPickerGps.addEventListener('click', () => requestGeolocation({ focusAfter: false }));
  }
  if (btnConfirm) {
    btnConfirm.addEventListener('click', confirmLocation);
  }
  if (btnPickerConfirm) {
    btnPickerConfirm.addEventListener('click', confirmLocation);
  }
  if (btnAdjust) {
    btnAdjust.addEventListener('click', openMapFocus);
  }
  if (btnPickerClose) {
    btnPickerClose.addEventListener('click', () => closeMapFocus());
  }

  if (btnZoomIn) {
    btnZoomIn.addEventListener('click', () => { if (exploreMap) exploreMap.zoomIn(); });
  }
  if (btnZoomOut) {
    btnZoomOut.addEventListener('click', () => { if (exploreMap) exploreMap.zoomOut(); });
  }
  if (btnRecenter) {
    btnRecenter.addEventListener('click', () => {
      initExploreMap();
      if (!exploreMap) return;
      if (locationState.gpsLat !== null && locationState.gpsLng !== null) {
        exploreMap.setView([locationState.gpsLat, locationState.gpsLng], 17);
        showToast('Mapa centralizado na sua localização do GPS.');
      } else if (hasPosition()) {
        exploreMap.setView([locationState.lat, locationState.lng], 17);
        showToast('Mapa centralizado na localização selecionada.');
      } else {
        const v = visaoInicial();
        exploreMap.setView([v.lat, v.lng], v.zoom);
        showToast('Nenhuma posição confirmada: o mapa mostra a visão do estado de São Paulo.');
      }
    });
  }

  // Edição manual do campo de origem invalida a posição escolhida no mapa.
  if (originInput) {
    originInput.addEventListener('input', () => {
      if (suppressOriginInput) return;
      clearSelectedLocation();
    });
  }

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && document.body.classList.contains('map-focus')) {
      closeMapFocus();
    }
  });

  // =========================================================================
  // 2. FILTROS DE CATEGORIA (PILLS)
  // =========================================================================
  const filterChips = document.querySelectorAll('.filter-chip');
  filterChips.forEach(chip => {
    chip.addEventListener('click', () => {
      filterChips.forEach(c => { c.classList.remove('active'); c.setAttribute('aria-pressed', 'false'); });
      chip.classList.add('active');
      chip.setAttribute('aria-pressed', 'true');
      showToast(`Filtro ativado: ${chip.textContent.trim()}`);
    });
  });

  // =========================================================================
  // 3. SUGESTÕES RÁPIDAS DE PESQUISA
  // =========================================================================
  const suggestionChips = document.querySelectorAll('.chip-suggestion');
  const destInput = document.getElementById('input-destination');
  suggestionChips.forEach(chip => {
    chip.addEventListener('click', () => {
      const query = chip.getAttribute('data-query');
      if (destInput) { destInput.value = query; destInput.focus(); showToast(`Pesquisa selecionada: "${query}"`); }
    });
  });

  // Destino escolhido no autocomplete: são as coordenadas que viajam até a tela
  // de resultados. Sem elas a busca de outro endereço não tem centro próprio.
  const destinoSel = { name: '', lat: null, lng: null, id: '', municipio: '', endereco: '' };

  // =========================================================================
  // 4. AUTOCOMPLETE DINÂMICO (locais cadastrados + geocodificação real via api/geocode.php)
  //    A geocodificação responde por qualquer município; os cadastros fixos só
  //    entram quando o banco confirmou a coordenada deles.
  // =========================================================================
  const autocompleteBox = document.getElementById('dest-autocomplete');
  if (destInput && autocompleteBox) {
    const escapeHtml = (value) => String(value)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#39;');

    // Combobox ARIA: o input declara a lista que ele controla e a opção em
    // foco. Sem isso o leitor de tela lê o texto digitado, mas nunca as
    // sugestões — e o usuário por teclado não tem como alcançá-las.
    destInput.setAttribute('role', 'combobox');
    destInput.setAttribute('aria-autocomplete', 'list');
    destInput.setAttribute('aria-haspopup', 'listbox');
    destInput.setAttribute('aria-controls', 'dest-autocomplete-list');
    destInput.setAttribute('aria-expanded', 'false');

    // Canal de anúncio estável: fica fora do innerHTML da lista, senão o
    // leitor perde a região de status a cada tecla digitada.
    const srStatus = document.createElement('span');
    srStatus.className = 'sr-only';
    srStatus.setAttribute('role', 'status');
    srStatus.setAttribute('aria-live', 'polite');
    autocompleteBox.parentNode.appendChild(srStatus);

    let apiResults = [];
    let geoTimer = null;
    let geoSeq = 0;
    let geoAbort = null;
    let geoEstado = 'ocioso';   // 'ocioso' | 'buscando' | 'ok' | 'erro'
    let geoErro = '';
    let opcoes = [];
    let ativoIdx = -1;
    let ultimaQuery = '';

    const montarLista = (query) => {
      // Só entram as sugestões que o banco confirmou com coordenadas reais: sem
      // cadastro não há como afirmar acessibilidade nem para onde levar o mapa.
      const staticMatches = accessiblePlaces.filter(p =>
        p.lat != null && p.lng != null && (
          p.name.toLowerCase().includes(query) ||
          p.address.toLowerCase().includes(query) ||
          p.type.toLowerCase().includes(query)
        )
      ).map(m => ({
        id: m.fromDb ? m.id : null, name: m.name, badge: m.badge,
        lat: m.lat ?? null, lng: m.lng ?? null,
        endereco: m.address || '', municipio: m.municipio || ''
      }));

      const staticNames = new Set(staticMatches.map(m => m.name.toLowerCase()));
      const extra = apiResults
        .filter(r => r.name && !staticNames.has(r.name.toLowerCase()))
        .map(r => ({
          name: r.name,
          badge: (r.parts && r.parts.city) ? r.parts.city : 'Endereço no mapa',
          lat: r.lat, lng: r.lng,
          endereco: r.address || r.name || '',
          municipio: (r.parts && r.parts.city) || ''
        }));

      opcoes = staticMatches.concat(extra);
      // O conteúdo mudou: a opção em foco anterior pode nem existir mais.
      ativoIdx = -1;
      destInput.removeAttribute('aria-activedescendant');

      const itens = opcoes.map((m, i) => `
        <div class="autocomplete-item" id="dest-opt-${i}" role="option" aria-selected="false"
          data-idx="${i}" data-name="${escapeHtml(m.name)}"
          ${m.lat != null ? ` data-lat="${m.lat}" data-lng="${m.lng}"` : ''}
          ${m.id ? ` data-id="${escapeHtml(m.id)}"` : ''}
          ${m.municipio ? ` data-municipio="${escapeHtml(m.municipio)}"` : ''}
          ${m.endereco ? ` data-endereco="${escapeHtml(m.endereco)}"` : ''}>
          <span>📍 ${escapeHtml(m.name)}</span>
          <span class="autocomplete-badge">${escapeHtml(m.badge)}</span>
        </div>`).join('');

      let aviso = '';
      if (geoEstado === 'buscando') {
        aviso = '<p class="autocomplete-msg">Buscando endereços reais…</p>';
      } else if (geoEstado === 'erro') {
        aviso = `<p class="autocomplete-msg autocomplete-msg-erro">${escapeHtml(geoErro)}</p>`;
      } else if (opcoes.length === 0) {
        aviso = `<p class="autocomplete-msg">Nenhum local cadastrado combina com "${escapeHtml(query)}". Você ainda pode buscar por tipo de lugar: a busca segue como texto até haver um endereço confirmado.</p>`;
      }

      autocompleteBox.innerHTML = `
        <div class="autocomplete-list" id="dest-autocomplete-list" role="listbox"
          aria-label="Sugestões de destino acessível">${itens}</div>${aviso}`;

      if (opcoes.length > 0) {
        srStatus.textContent = `${opcoes.length} ${opcoes.length === 1 ? 'sugestão disponível' : 'sugestões disponíveis'}. Use as setas para navegar.`;
      } else if (geoEstado !== 'buscando' && geoEstado !== 'erro') {
        srStatus.textContent = 'Nenhuma sugestão com endereço confirmado.';
      }
    };

    const abrirLista = () => {
      autocompleteBox.classList.add('open');
      destInput.setAttribute('aria-expanded', 'true');
    };

    const fecharLista = () => {
      autocompleteBox.classList.remove('open');
      destInput.setAttribute('aria-expanded', 'false');
      destInput.removeAttribute('aria-activedescendant');
      ativoIdx = -1;
    };

    const render = (query) => {
      ultimaQuery = query;
      montarLista(query);
      if (opcoes.length > 0 || geoEstado === 'buscando' || geoEstado === 'erro') abrirLista();
      else fecharLista();
    };

    const moverFoco = (idx) => {
      if (opcoes.length === 0) return;
      const limitado = Math.max(0, Math.min(opcoes.length - 1, idx));
      const anterior = autocompleteBox.querySelector('.autocomplete-item.is-active');
      if (anterior) {
        anterior.classList.remove('is-active');
        anterior.setAttribute('aria-selected', 'false');
      }
      ativoIdx = limitado;
      const atual = document.getElementById(`dest-opt-${ativoIdx}`);
      if (!atual) return;
      atual.classList.add('is-active');
      atual.setAttribute('aria-selected', 'true');
      destInput.setAttribute('aria-activedescendant', atual.id);
      if (atual.scrollIntoView) atual.scrollIntoView({ block: 'nearest' });
    };

    const pararBusca = () => {
      if (geoTimer) { clearTimeout(geoTimer); geoTimer = null; }
      if (geoAbort) { geoAbort.abort(); geoAbort = null; }
      // ++geoSeq descarta respostas que já estejam em trânsito.
      geoSeq++;
      geoEstado = 'ocioso';
      geoErro = '';
    };

    function aplicarDestino(m) {
      // Guarda o destino escolhido com as coordenadas reais: sem elas a tela
      // de resultados teria que adivinhar a região e cairia na cidade padrão.
      destinoSel.name = m.name;
      destinoSel.lat = (m.lat != null && Number.isFinite(Number(m.lat))) ? Number(m.lat) : null;
      destinoSel.lng = (m.lng != null && Number.isFinite(Number(m.lng))) ? Number(m.lng) : null;
      destinoSel.id = m.id || '';
      destinoSel.municipio = m.municipio || '';
      destinoSel.endereco = m.endereco || m.name;

      if (destinoSel.lat != null && destinoSel.lng != null) {
        sessionStorage.setItem('passeLivre_dest_text', m.name);
        sessionStorage.setItem('passeLivre_dest_lat', String(destinoSel.lat));
        sessionStorage.setItem('passeLivre_dest_lng', String(destinoSel.lng));
      } else {
        sessionStorage.removeItem('passeLivre_dest_lat');
        sessionStorage.removeItem('passeLivre_dest_lng');
        sessionStorage.removeItem('passeLivre_dest_text');
      }
      if (destinoSel.id) sessionStorage.setItem('passeLivre_dest_id', String(destinoSel.id));
      else sessionStorage.removeItem('passeLivre_dest_id');
    }

    function selecionar(idx) {
      const m = opcoes[idx];
      if (!m) return;
      pararBusca();
      destInput.value = m.name;
      aplicarDestino(m);
      fecharLista();
      montarLista(m.name.toLowerCase());
      srStatus.textContent = `Destino selecionado: ${m.name}`;
      showToast(`Local selecionado: ${m.name}`);
    }

    autocompleteBox.addEventListener('click', (e) => {
      const item = e.target.closest('.autocomplete-item');
      if (item) selecionar(Number(item.dataset.idx));
    });

    autocompleteBox.addEventListener('mousemove', (e) => {
      const item = e.target.closest('.autocomplete-item');
      if (item) moverFoco(Number(item.dataset.idx));
    });

    const fetchGeo = async (query, signal) => {
      const qs = new URLSearchParams({ action: 'search', q: query, limit: '6', autocomplete: '1' });
      // A posição confirmada entra só como preferência de ordenação: endereços
      // com o mesmo nome em outra cidade continuam aparecendo.
      const latBias = originLatInput ? parseFloat(originLatInput.value) : NaN;
      const lngBias = originLngInput ? parseFloat(originLngInput.value) : NaN;
      if (Number.isFinite(latBias) && Number.isFinite(lngBias)) {
        qs.set('lat', String(latBias));
        qs.set('lng', String(lngBias));
      }
      try {
        const res = await fetch(`api/geocode.php?${qs.toString()}`, {
          headers: { 'Accept': 'application/json' },
          credentials: 'same-origin',
          signal
        });
        let data = null;
        try { data = await res.json(); } catch (_) { data = null; }
        if (!res.ok) {
          const msg = data && data.mensagem ? data.mensagem : `A busca de endereços respondeu HTTP ${res.status}.`;
          return { erro: `${msg} As sugestões cadastradas continuam disponíveis.` };
        }
        if (!data || data.success !== true) {
          const msg = data && data.mensagem ? data.mensagem : 'A busca de endereços respondeu um formato inesperado.';
          return { erro: `${msg} As sugestões cadastradas continuam disponíveis.` };
        }
        return { resultados: Array.isArray(data.results) ? data.results : [] };
      } catch (err) {
        // Cancelamento esperado: a próxima tecla já substituiu esta consulta.
        if (err && err.name === 'AbortError') return null;
        return { erro: 'Não foi possível consultar a busca de endereços agora. Verifique a conexão e digite novamente.' };
      }
    };

    destInput.addEventListener('input', () => {
      const query = destInput.value.trim().toLowerCase();
      apiResults = [];
      if (geoAbort) { geoAbort.abort(); geoAbort = null; }
      // Texto diferente do destino escolhido: as coordenadas antigas não valem mais.
      if (destinoSel.name && query !== destinoSel.name.toLowerCase()) {
        destinoSel.name = '';
        destinoSel.lat = null;
        destinoSel.lng = null;
        destinoSel.id = '';
        destinoSel.municipio = '';
        destinoSel.endereco = '';
        sessionStorage.removeItem('passeLivre_dest_lat');
        sessionStorage.removeItem('passeLivre_dest_lng');
        sessionStorage.removeItem('passeLivre_dest_id');
      }
      if (query.length < 3) {
        if (geoTimer) { clearTimeout(geoTimer); geoTimer = null; }
        geoEstado = 'ocioso';
        geoErro = '';
        fecharLista();
        return;
      }

      geoEstado = 'buscando';
      geoErro = '';
      render(query);

      const seq = ++geoSeq;
      if (geoTimer) clearTimeout(geoTimer);
      geoTimer = setTimeout(async () => {
        if (geoAbort) geoAbort.abort();
        geoAbort = new AbortController();
        const out = await fetchGeo(query, geoAbort.signal);
        geoAbort = null;
        // seq diferente = o usuário já digitou outra coisa: descarte sem tocar a tela.
        if (out === null || seq !== geoSeq || destInput.value.trim().toLowerCase() !== query) return;
        if (out.erro) {
          geoEstado = 'erro';
          geoErro = out.erro;
          apiResults = [];
          srStatus.textContent = out.erro;
        } else {
          geoEstado = 'ok';
          apiResults = out.resultados.slice(0, 6);
        }
        render(query);
      }, 300);
    });

    destInput.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') {
        if (autocompleteBox.classList.contains('open')) {
          e.preventDefault();
          fecharLista();
          srStatus.textContent = 'Sugestões fechadas.';
        }
        return;
      }
      // Lista fechada ainda tem resultados da última consulta: reabri-la com
      // seta evita que o usuário precise apagar e redigitar o texto.
      if (!autocompleteBox.classList.contains('open') && (e.key === 'ArrowDown' || e.key === 'ArrowUp')) {
        if (opcoes.length > 0 && ultimaQuery.length >= 3) {
          e.preventDefault();
          abrirLista();
          moverFoco(e.key === 'ArrowDown' ? 0 : opcoes.length - 1);
        }
        return;
      }
      if (opcoes.length === 0) return;   // Enter livre segue enviando a busca por texto
      if (e.key === 'ArrowDown') { e.preventDefault(); moverFoco(ativoIdx < 0 ? 0 : ativoIdx + 1); }
      else if (e.key === 'ArrowUp') { e.preventDefault(); moverFoco(ativoIdx <= 0 ? opcoes.length - 1 : ativoIdx - 1); }
      else if (e.key === 'Home') { e.preventDefault(); moverFoco(0); }
      else if (e.key === 'End') { e.preventDefault(); moverFoco(opcoes.length - 1); }
      else if (e.key === 'Enter') {
        if (ativoIdx >= 0) { e.preventDefault(); selecionar(ativoIdx); }
        else fecharLista();
      }
      else if (e.key === 'Tab') fecharLista();
    });

    document.addEventListener('click', e => {
      if (!destInput.contains(e.target) && !autocompleteBox.contains(e.target)) {
        fecharLista();
      }
    });
  }

  // =========================================================================
  // 5. SUBMISSÃO DA BUSCA — usa a posição confirmada
  // =========================================================================
  const searchForm = document.getElementById('route-search-form');

  function redirectToResults() {
    const dest   = destInput ? destInput.value.trim() : '';
    const destId = destinoSel.id || sessionStorage.getItem('passeLivre_dest_id') || '';

    if (hasPosition() && !locationState.confirmed) {
      showSummary('Confirme a localização escolhida antes de buscar.');
      updateConfirmButtons();
      if (btnConfirm) btnConfirm.focus({ preventScroll: true });
      showToast('Confirme sua localização antes de buscar.', 'warning');
      return;
    }

    const lat    = originLatInput ? originLatInput.value : '';
    const lng    = originLngInput ? originLngInput.value : '';
    const origem = originAddrInput ? originAddrInput.value : '';

    const regiao = destinoSel.municipio ? ' em ' + destinoSel.municipio : '';
    showToast(`Buscando rotas e lugares acessíveis${regiao}…`);
    setTimeout(() => {
      const qs = new URLSearchParams();
      if (dest)   qs.set('q', dest);
      if (destId) qs.set('dest_id', destId);
      // As coordenadas do destino escolhido viajam até a tela de resultados: sem
      // elas a página não tem como centrar mapa e lista no endereço pesquisado.
      if (destinoSel.lat != null && destinoSel.lng != null) {
        qs.set('dest_lat', String(destinoSel.lat));
        qs.set('dest_lng', String(destinoSel.lng));
      }
      if (destinoSel.municipio) qs.set('dest_municipio', destinoSel.municipio);
      if (destinoSel.endereco)  qs.set('dest_endereco', destinoSel.endereco);
      if (lat && lng) qs.set('lat', lat);
      if (lat && lng) qs.set('lng', lng);
      if (origem) qs.set('origem', origem);
      const qStr = qs.toString();
      window.location.href = 'lugares-encontrados.php' + (qStr ? '?' + qStr : '');
    }, 350);
  }

  if (searchForm) {
    searchForm.addEventListener('submit', e => { e.preventDefault(); redirectToResults(); });
  }

  // =========================================================================
  // 6. MODAL DE DÚVIDAS SOBRE ROTAS
  // =========================================================================
  const linkFaq    = document.getElementById('link-faq-routes');
  const modalFaq   = document.getElementById('modal-faq');
  const btnCloseFaq = document.getElementById('btn-close-faq');

  if (linkFaq && modalFaq) {
    linkFaq.addEventListener('click', e => { e.preventDefault(); modalFaq.classList.add('open'); });
    if (btnCloseFaq) btnCloseFaq.addEventListener('click', () => modalFaq.classList.remove('open'));
    modalFaq.addEventListener('click', e => { if (e.target === modalFaq) modalFaq.classList.remove('open'); });
    document.addEventListener('keydown', e => { if (e.key === 'Escape') modalFaq.classList.remove('open'); });
  }

  // =========================================================================
  // 7. NOTIFICAÇÕES
  // =========================================================================
  const btnNotify = document.getElementById('btn-notifications');
  if (btnNotify) {
    btnNotify.addEventListener('click', () => {
      showToast('Novas calçadas táteis adicionadas ao redor da Estação Suzano!');
    });
  }

  // =========================================================================
  // 8. PARÂMETRO verified=true (vindo do cadastro)
  // =========================================================================
  const urlParams = new URLSearchParams(window.location.search);
  if (urlParams.get('verified') === 'true') {
    setTimeout(() => {
      showToast('E-mail verificado com sucesso! Bem-vindo(a) ao Passe Livre Suzano.');
    }, 400);
  }
});

/* ==========================================================================
   INTEGRAÇÃO SQL — explorar.php (Mapa Central do Cidadão)
   ========================================================================== */
document.addEventListener('DOMContentLoaded', async () => {
  if (!window.PasseLivreDBReady) return;
  try {
    await window.PasseLivreDBReady;
    if (!window.PLDB) return;

    // Complementa o autocomplete com locais reais do banco
    const dbLocais = window.PLDB.locais.findAll();
    if (dbLocais && dbLocais.length) {
      // O array de sugestões vive no outro DOMContentLoaded; a referência é
      // exposta em window.PasseLivreSugestoes. Antes daqui era lido um nome fora
      // do escopo, o que lançava ReferenceError e derrubava todo este bloco.
      const sugestoes = Array.isArray(window.PasseLivreSugestoes) ? window.PasseLivreSugestoes : null;
      if (sugestoes) {
        dbLocais.forEach(l => {
          const exists = sugestoes.find(p => p.name === l.name);
          if (exists) {
            // A sugestão fixa não tinha coordenadas: aproveita as do cadastro real.
            if (l.lat && l.lng) { exists.lat = Number(l.lat); exists.lng = Number(l.lng); }
            if (l.municipio) exists.municipio = l.municipio;
            // O id do protótipo (1..7) não é id de banco: só vale o da linha real,
            // senão o ?dest_id= da busca apontaria para outro cadastro.
            exists.id = l.id;
            exists.fromDb = true;
            return;
          }
          sugestoes.push({
            id:       l.id,
            fromDb:   true,
            name:     l.name,
            type:     l.category_key,
            category: l.access_status === 'verified' ? 'total' : 'partial',
            address:  l.address,
            badge:    l.access_text || l.access_status,
            lat:      l.lat ? Number(l.lat) : null,
            lng:      l.lng ? Number(l.lng) : null,
            municipio: l.municipio || '',
            features: []
          });
        });
        console.log(`[main SQL] Autocomplete atualizado com ${dbLocais.length} locais do banco.`);
      }
    }

    // Formulário de busca — salva rota no banco ao submeter
    const formBusca = document.getElementById('form-busca') || document.querySelector('form.route-search-form');
    if (formBusca) {
      formBusca.addEventListener('submit', async (e) => {
        const origin = document.getElementById('input-origin')?.value.trim()      || '';
        const dest   = document.getElementById('input-destination')?.value.trim() || '';
        if (origin && dest && window.PLDB) {
          const user   = window.PasseLivreAuth?.getUser();
          const dbUser = user ? window.PLDB.users.findByEmail(user.email) : null;
          window.PLDB.rotas.create({
            user_id:          dbUser?.id || null,
            origin_text:      origin,
            destination_text: dest,
            route_type:       'acessivel'
          });
          console.log(`[main SQL] Busca salva: ${origin} → ${dest}`);
        }
      });
    }
  } catch (e) {
    console.warn('[main] SQL:', e);
  }
});
