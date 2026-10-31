/**
 * PASSE LIVRE — NAVEGAÇÃO EM TEMPO REAL
 * Script de Controle do Mapa em Tela Cheia, HUD de Manobras Google Maps Style,
 * Telemetria de Trajeto, Áudio e Acessibilidade NBR 9050.
 */

document.addEventListener('DOMContentLoaded', () => {
  initNavigationMap();
  initNavigationControls();
  initNavigationCardToggle();
  initAudioAndSpeech();
  initRecalculateAction();
  initEndNavigation();

  // Sair/recarregar a página encerra o rastreador; nenhuma posição é inventada
  window.addEventListener('pagehide', stopPositionTracking);
});

function initNavigationCardToggle() {
  const toggle = document.getElementById('navigation-card-toggle');
  const details = document.getElementById('navigation-card-details');
  if (!toggle || !details) return;

  toggle.addEventListener('click', () => {
    const expanded = toggle.getAttribute('aria-expanded') !== 'true';
    toggle.setAttribute('aria-expanded', String(expanded));
    details.hidden = !expanded;
    toggle.querySelector('span').textContent = expanded
      ? 'Ver menos detalhes'
      : 'Ver detalhes e apoio';
  });
}

window.addEventListener('pagehide', stopPositionTracking);

/* Contexto entregue pelo PHP: rota escolhida, geometria, passos, perfil e
   endpoints. A tela não conhece nenhuma coordenada por conta própria. */
const NAV_CONTEXT = (() => {
  try {
    const el = document.getElementById('nav-route-context');
    const data = el ? JSON.parse(el.textContent || '{}') : {};
    return data && typeof data === 'object' ? data : {};
  } catch (err) {
    return {};
  }
})();

const PERFIS_VALIDOS = ['foot-walking', 'wheelchair'];

/* Variáveis Globais de Estado */
let navMap = null;
let routePolyline = null;
let routeDashLine = null;
let userMarker = null;
let calloutMarker = null;
let destMarker = null;
let isVoiceEnabled = true;

/* Traçado ainda não confirmado pelo serviço de rotas */
let tracoAproximado = true;

let liveRouteCoords = [];

/* ==========================================================================
   ESTADO DA NAVEGAÇÃO AO VIVO
   Sem origem e destino reais nada é traçado: a tela pede os pontos em vez de
   inventar um percurso.
   ========================================================================== */
const DEVIATION_LIMIT_M = 30;
const RECALC_MIN_INTERVAL_MS = 15000;
const GPS_MIN_INTERVAL_MS = 1000;

let LIVE_ROUTE_STEPS = [];
let STEP_END_METERS = [];
let liveRouteData = null;
let liveRouteLengthM = 0;
let navProgressM = 0;
let navWatchId = null;
let navSessionId = null;
let currentStepIndex = 0;
let lastRecalcAt = 0;
let navGpsPos = null;
let navGpsAccuracy = null;
let navInicioEm = null;
let navMetricsTimer = null;
let navRecalcEmAndamento = false;
let navRequestCtrl = null;
let navConsultaAssinatura = '';
let navUltimaFalhaRota = '';
let navGpsErroAvisado = false;
let navTileAvisado = false;
let navUltimoProcessamento = 0;
let navRecalcTimer = null;

let userLivePos = null;
let destinationLivePos = null;

/* Só entra como ponto real o par que passar pela mesma sanidade do backend
   (includes/geo_centro.php): 0,0 ou um ponto fora do Brasil não é endereço. */
const PONTOS_REAIS = { origem: false, destino: false };

function parValido(lat, lng) {
  return lat !== null && lng !== null
    && Number.isFinite(lat) && Number.isFinite(lng)
    && lat >= -35 && lat <= 6 && lng >= -75 && lng <= -30;
}

/* Origem e destino reais — sem os dois, nada é calculado. */
function rotaCalculavel() {
  return PONTOS_REAIS.origem && PONTOS_REAIS.destino;
}

function _toNumber(value) {
  const n = parseFloat(value);
  return Number.isFinite(n) ? n : null;
}

function _pontoDoContexto(obj) {
  const lat = _toNumber(obj && obj.lat);
  const lng = _toNumber(obj && obj.lng);
  return parValido(lat, lng) ? [lat, lng] : null;
}

function resolveNavigationEndpoints() {
  const ctxOrigem = _pontoDoContexto(NAV_CONTEXT.origem);
  const ctxDestino = _pontoDoContexto(NAV_CONTEXT.destino);

  const gpsLat = _toNumber(sessionStorage.getItem('passeLivre_gps_lat'));
  const gpsLng = _toNumber(sessionStorage.getItem('passeLivre_gps_lng'));
  const gpsSessao = parValido(gpsLat, gpsLng) ? [gpsLat, gpsLng] : null;

  const manualLat = _toNumber(sessionStorage.getItem('passeLivre_origem_manual_lat'));
  const manualLng = _toNumber(sessionStorage.getItem('passeLivre_origem_manual_lng'));
  const manualSessao = parValido(manualLat, manualLng) ? [manualLat, manualLng] : null;

  const destLat = _toNumber(sessionStorage.getItem('passeLivre_dest_lat'));
  const destLng = _toNumber(sessionStorage.getItem('passeLivre_dest_lng'));
  const destSessao = parValido(destLat, destLng) ? [destLat, destLng] : null;

  const origin = ctxOrigem || gpsSessao || manualSessao || null;
  const destination = ctxDestino || destSessao || null;

  PONTOS_REAIS.origem = origin !== null;
  PONTOS_REAIS.destino = destination !== null;

  return { origin, destination };
}

function navDestinationName() {
  const doContexto = String((NAV_CONTEXT.destino && NAV_CONTEXT.destino.texto) || '').trim();
  if (doContexto) return doContexto;
  return String(sessionStorage.getItem('passeLivre_dest_text') || '').trim();
}

/* Perfil vem da rota salva; sem ela, da preferência PCD já normalizada no PHP. */
function resolveNavigationProfile() {
  const doContexto = String(NAV_CONTEXT.perfil || '');
  if (PERFIS_VALIDOS.includes(doContexto)) return doContexto;
  const padrao = String(NAV_CONTEXT.perfil_padrao || '');
  if (PERFIS_VALIDOS.includes(padrao)) return padrao;
  const user = window.PasseLivreAuth && window.PasseLivreAuth.getUser ? window.PasseLivreAuth.getUser() : null;
  const cats = [].concat(user?.pcdCategories || [], user?.savedPreferences || [])
    .map(c => String(c).toLowerCase());
  return cats.some(c => ['cadeirante', 'mobilidade', 'cadeira', 'motora', 'fisica'].some(k => c.includes(k)))
    ? 'wheelchair'
    : 'foot-walking';
}

/* Distância Haversine em metros entre dois pontos [lat,lng] */
function haversineMeters(a, b) {
  const R = 6371000;
  const toRad = (v) => (v * Math.PI) / 180;
  const dLat = toRad(b[0] - a[0]);
  const dLng = toRad(b[1] - a[1]);
  const lat1 = toRad(a[0]);
  const lat2 = toRad(b[0]);
  const h = Math.sin(dLat / 2) ** 2 + Math.cos(lat1) * Math.cos(lat2) * Math.sin(dLng / 2) ** 2;
  return 2 * R * Math.asin(Math.min(1, Math.sqrt(h)));
}

function toRadiansSafe(latDeg) {
  const r = Math.cos((latDeg * Math.PI) / 180);
  return Math.abs(r) < 1e-9 ? 1e-9 : r;
}

/* Projeta a posição no traçado: desvio em metros e distância percorrida a
   partir da origem da rota. */
function progressoNaRota(point, coords) {
  const vazio = { distancia: Infinity, progresso_m: 0, total_m: 0, ponto: null, indice: 0 };
  if (!point || !Array.isArray(coords) || coords.length < 2) return vazio;

  let acumulado = 0;
  let melhor = vazio;
  for (let i = 0; i < coords.length - 1; i++) {
    const a = coords[i];
    const b = coords[i + 1];
    const segmento = haversineMeters(a, b);
    const cosLat = toRadiansSafe(a[0]);
    const ax = a[1] * cosLat, ay = a[0];
    const bx = b[1] * cosLat, by = b[0];
    const px = point[1] * cosLat, py = point[0];
    const dx = bx - ax, dy = by - ay;
    const len2 = dx * dx + dy * dy;
    let t = len2 > 0 ? ((px - ax) * dx + (py - ay) * dy) / len2 : 0;
    t = Math.max(0, Math.min(1, t));
    const proj = [ay + t * dy, (ax + t * dx) / cosLat];
    const d = haversineMeters(point, proj);
    if (d < melhor.distancia) {
      melhor = { distancia: d, progresso_m: acumulado + t * segmento, total_m: 0, ponto: proj, indice: i };
    }
    acumulado += segmento;
  }
  melhor.total_m = acumulado;
  return melhor;
}

function calcularFinsDosPassos(totalM) {
  STEP_END_METERS = [];
  let acc = 0;
  LIVE_ROUTE_STEPS.forEach((step) => {
    acc += Math.max(0, Number(step.distance_m) || 0);
    STEP_END_METERS.push(acc);
  });
  if (STEP_END_METERS.length && totalM > 0) {
    const ultimo = STEP_END_METERS[STEP_END_METERS.length - 1];
    if (ultimo > 0) {
      STEP_END_METERS = STEP_END_METERS.map(v => Math.min(totalM, (v / ultimo) * totalM));
    }
  }
}

/* ==========================================================================
   1. INICIALIZAÇÃO DO MAPA LEAFLET DE TELA CHEIA
   ========================================================================== */
function initNavigationMap() {
  const mapElement = document.getElementById('nav-map');
  if (!mapElement) return;

  const endpoints = resolveNavigationEndpoints();
  const coordsSalvas = (Array.isArray(NAV_CONTEXT.coords) ? NAV_CONTEXT.coords : [])
    .map(c => [Number(c && c[0]), Number(c && c[1])])
    .filter(c => parValido(c[0], c[1]));

  if (coordsSalvas.length >= 2) {
    liveRouteCoords = coordsSalvas;
    tracoAproximado = false;
    endpoints.origin = endpoints.origin || liveRouteCoords[0];
    endpoints.destination = endpoints.destination || liveRouteCoords[liveRouteCoords.length - 1];
    PONTOS_REAIS.origem = true;
    PONTOS_REAIS.destino = true;
  }

  userLivePos = endpoints.origin;
  destinationLivePos = endpoints.destination
    || (liveRouteCoords.length ? liveRouteCoords[liveRouteCoords.length - 1] : null);

  // Instância do Leaflet com suporte a arrastar, zoom e rotação suave
  const visao = visaoDaRota(endpoints);
  navMap = L.map('nav-map', {
    center: visao.center,
    zoom: visao.zoom,
    minZoom: 5,
    maxZoom: 19,
    zoomControl: false, // Usamos os controles customizados dedicados
    attributionControl: true
  });

  // Camada base: raster do OpenStreetMap. A CARTO passou a exigir chave e devolvia
  // o bloco "API KEY REQUIRED" em todos os tiles; o filtro quente continua no CSS.
  const camadaBase = L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
  }).addTo(navMap);
  camadaBase.on('tileerror', () => {
    if (navTileAvisado) return;
    navTileAvisado = true;
    showToast('O mapa base não carregou. O traçado e as instruções continuam disponíveis.');
  });

  if (liveRouteCoords.length >= 2) drawNavigationRoute();

  // Adiciona o marcador do usuário com halo pulsante e etiqueta
  addUserPositionMarker();

  // Adiciona os cartões flutuantes e marcadores ao longo da via
  addWaypointElements();

  atualizarPainelRota();

  const rotaSalva = liveRouteCoords.length >= 2 && NAV_CONTEXT.rota_encontrada === true;
  if (rotaSalva) {
    aplicarRotaSalva();
    startPositionTracking();
    startNavSession();
    return;
  }

  if (NAV_CONTEXT.rota_solicitada > 0 && NAV_CONTEXT.rota_encontrada !== true) {
    showToast('A rota informada não foi encontrada para este usuário. Escolha novamente na configuração.');
  }

  if (rotaCalculavel()) {
    // Sem rota salva (acesso direto): calcula de verdade a partir dos pontos reais
    startLiveNavigation();
  } else {
    const falta = PONTOS_REAIS.destino ? 'sua localização' : 'um destino';
    definirSemRota(`Sem trajeto para seguir: falta ${falta}. Volte à configuração de rota para informar os pontos.`);
    startPositionTracking();
  }
}

/* Enquadramento que comporta o traçado inteiro — nunca um zoom fixo de bairro
   para uma rota entre cidades. */
function visaoDaRota(endpoints) {
  const pontos = liveRouteCoords.length >= 2
    ? liveRouteCoords
    : [endpoints.origin, endpoints.destination].filter(p => p);

  if (!pontos.length) return { center: [-14.235, -51.925], zoom: 4 };
  if (pontos.length === 1) return { center: pontos[0], zoom: 14 };

  let minLat = 90, maxLat = -90, minLng = 180, maxLng = -180;
  pontos.forEach((p) => {
    minLat = Math.min(minLat, p[0]);
    maxLat = Math.max(maxLat, p[0]);
    minLng = Math.min(minLng, p[1]);
    maxLng = Math.max(maxLng, p[1]);
  });

  const center = [(minLat + maxLat) / 2, (minLng + maxLng) / 2];
  const larguraKm = haversineMeters([minLat, minLng], [minLat, maxLng]) / 1000;
  const alturaKm = haversineMeters([minLat, minLng], [maxLat, minLng]) / 1000;
  const km = Math.max(larguraKm, alturaKm);
  return {
    center,
    zoom: km > 60 ? 9 : km > 20 ? 10.5 : km > 8 ? 12 : km > 3 ? 13 : km > 1 ? 14 : 15.5
  };
}

function comprimentoDoTraco(coords) {
  let total = 0;
  for (let i = 0; i < coords.length - 1; i++) {
    total += haversineMeters(coords[i], coords[i + 1]);
  }
  return total;
}

/* ==========================================================================
   2B. ROTA REAL E POSICIONAMENTO AO VIVO
   ========================================================================== */
async function startLiveNavigation() {
  if (!rotaCalculavel()) {
    const falta = PONTOS_REAIS.destino ? 'sua localização' : 'um destino';
    definirSemRota(`Sem trajeto para seguir: falta ${falta}. Volte à configuração de rota para informar os pontos.`);
    showToast(PONTOS_REAIS.destino
      ? 'Ative o GPS ou informe sua origem para calcular o trajeto até este destino.'
      : 'Escolha um destino na busca para calcular o trajeto.');
    startPositionTracking();
    return;
  }

  definirCalloutRota('Calculando trajeto', 'Consultando o serviço de rotas...');
  const ok = await loadLiveRoute(userLivePos, destinationLivePos);
  if (!ok) {
    definirSemRota(navUltimaFalhaRota || 'Não foi possível calcular o trajeto agora.');
    showToast(navUltimaFalhaRota || 'O serviço de rotas não respondeu. Nenhum traçado foi desenhado.');
  }
  startPositionTracking();
}

/* Rota escolhida em configuracao-rota.php e gravada no banco: é esta geometria,
   este perfil e estes passos que a navegação segue. */
function aplicarRotaSalva() {
  const distancia = Number(NAV_CONTEXT.distancia_m);
  const duracaoS = Number(NAV_CONTEXT.duracao_s);
  const duracaoMin = Number(NAV_CONTEXT.duracao_min);

  liveRouteLengthM = comprimentoDoTraco(liveRouteCoords);
  liveRouteData = {
    distance_m: Number.isFinite(distancia) && distancia > 0 ? distancia : liveRouteLengthM,
    duration_s: Number.isFinite(duracaoS) ? duracaoS : (Number.isFinite(duracaoMin) ? duracaoMin * 60 : null),
    duration_min: Number.isFinite(duracaoMin) ? duracaoMin : null,
    profile_effective: resolveNavigationProfile(),
    provider: String(NAV_CONTEXT.provedor || ''),
    provider_label: String(NAV_CONTEXT.provedor_rotulo || '')
  };

  LIVE_ROUTE_STEPS = (Array.isArray(NAV_CONTEXT.passos) ? NAV_CONTEXT.passos : [])
    .filter(p => p && String(p.instrucao || '').trim() !== '')
    .map(p => ({
      instruction: String(p.instrucao).trim().slice(0, 300),
      distance_m: Math.max(0, Number(p.distancia_m) || 0)
    }));

  currentStepIndex = 0;
  navProgressM = 0;
  calcularFinsDosPassos(liveRouteData.distance_m);
  atualizarEstadoDaRota(true, liveRouteData.provider_label);
  updateRemainingMetrics(liveRouteData);

  const provedor = liveRouteData.provider_label ? ` · ${liveRouteData.provider_label}` : '';
  definirCalloutRota('Rota carregada', `Acessibilidade não verificada em campo${provedor}`);
  if (LIVE_ROUTE_STEPS.length) showStep(0, false, 0);
  lastRecalcAt = Date.now();
}

async function loadLiveRoute(origin, destination) {
  if (!origin || !destination) {
    navUltimaFalhaRota = 'Origem ou destino ausente: não há como consultar o roteador.';
    return false;
  }

  const perfil = resolveNavigationProfile();
  const assinatura = `${origin[0]},${origin[1]}|${destination[0]},${destination[1]}|${perfil}`;
  if (navRequestCtrl) {
    if (assinatura === navConsultaAssinatura) return false;
    navRequestCtrl.abort();
  }

  const ctrl = new AbortController();
  navRequestCtrl = ctrl;
  navConsultaAssinatura = assinatura;
  const limite = setTimeout(() => ctrl.abort(), 12000);
  navUltimaFalhaRota = '';

  const params = new URLSearchParams({
    profile: perfil,
    origin_lat: origin[0],
    origin_lng: origin[1],
    dest_lat: destination[0],
    dest_lng: destination[1],
    alternatives: '1'
  });
  const api = (NAV_CONTEXT.api && NAV_CONTEXT.api.rota) || 'api/rota.php';

  try {
    const res = await fetch(api + '?' + params.toString(), {
      headers: { 'Accept': 'application/json' },
      credentials: 'same-origin',
      signal: ctrl.signal
    });
    const data = await res.json().catch(() => null);
    if (ctrl.signal.aborted) return false;

    if (!res.ok) {
      if (res.status === 401) {
        navUltimaFalhaRota = 'Sua sessão expirou. Entre novamente para recalcular o trajeto.';
      } else if (res.status === 429) {
        navUltimaFalhaRota = 'Limite de consultas ao roteador atingido. Aguarde alguns minutos.';
      } else {
        navUltimaFalhaRota = (data && data.message) || 'O serviço de rotas não respondeu.';
      }
      return false;
    }

    if (!data || data.success !== true) {
      navUltimaFalhaRota = (data && data.message) || 'O serviço de rotas não devolveu um trajeto válido.';
      return false;
    }

    if (!applyLiveRoute(data)) {
      navUltimaFalhaRota = 'O trajeto recebido não tem geometria válida.';
      return false;
    }
    return true;
  } catch (err) {
    if (ctrl.signal.aborted) return false;
    navUltimaFalhaRota = 'Não foi possível conectar ao serviço de rotas. Verifique sua conexão.';
    return false;
  } finally {
    clearTimeout(limite);
    if (navRequestCtrl === ctrl) {
      navRequestCtrl = null;
      navConsultaAssinatura = '';
    }
  }
}

function applyLiveRoute(data) {
  const coords = (Array.isArray(data.coords) ? data.coords : [])
    .map(c => [Number(c && c[0]), Number(c && c[1])])
    .filter(c => parValido(c[0], c[1]));
  if (coords.length < 2) return false;

  const distancia = Number(data.distance_m);
  const duracaoS = Number(data.duration_s);
  const duracaoMin = Number(data.duration_min);

  liveRouteCoords = coords;
  liveRouteLengthM = comprimentoDoTraco(coords);
  liveRouteData = {
    distance_m: Number.isFinite(distancia) && distancia > 0 ? distancia : liveRouteLengthM,
    duration_s: Number.isFinite(duracaoS) ? duracaoS : (Number.isFinite(duracaoMin) ? duracaoMin * 60 : null),
    duration_min: Number.isFinite(duracaoMin) ? duracaoMin : null,
    profile_effective: String(data.profile_effective || data.profile || resolveNavigationProfile()),
    provider: String(data.provider || ''),
    provider_label: String(data.provider_label || '')
  };

  tracoAproximado = false;
  if (!routePolyline || !routeDashLine) {
    drawNavigationRoute();
  } else {
    routePolyline.setLatLngs(liveRouteCoords);
    routeDashLine.setLatLngs(liveRouteCoords);
    aplicarEstiloDoTraco();
  }

  LIVE_ROUTE_STEPS = (Array.isArray(data.steps) ? data.steps : [])
    .filter(s => s && String(s.instruction || '').trim() !== '')
    .map(s => ({
      instruction: String(s.instruction).trim().slice(0, 300),
      distance_m: Math.max(0, Number(s.distance_m) || 0)
    }));

  currentStepIndex = 0;
  navProgressM = 0;
  calcularFinsDosPassos(liveRouteData.distance_m);
  atualizarEstadoDaRota(true, liveRouteData.provider_label);
  updateRemainingMetrics(liveRouteData);
  if (LIVE_ROUTE_STEPS.length) showStep(0, false, 0);

  if (liveRouteData.profile_effective !== resolveNavigationProfile()) {
    showToast('O roteador devolveu um perfil diferente do solicitado; o trajeto não foi otimizado para o seu perfil.');
  }

  const provedor = liveRouteData.provider_label ? ` · ${liveRouteData.provider_label}` : '';
  definirCalloutRota('Trajeto calculado', `Acessibilidade não verificada em campo${provedor}`);
  lastRecalcAt = Date.now();
  return true;
}

function atualizarEstadoDaRota(ativa, rotuloProvedor) {
  const pill = document.getElementById('route-active-pill');
  if (!pill) return;
  const provedor = String(rotuloProvedor || NAV_CONTEXT.provedor_rotulo || '');
  pill.textContent = ativa ? (provedor ? `Rota calculada · ${provedor}` : 'Rota calculada') : 'Sem rota carregada';
  pill.title = ativa
    ? 'Trajeto calculado por serviço de roteamento; acessibilidade não verificada em campo'
    : 'Nenhum trajeto calculado';
}

function formatarDistanciaRestante(metros) {
  if (!Number.isFinite(metros)) return '--';
  if (metros >= 1000) {
    return `${(metros / 1000).toLocaleString('pt-BR', { maximumFractionDigits: 1 })} km`;
  }
  return `${Math.round(metros)} m`;
}

function formatarDuracaoMin(minutos) {
  if (!Number.isFinite(minutos) || minutos < 0) return '--';
  if (minutos < 1) return '< 1 min';
  return `${Math.round(minutos)} min`;
}

/* Tempo/distância restantes vêm da posição real no traçado. O tempo é a
   estimativa do roteador; o tempo decorrido é contado desde o primeiro GPS. */
function updateRemainingMetrics(data, restanteM) {
  const timeEl = document.getElementById('metric-remaining-time');
  const distEl = document.getElementById('metric-remaining-dist');
  const etaEl = document.getElementById('metric-eta-time');

  const distTotal = Number(data && data.distance_m);
  const durTotalMin = Number.isFinite(Number(data && data.duration_min))
    ? Number(data.duration_min)
    : (Number.isFinite(Number(data && data.duration_s)) ? Number(data.duration_s) / 60 : null);
  let restante = Number.isFinite(Number(restanteM)) ? Math.max(0, Number(restanteM)) : distTotal;
  if (!Number.isFinite(restante)) restante = null;

  let restanteMin = null;
  if (Number.isFinite(durTotalMin)) {
    restanteMin = Number.isFinite(distTotal) && distTotal > 0 && restante !== null
      ? durTotalMin * Math.min(1, restante / distTotal)
      : durTotalMin;
  }

  if (timeEl) {
    timeEl.textContent = formatarDuracaoMin(restanteMin);
    timeEl.title = 'Estimativa do roteador a partir da distância restante';
  }
  if (distEl) {
    distEl.textContent = restante !== null ? formatarDistanciaRestante(restante) : '--';
    distEl.title = navProgressM > 0
      ? 'Distância restante calculada a partir da sua posição no trajeto'
      : 'Distância total do trajeto calculado';
  }
  if (etaEl) {
    if (restanteMin === null) {
      etaEl.textContent = 'Sem rota calculada';
    } else {
      const eta = new Date(Date.now() + restanteMin * 60000);
      let texto = 'Chegada prevista ' + eta.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
      if (navInicioEm) {
        const decorrido = Math.max(0, Math.floor((Date.now() - navInicioEm) / 60000));
        texto += ` · no trajeto há ${decorrido} min`;
      }
      etaEl.textContent = texto;
      etaEl.title = 'Previsão do roteador; o tempo decorrido é real, desde a primeira posição do GPS';
    }
  }
}

/* Nome real do destino no painel e estado honesto quando falta um lado da rota. */
function atualizarPainelRota() {
  const destLabel = document.getElementById('route-dest-text');
  const nome = navDestinationName();
  if (destLabel) destLabel.textContent = 'Destino: ' + (nome || 'não informado');

  const pill = document.getElementById('route-active-pill');
  if (pill) pill.textContent = 'Sem rota carregada';

  // As medidas impressas no HTML são traços: só viram número com rota real.
  const instrEl = document.getElementById('turn-instruction-main');
  const distEl = document.getElementById('turn-dist-val');
  const timeEl = document.getElementById('metric-remaining-time');
  const distMetric = document.getElementById('metric-remaining-dist');
  const etaEl = document.getElementById('metric-eta-time');

  if (timeEl) timeEl.textContent = '--';
  if (distMetric) distMetric.textContent = '--';
  if (etaEl) etaEl.textContent = 'Sem rota calculada';

  if (rotaCalculavel()) {
    if (instrEl) instrEl.textContent = 'Calculando o trajeto...';
    if (distEl) distEl.textContent = 'Aguarde';
    return;
  }

  if (instrEl) instrEl.textContent = `Informe ${PONTOS_REAIS.destino ? 'sua localização' : 'um destino'} para traçar a rota.`;
  if (distEl) distEl.textContent = 'Aguardando';
}

function definirSemRota(mensagem) {
  liveRouteData = null;
  liveRouteCoords = [];
  LIVE_ROUTE_STEPS = [];
  STEP_END_METERS = [];
  navProgressM = 0;
  liveRouteLengthM = 0;
  tracoAproximado = true;

  if (routePolyline && navMap) { navMap.removeLayer(routePolyline); routePolyline = null; }
  if (routeDashLine && navMap) { navMap.removeLayer(routeDashLine); routeDashLine = null; }
  if (destMarker && navMap) { navMap.removeLayer(destMarker); destMarker = null; }

  const instrEl = document.getElementById('turn-instruction-main');
  const distEl = document.getElementById('turn-dist-val');
  const timeEl = document.getElementById('metric-remaining-time');
  const distMetric = document.getElementById('metric-remaining-dist');
  const etaEl = document.getElementById('metric-eta-time');

  if (instrEl) instrEl.textContent = mensagem || 'Sem trajeto calculado.';
  if (distEl) distEl.textContent = '--';
  if (timeEl) timeEl.textContent = '--';
  if (distMetric) distMetric.textContent = '--';
  if (etaEl) etaEl.textContent = 'Sem rota calculada';

  atualizarEstadoDaRota(false);
  definirCalloutRota('Sem trajeto calculado', 'Volte à configuração de rota para escolher um destino');
}

/* Mostra o passo real da rota no HUD e (opcionalmente) fala a instrução.
   A distância exibida é o quanto falta deste trecho até a próxima manobra. */
function showStep(index, speak, progressoM) {
  const step = LIVE_ROUTE_STEPS[index];
  if (!step) return;

  const instrEl = document.getElementById('turn-instruction-main');
  const distEl = document.getElementById('turn-dist-val');
  const progresso = Number.isFinite(Number(progressoM)) ? Math.max(0, Number(progressoM)) : 0;
  const total = Number(liveRouteData && liveRouteData.distance_m);

  if (instrEl) instrEl.textContent = step.instruction;
  if (distEl) {
    let restante = null;
    if (Number.isFinite(STEP_END_METERS[index])) {
      restante = Math.max(0, STEP_END_METERS[index] - progresso);
    } else if (Number.isFinite(total)) {
      restante = Math.max(0, total - progresso);
    }
    distEl.textContent = restante === null
      ? 'Trecho sem distância informada'
      : (restante <= 5 ? 'Agora' : `Em ${formatarDistanciaRestante(restante)}`);
  }
  if (speak) speakInstruction(step.instruction);
}

/* ==========================================================================
   2C. GPS EM TEMPO REAL (watchPosition) E DESVIO
   ========================================================================== */
function definirEstadoGps(estado, texto) {
  const badge = document.getElementById('gps-status-badge');
  const span = document.getElementById('gps-status-text');
  if (span) span.textContent = texto;
  if (!badge) return;
  badge.classList.toggle('is-off', estado !== 'ativo');
  badge.title = texto;
}

function startPositionTracking() {
  if (!('geolocation' in navigator)) {
    definirEstadoGps('indisponivel', 'GPS não suportado neste navegador');
    showToast('Este navegador não expõe GPS. A origem do trajeto é usada sem atualização em tempo real.');
    return;
  }
  if (window.isSecureContext !== true) {
    definirEstadoGps('indisponivel', 'GPS indisponível: exige HTTPS ou localhost');
    showToast('O GPS do navegador só funciona em HTTPS ou localhost. Abra o site por um endereço seguro para atualizar sua posição.');
    return;
  }
  if (navWatchId !== null) return;

  definirEstadoGps('aguardando', 'GPS aguardando sinal');
  let recebeuPosicao = false;

  navWatchId = navigator.geolocation.watchPosition(
    (pos) => {
      const lat = Number(pos.coords.latitude);
      const lng = Number(pos.coords.longitude);
      if (!parValido(lat, lng)) return;

      recebeuPosicao = true;
      navGpsErroAvisado = false;
      const precisao = Number(pos.coords.accuracy);
      navGpsAccuracy = Number.isFinite(precisao) ? precisao : null;
      definirEstadoGps('ativo', navGpsAccuracy !== null
        ? `GPS ativo · precisão ±${Math.round(navGpsAccuracy)} m`
        : 'GPS ativo');

      atualizarComPosicao([lat, lng]);
    },
    (err) => {
      // Um erro pontual depois de já ter posição boa não derruba a navegação
      if (recebeuPosicao && (!err || err.code !== err.PERMISSION_DENIED)) return;

      if (err && err.code === err.PERMISSION_DENIED) {
        stopPositionTracking();
        definirEstadoGps('negado', 'GPS não autorizado');
        showToast('Permissão de localização negada. O mapa mantém a origem do trajeto; a posição não será atualizada em tempo real.');
        return;
      }

      definirEstadoGps('sem-sinal', 'GPS sem sinal');
      if (!navGpsErroAvisado) {
        navGpsErroAvisado = true;
        showToast('Não foi possível ler o GPS agora. O mapa mantém a última posição conhecida.');
      }
    },
    { enableHighAccuracy: true, timeout: 10000, maximumAge: 5000 }
  );

  if (navMetricsTimer === null) {
    navMetricsTimer = window.setInterval(() => {
      if (!liveRouteData) return;
      updateRemainingMetrics(liveRouteData, restanteDaRota());
    }, 15000);
  }
}

function atualizarComPosicao(pos) {
  const agora = Date.now();
  if (agora - navUltimoProcessamento < GPS_MIN_INTERVAL_MS) {
    // Entre amostras só o marcador se move; nada é recalculado em excesso
    userLivePos = pos;
    if (userMarker) userMarker.setLatLng(pos);
    return;
  }
  navUltimoProcessamento = agora;

  if (!navInicioEm) navInicioEm = agora;
  navGpsPos = pos;
  userLivePos = pos;
  if (userMarker) {
    userMarker.setLatLng(pos);
    atualizarRotuloUsuario(true);
  }
  sessionStorage.setItem('passeLivre_gps_lat', String(pos[0]));
  sessionStorage.setItem('passeLivre_gps_lng', String(pos[1]));

  if (!liveRouteData || liveRouteCoords.length < 2) return;

  const info = progressoNaRota(pos, liveRouteCoords);
  const totalRoteador = Number(liveRouteData.distance_m);
  const escala = liveRouteLengthM > 0 && Number.isFinite(totalRoteador) && totalRoteador > 0
    ? totalRoteador / liveRouteLengthM
    : 1;
  navProgressM = Math.min(Number.isFinite(totalRoteador) ? totalRoteador : Infinity, info.progresso_m * escala);
  if (!Number.isFinite(navProgressM)) navProgressM = 0;

  atualizarPassoPorProgresso(navProgressM);
  updateRemainingMetrics(liveRouteData, restanteDaRota());

  // Recalcula somente após 15 s e com desvio acima do limite
  if (info.distancia > DEVIATION_LIMIT_M) checkDeviation(pos, info.distancia);
}

function restanteDaRota() {
  if (!liveRouteData) return null;
  const total = Number(liveRouteData.distance_m);
  if (!Number.isFinite(total)) return null;
  return Math.max(0, total - navProgressM);
}

function atualizarPassoPorProgresso(progressoM) {
  if (!LIVE_ROUTE_STEPS.length) return;
  let indice = currentStepIndex;
  while (indice < LIVE_ROUTE_STEPS.length - 1
    && Number.isFinite(STEP_END_METERS[indice])
    && progressoM >= STEP_END_METERS[indice] - 5) {
    indice += 1;
  }
  if (indice !== currentStepIndex) {
    currentStepIndex = indice;
    showStep(currentStepIndex, true, progressoM);
  } else {
    showStep(currentStepIndex, false, progressoM);
  }
}

function atualizarRotuloUsuario(temGps) {
  const label = document.querySelector('.nav-user-label');
  if (label) label.textContent = temGps ? 'Sua localização' : 'Origem do trajeto';
}

function checkDeviation(point, desvioM) {
  if (!liveRouteData || !liveRouteCoords.length) return;
  if (navRecalcEmAndamento) return;
  if (!Number.isFinite(desvioM) || desvioM <= DEVIATION_LIMIT_M) return;
  const agora = Date.now();
  const faltamMs = RECALC_MIN_INTERVAL_MS - (agora - lastRecalcAt);
  if (faltamMs > 0) {
    /* Dentro do intervalo mínimo: o desvio fica pendente e o recálculo sai
       quando o intervalo vencer, mesmo que o GPS pare de emitir novas posições. */
    agendarRecalculoPorDesvio(faltamMs);
    return;
  }
  lastRecalcAt = agora;
  recalculateRoute(point, desvioM, true);
}

function agendarRecalculoPorDesvio(atrasoMs) {
  if (navRecalcTimer !== null) return;
  navRecalcTimer = window.setTimeout(() => {
    navRecalcTimer = null;
    if (navRecalcEmAndamento || !liveRouteData || !userLivePos) return;
    const info = progressoNaRota(userLivePos, liveRouteCoords);
    if (!Number.isFinite(info.distancia) || info.distancia <= DEVIATION_LIMIT_M) return;
    lastRecalcAt = Date.now();
    recalculateRoute(userLivePos, info.distancia, true);
  }, Math.max(0, atrasoMs));
}

async function recalculateRoute(from, desvioM, automatico) {
  if (navRecalcEmAndamento) return false;
  if (!from || !parValido(from[0], from[1]) || !destinationLivePos) {
    if (!automatico) showToast('Não há origem e destino reais para recalcular.');
    return false;
  }

  navRecalcEmAndamento = true;
  if (automatico) {
    showToast(`Você saiu do trajeto (${Math.round(desvioM)} m). Recalculando o caminho...`);
  }

  const ok = await loadLiveRoute(from, destinationLivePos);
  navRecalcEmAndamento = false;

  if (!ok) {
    showToast(navUltimaFalhaRota || 'Não foi possível recalcular agora. O trajeto atual será mantido.');
    return false;
  }

  await registerRecalc();
  if (automatico) showToast('Rota recalculada a partir da sua posição atual.');
  return true;
}

/* Sessão de navegação no backend — sempre da rota escolhida (rota_id da URL). */
async function startNavSession() {
  const rotaId = Number(NAV_CONTEXT.rota_id) || 0;
  if (!rotaId || navSessionId) return;
  try {
    const api = (NAV_CONTEXT.api && NAV_CONTEXT.api.rotas) || 'api/rotas.php';
    const res = await fetch(api + '?action=nav_start', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      credentials: 'same-origin',
      body: JSON.stringify({ rota_id: rotaId })
    });
    const data = await res.json().catch(() => null);
    if (data && data.success) navSessionId = data.navegacao_id;
  } catch (err) {
    /* silencioso: a navegação continua mesmo sem registrar no banco */
  }
}

async function registerRecalc() {
  if (!navSessionId) await startNavSession();
  if (!navSessionId) return;
  try {
    const api = (NAV_CONTEXT.api && NAV_CONTEXT.api.rotas) || 'api/rotas.php';
    await fetch(api + '?action=nav_recalc', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      credentials: 'same-origin',
      body: JSON.stringify({ navegacao_id: navSessionId })
    });
  } catch (err) {
    /* silencioso */
  }
}

function stopPositionTracking() {
  if (navWatchId !== null && 'geolocation' in navigator) {
    navigator.geolocation.clearWatch(navWatchId);
    navWatchId = null;
  }
  if (navMetricsTimer !== null) {
    window.clearInterval(navMetricsTimer);
    navMetricsTimer = null;
  }
  if (navRecalcTimer !== null) {
    window.clearTimeout(navRecalcTimer);
    navRecalcTimer = null;
  }
}

async function finishNavSession() {
  if (!navSessionId) return;
  try {
    const api = (NAV_CONTEXT.api && NAV_CONTEXT.api.rotas) || 'api/rotas.php';
    await fetch(api + '?action=nav_finish', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      credentials: 'same-origin',
      body: JSON.stringify({ navegacao_id: navSessionId })
    });
  } catch (err) {
    /* silencioso */
  }
  navSessionId = null;
}

/* ==========================================================================
   2. DESENHO DO TRILHO DA ROTA ACESSÍVEL
   ========================================================================== */
function drawNavigationRoute() {
  // Linha Laranja de Base (espessura 9px para alta visibilidade)
  routePolyline = L.polyline(liveRouteCoords, {
    color: '#ea580c',
    weight: 9,
    opacity: 0.95,
    lineCap: 'round',
    lineJoin: 'round'
  }).addTo(navMap);

  // Guia interna branca tracejada para visual idêntico a sistemas de navegação
  routeDashLine = L.polyline(liveRouteCoords, {
    color: '#ffffff',
    weight: 2.5,
    dashArray: '4, 8',
    opacity: 0.9,
    lineCap: 'round'
  }).addTo(navMap);

  aplicarEstiloDoTraco();
}

/* O trilho laranja de "rota confirmada" só aparece depois que api/rota.php
   devolve o caminho. Antes disso o traçado é cinza tracejado: ele indica a
   direção, não as ruas nem as condições de acessibilidade. */
function aplicarEstiloDoTraco() {
  if (!routePolyline || !routeDashLine) return;
  routePolyline.setStyle(tracoAproximado
    ? { color: '#64748b', weight: 6, opacity: 0.8, dashArray: '10, 10' }
    : { color: '#ea580c', weight: 9, opacity: 0.95, dashArray: null });
  routeDashLine.setStyle({ opacity: tracoAproximado ? 0 : 0.9 });
}

/* ==========================================================================
   3. MARCADOR DE POSIÇÃO DO USUÁRIO
   ========================================================================== */
function addUserPositionMarker() {
  if (!userLivePos) return;

  const rotulo = navGpsPos ? 'Sua localização' : 'Origem do trajeto';
  const userHtml = `
    <div style="display: flex; flex-direction: column; align-items: center; transform: translate(-50%, -50%);">
      <div class="nav-user-halo">
        <div class="nav-user-disc">
          <svg viewBox="0 0 24 24" fill="currentColor">
            <polygon points="12 2 19 21 12 17 5 21 12 2"/>
          </svg>
        </div>
      </div>
      <div class="nav-user-label">${rotulo}</div>
    </div>
  `;

  const userIcon = L.divIcon({
    className: 'custom-user-marker-container',
    html: userHtml,
    iconSize: [80, 80],
    iconAnchor: [40, 40]
  });

  userMarker = L.marker(userLivePos, { icon: userIcon, zIndexOffset: 1000 }).addTo(navMap);
}

/* ==========================================================================
   4. MARCADORES E CARTÕES AO LONGO DA VIA NO MAPA
   ========================================================================== */
function addWaypointElements() {
  // 1. Cartão de estado do traçado: nunca afirma acessibilidade sem dado
  const ponto = pontoMedioDoTraco();
  if (ponto) {
    const temRota = liveRouteCoords.length >= 2;
    const tooltipIcon = L.divIcon({
      className: 'custom-tooltip-waypoint',
      html: calloutHtml(
        temRota ? 'Rota carregada' : 'Sem trajeto calculado',
        temRota ? 'Acessibilidade não verificada em campo'
                : 'Aguardando origem, destino e cálculo do roteador'
      ),
      iconSize: [260, 44],
      iconAnchor: [0, 22]
    });
    calloutMarker = L.marker(ponto, { icon: tooltipIcon }).addTo(navMap);
  }

  // 2. Destino real da rota; sem nome salvo, um rótulo neutro
  if (destinationLivePos) {
    definirDestinoBadge(navDestinationName() || 'Destino');
  }
}

function calloutHtml(titulo, sub) {
  return `
    <div class="map-route-tooltip-card">
      <svg class="tooltip-icon-ramp" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
        <polyline points="9 12 11 14 15 10"></polyline>
      </svg>
      <div class="tooltip-text-group">
        <strong class="tooltip-title-bold">${escNavText(titulo)}</strong>
        <span class="tooltip-sub-info">${escNavText(sub)}</span>
      </div>
    </div>
  `;
}

function escNavText(v) {
  return String(v === null || v === undefined ? '' : v)
    .replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}

/* Ponto do cartão: meio do traçado, ou a origem quando ainda não há rota */
function pontoMedioDoTraco() {
  if (liveRouteCoords.length >= 2) {
    const i = Math.floor((liveRouteCoords.length - 1) / 2);
    if (parValido(liveRouteCoords[i][0], liveRouteCoords[i][1])) return liveRouteCoords[i];
  }
  if (userLivePos && parValido(userLivePos[0], userLivePos[1])) return userLivePos;
  if (destinationLivePos && parValido(destinationLivePos[0], destinationLivePos[1])) return destinationLivePos;
  return null;
}

function definirCalloutRota(titulo, sub) {
  if (!calloutMarker) return;
  const ponto = pontoMedioDoTraco();
  if (!ponto) return;
  calloutMarker.setIcon(L.divIcon({
    className: 'custom-tooltip-waypoint',
    html: calloutHtml(titulo, sub),
    iconSize: [260, 44],
    iconAnchor: [0, 22]
  }));
  calloutMarker.setLatLng(ponto);
}

function definirDestinoBadge(nome) {
  const destHtml = `
    <div class="map-dest-badge">
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#ffffff" stroke-width="2.5"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/></svg>
      <span>${escNavText(nome)}</span>
    </div>
  `;

  const destIcon = L.divIcon({
    className: 'custom-dest-badge',
    html: destHtml,
    iconSize: [140, 30],
    iconAnchor: [70, 15]
  });

  if (destMarker) {
    destMarker.setLatLng(destinationLivePos).setIcon(destIcon);
    return;
  }
  destMarker = L.marker(destinationLivePos, { icon: destIcon }).addTo(navMap);
}

/* ==========================================================================
   6. CONTROLES DO MAPA (SUPERIOR DIREITO)
   ========================================================================== */
function initNavigationControls() {
  const btnZoomIn = document.getElementById('btn-zoom-in');
  const btnZoomOut = document.getElementById('btn-zoom-out');
  const btnCenterUser = document.getElementById('btn-center-user');
  const btnLayers = document.getElementById('btn-layers-toggle');

  if (btnZoomIn) btnZoomIn.addEventListener('click', () => navMap.zoomIn());
  if (btnZoomOut) btnZoomOut.addEventListener('click', () => navMap.zoomOut());

  if (btnCenterUser) {
    btnCenterUser.addEventListener('click', () => {
      if (!userLivePos) {
        showToast('Nenhuma posição conhecida ainda. Ative o GPS ou informe a origem na configuração.');
        return;
      }
      navMap.panTo(userLivePos, { animate: true, duration: 0.8 });
      showToast(navGpsPos
        ? 'Mapa centralizado na sua posição de GPS'
        : 'Mapa centralizado na origem do trajeto (sem posição de GPS)');
    });
  }

  if (btnLayers) {
    btnLayers.addEventListener('click', () => {
      showToast('Mostrando o traçado calculado pelo serviço de rotas. Pontos de acessibilidade verificados não estão disponíveis nesta região.');
    });
  }
}

/* ==========================================================================
   7. SÍNTESE DE VOZ E ÁUDIO DE NAVEGAÇÃO
   ========================================================================== */
function initAudioAndSpeech() {
  const btnSpeak = document.getElementById('btn-speak-instruction');
  const btnToggleVoice = document.getElementById('btn-toggle-voice');
  const voiceStatusText = document.getElementById('voice-status-text');

  // Usa somente o passo real da rota; sem rota, a tela não inventa instrução
  const currentInstruction = () => LIVE_ROUTE_STEPS[currentStepIndex]?.instruction || 'Sem instrução de manobra para este trajeto.';

  if (btnSpeak) {
    btnSpeak.addEventListener('click', () => {
      speakInstruction(currentInstruction());
      showToast('Reproduzindo instrução por voz...');
    });
  }

  if (btnToggleVoice) {
    btnToggleVoice.addEventListener('click', () => {
      isVoiceEnabled = !isVoiceEnabled;
      if (isVoiceEnabled) {
        voiceStatusText.textContent = 'Instruções por voz ativas';
        btnToggleVoice.style.opacity = '1';
        showToast('Instruções por voz ativadas');
        speakInstruction(currentInstruction());
      } else {
        voiceStatusText.textContent = 'Instruções por voz mutadas';
        btnToggleVoice.style.opacity = '0.7';
        if ('speechSynthesis' in window) window.speechSynthesis.cancel();
        showToast('Instruções por voz silenciadas');
      }
    });
  }
}

function speakInstruction(text) {
  if (!isVoiceEnabled) return;
  if ('speechSynthesis' in window) {
    window.speechSynthesis.cancel();
    const utterance = new SpeechSynthesisUtterance(text);
    utterance.lang = 'pt-BR';
    utterance.rate = 1.0;
    utterance.pitch = 1.0;
    window.speechSynthesis.speak(utterance);
  }
}

/* ==========================================================================
   8. AÇÃO DE RECALCULAR ROTA
   ========================================================================== */
function initRecalculateAction() {
  const btnRecalculate = document.getElementById('btn-recalculate');
  if (!btnRecalculate) return;

  btnRecalculate.addEventListener('click', async () => {
    if (!destinationLivePos || !userLivePos) {
      showToast(PONTOS_REAIS.destino
        ? 'Informe sua localização para recalcular o trajeto.'
        : 'Escolha um destino na busca para recalcular o trajeto.');
      return;
    }

    const nomeDestino = navDestinationName() || 'destino informado';
    const htmlOriginal = btnRecalculate.innerHTML;
    btnRecalculate.innerHTML = `
      <svg class="spin-icon" style="animation: spin 1s linear infinite;" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="2" x2="12" y2="6"/><line x1="12" y1="18" x2="12" y2="22"/><line x1="4.93" y1="4.93" x2="7.76" y2="7.76"/><line x1="16.24" y1="16.24" x2="19.07" y2="19.07"/></svg>
      <span>Recalculando...</span>
    `;
    btnRecalculate.disabled = true;

    showToast(`Recalculando o trajeto até ${nomeDestino}...`);
    const ok = await recalculateRoute(userLivePos, null, false);

    btnRecalculate.innerHTML = htmlOriginal;
    btnRecalculate.disabled = false;

    if (ok && routePolyline) {
      routePolyline.setStyle({ weight: 12, color: '#f59e0b' });
      setTimeout(() => {
        if (routePolyline) aplicarEstiloDoTraco();
      }, 350);
      showToast('Trajeto recalculado a partir da sua posição. Condições de acessibilidade não verificadas.');
    }
  });
}

/* ==========================================================================
   9. TOAST DE FEEDBACK
   ========================================================================== */
function showToast(message) {
  const toast = document.getElementById('toast-nav-msg');
  if (!toast) return;
  toast.textContent = message;
  toast.classList.add('show');
  setTimeout(() => toast.classList.remove('show'), 3200);
}

/* ==========================================================================
   10. ENCERRAR NAVEGAÇÃO COM CONFIRMAÇÃO
   ========================================================================== */
function initEndNavigation() {
  const btnEndNav   = document.getElementById('btn-end-nav');
  const modal       = document.getElementById('modal-end-nav');
  const btnCancel   = document.getElementById('btn-end-nav-cancel');
  const btnConfirm  = document.getElementById('btn-end-nav-confirm');

  if (!btnEndNav || !modal) return;

  btnEndNav.addEventListener('click', () => {
    modal.style.display = 'flex';
  });

  btnCancel.addEventListener('click', () => {
    modal.style.display = 'none';
  });

  btnConfirm.addEventListener('click', async () => {
    modal.style.display = 'none';
    stopPositionTracking();
    await finishNavSession();
    showToast('Navegação encerrada. Redirecionando...');
    setTimeout(() => { window.location.href = 'configuracao-rota.php'; }, 800);
  });

  // Fecha clicando fora do box
  modal.addEventListener('click', e => {
    if (e.target === modal) modal.style.display = 'none';
  });

  // Fecha com Escape
  document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && modal.style.display === 'flex') {
      modal.style.display = 'none';
    }
  });
}

/* ==========================================================================
   INTEGRAÇÃO SQL — Navegação em Tempo Real
   ========================================================================== */
document.addEventListener('DOMContentLoaded', async () => {
  if (!window.PasseLivreDBReady) return;
  try {
    await window.PasseLivreDBReady;
    if (!window.PLDB) return;

    // Recupera a última rota salva pelo usuário
    const user   = window.PasseLivreAuth?.getUser();
    const dbUser = user ? window.PLDB.users.findByEmail(user.email) : null;
    if (dbUser) {
      const lastRoute = window.PLDB.rotas.findByUser(dbUser.id)[0];
      if (lastRoute) {
        console.log(`[navegacao SQL] Última rota: ${lastRoute.origin_text} → ${lastRoute.destination_text}`);
        // Atualiza labels na tela de navegação se existirem
        const elDest = document.getElementById('nav-destination-label');
        if (elDest && lastRoute.destination_text) elDest.textContent = lastRoute.destination_text;
      }
    }

    // Botão "Reportar Obstáculo" durante navegação
    document.querySelectorAll('.btn-report-nav-obstacle, #btn-report-obstacle').forEach(btn => {
      btn.addEventListener('click', async () => {
        if (window.PLDB) {
          const u = window.PasseLivreAuth?.getUser();
          window.PLDB.solicitacoes.create({
            citizen_name:  u?.name  || 'Usuário Anônimo',
            citizen_email: u?.email || '',
            type_key:   'barreiras',
            type_name:  'Obstáculo na Rota',
            title:      'Obstáculo Reportado Durante Navegação',
            address:    'Trajeto ativo até ' + (navDestinationName() || 'destino da busca'),
            description:'Obstáculo identificado pelo usuário durante navegação em tempo real.',
            status:     'pending',
            priority:   'urgente'
          });
          if (window.PLDBAuditLog) await window.PLDBAuditLog('rotas', 'Obstáculo Reportado', 'Usuário reportou obstáculo durante navegação', 'medio');
        }
        if (typeof showToast === 'function') showToast('Obstáculo registrado no banco de dados!');
      });
    });

  } catch (e) {
    console.warn('[navegacao] SQL:', e);
  }
});
