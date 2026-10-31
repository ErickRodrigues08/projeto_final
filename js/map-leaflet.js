/**
 * Passe Livre — Mapa Interativo Leaflet (OpenStreetMap, gratuito)
 * Versao robusta: aguarda L estar disponivel, invalida size, garante loading removido
 */
(function () {
  'use strict';

  var _map = null;
  var _markersLayer = null;
  var _centerLat = -23.5350;
  var _centerLng = -46.3100;

  var STATUS_COLOR = {
    verified: '#059669', full: '#059669',
    partial: '#f59e0b',
    unverified: '#ef4444', critical: '#ef4444'
  };
  var STATUS_LABEL = {
    verified: 'Alta acessibilidade', full: 'Alta acessibilidade',
    partial: 'Acessibilidade parcial',
    unverified: 'Nao verificado', critical: 'Critico'
  };

  function removeLoading() {
    var el = document.getElementById('map-loading');
    if (el) el.style.display = 'none';
  }

  function showToast(msg) {
    if (typeof window.showToast === 'function') { window.showToast(msg); return; }
    var t = document.getElementById('app-toast');
    var m = document.getElementById('toast-message');
    if (t && m) { m.textContent = msg; t.classList.add('visible'); setTimeout(function(){ t.classList.remove('visible'); }, 3000); }
  }

  function createPinIcon(color, size) {
    size = size || 32;
    var svg = '<svg xmlns="http://www.w3.org/2000/svg" width="' + size + '" height="' + (size + 8) + '" viewBox="0 0 32 40">'
      + '<circle cx="16" cy="16" r="14" fill="' + color + '" stroke="#fff" stroke-width="2.5"/>'
      + '<circle cx="16" cy="16" r="5" fill="#fff"/>'
      + '<line x1="16" y1="30" x2="16" y2="38" stroke="' + color + '" stroke-width="2.5" stroke-linecap="round"/>'
      + '</svg>';
    return L.divIcon({
      html: svg,
      className: '',
      iconSize: [size, size + 8],
      iconAnchor: [size / 2, size + 8],
      popupAnchor: [0, -(size + 8)]
    });
  }

  function createDestIcon() {
    var svg = '<svg xmlns="http://www.w3.org/2000/svg" width="40" height="50" viewBox="0 0 40 50">'
      + '<circle cx="20" cy="20" r="18" fill="#ea580c" stroke="#fff" stroke-width="3"/>'
      + '<path d="M20 10 L22.2 16.8 L29.5 16.8 L23.6 21 L25.9 27.8 L20 23.6 L14.1 27.8 L16.4 21 L10.5 16.8 L17.8 16.8 Z" fill="#fff"/>'
      + '<line x1="20" y1="38" x2="20" y2="47" stroke="#ea580c" stroke-width="3" stroke-linecap="round"/>'
      + '</svg>';
    return L.divIcon({
      html: svg,
      className: '',
      iconSize: [40, 50],
      iconAnchor: [20, 50],
      popupAnchor: [0, -50]
    });
  }

  function createOrigemIcon() {
    var html = '<div style="position:relative;width:36px;height:36px;">'
      + '<svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" viewBox="0 0 36 36">'
      + '<circle cx="18" cy="18" r="15" fill="#2563eb" stroke="#fff" stroke-width="2.5"/>'
      + '<circle cx="18" cy="18" r="5" fill="#fff"/>'
      + '</svg>'
      + '<div style="position:absolute;bottom:-26px;left:50%;transform:translateX(-50%);white-space:nowrap;background:#1e3a8a;color:#fff;font-size:9px;font-weight:700;padding:2px 6px;border-radius:5px;">Voce esta aqui</div>'
      + '</div>';
    return L.divIcon({ html: html, className: '', iconSize: [36, 36], iconAnchor: [18, 18], popupAnchor: [0, -20] });
  }

  function buildPopup(loc, isDestino) {
    var cor = STATUS_COLOR[loc.access_status] || '#78716c';
    var label = STATUS_LABEL[loc.access_status] || '';
    var stars = loc.rating ? '\u2605'.repeat(Math.round(parseFloat(loc.rating))) : '';
    var dist = loc.distancia_km ? '<span style="font-size:11px;color:#78716c;margin-left:4px;">' + parseFloat(loc.distancia_km).toFixed(1) + ' km</span>' : '';
    var destBadge = isDestino ? '<div style="background:#fff7ed;border:1px solid #fed7aa;color:#c2410c;font-size:10px;font-weight:700;padding:3px 8px;border-radius:20px;margin-bottom:8px;display:inline-block;">Destino selecionado</div><br>' : '';
    return '<div style="font-family:sans-serif;min-width:180px;max-width:240px;">'
      + destBadge
      + '<div style="font-size:11px;color:' + cor + ';font-weight:700;margin-bottom:2px;">' + label + '</div>'
      + '<div style="font-size:14px;font-weight:800;color:#1c1917;margin-bottom:4px;line-height:1.3;">' + (loc.name || '') + '</div>'
      + '<div style="font-size:11px;color:#78716c;margin-bottom:6px;">' + (loc.address || '') + '</div>'
      + '<div style="font-size:13px;color:#f59e0b;margin-bottom:8px;">' + stars + dist + '</div>'
      + '<div style="display:flex;gap:6px;">'
      + '<a href="detalhes-local.php?id=' + loc.id + '" style="flex:1;text-align:center;background:#f5f0e8;border:1px solid #e7ded2;color:#44403c;text-decoration:none;padding:6px 8px;border-radius:7px;font-size:11px;font-weight:700;">Detalhes</a>'
      + '<a href="configuracao-rota.php?dest_id=' + loc.id + '" style="flex:1.2;text-align:center;background:#d97706;color:#fff;text-decoration:none;padding:6px 8px;border-radius:7px;font-size:11px;font-weight:700;">Como chegar</a>'
      + '</div></div>';
  }

  function init(containerId, options) {
    options = options || {};
    if (_map) { return _map; }

    // Verificacao defensiva — L deve existir
    if (typeof L === 'undefined') {
      console.error('[PasseLivreMap] Leaflet (L) nao esta disponivel. Verifique se o CDN carregou.');
      removeLoading();
      return null;
    }

    var el = document.getElementById(containerId);
    if (!el) {
      console.error('[PasseLivreMap] Container #' + containerId + ' nao encontrado no DOM.');
      removeLoading();
      return null;
    }

    // Garante que o container tem altura em px (Leaflet exige)
    if (el.offsetHeight < 10) {
      el.style.height = '500px';
    }

    _centerLat = options.lat || -23.5350;
    _centerLng = options.lng || -46.3100;

    try {
      // Limites geograficos do territorio brasileiro
      var BRASIL_BOUNDS = L.latLngBounds(
        L.latLng(-33.75, -73.99),  // sudoeste (RS / Acre)
        L.latLng(5.27,  -28.85)    // nordeste  (RR / costa NE)
      );

      _map = L.map(containerId, {
        center: [_centerLat, _centerLng],
        zoom: options.zoom || 14,
        zoomControl: false,
        scrollWheelZoom: true,
        minZoom: 5,
        maxZoom: 19,
        maxBounds: BRASIL_BOUNDS,
        maxBoundsViscosity: 1.0
      });

      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
        maxZoom: 19
      }).addTo(_map);

      _markersLayer = L.layerGroup().addTo(_map);

      // Botoes zoom da UI
      var btnPlus  = document.getElementById('btn-zoom-plus');
      var btnMinus = document.getElementById('btn-zoom-minus');
      var btnRecenter = document.getElementById('btn-map-recenter');
      var btnReset = document.getElementById('btn-map-reset');
      var btnLayers = document.getElementById('btn-map-layers');

      if (btnPlus)    btnPlus.addEventListener('click', function(){ _map.zoomIn(); });
      if (btnMinus)   btnMinus.addEventListener('click', function(){ _map.zoomOut(); });
      if (btnRecenter) btnRecenter.addEventListener('click', function(){ _map.setView([_centerLat, _centerLng], 14); });
      if (btnReset)   btnReset.addEventListener('click', function(){ _map.setView([_centerLat, _centerLng], 14); });

      var layers = [
        { name: 'Padrao (OpenStreetMap)', url: 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', attr: '&copy; OpenStreetMap contributors' },
        { name: 'Cartografia (Carto)', url: 'https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', attr: '&copy; OpenStreetMap &copy; CARTO' },
        { name: 'Satelite (Esri)', url: 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', attr: 'Tiles &copy; Esri' }
      ];
      var layerIdx = 0;
      var extraLayer = null;
      if (btnLayers) {
        btnLayers.addEventListener('click', function(){
          layerIdx = (layerIdx + 1) % layers.length;
          var tl = layers[layerIdx];
          if (extraLayer) _map.removeLayer(extraLayer);
          extraLayer = L.tileLayer(tl.url, { attribution: tl.attr, maxZoom: 19 }).addTo(_map);
          extraLayer.bringToBack();
          showToast('Camada: ' + tl.name);
        });
      }

      // Forca o mapa a recalcular tamanho apos render
      setTimeout(function(){ _map.invalidateSize(); }, 300);

    } catch (e) {
      console.error('[PasseLivreMap] Erro ao inicializar Leaflet:', e);
      removeLoading();
      return null;
    }

    return _map;
  }

  function loadPlaces(params) {
    params = params || {};
    return new Promise(function(resolve, reject) {
      if (!_map) { removeLoading(); resolve(null); return; }

      var url = new URL('api/map-places.php', window.location.href);
      if (params.q)       url.searchParams.set('q', params.q);
      if (params.lat)     url.searchParams.set('lat', params.lat);
      if (params.lng)     url.searchParams.set('lng', params.lng);
      if (params.dest_id) url.searchParams.set('dest_id', params.dest_id);

      fetch(url.toString())
        .then(function(r){ return r.json(); })
        .then(function(data) {
          if (!data || !data.success) { removeLoading(); resolve(null); return; }

          _markersLayer.clearLayers();

          // Marcador origem
          if (data.origem && data.origem.lat) {
            L.marker([parseFloat(data.origem.lat), parseFloat(data.origem.lng)], {
              icon: createOrigemIcon(),
              zIndexOffset: 1000
            }).addTo(_markersLayer);
          }

          // Marcadores dos locais
          (data.locais || []).forEach(function(loc) {
            if (!loc.lat || !loc.lng) return;
            var cor = STATUS_COLOR[loc.access_status] || '#78716c';
            var marker = L.marker([parseFloat(loc.lat), parseFloat(loc.lng)], {
              icon: createPinIcon(cor),
              title: loc.name
            });
            marker.bindPopup(buildPopup(loc, false), { maxWidth: 260, className: 'pl-popup' });
            marker.on('click', function(){ highlightCard(loc.id); });
            marker.addTo(_markersLayer);
          });

          // Marcador destino (destacado)
          if (data.destino && data.destino.lat) {
            var dest = data.destino;
            var dm = L.marker([parseFloat(dest.lat), parseFloat(dest.lng)], {
              icon: createDestIcon(),
              zIndexOffset: 2000
            });
            dm.bindPopup(buildPopup(dest, true), { maxWidth: 260, className: 'pl-popup pl-popup-destino' });
            dm.addTo(_markersLayer);
            _map.setView([parseFloat(dest.lat), parseFloat(dest.lng)], 16);
            setTimeout(function(){ dm.openPopup(); }, 600);

            // Atualiza card de rota
            var rc = document.querySelector('.map-floating-route-card');
            if (rc) {
              var ti = rc.querySelector('.route-info-title');
              var di = rc.querySelector('.route-info-desc');
              if (ti) ti.textContent = dest.name;
              if (di) di.textContent = dest.address || 'Local selecionado';
            }
          }

          _map.invalidateSize();
          removeLoading();
          resolve(data);
        })
        .catch(function(err) {
          console.error('[PasseLivreMap] Erro fetch:', err);
          removeLoading();
          resolve(null);
        });
    });
  }

  function highlightCard(localId) {
    document.querySelectorAll('.place-card').forEach(function(c){ c.classList.remove('map-highlighted'); });
    var card = document.querySelector('.place-card[data-local-id="' + localId + '"]');
    if (card) {
      card.classList.add('map-highlighted');
      card.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
  }

  window.PasseLivreMap = {
    init: init,
    loadPlaces: loadPlaces,
    highlightCard: highlightCard,
    getMap: function(){ return _map; }
  };

}());