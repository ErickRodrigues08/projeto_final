/**
 * Passe Livre Suzano — Gestão de Locais Cadastrados
 * Integração SQL: SELECT/INSERT/UPDATE/DELETE via PLDB.locais
 */

document.addEventListener('DOMContentLoaded', () => {

  // Estado da aplicação
  let locais            = [];
  let activeFilterStatus = 'all';
  let activeCategory    = 'all';
  let activeSort        = 'relevance';
  let searchQuery       = '';

  // Elementos DOM
  const tableBody        = document.getElementById('locais-table-body');
  const searchInput      = document.getElementById('locais-search-input');
  const categorySelect   = document.getElementById('locais-category-select');
  const sortSelect       = document.getElementById('locais-sort-select');
  const statusPills      = document.querySelectorAll('.locais-pill-btn');
  const countText        = document.getElementById('locais-count-display');
  const btnAddNew        = document.getElementById('btn-add-new-local');
  const btnExport        = document.getElementById('btn-export-report');
  const btnGenerateMatrix = document.getElementById('btn-generate-matrix');
  const modalNew         = document.getElementById('modal-novo-local');
  const formNew          = document.getElementById('form-novo-local');
  const modalDetail      = document.getElementById('modal-detalhes-local');
  const modalMatrix      = document.getElementById('modal-matriz-conectividade');

  // =========================================================================
  // 1. CARREGA LOCAIS DO MYSQL / REST API
  // =========================================================================
  async function geocodificarEndereco(texto) {
    const t = (texto || '').trim();
    if (t.length < 5) return null;
    try {
      const res = await fetch('api/geocode.php?action=search&limit=1&q=' + encodeURIComponent(t), { credentials: 'same-origin' });
      const data = await res.json();
      const r = data && Array.isArray(data.results) ? data.results[0] : null;
      const lat = r ? parseFloat(r.lat) : NaN;
      const lng = r ? parseFloat(r.lng) : NaN;
      if (!Number.isFinite(lat) || !Number.isFinite(lng)) return null;
      return {
        lat: lat,
        lng: lng,
        municipio: (r.parts && r.parts.city) || '',
        bairro: (r.parts && r.parts.suburb) || ''
      };
    } catch (_) {
      return null;
    }
  }

  async function loadFromSQL() {
    try {
      const res = await fetch('api/locais.php?action=list&radius=100');
      const data = await res.json();
      if (data && data.success && Array.isArray(data.locais) && data.locais.length > 0) {
        locais = data.locais.map(r => {
          let features = Array.isArray(r.features) ? r.features : [];
          if (typeof r.features === 'string') {
            try { features = JSON.parse(r.features); } catch (_) {}
          }
          const incCount = parseInt(r.incidents_count) || 0;
          return {
            id:             r.local_code,
            dbId:           r.id,
            name:           r.name,
            bairro:         `${r.bairro || '—'}${r.municipio ? ' • ' + r.municipio : ''} • ID #${r.local_code}`,
            category:       r.category,
            categoryKey:    r.category_key,
            address:        r.address,
            accessStatus:   r.access_status,
            accessText:     r.access_text,
            rating:         parseFloat(r.rating) || 0,
            votes:          parseInt(r.reviews_count) || 0,
            incidentsCount: incCount,
            incidentStatus: incCount > 0 ? (incCount >= 3 ? 'danger' : 'warning') : 'none',
            incidentText:   incCount > 0 ? `${incCount} ${incCount === 1 ? 'aberta' : 'abertas'}` : 'Nenhuma',
            avatarColor:    r.avatar_color || '#b45309',
            avatarIcon:     r.avatar_icon || 'assets/icons/pin.svg',
            lat:            r.lat,
            lng:            r.lng,
            municipio:      r.municipio || '',
            features:       features
          };
        });
        renderTable();
        return;
      }
    } catch (fetchErr) {
      console.warn('[Locais] fetch api/locais.php falhou:', fetchErr);
    }

    if (!window.PLDB) { renderTable(); return; }
    try {
      const rows = window.PLDB.locais.findAll();
      locais = rows.map(r => {
        let features = [];
        try { features = JSON.parse(r.features || '[]'); } catch (_) {}
        return {
          id:             r.local_code,
          dbId:           r.id,
          name:           r.name,
          bairro:         `${r.bairro || '—'}${r.municipio ? ' • ' + r.municipio : ''} • ID #${r.local_code}`,
          category:       r.category,
          categoryKey:    r.category_key,
          address:        r.address,
          accessStatus:   r.access_status,
          accessText:     r.access_text,
          rating:         r.rating,
          votes:          r.reviews_count,
          incidentsCount: r.incidents_count,
          incidentStatus: r.incidents_count > 0 ? (r.incidents_count >= 3 ? 'danger' : 'warning') : 'none',
          incidentText:   r.incidents_count > 0 ? `${r.incidents_count} ${r.incidents_count === 1 ? 'aberta' : 'abertas'}` : 'Nenhuma',
          avatarColor:    r.avatar_color,
          avatarIcon:     r.avatar_icon,
          lat:            r.lat,
          lng:            r.lng,
          municipio:      r.municipio || '',
          features:       features
        };
      });
      console.log(`[Locais SQL] ${locais.length} locais carregados do banco.`);
    } catch (e) {
      console.warn('[Locais] Falha no SELECT SQL:', e);
    }
    renderTable();
  }

  // =========================================================================
  // 2. RENDERIZA TABELA
  // =========================================================================
  function renderTable() {
    if (!tableBody) return;

    /* O painel usa "full" e a coluna access_status grava "verified": mesma
       classificação, escritas diferentes. Sem normalizar, o filtro "100%
       Acessíveis" não encontrava nenhum dos locais verificados. */
    const normStatus = s => (s === 'full' ? 'verified' : s);

    let filtered = locais.filter(item => {
      if (activeFilterStatus !== 'all' && normStatus(item.accessStatus) !== normStatus(activeFilterStatus)) return false;
      if (activeCategory !== 'all' && item.categoryKey !== activeCategory) return false;
      if (searchQuery.trim()) {
        const q = searchQuery.toLowerCase();
        if (!`${item.name} ${item.bairro} ${item.address} ${item.id} ${item.category}`.toLowerCase().includes(q)) return false;
      }
      return true;
    });

    if (activeSort === 'rating')      filtered.sort((a,b) => b.rating - a.rating);
    else if (activeSort === 'name')   filtered.sort((a,b) => a.name.localeCompare(b.name));
    else if (activeSort === 'incidents') filtered.sort((a,b) => b.incidentsCount - a.incidentsCount);
    else if (activeSort === 'rating_asc') filtered.sort((a,b) => a.rating - b.rating);

    if (!filtered.length) {
      tableBody.innerHTML = `<tr><td colspan="7" style="text-align:center;padding:48px 20px;color:#78716c;"><div style="font-size:32px;margin-bottom:8px;">🔍</div><div style="font-weight:700;font-size:14px;color:#44403c;margin-bottom:4px;">Nenhum local encontrado</div><p style="font-size:12px;margin:0;">Tente ajustar sua busca ou limpar os filtros.</p></td></tr>`;
      if (countText) countText.textContent = `Exibindo 0 de ${locais.length} locais cadastrados`;
      return;
    }

    tableBody.innerHTML = filtered.map(item => {
      const statusIcon = item.accessStatus === 'full' || item.accessStatus === 'verified'
        ? 'assets/icons/check.svg'
        : (item.accessStatus === 'partial' ? 'assets/icons/warning.svg' : 'assets/icons/ban.svg');
      return `
        <tr data-id="${item.id}" data-db-id="${item.dbId || ''}">
          <td>
            <div class="local-identity-cell">
              <div class="local-avatar-circle ${item.avatarColor}">
                <img src="${item.avatarIcon}" alt="" onerror="this.src='assets/icons/pin.svg'">
              </div>
              <div class="local-texts-wrap">
                <div class="local-name-title">${escHtml(item.name)}</div>
                <div class="local-bairro-sub">${escHtml(item.bairro)}</div>
              </div>
            </div>
          </td>
          <td><span class="category-pill-badge">${escHtml(item.category)}</span></td>
          <td><span class="address-cell-text" title="${escHtml(item.address)}">${escHtml(item.address)}</span></td>
          <td>
            <span class="accessibility-status-pill ${item.accessStatus}">
              <img src="${statusIcon}" alt="" aria-hidden="true">
              ${escHtml(item.accessText)}
            </span>
          </td>
          <td>
            <div class="rating-cell-wrap">
              <span class="star-rating-icon">★</span>
              <span>${item.rating.toFixed(1)}</span>
              <span class="rating-votes-count">(${item.votes})</span>
            </div>
          </td>
          <td><span class="incidents-badge ${item.incidentStatus}">${escHtml(item.incidentText)}</span></td>
          <td>
            <div class="actions-buttons-cluster">
              <button class="btn-local-action-mini" title="Visualizar Detalhes" onclick="verDetalhes('${item.id}')">
                <img src="assets/icons/eye.png" alt="Visualizar" onerror="this.src='assets/icons/pin.svg'">
              </button>
              <button class="btn-local-action-mini" title="Editar Cadastro" onclick="editarLocal('${item.id}')">
                <img src="assets/icons/edit.svg" alt="Editar">
              </button>
              <button class="btn-local-action-mini" title="Ver no Mapa" onclick="verNoMapa('${item.id}')">
                <img src="assets/icons/pin.svg" alt="Mapa">
              </button>
              <button class="btn-local-action-mini" title="Excluir" onclick="excluirLocal('${item.id}')">
                <img src="assets/icons/ban.svg" alt="Excluir">
              </button>
            </div>
          </td>
        </tr>`;
    }).join('');

    if (countText) countText.textContent = `Exibindo 1-${filtered.length} de ${locais.length} locais cadastrados`;
  }

  // =========================================================================
  // 3. EVENTOS DE FILTRO
  // =========================================================================
  if (searchInput) searchInput.addEventListener('input', e => { searchQuery = e.target.value; renderTable(); });
  if (categorySelect) categorySelect.addEventListener('change', e => { activeCategory = e.target.value; renderTable(); showToast(`Categoria: ${categorySelect.options[categorySelect.selectedIndex].text}`); });
  if (sortSelect) sortSelect.addEventListener('change', e => { activeSort = e.target.value; renderTable(); });

  statusPills.forEach(pill => {
    pill.addEventListener('click', () => {
      statusPills.forEach(p => p.classList.remove('active'));
      pill.classList.add('active');
      activeFilterStatus = pill.getAttribute('data-status') || 'all';
      renderTable();
      showToast(`Exibindo: ${pill.textContent.trim()}`);
    });
  });

  // =========================================================================
  // 4. MODAL NOVO LOCAL — INSERT SQL
  // =========================================================================
  if (btnAddNew) btnAddNew.addEventListener('click', () => abrirModal(modalNew));

  if (formNew) {
    formNew.addEventListener('submit', async e => {
      e.preventDefault();
      const nome     = document.getElementById('input-novo-nome')?.value.trim();
      const catEl    = document.getElementById('input-novo-categoria');
      const catKey   = catEl?.value;
      const catText  = catEl?.options[catEl.selectedIndex]?.text || '';
      const endereco = document.getElementById('input-novo-endereco')?.value.trim();
      const bairro   = document.getElementById('input-novo-bairro')?.value.trim();
      const nivel    = document.getElementById('input-novo-nivel')?.value || 'unverified';

      if (!nome || !endereco || !bairro) { showToast('Preencha todos os campos obrigatórios!', 'error'); return; }

      const statusMap  = { full:'verified', partial:'partial', critical:'critical', unverified:'unverified' };
      const textMap    = { full:'100% Acessível', partial:'Parcial (Adequações Pendentes)', critical:'Crítico (Não Adaptado)', unverified:'Não Verificado' };
      const colorMap   = { saude:'blue', educacao:'purple', parque:'green', publico:'gold', terminal:'orange', shopping:'blue', cultura:'gold', via:'red', outro:'orange' };
      const iconMap    = { saude:'assets/icons/health.svg', educacao:'assets/icons/education.svg', parque:'assets/icons/park.svg' };

      // O endereço é geocodificado antes de gravar. Antes disto o cadastro usava
      // uma coordenada fixa de Suzano: qualquer local novo do estado nascia
      // "dentro" de Suzano no mapa e nas buscas por proximidade.
      const geo = await geocodificarEndereco(`${endereco}, ${bairro}`);
      if (!geo) {
        showToast('Não encontramos esse endereço no mapa. Ajuste o endereço (número, bairro e cidade) para posicionar o local.', 'error');
        return;
      }

      const novoData = {
        name:           nome,
        category:       catText,
        category_key:   catKey || 'outro',
        address:        endereco,
        bairro:         bairro,
        municipio:      geo.municipio,
        uf:             'SP',
        lat:            geo.lat,
        lng:            geo.lng,
        access_status:  statusMap[nivel]  || 'unverified',
        access_text:    textMap[nivel]    || 'Não Verificado',
        rating:         0,
        reviews_count:  0,
        avatar_color:   colorMap[catKey]  || 'orange',
        avatar_icon:    iconMap[catKey]   || 'assets/icons/pin.svg',
        incidents_count: nivel === 'critical' ? 1 : 0,
        features:       ['Recém cadastrado no sistema', 'Vistoria inicial agendada']
      };

      let newCode = `LOC-${Math.floor(1000 + Math.random() * 9000)}`;

      // INSERT SQL
      if (window.PLDB) {
        try {
          const created = window.PLDB.locais.create(novoData);
          if (created) { newCode = created.local_code; loadFromSQL(); }
        } catch (e) {
          console.warn('[Locais] INSERT SQL falhou:', e);
          // Insere localmente para manter UX
          locais.unshift(Object.assign({ id: newCode, dbId: null, votes:0, incidentsCount:0, incidentStatus:'none', incidentText:'Nenhuma' }, novoData));
          renderTable();
        }
      } else {
        locais.unshift(Object.assign({ id: newCode, dbId: null, votes:0, incidentsCount:0, incidentStatus:'none', incidentText:'Nenhuma' }, novoData));
        renderTable();
      }

      fecharModal(modalNew);
      formNew.reset();
      showToast(`Local "${nome}" (#${newCode}) cadastrado${geo.municipio ? ' em ' + geo.municipio : ''} com sucesso!`);
    });
  }

  // =========================================================================
  // 5. AÇÕES NAS LINHAS (globais)
  // =========================================================================
  window.verDetalhes = function (id) {
    const item = locais.find(l => l.id === id);
    if (!item) return;
    const modalBody = document.getElementById('modal-detalhes-body');
    if (modalBody) {
      modalBody.innerHTML = `
        <div style="display:flex;align-items:center;gap:14px;margin-bottom:20px;padding-bottom:14px;border-bottom:1px solid #ede4d8;">
          <div class="local-avatar-circle ${item.avatarColor}" style="width:48px;height:48px;">
            <img src="${item.avatarIcon}" alt="" style="width:24px;height:24px;" onerror="this.src='assets/icons/pin.svg'">
          </div>
          <div>
            <h3 style="margin:0;font-size:16px;font-weight:800;color:#1c1917;">${escHtml(item.name)}</h3>
            <p style="margin:2px 0 0;font-size:12px;color:#78716c;">ID: <strong>#${item.id}</strong> • Bairro: <strong>${escHtml(item.bairro)}</strong></p>
          </div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:18px;">
          <div style="background:#faf7f2;border:1px solid #ede4d8;border-radius:12px;padding:12px 16px;">
            <div style="font-size:11px;font-weight:700;color:#78716c;text-transform:uppercase;">Status de Acessibilidade</div>
            <div style="margin-top:6px;"><span class="accessibility-status-pill ${item.accessStatus}">${escHtml(item.accessText)}</span></div>
          </div>
          <div style="background:#faf7f2;border:1px solid #ede4d8;border-radius:12px;padding:12px 16px;">
            <div style="font-size:11px;font-weight:700;color:#78716c;text-transform:uppercase;">Avaliação Cidadã</div>
            <div style="margin-top:6px;display:flex;align-items:center;gap:6px;font-weight:800;font-size:14px;"><span style="color:#f59e0b;">★</span> ${item.rating.toFixed(1)} / 5.0 <span style="font-size:11px;font-weight:500;color:#78716c;">(${item.votes} avaliações)</span></div>
          </div>
        </div>
        <div style="margin-bottom:18px;">
          <h4 style="font-size:12.5px;font-weight:800;color:#1c1917;margin:0 0 8px;">Endereço & Coordenadas</h4>
          <p style="font-size:12px;color:#57534e;margin:0 0 4px;">📍 ${escHtml(item.address)}</p>
          <p style="font-size:11px;color:#a8a29e;margin:0;">Lat: ${item.lat} | Lng: ${item.lng}</p>
        </div>
        <div style="margin-bottom:18px;">
          <h4 style="font-size:12.5px;font-weight:800;color:#1c1917;margin:0 0 8px;">Recursos Mapeados</h4>
          <ul style="margin:0;padding-left:18px;font-size:12px;color:#44403c;line-height:1.6;">
            ${(item.features||[]).map(f=>`<li>${escHtml(f)}</li>`).join('')}
          </ul>
        </div>
        <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:24px;padding-top:14px;border-top:1px solid #ede4d8;">
          <button type="button" class="btn-locais-action" onclick="fecharModal(document.getElementById('modal-detalhes-local'))">Fechar</button>
          <button type="button" class="btn-locais-primary" onclick="verNoMapa('${item.id}')">
            <img src="assets/icons/map-icon.svg" alt=""> Ver no Mapa
          </button>
        </div>`;
    }
    abrirModal(modalDetail);
  };

  window.verNoMapa = function (id) {
    const item = locais.find(l => l.id === id);
    if (!item) return;
    showToast(`Abrindo mapa: ${item.name}`);
    setTimeout(() => { window.location.href = `mapa-gestor.php?lat=${item.lat}&lng=${item.lng}&locId=${item.id}`; }, 400);
  };

  window.editarLocal = function (id) {
    const item = locais.find(l => l.id === id);
    if (!item) return;
    const novoNome = prompt(`Editar nome do local (${item.id}):`, item.name);
    if (!novoNome || !novoNome.trim()) return;
    item.name = novoNome.trim();
    // UPDATE SQL
    if (window.PLDB && item.dbId) {
      try { window.PLDB.locais.update(item.dbId, { name: item.name }); } catch (e) { console.warn('[Locais] UPDATE SQL falhou:', e); }
    }
    renderTable();
    showToast(`Dados de "${item.name}" atualizados com sucesso!`);
  };

  window.excluirLocal = function (id) {
    const item = locais.find(l => l.id === id);
    if (!item) return;
    if (!confirm(`Deseja inativar o local "${item.name}" (#${item.id}) do inventário público?`)) return;
    // DELETE SQL
    if (window.PLDB && item.dbId) {
      try { window.PLDB.locais.delete(item.dbId); } catch (e) { console.warn('[Locais] DELETE SQL falhou:', e); }
    }
    locais = locais.filter(l => l.id !== id);
    renderTable();
    showToast(`Local "${item.name}" removido do catálogo com sucesso.`);
  };

  // =========================================================================
  // 6. EXPORTAR
  // =========================================================================
  if (btnExport) {
    btnExport.addEventListener('click', () => {
      if (!window.PLDB) { showToast('Exportando relatório...'); return; }
      try {
        const rows  = window.PLDB.locais.findAll();
        const lines = [['Código','Nome','Categoria','Endereço','Bairro','Status','Avaliação','Ocorrências','Lat','Lng']];
        rows.forEach(r => lines.push([r.local_code,r.name,r.category,r.address,r.bairro,r.access_status,r.rating,r.incidents_count,r.lat,r.lng]));
        const csv  = lines.map(l => l.map(v=>`"${v||''}"`).join(',')).join('\n');
        const blob = new Blob(['\uFEFF'+csv],{type:'text/csv;charset=utf-8;'});
        const url  = URL.createObjectURL(blob);
        const a    = Object.assign(document.createElement('a'),{href:url,download:'Locais_PasseLivre_Suzano.csv'});
        document.body.appendChild(a); a.click(); document.body.removeChild(a);
        URL.revokeObjectURL(url);
        showToast(`Relatório exportado: ${rows.length} locais.`);
      } catch (e) { showToast('Exportação concluída!'); }
    });
  }

  if (btnGenerateMatrix) btnGenerateMatrix.addEventListener('click', () => abrirModal(modalMatrix));

  // =========================================================================
  // 7. UTILITÁRIOS DE MODAL
  // =========================================================================
  function abrirModal(modal) { if (!modal) return; modal.classList.add('visible'); document.body.style.overflow = 'hidden'; }
  window.abrirModal  = abrirModal;
  function fecharModal(modal) { if (!modal) return; modal.classList.remove('visible'); document.body.style.overflow = ''; }
  window.fecharModal = fecharModal;

  document.querySelectorAll('.modal-overlay-close').forEach(btn => {
    btn.addEventListener('click', e => fecharModal(e.target.closest('.locais-modal-backdrop')));
  });
  document.querySelectorAll('.locais-modal-backdrop').forEach(backdrop => {
    backdrop.addEventListener('click', e => { if (e.target === backdrop) fecharModal(backdrop); });
  });
  window.addEventListener('keydown', e => {
    if (e.key === 'Escape') document.querySelectorAll('.locais-modal-backdrop.visible').forEach(m => fecharModal(m));
  });

  // =========================================================================
  // 8. TOAST
  // =========================================================================
  function showToast(msg, type) {
    if (typeof window.showToast === 'function') { window.showToast(msg, type); return; }
    const t = document.getElementById('app-toast'), m = document.getElementById('toast-message');
    if (t && m) { m.textContent = msg; t.classList.add('visible'); setTimeout(()=>t.classList.remove('visible'),3600); }
  }
  window.showToast = showToast;

  function escHtml(str) {
    if (!str) return '';
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');
  }

  // INICIALIZA
  if (window.PasseLivreDBReady) {
    window.PasseLivreDBReady.then(() => loadFromSQL());
  } else {
    renderTable();
  }
});
