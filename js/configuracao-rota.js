/**
 * PASSE LIVRE — CONFIGURAÇÃO DA ROTA ACESSÍVEL
 * Script de controle do mapa interativo: rotas calculadas sobre o endereço
 * escolhido pelo usuário, camadas de acessibilidade NBR 9050, alertas de
 * barreiras e navegação assistida.
 */

document.addEventListener('DOMContentLoaded', () => {
  initRouteMap();
  sincronizarPainelDeRota();
  initMapControls();
  initRouteInteractions();
  initNavigationHUD();
  initHeaderSearch();
});

/* Variáveis Globais de Estado */
let map = null;
let auditedPolyline = null;
let alternateLayers = [];
let tactileMarkersGroup = null;
let alertMarkersGroup = null;
let originMarker = null;
let destinationMarker = null;
let destinationLabelMarker = null;
let navigationInterval = null;
let userMovingMarker = null;
let isAccessibilityVisible = true;

/* ==========================================================================
   CONTEXTO DA ROTA RESOLVIDO NO PHP
   configuracao-rota.php consulta o banco, normaliza as categorias de deficiência
   em includes/acessibilidade.php e entrega tudo num bloco JSON. O JavaScript
   não inventa origem nem destino: GPS e última posição da navegação ficam no
   sessionStorage (dado do próprio navegador) e o resto vem do contexto.
   ========================================================================== */
function lerContextoDaRota() {
  const el = document.getElementById('route-context');
  if (!el) return {};
  try {
    const dados = JSON.parse(el.textContent || '{}');
    return dados && typeof dados === 'object' ? dados : {};
  } catch (err) {
    console.warn('[rota] contexto PHP inválido:', err);
    return {};
  }
}

const ROUTE_CONTEXT = lerContextoDaRota();
const ENDPOINTS_REAIS = { origem: false, destino: false };
const PCD_CATEGORIAS = Array.isArray(ROUTE_CONTEXT.pcd_categorias)
  ? ROUTE_CONTEXT.pcd_categorias.map(String)
  : [];
const PREFERENCIAS = Array.isArray(ROUTE_CONTEXT.preferencias)
  ? ROUTE_CONTEXT.preferencias.map(String)
  : [];
const ROUTE_PROFILE = ROUTE_CONTEXT.perfil === 'wheelchair' ? 'wheelchair' : 'foot-walking';
const ROUTE_PERFIL_ROTULO = ROUTE_PROFILE === 'wheelchair' ? 'cadeira de rodas' : 'a pé';

function _num(value) {
  const n = parseFloat(value);
  return Number.isFinite(n) ? n : null;
}

/* Mesma sanidade do backend (includes/geo_centro.php): 0,0 ou um ponto fora do
   Brasil não pode ser tratado como endereço escolhido pelo usuário. */
function parValido(lat, lng) {
  return lat !== null && lng !== null && lat >= -35 && lat <= 6 && lng >= -75 && lng <= -30;
}

function _txt(v) {
  return String(v === null || v === undefined ? '' : v);
}

/* Escapa o texto livre antes de entregá-lo ao innerHTML dos ícones do Leaflet. */
function _esc(v) {
  const s = _txt(v);
  return s.replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}

function _coordDoContexto(obj, origemDado) {
  if (!obj) return null;
  const lat = _num(obj.lat);
  const lng = _num(obj.lng);
  if (!parValido(lat, lng)) return null;
  return { coords: [lat, lng], origemDado };
}

/* Destino: banco/?dest_id primeiro; a última escolha da sessão só entra quando
   a página realmente abriu sem destino — nunca um ponto fixo de Suzano. */
function destinoDoContexto() {
  const d = ROUTE_CONTEXT.destino;
  const base = _coordDoContexto(d, _txt(d && d.origem_dado) || 'banco');
  if (!base) return null;
  return Object.assign(base, {
    id: _txt(d.id),
    text: _txt(d.nome) || 'Destino escolhido',
    address: _txt(d.endereco),
    municipio: _txt(d.municipio),
    uf: _txt(d.uf) || 'SP',
    accessStatus: _txt(d.status_acessibilidade) || 'unverified',
    accessText: _txt(d.texto_acessibilidade)
  });
}

function destinoDaSessao() {
  const lat = _num(sessionStorage.getItem('passeLivre_dest_lat'));
  const lng = _num(sessionStorage.getItem('passeLivre_dest_lng'));
  if (!parValido(lat, lng)) return null;
  return {
    coords: [lat, lng],
    id: _txt(sessionStorage.getItem('passeLivre_dest_id')),
    text: _txt(sessionStorage.getItem('passeLivre_dest_text')) || 'Destino escolhido',
    address: '',
    municipio: _txt(sessionStorage.getItem('passeLivre_dest_municipio')),
    uf: 'SP',
    accessStatus: 'unverified',
    accessText: '',
    origemDado: 'sessao'
  };
}

/* Prioridade da origem: GPS autorizado → ponto escolhido no mapa → coordenada
   recebida de outra tela. Sem nenhuma delas, não há origem — e sem origem não
   existe trajeto a calcular. */
function origemDoGPS() {
  const lat = _num(sessionStorage.getItem('passeLivre_gps_lat'));
  const lng = _num(sessionStorage.getItem('passeLivre_gps_lng'));
  if (!parValido(lat, lng)) return null;
  return {
    coords: [lat, lng],
    text: 'Minha localização atual',
    address: 'Posição obtida pelo GPS autorizado neste navegador',
    origemDado: 'gps'
  };
}

function origemDaSessaoManual() {
  const lat = _num(sessionStorage.getItem('passeLivre_origem_manual_lat'));
  const lng = _num(sessionStorage.getItem('passeLivre_origem_manual_lng'));
  if (!parValido(lat, lng)) return null;
  return {
    coords: [lat, lng],
    text: _txt(sessionStorage.getItem('passeLivre_origem_manual_nome')) || 'Ponto escolhido no mapa',
    address: 'Origem selecionada manualmente no mapa',
    origemDado: 'mapa'
  };
}

function origemDoContexto() {
  const o = ROUTE_CONTEXT.origem;
  const base = _coordDoContexto(o, 'parametros');
  if (!base) return null;
  return Object.assign(base, {
    text: _txt(o.nome) || 'Ponto de partida informado',
    address: 'Origem recebida da tela anterior'
  });
}

function resolveRouteEndpoints() {
  return {
    origin: origemDoGPS() || origemDaSessaoManual() || origemDoContexto(),
    destination: destinoDoContexto() || destinoDaSessao()
  };
}

const ROUTE_ENDPOINTS = resolveRouteEndpoints();
ENDPOINTS_REAIS.origem = ROUTE_ENDPOINTS.origin !== null;
ENDPOINTS_REAIS.destino = ROUTE_ENDPOINTS.destination !== null;

/* Rota real obtida de api/rota.php; ROUTE_LIST guarda todas as alternativas
   recebidas, cada uma com as próprias métricas. */
let LIVE_ROUTE = null;
let ROUTE_LIST = [];
let selectedRouteIndex = 0;

/* ==========================================================================
   1. INICIALIZAÇÃO DO MAPA INTERATIVO (LEAFLET + TILES SUAVE TONS QUENTES)
   ========================================================================== */
function pontoMedio(a, b) {
  return [(a[0] + b[0]) / 2, (a[1] + b[1]) / 2];
}

/* O mapa abre onde a rota está: nos dois pontos reais, no único ponto conhecido
   ou no Brasil quando ainda não há nenhum. Nunca num ponto fictício de Suzano. */
function centroInicialDoMapa() {
  const o = ROUTE_ENDPOINTS.origin && ROUTE_ENDPOINTS.origin.coords;
  const d = ROUTE_ENDPOINTS.destination && ROUTE_ENDPOINTS.destination.coords;
  if (o && d) return pontoMedio(o, d);
  if (d) return d;
  if (o) return o;
  return [-15.78, -47.93];
}

function zoomInicialDoMapa() {
  const o = ROUTE_ENDPOINTS.origin;
  const d = ROUTE_ENDPOINTS.destination;
  if (o && d) return 14;
  if (o || d) return 15;
  return 4;
}

function initRouteMap() {
  const mapElement = document.getElementById('route-map');
  if (!mapElement) return;

  const centro = centroInicialDoMapa();

  // Criação da instância Leaflet
  map = L.map('route-map', {
    center: centro,
    zoom: zoomInicialDoMapa(),
    minZoom: 4,
    maxZoom: 19,
    zoomControl: false, // Usamos os botões dedicados do protótipo
    attributionControl: true
  });

  // Camada base: raster padrão do OpenStreetMap. A antiga camada CARTO passou a
  // exigir chave e respondia a todos os tiles com o bloco "API KEY REQUIRED";
  // o filtro CSS do tom pergaminho continua aplicado por cima de qualquer raster.
  const camadaBase = L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
  }).addTo(map);

  // Falha de camada é avisada uma vez, e só quando nenhum tile abriu: tile que
  // volta em branco ainda conta como load, então o aviso nunca esconde o trajeto.
  let tilesComFalha = 0;
  let tilesAbertos = 0;
  let avisouTiles = false;
  camadaBase.on('tileerror', () => {
    tilesComFalha++;
    if (!avisouTiles && tilesComFalha >= 4 && tilesAbertos === 0) {
      avisouTiles = true;
      showToast('Camada de mapas indisponível. O trajeto continua desenhado sobre o fundo.');
    }
  });
  camadaBase.on('tileload', () => { tilesAbertos++; });

  // Grupos de Camadas para Acessibilidade
  tactileMarkersGroup = L.layerGroup().addTo(map);
  alertMarkersGroup = L.layerGroup().addTo(map);

  // Clique no mapa escolhe o ponto de partida quando o modo está ativo
  map.on('click', aoClicarNoMapaParaOrigem);

  // Traçar as rotas visualmente (linha tracejada de direção até o cálculo)
  drawRoutes();

  // Adicionar marcadores reais
  addRouteMarkers();

  // Buscar o traçado real somente quando existem os dois pontos
  fetchRealWalkingRoute();

  // Ajustar enquadramento inicial garantindo visão completa do percurso
  setTimeout(() => {
    enquadraRota({ paddingTopLeft: [460, 40] }); // espaço para o card flutuante à esquerda
  }, 350);
}

/* Enquadra o trajeto calculado; sem linha válida, mantém o mapa no ponto conhecido. */
function enquadraRota(opcoes) {
  if (!map) return;
  const pontos = auditedPolyline ? auditedPolyline.getLatLngs() : [];
  if (pontos.length >= 2) {
    map.fitBounds(auditedPolyline.getBounds(), Object.assign({
      paddingTopLeft: [window.innerWidth > 960 ? 460 : 40, 40],
      paddingBottomRight: [60, 40]
    }, opcoes || {}));
    return;
  }
  map.setView(centroInicialDoMapa(), Math.max(map.getZoom(), 14), Object.assign({}, opcoes || {}));
}

/* ==========================================================================
   2. DESENHO DAS LINHAS DE ROTA (LARANJA AUDITADA & CINZA PONTILHADA)
   ========================================================================== */

/* Sem roteador respondido ainda, a única linha honesta entre dois pontos reais é
   a direção geral — por isso ela entra tracejada e vira trajeto ao calcular. */
function tracoInicialDaRota() {
  if (!ENDPOINTS_REAIS.origem || !ENDPOINTS_REAIS.destino) return [];
  return [ROUTE_ENDPOINTS.origin.coords, ROUTE_ENDPOINTS.destination.coords];
}

function textoPopupRota() {
  const selecionada = ROUTE_LIST[selectedRouteIndex];
  if (LIVE_ROUTE && selecionada) {
    return `
      <div class="custom-popup-content">
        <span class="popup-tag-badge popup-tag-accessible">Trajeto calculado • ${_esc(ROUTE_PERFIL_ROTULO)}</span>
        <h3 class="popup-heading">${_esc(selecionada.rotulo || 'Trajeto calculado')}</h3>
        <p class="popup-desc">${formatarMin(selecionada.duration_min)} • ${formatarKm(selecionada.distance_km)}. Fonte: ${_esc(selecionada.provider_label)}. Condições de calçada, rampas e travessias não foram verificadas em campo.</p>
      </div>
    `;
  }
  if (!ENDPOINTS_REAIS.origem) {
    return `
      <div class="custom-popup-content">
        <span class="popup-tag-badge popup-tag-risk">Ponto de partida faltando</span>
        <h3 class="popup-heading">Informe sua localização</h3>
        <p class="popup-desc">Sem a origem não há trajeto a calcular. Use o GPS, clique no mapa ou volte à busca e escolha o ponto de partida.</p>
      </div>
    `;
  }
  if (!ENDPOINTS_REAIS.destino) {
    return `
      <div class="custom-popup-content">
        <span class="popup-tag-badge popup-tag-risk">Destino faltando</span>
        <h3 class="popup-heading">Escolha um destino</h3>
        <p class="popup-desc">Volte à busca e selecione o endereço que você quer alcançar.</p>
      </div>
    `;
  }
  if (rotaIndisponivel) {
    return `
      <div class="custom-popup-content">
        <span class="popup-tag-badge popup-tag-risk">Trajeto não calculado</span>
        <h3 class="popup-heading">Direção geral do deslocamento</h3>
        <p class="popup-desc">O roteador não devolveu o caminho calçado. A linha tracejada é apenas a direção entre os pontos reais — não é uma rota.</p>
      </div>
    `;
  }
  return `
    <div class="custom-popup-content">
      <span class="popup-tag-badge popup-tag-tactile">Trajeto em cálculo</span>
      <h3 class="popup-heading">Direção geral do deslocamento</h3>
      <p class="popup-desc">A linha tracejada liga origem e destino em linha reta. O roteador público ainda não devolveu o caminho calçado.</p>
    </div>
  `;
}

function drawRoutes() {
  const pontos = tracoInicialDaRota();
  if (!pontos.length) {
    auditedPolyline = null;
    return;
  }

  auditedPolyline = L.polyline(pontos, {
    color: '#ea580c',
    weight: 7,
    opacity: 0.95,
    dashArray: '10, 10',
    lineCap: 'round',
    lineJoin: 'round'
  }).addTo(map);

  auditedPolyline.bindPopup(textoPopupRota());

  // Interação de hover nas rotas
  auditedPolyline.on('mouseover', function() {
    this.setStyle({ weight: 9, opacity: 1 });
  });
  auditedPolyline.on('mouseout', function() {
    this.setStyle({ weight: 7, opacity: 0.95 });
    if (LIVE_ROUTE) this.setStyle({ dashArray: null });
  });
}

/* ==========================================================================
   3. ADIÇÃO DE MARCADORES (DESTINO, ALERTA, PISO TÁTIL E ORIGEM)
   ========================================================================== */
function addRouteMarkers() {
  atualizarMarcadores();
}

function removerMarcador(marcador) {
  if (marcador && map) map.removeLayer(marcador);
  return null;
}

function atualizarMarcadores() {
  atualizarMarcadorOrigem();
  desenhaMarcadorDestino();
}

/* Origem: só existe marcador quando existe uma posição real (GPS, escolha no
   mapa ou coordenada recebida). Nada é desenhado "para ilustrar". */
function atualizarMarcadorOrigem() {
  if (!map) return;
  originMarker = removerMarcador(originMarker);
  const origem = ROUTE_ENDPOINTS.origin;
  if (!origem) return;

  const rotulo = _txt(origem.text) || 'Ponto de partida';
  const curto = rotulo.length > 34 ? rotulo.slice(0, 33).trimEnd() + '…' : rotulo;
  const fonte = {
    gps: 'Posição obtida pelo GPS autorizado',
    mapa: 'Ponto escolhido manualmente no mapa',
    parametros: 'Origem recebida da tela anterior'
  }[origem.origemDado] || 'Ponto de partida informado';

  const originCalloutIcon = L.divIcon({
    className: 'map-callout-origin-wrapper',
    html: `
      <div class="map-callout-origin" title="${_esc(rotulo)}">
        <span class="origin-pin-dot"></span>
        <span>${_esc(curto)}</span>
      </div>
    `,
    iconSize: [230, 32],
    iconAnchor: [16, 16]
  });

  originMarker = L.marker(origem.coords, { icon: originCalloutIcon, zIndexOffset: 400 }).addTo(map);
  originMarker.bindPopup(`
    <div class="custom-popup-content">
      <span class="popup-tag-badge popup-tag-accessible">Origem</span>
      <h3 class="popup-heading">${_esc(rotulo)}</h3>
      <p class="popup-desc">${_esc(fonte)}. ${_esc(origem.address || '')}</p>
    </div>
  `);
}

/* Destino: teardrop marrom, rótulo e pop-up com a condição real do cadastro.
   Nada de "destino certificado" sem access_status confirmado. */
function desenhaMarcadorDestino() {
  if (!map) return;
  destinationMarker = removerMarcador(destinationMarker);
  destinationLabelMarker = removerMarcador(destinationLabelMarker);

  const dest = ROUTE_ENDPOINTS.destination;
  if (!dest) return;

  const rotulo = _txt(dest.text) || 'Destino escolhido';
  const etiqueta = {
    verified: ['Destino verificado', 'popup-tag-accessible'],
    partial: ['Acessibilidade parcial', 'popup-tag-tactile']
  }[dest.accessStatus] || ['Acessibilidade não verificada', 'popup-tag-risk'];

  const descricao = _txt(dest.accessText)
    || 'Ainda não há vistoria de acessibilidade registrada para este endereço.';

  const idDestino = parseInt(dest.id, 10);
  const linkDetalhes = idDestino > 0
    ? `<a href="detalhes-local.php?id=${idDestino}" style="display: inline-block; margin-top: 6px; font-size: 12px; font-weight: 700; color: #ea580c; text-decoration: none;">Ver detalhes completos do local &rarr;</a>`
    : '';

  const destIcon = L.divIcon({
    className: 'pin-destination-marker',
    iconSize: [32, 40],
    iconAnchor: [16, 40]
  });

  destinationMarker = L.marker(dest.coords, { icon: destIcon }).addTo(map);
  destinationMarker.bindPopup(`
    <div class="custom-popup-content">
      <span class="popup-tag-badge ${etiqueta[1]}">${etiqueta[0]}</span>
      <h3 class="popup-heading">${_esc(rotulo)}${dest.municipio ? ' — ' + _esc(dest.municipio) : ''}</h3>
      <p class="popup-desc">${_esc(dest.address ? dest.address + '. ' : '')}${_esc(descricao)}</p>
      ${linkDetalhes}
    </div>
  `);

  const rotuloCurto = rotulo.length > 28 ? rotulo.slice(0, 27).trimEnd() + '…' : rotulo;
  const labelDestIcon = L.divIcon({
    className: 'map-destination-label',
    html: `<span title="${_esc(rotulo)}">${_esc(rotuloCurto)}</span>`,
    iconSize: [180, 24],
    iconAnchor: [90, 48]
  });
  destinationLabelMarker = L.marker(dest.coords, { icon: labelDestIcon, interactive: false }).addTo(map);
}

/* ==========================================================================
   5. CONSULTA DINÂMICA DA ROTA REAL (api/rota.php -> OpenRouteService / OSRM)
      Sem chave ou em caso de falha, o traçado de demonstração permanece.
   ========================================================================== */
/* Textos do card quando ainda não existe percurso calculado. Os números do
   cenário de Suzano só valem em modo demonstração; fora dele o painel não pode
   afirmar rampas, piso tátil ou auditoria que nenhum registro sustenta. */
const TEXTOS_PERCURSO_SEM_CALCULO = [
  'Aguarde o cálculo do trajeto',
  'Distância e tempo aparecem depois do cálculo',
  'Acessibilidade das calçadas: não verificada em campo',
  'Você pode reportar obstáculos encontrados no percurso'
];

function formatarKm(km) {
  const n = Number(km);
  if (!Number.isFinite(n)) return '—';
  return `${n.toLocaleString('pt-BR', { minimumFractionDigits: 0, maximumFractionDigits: 1 })} km`;
}

function formatarMin(min) {
  const n = Math.round(Number(min));
  return Number.isFinite(n) ? `${n} min` : '—';
}

function definirPercursoSemCalculo(motivo) {
  const card = document.getElementById('card-route-accessible');
  if (card) {
    const badge = document.getElementById('selected-route-badge');
    const audit = document.getElementById('selected-route-audit');
    const heading = document.getElementById('selected-route-heading');
    const t = document.getElementById('selected-route-time');
    const d = document.getElementById('selected-route-dist');
    const s = document.getElementById('selected-route-tag');
    if (badge) badge.textContent = 'AGUARDANDO CÁLCULO';
    if (audit) audit.textContent = 'Acessibilidade não verificada';
    if (heading) heading.textContent = 'Trajeto não calculado';
    if (t) t.textContent = 'A calcular';
    if (d) d.textContent = '—';
    if (s) s.textContent = 'Sem auditoria';
    const textos = TEXTOS_PERCURSO_SEM_CALCULO.slice();
    if (motivo) textos[0] = motivo;
    card.querySelectorAll('.route-checklist-item > span').forEach((el, i) => {
      if (textos[i]) el.textContent = textos[i];
    });
  }

  const countEl = document.getElementById('route-options-count');
  if (countEl) countEl.textContent = 'Sem rota';
  const acoes = document.getElementById('selected-route-actions');
  if (acoes) acoes.replaceChildren();
  [2, 3].forEach(slot => {
    const alt = document.getElementById(`card-route-alt-${slot}`);
    if (alt) {
      alt.hidden = true;
      delete alt.dataset.routeIndex;
    }
  });
}

/* Nenhuma métrica sobrevive a uma falha: o painel não pode continuar exibindo
   números de uma resposta anterior como se fossem do trajeto atual. */
function descartarRotasCalculadas() {
  ROUTE_LIST = [];
  LIVE_ROUTE = null;
  selectedRouteIndex = 0;
  perfilEfetivoDaRota = ROUTE_PROFILE;

  alternateLayers.forEach(layer => { if (map && map.hasLayer(layer)) map.removeLayer(layer); });
  alternateLayers = [];
  if (auditedPolyline && map) map.removeLayer(auditedPolyline);
  auditedPolyline = null;
  if (tactileMarkersGroup) tactileMarkersGroup.clearLayers();
  if (alertMarkersGroup) alertMarkersGroup.clearLayers();
}

let rotaIndisponivel = false;

function avisoRotaIndisponivel(mensagem, dados) {
  rotaIndisponivel = true;
  descartarRotasCalculadas();
  drawRoutes();
  const motivo = dados && dados.reason === 'perfil_indisponivel'
    ? 'Rota para cadeira de rodas indisponível — veja o aviso exibido'
    : 'O roteador não devolveu um trajeto para estes pontos';
  definirPercursoSemCalculo(motivo);
  showToast(mensagem || 'O trajeto não pôde ser calculado agora. A linha tracejada mostra apenas a direção entre a origem e o destino.');
  if (dados && dados.reason === 'perfil_indisponivel') oferecerTrajetoAPe(dados);
}

const PL_ROTAS_MAX_ALT = Math.max(0, Math.min(3, parseInt(ROUTE_CONTEXT.alternativas_max, 10) || 3));

let rotaRequestCtrl = null;
let rotaConsultaEmAndamento = '';
let rotaAssinaturaCalculada = '';
let perfilEfetivoDaRota = ROUTE_PROFILE;

function apiDaRota(chave, padrao) {
  const api = ROUTE_CONTEXT.api;
  return _txt(api && api[chave]) || padrao;
}

function assinaturaDosPontos() {
  if (!ENDPOINTS_REAIS.origem || !ENDPOINTS_REAIS.destino) return '';
  const o = ROUTE_ENDPOINTS.origin.coords;
  const d = ROUTE_ENDPOINTS.destination.coords;
  return `${o[0].toFixed(5)},${o[1].toFixed(5)}>${d[0].toFixed(5)},${d[1].toFixed(5)}|${ROUTE_PROFILE}`;
}

/* One query per origin/destination/profile pair: reloading the page or opening a
   card must not repeat the same call. Failures are never cached — the recalculate
   action can always try again. */
async function fetchRealWalkingRoute(opcoes) {
  const opts = opcoes || {};
  if (!ENDPOINTS_REAIS.origem || !ENDPOINTS_REAIS.destino) {
    sincronizarPainelDeRota();
    return false;
  }

  const assinatura = assinaturaDosPontos() + (opts.permitirAPe ? '|a-pe' : '');
  if (!opts.forcar && assinatura === rotaAssinaturaCalculada && ROUTE_LIST.length) return true;
  if (rotaRequestCtrl) {
    if (assinatura === rotaConsultaEmAndamento) return false;
    rotaRequestCtrl.abort();
  }

  const ctrl = new AbortController();
  rotaRequestCtrl = ctrl;
  rotaConsultaEmAndamento = assinatura;
  const limite = setTimeout(() => ctrl.abort(), 12000);

  const params = new URLSearchParams({
    profile: ROUTE_PROFILE,
    origin_lat: String(ROUTE_ENDPOINTS.origin.coords[0]),
    origin_lng: String(ROUTE_ENDPOINTS.origin.coords[1]),
    dest_lat: String(ROUTE_ENDPOINTS.destination.coords[0]),
    dest_lng: String(ROUTE_ENDPOINTS.destination.coords[1]),
    alternatives: String(PL_ROTAS_MAX_ALT)
  });
  if (opts.permitirAPe) params.set('allow_foot_fallback', '1');

  if (!ROUTE_LIST.length) definirPercursoSemCalculo('Calculando o trajeto com o roteador…');

  try {
    const response = await fetch(apiDaRota('rota', 'api/rota.php') + '?' + params.toString(), {
      headers: { 'Accept': 'application/json' },
      credentials: 'same-origin',
      signal: ctrl.signal
    });
    const data = await response.json().catch(() => null);
    if (ctrl.signal.aborted) return false;

    if (!response.ok) {
      if (response.status === 401) {
        definirPercursoSemCalculo('Sessão expirada — entre novamente para calcular');
        showToast('Sua sessão expirou. Entre novamente para calcular o trajeto.');
        return false;
      }
      if (response.status === 429) {
        definirPercursoSemCalculo('Limite de consultas atingido — aguarde um minuto');
        showToast('Muitas consultas ao serviço de rotas. Aguarde um minuto e tente novamente.');
        return false;
      }
      avisoRotaIndisponivel((data && (data.message || data.error)) || 'O serviço de rotas não respondeu.', data);
      return false;
    }

    if (!data || data.success !== true) {
      avisoRotaIndisponivel((data && data.message) || 'O trajeto não pôde ser calculado agora.', data);
      return false;
    }

    const ok = aplicarRotasReais(data);
    if (ok) rotaAssinaturaCalculada = assinatura;
    return ok;
  } catch (err) {
    if (ctrl.signal.aborted) return false;
    console.info('[rota] roteador indisponível:', err && err.message);
    avisoRotaIndisponivel('Não foi possível calcular a rota agora. A linha tracejada mostra apenas a direção entre os pontos.');
    return false;
  } finally {
    clearTimeout(limite);
    if (rotaRequestCtrl === ctrl) {
      rotaRequestCtrl = null;
      rotaConsultaEmAndamento = '';
    }
  }
}

/* Normaliza cada rota devolvida pelo servidor. Coordenadas fora do Brasil e
   métricas ausentes não viram números inventados: a rota é descartada. */
function normalizarRotasDaResposta(data) {
  const lista = Array.isArray(data && data.rotas) ? data.rotas : [];
  const rotas = [];

  lista.forEach((bruta, indice) => {
    if (!bruta || typeof bruta !== 'object') return;
    const coords = (Array.isArray(bruta.coords) ? bruta.coords : [])
      .map(c => [Number(c && c[0]), Number(c && c[1])])
      .filter(c => parValido(c[0], c[1]));
    if (coords.length < 2) return;

    const distanceM = Number(bruta.distance_m);
    const durationS = Number(bruta.duration_s);
    const distanceKm = Number.isFinite(Number(bruta.distance_km))
      ? Number(bruta.distance_km)
      : (Number.isFinite(distanceM) ? distanceM / 1000 : null);
    const durationMin = Number.isFinite(Number(bruta.duration_min))
      ? Number(bruta.duration_min)
      : (Number.isFinite(durationS) ? Math.max(1, Math.ceil(durationS / 60)) : null);

    const steps = (Array.isArray(bruta.steps) ? bruta.steps : [])
      .filter(s => s && _txt(s.instruction).trim() !== '')
      .map(s => ({
        instruction: _txt(s.instruction).trim().slice(0, 300),
        distance_m: Number.isFinite(Number(s.distance_m))
          ? Number(s.distance_m)
          : Math.max(0, Math.round((Number(s.distance_km) || 0) * 1000)),
        lat: _num(s.lat),
        lng: _num(s.lng)
      }));

    rotas.push({
      id: _txt(bruta.id) || `rota-${indice + 1}`,
      numero: indice + 1,
      rotulo: _txt(bruta.rotulo) || (indice === 0 ? 'Trajeto calculado' : `Alternativa ${indice + 1}`),
      rotulo_kind: _txt(bruta.rotulo_kind) || (indice === 0 ? 'recommended' : 'alternative'),
      coords,
      steps,
      distance_m: Number.isFinite(distanceM) ? Math.round(distanceM) : null,
      distance_km: distanceKm,
      duration_s: Number.isFinite(durationS) ? Math.round(durationS) : null,
      duration_min: durationMin,
      profile_effective: _txt(bruta.profile_effective) || _txt(data.profile_effective) || ROUTE_PROFILE,
      provider: _txt(bruta.provider) || _txt(data.provider) || 'roteador',
      provider_label: _txt(bruta.provider_label) || _txt(data.provider_label) || 'Serviço de rotas',
      acessibilidade: { estado: 'carregando' }
    });
  });

  return rotas;
}

function aplicarRotasReais(data) {
  const rotas = normalizarRotasDaResposta(data);
  if (!rotas.length) {
    avisoRotaIndisponivel('O roteador respondeu sem um traçado válido para estes pontos.');
    return false;
  }

  LIVE_ROUTE = data;
  ROUTE_LIST = rotas;
  rotaIndisponivel = false;
  selectedRouteIndex = Math.min(selectedRouteIndex, rotas.length - 1);
  perfilEfetivoDaRota = _txt(data.profile_effective) || ROUTE_PROFILE;

  if (ROUTE_PROFILE === 'wheelchair' && perfilEfetivoDaRota !== 'wheelchair') {
    showToast('Trajeto a pé calculado a seu pedido: ele NÃO é otimizado para cadeira de rodas.');
  } else if (data.aviso_regiao) {
    showToast(_txt(data.aviso_regiao));
  }

  selecionarRota(selectedRouteIndex, { animar: true });
  carregarAcessibilidadeDasRotas();
  return true;
}

function tituloDaRota(rota) {
  if (!rota) return 'Trajeto calculado';
  if (rota.rotulo_kind === 'recommended') return ROUTE_LIST.length > 1 ? 'Rota recomendada' : 'Trajeto calculado';
  if (rota.rotulo_kind === 'fastest') return 'Rota mais rápida';
  if (rota.rotulo_kind === 'shortest') return 'Rota mais curta';
  return `Alternativa ${rota.numero}`;
}

function resumoAcessibilidade(rota) {
  const a = rota && rota.acessibilidade;
  if (!a || a.estado === 'carregando') {
    return {
      linha: 'Consultando dados de acessibilidade (OpenStreetMap e relatos)…',
      audit: 'Acessibilidade não verificada',
      apoio: 'Consultando',
      acesso: 'Não verificado'
    };
  }
  if (a.estado === 'indisponivel') {
    return {
      linha: 'Dados de acessibilidade indisponíveis agora — informação não verificada',
      audit: 'Acessibilidade não verificada',
      apoio: 'Indisponível',
      acesso: 'Informação indisponível'
    };
  }

  const partes = [];
  if (a.osm_erro) partes.push('pontos do OSM indisponíveis agora');
  else if (a.osm > 0) partes.push(`${a.osm} ponto${a.osm === 1 ? '' : 's'} do OSM (rampa/piso tátil) a até 25 m`);
  else partes.push('nenhum ponto de rampa ou piso tátil do OSM a até 25 m');

  if (a.ocorrencias_erro) partes.push('relatos de barreiras indisponíveis agora');
  else if (a.ocorrencias > 0) partes.push(`${a.ocorrencias} barreira${a.ocorrencias === 1 ? ' ativa reportada' : 's ativas reportadas'} a até 40 m`);
  else partes.push('nenhuma barreira ativa reportada a até 40 m');

  return {
    linha: partes.join(' • ') + '. Dados comunitários, não é vistoria.',
    audit: a.ocorrencias > 0 ? `${a.ocorrencias} barreira${a.ocorrencias === 1 ? '' : 's'} no entorno` : 'Acessibilidade não verificada',
    apoio: `${a.osm} ponto${a.osm === 1 ? '' : 's'} OSM`,
    acesso: a.ocorrencias > 0
      ? `${a.ocorrencias} barreira${a.ocorrencias === 1 ? '' : 's'} reportada${a.ocorrencias === 1 ? '' : 's'}`
      : 'Sem barreiras reportadas'
  };
}

/* ==========================================================================
   6B. DESENHO DAS ROTAS REAIS, CARTÕES DINÂMICOS E ACESSIBILIDADE
   ========================================================================== */
/* O traçado selecionado é sempre o laranja da referência. As alternativas usam
   azul e roxo (contraste entre si e contra o fundo claro), nunca só opacidade. */
const CORES_ALTERNATIVAS = ['#1d4ed8', '#7e22ce'];

function desenharRotasReais() {
  if (!map || !ROUTE_LIST.length) return;
  const selecionada = ROUTE_LIST[selectedRouteIndex];
  if (!selecionada) return;

  alternateLayers.forEach(layer => map.removeLayer(layer));
  alternateLayers = [];

  ROUTE_LIST.forEach((rota, indice) => {
    if (indice === selectedRouteIndex) return;
    const cor = CORES_ALTERNATIVAS[indice === 1 ? 0 : 1];
    const linha = L.polyline(rota.coords, {
      color: cor,
      weight: 4.5,
      opacity: 0.85,
      lineCap: 'round',
      lineJoin: 'round'
    }).addTo(map);

    linha.bindTooltip(
      `${tituloDaRota(rota)} • ${formatarMin(rota.duration_min)} • ${formatarKm(rota.distance_km)}`,
      { sticky: true }
    );
    linha.bindPopup(`
      <div class="custom-popup-content">
        <span class="popup-tag-badge popup-tag-tactile">Alternativa calculada</span>
        <h3 class="popup-heading">${_esc(tituloDaRota(rota))}</h3>
        <p class="popup-desc">${formatarMin(rota.duration_min)} • ${formatarKm(rota.distance_km)}. Fonte: ${_esc(rota.provider_label)}. Acessibilidade não verificada em campo.</p>
      </div>
    `);
    linha.on('click', () => {
      selecionarRota(indice, { animar: false });
      showToast(`Alternativa selecionada: ${tituloDaRota(rota)} (${formatarMin(rota.duration_min)} • ${formatarKm(rota.distance_km)})`);
    });
    alternateLayers.push(linha);
  });

  if (!auditedPolyline) {
    auditedPolyline = L.polyline(selecionada.coords, {}).addTo(map);
    auditedPolyline.on('mouseover', function () { this.setStyle({ weight: 9 }); });
    auditedPolyline.on('mouseout', function () { this.setStyle({ weight: 7 }); });
  } else {
    auditedPolyline.setLatLngs(selecionada.coords);
  }
  auditedPolyline.setStyle({
    color: '#ea580c',
    weight: 7,
    opacity: 0.95,
    dashArray: null,
    lineCap: 'round',
    lineJoin: 'round'
  });
  auditedPolyline.setPopupContent(textoPopupRota());
  auditedPolyline.bringToFront();
}

function selecionarRota(indice, opcoes) {
  if (!ROUTE_LIST.length) return;
  selectedRouteIndex = Math.max(0, Math.min(ROUTE_LIST.length - 1, Number(indice) || 0));

  desenharRotasReais();
  renderizarCartoes();
  desenharMarcadoresAcessibilidade();
  atualizarResumoDaRotaEscolhida();

  const cardSelecionado = document.getElementById('card-route-accessible');
  if (cardSelecionado) {
    cardSelecionado.setAttribute('aria-pressed', 'true');
    cardSelecionado.setAttribute('aria-label', `Opção selecionada: ${tituloDaRota(ROUTE_LIST[selectedRouteIndex])}`);
  }
  document.querySelectorAll('#card-route-alt-2, #card-route-alt-3').forEach(el => el.setAttribute('aria-pressed', 'false'));

  if (!opcoes || opcoes.animar !== false) enquadraRota({ animate: true, duration: 0.5 });
  if (document.getElementById('modal-instructions-list')?.classList.contains('open')) renderRouteInstructions();
}

function renderizarCartoes() {
  const rota = ROUTE_LIST[selectedRouteIndex];
  const card = document.getElementById('card-route-accessible');

  if (!rota) {
    definirPercursoSemCalculo();
    renderizarAlternativas();
    return;
  }

  if (card) {
    const resumo = resumoAcessibilidade(rota);
    const perfilCadeira = ROUTE_PROFILE === 'wheelchair' && perfilEfetivoDaRota === 'wheelchair';
    const set = (id, texto) => { const el = document.getElementById(id); if (el) el.textContent = texto; };

    const icone = card.querySelector('.wheelchair-icon');
    if (icone) icone.style.display = perfilCadeira ? '' : 'none';

    set('selected-route-badge', _txt(rota.rotulo).toUpperCase() || 'ROTA CALCULADA');
    set('selected-route-audit', resumo.audit);
    set('selected-route-heading', tituloDaRota(rota));
    set('selected-route-time', formatarMin(rota.duration_min));
    set('selected-route-dist', formatarKm(rota.distance_km));
    set('selected-route-tag', rota.provider === 'ors' ? 'OpenRouteService'
      : (rota.provider === 'osrm' ? 'OSRM' : rota.provider_label));

    const linhas = [
      perfilCadeira
        ? 'Trajeto calculado com o perfil "cadeira de rodas" do OpenRouteService — não é auditoria das calçadas'
        : (ROUTE_PROFILE === 'wheelchair'
          ? 'Trajeto a pé calculado a seu pedido — NÃO é otimizado para cadeira de rodas'
          : 'Trajeto a pé calculado sobre a malha de pedestres do OpenStreetMap'),
      'Distância e tempo estimados pelo roteador; não incluem paradas',
      resumo.linha,
      'Você pode reportar obstáculos encontrados no percurso'
    ];
    card.querySelectorAll('.route-checklist-item > span').forEach((el, i) => {
      if (linhas[i]) el.textContent = linhas[i];
    });

    const acoes = document.getElementById('selected-route-actions');
    if (acoes) {
      acoes.replaceChildren();
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'btn-route-inline-action';
      btn.textContent = 'Ver instruções da rota';
      btn.addEventListener('click', (e) => { e.stopPropagation(); openInstructionsModal(); });
      acoes.appendChild(btn);
    }

    const count = document.getElementById('route-options-count');
    if (count) count.textContent = `${ROUTE_LIST.length} ${ROUTE_LIST.length === 1 ? 'rota calculada' : 'rotas calculadas'}`;

    const btnStart = document.getElementById('btn-start-navigation');
    if (btnStart) {
      const span = btnStart.querySelector('span');
      if (span) span.textContent = perfilCadeira ? 'Iniciar navegação em cadeira de rodas' : 'Iniciar navegação a pé';
    }
  }

  renderizarAlternativas();
}

function renderizarAlternativas() {
  const outros = ROUTE_LIST
    .map((rota, indice) => ({ rota, indice }))
    .filter(item => item.indice !== selectedRouteIndex);

  [2, 3].forEach((slot, i) => {
    const card = document.getElementById(`card-route-alt-${slot}`);
    if (!card) return;
    const item = outros[i];
    if (!item) {
      card.hidden = true;
      delete card.dataset.routeIndex;
      return;
    }

    const titulo = card.querySelector('.alt-title');
    const metricas = card.querySelector('.alt-metrics');
    const acao = card.querySelector('.alt-action');
    const resumo = `${formatarMin(item.rota.duration_min)} • ${formatarKm(item.rota.distance_km)} • ${item.rota.provider_label}`;

    card.hidden = false;
    card.dataset.routeIndex = String(item.indice);
    if (titulo) titulo.textContent = `Opção ${slot} — ${tituloDaRota(item.rota)}`;
    if (metricas) metricas.textContent = resumo;
    if (acao) acao.textContent = 'Selecionar rota →';
    card.setAttribute('aria-label', `Opção ${slot}: ${tituloDaRota(item.rota)}, ${resumo}`);
  });
}

function atualizarResumoDaRotaEscolhida() {
  const rota = ROUTE_LIST[selectedRouteIndex];
  const set = (id, texto) => { const el = document.getElementById(id); if (el) el.textContent = texto; };

  if (!rota) {
    set('chosen-route-title', 'Trajeto não calculado');
    set('chosen-badge-text', 'Sem rota');
    set('chosen-origin-text', ROUTE_ENDPOINTS.origin ? ROUTE_ENDPOINTS.origin.text : 'Origem não informada');
    set('chosen-destination-text', ROUTE_ENDPOINTS.destination ? ROUTE_ENDPOINTS.destination.text : 'Destino não informado');
    set('chosen-guarantee-text', 'Acessibilidade não verificada em campo');
    set('chosen-stat-time', '—');
    set('chosen-stat-dist', '—');
    set('chosen-stat-support', 'Sem dados');
    set('chosen-stat-access', 'Não verificado');
    return;
  }

  const resumo = resumoAcessibilidade(rota);
  set('chosen-route-title', tituloDaRota(rota));
  set('chosen-badge-text', rota.rotulo);
  set('chosen-origin-text', ROUTE_ENDPOINTS.origin ? ROUTE_ENDPOINTS.origin.text : 'Origem não informada');
  set('chosen-destination-text', ROUTE_ENDPOINTS.destination ? ROUTE_ENDPOINTS.destination.text : 'Destino não informado');
  set('chosen-guarantee-text', resumo.acesso === 'Sem barreiras reportadas' || resumo.acesso === 'Não verificado'
    ? 'Acessibilidade não verificada em campo'
    : resumo.acesso);
  set('chosen-stat-time', formatarMin(rota.duration_min));
  set('chosen-stat-dist', formatarKm(rota.distance_km));
  set('chosen-stat-support', resumo.apoio);
  set('chosen-stat-access', resumo.acesso);

  const reopen = document.getElementById('btn-reopen-popup-info');
  if (reopen) reopen.textContent = `${formatarMin(rota.duration_min)} • ${formatarKm(rota.distance_km)}`;
}

/* Perfil "wheelchair" recusado pelo servidor: o único caminho honesto é o
   usuário pedir explicitamente o trajeto a pé, que continua rotulado como tal. */
function oferecerTrajetoAPe(dados) {
  const acoes = document.getElementById('selected-route-actions');
  if (!acoes) return;
  acoes.replaceChildren();

  const rotulo = (dados && dados.alternativa_oferecida && dados.alternativa_oferecida.rotulo)
    || 'Ver o trajeto a pé (não otimizado para cadeira de rodas)';
  const btn = document.createElement('button');
  btn.type = 'button';
  btn.className = 'btn-route-inline-action';
  btn.textContent = rotulo;
  btn.addEventListener('click', () => fetchRealWalkingRoute({ permitirAPe: true, forcar: true }));
  acoes.appendChild(btn);
}

/* ==========================================================================
   6C. ACESSIBILIDADE REAL DO TRAJETO (OpenStreetMap + relatos do projeto)
   ========================================================================== */
let acessibilidadeConsulta = 0;

function haversineMetros(a, b) {
  const R = 6371000;
  const rad = v => (v * Math.PI) / 180;
  const dLat = rad(b[0] - a[0]);
  const dLng = rad(b[1] - a[1]);
  const h = Math.sin(dLat / 2) ** 2
    + Math.cos(rad(a[0])) * Math.cos(rad(b[0])) * Math.sin(dLng / 2) ** 2;
  return 2 * R * Math.asin(Math.min(1, Math.sqrt(h)));
}

/* Menor distância entre um ponto e o traçado (aproximação por segmentos). */
function distanciaAteRotaMetros(ponto, coords) {
  let menor = Infinity;
  for (let i = 0; i < coords.length - 1; i++) {
    const a = coords[i];
    const b = coords[i + 1];
    const cosLat = Math.cos((a[0] * Math.PI) / 180) || 1e-9;
    const ax = a[1] * cosLat, ay = a[0];
    const bx = b[1] * cosLat, by = b[0];
    const px = ponto[1] * cosLat, py = ponto[0];
    const dx = bx - ax, dy = by - ay;
    const tam2 = dx * dx + dy * dy;
    let t = tam2 > 0 ? ((px - ax) * dx + (py - ay) * dy) / tam2 : 0;
    t = Math.max(0, Math.min(1, t));
    menor = Math.min(menor, haversineMetros(ponto, [ay + t * dy, (ax + t * dx) / cosLat]));
  }
  return menor;
}

function bboxDaRota(coords) {
  if (!Array.isArray(coords) || coords.length < 2) return null;
  let sul = Infinity, norte = -Infinity, oeste = Infinity, leste = -Infinity;
  coords.forEach(c => {
    sul = Math.min(sul, c[0]);
    norte = Math.max(norte, c[0]);
    oeste = Math.min(oeste, c[1]);
    leste = Math.max(leste, c[1]);
  });
  const margem = 0.0015; // ~150 m: pega rampas/piso tátil na calçada do traçado
  return [sul - margem, oeste - margem, norte + margem, leste + margem]
    .map(v => v.toFixed(5)).join(',');
}

async function buscarPontosOsmDaRota(rota) {
  const caixa = bboxDaRota(rota.coords);
  if (!caixa) return { erro: true };
  const url = apiDaRota('osm_pontos', 'api/osm_pontos.php') + '?' + new URLSearchParams({
    bbox: caixa,
    limite: '80'
  }).toString();

  try {
    const res = await fetch(url, {
      headers: { 'Accept': 'application/json' },
      credentials: 'same-origin',
      signal: AbortSignal.timeout(10000)
    });
    if (!res.ok) return { erro: true };
    const data = await res.json().catch(() => null);
    if (!data || data.success !== true || !Array.isArray(data.pontos)) return { erro: true };
    return { pontos: data.pontos.filter(p => parValido(_num(p.lat), _num(p.lng))) };
  } catch (err) {
    return { erro: true };
  }
}

async function buscarOcorrenciasAtivas() {
  const url = apiDaRota('ocorrencias', 'api/ocorrencias.php') + '?' + new URLSearchParams({
    action: 'list',
    status: 'ativa',
    limit: '100'
  }).toString();

  try {
    const res = await fetch(url, {
      headers: { 'Accept': 'application/json' },
      credentials: 'same-origin',
      signal: AbortSignal.timeout(10000)
    });
    if (!res.ok) return null;
    const data = await res.json().catch(() => null);
    return data && data.success === true && Array.isArray(data.ocorrencias) ? data.ocorrencias : null;
  } catch (err) {
    return null;
  }
}

async function carregarAcessibilidadeDasRotas() {
  const consulta = ++acessibilidadeConsulta;
  const rotas = ROUTE_LIST.slice();
  if (!rotas.length) return;

  rotas.forEach(r => { r.acessibilidade = { estado: 'carregando' }; });
  renderizarCartoes();

  const ocorrencias = await buscarOcorrenciasAtivas();
  const resultadosOsm = await Promise.all(rotas.map(buscarPontosOsmDaRota));
  if (consulta !== acessibilidadeConsulta) return;

  rotas.forEach((rota, i) => {
    const osm = resultadosOsm[i];
    const osmErro = !osm || osm.erro === true;
    const pontos = [];

    if (!osmErro) {
      osm.pontos.forEach(p => {
        const lat = _num(p.lat);
        const lng = _num(p.lng);
        if (parValido(lat, lng) && distanciaAteRotaMetros([lat, lng], rota.coords) <= 25) {
          pontos.push({ nome: _txt(p.nome), tipo: _txt(p.tipo), lat, lng });
        }
      });
    }

    const ocorrenciasErro = !Array.isArray(ocorrencias);
    const barreiras = [];
    if (!ocorrenciasErro) {
      ocorrencias.forEach(o => {
        const lat = _num(o.lat);
        const lng = _num(o.lng);
        if (parValido(lat, lng) && distanciaAteRotaMetros([lat, lng], rota.coords) <= 40) {
          barreiras.push({
            titulo: _txt(o.titulo) || 'Barreira reportada',
            descricao: _txt(o.descricao),
            gravidade: _txt(o.gravidade),
            lat,
            lng
          });
        }
      });
    }

    rota.acessibilidade = {
      estado: osmErro && ocorrenciasErro ? 'indisponivel' : 'ok',
      osm: pontos.length,
      pontos,
      ocorrencias: barreiras.length,
      barreiras,
      osm_erro: osmErro,
      ocorrencias_erro: ocorrenciasErro
    };
  });

  renderizarCartoes();
  desenharMarcadoresAcessibilidade();
  atualizarResumoDaRotaEscolhida();
}

/* Pontos e alertas exibidos são sempre os da rota selecionada. Rampa/piso tátil
   vêm do OSM; barreiras vêm dos relatos ativos do projeto. Nada é contado duas
   vezes e nada é criado quando a fonte falha. */
function desenharMarcadoresAcessibilidade() {
  if (!map || !tactileMarkersGroup || !alertMarkersGroup) return;
  tactileMarkersGroup.clearLayers();
  alertMarkersGroup.clearLayers();

  const rota = ROUTE_LIST[selectedRouteIndex];
  const a = rota && rota.acessibilidade;
  if (!a || a.estado !== 'ok') return;

  a.pontos.forEach(ponto => {
    L.circleMarker([ponto.lat, ponto.lng], {
      radius: 6,
      color: '#a16207',
      weight: 2,
      fillColor: '#fbbf24',
      fillOpacity: 0.9
    }).addTo(tactileMarkersGroup).bindPopup(`
      <div class="custom-popup-content">
        <span class="popup-tag-badge popup-tag-tactile">OpenStreetMap</span>
        <h3 class="popup-heading">${_esc(ponto.nome || ponto.tipo || 'Ponto de acessibilidade')}</h3>
        <p class="popup-desc">${_esc(ponto.tipo || 'Ponto mapeado')} a até 25 m do traçado. Dado comunitário do OSM, não é vistoria.</p>
      </div>
    `);
  });

  a.barreiras.forEach(barreira => {
    L.circle([barreira.lat, barreira.lng], {
      radius: 40,
      color: '#dc2626',
      weight: 2,
      dashArray: '6, 6',
      fillColor: '#dc2626',
      fillOpacity: 0.08
    }).addTo(alertMarkersGroup);

    L.circleMarker([barreira.lat, barreira.lng], {
      radius: 6,
      color: '#b91c1c',
      weight: 2,
      fillColor: '#dc2626',
      fillOpacity: 0.95
    }).addTo(alertMarkersGroup).bindPopup(`
      <div class="custom-popup-content">
        <span class="popup-tag-badge popup-tag-risk">Barreira reportada</span>
        <h3 class="popup-heading">${_esc(barreira.titulo)}</h3>
        <p class="popup-desc">${_esc(barreira.descricao || 'Sem descrição no relato.')}${barreira.gravidade ? ' Gravidade informada: ' + _esc(barreira.gravidade) + '.' : ''} A até 40 m do traçado.</p>
      </div>
    `);
  });
}

/* ==========================================================================
   6. CONTROLES DO MAPA (ZOOM, RECENTRALIZAR E CAMADAS PCD)
   ========================================================================== */

/* O painel fala somente dos pontos que existem de verdade. Sem origem e/ou sem
   destino, ele diz qual peça falta em vez de exibir endereços de exemplo. */
function sincronizarPainelDeRota() {
  const origem = ROUTE_ENDPOINTS.origin;
  const destino = ROUTE_ENDPOINTS.destination;
  const setTitle = (id, texto) => { const el = document.getElementById(id); if (el) el.textContent = texto; };

  setTitle('origin-title', origem ? (_txt(origem.text) || 'Ponto de partida') : 'Origem não informada');
  setTitle('origin-address', origem
    ? ([_txt(origem.address), origem.origemDado === 'gps' ? 'GPS deste navegador' : ''].filter(Boolean).join(' — ')
        || 'Ponto de partida informado')
    : 'Use o GPS, escolha no mapa ou informe na busca');
  setTitle('destination-title', destino ? (_txt(destino.text) || 'Destino') : 'Destino não informado');
  setTitle('destination-address', destino
    ? ([_txt(destino.address), _txt(destino.municipio)].filter(Boolean).join(' — ') || 'Endereço não informado')
    : 'Escolha um endereço na busca para calcular');

  const count = document.getElementById('route-options-count');
  if (count && !ROUTE_LIST.length) count.textContent = 'Aguardando cálculo';

  garantirBotaoEscolherOrigem(origem);

  if (!ENDPOINTS_REAIS.origem && !ENDPOINTS_REAIS.destino) {
    definirPercursoSemCalculo('Informe o ponto de partida e o destino para calcular');
  } else if (!ENDPOINTS_REAIS.origem) {
    definirPercursoSemCalculo('Falta o ponto de partida: use o GPS ou escolha no mapa');
  } else if (!ENDPOINTS_REAIS.destino) {
    definirPercursoSemCalculo('Falta o destino: escolha um endereço na busca');
  } else if (!ROUTE_LIST.length) {
    definirPercursoSemCalculo('Calculando o trajeto com o roteador…');
  }
}

function garantirBotaoEscolherOrigem(origem) {
  const box = document.getElementById('origin-box');
  if (!box) return;

  let btn = document.getElementById('btn-pick-origin');
  if (!btn) {
    const wrap = box.querySelector('.location-text-wrap') || box;
    btn = document.createElement('button');
    btn.type = 'button';
    btn.id = 'btn-pick-origin';
    btn.className = 'btn-pick-origin';
    btn.addEventListener('click', (e) => {
      e.stopPropagation();
      alternarEscolhaDeOrigem();
    });
    wrap.appendChild(btn);
  }

  const ativo = document.body.classList.contains('origin-pick-mode');
  btn.textContent = ativo
    ? 'Cancelar escolha no mapa'
    : (origem ? 'Alterar ponto de partida no mapa' : 'Escolher ponto de partida no mapa');
}

function definirModoEscolhaDeOrigem(ativo) {
  document.body.classList.toggle('origin-pick-mode', ativo);
  if (map) {
    map.getContainer().style.cursor = ativo ? 'crosshair' : '';
  }
  garantirBotaoEscolherOrigem(ROUTE_ENDPOINTS.origin);
}

function alternarEscolhaDeOrigem() {
  const ativo = document.body.classList.contains('origin-pick-mode');
  definirModoEscolhaDeOrigem(!ativo);
  showToast(ativo
    ? 'Escolha no mapa cancelada.'
    : 'Clique no mapa no ponto de partida.');
}

function aoClicarNoMapaParaOrigem(e) {
  if (!document.body.classList.contains('origin-pick-mode')) return;
  definirModoEscolhaDeOrigem(false);
  definirOrigemManual(e.latlng.lat, e.latlng.lng);
}

/* Origem escolhida no mapa: o ponto é do usuário e vale mesmo que a
   geocodificação reversa falhe — nesse caso o rótulo continua "Ponto escolhido
   no mapa", nunca um endereço inventado. */
async function definirOrigemManual(lat, lng) {
  if (!parValido(lat, lng)) {
    showToast('Ponto fora da área aceita. Clique novamente dentro do mapa.');
    return;
  }

  const rotuloInicial = 'Ponto escolhido no mapa';
  sessionStorage.setItem('passeLivre_origem_manual_lat', String(lat));
  sessionStorage.setItem('passeLivre_origem_manual_lng', String(lng));
  sessionStorage.setItem('passeLivre_origem_manual_nome', rotuloInicial);

  ROUTE_ENDPOINTS.origin = {
    coords: [lat, lng],
    text: rotuloInicial,
    address: 'Origem selecionada manualmente no mapa',
    origemDado: 'mapa'
  };
  ENDPOINTS_REAIS.origem = true;
  ROUTE_LIST = [];
  LIVE_ROUTE = null;
  rotaAssinaturaCalculada = '';

  atualizarMarcadores();
  sincronizarPainelDeRota();
  fetchRealWalkingRoute({ forcar: true });
  showToast('Ponto de partida definido. Calculando o trajeto…');

  const url = apiDaRota('geocode', 'api/geocode.php') + '?' + new URLSearchParams({
    action: 'reverse',
    lat: String(lat),
    lng: String(lng)
  }).toString();

  try {
    const res = await fetch(url, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' });
    if (!res.ok) return;
    const data = await res.json().catch(() => null);
    const achado = data && data.success === true && Array.isArray(data.results) ? data.results[0] : null;
    if (!achado) return;

    const atual = ROUTE_ENDPOINTS.origin;
    if (!atual || atual.origemDado !== 'mapa'
        || atual.coords[0] !== lat || atual.coords[1] !== lng) return;

    const nome = (_txt(achado.name).trim() || _txt(achado.address).trim()).slice(0, 120);
    if (!nome) return;
    atual.text = nome;
    atual.address = _txt(achado.address).trim() || 'Endereço obtido por geocodificação reversa';
    sessionStorage.setItem('passeLivre_origem_manual_nome', nome);
    atualizarMarcadorOrigem();
    sincronizarPainelDeRota();
  } catch (err) {
    /* o ponto escolhido continua válido mesmo sem o nome da rua */
  }
}

/* GPS só é oferecido quando o navegador realmente permite (contexto seguro).
   Em HTTP na rede local a permissão é negada pelo navegador — a tela explica e
   oferece a escolha manual no mapa, sem inventar posição. */
function gpsDisponivel() {
  return window.isSecureContext === true && 'geolocation' in navigator;
}

function pedirLocalizacaoParaOrigem() {
  if (!gpsDisponivel()) {
    showToast('Este navegador não libera GPS aqui (é preciso HTTPS ou localhost). Escolha o ponto no mapa.');
    definirModoEscolhaDeOrigem(true);
    return;
  }

  showToast('Buscando sua localização para iniciar a rota…');
  navigator.geolocation.getCurrentPosition(pos => {
    const lat = pos.coords.latitude;
    const lng = pos.coords.longitude;
    if (!parValido(lat, lng)) {
      showToast('Localização recebida fora da área atendida.');
      return;
    }

    const precisao = Number(pos.coords.accuracy);
    sessionStorage.setItem('passeLivre_gps_lat', String(lat));
    sessionStorage.setItem('passeLivre_gps_lng', String(lng));

    ROUTE_ENDPOINTS.origin = {
      coords: [lat, lng],
      text: 'Minha localização atual',
      address: `GPS deste navegador${Number.isFinite(precisao) ? ` (precisão ~${Math.round(precisao)} m)` : ''}`,
      origemDado: 'gps'
    };
    ENDPOINTS_REAIS.origem = true;
    ROUTE_LIST = [];
    LIVE_ROUTE = null;
    rotaAssinaturaCalculada = '';

    atualizarMarcadores();
    sincronizarPainelDeRota();
    fetchRealWalkingRoute({ forcar: true });

    if (Number.isFinite(precisao) && precisao > 100) {
      showToast(`GPS com precisão baixa (~${Math.round(precisao)} m). Confira o ponto no mapa.`);
    }
  }, err => {
    const negado = err && (err.code === err.PERMISSION_DENIED || err.code === 1);
    showToast(negado
      ? 'Permissão de localização negada. Escolha o ponto de partida no mapa.'
      : 'Não foi possível ler o GPS. Escolha o ponto de partida no mapa.');
    definirModoEscolhaDeOrigem(true);
  }, { enableHighAccuracy: true, timeout: 12000, maximumAge: 60000 });
}

function initMapControls() {
  const btnZoomIn = document.getElementById('btn-zoom-in');
  const btnZoomOut = document.getElementById('btn-zoom-out');
  const btnRecenter = document.getElementById('btn-recenter');
  const btnTogglePcd = document.getElementById('btn-toggle-pcd');

  if (btnZoomIn) {
    btnZoomIn.addEventListener('click', () => map.zoomIn());
  }

  if (btnZoomOut) {
    btnZoomOut.addEventListener('click', () => map.zoomOut());
  }

  if (btnRecenter) {
    btnRecenter.addEventListener('click', () => {
      if (!ENDPOINTS_REAIS.origem && ENDPOINTS_REAIS.destino) {
        pedirLocalizacaoParaOrigem();
        return;
      }
      if (!ENDPOINTS_REAIS.origem && !ENDPOINTS_REAIS.destino) {
        showToast('Nenhum ponto definido ainda: informe a origem e o destino.');
        return;
      }
      enquadraRota({ animate: true, duration: 0.8 });
      showToast(ROUTE_LIST.length ? 'Mapa centralizado no trajeto calculado' : 'Mapa centralizado no ponto conhecido');
    });
  }

  if (btnTogglePcd) {
    btnTogglePcd.addEventListener('click', () => {
      isAccessibilityVisible = !isAccessibilityVisible;
      btnTogglePcd.classList.toggle('active-pcd', isAccessibilityVisible);

      const total = tactileMarkersGroup.getLayers().length + alertMarkersGroup.getLayers().length;

      if (isAccessibilityVisible) {
        map.addLayer(tactileMarkersGroup);
        map.addLayer(alertMarkersGroup);
        showToast(total
          ? `Camadas de acessibilidade ativadas (${total} elemento${total === 1 ? '' : 's'} no trajeto)`
          : 'Camadas ativadas, mas não há ponto do OSM nem barreira reportada neste trajeto.');
      } else {
        map.removeLayer(tactileMarkersGroup);
        map.removeLayer(alertMarkersGroup);
        showToast('Camadas de Acessibilidade ocultadas');
      }
    });
  }

  // Interatividade da Legenda do Rodapé
  const legAudited = document.getElementById('legend-audited');
  const legRisk = document.getElementById('legend-risk');
  const legTactile = document.getElementById('legend-tactile');

  if (legAudited) {
    legAudited.addEventListener('click', () => {
      if (!LIVE_ROUTE || !ROUTE_LIST.length) {
        showToast('O trajeto calculado ainda não está disponível.');
        return;
      }
      enquadraRota();
      if (auditedPolyline) auditedPolyline.openPopup();
    });
  }

  if (legRisk) {
    legRisk.addEventListener('click', () => {
      const marcadores = alertMarkersGroup
        ? alertMarkersGroup.getLayers().filter(l => typeof l.getLatLng === 'function')
        : [];
      if (!marcadores.length) {
        showToast('Nenhuma barreira ativa reportada a até 40 m deste trajeto.');
        return;
      }
      map.setView(marcadores[0].getLatLng(), Math.max(map.getZoom(), 16), { animate: true });
      marcadores.slice(0, 3).forEach(l => l.openPopup());
    });
  }

  if (legTactile) {
    legTactile.addEventListener('click', () => {
      const marcadores = tactileMarkersGroup
        ? tactileMarkersGroup.getLayers().filter(l => typeof l.getLatLng === 'function')
        : [];
      if (!marcadores.length) {
        showToast('Nenhum ponto de rampa ou piso tátil do OSM mapeado a até 25 m deste trajeto.');
        return;
      }
      showToast(`Exibindo ${marcadores.length} ponto${marcadores.length === 1 ? '' : 's'} do OpenStreetMap (dado comunitário, não é vistoria)`);
      map.setView(marcadores[0].getLatLng(), Math.max(map.getZoom(), 16), { animate: true });
      marcadores.slice(0, 3).forEach(l => l.openPopup());
    });
  }
}

/* Rotas, rótulos e métricas vêm exclusivamente de api/rota.php (ROUTE_LIST).
   A antiga tabela fixa de opções de Suzano foi removida junto com a linha de
   ônibus fictícia. */

/* ==========================================================================
   7. INTERAÇÕES DO PAINEL E EXIBIÇÃO DO POP UP DE ROTA ESCOLHIDA
   ========================================================================== */
function initRouteInteractions() {
  const btnSwap = document.getElementById('btn-swap-locations');
  if (btnSwap) {
    btnSwap.addEventListener('click', inverterOrigemEDestino);
  }

  // Card grande = rota selecionada; slots 2 e 3 = alternativas reais restantes.
  const cards = [
    document.getElementById('card-route-accessible'),
    document.getElementById('card-route-alt-2'),
    document.getElementById('card-route-alt-3')
  ];

  cards.forEach(card => {
    if (!card) return;
    const acionar = () => {
      if (!ROUTE_LIST.length) {
        showToast('A rota ainda não foi calculada para estes pontos.');
        return;
      }
      const indice = card.id === 'card-route-accessible'
        ? 0
        : parseInt(card.dataset.routeIndex, 10);
      if (!Number.isInteger(indice) || indice < 0 || indice >= ROUTE_LIST.length) return;
      selecionarRota(indice, { animar: false });
      openChosenRoutePopup();
    };

    card.addEventListener('click', acionar);
    card.addEventListener('keydown', (e) => {
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        acionar();
      }
    });
  });

  const btnStartNavPanel = document.getElementById('btn-start-navigation');
  if (btnStartNavPanel) {
    btnStartNavPanel.addEventListener('click', () => {
      if (!ROUTE_LIST.length) {
        showToast('Calcule a rota antes de iniciar a navegação.');
        return;
      }
      openChosenRoutePopup();
    });
  }

  // Controles do Pop Up da Rota Escolhida
  const btnCloseChosen = document.getElementById('btn-close-chosen-modal');
  const overlayChosen = document.getElementById('route-chosen-overlay');
  const btnTogglePanel = document.getElementById('btn-toggle-route-panel');
  const btnReopenPopup = document.getElementById('btn-reopen-popup');

  if (btnCloseChosen) {
    btnCloseChosen.addEventListener('click', closeChosenRoutePopup);
  }

  if (btnTogglePanel) {
    btnTogglePanel.addEventListener('click', closeChosenRoutePopup);
  }

  if (btnReopenPopup) {
    btnReopenPopup.addEventListener('click', () => {
      openChosenRoutePopup();
    });
  }

  // Fechar ao clicar fora do card (no overlay)
  if (overlayChosen) {
    overlayChosen.addEventListener('click', (e) => {
      if (e.target === overlayChosen) {
        closeChosenRoutePopup();
      }
    });
  }

  // Ações dentro do Pop Up da Rota Escolhida
  const btnReportObstacle = document.getElementById('btn-report-obstacle');
  const btnViewInstructions = document.getElementById('btn-view-instructions');
  const btnContinueStepNav = document.getElementById('btn-continue-step-nav');

  if (btnReportObstacle) {
    btnReportObstacle.addEventListener('click', () => {
      openObstacleModal();
    });
  }

  if (btnViewInstructions) {
    btnViewInstructions.addEventListener('click', () => {
      openInstructionsModal();
    });
  }

  if (btnContinueStepNav) {
    btnContinueStepNav.addEventListener('click', () => iniciarNavegacao(btnContinueStepNav));
  }

  // Inicializar handlers dos modais de obstáculo e instruções
  initAuxiliaryModals();

  // Verificar se há parâmetro na URL para abrir automaticamente o popup
  checkAutoOpenParam();
}

/* Troca real dos dois pontos: inverte coordenadas, textos, marcadores e a
   sessão usada pela navegação. Sem os dois pontos não há o que inverter. */
function inverterOrigemEDestino() {
  const origem = ROUTE_ENDPOINTS.origin;
  const destino = ROUTE_ENDPOINTS.destination;
  if (!origem || !destino) {
    showToast('Para inverter, informe a origem e o destino primeiro.');
    return;
  }

  ROUTE_ENDPOINTS.origin = Object.assign({}, destino, {
    origemDado: 'invertido',
    address: 'Destino anterior usado como ponto de partida'
  });
  ROUTE_ENDPOINTS.destination = Object.assign({}, origem, {
    origemDado: 'invertido',
    accessStatus: 'unverified',
    accessText: 'Acessibilidade não verificada para este ponto.'
  });
  ENDPOINTS_REAIS.origem = true;
  ENDPOINTS_REAIS.destino = true;

  // A sessão passa a refletir o novo par: o GPS antigo não pode voltar a ser a
  // origem ao recarregar a página.
  sessionStorage.removeItem('passeLivre_gps_lat');
  sessionStorage.removeItem('passeLivre_gps_lng');
  sessionStorage.setItem('passeLivre_origem_manual_lat', String(ROUTE_ENDPOINTS.origin.coords[0]));
  sessionStorage.setItem('passeLivre_origem_manual_lng', String(ROUTE_ENDPOINTS.origin.coords[1]));
  sessionStorage.setItem('passeLivre_origem_manual_nome', _txt(ROUTE_ENDPOINTS.origin.text));
  sessionStorage.setItem('passeLivre_dest_lat', String(ROUTE_ENDPOINTS.destination.coords[0]));
  sessionStorage.setItem('passeLivre_dest_lng', String(ROUTE_ENDPOINTS.destination.coords[1]));
  sessionStorage.setItem('passeLivre_dest_text', _txt(ROUTE_ENDPOINTS.destination.text));
  sessionStorage.setItem('passeLivre_dest_municipio', _txt(ROUTE_ENDPOINTS.destination.municipio || ''));
  if (ROUTE_ENDPOINTS.destination.id) sessionStorage.setItem('passeLivre_dest_id', _txt(ROUTE_ENDPOINTS.destination.id));
  else sessionStorage.removeItem('passeLivre_dest_id');

  ROUTE_LIST = [];
  LIVE_ROUTE = null;
  selectedRouteIndex = 0;
  rotaAssinaturaCalculada = '';

  atualizarMarcadores();
  sincronizarPainelDeRota();
  fetchRealWalkingRoute({ forcar: true });
  showToast('Origem e destino invertidos. Calculando o trajeto…');
}

let navegacaoEmAndamento = false;

async function iniciarNavegacao(botao) {
  if (navegacaoEmAndamento) return;

  const rota = ROUTE_LIST[selectedRouteIndex];
  if (!rota || !ENDPOINTS_REAIS.origem || !ENDPOINTS_REAIS.destino) {
    showToast('Calcule uma rota com origem e destino antes de iniciar a navegação.');
    return;
  }

  navegacaoEmAndamento = true;
  const htmlOriginal = botao ? botao.innerHTML : '';
  if (botao) {
    botao.disabled = true;
    botao.innerHTML = '<span>Salvando a rota…</span>';
  }

  try {
    const rotaId = await salvarRotaSelecionada();
    if (!rotaId) return;

    guardarDestinoNaSessao();
    sessionStorage.setItem('passeLivre_rota_id', String(rotaId));
    window.location.href = 'navegacao.php?rota_id=' + encodeURIComponent(rotaId);
  } finally {
    navegacaoEmAndamento = false;
    if (botao) {
      botao.disabled = false;
      botao.innerHTML = htmlOriginal;
    }
  }
}

/* Persiste exatamente a rota exibida: geometria, passos, perfil e métricas do
   roteador. A navegação depois lê este mesmo registro pelo id. */
async function salvarRotaSelecionada() {
  const rota = ROUTE_LIST[selectedRouteIndex];
  if (!rota || !ENDPOINTS_REAIS.origem || !ENDPOINTS_REAIS.destino) return null;

  const origem = ROUTE_ENDPOINTS.origin;
  const destino = ROUTE_ENDPOINTS.destination;
  const distanciaKm = Number(rota.distance_km);
  const duracaoMin = Number(rota.duration_min);

  if (!Number.isFinite(distanciaKm) || !Number.isFinite(duracaoMin) || !rota.coords.length) {
    showToast('A rota não tem métricas do roteador; ela não será salva com números inventados.');
    return null;
  }

  const corpo = {
    origin_text: (_txt(origem.text) || 'Ponto de partida').slice(0, 200),
    destination_text: (_txt(destino.text) || 'Destino').slice(0, 200),
    origin_lat: origem.coords[0],
    origin_lng: origem.coords[1],
    dest_lat: destino.coords[0],
    dest_lng: destino.coords[1],
    route_type: rota.profile_effective === 'wheelchair' ? 'cadeira_de_rodas' : 'a_pe',
    distance_km: distanciaKm,
    distance_m: rota.distance_m,
    duration_min: duracaoMin,
    duration_s: rota.duration_s,
    profile: rota.profile_effective,
    provider: rota.provider,
    geometria: rota.coords,
    passos: rota.steps.map((passo, i) => ({
      ordem: i + 1,
      instrucao: passo.instruction,
      icone: 'turn-right',
      distancia_m: Math.max(0, Math.round(Number(passo.distance_m) || 0)),
      acessivel: 0
    }))
  };

  try {
    const res = await fetch(apiDaRota('salvar_rota', 'api/rotas.php') + '?action=plan', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      credentials: 'same-origin',
      body: JSON.stringify(corpo)
    });
    const data = await res.json().catch(() => null);

    if (res.ok && data && data.success === true && data.rota_id) {
      return String(data.rota_id);
    }
    if (res.status === 401) {
      showToast('Sua sessão expirou. Entre novamente para iniciar a navegação.');
    } else {
      showToast((data && (data.error || data.message)) || 'Não foi possível salvar a rota agora.');
    }
    return null;
  } catch (err) {
    console.warn('[rota] falha ao salvar a rota:', err);
    showToast('Sem conexão com o servidor: a navegação não foi iniciada.');
    return null;
  }
}

function guardarDestinoNaSessao() {
  const destino = ROUTE_ENDPOINTS.destination;
  const origem = ROUTE_ENDPOINTS.origin;
  if (!destino) return;

  sessionStorage.setItem('passeLivre_dest_lat', String(destino.coords[0]));
  sessionStorage.setItem('passeLivre_dest_lng', String(destino.coords[1]));
  sessionStorage.setItem('passeLivre_dest_text', _txt(destino.text));
  sessionStorage.setItem('passeLivre_dest_municipio', _txt(destino.municipio || ''));
  if (destino.id) sessionStorage.setItem('passeLivre_dest_id', _txt(destino.id));
  else sessionStorage.removeItem('passeLivre_dest_id');

  if (origem && origem.origemDado === 'gps') {
    sessionStorage.setItem('passeLivre_gps_lat', String(origem.coords[0]));
    sessionStorage.setItem('passeLivre_gps_lng', String(origem.coords[1]));
  }
}

/* ==========================================================================
   FUNÇÃO PRINCIPAL: ABRIR POP UP DA ROTA ESCOLHIDA (CONFORME IMAGEM)
   ========================================================================== */
function openChosenRoutePopup() {
  const rota = ROUTE_LIST[selectedRouteIndex];
  if (!rota) {
    showToast('Calcule o trajeto antes de abrir os detalhes da rota.');
    return;
  }

  atualizarResumoDaRotaEscolhida();

  // Minimizar painel esquerdo para deixar o mapa amplo
  const panel = document.querySelector('.route-card-panel');
  if (panel) {
    panel.classList.add('minimized');
  }

  // Exibir botão flutuante de restauração do painel
  const btnTogglePanel = document.getElementById('btn-toggle-route-panel');
  if (btnTogglePanel) {
    btnTogglePanel.classList.add('show');
  }

  // Ocultar botão de reabertura enquanto o popup está aberto
  const btnReopenPopup = document.getElementById('btn-reopen-popup');
  if (btnReopenPopup) {
    btnReopenPopup.classList.remove('show');
  }

  // Ativar o overlay do Popup da Rota Escolhida
  const overlayChosen = document.getElementById('route-chosen-overlay');
  if (overlayChosen) {
    overlayChosen.classList.add('active');
  }

  // Centralizar o mapa no enquadramento exato para contemplar a rota e os callouts
  enquadraRota({ animate: true, duration: 0.6 });

  showToast(`Rota ativa: ${tituloDaRota(rota)} (${formatarMin(rota.duration_min)} • ${formatarKm(rota.distance_km)})`);
}

/* Fechar Popup da Rota Escolhida */
function closeChosenRoutePopup() {
  const overlayChosen = document.getElementById('route-chosen-overlay');
  if (overlayChosen) {
    overlayChosen.classList.remove('active');
  }

  const panel = document.querySelector('.route-card-panel');
  if (panel) {
    panel.classList.remove('minimized');
  }

  const btnTogglePanel = document.getElementById('btn-toggle-route-panel');
  if (btnTogglePanel) {
    btnTogglePanel.classList.remove('show');
  }

  const btnReopenPopup = document.getElementById('btn-reopen-popup');
  if (btnReopenPopup) {
    btnReopenPopup.classList.add('show');
  }

  // Reajustar enquadramento do mapa para considerar o painel aberto
  enquadraRota({ animate: true, duration: 0.6 });
}

/* ==========================================================================
   MODAIS AUXILIARES: REPORTAR OBSTÁCULO E LISTA DE INSTRUÇÕES
   ========================================================================== */
function initAuxiliaryModals() {
  // 1. Modal Reportar Obstáculo
  const modalObstacle = document.getElementById('modal-report-obstacle');
  const btnCloseObstacle = document.getElementById('btn-close-obstacle-modal');
  const btnCancelObstacle = document.getElementById('btn-cancel-obstacle');
  const formObstacle = document.getElementById('form-report-obstacle');

  function closeObstacleModal() {
    if (modalObstacle) modalObstacle.classList.remove('open');
  }

  if (btnCloseObstacle) btnCloseObstacle.addEventListener('click', closeObstacleModal);
  if (btnCancelObstacle) btnCancelObstacle.addEventListener('click', closeObstacleModal);

  if (modalObstacle) {
    modalObstacle.addEventListener('click', (e) => {
      if (e.target === modalObstacle) closeObstacleModal();
    });
  }

  if (formObstacle) {
    formObstacle.addEventListener('submit', async (e) => {
      e.preventDefault();

      const tipoEl = document.getElementById('obstacle-type');
      const localEl = document.getElementById('obstacle-location');
      const notasEl = document.getElementById('obstacle-notes');
      const tipo = tipoEl && tipoEl.selectedIndex >= 0 ? tipoEl.options[tipoEl.selectedIndex].text : 'Barreira na calçada';
      const local = _txt(localEl && localEl.value);
      const notas = _txt(notasEl && notasEl.value);

      if (!local) {
        showToast('Informe o local aproximado da barreira.');
        if (localEl) localEl.focus();
        return;
      }

      const corpo = {
        titulo: ENDPOINTS_REAIS.origem && ENDPOINTS_REAIS.destino
          ? `${tipo} no trajeto ${ROUTE_ENDPOINTS.origin.text} → ${ROUTE_ENDPOINTS.destination.text}`
          : `${tipo} reportada no mapa`,
        descricao: notas || `Barreira reportada pelo planejador de rotas: ${tipo.toLowerCase()}.`,
        categoria: tipo,
        endereco: local,
        gravidade: 'media',
        lat: ENDPOINTS_REAIS.origem ? ROUTE_ENDPOINTS.origin.coords[0] : null,
        lng: ENDPOINTS_REAIS.origem ? ROUTE_ENDPOINTS.origin.coords[1] : null
      };

      const botao = formObstacle.querySelector('button[type="submit"]');
      if (botao) botao.disabled = true;
      try {
        const res = await fetch('api/ocorrencias.php?action=create', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
          credentials: 'same-origin',
          body: JSON.stringify(corpo)
        });
        const data = await res.json().catch(() => null);
        if (res.ok && data && data.success) {
          closeObstacleModal();
          formObstacle.reset();
          showToast(`Barreira registrada${data.protocolo ? ` como ${data.protocolo}` : ''}. Obrigado pela Auditoria Cidadã.`);
        } else {
          showToast((data && (data.message || data.error)) || 'Não foi possível registrar a barreira agora. Tente novamente.');
        }
      } catch (err) {
        console.warn('[rota] falha ao registrar barreira:', err);
        showToast('Sem conexão com o servidor de ocorrências. Nenhum registro foi criado.');
      } finally {
        if (botao) botao.disabled = false;
      }
    });
  }

  // 2. Modal Lista de Instruções
  const modalInstructions = document.getElementById('modal-instructions-list');
  const btnCloseInstructions = document.getElementById('btn-close-instructions-modal');
  const btnOkInstructions = document.getElementById('btn-ok-instructions');
  const btnModalStartNav = document.getElementById('btn-modal-start-nav');

  function closeInstructionsModal() {
    if (!modalInstructions) return;
    modalInstructions.classList.remove('open');
    modalInstructions.setAttribute('aria-hidden', 'true');
    document.getElementById('btn-view-instructions')?.focus();
  }

  if (btnCloseInstructions) btnCloseInstructions.addEventListener('click', closeInstructionsModal);
  if (btnOkInstructions) btnOkInstructions.addEventListener('click', closeInstructionsModal);

  if (modalInstructions) {
    modalInstructions.addEventListener('click', (e) => {
      if (e.target === modalInstructions) closeInstructionsModal();
    });
  }

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && modalInstructions?.classList.contains('open')) {
      closeInstructionsModal();
    }
  });

  if (btnModalStartNav) {
    btnModalStartNav.addEventListener('click', () => {
      closeInstructionsModal();
      iniciarNavegacao(btnModalStartNav);
    });
  }
}

function openObstacleModal() {
  const modal = document.getElementById('modal-report-obstacle');
  if (modal) modal.classList.add('open');
}

function openInstructionsModal() {
  const modal = document.getElementById('modal-instructions-list');
  if (!modal) return;

  renderRouteInstructions();
  modal.setAttribute('aria-hidden', 'false');
  modal.classList.add('open');
  document.getElementById('btn-close-instructions-modal')?.focus();
}

function renderRouteInstructions() {
  const rota = ROUTE_LIST[selectedRouteIndex];
  const list = document.getElementById('route-instructions-list');
  const summary = document.getElementById('instructions-route-summary');
  const emptyState = document.getElementById('instructions-empty-state');
  const title = document.getElementById('instructions-modal-title');

  if (!list || !summary || !emptyState) return;

  const origemTexto = ROUTE_ENDPOINTS.origin ? ROUTE_ENDPOINTS.origin.text : 'Origem não informada';
  const destinoTexto = ROUTE_ENDPOINTS.destination ? ROUTE_ENDPOINTS.destination.text : 'Destino não informado';

  if (title) title.textContent = rota ? `Instruções — ${tituloDaRota(rota)}` : 'Instruções da rota';
  summary.textContent = rota
    ? `${origemTexto} → ${destinoTexto} • ${formatarKm(rota.distance_km)} • ${formatarMin(rota.duration_min)} • ${rota.provider_label}`
    : 'Nenhum trajeto calculado';
  list.replaceChildren();

  const steps = rota ? rota.steps : [];
  emptyState.hidden = steps.length > 0;
  emptyState.textContent = steps.length
    ? ''
    : (rota
      ? 'O roteador não devolveu instruções rua a rua para este trajeto. A geometria e as métricas continuam disponíveis no mapa.'
      : 'Calcule o trajeto para ver as instruções rua a rua.');

  steps.forEach((step, index) => {
    const item = document.createElement('li');
    item.className = 'instruction-step-item';

    const number = document.createElement('span');
    number.className = 'step-num-badge';
    number.textContent = String(index + 1);

    const description = document.createElement('div');
    description.className = 'step-desc-wrap';
    const instruction = document.createElement('strong');
    instruction.textContent = step.instruction;
    description.appendChild(instruction);

    if (step.distance_m > 0) {
      const detail = document.createElement('p');
      detail.textContent = step.distance_m >= 1000
        ? `Distância do trecho: ${(step.distance_m / 1000).toLocaleString('pt-BR', { maximumFractionDigits: 1 })} km`
        : `Distância do trecho: ${Math.round(step.distance_m)} m`;
      description.appendChild(detail);
    }

    item.append(number, description);
    const lat = step.lat === null || step.lat === undefined ? NaN : Number(step.lat);
    const lng = step.lng === null || step.lng === undefined ? NaN : Number(step.lng);
    if (Number.isFinite(lat) && Number.isFinite(lng) && map) {
      const focusButton = document.createElement('button');
      focusButton.type = 'button';
      focusButton.className = 'instruction-map-button';
      focusButton.textContent = 'Ver no mapa';
      focusButton.addEventListener('click', () => {
        map.setView([lat, lng], Math.max(map.getZoom(), 17), { animate: true });
      });
      item.appendChild(focusButton);
    }
    list.appendChild(item);
  });
}

/* Verifica parâmetros para auto-abertura */
function checkAutoOpenParam() {
  const urlParams = new URLSearchParams(window.location.search);
  if (urlParams.has('auto') || urlParams.has('selected') || urlParams.has('escolher') || window.location.hash === '#escolhida') {
    setTimeout(() => {
      if (ROUTE_LIST.length) openChosenRoutePopup();
    }, 400);
  }
}

/* ==========================================================================
   8. HUD DE NAVEGAÇÃO EM TEMPO REAL E LEITURA DE VOZ
   ========================================================================== */
function initNavigationHUD() {
  const btnStopNav = document.getElementById('btn-stop-nav');
  const navHud = document.getElementById('navigation-hud');

  if (btnStopNav && navHud) {
    btnStopNav.addEventListener('click', () => {
      navHud.style.display = 'none';
      showToast('Navegação pausada.');
    });
  }
}

/* Síntese de voz em português para acessibilidade de pessoas com baixa visão ou cegas */
function speakInstruction(text) {
  if ('speechSynthesis' in window) {
    window.speechSynthesis.cancel();
    const utterance = new SpeechSynthesisUtterance(text);
    utterance.lang = 'pt-BR';
    utterance.rate = 1.0;
    window.speechSynthesis.speak(utterance);
  }
}

/* ==========================================================================
   9. CAMPO DE BUSCA NO TOPO
   ========================================================================== */
function initHeaderSearch() {
  const searchInput = document.getElementById('global-search-input');
  if (!searchInput) return;

  searchInput.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') {
      const q = searchInput.value.trim();
      if (q) {
        window.location.href = `lugares-encontrados.php?q=${encodeURIComponent(q)}`;
      }
    }
  });
}

/* ==========================================================================
   10. TOAST DE FEEDBACK
   ========================================================================== */
function showToast(message) {
  const toast = document.getElementById('toast-feedback');
  if (!toast) return;

  toast.textContent = message;
  toast.classList.add('show');

  setTimeout(() => {
    toast.classList.remove('show');
  }, 3200);
}

/* A persistência da rota escolhida acontece em iniciarNavegacao()/salvarRotaSelecionada(),
   via api/rotas.php (tabelas rotas, rota_opcoes e rota_passos). O alerta de
   barreira é gravado no envio do formulário (api/ocorrencias.php). */
