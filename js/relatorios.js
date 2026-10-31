/**
 * Passe Livre Suzano — Relatórios e Análises
 * Integração real com api/relatorios.php: listagem, KPIs, emissão e download.
 */

document.addEventListener('DOMContentLoaded', async () => {

  let reports = [];

  // Elementos DOM
  const tableBody      = document.getElementById('relatorios-table-body');
  const countText      = document.getElementById('relatorios-count-display');
  const btnExportQuick = document.getElementById('btn-export-quick');
  const btnNewReport   = document.getElementById('btn-new-report');
  const modalNew       = document.getElementById('modal-novo-relatorio');
  const formNew        = document.getElementById('form-novo-relatorio');
  const modalPreview   = document.getElementById('modal-preview-relatorio');

  // =========================================================================
  // HELPERS
  // =========================================================================
  async function relApi(action, payload) {
    const opts = { method: payload ? 'POST' : 'GET', credentials: 'same-origin', headers: {} };
    if (payload) {
      opts.headers['Content-Type'] = 'application/json';
      opts.body = JSON.stringify(payload);
    }
    const res = await fetch('api/relatorios.php?action=' + encodeURIComponent(action), opts);
    let json = null;
    try { json = await res.json(); } catch (_) {}
    if (!res.ok || !json || json.success === false) {
      throw new Error((json && (json.error || json.message)) || ('HTTP ' + res.status));
    }
    return json;
  }

  function fmtDate(value) {
    if (!value) return '—';
    const parts = String(value).slice(0, 10).split('-');
    return parts.length === 3 ? parts[2] + '/' + parts[1] + '/' + parts[0] : value;
  }

  function fmtDateTime(value) {
    if (!value) return '—';
    const d = new Date(String(value).replace(' ', 'T'));
    if (isNaN(d.getTime())) return value;
    return d.toLocaleDateString('pt-BR') + ' às ' +
      d.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
  }

  function fmtSize(bytes) {
    const n = parseInt(bytes, 10);
    if (!n || n <= 0) return '—';
    if (n < 1024) return n + ' B';
    if (n < 1024 * 1024) return (n / 1024).toFixed(1) + ' KB';
    return (n / (1024 * 1024)).toFixed(1) + ' MB';
  }

  function downloadUrl(id) {
    return 'api/relatorios.php?action=download&id=' + encodeURIComponent(id);
  }

  function mapReport(row) {
    const format = String(row.formato || 'PDF').toUpperCase();
    return {
      id: row.id,
      name: row.titulo || 'Relatório sem título',
      desc: [row.tipo_relatorio, row.gestor_nome ? 'emitido por ' + row.gestor_nome : ''].filter(Boolean).join(' · '),
      period: fmtDate(row.periodo_inicio) + ' — ' + fmtDate(row.periodo_fim),
      date: fmtDateTime(row.created_at),
      format: format,
      formatClass: format.toLowerCase(),
      size: row.arquivo_existe ? fmtSize(row.tamanho_bytes) : 'arquivo ausente',
      exists: !!row.arquivo_existe
    };
  }

  // =========================================================================
  // 1. KPIs REAIS VIA API
  // =========================================================================
  async function loadKPIs() {
    try {
      const json = await relApi('kpis');
      const k = json.kpis || {};
      _setText('kpi-total-solicitacoes', Number(k.total_solicitacoes || 0).toLocaleString('pt-BR'));
      _setText('kpi-taxa-deferimento', (k.taxa_deferimento != null ? k.taxa_deferimento : 0) + '%');
      _setText('kpi-tempo-analise', k.tempo_medio_dias != null ? k.tempo_medio_dias : '—');
      _setText('kpi-indice-nps', Number(k.total_locais || 0).toLocaleString('pt-BR'));
    } catch (e) {
      console.warn('[Relatórios] Falha ao carregar KPIs:', e);
    }
  }

  // =========================================================================
  // 2. LISTA DE RELATÓRIOS (banco de dados)
  // =========================================================================
  let activeFormatFilter = '';

  async function loadReports() {
    if (!tableBody) return;
    try {
      const json = await relApi('list');
      reports = (json.rows || []).map(mapReport);
      renderReportsTable();
    } catch (e) {
      tableBody.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:30px;color:#b91c1c;">Não foi possível carregar os relatórios: ' + _esc(e.message) + '</td></tr>';
      if (countText) countText.textContent = 'Erro ao consultar relatórios';
    }
  }

  function renderReportsTable() {
    if (!tableBody) return;

    const filtered = activeFormatFilter
      ? reports.filter(r => r.format === activeFormatFilter)
      : reports;

    if (!filtered.length) {
      tableBody.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:40px 20px;color:#78716c;"><div style="font-size:28px;margin-bottom:6px;">📄</div><div style="font-weight:700;font-size:13px;">' +
        (reports.length ? 'Nenhum relatório para o filtro selecionado' : 'Nenhum relatório emitido até o momento') +
        '</div></td></tr>';
      if (countText) countText.textContent = 'Exibindo 0 relatórios';
      return;
    }

    tableBody.innerHTML = filtered.map(item => `
      <tr data-id="${item.id}">
        <td>
          <div class="report-identity-cell">
            <div class="report-file-icon-circle ${item.formatClass}">
              <img src="${item.format === 'PDF' ? 'assets/icons/pdf.svg' : 'assets/icons/file-text.svg'}" alt="${item.format}" onerror="this.src='assets/icons/file-text.svg'">
            </div>
            <div class="report-texts-wrap">
              <div class="report-name-title">${_esc(item.name)}</div>
              <div class="report-desc-sub">${_esc(item.desc)}</div>
            </div>
          </div>
        </td>
        <td><span class="report-period-text">${_esc(item.period)}</span></td>
        <td><span class="report-date-text">${_esc(item.date)}</span></td>
        <td><span class="report-format-badge ${item.formatClass}">${item.format}</span></td>
        <td><span class="report-size-text">${_esc(item.size)}</span></td>
        <td>
          <div class="report-actions-cluster">
            <button class="btn-report-icon-mini" title="Copiar link de download" onclick="compartilharRelatorio('${item.id}')"><img src="assets/icons/share.svg" alt="Compartilhar" onerror="this.src='assets/icons/pin.svg'"></button>
            <button class="btn-report-icon-mini" title="Pré-visualizar" onclick="visualizarRelatorio('${item.id}')"><img src="assets/icons/eye.png" alt="Ver" onerror="this.src='assets/icons/pin.svg'"></button>
            <button class="btn-report-download" onclick="baixarRelatorio('${item.id}')" ${item.exists ? '' : 'disabled title="Arquivo indisponível — emita novamente"'}>
              <img src="assets/icons/download.svg" alt="" aria-hidden="true"> Baixar
            </button>
          </div>
        </td>
      </tr>`).join('');

    if (countText) {
      countText.textContent = 'Exibindo ' + filtered.length + ' de ' + reports.length + ' relatório(s) gerado(s)';
    }
  }

  // =========================================================================
  // 3. FILTROS
  // =========================================================================
  window.filtrarRelatorios = function () {
    const entrada = (window.prompt('Filtrar por formato (PDF, XLSX, CSV). Deixe vazio para exibir todos:', activeFormatFilter) || '').trim().toUpperCase();
    if (entrada && ['PDF', 'XLSX', 'CSV'].indexOf(entrada) === -1) {
      showToast('Formato inválido. Use PDF, XLSX ou CSV.', 'error');
      return;
    }
    activeFormatFilter = entrada;
    renderReportsTable();
    showToast(entrada ? 'Filtrando relatórios em ' + entrada : 'Exibindo todos os formatos.');
  };

  // =========================================================================
  // 4. AÇÕES DE RELATÓRIO
  // =========================================================================
  window.compartilharRelatorio = function (id) {
    const item = reports.find(r => String(r.id) === String(id));
    if (!item) return;
    const absolute = new URL(downloadUrl(id), window.location.href).href;
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(absolute)
        .then(() => showToast('Link de download copiado (requer sessão de gestor).'))
        .catch(() => showToast('Link: ' + absolute));
    } else {
      showToast('Link: ' + absolute);
    }
  };

  window.visualizarRelatorio = function (id) {
    const item = reports.find(r => String(r.id) === String(id));
    if (!item || !modalPreview) return;
    const bodyEl = document.getElementById('modal-preview-body');
    if (bodyEl) {
      bodyEl.innerHTML =
        '<h4 style="margin:0 0 8px;font-size:15px;">' + _esc(item.name) + '</h4>' +
        '<p style="font-size:12px;color:#78716c;margin:0 0 10px;">' + _esc(item.desc) + '</p>' +
        '<p style="font-size:12.5px;color:#44403c;margin:0 0 4px;"><strong>Período:</strong> ' + _esc(item.period) + '</p>' +
        '<p style="font-size:12.5px;color:#44403c;margin:0 0 4px;"><strong>Emitido em:</strong> ' + _esc(item.date) + '</p>' +
        '<p style="font-size:12.5px;color:#44403c;margin:0 0 4px;"><strong>Formato:</strong> ' + item.format + '</p>' +
        '<p style="font-size:12.5px;color:#44403c;margin:0 0 14px;"><strong>Tamanho:</strong> ' + _esc(item.size) + '</p>' +
        (item.exists
          ? '<button type="button" class="btn-relatorio-primary" onclick="baixarRelatorio(\'' + item.id + '\')">Baixar arquivo</button>'
          : '<p style="font-size:12px;color:#b91c1c;margin:0;">Arquivo indisponível no servidor. Emita o relatório novamente.</p>');
    }
    modalPreview.classList.add('active');
  };

  window.baixarRelatorio = function (id) {
    const item = reports.find(r => String(r.id) === String(id));
    if (!item) return;
    if (!item.exists) {
      showToast('Arquivo indisponível. Emita o relatório novamente.', 'error');
      return;
    }
    window.location.href = downloadUrl(id);
    showToast('Download iniciado: ' + item.name);
  };

  // =========================================================================
  // 5. NOVO RELATÓRIO — emissão real via API
  // =========================================================================
  if (btnNewReport) btnNewReport.addEventListener('click', () => modalNew?.classList.add('active'));

  const MONTHS = {
    janeiro: 1, fevereiro: 2, marco: 3, março: 3, abril: 4, maio: 5, junho: 6,
    julho: 7, agosto: 8, setembro: 9, outubro: 10, novembro: 11, dezembro: 12
  };

  function periodToRange(periodo) {
    const yearMatch = periodo.match(/^Ano Base (\d{4})$/i);
    if (yearMatch) {
      return { inicio: yearMatch[1] + '-01-01', fim: yearMatch[1] + '-12-31' };
    }
    const monthMatch = periodo.match(/^([A-Za-zÀ-ÿ]+)\s+(\d{4})$/);
    if (monthMatch) {
      const month = MONTHS[monthMatch[1].toLowerCase()];
      const year = parseInt(monthMatch[2], 10);
      if (month) {
        const lastDay = new Date(year, month, 0).getDate();
        const mm = String(month).padStart(2, '0');
        return { inicio: year + '-' + mm + '-01', fim: year + '-' + mm + '-' + String(lastDay).padStart(2, '0') };
      }
    }
    const today = new Date();
    const iso = today.toISOString().slice(0, 10);
    return { inicio: iso.slice(0, 8) + '01', fim: iso };
  }

  if (formNew) {
    formNew.addEventListener('submit', async e => {
      e.preventDefault();

      const titulo  = document.getElementById('input-relatorio-titulo')?.value.trim() || '';
      const modulo  = document.getElementById('input-relatorio-modulo')?.value || 'geral';
      const formato = document.getElementById('input-relatorio-formato')?.value || 'PDF';
      const periodo = document.getElementById('input-relatorio-periodo')?.value || 'Ano Base ' + new Date().getFullYear();
      const tipoAgr = document.getElementById('input-relatorio-tipo')?.value || 'executivo';
      const desc    = document.getElementById('input-relatorio-desc')?.value.trim() || '';

      if (!titulo) { showToast('Informe o título do relatório.', 'error'); return; }

      const range = periodToRange(periodo);
      const tipo = (tipoAgr === 'analitico' ? 'Analítico' : 'Sumário Executivo') +
        (desc ? ' — ' + desc.slice(0, 80) : '');

      const submitBtn = formNew.querySelector('button[type="submit"]');
      if (submitBtn) submitBtn.disabled = true;

      try {
        const json = await relApi('generate', {
          titulo: titulo,
          tipo: tipo,
          modulo: modulo,
          formato: formato,
          periodo_inicio: range.inicio,
          periodo_fim: range.fim,
          periodo_label: periodo
        });

        modalNew?.classList.remove('active');
        formNew.reset();
        await loadReports();
        await loadKPIs();
        showToast(json.message || 'Relatório emitido com sucesso!');

        if (json.download_url) {
          window.location.href = json.download_url;
        }
      } catch (err) {
        showToast('Erro ao emitir relatório: ' + err.message, 'error');
      } finally {
        if (submitBtn) submitBtn.disabled = false;
      }
    });
  }

  // =========================================================================
  // 6. EXPORTAR RÁPIDO — CSV do ano corrente, gerado no servidor
  // =========================================================================
  if (btnExportQuick) {
    btnExportQuick.addEventListener('click', async () => {
      btnExportQuick.disabled = true;
      try {
        const year = new Date().getFullYear();
        const json = await relApi('generate', {
          titulo: 'Exportação Completa de Solicitações — ' + year,
          tipo: 'Exportação Rápida',
          modulo: 'geral',
          formato: 'CSV',
          periodo_inicio: year + '-01-01',
          periodo_fim: year + '-12-31'
        });
        await loadReports();
        showToast(json.message || 'Exportação gerada.');
        if (json.download_url) {
          window.location.href = json.download_url;
        }
      } catch (err) {
        showToast('Erro ao exportar: ' + err.message, 'error');
      } finally {
        btnExportQuick.disabled = false;
      }
    });
  }

  // =========================================================================
  // 7. MODAIS E TOAST
  // =========================================================================
  document.querySelectorAll('.btn-modal-close, .btn-modal-cancel, .modal-overlay-close').forEach(btn => {
    btn.addEventListener('click', () => btn.closest('.modal-overlay, .relatorio-modal-backdrop')?.classList.remove('active'));
  });
  window.addEventListener('click', e => {
    if (e.target.classList.contains('relatorio-modal-backdrop')) e.target.classList.remove('active');
  });

  const globalToast = (typeof window.showToast === 'function') ? window.showToast.bind(window) : null;
  function showToast(msg, type) {
    if (globalToast) { globalToast(msg, type); return; }
    const t = document.getElementById('app-toast');
    const m = document.getElementById('toast-message');
    if (t && m) { m.textContent = msg; t.classList.add('visible'); setTimeout(() => t.classList.remove('visible'), 3600); }
  }

  function _setText(id, val) { const el = document.getElementById(id); if (el) el.textContent = val == null ? '' : val; }
  function _esc(str) {
    if (str == null) return '';
    return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

  // INICIALIZA
  await loadKPIs();
  await loadReports();
});
