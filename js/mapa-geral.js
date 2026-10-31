/**
 * PASSE LIVRE — MAPA GERAL DE ACESSIBILIDADE
 * Script de Controle do Mapa Interativo, Filtros, Marcadores Reais e Georreferenciamento
 */

document.addEventListener('DOMContentLoaded', () => {
  initAccessibilityMap();
  initFilterPills();
  initMapControls();
  initBarrierReporting();
  initHeaderSearch();
});

/* Variáveis Globais de Estado */
let map = null;
let markersLayerGroup = null;
let labelsLayerGroup = null;
let currentFilter = 'all';
let allMarkersData = [];
let markerInstances = {};
let osmLayerGroup = null;

/*
 * Centro da busca. O mapa já usou uma coordenada fixa de Suzano em todas as
 * chamadas de api/locais.php e api/osm_pontos.php, então pesquisasse o usuário
 * qualquer cidade o "Explorar Mundo" devolvia sempre os mesmos pontos. O centro
 * passa a vir da URL, do endereço geocodificado ou da posição aprovada no
 * navegador; sem centro, o mapa mostra a visão do estado e não inventa
 * marcadores.
 */
const CENTRO_DEMONSTRACAO = [-23.5350, -46.3075]; // região da maquete do projeto
const VISAO_ESTADO = { lat: -22.0, lng: -48.0, zoom: 7 };
const DEFAULT_ZOOM = 15;

let centroAtual = null;   // [lat, lng] | null
let rotuloAtual = '';
let pedidoSeq = 0;
let geoClickSeq = 0;
let pontoClicado = null;  // última posição clicada no mapa { lat, lng }

/* Mesma faixa de sanidade usada no backend (includes/geo_centro.php). */
function parValido(lat, lng) {
  return Number.isFinite(lat) && Number.isFinite(lng) &&
    lat >= -35 && lat <= 6 && lng >= -75 && lng <= -30;
}

function kmEntre(a, b) {
  const rad = Math.PI / 180;
  const dLat = (b[0] - a[0]) * rad;
  const dLng = (b[1] - a[1]) * rad;
  const h = Math.sin(dLat / 2) ** 2 +
    Math.cos(a[0] * rad) * Math.cos(b[0] * rad) * Math.sin(dLng / 2) ** 2;
  return 2 * 6371 * Math.asin(Math.sqrt(h));
}

/* Os rótulos de bairro da maquete só significam algo dentro da própria maquete. */
function emRegiaoDemonstracao() {
  return !!centroAtual && kmEntre(centroAtual, CENTRO_DEMONSTRACAO) <= 5;
}

/* ==========================================================================
   1. INICIALIZAÇÃO DO MAPA INTERATIVO (LEAFLET + TILES ESTILIZADOS)
   ========================================================================== */
async function initAccessibilityMap() {
  const mapContainer = document.getElementById('interactive-map');
  if (!mapContainer) return;

  // Criação da instância do Leaflet
  map = L.map('interactive-map', {
    center: [VISAO_ESTADO.lat, VISAO_ESTADO.lng],
    zoom: VISAO_ESTADO.zoom,
    minZoom: 5,
    maxZoom: 18,
    zoomControl: false, // Desativado para usar os controles personalizados fiéis ao design
    attributionControl: true
  });

  // Camada base: raster do OpenStreetMap (a CARTO passou a exigir chave e só
  // devolvia "API KEY REQUIRED"). O filtro quente continua aplicado no CSS.
  L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
  }).addTo(map);

  // LayerGroup para gerenciar os marcadores com filtragem dinâmica
  markersLayerGroup = L.layerGroup().addTo(map);
  labelsLayerGroup = L.layerGroup().addTo(map);

  // Camada extra para pontos de acessibilidade vindos do OpenStreetMap
  osmLayerGroup = L.layerGroup();

  // Clique no mapa para capturar endereço e reportar barreira
  map.on('click', onMapClick);

  const inicio = await resolverCentroInicial();
  aplicarCentro(inicio ? inicio.coords : null, inicio ? inicio.rotulo : '');
}

/* Geocodificação pelo mesmo endpoint da busca de endereços do projeto. */
async function geocodificar(texto) {
  const t = (texto || '').trim();
  if (t.length < 3) return null;
  try {
    const p = new URLSearchParams({ action: 'search', q: t, limit: '1' });
    if (centroAtual) {
      p.set('lat', String(centroAtual[0]));
      p.set('lng', String(centroAtual[1]));
    }
    const res = await fetch('api/geocode.php?' + p.toString(), { headers: { 'Accept': 'application/json' } });
    const data = await res.json();
    const r = data && Array.isArray(data.results) ? data.results[0] : null;
    const lat = r ? parseFloat(r.lat) : NaN;
    const lng = r ? parseFloat(r.lng) : NaN;
    if (!parValido(lat, lng)) return null;
    return {
      coords: [lat, lng],
      rotulo: (r.parts && r.parts.city) || r.address || r.name || t
    };
  } catch (erro) {
    return null;
  }
}

/** URL (?lat/?lng, ?dest_lat/?dest_lng, ?q) > texto geocodificado > GPS aprovado. */
async function resolverCentroInicial() {
  const p = new URLSearchParams(window.location.search);
  const lat = parseFloat(p.get('lat') ?? p.get('dest_lat') ?? '');
  const lng = parseFloat(p.get('lng') ?? p.get('dest_lng') ?? '');
  if (parValido(lat, lng)) {
    return { coords: [lat, lng], rotulo: (p.get('municipio') || p.get('dest_nome') || '').trim() };
  }

  const texto = (p.get('q') || p.get('busca') || p.get('search') || '').trim();
  if (texto.length >= 3) {
    const geo = await geocodificar(texto);
    if (geo) return geo;
  }

  const gpsLat = parseFloat(sessionStorage.getItem('passeLivre_gps_lat') || '');
  const gpsLng = parseFloat(sessionStorage.getItem('passeLivre_gps_lng') || '');
  if (parValido(gpsLat, gpsLng)) return { coords: [gpsLat, gpsLng], rotulo: 'sua localização' };

  return null;
}

/** Move o mapa, redesenha os rótulos e recarrega as camadas para o novo centro. */
function aplicarCentro(coords, rotulo) {
  centroAtual = coords && coords.length === 2 ? coords : null;
  rotuloAtual = rotulo || '';
  const seq = ++pedidoSeq;

  if (map) {
    if (centroAtual) map.setView(centroAtual, DEFAULT_ZOOM);
    else map.setView([VISAO_ESTADO.lat, VISAO_ESTADO.lng], VISAO_ESTADO.zoom);
  }
  desenhaRoticosDeBairro();
  loadAccessibilityMarkers(seq);
  loadOpenStreetMapPoints(seq);
  atualizaAvisoRegiao();
}

/* Aviso discreto sobre a região que o mapa está mostrando (e quando não há). */
function atualizaAvisoRegiao(textoExtra) {
  let box = document.getElementById('map-aviso-regiao');
  if (!box && map) {
    box = document.createElement('div');
    box.id = 'map-aviso-regiao';
    box.setAttribute('role', 'status');
    box.style.cssText = 'position:absolute;left:12px;bottom:12px;z-index:9;max-width:74%;'
      + 'background:rgba(255,255,255,.95);border:1px solid #e7ded2;border-radius:12px;'
      + 'padding:8px 12px;font-size:11px;line-height:1.45;color:#44403c;font-family:sans-serif;'
      + 'box-shadow:0 4px 14px rgba(0,0,0,.08)';
    const alvo = document.getElementById('interactive-map');
    if (alvo) alvo.appendChild(box);
  }
  if (!box) return;

  let texto = textoExtra || '';
  if (!texto) {
    if (!centroAtual) {
      texto = 'Pesquise um endereço, uma cidade ou permita sua localização para ver os pontos desta região.';
    } else if (allMarkersData.length) {
      texto = allMarkersData.length + (allMarkersData.length === 1 ? ' local cadastrado' : ' locais cadastrados') +
        ' em até ' + String(RAIO_MAPA_KM).replace('.', ',') + ' km' + (rotuloAtual ? ' de ' + rotuloAtual : '') + '.';
    } else {
      texto = 'Nenhum local cadastrado em até ' + String(RAIO_MAPA_KM).replace('.', ',') + ' km' +
        (rotuloAtual ? ' de ' + rotuloAtual : ' desta região') +
        '. Os pontos abertos do OpenStreetMap aparecem quando a fonte tiver dados aqui.';
    }
  }

  if (texto) { box.textContent = texto; box.style.display = 'block'; }
  else { box.textContent = ''; box.style.display = 'none'; }
}

/* Rótulos de bairro sobre o mapa: só na região da apresentação do projeto. */
function desenhaRoticosDeBairro() {
  if (!map || !labelsLayerGroup) return;
  labelsLayerGroup.clearLayers();
  if (!emRegiaoDemonstracao()) return;

  const neighborhoods = [
    { name: 'SUZANO CENTRO', coords: [-23.5320, -46.3140] },
    { name: 'JARDIM IMPERADOR', coords: [-23.5290, -46.3045] },
    { name: 'VILA AMORIM', coords: [-23.5435, -46.3160] }
  ];

  neighborhoods.forEach(nh => {
    const labelIcon = L.divIcon({
      className: 'map-neighborhood-label',
      html: `<span>${nh.name}</span>`,
      iconSize: [200, 24],
      iconAnchor: [100, 12]
    });

    L.marker(nh.coords, { icon: labelIcon, interactive: false }).addTo(labelsLayerGroup);
  });
}

/* ==========================================================================
   2. CRIAÇÃO DOS MARCADORES E POPUPS PERSONALIZADOS
   ========================================================================== */
const RAIO_MAPA_KM = 10; // mesmo padrão de api/locais.php (PL_LOCAIS_RAIO_PADRAO_KM)

function loadAccessibilityMarkers(seq) {
  buscarLocaisDoBanco()
    .then(locais => {
      if (seq !== pedidoSeq) return;
      renderMarkers(locais);
      atualizaAvisoRegiao();
    })
    .catch(() => {
      if (seq !== pedidoSeq) return;
      renderMarkers([]);
      atualizaAvisoRegiao('A consulta aos locais cadastrados não respondeu agora. Tente novamente.');
    });
}

/* Fonte primária dos marcadores: api/locais.php (tabela locais do MySQL) */
async function buscarLocaisDoBanco() {
  // Sem centro não há proximidade a prometer: a listagem antiga caía no banco
  // inteiro e devolvia sempre os cadastros de Suzano para qualquer cidade.
  if (!centroAtual) return [];
  const p = new URLSearchParams({
    action: 'list',
    raio_km: String(RAIO_MAPA_KM),
    lat: String(centroAtual[0]),
    lng: String(centroAtual[1])
  });
  const resposta = await fetch('api/locais.php?' + p.toString(), {
    headers: { 'Accept': 'application/json' },
    signal: AbortSignal.timeout(8000)
  });
  if (!resposta.ok) return [];
  const dados = await resposta.json();
  if (!dados || !dados.success || !Array.isArray(dados.locais)) return [];
  return dados.locais.map(mapearLocalParaMarcador).filter(Boolean);
}

const ICONE_POR_CATEGORIA = {
  transporte: { iconClass: 'pin-orange-bus',        iconImg: 'assets/icons/bus-white.svg' },
  comercio:   { iconClass: 'pin-green-shop',        iconImg: 'assets/icons/bag-white.svg' },
  publico:    { iconClass: 'pin-teal-wheelchair',   iconImg: 'assets/icons/wheelchair.svg' },
  parque:     { iconClass: 'pin-green-tree',        iconImg: 'assets/icons/tree-white.svg' },
  educacao:   { iconClass: 'pin-green-check',       iconImg: 'assets/icons/check.svg' },
  saude:      { iconClass: 'pin-teal-wheelchair',   iconImg: 'assets/icons/wheelchair.svg' }
};

const RECURSO_EM_TIPO = {
  rampas: 'cadeirante', rampa_acesso: 'cadeirante', elevador: 'cadeirante',
  cadeira_emprestimo: 'cadeirante', piso_tatil: 'visual',
  sinalizacao_sonora: 'auditivo', banheiro_adaptado: 'banheiro',
  vagas_pcd: 'vaga', vaga_pcd: 'vaga', estacionamento: 'vaga'
};

function mapearLocalParaMarcador(loc) {
  const lat = parseFloat(loc.lat);
  const lng = parseFloat(loc.lng);
  if (!Number.isFinite(lat) || !Number.isFinite(lng)) return null;

  const status = loc.access_status || 'unverified';
  const porStatus = {
    verified:   { category: 'high',    conformityClass: 'popup-tag-high',   base: { iconClass: 'pin-green-check',      iconImg: 'assets/icons/check.svg' } },
    partial:    { category: 'partial', conformityClass: 'popup-tag-partial', base: { iconClass: 'pin-orange-store',     iconImg: 'assets/icons/store-white.svg' } },
    critical:   { category: 'low',     conformityClass: 'popup-tag-low',     base: { iconClass: 'pin-red-alert',        iconImg: 'assets/icons/warning.svg' } }
  }[status] || { category: 'audit', conformityClass: 'popup-tag-audit', base: { iconClass: 'pin-white-hourglass', iconImg: 'assets/icons/hourglass-white.svg' } };

  const icone = ICONE_POR_CATEGORIA[loc.category_key] || porStatus.base;

  const recursos = Array.isArray(loc.recursos_detalhados) ? loc.recursos_detalhados : [];
  const features = recursos.length
    ? recursos.map(r => r.nome_recurso)
    : (Array.isArray(loc.features) ? loc.features : []);

  const types = new Set(['all', porStatus.category === 'audit' ? 'auditoria' : porStatus.category]);
  if (status === 'verified') types.add('total');
  if (status === 'partial') types.add('partial');
  if (status === 'critical') { types.add('none'); types.add('obstaculo'); }
  types.add('entrada');
  recursos.forEach(r => { if (RECURSO_EM_TIPO[r.recurso_key]) types.add(RECURSO_EM_TIPO[r.recurso_key]); });

  return {
    id: `loc-${loc.id}`,
    localId: loc.id ? String(loc.id) : null,
    title: loc.name,
    category: porStatus.category,
    types: Array.from(types),
    coords: [lat, lng],
    iconClass: icone.iconClass,
    iconImg: icone.iconImg,
    address: `${loc.address}${loc.bairro ? ' - ' + loc.bairro : ''}${loc.municipio ? ' — ' + loc.municipio : ''}`,
    conformity: loc.access_text ||
      (status === 'unverified' ? 'Acessibilidade não verificada' : porStatus.conformityClass.replace('popup-tag-', '')),
    conformityClass: porStatus.conformityClass,
    features: features.slice(0, 6),
    description: [loc.category, loc.opening_hours ? `Horário: ${loc.opening_hours}` : '', loc.bus_lines ? `Linhas: ${loc.bus_lines}` : '']
      .filter(Boolean).join(' • ')
  };
}

/* Rota para o ponto real do marcador: id de banco quando existe cadastro,
   coordenada quando é um ponto aberto do OpenStreetMap. */
function linkTraçarRota(item) {
  if (item.localId) return `configuracao-rota.php?dest_id=${encodeURIComponent(item.localId)}`;
  const q = new URLSearchParams({
    dest_lat: String(item.coords[0]),
    dest_lng: String(item.coords[1]),
    dest_nome: item.title
  });
  return `configuracao-rota.php?${q.toString()}`;
}

function criarConteudoPopup(item) {
  const featuresHtml = item.features.map(f => `<span class="popup-feature-pill">${esc(f)}</span>`).join('');
  return `
      <div class="map-custom-popup">
        <span class="popup-tag-badge ${item.conformityClass}">${esc(item.conformity)}</span>
        <h4 class="popup-title">${esc(item.title)}</h4>
        <p class="popup-address">${esc(item.address)}</p>
        <p style="font-size: 11px; color: #57534e; line-height: 1.35; margin-top: 2px;">${esc(item.description)}${item.fonte ? ` <em style="color:#a8a29e;">(fonte: ${esc(item.fonte)})</em>` : ''}</p>
        <div class="popup-features-list">
          ${featuresHtml}
        </div>
        <a href="${linkTraçarRota(item)}" class="popup-btn-action">
          Traçar Rota Acessível &rarr;
        </a>
      </div>
  `;
}

function criarPin(item) {
  const pinContent = item.iconImg ? `<img src="${item.iconImg}" alt="" aria-hidden="true">` : '';
  return L.divIcon({
    className: `custom-map-pin ${item.iconClass}`,
    html: pinContent,
    iconSize: [32, 32],
    iconAnchor: [16, 16],
    popupAnchor: [0, -18]
  });
}

function renderMarkers(lista) {
  allMarkersData = lista;
  markersLayerGroup.clearLayers();
  markerInstances = {};

  lista.forEach(item => {
    const marker = L.marker(item.coords, {
      icon: criarPin(item),
      title: item.title,
      riseOnHover: true
    });

    marker.bindPopup(criarConteudoPopup(item));
    marker.markerData = item;

    // Adiciona ao mapa
    marker.addTo(markersLayerGroup);
    markerInstances[item.id] = marker;
  });
}

/* Pontos de acessibilidade colaborativos do OpenStreetMap (camada opcional) */
async function loadOpenStreetMapPoints(seq) {
  if (!osmLayerGroup) return;
  osmLayerGroup.clearLayers();
  osmLayerGroup.remove();
  if (!centroAtual) return;
  try {
    const p = new URLSearchParams({
      lat: String(centroAtual[0]),
      lng: String(centroAtual[1]),
      raio_km: String(RAIO_MAPA_KM),
      limite: '60'
    });
    const resposta = await fetch('api/osm_pontos.php?' + p.toString(), {
      headers: { 'Accept': 'application/json' },
      signal: AbortSignal.timeout(9000)
    });
    if (!resposta.ok) return;
    const dados = await resposta.json();
    if (seq !== pedidoSeq) return; // região mudou enquanto o Overpass respondia
    if (!dados || !dados.success || !Array.isArray(dados.pontos) || !dados.pontos.length) return;

    dados.pontos.forEach(pt => {
      const item = {
        id: `osm-${pt.id}`,
        title: pt.nome,
        category: 'audit',
        types: ['all'],
        coords: [pt.lat, pt.lng],
        iconClass: 'pin-teal-wheelchair',
        iconImg: 'assets/icons/wheelchair.svg',
        address: pt.tipo,
        conformity: 'Dado aberto — não verificado',
        conformityClass: 'popup-tag-audit',
        features: [pt.tipo],
        description: 'Ponto mapeado pela comunidade do mapa aberto.',
        fonte: dados.fonte || 'OpenStreetMap'
      };
      L.marker(item.coords, { icon: criarPin(item), title: item.title, riseOnHover: true })
        .bindPopup(criarConteudoPopup(item))
        .addTo(osmLayerGroup);
    });

    osmLayerGroup.addTo(map);
  } catch (erro) {
    /* Sem Overpass: o mapa segue só com os locais cadastrados. */
  }
}

function esc(valor) {
  return String(valor ?? '')
    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}

/* ==========================================================================
   3. SISTEMA DE FILTRAGEM POR PÍLULAS (CADEIRANTE, CEGO, SURDO, VAGAS, ETC.)
   ========================================================================== */
function initFilterPills() {
  const pills = document.querySelectorAll('.filter-pill');

  pills.forEach(pill => {
    pill.addEventListener('click', () => {
      // Atualiza classe ativa nas pílulas
      pills.forEach(p => p.classList.remove('active'));
      pill.classList.add('active');

      const filterType = pill.getAttribute('data-filter') || 'all';
      currentFilter = filterType;
      applyFilterToMap(filterType);

      // Feedback amigável para o usuário
      const filterLabel = pill.textContent.trim();
      showToast(`Exibindo filtro: ${filterLabel}`);
    });
  });
}

function applyFilterToMap(filterType) {
  if (!markersLayerGroup) return;

  markersLayerGroup.clearLayers();

  let countVisible = 0;

  allMarkersData.forEach(item => {
    let match = false;

    if (filterType === 'all') {
      match = true;
    } else if (item.types.includes(filterType)) {
      match = true;
    }

    if (match && markerInstances[item.id]) {
      markerInstances[item.id].addTo(markersLayerGroup);
      countVisible++;
    }
  });
}

/* ==========================================================================
   4. CONTROLES DO MAPA (ZOOM IN, ZOOM OUT, RECENTRALIZAR)
   ========================================================================== */
function initMapControls() {
  const btnZoomIn = document.getElementById('btn-zoom-in');
  const btnZoomOut = document.getElementById('btn-zoom-out');
  const btnRecenter = document.getElementById('btn-recenter-map');

  if (btnZoomIn) {
    btnZoomIn.addEventListener('click', () => {
      if (map) map.zoomIn();
    });
  }

  if (btnZoomOut) {
    btnZoomOut.addEventListener('click', () => {
      if (map) map.zoomOut();
    });
  }

  if (btnRecenter) {
    btnRecenter.addEventListener('click', async () => {
      if (!map) return;
      if (centroAtual) {
        map.flyTo(centroAtual, DEFAULT_ZOOM, { duration: 1.2, easeLinearity: 0.25 });
        showToast('📍 Centralizado na região da busca');
        return;
      }
      // Ainda sem centro: pede a posição ao navegador em vez de pular para Suzano.
      const gps = await posicaoDoNavegador();
      if (gps) {
        aplicarCentro(gps.coords, gps.rotulo);
        showToast('📍 Buscando pontos perto da sua localização');
      } else {
        showToast('Pesquise um endereço ou cidade para centralizar o mapa.');
      }
    });
  }
}

/** Posição aprovada pelo usuário (https/localhost); null quando negada ou indisponível. */
function posicaoDoNavegador() {
  return new Promise((resolve) => {
    if (!navigator.geolocation) return resolve(null);
    navigator.geolocation.getCurrentPosition(
      (pos) => {
        const lat = pos.coords.latitude, lng = pos.coords.longitude;
        if (!parValido(lat, lng)) return resolve(null);
        sessionStorage.setItem('passeLivre_gps_lat', String(lat));
        sessionStorage.setItem('passeLivre_gps_lng', String(lng));
        resolve({ coords: [lat, lng], rotulo: 'sua localização' });
      },
      () => resolve(null),
      { enableHighAccuracy: true, timeout: 12000, maximumAge: 60000 }
    );
  });
}

/* ==========================================================================
   5. CAPTURA DE PONTO NO MAPA E REPORTAR NOVA BARREIRA
   ========================================================================== */
function onMapClick(e) {
  const lat = e.latlng.lat.toFixed(5);
  const lng = e.latlng.lng.toFixed(5);
  pontoClicado = { lat: parseFloat(lat), lng: parseFloat(lng) };

  // Se o modal estiver aberto, atualiza o campo de endereço
  const inputAddress = document.getElementById('barrier-address');
  if (!inputAddress) return;
  inputAddress.value = `Coordenadas: ${lat}, ${lng}`;

  // O endereço por extenso vem do geocodificador reverso, para o relato sair
  // com a cidade real do ponto clicado.
  const seq = ++geoClickSeq;
  fetch(`api/geocode.php?action=reverse&lat=${lat}&lng=${lng}`, { headers: { 'Accept': 'application/json' } })
    .then(r => r.json())
    .then(d => {
      if (seq !== geoClickSeq) return;
      const r = d && Array.isArray(d.results) ? d.results[0] : null;
      if (r && r.address) inputAddress.value = `${r.address} (Coordenadas: ${lat}, ${lng})`;
    })
    .catch(() => { /* mantém as coordenadas */ });
}

function initBarrierReporting() {
  const btnOpenModal = document.getElementById('btn-report-barrier');
  const modalBackdrop = document.getElementById('modal-report-barrier');
  const btnCloseModal = document.getElementById('btn-close-barrier-modal');
  const btnCancelModal = document.getElementById('btn-cancel-barrier');
  const formBarrier = document.getElementById('form-report-barrier');

  if (btnOpenModal && modalBackdrop) {
    btnOpenModal.addEventListener('click', (e) => {
      e.preventDefault();
      modalBackdrop.classList.add('active');
    });
  }

  const closeModal = () => {
    if (modalBackdrop) modalBackdrop.classList.remove('active');
  };

  if (btnCloseModal) btnCloseModal.addEventListener('click', closeModal);
  if (btnCancelModal) btnCancelModal.addEventListener('click', closeModal);

  if (modalBackdrop) {
    modalBackdrop.addEventListener('click', (e) => {
      if (e.target === modalBackdrop) closeModal();
    });
  }

  // Envio do formulário de barreira
  if (formBarrier) {
    formBarrier.addEventListener('submit', async (e) => {
      e.preventDefault();

      const inputAddress = document.getElementById('barrier-address');
      const selectCategory = document.getElementById('barrier-category');
      const inputNotes = document.getElementById('barrier-notes');
      const address = ((inputAddress && inputAddress.value) || '').trim();
      const category = ((selectCategory && selectCategory.value) || '').trim();
      const notes = ((inputNotes && inputNotes.value) || '').trim();

      if (!pontoClicado) {
        showToast('📍 Clique no mapa sobre o trecho onde está a barreira.');
        return;
      }

      const btnEnviar = formBarrier.querySelector('button[type="submit"]');
      if (btnEnviar) btnEnviar.disabled = true;
      try {
        const res = await fetch('api/ocorrencias.php?action=create', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          credentials: 'same-origin',
          body: JSON.stringify({
            titulo: `Barreira reportada: ${category || 'Obstáculo na Calçada'}`,
            descricao: notes || address || 'Barreira indicada pelo cidadão no mapa.',
            categoria: category || 'Obstáculo na Calçada',
            endereco: address,
            gravidade: 'media',
            lat: pontoClicado.lat,
            lng: pontoClicado.lng
          })
        });
        const dados = await res.json();
        if (!dados || !dados.success) {
          throw new Error((dados && (dados.error || dados.message)) || 'consulta indisponível');
        }

        desenhaRelatoDeBarreira({
          id: 'oco-' + (dados.protocolo || Date.now()),
          protocolo: dados.protocolo || '',
          coords: [pontoClicado.lat, pontoClicado.lng],
          title: `Novo Relato: ${category}`,
          address: address,
          notes: notes
        });

        closeModal();
        formBarrier.reset();
        pontoClicado = null;
        showToast('✅ Relato ' + (dados.protocolo || '') + ' registrado no ponto escolhido.');
      } catch (erro) {
        showToast('Não foi possível registrar o relato agora: ' + ((erro && erro.message) || 'tente novamente'));
      } finally {
        if (btnEnviar) btnEnviar.disabled = false;
      }
    });
  }
}

/* Marcador do relato aceito pelo backend, desenhado na coordenada clicada. */
function desenhaRelatoDeBarreira(rel) {
  const customIcon = L.divIcon({
    className: 'custom-map-pin pin-red-alert',
    html: `<img src="assets/icons/warning.svg" alt="">`,
    iconSize: [34, 34],
    iconAnchor: [17, 17]
  });

  const marker = L.marker(rel.coords, { icon: customIcon }).addTo(markersLayerGroup);
  marker.bindPopup(`
    <div class="map-custom-popup">
      <span class="popup-tag-badge popup-tag-low">Barreira Reportada</span>
      <h4 class="popup-title">${esc(rel.title)}</h4>
      <p class="popup-address">${esc(rel.address)}</p>
      <p style="font-size: 11px; margin-top: 4px;">${esc(rel.notes)}</p>
      ${rel.protocolo ? `<p style="font-size: 10px; color: #a8a29e; margin-top: 4px;">Protocolo ${esc(rel.protocolo)} — aguardando vistoria</p>` : ''}
    </div>
  `);
  markerInstances[rel.id] = marker;
}

/* ==========================================================================
   6. DOWNLOAD DO RELATÓRIO DE ACESSIBILIDADE
      O botão da lateral é um <a href="relatorios.php"> e leva à tela de
      relatórios, onde a emissão usa os dados do MySQL. Não há PDF gerado aqui,
      então o clique não é mais interceptado para fingir um download.
   ========================================================================== */

/* ==========================================================================
   7. BUSCA GLOBAL DE ENDEREÇOS, BAIRROS E CIDADES
   ========================================================================== */
function initHeaderSearch() {
  const searchInput = document.getElementById('global-search-input');
  const dropdown = document.getElementById('search-dropdown');

  if (!searchInput || !dropdown) return;

  const escapeHtml = (value) => String(value)
    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;').replace(/'/g, '&#39;');

  let apiPlaces = [];
  let geoTimer = null;
  let geoSeq = 0;

  /*
   * Sugestões = endereços devolvidos pelo geocodificador (qualquer cidade do
   * Brasil) + os locais já carregados para a região do mapa. A lista fixa de
   * pontos de Suzano do protótipo saiu do código: ela aparecia para qualquer
   * texto pesquisado e dava a impressão de que a busca só valia lá.
   */
  const paint = (term) => {
    const cadastrados = allMarkersData
      .filter(m => (m.title || '').toLowerCase().includes(term) ||
                   (m.address || '').toLowerCase().includes(term))
      .map(m => ({ name: m.title, address: m.address || '', coords: m.coords }));

    const nomes = new Set(cadastrados.map(l => String(l.name).toLowerCase()));
    const doGeo = apiPlaces
      .filter(p => p && p.name && !nomes.has(String(p.name).toLowerCase()))
      .map(p => ({
        name: p.name,
        address: p.address || '',
        coords: [parseFloat(p.lat), parseFloat(p.lng)]
      }))
      .filter(l => parValido(l.coords[0], l.coords[1]));

    const matches = cadastrados.concat(doGeo);

    if (matches.length > 0) {
      dropdown.innerHTML = matches.map(loc => `
        <div class="search-suggestion-item" data-lat="${loc.coords[0]}" data-lng="${loc.coords[1]}" data-name="${escapeHtml(loc.name)}">
          <img src="assets/icons/pin.svg" alt="">
          <div>
            <div class="search-suggestion-title">${escapeHtml(loc.name)}</div>
            <div class="search-suggestion-sub">${escapeHtml(loc.address)}</div>
          </div>
        </div>
      `).join('');
      dropdown.classList.add('active');

      // Click nas sugestões
      dropdown.querySelectorAll('.search-suggestion-item').forEach(item => {
        item.addEventListener('click', () => {
          const lat = parseFloat(item.dataset.lat);
          const lng = parseFloat(item.dataset.lng);
          const name = item.dataset.name;
          if (!parValido(lat, lng)) return;

          searchInput.value = name;
          sessionStorage.setItem('passeLivre_dest_text', name);
          sessionStorage.setItem('passeLivre_dest_lat', String(lat));
          sessionStorage.setItem('passeLivre_dest_lng', String(lng));
          dropdown.classList.remove('active');
          // A região nova troca o centro do mapa e as duas camadas de pontos.
          aplicarCentro([lat, lng], name);
          showToast(`🔍 ${name}`);
        });
      });
    } else {
      dropdown.innerHTML = `
        <div class="search-suggestion-item" style="cursor: default; color: #a8a29e;">
          Nenhum endereço encontrado para "${escapeHtml(term)}"
        </div>
      `;
      dropdown.classList.add('active');
    }
  };

  const fetchGeo = async (term) => {
    try {
      const res = await fetch(`api/geocode.php?action=search&q=${encodeURIComponent(term)}`, {
        headers: { 'Accept': 'application/json' }
      });
      const data = await res.json();
      if (data && data.success && Array.isArray(data.results)) {
        return data.results;
      }
    } catch (err) {
      /* silencioso: seguem apenas os locais já carregados */
    }
    return null;
  };

  searchInput.addEventListener('input', () => {
    const term = searchInput.value.trim().toLowerCase();
    apiPlaces = [];

    if (term.length < 3) {
      dropdown.classList.remove('active');
      dropdown.innerHTML = '';
      if (geoTimer) { clearTimeout(geoTimer); geoTimer = null; }
      return;
    }

    paint(term);

    const seq = ++geoSeq;
    if (geoTimer) clearTimeout(geoTimer);
    geoTimer = setTimeout(async () => {
      const results = await fetchGeo(term);
      if (results && seq === geoSeq) {
        apiPlaces = results.slice(0, 6);
        paint(term);
      }
    }, 300);
  });

  // Enter confirma a região digitada (endereço, bairro ou cidade).
  searchInput.addEventListener('keydown', async (e) => {
    if (e.key !== 'Enter') return;
    const termo = searchInput.value.trim();
    if (termo.length < 3) {
      showToast('Digite ao menos 3 caracteres para buscar um endereço ou cidade.');
      return;
    }
    if (geoTimer) { clearTimeout(geoTimer); geoTimer = null; }
    dropdown.classList.remove('active');
    const geo = await geocodificar(termo);
    if (!geo) {
      showToast(`Não encontramos "${termo}" no mapa. Tente um endereço mais completo.`);
      return;
    }
    aplicarCentro(geo.coords, geo.rotulo || termo);
    showToast(`🔍 ${geo.rotulo || termo}`);
  });

  // Fechar dropdown ao clicar fora
  document.addEventListener('click', (e) => {
    if (!searchInput.contains(e.target) && !dropdown.contains(e.target)) {
      dropdown.classList.remove('active');
    }
  });
}

/* ==========================================================================
   8. FEEDBACK COM TOAST NOTIFICATION
   ========================================================================== */
function showToast(message) {
  let toast = document.getElementById('app-toast-alert');
  if (!toast) {
    toast = document.createElement('div');
    toast.id = 'app-toast-alert';
    toast.className = 'app-toast-alert';
    document.body.appendChild(toast);
  }

  toast.textContent = message;
  toast.classList.add('show');

  setTimeout(() => {
    toast.classList.remove('show');
  }, 2800);
}

/* ==========================================================================
   INTEGRAÇÃO SQL — registra auditoria de acesso ao mapa
   ========================================================================== */
document.addEventListener('DOMContentLoaded', async () => {
  if (!window.PasseLivreDBReady) return;
  try {
    await window.PasseLivreDBReady;
    if (!window.PLDB) return;

    // Os marcadores já vêm de api/locais.php; aqui só fica o registro de auditoria.
    if (window.PLDBAuditLog) {
      await window.PLDBAuditLog('sistema', 'Mapa Geral Acessado', 'Usuário visualizou o mapa de acessibilidade', 'baixo');
    }
  } catch (e) {
    console.warn('[mapa-geral] SQL:', e);
  }
});
