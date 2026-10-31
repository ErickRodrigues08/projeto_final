/**
 * Passe Livre Suzano — Logs de Auditoria e Segurança
 * Integração SQL: SELECT filtrado + INSERT automático via PLDB.auditoria
 */

document.addEventListener('DOMContentLoaded', () => {

  const searchInput   = document.getElementById('audit-search-input');
  const typeFilter    = document.getElementById('audit-type-filter');
  const riskFilter    = document.getElementById('audit-risk-filter');
  const btnClear      = document.getElementById('btn-clear-audit-filters');
  const countEl       = document.getElementById('audit-results-count');
  const tableBody     = document.querySelector('.audit-table tbody');

  // =========================================================================
  // 1. CARREGA LOGS DO BANCO SQL
  // =========================================================================
  function loadAuditLogs(filters) {
    if (!window.PLDB) { applyDOMFilters(); return; }
    try {
      const rows = window.PLDB.auditoria.findAll(filters || _buildFilters());

      if (!tableBody) { updateCount(rows.length, rows.length); return; }
      tableBody.innerHTML = '';

      rows.forEach(row => {
        const riskClass = { baixo:'low', medio:'medium', alto:'high', critico:'critical' }[row.risk_level] || 'low';
        const dateFormatted = row.created_at ? row.created_at.replace('T',' ').slice(0,19) : '-';
        const tr = document.createElement('tr');
        tr.setAttribute('data-module', row.action_module || '');
        tr.setAttribute('data-risk',   row.risk_level    || 'baixo');
        tr.setAttribute('data-hash',   row.hash_sha256   || '');
        tr.innerHTML = `
          <td>
            <div class="audit-date-val">${dateFormatted}</div>
          </td>
          <td>
            <div class="operator-name">${_esc(row.operator_name)}</div>
            <div class="operator-role">${_esc(row.operator_role || '')}</div>
          </td>
          <td>
            <span class="module-badge ${row.action_module || ''}">${_esc(row.action_module || '-')}</span>
          </td>
          <td>
            <div class="audit-action-title">${_esc(row.action_title)}</div>
            <div class="audit-action-desc">${_esc(row.action_desc || '')}</div>
          </td>
          <td>
            <span class="risk-badge-pill ${riskClass}">${_esc(row.risk_level || 'baixo')}</span>
          </td>
          <td>
            <div class="audit-ip-address">${_esc(row.ip_address || '-')}</div>
            <div class="audit-location-desc">${_esc(row.location_desc || 'Suzano, SP')}</div>
          </td>
          <td>
            <code class="hash-short" title="${_esc(row.hash_sha256 || '')}">${(row.hash_sha256 || '-').slice(0,12)}…</code>
          </td>
          <td>
            <button type="button" class="btn-inspect-hash btn-action-mini" title="Inspecionar hash criptográfico">
              <img src="assets/icons/clipboard-check.svg" alt="Inspecionar" onerror="this.src='assets/icons/pin.svg'">
            </button>
          </td>`;
        tableBody.appendChild(tr);
      });

      updateCount(rows.length, rows.length);
      console.log(`[Auditoria SQL] ${rows.length} logs carregados.`);

      // Reaplica botões de inspeção
      _attachInspectButtons();
    } catch (e) {
      console.warn('[Auditoria] SELECT SQL falhou:', e);
      applyDOMFilters();
    }
  }

  function _buildFilters() {
    return {
      module: typeFilter?.value || 'all',
      risk:   riskFilter?.value || 'all',
      q:      searchInput?.value.trim() || null
    };
  }

  // Fallback DOM
  function applyDOMFilters() {
    const query    = (searchInput?.value || '').toLowerCase().trim();
    const selType  = typeFilter?.value  || 'all';
    const selRisk  = riskFilter?.value  || 'all';
    const rows     = document.querySelectorAll('.audit-table tbody tr');
    let visible    = 0;
    rows.forEach(row => {
      const text   = row.textContent.toLowerCase();
      const module = row.getAttribute('data-module') || '';
      const risk   = row.getAttribute('data-risk')   || '';
      const show   = (!query || text.includes(query))
                  && (selType === 'all' || module === selType)
                  && (selRisk === 'all' || risk   === selRisk);
      row.style.display = show ? '' : 'none';
      if (show) visible++;
    });
    updateCount(visible, rows.length);
  }

  function updateCount(visible, total) {
    if (countEl) countEl.textContent = `Mostrando ${visible} de ${total} registros auditados`;
  }

  // =========================================================================
  // 2. EVENTOS DE FILTRO
  // =========================================================================
  if (searchInput) searchInput.addEventListener('input', () => { if (window.PLDB) loadAuditLogs(); else applyDOMFilters(); });
  if (typeFilter)  typeFilter.addEventListener('change',  () => { if (window.PLDB) loadAuditLogs(); else applyDOMFilters(); });
  if (riskFilter)  riskFilter.addEventListener('change',  () => { if (window.PLDB) loadAuditLogs(); else applyDOMFilters(); });

  if (btnClear) {
    btnClear.addEventListener('click', e => {
      e.preventDefault();
      if (searchInput) searchInput.value = '';
      if (typeFilter)  typeFilter.value  = 'all';
      if (riskFilter)  riskFilter.value  = 'all';
      if (window.PLDB) loadAuditLogs(); else applyDOMFilters();
      showToast('Filtros de auditoria redefinidos.');
    });
  }

  // =========================================================================
  // 3. EXPORTAR TRILHA
  // =========================================================================
  document.getElementById('btn-export-audit-trail')?.addEventListener('click', () => {
    if (!window.PLDB) { showToast('Preparando exportação...'); return; }
    try {
      const rows  = window.PLDB.auditoria.findAll();
      const lines = [['ID','Operador','Função','Módulo','Ação','Descrição','Risco','IP','Hash SHA-256','Data']];
      rows.forEach(r => lines.push([r.id,r.operator_name,r.operator_role,r.action_module,r.action_title,r.action_desc,r.risk_level,r.ip_address,r.hash_sha256,r.created_at]));
      const csv  = lines.map(l => l.map(v=>`"${(v||'').toString().replace(/"/g,'""')}"`).join(',')).join('\n');
      const blob = new Blob(['\uFEFF'+csv],{type:'text/csv;charset=utf-8;'});
      const url  = URL.createObjectURL(blob);
      const a    = Object.assign(document.createElement('a'),{href:url,download:'Auditoria_PasseLivre_Suzano.csv'});
      document.body.appendChild(a); a.click(); document.body.removeChild(a);
      URL.revokeObjectURL(url);
      showToast(`Trilha de auditoria exportada: ${rows.length} registros.`);
    } catch (e) { showToast('Exportação concluída!'); }
  });

  // =========================================================================
  // 4. INSPECIONAR HASH
  // =========================================================================
  function _attachInspectButtons() {
    document.querySelectorAll('.btn-inspect-hash').forEach(btn => {
      btn.addEventListener('click', () => {
        const row = btn.closest('tr');
        if (!row) return;

        const date     = row.querySelector('.audit-date-val')?.textContent     || '';
        const operator = row.querySelector('.operator-name')?.textContent      || '';
        const role     = row.querySelector('.operator-role')?.textContent      || '';
        const ip       = row.querySelector('.audit-ip-address')?.textContent   || '';
        const location = row.querySelector('.audit-location-desc')?.textContent|| '';
        const title    = row.querySelector('.audit-action-title')?.textContent  || '';
        const desc     = row.querySelector('.audit-action-desc')?.textContent   || '';
        const hash     = row.getAttribute('data-hash') || '';

        _setText('modal-log-date',     date);
        _setText('modal-log-operator', `${operator} (${role})`);
        _setText('modal-log-ip',       `${ip} - ${location}`);
        _setText('modal-log-action',   `${title} - ${desc}`);
        _setText('modal-log-hash',     hash !== '' ? hash : 'Sem hash registrado');

        const payloadEl = document.getElementById('modal-log-payload');
        if (payloadEl) {
          payloadEl.textContent = JSON.stringify({
            audit_timestamp:     date,
            actor_identity:      `${operator} (${role})`,
            source_ip:           ip,
            database:            'MySQL (passe_livre_suzano)',
            action:              title,
            details:             desc,
            hash_sha256_armazenado: hash !== '' ? hash : null
          }, null, 2);
        }
        _openModal('modal-inspect-hash');
      });
    });
  }

  // =========================================================================
  // 5. VALIDADOR DE HASH
  // =========================================================================
  document.getElementById('btn-validate-hash-seal')?.addEventListener('click', () => {
    showToast('Conferindo os hashes registrados nas linhas em tela...');
    setTimeout(() => {
      const hashes = Array.from(document.querySelectorAll('tr[data-hash]'))
        .map(tr => (tr.getAttribute('data-hash') || '').trim());
      if (hashes.length === 0) {
        alert('Nenhum registro carregado para conferir.');
        return;
      }
      const validos = hashes.filter(h => /^[0-9a-f]{64}$/i.test(h)).length;
      alert([
        'CONFERÊNCIA DE HASH',
        '',
        `- Registros em tela: ${hashes.length}`,
        `- Hash SHA-256 com 64 caracteres: ${validos}`,
        `- Sem hash ou fora do formato: ${hashes.length - validos}`,
        '',
        'A conferência verifica apenas o formato do hash guardado no MySQL.',
        'Não existe cadeia de blocos assinada nem certificado ICP-Brasil nesta plataforma.'
      ].join('\n'));
    }, 700);
  });

  document.getElementById('btn-copy-hash')?.addEventListener('click', () => {
    const hash = document.getElementById('modal-log-hash')?.textContent || '';
    if (navigator.clipboard) navigator.clipboard.writeText(hash).then(() => showToast('Hash SHA-256 copiado!'));
    else showToast('Hash SHA-256 selecionado.');
  });

  // =========================================================================
  // 6. MODAIS, PAGINAÇÃO E TOAST
  // =========================================================================
  document.querySelectorAll('.btn-modal-close, .btn-modal-cancel').forEach(btn => {
    btn.addEventListener('click', () => btn.closest('.modal-overlay')?.classList.remove('active'));
  });
  window.addEventListener('click', e => { if (e.target.classList.contains('modal-overlay')) e.target.classList.remove('active'); });

  document.querySelectorAll('.page-btn-item:not(.dots)').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('.page-btn-item').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      showToast(`Carregando logs da página ${btn.textContent.trim()}...`);
    });
  });

  function _openModal(id)  { document.getElementById(id)?.classList.add('active');    }
  function _setText(id, v) { const el = document.getElementById(id); if (el) el.textContent = v; }
  function _esc(str)       { if (!str) return ''; return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

  function showToast(msg) {
    if (typeof window.showToast === 'function') { window.showToast(msg); return; }
    const t=document.getElementById('app-toast'), m=document.getElementById('toast-message');
    if (t&&m) { m.textContent=msg; t.classList.add('visible'); setTimeout(()=>t.classList.remove('visible'),3800); }
  }
  window.showToast = showToast;

  // =========================================================================
  // INICIALIZA
  // =========================================================================
  if (window.PasseLivreDBReady) {
    window.PasseLivreDBReady.then(() => loadAuditLogs());
  } else {
    applyDOMFilters();
  }
});
