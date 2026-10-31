/**
 * PASSE LIVRE SUZANO — ALERTA DE OBSTÁCULO NA ROTA
 * Mapa demonstrativo interativo com desenho estilizado de vias, quarteirões,
 * trajeto do usuário, trecho bloqueado e desvio acessível.
 * Totalmente interativo: arrastar (pan), zoom (roda/botões), centralizar,
 * alternar visualização do mapa completo e ativar desvio com animação.
 */

document.addEventListener('DOMContentLoaded', () => {
  initDemonstrativeMap();
  initModalActions();
  initPhotoEvidenceModal();
});

/* ==========================================================================
   1. MAPA DEMONSTRATIVO VETORIAL INTERATIVO
   ========================================================================== */

let mapState = {
  container: null,
  worldGroup: null,
  svgElement: null,
  isDragging: false,
  startX: 0,
  startY: 0,
  panX: 0,
  panY: 0,
  scale: 1,
  minScale: 0.5,
  maxScale: 2.5,
  animId: null,
  isDetourActive: false,
  isModalMinimized: false,
  // Coordenadas mundiais do ponto focal (Interseção do Alerta: x=675, y=770)
  focusPoint: { x: 860, y: 650 }
};

function initDemonstrativeMap() {
  const container = document.getElementById('obstacle-map');
  if (!container) return;

  mapState.container = container;

  // Constrói o SVG Vetorial do Mapa com identidade visual fiel ao protótipo
  container.innerHTML = generateDemonstrativeMapSvg();

  mapState.svgElement = container.querySelector('#demonstrative-map-svg');
  mapState.worldGroup = container.querySelector('#map-world');

  // Configuração inicial de Pan & Zoom para enquadramento idêntico ao protótipo
  setupInitialView();

  // Eventos de Arrastar (Mouse e Toque)
  bindPanEvents();

  // Eventos de Zoom (Roda do Mouse)
  bindZoomEvents();

  // Controles Flutuantes do HUD (Zoom In, Zoom Out, Centralizar, Alternar Mapa)
  bindFloatingHudControls();

  // Tooltips interativos nos elementos do mapa
  bindMapTooltips();

  // Redimensionamento responsivo
  window.addEventListener('resize', () => {
    setupInitialView(true);
  });
}

/**
 * Gera o SVG do mapa com:
 * - Fundo pergaminho quente (#ede6db)
 * - Quarteirões com cantos arredondados suaves (#ded6c9)
 * - Praça em tom verde suave (#dce0cf)
 * - Faixas brancas tracejadas no centro das avenidas
 * - Ruas genéricas rotuladas (Via do trajeto, Via principal): o desenho é um
 *   esquema do relato, não o mapa da região pesquisada
 * - Trajeto em linha sobre o leito das vias: Azul (percorrido), Vermelho tracejado (bloqueado), Laranja tracejado (desvio)
 * - Etiqueta de obras e indicador de posição do usuário
 */
function generateDemonstrativeMapSvg() {
  return `
    <svg id="demonstrative-map-svg" class="demonstrative-map-svg" viewBox="0 0 2000 1200" preserveAspectRatio="xMidYMid slice" width="100%" height="100%" xmlns="http://www.w3.org/2000/svg">
      <defs>
        <!-- Filtro de Sombra Suave para as Placas do Mapa -->
        <filter id="badge-shadow" x="-10%" y="-20%" width="125%" height="150%">
          <feDropShadow dx="0" dy="2" stdDeviation="3" flood-color="#78350f" flood-opacity="0.12"/>
        </filter>

        <!-- Filtro de Brilho do Desvio Ativo -->
        <filter id="detour-glow-filter" x="-20%" y="-20%" width="140%" height="140%">
          <feGaussianBlur stdDeviation="5" result="blur"/>
          <feComposite in="SourceGraphic" in2="blur" operator="over"/>
        </filter>

        <!-- Gradiente da Rota de Desvio Ativa -->
        <linearGradient id="detour-active-grad" x1="0%" y1="100%" x2="100%" y2="0%">
          <stop offset="0%" stop-color="#ea580c"/>
          <stop offset="50%" stop-color="#f59e0b"/>
          <stop offset="100%" stop-color="#ea580c"/>
        </linearGradient>

        <!-- Padrão Sutil de Calçada/Textura de Praça -->
        <pattern id="park-leaf-pat" width="24" height="24" patternUnits="userSpaceOnUse">
          <circle cx="6" cy="6" r="1.5" fill="#cad5be" opacity="0.45"/>
          <circle cx="18" cy="18" r="1.8" fill="#cad5be" opacity="0.45"/>
        </pattern>
      </defs>

      <!-- Grupo Global do Mundo que sofre Pan e Zoom -->
      <g id="map-world">
        <!-- Fundo Geral de Solo/Vias (Tons Quentes de Pergaminho) -->
        <rect x="-1000" y="-800" width="4000" height="2800" fill="#ede6db" />

        <!-- ===============================================================
             1. QUARTEIRÕES (BLOCOS ARREDONDADOS CONFORME PROTÓTIPO)
             =============================================================== -->
        <g id="map-blocks" class="map-blocks-group">
          <!-- Coluna 1 (Oeste / Esquerda) -->
          <!-- Bloco Superior Esquerdo (Acima da R. Glicério) -->
          <rect class="city-block" x="40" y="40" width="400" height="270" rx="26" ry="26" fill="#ded6c9" />
          
          <!-- Bloco Médio Esquerdo (Entre R. Glicério e Av. Principal) -->
          <rect class="city-block" x="40" y="390" width="400" height="340" rx="26" ry="26" fill="#ded6c9" />
          
          <!-- Bloco Inferior Esquerdo (Abaixo da Av. Principal) -->
          <rect class="city-block" x="40" y="810" width="400" height="350" rx="26" ry="26" fill="#ded6c9" />

          <!-- Coluna 2 (Centro-Oeste / Em torno da Praça Cidade das Flores) -->
          <!-- Bloco Superior Centro (Acima da R. Glicério) -->
          <rect class="city-block" x="520" y="40" width="430" height="270" rx="26" ry="26" fill="#ded6c9" />
          
          <!-- Bloco Estreito entre R. Glicério e o Desvio da Praça -->
          <rect class="city-block" x="520" y="390" width="430" height="85" rx="20" ry="20" fill="#ded6c9" />

          <!-- Bloco Inferior Centro (Abaixo da Av. Principal) -->
          <rect class="city-block" x="520" y="810" width="430" height="350" rx="26" ry="26" fill="#ded6c9" />

          <!-- Coluna 3 (Centro-Leste / Atrás da Caixa de Alerta) -->
          <!-- Bloco Superior Direita 1 -->
          <rect class="city-block" x="1030" y="40" width="470" height="270" rx="26" ry="26" fill="#ded6c9" />
          
          <!-- Bloco Médio Direita 1 -->
          <rect class="city-block" x="1030" y="390" width="470" height="340" rx="26" ry="26" fill="#ded6c9" />
          
          <!-- Bloco Inferior Direita 1 -->
          <rect class="city-block" x="1030" y="810" width="470" height="350" rx="26" ry="26" fill="#ded6c9" />

          <!-- Coluna 4 (Extremo Leste) -->
          <rect class="city-block" x="1580" y="40" width="380" height="270" rx="26" ry="26" fill="#ded6c9" />
          <rect class="city-block" x="1580" y="390" width="380" height="340" rx="26" ry="26" fill="#ded6c9" />
          <rect class="city-block" x="1580" y="810" width="380" height="350" rx="26" ry="26" fill="#ded6c9" />
        </g>

        <!-- ===============================================================
             2. PRAÇA CIDADE DAS FLORES (ÁREA VERDE ORGÂNICA ARREDONDADA)
             =============================================================== -->
        <g id="map-park-flowers" class="map-park-group" cursor="pointer">
          <!-- Base da Praça em Verde Suave -->
          <rect class="park-polygon" x="520" y="525" width="460" height="205" rx="34" ry="34" fill="#dce0cf" />
          <rect class="park-texture" x="520" y="525" width="460" height="205" rx="34" ry="34" fill="url(#park-leaf-pat)" pointer-events="none" />
          
          <!-- Ícones Sutis de Árvores na Praça -->
          <g class="park-trees" opacity="0.65" pointer-events="none">
            <circle cx="555" cy="565" r="9" fill="#9bb38f" />
            <circle cx="575" cy="585" r="12" fill="#8ba67e" />
            <circle cx="615" cy="555" r="8" fill="#9bb38f" />
            <circle cx="920" cy="565" r="11" fill="#8ba67e" />
            <circle cx="945" cy="590" r="9" fill="#9bb38f" />
            <circle cx="920" cy="670" r="12" fill="#8ba67e" />
            <circle cx="550" cy="675" r="10" fill="#9bb38f" />
          </g>

          <!-- Nome da Praça Posicionado à Esquerda do Desvio Conforme Protótipo -->
          <text class="park-label-text" x="595" y="630" text-anchor="middle" fill="#3f5a3b" font-family="'Plus Jakarta Sans', system-ui, sans-serif" font-size="12.5" font-weight="600" letter-spacing="0.2">
            Praça / contorno do trajeto
          </text>
        </g>

        <!-- ===============================================================
             3. LEITO PAVIMENTADO E FAIXAS DAS RUAS E DESVIO
             =============================================================== -->
        <g id="map-street-markings" pointer-events="none">
          <!-- Corredor Pavimentado do Desvio Acessível através do Contorno -->
          <path d="M 675 770 L 675 520 L 980 520 L 980 770" 
                stroke="#f3eee6" 
                stroke-width="26" 
                stroke-linecap="round" 
                stroke-linejoin="round" 
                fill="none" />
          
          <!-- Faixa Branca Tracejada da Rota de Desvio -->
          <path d="M 675 770 L 675 520 L 980 520 L 980 770" 
                stroke="#ffffff" 
                stroke-width="2.5" 
                stroke-dasharray="10 10" 
                stroke-linecap="round" 
                stroke-linejoin="round" 
                opacity="0.95" 
                fill="none" />

          <!-- Rua General Francisco Glicério (Horizontal Superior - y=350) -->
          <line x1="-200" y1="350" x2="2200" y2="350" stroke="#ffffff" stroke-width="3.5" stroke-dasharray="14 14" opacity="0.95" />

          <!-- Av. Armando Salles de Oliveira (Vertical - x=480) -->
          <line x1="480" y1="-200" x2="480" y2="1400" stroke="#ffffff" stroke-width="3.5" stroke-dasharray="14 14" opacity="0.95" />

          <!-- Avenida Principal do Trajeto (Horizontal Inferior - y=770) -->
          <!-- Faixa leste (visível à direita da caixa de alerta) -->
          <line x1="1020" y1="770" x2="2200" y2="770" stroke="#ffffff" stroke-width="3.5" stroke-dasharray="14 14" opacity="0.95" />
          <!-- Faixa oeste antes do início da linha azul -->
          <line x1="-200" y1="770" x2="160" y2="770" stroke="#ffffff" stroke-width="3.5" stroke-dasharray="14 14" opacity="0.95" />
        </g>

        <!-- ===============================================================
             4. NOMES DAS RUAS (EXATAMENTE COMO NO PROTÓTIPO)
             =============================================================== -->
        <g id="map-street-labels" pointer-events="none">
          <!-- Rua General Francisco Glicério -->
          <text x="120" y="340" fill="#5c544d" font-family="'Plus Jakarta Sans', system-ui, sans-serif" font-size="13" font-weight="600" letter-spacing="0.2">
            Via do trajeto
          </text>

          <!-- Av. Armando Salles de Oliveira (Rotacionado verticalmente) -->
          <g transform="translate(470, 220) rotate(-90)">
            <text x="0" y="0" text-anchor="middle" fill="#5c544d" font-family="'Plus Jakarta Sans', system-ui, sans-serif" font-size="13" font-weight="600" letter-spacing="0.2">
              Via principal
            </text>
          </g>

          <!-- Identificação da Rua Tiradentes no Desvio -->
          <text x="830" y="510" text-anchor="middle" fill="#78350f" font-family="'Plus Jakarta Sans', system-ui, sans-serif" font-size="11" font-weight="700" opacity="0.8">
            Rota alternativa sugerida (sem vistoria)
          </text>
        </g>

        <!-- ===============================================================
             5. TRAJETOS DO USUÁRIO (ESTRITAMENTE ALINHADOS NAS VIAS)
             =============================================================== -->
        <g id="map-routes-group">
          <!-- 1. Trajeto Percorrido com Segurança (Linha Azul Sólida na Av. Principal) -->
          <path id="route-traveled-path" 
                class="route-traveled" 
                d="M 160 770 L 675 770" 
                stroke="#5a8fc7" 
                stroke-width="7.5" 
                stroke-linecap="round" 
                fill="none" 
                cursor="pointer" />

          <!-- 2. Trecho Bloqueado por Obras (Linha Vermelha Tracejada na Av. Principal) -->
          <path id="route-blocked-path" 
                class="route-blocked" 
                d="M 675 770 L 1050 770" 
                stroke="#cf5252" 
                stroke-width="7" 
                stroke-dasharray="10 10" 
                stroke-linecap="round" 
                fill="none" 
                cursor="pointer" />

          <!-- 3. Rota Recomendada de Desvio Acessível (Linha Laranja Contornando a Praça) -->
          <!-- Alinhamento estrito sobre a via acessível e R. Tiradentes -->
          <path id="route-detour-path" 
                class="route-detour" 
                d="M 675 770 L 675 520 L 980 520 L 980 770" 
                stroke="#ea580c" 
                stroke-width="7.5" 
                stroke-dasharray="10 10" 
                stroke-linecap="round" 
                stroke-linejoin="round" 
                fill="none" 
                cursor="pointer" />

          <!-- Rota de Desvio Ativa com Brilho Pulsante (Invisível até clicar em Desviar Agora) -->
          <path id="route-detour-glow-path" 
                class="route-detour-active-glow" 
                d="M 675 770 L 675 520 L 980 520 L 980 770" 
                stroke="url(#detour-active-grad)" 
                stroke-width="9" 
                stroke-linecap="round" 
                stroke-linejoin="round" 
                fill="none" 
                opacity="0" 
                pointer-events="none" />
        </g>

        <!-- ===============================================================
             6. ETIQUETA DE OBRAS E SINALIZAÇÃO NO LOCAL DO BLOQUEIO
             =============================================================== -->
        <g id="map-badge-blocked" class="map-badge-blocked-group" cursor="pointer" filter="url(#badge-shadow)">
          <!-- Fundo pílula vermelho claro -->
          <rect x="695" y="792" width="186" height="28" rx="8" ry="8" fill="#fee2e2" stroke="#fca5a5" stroke-width="1.5" />
          
          <!-- Ícone de Proibição / Obras -->
          <g transform="translate(705, 798)">
            <circle cx="8" cy="8" r="7.5" fill="none" stroke="#991b1b" stroke-width="1.8"/>
            <line x1="2.7" y1="2.7" x2="13.3" y2="13.3" stroke="#991b1b" stroke-width="1.8"/>
          </g>

          <!-- Texto do Alerta -->
          <text id="badge-blocked-text" x="728" y="811" fill="#991b1b" font-family="'Plus Jakarta Sans', system-ui, sans-serif" font-size="11" font-weight="700">
            Barreira relatada
          </text>
        </g>

        <!-- ===============================================================
             7. MARCADOR DE POSIÇÃO ATUAL DO USUÁRIO (INTERSEÇÃO A 250M)
             =============================================================== -->
        <g id="map-user-marker" class="map-user-marker-group" transform="translate(675, 770)" pointer-events="none">
          <!-- Halo de Pulsação de GPS -->
          <circle class="user-pulse-halo" cx="0" cy="0" r="18" fill="none" stroke="#3b82f6" stroke-width="2" opacity="0.6"/>
          <!-- Círculo Central de Posição -->
          <circle cx="0" cy="0" r="8" fill="#2563eb" stroke="#ffffff" stroke-width="2.5" />
          <!-- Seta Direcional em Direção ao Obstáculo -->
          <polygon points="3,-3 9,0 3,3" fill="#ffffff"/>
        </g>
      </g>
    </svg>
  `;
}

/* ==========================================================================
   2. CONTROLE DE PAN E ZOOM (ARRASTAR E ZOOM SUAVE)
   ========================================================================== */

function setupInitialView(isResize = false) {
  if (!mapState.container || !mapState.worldGroup) return;

  const rect = mapState.container.getBoundingClientRect();
  const width = rect.width;
  const height = rect.height;

  // Calcula a escala para enquadrar a cena como no protótipo
  // Largura de referência do protótipo: ~1024px
  const targetScale = Math.max(0.75, Math.min(1.3, width / 1100));

  if (!isResize || mapState.scale === 1) {
    mapState.scale = targetScale;
  }

  // O ponto focal é o ponto de alerta e a Praça Cidade das Flores (x=780, y=640)
  // Alinhamos para que a caixa de alerta fique centralizada e o início da rota azul apareça à esquerda
  const focalX = mapState.focusPoint.x;
  const focalY = mapState.focusPoint.y;

  mapState.panX = (width / 2) - (focalX * mapState.scale);
  mapState.panY = (height / 2) - (focalY * mapState.scale);

  applyTransform();
}

function applyTransform() {
  if (!mapState.worldGroup) return;
  mapState.worldGroup.setAttribute(
    'transform',
    `translate(${mapState.panX}, ${mapState.panY}) scale(${mapState.scale})`
  );
}

function bindPanEvents() {
  const container = mapState.container;
  if (!container) return;

  // Início do Arraste (Mouse)
  container.addEventListener('mousedown', (e) => {
    // Apenas botão principal (esquerdo) e ignora cliques na caixa de diálogo
    if (e.button !== 0 || e.target.closest('.obstacle-alert-modal') || e.target.closest('.map-floating-hud')) return;

    mapState.isDragging = true;
    mapState.startX = e.clientX - mapState.panX;
    mapState.startY = e.clientY - mapState.panY;
    container.classList.add('is-dragging');
  });

  // Movimento do Arraste (Mouse)
  window.addEventListener('mousemove', (e) => {
    if (!mapState.isDragging) return;

    mapState.panX = e.clientX - mapState.startX;
    mapState.panY = e.clientY - mapState.startY;

    applyTransform();
  });

  // Fim do Arraste (Mouse)
  window.addEventListener('mouseup', () => {
    if (mapState.isDragging) {
      mapState.isDragging = false;
      container.classList.remove('is-dragging');
    }
  });

  // Suporte a Toque em Dispositivos Móveis e Touchscreens
  let initialTouchDist = 0;
  let initialTouchScale = 1;

  container.addEventListener('touchstart', (e) => {
    if (e.target.closest('.obstacle-alert-modal') || e.target.closest('.map-floating-hud')) return;

    if (e.touches.length === 1) {
      mapState.isDragging = true;
      mapState.startX = e.touches[0].clientX - mapState.panX;
      mapState.startY = e.touches[0].clientY - mapState.panY;
    } else if (e.touches.length === 2) {
      mapState.isDragging = false;
      initialTouchDist = Math.hypot(
        e.touches[0].clientX - e.touches[1].clientX,
        e.touches[0].clientY - e.touches[1].clientY
      );
      initialTouchScale = mapState.scale;
    }
  }, { passive: true });

  window.addEventListener('touchmove', (e) => {
    if (mapState.isDragging && e.touches.length === 1) {
      mapState.panX = e.touches[0].clientX - mapState.startX;
      mapState.panY = e.touches[0].clientY - mapState.startY;
      applyTransform();
    } else if (e.touches.length === 2 && initialTouchDist > 0) {
      const currentDist = Math.hypot(
        e.touches[0].clientX - e.touches[1].clientX,
        e.touches[0].clientY - e.touches[1].clientY
      );
      const factor = currentDist / initialTouchDist;
      zoomAtPoint(initialTouchScale * factor, (e.touches[0].clientX + e.touches[1].clientX) / 2, (e.touches[0].clientY + e.touches[1].clientY) / 2);
    }
  }, { passive: true });

  window.addEventListener('touchend', () => {
    mapState.isDragging = false;
    initialTouchDist = 0;
  });
}

function bindZoomEvents() {
  const container = mapState.container;
  if (!container) return;

  container.addEventListener('wheel', (e) => {
    // Ignora scroll dentro da caixa de diálogo se ela tiver rolagem interna
    if (e.target.closest('.obstacle-alert-modal')) return;

    e.preventDefault();

    const zoomFactor = e.deltaY < 0 ? 1.12 : 0.89;
    const newScale = Math.max(mapState.minScale, Math.min(mapState.maxScale, mapState.scale * zoomFactor));

    zoomAtPoint(newScale, e.clientX, e.clientY);
  }, { passive: false });
}

function zoomAtPoint(newScale, clientX, clientY) {
  const rect = mapState.container.getBoundingClientRect();
  const mouseX = clientX - rect.left;
  const mouseY = clientY - rect.top;

  // Converte a posição do mouse para coordenadas do mundo SVG
  const worldX = (mouseX - mapState.panX) / mapState.scale;
  const worldY = (mouseY - mapState.panY) / mapState.scale;

  mapState.scale = newScale;
  mapState.panX = mouseX - (worldX * mapState.scale);
  mapState.panY = mouseY - (worldY * mapState.scale);

  applyTransform();
}

function animateViewTo(targetPanX, targetPanY, targetScale, duration = 400) {
  const startPanX = mapState.panX;
  const startPanY = mapState.panY;
  const startScale = mapState.scale;
  const startTime = performance.now();

  if (mapState.animId) cancelAnimationFrame(mapState.animId);

  function step(now) {
    const elapsed = now - startTime;
    const progress = Math.min(1, elapsed / duration);
    // Curva de desaceleração suave (ease-out cubic)
    const ease = 1 - Math.pow(1 - progress, 3);

    mapState.panX = startPanX + (targetPanX - startPanX) * ease;
    mapState.panY = startPanY + (targetPanY - startPanY) * ease;
    mapState.scale = startScale + (targetScale - startScale) * ease;

    applyTransform();

    if (progress < 1) {
      mapState.animId = requestAnimationFrame(step);
    }
  }

  mapState.animId = requestAnimationFrame(step);
}

/* ==========================================================================
   3. CONTROLES FLUTUANTES DO HUD NO MAPA
   ========================================================================== */

function bindFloatingHudControls() {
  const btnZoomIn = document.getElementById('btn-map-zoom-in');
  const btnZoomOut = document.getElementById('btn-map-zoom-out');
  const btnRecenter = document.getElementById('btn-map-recenter');
  const btnToggleModal = document.getElementById('btn-toggle-modal-view');
  const btnMiniExpand = document.getElementById('btn-expand-modal-action');
  const btnMiniDetour = document.getElementById('btn-mini-detour-action');

  if (btnZoomIn) {
    btnZoomIn.addEventListener('click', () => {
      const rect = mapState.container.getBoundingClientRect();
      const newScale = Math.min(mapState.maxScale, mapState.scale * 1.25);
      zoomAtPoint(newScale, rect.left + rect.width / 2, rect.top + rect.height / 2);
    });
  }

  if (btnZoomOut) {
    btnZoomOut.addEventListener('click', () => {
      const rect = mapState.container.getBoundingClientRect();
      const newScale = Math.max(mapState.minScale, mapState.scale * 0.8);
      zoomAtPoint(newScale, rect.left + rect.width / 2, rect.top + rect.height / 2);
    });
  }

  if (btnRecenter) {
    btnRecenter.addEventListener('click', () => {
      recenterMapOnAlert();
      showToast('Mapa recentralizado no ponto de alerta!');
    });
  }

  if (btnToggleModal) {
    btnToggleModal.addEventListener('click', toggleModalVisibility);
  }

  if (btnMiniExpand) {
    btnMiniExpand.addEventListener('click', toggleModalVisibility);
  }

  if (btnMiniDetour) {
    btnMiniDetour.addEventListener('click', () => {
      activateAccessibleDetour();
    });
  }
}

function recenterMapOnAlert() {
  const rect = mapState.container.getBoundingClientRect();
  const targetScale = Math.max(0.75, Math.min(1.3, rect.width / 1100));
  const targetPanX = (rect.width / 2) - (mapState.focusPoint.x * targetScale);
  const targetPanY = (rect.height / 2) - (mapState.focusPoint.y * targetScale);

  animateViewTo(targetPanX, targetPanY, targetScale, 450);
}

function toggleModalVisibility() {
  const modal = document.querySelector('.obstacle-alert-modal');
  const miniBanner = document.getElementById('minimized-alert-banner');
  const lblToggle = document.getElementById('lbl-toggle-modal');

  if (!modal || !miniBanner) return;

  mapState.isModalMinimized = !mapState.isModalMinimized;

  if (mapState.isModalMinimized) {
    modal.classList.add('is-minimized');
    miniBanner.style.display = 'flex';
    if (lblToggle) lblToggle.textContent = 'Ver Detalhes';
    showToast('Visualização do mapa expandida. Arraste ou use o zoom livremente.');
  } else {
    modal.classList.remove('is-minimized');
    miniBanner.style.display = 'none';
    if (lblToggle) lblToggle.textContent = 'Ver Mapa Completo';
  }
}

/* ==========================================================================
   4. TOOLTIPS INTERATIVOS DOS ELEMENTOS DO MAPA
   ========================================================================== */

function bindMapTooltips() {
  const tooltip = document.getElementById('map-feature-tooltip');
  if (!tooltip) return;

  const features = [
    { id: 'route-traveled-path', text: 'Trajeto percorrido pelo usuário (esquema ilustrativo, não é mapa real)' },
    { id: 'route-blocked-path', text: '⛔ Trecho do alerta: barreira informada no relato, sem vistoria' },
    { id: 'route-detour-path', text: 'Desvio sugerido: acessibilidade ainda não verificada em campo' },
    { id: 'map-badge-blocked', text: 'Ocupação da calçada descrita no relato do cidadão' },
    { id: 'map-park-flowers', text: 'Quadra do esquema ilustrativo: sem dados de calçada confirmados' }
  ];

  features.forEach(feat => {
    const el = document.getElementById(feat.id);
    if (!el) return;

    el.addEventListener('mouseenter', (e) => {
      tooltip.textContent = feat.text;
      tooltip.classList.add('visible');
      positionTooltip(e, tooltip);
    });

    el.addEventListener('mousemove', (e) => {
      positionTooltip(e, tooltip);
    });

    el.addEventListener('mouseleave', () => {
      tooltip.classList.remove('visible');
    });

    // Clique interativo na rota
    el.addEventListener('click', () => {
      if (feat.id === 'route-detour-path' && !mapState.isDetourActive) {
        activateAccessibleDetour();
      } else {
        showToast(feat.text);
      }
    });
  });
}

function positionTooltip(e, tooltip) {
  const offset = 14;
  tooltip.style.left = `${e.clientX + offset}px`;
  tooltip.style.top = `${e.clientY + offset}px`;
}

/* ==========================================================================
   5. AÇÕES DO MODAL (DESVIAR AGORA, CONTINUAR MESMO ASSIM, NOTIFICAR)
   ========================================================================== */

function initModalActions() {
  const btnDetour = document.getElementById('btn-detour-action');
  const btnContinueManual = document.getElementById('btn-continue-manual');

  if (btnDetour) {
    btnDetour.addEventListener('click', async () => {
      activateAccessibleDetour();
      try {
        const token = localStorage.getItem('passeLivre_token') || localStorage.getItem('passeLivreToken') || '';
        await fetch('api/ocorrencias.php?action=choose_action', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            ...(token ? { 'X-Session-Token': token } : {})
          },
          body: JSON.stringify({ escolha: 'desviar' })
        });
      } catch (e) {
        console.warn('[alerta-obstaculo] Erro ao registrar desvio no MySQL:', e);
      }
    });
  }

  if (btnContinueManual) {
    btnContinueManual.addEventListener('click', async () => {
      showToast('Atenção: Calçada intransponível sem auxílio manual no trecho de obras. Prossiga com cuidado.');
      try {
        const token = localStorage.getItem('passeLivre_token') || localStorage.getItem('passeLivreToken') || '';
        await fetch('api/ocorrencias.php?action=choose_action', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            ...(token ? { 'X-Session-Token': token } : {})
          },
          body: JSON.stringify({ escolha: 'continuar' })
        });
      } catch (e) {
        console.warn('[alerta-obstaculo] Erro ao registrar escolha continuar no MySQL:', e);
      }
    });
  }
}

/**
 * Ativa o desvio acessível no mapa:
 * - A linha laranja se torna sólida, vibrante e com feixe de luz pulsante
 * - O trecho vermelho é esmaecido
 * - O mapa enquadra suavemente a rota de desvio
 * - O botão confirma com visual verde de sucesso
 */
function activateAccessibleDetour() {
  mapState.isDetourActive = true;

  const detourPath = document.getElementById('route-detour-path');
  const detourGlow = document.getElementById('route-detour-glow-path');
  const blockedPath = document.getElementById('route-blocked-path');
  const badgeText = document.getElementById('badge-blocked-text');
  const btnDetour = document.getElementById('btn-detour-action');
  const btnMiniDetour = document.getElementById('btn-mini-detour-action');

  // Transforma a linha de desvio em traçado sólido contínuo
  if (detourPath) {
    detourPath.setAttribute('stroke', '#ea580c');
    detourPath.setAttribute('stroke-width', '8.5');
    detourPath.removeAttribute('stroke-dasharray');
    detourPath.classList.add('detour-solid-active');
  }

  // Ativa o brilho luminoso
  if (detourGlow) {
    detourGlow.setAttribute('opacity', '0.75');
    detourGlow.classList.add('glow-pulse-active');
  }

  // Esmaece o trecho vermelho bloqueado
  if (blockedPath) {
    blockedPath.setAttribute('opacity', '0.35');
    blockedPath.setAttribute('stroke', '#991b1b');
  }

  // Atualiza etiqueta no mapa
  if (badgeText) {
    badgeText.textContent = 'Obstrução evitada (+3 min)';
  }

  // Atualiza o botão principal para estado confirmado
  if (btnDetour) {
    btnDetour.innerHTML = `
      <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="#ffffff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
        <polyline points="20 6 9 17 4 12"/>
      </svg>
      <span>Desvio ativado com sucesso</span>
    `;
    btnDetour.style.background = '#15803d';
    btnDetour.style.boxShadow = '0 6px 18px rgba(21, 128, 61, 0.4)';
  }

  // Atualiza o botão da barra compacta se estiver recolhido
  if (btnMiniDetour) {
    btnMiniDetour.textContent = '✓ Desvio Ativo';
    btnMiniDetour.style.background = '#15803d';
  }

  // Animação suave para centralizar o desvio da Praça Cidade das Flores
  const rect = mapState.container.getBoundingClientRect();
  const detourCenterX = 830;
  const detourCenterY = 645;
  const targetScale = Math.max(0.85, Math.min(1.35, rect.width / 1050));
  const targetPanX = (rect.width / 2) - (detourCenterX * targetScale);
  const targetPanY = (rect.height / 2) - (detourCenterY * targetScale);

  animateViewTo(targetPanX, targetPanY, targetScale, 500);

  showToast('Desvio destacado no esquema. A acessibilidade deste caminho ainda não foi verificada em campo.');
}

/* ==========================================================================
   6. MODAL DE FOTOS E EVIDÊNCIAS DE ACESSIBILIDADE
   ========================================================================== */

function initPhotoEvidenceModal() {
  const btnOpenPhotos = document.getElementById('btn-open-photos');
  const modalPhoto = document.getElementById('photo-evidence-modal');
  const btnClosePhotos = document.getElementById('btn-close-photos');

  if (btnOpenPhotos && modalPhoto) {
    btnOpenPhotos.addEventListener('click', () => {
      modalPhoto.classList.add('open');
    });
  }

  if (btnClosePhotos && modalPhoto) {
    btnClosePhotos.addEventListener('click', () => {
      modalPhoto.classList.remove('open');
    });

    modalPhoto.addEventListener('click', (e) => {
      if (e.target === modalPhoto) {
        modalPhoto.classList.remove('open');
      }
    });

    window.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && modalPhoto.classList.contains('open')) {
        modalPhoto.classList.remove('open');
      }
    });
  }
}

/* ==========================================================================
   7. TOAST DE FEEDBACK
   ========================================================================== */

let toastTimeout = null;

function showToast(message) {
  const toast = document.getElementById('toast-obstacle-msg');
  if (!toast) return;

  if (toastTimeout) clearTimeout(toastTimeout);

  toast.textContent = message;
  toast.classList.add('show');

  toastTimeout = setTimeout(() => {
    toast.classList.remove('show');
  }, 3600);
}

/* ==========================================================================
   DISTÂNCIA DO ALERTA E NOTIFICAÇÃO À CENTRAL
   ========================================================================== */

/* A distância só aparece quando a ocorrência tem coordenada e o navegador tem
   posição autorizada; caso contrário permanece "Distância não medida". O rótulo
   vermelho do esquema recebe a categoria informada no relato. */
function aplicarDistanciaAlerta() {
  const badge = document.getElementById('badge-obstacle-distance');
  const out = document.getElementById('obstacle-distance-text');
  const labelSvg = document.getElementById('badge-blocked-text');
  const categoria = (document.querySelector('.report-tag-item span')?.textContent || '').trim();
  if (labelSvg && categoria !== '' && !categoria.startsWith('Categoria não informada')) {
    labelSvg.textContent = categoria;
  }
  if (!badge || !out) return;

  const lat = parseFloat(badge.getAttribute('data-lat') || '');
  const lng = parseFloat(badge.getAttribute('data-lng') || '');
  const gpsLat = parseFloat(sessionStorage.getItem('passeLivre_gps_lat') || '');
  const gpsLng = parseFloat(sessionStorage.getItem('passeLivre_gps_lng') || '');
  if (![lat, lng, gpsLat, gpsLng].every(Number.isFinite)) return;

  const rad = Math.PI / 180;
  const dLat = (lat - gpsLat) * rad;
  const dLng = (lng - gpsLng) * rad;
  const a = Math.sin(dLat / 2) ** 2
    + Math.cos(gpsLat * rad) * Math.cos(lat * rad) * Math.sin(dLng / 2) ** 2;
  const km = 6371 * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));

  out.textContent = km < 1
    ? `A ${Math.round(km * 1000)} m de você`
    : `A ${km.toFixed(1).replace('.', ',')} km de você`;
}

/* O registro vai para api/solicitacoes.php com os dados da própria ocorrência:
   título, endereço e coordenada impressos na tela. Nada de "Calçada Bloqueada
   por Obras — Suzano" fixo, que criaria um caso de outra cidade. */
function initNotificacaoCentral() {
  const btnNotify = document.getElementById('btn-notify-central');
  if (!btnNotify) return;

  const heading = document.getElementById('modal-obstacle-title');
  const tituloTela = (heading?.textContent || '').trim();
  if (tituloTela.startsWith('Sem ocorrência')) {
    btnNotify.disabled = true;
    btnNotify.title = 'Nenhum relato aberto para notificar';
    return;
  }

  const localEl = document.querySelector('.report-cause-text');
  const enderecoTela = (localEl?.textContent || '')
    .replace(/^\s*Local:\s*/i, '').trim();
  const badge = document.getElementById('badge-obstacle-distance');

  btnNotify.addEventListener('click', async () => {
    const user = window.PasseLivreAuth?.getUser();
    const payload = {
      citizen_name:  user?.name  || 'Usuário Anônimo',
      citizen_email: user?.email || '',
      type_key:      'barreiras',
      type_name:     'Obstáculo Viário',
      title:         `Refuerzo do alerta: ${tituloTela}`,
      address:       enderecoTela !== '' && !enderecoTela.startsWith('endereço não informado')
        ? enderecoTela : '',
      latitude:  parseFloat(badge?.getAttribute('data-lat') || '') || null,
      longitude: parseFloat(badge?.getAttribute('data-lng') || '') || null,
      description: 'Alerta reforçado pelo cidadão na tela de obstáculo. Acessibilidade do desvio não verificada.',
      status:      'pending',
      priority:    'urgente'
    };

    try {
      const res = await fetch('api/solicitacoes.php?action=create', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify(payload)
      });
      const data = await res.json().catch(() => ({}));
      if (!res.ok || !data.success) throw new Error(data.error || 'Falha no registro');

      if (window.PLDB) {
        try { window.PLDB.solicitacoes.create(payload); } catch (_) { /* espelho local */ }
      }
      if (window.PLDBAuditLog) {
        await window.PLDBAuditLog('rotas', 'Obstáculo Notificado à Central',
          `Usuário reforçou o alerta "${tituloTela}"`, 'medio').catch(() => {});
      }
      showToast(data.protocol
        ? `Reforço registrado com o protocolo ${data.protocol}.`
        : 'Reforço registrado nas solicitações.');
    } catch (err) {
      showToast(err.message || 'Não foi possível registrar o reforço agora.');
    }
  });
}

document.addEventListener('DOMContentLoaded', async () => {
  aplicarDistanciaAlerta();
  initNotificacaoCentral();
});
