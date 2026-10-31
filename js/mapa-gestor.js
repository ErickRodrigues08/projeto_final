/**
 * PASSE LIVRE — DASHBOARD GEORREFERENCIADO (MAPA E ACESSIBILIDADE GESTOR)
 * Lógica interativa de visualização SIG, camadas de acessibilidade e detalhamento.
 */

document.addEventListener('DOMContentLoaded', () => {
  initViewModes();
  initMapControls();
  initLayerToggles();
  initStatusViarioFilters();
  initTerminalCard();
  initGisExportAndReport();
  initMapSearch();
  initMarkerInteractions();
  sincronizarLocaisDoBanco();
});

/* ==========================================================================
   0. FONTE PRIMÁRIA DOS PONTOS E DO CARD: api/locais.php (tabela locais)
   ========================================================================== */
const CORRESPONDENCIA_MARCADOR = {
  'terminal-ayrton-senna': /terminal|ayrton/i,
  'estacao-suzano':        /cptm|esta(c|ç)ao|estacao/i,
  'barreira-glicerio':     /glicerio|glícério/i,
  'praca-joao-pessoa':     /pra(c|ç)a|joao|joão/i,
  'cruzamento-prudente':   /prudente|cruzamento/i
};

/* Centro pedido na URL (?lat=&lng=&raio_km=&municipio=); sem ele o SIG continua
   na vista de Suzano, onde os marcadores impressos no HTML foram desenhados. */
function centroDoSig() {
  const p = new URLSearchParams(window.location.search);
  const lat = parseFloat(p.get('lat'));
  const lng = parseFloat(p.get('lng'));
  const raio = parseFloat(p.get('raio_km'));
  return {
    lat: Number.isFinite(lat) ? lat : -23.5350,
    lng: Number.isFinite(lng) ? lng : -46.3100,
    raio: Number.isFinite(raio) ? raio : 50,
    municipio: (p.get('municipio') || '').trim()
  };
}

async function sincronizarLocaisDoBanco() {
  let locais = [];
  const regiao = centroDoSig();
  try {
    const q = new URLSearchParams({
      action: 'list',
      raio_km: String(regiao.raio),
      lat: String(regiao.lat),
      lng: String(regiao.lng)
    });
    if (regiao.municipio) q.set('municipio', regiao.municipio);
    const resposta = await fetch('api/locais.php?' + q.toString(), {
      headers: { 'Accept': 'application/json' },
      signal: AbortSignal.timeout(8000)
    });
    if (!resposta.ok) return;
    const dados = await resposta.json();
    if (!dados || !dados.success || !Array.isArray(dados.locais)) return;
    locais = dados.locais;
  } catch (erro) {
    return; /* SIG sem acesso ao banco: mantém os rótulos já impressos no mapa */
  }
  if (!locais.length) return;

  const textoDe = (local) => `${local.access_text || 'Não verificado'} • ${local.rating || 0} (${local.reviews_count || 0} avaliações)`;

  document.querySelectorAll('.gis-marker').forEach(marcador => {
    const regex = CORRESPONDENCIA_MARCADOR[marcador.dataset.id];
    if (!regex) return;
    const local = locais.find(l => regex.test(String(l.name || '')));
    if (!local) return;
    marcador.dataset.title = local.name;
    marcador.dataset.statusDesc = textoDe(local);
  });

  const terminalLocal = locais.find(l => /terminal|ayrton/i.test(String(l.name || '')));
  if (terminalLocal) preencherCardTerminal(terminalLocal);
}

function preencherCardTerminal(local) {
  const card = document.getElementById('terminal-detail-card');
  if (!card) return;

  const definir = (seletor, texto) => {
    const alvo = card.querySelector(seletor);
    if (alvo) alvo.textContent = texto;
  };

  const codigo = String(local.local_code || '').replace(/^LOC-?/i, '');
  definir('.terminal-photo-loc-id', `ID #SZ-LOC-${codigo.padStart(4, '0')}`);
  definir('.terminal-category-tag', local.category || 'Terminal de Transporte Coletivo');
  definir('.terminal-card-title', local.name);
  definir('.terminal-card-address span:last-of-type', [local.address, local.bairro].filter(Boolean).join(' - '));
  definir('.rating-badge-amber span:last-of-type', Number(local.rating || 0).toFixed(1).replace('.', ','));
  definir('.rating-reviews-count', `${Number(local.reviews_count || 0)} avaliações da comunidade PcD`);

  const totalRecs = Array.isArray(local.recursos_detalhados) ? local.recursos_detalhados.length : 0;
  if (totalRecs > 0) {
    const homologados = local.recursos_detalhados.filter(r => r.status === 'disponivel').length;
    definir('.terminal-homologados-count', `${homologados} de ${totalRecs} homologados`);
  }

  if (local.last_audit) {
    definir('.audit-tag-green span:last-of-type', `Auditado em ${local.last_audit}`);
  }

  window.terminalDetalhesBanco = local;
}

/* ==========================================================================
   1. MODO DE VISUALIZAÇÃO (VETOR / SATÉLITE / HEATMAP)
   ========================================================================== */
function initViewModes() {
  const modeButtons = document.querySelectorAll('.btn-view-mode');
  const satelliteOverlay = document.getElementById('map-satellite-overlay');
  const heatmapOverlay = document.getElementById('map-heatmap-overlay');

  modeButtons.forEach(btn => {
    btn.addEventListener('click', () => {
      modeButtons.forEach(b => b.classList.remove('active'));
      btn.classList.add('active');

      const mode = btn.dataset.mode;
      if (satelliteOverlay) satelliteOverlay.classList.remove('active');
      if (heatmapOverlay) heatmapOverlay.classList.remove('active');

      if (mode === 'satelite' && satelliteOverlay) {
        satelliteOverlay.classList.add('active');
        showToast('🛰️ Camada Satélite de Alta Resolução ativada');
      } else if (mode === 'heatmap' && heatmapOverlay) {
        heatmapOverlay.classList.add('active');
        showToast('🔥 Mapa de Calor PcD (Concentração de Demandas) ativado');
      } else {
        showToast('🗺️ Camada Vetorial Acessível SIG Suzano ativa');
      }
    });
  });
}

/* ==========================================================================
   2. CONTROLES DE ZOOM, CENTRALIZAÇÃO E ORIENTAÇÃO
   ========================================================================== */
let currentZoom = 1;
const MIN_ZOOM = 0.85;
const MAX_ZOOM = 1.75;
const ZOOM_STEP = 0.2;

function initMapControls() {
  const btnZoomIn = document.getElementById('btn-zoom-in');
  const btnZoomOut = document.getElementById('btn-zoom-out');
  const btnRecenter = document.getElementById('btn-recenter');
  const btnCompass = document.getElementById('btn-compass');
  const mapCanvas = document.getElementById('gis-map-inner-canvas');

  if (btnZoomIn && mapCanvas) {
    btnZoomIn.addEventListener('click', () => {
      if (currentZoom < MAX_ZOOM) {
        currentZoom = Math.min(MAX_ZOOM, currentZoom + ZOOM_STEP);
        applyTransform(mapCanvas);
        showToast(`Zoom: ${Math.round(currentZoom * 100)}%`);
      }
    });
  }

  if (btnZoomOut && mapCanvas) {
    btnZoomOut.addEventListener('click', () => {
      if (currentZoom > MIN_ZOOM) {
        currentZoom = Math.max(MIN_ZOOM, currentZoom - ZOOM_STEP);
        applyTransform(mapCanvas);
        showToast(`Zoom: ${Math.round(currentZoom * 100)}%`);
      }
    });
  }

  if (btnRecenter && mapCanvas) {
    btnRecenter.addEventListener('click', () => {
      currentZoom = 1;
      applyTransform(mapCanvas);
      showToast('📍 Visão recentralizada no Terminal Urbano Ayrton Senna');
      reopenTerminalCard();
    });
  }

  if (btnCompass && mapCanvas) {
    btnCompass.addEventListener('click', () => {
      btnCompass.style.transform = 'scale(0.9) rotate(360deg)';
      setTimeout(() => {
        btnCompass.style.transform = '';
      }, 400);
      showToast('🧭 Orientação Norte redefinida com precisão SIG');
    });
  }
}

function applyTransform(element) {
  element.style.transform = `scale(${currentZoom})`;
}

/* ==========================================================================
   3. CAMADAS ATIVAS (PISO TÁTIL, SEMÁFOROS, RAMPAS)
   ========================================================================== */
function initLayerToggles() {
  const layerChips = document.querySelectorAll('.layer-tag-chip');

  layerChips.forEach(chip => {
    chip.addEventListener('click', (e) => {
      const layerType = chip.dataset.layer;
      const isCurrentlyActive = !chip.classList.contains('inactive');

      if (isCurrentlyActive) {
        chip.classList.add('inactive');
        chip.style.opacity = '0.5';
        chip.style.textDecoration = 'line-through';
        toggleSvgLayer(layerType, false);
        showToast(`Camada "${chip.querySelector('span').textContent}" desativada`);
      } else {
        chip.classList.remove('inactive');
        chip.style.opacity = '1';
        chip.style.textDecoration = 'none';
        toggleSvgLayer(layerType, true);
        showToast(`Camada "${chip.querySelector('span').textContent}" ativada`);
      }
    });
  });

  const btnFilterSliders = document.getElementById('btn-filter-sliders');
  if (btnFilterSliders) {
    btnFilterSliders.addEventListener('click', () => {
      showToast('⚙️ Filtros avançados de densidade viária e auditoria');
    });
  }
}

function toggleSvgLayer(layerType, isVisible) {
  const elements = document.querySelectorAll(`.svg-layer-${layerType}`);
  elements.forEach(el => {
    el.style.display = isVisible ? '' : 'none';
  });
}

/* ==========================================================================
   4. STATUS VIÁRIO (FILTRAR PONTOS POR CONDIÇÃO)
   ========================================================================== */
function initStatusViarioFilters() {
  const statusPills = document.querySelectorAll('.pill-status-item');

  statusPills.forEach(pill => {
    pill.addEventListener('click', () => {
      const status = pill.dataset.status;
      const label = pill.textContent.trim();
      highlightMarkersByStatus(status);
      showToast(`Filtrando ocorrências: ${label}`);
    });
  });
}

function highlightMarkersByStatus(status) {
  const markers = document.querySelectorAll('.gis-marker');
  markers.forEach(marker => {
    if (!status || status === 'all') {
      marker.style.opacity = '1';
      marker.style.filter = '';
    } else if (marker.dataset.status === status) {
      marker.style.opacity = '1';
      marker.style.filter = 'drop-shadow(0 0 10px rgba(217, 119, 6, 0.9))';
    } else {
      marker.style.opacity = '0.3';
      marker.style.filter = '';
    }
  });
}

/* ==========================================================================
   5. CARD FLUTUANTE DO TERMINAL URBANO AYRTON SENNA
   ========================================================================== */
function initTerminalCard() {
  const card = document.getElementById('terminal-detail-card');
  const btnClose = document.getElementById('btn-close-terminal-card');
  const btnReopen = document.getElementById('btn-reopen-card');
  const btnBookmark = document.getElementById('btn-bookmark-terminal');
  const btnShare = document.getElementById('btn-share-terminal');
  const btnFullDetails = document.getElementById('btn-view-terminal-full');

  if (btnClose && card && btnReopen) {
    btnClose.addEventListener('click', () => {
      card.classList.add('hidden');
      btnReopen.classList.add('visible');
      showToast('Card recolhido. Clique no ponto no mapa ou no botão para reabrir.');
    });

    btnReopen.addEventListener('click', () => {
      reopenTerminalCard();
    });
  }

  if (btnBookmark) {
    btnBookmark.addEventListener('click', () => {
      btnBookmark.classList.toggle('bookmarked');
      const isBookmarked = btnBookmark.classList.contains('bookmarked');
      showToast(isBookmarked ? '⭐ Terminal Ayrton Senna adicionado aos destaques municipais' : 'Item removido dos destaques');
    });
  }

  if (btnShare) {
    btnShare.addEventListener('click', () => {
      navigator.clipboard?.writeText(window.location.href);
      showToast('🔗 Coordenadas do Terminal copiadas para a área de transferência');
    });
  }

  if (btnFullDetails) {
    btnFullDetails.addEventListener('click', () => {
      openTerminalFullModal();
    });
  }
}

function reopenTerminalCard() {
  const card = document.getElementById('terminal-detail-card');
  const btnReopen = document.getElementById('btn-reopen-card');
  if (card) card.classList.remove('hidden');
  if (btnReopen) btnReopen.classList.remove('visible');
}

/* Modal com Relatório Completo de Acessibilidade do Terminal */
function openTerminalFullModal() {
  const existingModal = document.getElementById('modal-terminal-details');
  if (existingModal) {
    existingModal.classList.add('open');
    return;
  }

  const local = window.terminalDetalhesBanco || null;
  const recursos = local && Array.isArray(local.recursos_detalhados) ? local.recursos_detalhados : [];

  const rotuloStatus = { disponivel: 'Homologado', parcial: 'Parcial', indisponivel: 'Indisponível' };
  const descricaoRecurso = (r) => {
    if (r.status === 'disponivel') return 'Vistoriado e homologado pela equipe técnica municipal.';
    if (r.status === 'parcial') return 'Funciona parcialmente; apontado na última inspeção.';
    return 'Indisponível na última inspeção registrada no banco de dados.';
  };

  const cartoesRecursos = recursos.length
    ? recursos.map(r => `
            <div style="background: #faf7f2; padding: 12px; border-radius: 10px; border: 1px solid #ede4d8;">
              <div style="font-weight: 700; color: #1c1917; margin-bottom: 4px;">${esc(r.nome_recurso)} — ${rotuloStatus[r.status] || 'A validar'}</div>
              <div style="color: #78716c; font-size: 11px;">${descricaoRecurso(r)}</div>
            </div>`).join('')
    : `
            <div style="background: #faf7f2; padding: 12px; border-radius: 10px; border: 1px solid #ede4d8;">
              <div style="font-weight: 700; color: #1c1917; margin-bottom: 4px;">Recursos sem registro</div>
              <div style="color: #78716c; font-size: 11px;">Nenhum recurso homologado consta no banco de dados para este local.</div>
            </div>`;

  const modalHtml = `
    <div class="modal-overlay open" id="modal-terminal-details" role="dialog" aria-modal="true" aria-labelledby="modal-term-title">
      <div class="modal-box" style="max-width: 600px;">
        <div class="modal-header">
          <div>
            <span style="font-size: 11px; font-weight: 800; color: #b45309; text-transform: uppercase;">Laudo Técnico de Vistoria Municipal</span>
            <h3 id="modal-term-title" style="margin-top: 2px;">${local ? esc(local.name) : 'Terminal Urbano Ayrton Senna'} — Relatório SIG</h3>
          </div>
          <button class="btn-close-modal" id="btn-close-term-modal" aria-label="Fechar">&times;</button>
        </div>
        <div class="modal-content" style="display: flex; flex-direction: column; gap: 16px;">
          <div style="background: #ecfdf5; border: 1px solid #a7f3d0; border-radius: 12px; padding: 14px; display: flex; gap: 12px; align-items: center;">
            <div style="width: 38px; height: 38px; border-radius: 50%; background: #10b981; display: flex; align-items: center; justify-content: center; color: white; font-weight: 800; font-size: 18px;">✓</div>
            <div>
              <div style="font-weight: 800; color: #065f46; font-size: 13px;">Classificação NBR 9050: ${local ? esc(local.access_text || 'Não verificado') : 'Sem classificação no banco (painel de demonstração)'}</div>
              <div style="font-size: 11.5px; color: #047857;">Última vistoria técnica presencial registrada em ${local && local.last_audit ? esc(local.last_audit) : 'data não informada'} pelo Depto. de Obras e Mobilidade.</div>
            </div>
          </div>

          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; font-size: 12px;">${cartoesRecursos}
          </div>

          <div style="border-top: 1px solid #ede4d8; padding-top: 14px; display: flex; justify-content: flex-end; gap: 10px;">
            <button class="btn-gis-outline" id="btn-print-report" style="font-size: 12px;">Imprimir Laudo PDF</button>
            <button class="btn-report-point" id="btn-ok-term-modal" style="font-size: 12px;">Fechar Relatório</button>
          </div>
        </div>
      </div>
    </div>
  `;

  document.body.insertAdjacentHTML('beforeend', modalHtml);

  const modal = document.getElementById('modal-terminal-details');
  const closeBtn = document.getElementById('btn-close-term-modal');
  const okBtn = document.getElementById('btn-ok-term-modal');
  const printBtn = document.getElementById('btn-print-report');

  const closeModal = () => modal.classList.remove('open');
  closeBtn.addEventListener('click', closeModal);
  okBtn.addEventListener('click', closeModal);
  modal.addEventListener('click', (e) => {
    if (e.target === modal) closeModal();
  });
  printBtn.addEventListener('click', () => {
    showToast('Gerando laudo técnico oficial em PDF...');
  });
}

/* ==========================================================================
   6. EXPORTAÇÃO GIS / KML E REPORTAR PONTO NO MAPA
   ========================================================================== */
function initGisExportAndReport() {
  const btnExport = document.getElementById('btn-export-gis');
  const btnReport = document.getElementById('btn-report-point');

  if (btnExport) {
    btnExport.addEventListener('click', async () => {
      showToast('Gerando GeoJSON dos locais cadastrados...');
      try {
        const res = await fetch('api/locais.php?action=export_geojson', { headers: { Accept: 'application/geo+json' } });
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        const geo = await res.json();
        const total = Array.isArray(geo.features) ? geo.features.length : 0;
        if (total === 0) {
          showToast('Nenhum local com coordenadas para exportar.');
          return;
        }
        const blob = new Blob([JSON.stringify(geo)], { type: 'application/geo+json' });
        const url  = URL.createObjectURL(blob);
        const a    = Object.assign(document.createElement('a'), { href: url, download: 'locais_passe_livre.geojson' });
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
        showToast(`GeoJSON exportado: ${total} local(is) com coordenadas.`);
      } catch (err) {
        showToast('A exportação GeoJSON não pôde ser concluída agora.');
      }
    });
  }

  if (btnReport) {
    btnReport.addEventListener('click', () => {
      openReportPointModal();
    });
  }
}

function openReportPointModal() {
  const existing = document.getElementById('modal-report-point');
  if (existing) {
    existing.classList.add('open');
    return;
  }

  const modalHtml = `
    <div class="modal-overlay open" id="modal-report-point" role="dialog" aria-modal="true" aria-labelledby="modal-rep-title">
      <div class="modal-box" style="max-width: 520px;">
        <div class="modal-header">
          <div>
            <span style="font-size: 11px; font-weight: 800; color: #d97706; text-transform: uppercase;">Gestão Territorial</span>
            <h3 id="modal-rep-title" style="margin-top: 2px;">Reportar Ponto no Mapa SIG</h3>
          </div>
          <button class="btn-close-modal" id="btn-close-rep-modal" aria-label="Fechar">&times;</button>
        </div>
        <form id="form-report-point" class="modal-content" style="display: flex; flex-direction: column; gap: 14px;">
          <div>
            <label style="display: block; font-size: 12px; font-weight: 700; color: #44403c; margin-bottom: 6px;">Tipo de Registro</label>
            <select name="rep-tipo" style="width: 100%; padding: 10px 14px; border: 1.5px solid #d6cbbe; border-radius: 10px; font-family: inherit; font-size: 12.5px; background: #fff;" required>
              <option value="piso">🦯 Piso Tátil (Implantação ou Manutenção)</option>
              <option value="semaforo">📢 Semáforo Sonoro para Deficientes Visuais</option>
              <option value="rampa">♿ Adequação de Guia / Rampa NBR 9050</option>
              <option value="barreira">⚠️ Barreira Arquitetônica ou Obstrução Temporária</option>
            </select>
          </div>

          <div>
            <label style="display: block; font-size: 12px; font-weight: 700; color: #44403c; margin-bottom: 6px;">Logradouro / Bairro</label>
            <input type="text" name="rep-logradouro" placeholder="Ex: Rua Baruel, altura do nº 450 - Centro" style="width: 100%; padding: 10px 14px; border: 1.5px solid #d6cbbe; border-radius: 10px; font-family: inherit; font-size: 12.5px;" required>
          </div>

          <div>
            <label style="display: block; font-size: 12px; font-weight: 700; color: #44403c; margin-bottom: 6px;">Nível de Acessibilidade</label>
            <div style="display: flex; gap: 10px;">
              <label style="font-size: 12px; display: flex; align-items: center; gap: 6px; cursor: pointer;">
                <input type="radio" name="rep-status" value="verde" checked> Adaptado
              </label>
              <label style="font-size: 12px; display: flex; align-items: center; gap: 6px; cursor: pointer;">
                <input type="radio" name="rep-status" value="amarelo"> Parcial
              </label>
              <label style="font-size: 12px; display: flex; align-items: center; gap: 6px; cursor: pointer;">
                <input type="radio" name="rep-status" value="vermelho"> Obstruído
              </label>
            </div>
          </div>

          <div>
            <label style="display: block; font-size: 12px; font-weight: 700; color: #44403c; margin-bottom: 6px;">Observações Técnicas</label>
            <textarea name="rep-obs" rows="3" placeholder="Detalhes para a equipe de fiscalização..." style="width: 100%; padding: 10px 14px; border: 1.5px solid #d6cbbe; border-radius: 10px; font-family: inherit; font-size: 12.5px;"></textarea>
          </div>

          <div style="border-top: 1px solid #ede4d8; padding-top: 14px; display: flex; justify-content: flex-end; gap: 10px;">
            <button type="button" class="btn-gis-outline" id="btn-cancel-rep">Cancelar</button>
            <button type="submit" class="btn-report-point">Gravar Ponto SIG</button>
          </div>
        </form>
      </div>
    </div>
  `;

  document.body.insertAdjacentHTML('beforeend', modalHtml);

  const modal = document.getElementById('modal-report-point');
  const closeBtn = document.getElementById('btn-close-rep-modal');
  const cancelBtn = document.getElementById('btn-cancel-rep');
  const form = document.getElementById('form-report-point');

  const closeModal = () => modal.classList.remove('open');
  closeBtn.addEventListener('click', closeModal);
  cancelBtn.addEventListener('click', closeModal);
  modal.addEventListener('click', (e) => {
    if (e.target === modal) closeModal();
  });

  const NOME_TIPO = {
    piso: 'Piso tátil', semaforo: 'Semáforo sonoro',
    rampa: 'Rampa / guia (NBR 9050)', barreira: 'Barreira arquitetônica'
  };
  const GRAVIDADE_STATUS = { verde: 'baixa', amarelo: 'media', vermelho: 'alta' };

  form.addEventListener('submit', async (e) => {
    e.preventDefault();

    const tipo = form.elements['rep-tipo'] ? form.elements['rep-tipo'].value : 'barreira';
    const logradouro = (form.elements['rep-logradouro'] || {}).value || '';
    const status = (form.querySelector('input[name="rep-status"]:checked') || {}).value || 'amarelo';
    const obs = (form.elements['rep-obs'] || {}).value || '';

    const btn = form.querySelector('.btn-report-point');
    if (btn) { btn.disabled = true; btn.textContent = 'Registrando...'; }

    try {
      const resposta = await fetch('api/ocorrencias.php?action=create', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({
          titulo: `Ponto SIG: ${NOME_TIPO[tipo] || tipo}`,
          descricao: obs || 'Registro técnico feito no painel SIG.',
          categoria: NOME_TIPO[tipo] || 'Barreira arquitetônica',
          endereco: logradouro,
          gravidade: GRAVIDADE_STATUS[status] || 'media'
        })
      });
      const dados = await resposta.json().catch(() => null);
      if (resposta.ok && dados && dados.success) {
        closeModal();
        showToast(`Ocorrência ${dados.protocolo} registrada na base de vistoria.`);
      } else {
        showToast((dados && (dados.message || dados.error)) || 'O registro não foi salvo. Tente novamente.');
      }
    } catch (erro) {
      showToast('Sem conexão com o banco: o ponto não foi registrado.');
    } finally {
      if (btn) { btn.disabled = false; btn.textContent = 'Gravar Ponto SIG'; }
    }
  });
}

/* ========================================================================
   7. INTERAÇÃO COM MARCADORES NO MAPA (CLICKS)
   ======================================================================== */
function initMarkerInteractions() {
  const markers = document.querySelectorAll('.gis-marker');

  markers.forEach(marker => {
    marker.addEventListener('click', () => {
      const pointId = marker.dataset.id;
      if (pointId === 'terminal-ayrton-senna') {
        reopenTerminalCard();
        showToast('📍 Terminal Urbano Ayrton Senna selecionado');
      } else {
        const title = marker.dataset.title || 'Ponto no Mapa';
        showToast(`📍 Ponto selecionado: ${title}`);
      }
    });
  });
}

/* ==========================================================================
   8. BUSCA NO MAPA
   ========================================================================== */
function initMapSearch() {
  const searchInput = document.getElementById('input-map-search');
  if (!searchInput) return;

  searchInput.addEventListener('keydown', async (e) => {
    if (e.key !== 'Enter') return;
    const query = searchInput.value.trim();
    if (!query) return;

    // O SIG em tela é um painel fixo de Suzano: a busca consulta o banco e diz
    // quantos cadastros reais existem para o termo, em vez de prometer um
    // destaque no mapa que não acontece.
    try {
      const regiao = centroDoSig();
      const q = new URLSearchParams({
        action: 'list', q, raio_km: String(regiao.raio),
        lat: String(regiao.lat), lng: String(regiao.lng)
      });
      const resposta = await fetch('api/locais.php?' + q.toString(), {
        headers: { 'Accept': 'application/json' },
        signal: AbortSignal.timeout(8000)
      });
      const dados = resposta.ok ? await resposta.json() : null;
      const total = dados && dados.success ? (dados.total ?? (dados.locais || []).length) : 0;
      showToast(total
        ? `${total} cadastro(s) no banco para "${query}".`
        : `Nenhum cadastro no banco para "${query}".`);
    } catch (erro) {
      showToast('Não foi possível consultar o banco agora.');
    }
  });
}

/* ==========================================================================
   9. TOAST NOTICES
   ========================================================================== */
function esc(valor) {
  return String(valor ?? '')
    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}

function showToast(message) {
  const toast = document.getElementById('app-toast');
  const msgSpan = document.getElementById('toast-message');
  if (!toast || !msgSpan) return;

  msgSpan.textContent = message;
  toast.classList.add('show');

  if (window._toastTimeout) clearTimeout(window._toastTimeout);
  window._toastTimeout = setTimeout(() => {
    toast.classList.remove('show');
  }, 3500);
}

/* ==========================================================================
   INTEGRAÇÃO SQL — Mapa do Gestor
   ========================================================================== */
document.addEventListener('DOMContentLoaded', async () => {
  if (!window.PasseLivreDBReady) return;
  try {
    await window.PasseLivreDBReady;
    if (!window.PLDB) return;

    // Carrega locais com incidentes para destaque no mapa
    const locais = window.PLDB.locais.findAll();
    const comIncidentes = locais.filter(l => l.incidents_count > 0);
    console.log(`[mapa-gestor SQL] ${locais.length} locais carregados | ${comIncidentes.length} com incidentes ativos.`);

    // Carrega solicitações pendentes para marcar no mapa
    const pendentes = window.PLDB.solicitacoes.findAll({ status: 'pending' });
    console.log(`[mapa-gestor SQL] ${pendentes.length} solicitações pendentes para exibir no mapa.`);

    // Registra acesso na auditoria
    if (window.PLDBAuditLog) {
      await window.PLDBAuditLog('locais', 'Mapa do Gestor Acessado', `Visualização georreferenciada com ${locais.length} pontos`, 'baixo');
    }

    // Atualiza contador no painel lateral (se existir)
    const elCount = document.getElementById('map-locais-count');
    if (elCount) elCount.textContent = locais.length;
    const elPend = document.getElementById('map-pending-count');
    if (elPend) elPend.textContent = pendentes.length;

  } catch (e) {
    console.warn('[mapa-gestor] SQL:', e);
  }
});
