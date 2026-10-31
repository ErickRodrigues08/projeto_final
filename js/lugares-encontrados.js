/**
 * Passe Livre — Lugares Encontrados
 * Integracao com Mapa Leaflet (OpenStreetMap) + SQL via PLDB
 */

// A sessao do servidor e a fonte de verdade do perfil; evita que um
// localStorage antigo (de outro usuario) sobrescreva o header.
(function syncServerUser() {
  const el = document.getElementById('php-user-data');
  if (!el || !window.PasseLivreAuth) return;
  try {
    const user = JSON.parse(el.textContent || 'null');
    if (user && user.id) {
      window.PasseLivreAuth.setUser(user, window.PasseLivreAuth.getToken());
    }
  } catch (_) {}
})();

/**
 * Contrato do centro da busca (definido fora do DOMContentLoaded porque o script
 * inline do mapa e registrado depois deste arquivo e precisa encontrar a promise
 * ja existente quando rodar).
 *
 * Lista de resultados, marcadores do mapa e camada OpenStreetMap usam o MESMO
 * centro: é assim que a página garante que o mapa não mostra uma região enquanto
 * a lista mostra outra.
 */
window.plBuscarCentro = (function () {
  let resolver;
  const api = {
    current: null,
    ready: new Promise((resolve) => { resolver = resolve; }),
    publicar(ctx) {
      api.current = ctx;
      resolver(ctx);
      document.dispatchEvent(new CustomEvent('pl:centro', { detail: ctx }));
    },
    ouvir(cb) {
      if (api.current) cb(api.current);
      document.addEventListener('pl:centro', (e) => cb(e.detail));
    }
  };
  return api;
}());

document.addEventListener('DOMContentLoaded', async () => {

  const params     = new URLSearchParams(window.location.search);
  const query      = params.get('q') || '';

  // =========================================================================
  // 0. LE O QUE FOI CONFIRMADO NA TELA DE EXPLORACAO
  // =========================================================================
  const body      = document.body;
  const destId    = parseInt(body.dataset.destId   || '0', 10);
  const destName  = body.dataset.destName  || '';
  const destQuery = body.dataset.destQuery || query;

  const destMunicipio = (body.dataset.destMunicipio || '').trim();
  const destEndereco  = (body.dataset.destEndereco  || '').trim();
  const origemTxt     = (body.dataset.origem        || '').trim();
  const termoBusca    = destEndereco || destName || destQuery || query;

  const paraNum = (v) => {
    const n = parseFloat(v);
    return Number.isFinite(n) ? n : null;
  };
  // Mesma faixa de sanidade do backend: coordenada fora do Brasil é descarte.
  const parValido = (lat, lng) =>
    lat !== null && lng !== null && lat >= -35 && lat <= 6 && lng >= -75 && lng <= -30;

  const destLat = paraNum(body.dataset.destLat);
  const destLng = paraNum(body.dataset.destLng);
  const origLat = paraNum(body.dataset.lat);
  const origLng = paraNum(body.dataset.lng);

  let serverUser = null;
  try {
    const el = document.getElementById('php-user-data');
    serverUser = el ? JSON.parse(el.textContent || 'null') : null;
  } catch (_) {}
  let hasNeeds = (serverUser && serverUser.hasNeeds) || body.dataset.hasNeeds === '1';

  const mapLoading = document.getElementById('map-loading');
  function esconderCarregamentoMapa() {
    if (mapLoading && mapLoading.style.display !== 'none') mapLoading.style.display = 'none';
  }

  // Timeout de seguranca: remove o loading em 10s mesmo que a API falhe
  setTimeout(esconderCarregamentoMapa, 10000);

  // Exibe termo de busca no header
  if (termoBusca) {
    const display = document.getElementById('search-term-display');
    if (display) {
      display.textContent = 'Buscando por: "' + termoBusca + '"';
      display.style.display = 'block';
    }
  }

  // Endereço de partida confirmado em explorar.php
  if (origemTxt) {
    const origemDisplay = document.getElementById('origin-display');
    if (origemDisplay) {
      origemDisplay.textContent = 'Partindo de: ' + origemTxt;
      origemDisplay.style.display = 'block';
    }
  }

  // =========================================================================
  // 1. RESOLVE O CENTRO DA BUSCA (destino > posicao confirmada > geocodificar
  //    o texto > nada) e carrega a lista a partir dele
  // =========================================================================
  let allPlaces  = [];
  let sortMode   = 'distance'; // 'distance' | 'compat'
  let raioAtual = paraNum(body.dataset.raio) || 10;
  let centroAtual = null;
  let semResultado = null;
  let avisoRegiao = '';
  let falhaConsulta = false;
  let osmPontos = [];
  let osmEstado = 'carregando'; // 'carregando' | 'ok' | 'vazio' | 'falha' | 'sem-centro'
  let osmPedido = 0;

  function distanciaAte(lat, lng) {
    if (!centroAtual) return null;
    if (!Number.isFinite(centroAtual.lat) || !Number.isFinite(centroAtual.lng) ||
        !Number.isFinite(lat) || !Number.isFinite(lng)) return null;
    const R = 6371;
    const rad = Math.PI / 180;
    const dLat = (lat - centroAtual.lat) * rad;
    const dLng = (lng - centroAtual.lng) * rad;
    const h = Math.sin(dLat / 2) ** 2 +
      Math.cos(centroAtual.lat * rad) * Math.cos(lat * rad) * Math.sin(dLng / 2) ** 2;
    return 2 * R * Math.asin(Math.sqrt(h));
  }

  /**
   * A base verificada pela equipe só tem cadastros em alguns municípios. Para o
   * resto do estado entra a camada colaborativa do OpenStreetMap pelo MESMO
   * centro da lista, exibida em bloco próprio e marcada como não verificada.
   */
  async function carregarOsm() {
    const meuPedido = ++osmPedido;
    if (!centroAtual) {
      osmPontos = [];
      osmEstado = 'sem-centro';
      publicarOsm();
      return;
    }
    osmEstado = 'carregando';
    const p = new URLSearchParams({
      lat: String(centroAtual.lat),
      lng: String(centroAtual.lng),
      raio_km: String(Math.min(raioAtual, 10)),
      limite: '40'
    });
    let pontos = [];
    let chamadaFalhou = false;
    try {
      const res  = await fetch('api/osm_pontos.php?' + p.toString(), { credentials: 'same-origin' });
      const json = await res.json();
      if (json && json.success && Array.isArray(json.pontos)) {
        pontos = json.pontos
          .map(pt => Object.assign({}, pt, { distancia_km: distanciaAte(pt.lat, pt.lng) }))
          .filter(pt => Number.isFinite(pt.lat) && Number.isFinite(pt.lng))
          .sort((a, b) => (a.distancia_km ?? 1e9) - (b.distancia_km ?? 1e9));
      } else {
        chamadaFalhou = true;
        console.info('[LugaresEncontrados] Camada OpenStreetMap indisponível: ' +
          ((json && json.motivo) || 'resposta inesperada'));
      }
    } catch (e) {
      chamadaFalhou = true;
      console.warn('[LugaresEncontrados] Falha na camada OpenStreetMap:', e);
    }
    // Uma resposta antiga nunca pode desenhar por cima da região atual.
    if (meuPedido !== osmPedido) return;
    osmPontos = pontos;
    osmEstado = pontos.length ? 'ok' : (chamadaFalhou ? 'falha' : 'vazio');
    publicarOsm();
    renderCards(getFilteredPlaces());
  }

  /** Publica os pontos abertos para o mapa inline desenhar os mesmos marcadores. */
  function publicarOsm() {
    document.dispatchEvent(new CustomEvent('pl:osm', {
      detail: { pontos: osmPontos, estado: osmEstado, centro: centroAtual, raio_km: raioAtual }
    }));
  }

  async function geocodificar(texto) {
    const t = (texto || '').trim();
    if (t.length < 3) return null;
    try {
      const p = new URLSearchParams({ action: 'search', q: t, limit: '1' });
      // A posicao de partida, se existir, só vira preferencia de ordenacao.
      if (parValido(origLat, origLng)) {
        p.set('lat', String(origLat));
        p.set('lng', String(origLng));
      }
      const res  = await fetch('api/geocode.php?' + p.toString(), { credentials: 'same-origin' });
      const json = await res.json();
      const r = json && Array.isArray(json.results) ? json.results[0] : null;
      const lat = r ? paraNum(r.lat) : null;
      const lng = r ? paraNum(r.lng) : null;
      if (!parValido(lat, lng)) {
        avisoRegiao = (json && json.mensagem)
          || ('Não encontramos "' + t + '" no mapa. Informe um endereço mais completo ou escolha o ponto no mapa.');
        return null;
      }
      return {
        lat: lat,
        lng: lng,
        municipio: (r.parts && r.parts.city) || '',
        endereco: r.address || r.name || t
      };
    } catch (e) {
      avisoRegiao = 'Não foi possível consultar a busca de endereços agora. Tente novamente.';
      return null;
    }
  }

  function localizacaoNavegador() {
    return new Promise((resolve) => {
      if (!navigator.geolocation) {
        avisoRegiao = 'Seu navegador não permite compartilhar localização. Informe um endereço para buscar perto de você.';
        resolve(null);
        return;
      }
      navigator.geolocation.getCurrentPosition(
        (pos) => resolve({
          lat: pos.coords.latitude,
          lng: pos.coords.longitude,
          municipio: '',
          endereco: 'sua localização atual',
          fonte: 'gps'
        }),
        () => {
          avisoRegiao = 'Não tivemos permissão para usar sua localização. Informe um endereço para buscar perto de você.';
          resolve(null);
        },
        { enableHighAccuracy: true, timeout: 12000, maximumAge: 60000 }
      );
    });
  }

  async function resolverCentro() {
    if (parValido(destLat, destLng)) {
      return { lat: destLat, lng: destLng, municipio: destMunicipio, endereco: destEndereco, fonte: 'destino' };
    }
    if (parValido(origLat, origLng)) {
      return { lat: origLat, lng: origLng, municipio: destMunicipio, endereco: origemTxt || destEndereco, fonte: 'posicao' };
    }
    if (!termoBusca) return null;
    const geo = await geocodificar(termoBusca);
    return geo ? Object.assign(geo, { fonte: 'busca' }) : null;
  }

  function rotuloCentro(ctx) {
    if (!ctx) return '';
    if (ctx.municipio) return ctx.municipio + ' - SP';
    if (ctx.endereco) return ctx.endereco;
    return Number(ctx.lat).toFixed(4) + ', ' + Number(ctx.lng).toFixed(4);
  }

  function atualizarCabecalhoResultados(total, ctx) {
    const label = document.getElementById('places-count-label');
    if (!label) return;
    const onde = ctx ? ' de ' + rotuloCentro(ctx) : '';
    const limite = ctx ? ' em até ' + String(raioAtual).replace('.', ',') + ' km' + onde
                       : ' na base cadastrada';
    if (total === 0) {
      label.textContent = 'Nenhum local cadastrado' + (ctx ? limite : ' na base');
      return;
    }
    label.textContent = total + (total === 1 ? ' local encontrado' : ' locais encontrados') + limite;
  }

  async function carregarLocais() {
    falhaConsulta = false;
    semResultado = null;

    // Lista, marcadores e camada OSM passam a usar o mesmo centro.
    window.plBuscarCentro.publicar(Object.assign({}, centroAtual || {}, {
      raio_km: raioAtual,
      dest_id: destId,
      // O texto só vira filtro do mapa quando ele é o próprio destino: um nome
      // de categoria deslocaria a vista para o melhor avaliado da tabela.
      q: (!centroAtual || centroAtual.fonte === 'busca') ? termoBusca : ''
    }));

    const apiParams = new URLSearchParams({ action: 'list', raio_km: String(raioAtual) });
    if (centroAtual) {
      apiParams.set('lat', String(centroAtual.lat));
      apiParams.set('lng', String(centroAtual.lng));
      // O texto virou o centro geométrico; filtrar por ele de novo sumiria com
      // os locais próximos que têm outro nome.
    } else if (termoBusca) {
      apiParams.set('q', termoBusca);
    }
    const municipioFiltro = (params.get('municipio') || '').trim();
    if (municipioFiltro && municipioFiltro.toLowerCase() !== 'all') {
      apiParams.set('municipio', municipioFiltro);
    }

    try {
      const res  = await fetch('api/locais.php?' + apiParams.toString(), { credentials: 'same-origin' });
      const json = await res.json();
      if (json && json.success && Array.isArray(json.locais)) {
        allPlaces = json.locais.map(p => Object.assign({}, p, {
          features: JSON.stringify(p.features || [])
        }));
        semResultado = json.sem_resultado || null;
        if (Array.isArray(json.necessidades_usuario)) {
          hasNeeds = json.necessidades_usuario.length > 0;
        }
        atualizarCabecalhoResultados(allPlaces.length, centroAtual);
        console.log('[LugaresEncontrados API] ' + allPlaces.length + ' locais carregados.');
      } else {
        allPlaces = [];
        falhaConsulta = true;
      }
    } catch (e) {
      allPlaces = [];
      falhaConsulta = true;
      console.warn('[LugaresEncontrados] Falha ao consultar api/locais.php:', e);
    }

    if (allPlaces.length && centroAtual) {
      const url = new URL(window.location.href);
      url.searchParams.set('raio_km', String(raioAtual));
      window.history.replaceState({}, '', url.toString());
    }

    // A camada aberta é consultada pelo mesmo centro, sem travar a lista.
    carregarOsm();
  }

  async function aplicarCentro(novo, mensagem) {
    centroAtual = novo;
    avisoRegiao = '';
    await carregarLocais();
    renderCards(getFilteredPlaces());
    if (mensagem) showToast(mensagem);
  }

  centroAtual = await resolverCentro();
  if (!centroAtual && termoBusca && avisoRegiao) {
    showToast(avisoRegiao);
  }
  await carregarLocais();

  // Lista vazia é um resultado real (região sem cadastro) e uma consulta que
  // falhou não pode virar dado fictício: o estado vazio diz "consulta indisponível".

  // =========================================================================
  // 2. RENDERIZA OS CARDS DE LOCAIS
  // =========================================================================
  function compatScore(place) {
    const value = place.compatibilidade_pct;
    if (value === null || value === undefined || value === '') return -1;
    const n = Number(value);
    return Number.isNaN(n) ? -1 : n;
  }

  function compatTitle(place) {
    const criterios = Array.isArray(place.compatibilidade_criterios) ? place.compatibilidade_criterios : [];
    const statusLabel = {
      disponivel: 'disponível',
      parcial: 'parcial',
      indisponivel: 'indisponível',
      nao_confirmado: 'não confirmado',
      nao_informado: 'não informado'
    };
    return criterios.map(c => c.label + ': ' + (statusLabel[c.status] || c.status)).join('; ');
  }

  function sortPlaces(places) {
    const arr = places.slice();
    const hasDistance = arr.some(p =>
      p.distancia_km !== undefined && p.distancia_km !== null && !Number.isNaN(Number(p.distancia_km))
    );

    if (sortMode === 'compat' && hasNeeds) {
      arr.sort((a, b) => {
        const diff = compatScore(b) - compatScore(a);
        if (diff !== 0) return diff;
        if (hasDistance) {
          const da = Number(a.distancia_km), db = Number(b.distancia_km);
          if (da !== db) return da - db;
        }
        return Number(b.rating || 0) - Number(a.rating || 0);
      });
    } else if (hasDistance) {
      arr.sort((a, b) => {
        const diff = Number(a.distancia_km) - Number(b.distancia_km);
        if (diff !== 0) return diff;
        return Number(b.rating || 0) - Number(a.rating || 0);
      });
    }
    return arr;
  }

  const EMPTY_BT = 'display:inline-block;margin-top:10px;margin-right:8px;padding:10px 18px;background:#c2693f;color:#fff;border:1px solid #c2693f;border-radius:10px;font-size:13px;font-weight:600;text-decoration:none;cursor:pointer;font-family:inherit;';
  const EMPTY_BT2 = 'display:inline-block;margin-top:10px;margin-right:8px;padding:10px 18px;background:#fff;color:#44403c;border:1px solid #e7ded2;border-radius:10px;font-size:13px;font-weight:600;text-decoration:none;cursor:pointer;font-family:inherit;';

  function _vazioHtml() {
    // Filtro de categoria sem correspondência é outra situação: há locais, mas
    // nenhum bate com o chip escolhido.
    if (allPlaces.length > 0) {
      return '<div style="text-align:center;padding:48px 20px;color:#78716c;">' +
        '<div style="font-size:40px;margin-bottom:12px;">&#x1F6AB;</div>' +
        '<h3 style="margin:0 0 8px;font-size:16px;color:#44403c;">Nenhum local com esse filtro</h3>' +
        '<p style="font-size:13px;margin:0;">Há ' + allPlaces.length +
          ' local(is) na região, mas nenhum corresponde ao filtro selecionado.</p>' +
        '<button type="button" style="' + EMPTY_BT + '" data-acao="limpar-filtros">Remover filtro</button>' +
      '</div>';
    }

    const motivo = semResultado && semResultado.motivo
      ? semResultado.motivo
      : (falhaConsulta ? 'consulta_indisponivel' : (centroAtual ? 'nenhum_local_no_raio' : 'sem_centro_geografico'));

    let titulo = 'Nenhum local encontrado';
    let texto  = '';

    if (motivo === 'sem_centro_geografico') {
      titulo = 'Não sabemos onde buscar';
      texto = avisoRegiao
        ? _esc(avisoRegiao)
        : 'Informe um endereço ou permita o uso da sua localização para buscar lugares acessíveis perto de você.';
    } else if (motivo === 'consulta_indisponivel') {
      titulo = 'Não foi possível carregar os locais';
      texto = 'A consulta aos locais cadastrados falhou agora. Tente novamente em instantes.';
    } else {
      titulo = 'Nenhum local cadastrado nessa região';
      const perto = semResultado && semResultado.mais_proximo;
      texto = perto
        ? 'O local cadastrado mais próximo de ' + _esc(rotuloCentro(centroAtual)) + ' é <strong>' +
          _esc(perto.name) + '</strong>' + (perto.municipio ? ', em ' + _esc(perto.municipio) : '') +
          ', a ' + String(perto.distancia_km).replace('.', ',') + ' km.'
        : 'Ainda não há local com dados de acessibilidade registrados onde você pesquisou.';
    }

    let botoes = '';
    const sugestoes = semResultado && Array.isArray(semResultado.sugestoes_raio) ? semResultado.sugestoes_raio : [];
    sugestoes.forEach((r) => {
      if (Number(r) > raioAtual) {
        botoes += '<button type="button" style="' + EMPTY_BT + '" data-acao="ampliar-raio" data-raio="' + Number(r) + '">' +
          'Buscar em até ' + String(Number(r)).replace('.', ',') + ' km</button>';
      }
    });
    if (!centroAtual) {
      botoes += '<button type="button" style="' + EMPTY_BT + '" data-acao="usar-gps">Usar minha localização</button>';
    }
    botoes += '<a href="explorar.php" style="' + EMPTY_BT2 + '">Buscar em outra região</a>';

    let cobertura = '';
    const municipios = semResultado && Array.isArray(semResultado.municipios) ? semResultado.municipios : [];
    const verificados = semResultado && Array.isArray(semResultado.municipios_verificados) ? semResultado.municipios_verificados : [];
    if (municipios.length || verificados.length) {
      // Duas coberturas distintas: a equipe só pode ser citada onde houve vistoria.
      const _lista = (v) => {
        const nomes = v.slice(0, 10).map(_esc).join(', ');
        return v.length > 10 ? nomes + ' e mais ' + (v.length - 10) : nomes;
      };
      const pendentes = municipios.filter((m) => verificados.indexOf(m) < 0);
      cobertura = '<p style="font-size:12px;color:#a8a29e;margin:14px 0 0;">' +
        (verificados.length
          ? 'Locais verificados pela equipe hoje disponíveis em: ' + _lista(verificados) + '. '
          : 'A equipe ainda não concluiu a vistoria de nenhum local. ') +
        (pendentes.length
          ? 'Há endereços cadastrados aguardando vistoria em: ' + _lista(pendentes) + '. '
          : '') +
        _esc(textoCoberturaAberta()) + '</p>';
    }

    return '<div style="text-align:center;padding:48px 20px;color:#78716c;">' +
      '<div style="font-size:40px;margin-bottom:12px;">&#x1F50D;</div>' +
      '<h3 style="margin:0 0 8px;font-size:16px;color:#44403c;">' + _esc(titulo) + '</h3>' +
      '<p style="font-size:13px;margin:0;">' + texto + '</p>' +
      '<div>' + botoes + '</div>' + cobertura +
    '</div>';
  }

  /**
   * O que a camada aberta consegue dizer hoje, sem prometer cobertura que ainda
   * não foi testada.
   */
  function textoCoberturaAberta() {
    if (!centroAtual) return 'Para buscar perto de você, informe um endereço ou use sua localização.';
    if (osmEstado === 'ok') return 'Os pontos do OpenStreetMap desta região aparecem abaixo, ainda sem verificação da equipe.';
    if (osmEstado === 'carregando') return 'Consultando também as informações abertas do OpenStreetMap desta região.';
    if (osmEstado === 'falha') return 'As informações abertas do OpenStreetMap não responderam agora.';
    return 'O OpenStreetMap também não registrou pontos de acessibilidade nesta área.';
  }

  const OSM_ESTILO_BT = 'display:inline-block;margin-top:4px;padding:8px 14px;background:#fff;color:#44403c;border:1px solid #d6cdbf;border-radius:9px;font-size:12px;font-weight:600;cursor:pointer;font-family:inherit;';

  /** Bloco da camada colaborativa: nunca se passa por local verificado. */
  function osmSectionHtml() {
    // Com um filtro de categoria ativo a lista abaixo não teria como acompanhar
    // o filtro, então o bloco aberto fica de fora em vez de misturar critérios.
    if (activeFilter !== 'all') return '';
    if (!centroAtual || !osmPontos.length) return '';
    const max = 20;
    const lista = osmPontos.slice(0, max);

    const itens = lista.map((pt) => {
      const d = Number.isFinite(pt.distancia_km)
        ? '<span class="meta-dot">&bull;</span><span>' + pt.distancia_km.toFixed(1).replace('.', ',') + ' km</span>'
        : '';
      return '<article class="place-card" data-osm-id="' + _esc(pt.id) + '" style="border-style:dashed;">' +
        '<div class="place-category-pill"><span>' + _esc(pt.tipo || 'Ponto de acessibilidade') + '</span></div>' +
        '<h2 class="place-card-title">' + _esc(pt.nome || 'Ponto sem nome') + '</h2>' +
        '<div class="place-card-meta">' +
          '<span style="color:#78716c;">' + _esc(pt.tags && pt.tags.amenity ? pt.tags.amenity : 'OpenStreetMap') + '</span>' + d +
        '</div>' +
        '<div class="place-badges-row">' +
          '<span class="badge-compatibility">Dado aberto &mdash; não verificado</span>' +
        '</div>' +
        '<div class="place-actions-row">' +
          '<button type="button" style="' + OSM_ESTILO_BT + '" data-acao="como-chegar-osm" ' +
            'data-lat="' + _esc(String(pt.lat)) + '" data-lng="' + _esc(String(pt.lng)) + '" ' +
            'data-nome="' + _esc(pt.nome || 'Ponto do OpenStreetMap') + '"><span>Como chegar</span></button>' +
        '</div>' +
      '</article>';
    }).join('');

    const mais = osmPontos.length > max
      ? '<p style="font-size:12px;color:#a8a29e;margin:10px 0 0;">Mostrando ' + max + ' de ' +
        osmPontos.length + ' pontos abertos encontrados em até ' +
        String(Math.min(raioAtual, 10)).replace('.', ',') + ' km.</p>'
      : '';

    return '<section style="margin-top:28px;border-top:1px dashed #d6cdbf;padding-top:20px;">' +
      '<h3 style="margin:0 0 4px;font-size:15px;color:#44403c;">Pontos do OpenStreetMap nesta região</h3>' +
      '<p style="font-size:12.5px;color:#78716c;margin:0 0 14px;">' +
        'Informação colaborativa do mapa aberto, consultada para o mesmo centro da busca acima. ' +
        'Acessibilidade não confirmada por vistoria da equipe.' +
      '</p>' +
      itens + mais +
    '</section>';
  }

  function renderCards(places) {
    const container  = document.getElementById('places-results-list');
    const countLabel = document.getElementById('places-count-label');

    if (!container) return;

    if (!places.length) {
      container.innerHTML = _vazioHtml() + osmSectionHtml();
      if (countLabel) {
        if (allPlaces.length) {
          countLabel.textContent = '0 locais com este filtro' +
            (centroAtual ? ' em ' + rotuloCentro(centroAtual) : ' na base cadastrada');
        } else atualizarCabecalhoResultados(0, centroAtual);
      }
      return;
    }

    const sorted = sortPlaces(places);

    container.innerHTML = sorted.map((p, i) => {
      const rating  = p.rating ? parseFloat(p.rating).toFixed(1) : '-';
      const reviews = parseInt(p.reviews_count || 0, 10);
      const isFull  = p.access_status === 'verified' || p.access_status === 'full';
      const badge   = isFull ? 'badge-accessibility-high' : 'badge-compatibility';
      const dot     = isFull ? 'dot-accessible' : '';
      const pill    = (p.category_key === 'parque' || /parque/i.test(p.category || '')) ? ' park' : '';
      const pct     = compatScore(p);
      const known   = parseInt(p.compatibilidade_dados || 0, 10);
      const isTop   = sortMode === 'compat' && hasNeeds && i === 0 && pct > 0;
      const dist    = (p.distancia_km !== undefined && p.distancia_km !== null && !Number.isNaN(Number(p.distancia_km)))
        ? '<span class="meta-dot">&bull;</span><span>' + Number(p.distancia_km).toFixed(1).replace('.', ',') + ' km</span>'
        : '';

      let compatBadge = '';
      if (pct >= 0) {
        const title = _esc(compatTitle(p));
        compatBadge = known > 0
          ? '<span class="badge-compatibility" title="' + title + '"><svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>' + pct + '% compatível com você</span>'
          : '<span class="badge-compatibility" title="' + title + '">Sem dados de acessibilidade</span>';
      }

      return (
        '<article class="place-card' + (isTop ? ' top-match-card' : '') + '" data-id="' + _esc(p.local_code || '') + '" data-local-id="' + _esc(String(p.id || '')) + '" data-category="' + _esc(p.category_key || '') + '">' +
          (isTop ? '<div class="top-match-badge">TOP MATCH</div>' : '') +
          '<div class="place-category-pill' + pill + '"><span>' + _esc(p.category || 'Local') + '</span></div>' +
          '<h2 class="place-card-title"><a href="detalhes-local.php?id=' + encodeURIComponent(p.id || '') + '" style="color:inherit;text-decoration:none;">' + _esc(p.name) + '</a></h2>' +
          '<div class="place-card-meta">' +
            '<span class="place-card-rating"><span class="star-icon">&#9733;</span> ' + rating + ' <span style="font-weight:500;color:#78716c;">(' + reviews + ' avaliações)</span></span>' +
            dist +
            '<span class="meta-dot">&bull;</span><span>' + _esc(p.address || '') + '</span>' +
          '</div>' +
          '<div class="place-badges-row">' +
            '<span class="' + badge + '"><span class="dot-indicator ' + dot + '" style="width:6px;height:6px;' + (isFull ? '' : 'background:#f59e0b;') + '" aria-hidden="true"></span>' + _esc(p.access_text || p.access_status || '') + '</span>' +
            compatBadge +
          '</div>' +
          '<div class="place-actions-row">' +
            '<button type="button" class="btn-card-details" data-place-id="' + _esc(String(p.id || '')) + '"><img src="assets/icons/clipboard-check.svg" alt=""><span>Ver detalhes</span></button>' +
            '<button type="button" class="btn-card-directions" data-place-id="' + _esc(String(p.id || '')) + '"><img src="assets/icons/compass.svg" alt=""><span>Como chegar</span></button>' +
          '</div>' +
        '</article>'
      );
    }).join('') + osmSectionHtml();

    // O cabeçalho guarda a região da busca: sem ele a lista de outra cidade
    // ficaria sem dizer de onde são os resultados, exatamente o que o mapa evita.
    if (countLabel) atualizarCabecalhoResultados(sorted.length, centroAtual);
  }

  // Ações dos cards (delegação: os cards são recriados a cada filtro)
  const resultsList = document.getElementById('places-results-list');
  if (resultsList) {
    resultsList.addEventListener('click', async (e) => {
      const acao = e.target.closest('[data-acao]');
      if (acao) {
        const tipo = acao.getAttribute('data-acao');
        if (tipo === 'ampliar-raio') {
          const novo = parseFloat(acao.getAttribute('data-raio'));
          if (Number.isFinite(novo) && novo > raioAtual) {
            raioAtual = Math.min(novo, 60);
            await carregarLocais();
            renderCards(getFilteredPlaces());
            showToast('Busca ampliada para ' + String(raioAtual).replace('.', ',') + ' km.');
          }
        } else if (tipo === 'usar-gps') {
          const gps = await localizacaoNavegador();
          if (gps) await aplicarCentro(gps, 'Buscando lugares acessíveis perto de você.');
          else renderCards(getFilteredPlaces());
        } else if (tipo === 'limpar-filtros') {
          filterChips.forEach(c => { c.classList.remove('active'); c.setAttribute('aria-pressed', 'false'); });
          document.querySelector('.filter-chip[data-filter="all"]')?.classList.add('active');
          document.querySelector('.filter-chip[data-filter="all"]')?.setAttribute('aria-pressed', 'true');
          activeFilter = 'all';
          renderCards(getFilteredPlaces());
        } else if (tipo === 'como-chegar-osm') {
          const lat = parseFloat(acao.getAttribute('data-lat'));
          const lng = parseFloat(acao.getAttribute('data-lng'));
          if (!parValido(lat, lng)) {
            showToast('Este ponto não tem coordenada válida para traçar a rota.');
            return;
          }
          const q = new URLSearchParams({ dest_lat: String(lat), dest_lng: String(lng) });
          const nome = acao.getAttribute('data-nome');
          if (nome) q.set('dest_nome', nome);
          window.location.href = 'configuracao-rota.php?' + q.toString();
        }
        return;
      }
      const details = e.target.closest('.btn-card-details');
      if (details) {
        window.location.href = 'detalhes-local.php?id=' + encodeURIComponent(details.getAttribute('data-place-id') || '');
        return;
      }
      const directions = e.target.closest('.btn-card-directions');
      if (directions) {
        window.location.href = 'configuracao-rota.php?dest_id=' + encodeURIComponent(directions.getAttribute('data-place-id') || '');
        return;
      }
      const card = e.target.closest('.place-card');
      if (card && card.dataset.localId) {
        window.location.href = 'detalhes-local.php?id=' + encodeURIComponent(card.dataset.localId);
      }
    });
  }

  // =========================================================================
  // 3. FILTROS DE CATEGORIA (PILLS)
  // =========================================================================
  const filterChips = document.querySelectorAll('.filter-chip');
  let activeFilter  = 'all';

  // Os chips usam rótulos próprios; traduz para as chaves reais do banco
  const filterAlias = {
    total:     'verified',
    none:      'none',
    food:      'comercio',
    health:    'saude',
    education: 'educacao',
    restroom:  'banheiro'
  };

  function getFilteredPlaces() {
    if (activeFilter === 'all') return allPlaces;
    const key = filterAlias[activeFilter] || activeFilter;
    return allPlaces.filter(p =>
      p.category_key === key ||
      p.access_status === key ||
      (p.category || '').toLowerCase().includes(key) ||
      (p.features || '').toLowerCase().includes(key)
    );
  }

  function updateSortButton() {
    const btn = document.getElementById('btn-sort-filter');
    if (!btn) return;
    const label = btn.querySelector('span');
    btn.classList.toggle('active', sortMode === 'compat');
    btn.setAttribute('aria-pressed', String(sortMode === 'compat'));
    if (label) label.textContent = sortMode === 'compat' ? 'Mais próximos' : 'Mais compatíveis';
    btn.title = sortMode === 'compat'
      ? 'Voltar a ordenar por distância'
      : 'Ordenar por compatibilidade com o seu perfil';
  }

  document.getElementById('btn-sort-filter')?.addEventListener('click', () => {
    if (!hasNeeds) {
      showToast('Cadastre suas necessidades de acessibilidade em Meu Perfil para ordenar por compatibilidade.');
      return;
    }
    sortMode = sortMode === 'compat' ? 'distance' : 'compat';
    updateSortButton();
    renderCards(getFilteredPlaces());
    showToast(sortMode === 'compat'
      ? 'Locais ordenados pela compatibilidade com o seu perfil.'
      : 'Locais ordenados por distância.');
  });

  filterChips.forEach(chip => {
    chip.addEventListener('click', () => {
      filterChips.forEach(c => { c.classList.remove('active'); c.setAttribute('aria-pressed', 'false'); });
      chip.classList.add('active');
      chip.setAttribute('aria-pressed', 'true');
      activeFilter = chip.getAttribute('data-filter') || 'all';
      renderCards(getFilteredPlaces());
      showToast('Filtro: ' + chip.textContent.trim());
    });
  });

  // =========================================================================
  // 4. CONTROLES DE MAPA (ZOOM + CENTRALIZAR)
  // =========================================================================
  // Zoom e recentralização do Leaflet são tratados pelo script inline da página,
  // que escuta window.plBuscarCentro: assim o mapa segue exatamente o centro que
  // a lista está usando. Aqui ficam as ações que dependem da busca em si.
  document.getElementById('btn-map-recenter')?.addEventListener('click', async () => {
    const gps = await localizacaoNavegador();
    if (!gps) {
      showToast(avisoRegiao || 'Não foi possível usar sua localização.');
      renderCards(getFilteredPlaces());
      return;
    }
    await aplicarCentro(gps, 'Buscando lugares acessíveis perto de você.');
  });

  document.getElementById('btn-map-reset')?.addEventListener('click', () => {
    filterChips.forEach(c => { c.classList.remove('active'); c.setAttribute('aria-pressed', 'false'); });
    document.querySelector('.filter-chip[data-filter="all"]')?.classList.add('active');
    activeFilter = 'all';
    sortMode = 'distance';
    updateSortButton();
    renderCards(getFilteredPlaces());
    showToast('Visualizacao redefinida.');
  });

  // =========================================================================
  // 5. MODAL DE DETALHES
  // =========================================================================
  const modal         = document.getElementById('modal-place-details');
  const btnCloseModal = document.getElementById('btn-close-details');

  if (btnCloseModal) btnCloseModal.addEventListener('click', () => modal?.classList.remove('active'));
  modal?.addEventListener('click', e => { if (e.target === modal) modal.classList.remove('active'); });

  document.querySelectorAll('.btn-card-details[data-place-id]').forEach(btn => {
    btn.addEventListener('click', () => {
      const placeId = btn.getAttribute('data-place-id');
      if (placeId) window.location.href = 'detalhes-local.php?id=' + encodeURIComponent(placeId);
    });
  });

  document.querySelectorAll('.btn-card-directions[data-place-id]').forEach(btn => {
    btn.addEventListener('click', () => {
      const placeId = btn.getAttribute('data-place-id');
      if (placeId) window.location.href = 'configuracao-rota.php?dest_id=' + encodeURIComponent(placeId);
    });
  });

  // =========================================================================
  // 6. DROPDOWN DO USUARIO
  // =========================================================================
  const profileBtn  = document.getElementById('user-profile-btn');
  const profileMenu = document.getElementById('user-dropdown-menu');

  if (profileBtn && profileMenu) {
    profileBtn.addEventListener('click', e => {
      e.stopPropagation();
      const isOpen = profileMenu.classList.toggle('show');
      profileBtn.setAttribute('aria-expanded', String(isOpen));
    });
    document.addEventListener('click', () => {
      profileMenu.classList.remove('show');
      profileBtn.setAttribute('aria-expanded', 'false');
    });
  }

  // =========================================================================
  // 7. LOGOUT + TOAST
  // =========================================================================
  document.querySelectorAll('#btn-logout-results, .btn-logout').forEach(btn => {
    btn.addEventListener('click', e => {
      e.preventDefault();
      if (window.PasseLivreAuth) window.PasseLivreAuth.clearUser();
      else localStorage.removeItem('passeLivreUser');
      setTimeout(() => window.location.href = 'index.php', 500);
    });
  });

  function showToast(msg) {
    if (typeof window.showToast === 'function') { window.showToast(msg); return; }
    const t = document.getElementById('app-toast');
    const m = document.getElementById('toast-message');
    if (t && m) { m.textContent = msg; t.classList.add('visible'); setTimeout(() => t.classList.remove('visible'), 3200); }
  }

  function _esc(str) {
    if (!str) return '';
    return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

  if (query) {
    const ql = document.getElementById('search-query-label');
    const rl = document.getElementById('search-results-title');
    if (ql) ql.textContent = '"' + query + '"';
    if (rl) rl.textContent = 'Resultados para "' + query + '"';
  }

  // Busca do cabecalho: uma nova pesquisa substitui a anterior por completo
  // (centro, destino e raio), em vez de manter a região antiga na URL.
  const headerSearch = document.getElementById('global-search-input');
  headerSearch?.addEventListener('keydown', (e) => {
    if (e.key !== 'Enter') return;
    const termo = (headerSearch.value || '').trim();
    if (termo.length < 3) {
      showToast('Digite ao menos 3 caracteres para buscar um endereço ou lugar.');
      return;
    }
    const proxima = new URLSearchParams(window.location.search);
    ['dest_id', 'dest_name', 'dest_lat', 'dest_lng', 'dest_municipio', 'dest_endereco', 'raio_km']
      .forEach((k) => proxima.delete(k));
    proxima.set('q', termo);
    window.location.href = 'lugares-encontrados.php?' + proxima.toString();
  });

  updateSortButton();
  renderCards(getFilteredPlaces());
});