/**
 * Passe Livre Suzano — Lista de Usuários do Gestor
 * perfil-gestor.php  ·  js/perfil-gestor.js
 *
 * Funcionalidades:
 *  - KPIs de resumo (total, ativos, pendentes, por deficiência)
 *  - Busca em tempo real (nome, e-mail, CPF, ID)
 *  - Filtros: deficiência, gênero, status, laudo, faixa etária, cidade/bairro
 *  - Chips visuais dos filtros ativos com remoção individual
 *  - Ordenação
 *  - Paginação (8 cards por página)
 *  - Exportação CSV da lista filtrada
 *  - Clique em qualquer card navega para perfil-cidadao-detalhe.php?id=USR-XXX
 */

document.addEventListener('DOMContentLoaded', () => {

  let db = window.USUARIOS_DB || [];

  /* ──────────────────────────────────────────────────────────────────────────
     ESTADO
  ────────────────────────────────────────────────────────────────────────── */
  const state = {
    busca: '',
    filtros: {
      deficiencia: '',
      genero: '',
      status: '',
      laudo: '',
      idadeMin: '',
      idadeMax: '',
      cidade: ''
    },
    ordenar: 'nome-az',
    paginaAtual: 1,
    porPagina: 8
  };

  /* ──────────────────────────────────────────────────────────────────────────
     ELEMENTOS DO DOM
  ────────────────────────────────────────────────────────────────────────── */
  const elGrid         = document.getElementById('usuarios-grid');
  const elCount        = document.getElementById('resultado-count');
  const elPaginacao    = document.getElementById('paginacao-wrap');
  const elChipsAtivos  = document.getElementById('filtros-ativos-row');
  const elKpis         = document.getElementById('kpis-usuarios');

  const elBusca        = document.getElementById('busca-usuarios');
  const elHeaderBusca  = document.getElementById('header-search-quick');
  const elDeficiencia  = document.getElementById('filtro-deficiencia');
  const elGenero       = document.getElementById('filtro-genero');
  const elStatus       = document.getElementById('filtro-status');
  const elLaudo        = document.getElementById('filtro-laudo');
  const elIdadeMin     = document.getElementById('filtro-idade-min');
  const elIdadeMax     = document.getElementById('filtro-idade-max');
  const elCidade       = document.getElementById('filtro-cidade');
  const elOrdenar      = document.getElementById('ordenar-por');
  const elBtnLimpar    = document.getElementById('btn-limpar-filtros');
  const elBtnExportar  = document.getElementById('btn-exportar-lista');

  /* ──────────────────────────────────────────────────────────────────────────
     KPIs
  ────────────────────────────────────────────────────────────────────────── */
  function renderKpis() {
    if (!elKpis) return;

    const total     = db.length;
    const ativos    = db.filter(u => u.status === 'Ativo').length;
    const pend      = db.filter(u => u.status === 'Pendente').length;
    const bloq      = db.filter(u => u.status === 'Bloqueado').length;
    const fisico    = db.filter(u => u.deficiencia === 'Física/Motora').length;
    const visual    = db.filter(u => u.deficiencia === 'Visual').length;
    const auditiva  = db.filter(u => u.deficiencia === 'Auditiva').length;
    const tea       = db.filter(u => u.deficiencia === 'Intelectual/TEA').length;
    const mob       = db.filter(u => u.deficiencia === 'Mobilidade Reduzida').length;
    const laudoOk   = db.filter(u => u.laudoValido).length;
    const laudoPend = db.filter(u => !u.laudoValido).length;

    // Percentual de ativos para barra
    const pctAtivos = total > 0 ? Math.round((ativos / total) * 100) : 0;

    const kpis = [
      {
        label: 'Total Cadastrados',
        valor: total,
        sub: `${ativos} ativos • ${pend} pendentes`,
        icone: `<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>`,
        bg: '#f0f9ff', border: '#bae6fd', iconBg: '#0ea5e9', valorColor: '#0c4a6e',
        barra: null,
        filtro: null
      },
      {
        label: 'Usuários Ativos',
        valor: ativos,
        sub: `${pctAtivos}% do total`,
        icone: `<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>`,
        bg: '#f0fdf4', border: '#bbf7d0', iconBg: '#16a34a', valorColor: '#14532d',
        barra: pctAtivos,
        barraColor: '#22c55e',
        filtro: 'status=Ativo'
      },
      {
        label: 'Pendentes',
        valor: pend,
        sub: pend > 0 ? 'Aguardam análise' : 'Nenhum pendente',
        icone: `<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>`,
        bg: '#fffbeb', border: '#fde68a', iconBg: '#d97706', valorColor: '#78350f',
        barra: null,
        filtro: 'status=Pendente'
      },
      {
        label: 'Laudos Válidos',
        valor: laudoOk,
        sub: `${laudoPend} pendente${laudoPend !== 1 ? 's' : ''}`,
        icone: `<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><polyline points="9 15 11 17 15 13"/></svg>`,
        bg: '#fdf4ff', border: '#e9d5ff', iconBg: '#9333ea', valorColor: '#581c87',
        barra: null,
        filtro: 'laudo=valido'
      },
    ];

    // Cards de deficiência
    const defsKpis = [
      { label: 'Física / Motora',     valor: fisico,   cor: '#ea580c', bg: '#fff7ed', border: '#fed7aa', filtro: 'deficiencia=Física/Motora' },
      { label: 'Visual',              valor: visual,   cor: '#2563eb', bg: '#eff6ff', border: '#bfdbfe', filtro: 'deficiencia=Visual'         },
      { label: 'Auditiva',            valor: auditiva, cor: '#16a34a', bg: '#f0fdf4', border: '#bbf7d0', filtro: 'deficiencia=Auditiva'       },
      { label: 'Intelectual / TEA',   valor: tea,      cor: '#7c3aed', bg: '#faf5ff', border: '#e9d5ff', filtro: 'deficiencia=Intelectual/TEA'},
      { label: 'Mob. Reduzida',       valor: mob,      cor: '#b45309', bg: '#fef9c3', border: '#fde68a', filtro: 'deficiencia=Mobilidade Reduzida'},
    ];

    elKpis.innerHTML = `
      <!-- Linha 1: 4 KPIs principais -->
      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:14px;">
        ${kpis.map(k => `
          <div
            role="${k.filtro ? 'button' : 'presentation'}"
            tabindex="${k.filtro ? '0' : '-1'}"
            ${k.filtro ? `onclick="aplicarFiltroRapido('${k.filtro}')" onkeydown="if(event.key==='Enter')aplicarFiltroRapido('${k.filtro}')"` : ''}
            aria-label="${k.filtro ? `Filtrar por ${k.label}` : k.label}"
            style="
              background:${k.bg};
              border:1.5px solid ${k.border};
              border-radius:16px;
              padding:18px 20px;
              display:flex;
              flex-direction:column;
              gap:10px;
              cursor:${k.filtro ? 'pointer' : 'default'};
              transition:all .18s ease;
              box-shadow:0 1px 4px rgba(0,0,0,.04);
            "
            onmouseenter="${k.filtro ? "this.style.transform='translateY(-2px)';this.style.boxShadow='0 6px 18px -4px rgba(0,0,0,.12)';" : ''}"
            onmouseleave="${k.filtro ? "this.style.transform='';this.style.boxShadow='0 1px 4px rgba(0,0,0,.04)';" : ''}"
          >
            <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:8px;">
              <div style="width:40px;height:40px;border-radius:12px;background:${k.iconBg};display:flex;align-items:center;justify-content:center;flex-shrink:0;color:#fff;">
                ${k.icone}
              </div>
              ${k.filtro ? `<span style="font-size:10px;font-weight:700;color:${k.iconBg};background:${k.bg};border:1px solid ${k.border};border-radius:9999px;padding:2px 8px;letter-spacing:.3px;">Ver</span>` : ''}
            </div>
            <div>
              <div style="font-size:32px;font-weight:800;color:${k.valorColor};line-height:1;">${k.valor}</div>
              <div style="font-size:13px;font-weight:700;color:${k.valorColor};margin-top:3px;opacity:.85;">${k.label}</div>
              <div style="font-size:11.5px;color:#78716c;margin-top:2px;">${k.sub}</div>
            </div>
            ${k.barra !== null ? `
              <div style="height:5px;background:rgba(0,0,0,.07);border-radius:9999px;overflow:hidden;" role="progressbar" aria-valuenow="${k.barra}" aria-valuemin="0" aria-valuemax="100" aria-label="${k.label}: ${k.barra}%">
                <div style="height:100%;width:${k.barra}%;background:${k.barraColor};border-radius:9999px;transition:width .6s ease;"></div>
              </div>` : ''}
          </div>
        `).join('')}
      </div>

      <!-- Linha 2: 5 chips de deficiência -->
      <div style="display:flex;flex-wrap:wrap;gap:10px;align-items:stretch;">
        <span style="font-size:11px;font-weight:700;color:#a8a29e;text-transform:uppercase;letter-spacing:.5px;align-self:center;white-space:nowrap;">Por tipo de deficiência:</span>
        ${defsKpis.map(d => `
          <button
            type="button"
            onclick="aplicarFiltroRapido('${d.filtro}')"
            aria-label="Filtrar: ${d.label} (${d.valor} usuário${d.valor !== 1 ? 's' : ''})"
            style="
              display:inline-flex;
              align-items:center;
              gap:8px;
              background:${d.bg};
              border:1.5px solid ${d.border};
              border-radius:12px;
              padding:10px 16px;
              cursor:pointer;
              transition:all .15s ease;
              font-family:var(--font-main);
            "
            onmouseenter="this.style.transform='translateY(-1px)';this.style.boxShadow='0 4px 12px rgba(0,0,0,.10)';"
            onmouseleave="this.style.transform='';this.style.boxShadow='';"
          >
            <span style="font-size:22px;font-weight:800;color:${d.cor};line-height:1;">${d.valor}</span>
            <span style="display:flex;flex-direction:column;align-items:flex-start;gap:1px;">
              <span style="font-size:12.5px;font-weight:700;color:${d.cor};">${d.label}</span>
              <span style="font-size:10.5px;color:#78716c;">usuário${d.valor !== 1 ? 's' : ''}</span>
            </span>
          </button>
        `).join('')}
      </div>
    `;
  }

  /* Filtro rápido via clique nos KPIs */
  window.aplicarFiltroRapido = function(queryStr) {
    const params = Object.fromEntries(new URLSearchParams(queryStr));

    if (params.status) {
      state.filtros.status = params.status;
      if (elStatus) elStatus.value = params.status;
    }
    if (params.deficiencia) {
      state.filtros.deficiencia = params.deficiencia;
      if (elDeficiencia) elDeficiencia.value = params.deficiencia;
    }
    if (params.laudo) {
      state.filtros.laudo = params.laudo;
      if (elLaudo) elLaudo.value = params.laudo;
    }

    state.paginaAtual = 1;
    render();

    // Scroll suave até o grid
    elGrid?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    window.showToast?.(`Filtro aplicado: ${queryStr.replace('=', ': ')}`);
  };

  /* ──────────────────────────────────────────────────────────────────────────
     FILTRO + BUSCA + ORDENAÇÃO
  ────────────────────────────────────────────────────────────────────────── */
  function filtrar() {
    const q = state.busca.toLowerCase().trim();

    return db.filter(u => {
      // Busca textual
      if (q) {
        const campo = `${u.nome} ${u.email} ${u.cpf} ${u.id}`.toLowerCase();
        if (!campo.includes(q)) return false;
      }

      const f = state.filtros;

      if (f.deficiencia && u.deficiencia !== f.deficiencia)      return false;
      if (f.genero      && u.genero      !== f.genero)           return false;
      if (f.status      && u.status      !== f.status)           return false;

      if (f.laudo === 'valido'   && !u.laudoValido)              return false;
      if (f.laudo === 'invalido' && u.laudoValido)               return false;

      if (f.idadeMin !== '' && u.idade < Number(f.idadeMin))     return false;
      if (f.idadeMax !== '' && u.idade > Number(f.idadeMax))     return false;

      if (f.cidade && u.bairro !== f.cidade)                     return false;

      return true;
    });
  }

  function ordenar(lista) {
    const copia = [...lista];
    switch (state.ordenar) {
      case 'nome-az':          return copia.sort((a, b) => a.nome.localeCompare(b.nome));
      case 'nome-za':          return copia.sort((a, b) => b.nome.localeCompare(a.nome));
      case 'idade-asc':        return copia.sort((a, b) => a.idade - b.idade);
      case 'idade-desc':       return copia.sort((a, b) => b.idade - a.idade);
      case 'solicitacoes-desc':return copia.sort((a, b) => b.solicitacoes - a.solicitacoes);
      case 'membro-recente':   return copia.sort((a, b) => b.id.localeCompare(a.id));
      default:                 return copia;
    }
  }

  /* ──────────────────────────────────────────────────────────────────────────
     HELPERS DE RENDERIZAÇÃO
  ────────────────────────────────────────────────────────────────────────── */
  function classeDeficiencia(def) {
    const mapa = {
      'Visual':             'visual',
      'Auditiva':           'auditiva',
      'Intelectual/TEA':    'tea',
      'Mobilidade Reduzida':'mobilidade',
      'Física/Motora':      ''
    };
    return mapa[def] || '';
  }

  function classeStatus(status) {
    const mapa = { 'Ativo': 'ativo', 'Pendente': 'pendente', 'Bloqueado': 'bloqueado' };
    return mapa[status] || 'ativo';
  }

  function initials(nome) {
    return nome.split(' ').slice(0, 2).map(p => p[0]).join('').toUpperCase();
  }

  function renderCard(u) {
    const statusCls  = classeStatus(u.status.toLowerCase());
    const defCls     = classeDeficiencia(u.deficiencia);
    const solAprov   = u.solicitacoesAprovadas || 0;
    const solTotal   = u.solicitacoes || 0;

    return `
      <article
        class="usuario-card status-${statusCls}"
        role="article"
        tabindex="0"
        data-id="${u.id}"
        aria-label="Usuário ${u.nomeExibicao}, ${u.deficiencia}, ${u.status}"
        onclick="irParaDetalhe('${u.id}')"
        onkeydown="if(event.key==='Enter'||event.key===' ')irParaDetalhe('${u.id}')"
      >
        <!-- Topo -->
        <div class="card-usuario-top">
          <div class="card-avatar-wrap">
            <img
              src="${u.avatar}"
              alt="Foto de ${u.nomeExibicao}"
              class="card-avatar-img"
              onerror="this.style.display='none';this.nextElementSibling.style.display='flex';"
            >
            <div class="card-avatar-initials" style="display:none;">${initials(u.nome)}</div>
            <span class="card-status-dot ${statusCls}" title="${u.status}"></span>
          </div>

          <div class="card-usuario-info">
            <div class="card-usuario-nome">${u.nomeExibicao}</div>
            <div class="card-usuario-id">${u.id} &bull; ${u.idade} anos &bull; ${u.genero}</div>
          </div>

          <span class="card-status-pill ${statusCls}">${u.status}</span>
        </div>

        <!-- Detalhes -->
        <div class="card-usuario-detalhes">
          <div class="card-detalhe-row">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
              <polyline points="22,6 12,13 2,6"/>
            </svg>
            <span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${u.email}</span>
          </div>
          <div class="card-detalhe-row">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
              <circle cx="12" cy="10" r="3"/>
            </svg>
            <span>${u.bairro}, ${u.cidade}</span>
          </div>
          <div class="card-detalhe-row">
            <span class="card-deficiencia-tag ${defCls}">${u.deficiencia}</span>
            ${!u.laudoValido ? `<span style="font-size:11px;color:#b91c1c;font-weight:700;">⚠ Laudo pendente</span>` : ''}
          </div>
        </div>

        <!-- Rodapé -->
        <div class="card-usuario-footer">
          <span class="card-solicitacoes-info">
            <strong>${solTotal}</strong> solicitaç${solTotal === 1 ? 'ão' : 'ões'}
            &bull; <strong>${solAprov}</strong> aprovada${solAprov !== 1 ? 's' : ''}
          </span>
          <span class="card-ver-link" aria-hidden="true">
            Ver perfil
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
              <polyline points="9 18 15 12 9 6"/>
            </svg>
          </span>
        </div>
      </article>
    `;
  }

  /* ──────────────────────────────────────────────────────────────────────────
     CHIPS DE FILTROS ATIVOS
  ────────────────────────────────────────────────────────────────────────── */
  const FILTRO_LABELS = {
    deficiencia: 'Deficiência',
    genero:      'Gênero',
    status:      'Status',
    laudo:       { valido: 'Laudo válido', invalido: 'Laudo pendente/vencido' },
    cidade:      'Bairro'
  };

  function renderChips() {
    if (!elChipsAtivos) return;
    const chips = [];
    const f = state.filtros;

    if (f.deficiencia) chips.push({ key: 'deficiencia', label: `Deficiência: ${f.deficiencia}` });
    if (f.genero)      chips.push({ key: 'genero',      label: `Gênero: ${f.genero}` });
    if (f.status)      chips.push({ key: 'status',      label: `Status: ${f.status}` });
    if (f.laudo)       chips.push({ key: 'laudo',       label: FILTRO_LABELS.laudo[f.laudo] });
    if (f.cidade)      chips.push({ key: 'cidade',      label: `Bairro: ${f.cidade}` });
    if (f.idadeMin || f.idadeMax) {
      const min = f.idadeMin || '0';
      const max = f.idadeMax || '∞';
      chips.push({ key: 'idade', label: `Idade: ${min}–${max} anos` });
    }
    if (state.busca) chips.push({ key: 'busca', label: `Busca: "${state.busca}"` });

    elChipsAtivos.innerHTML = chips.map(c => `
      <div class="filtro-chip-ativo" role="listitem">
        <span>${c.label}</span>
        <button
          type="button"
          class="btn-remover-chip"
          aria-label="Remover filtro ${c.label}"
          data-chip="${c.key}"
        >&times;</button>
      </div>
    `).join('');

    elChipsAtivos.querySelectorAll('.btn-remover-chip').forEach(btn => {
      btn.addEventListener('click', () => {
        const key = btn.getAttribute('data-chip');
        if (key === 'busca') {
          state.busca = '';
          if (elBusca) elBusca.value = '';
        } else if (key === 'idade') {
          state.filtros.idadeMin = '';
          state.filtros.idadeMax = '';
          if (elIdadeMin) elIdadeMin.value = '';
          if (elIdadeMax) elIdadeMax.value = '';
        } else {
          state.filtros[key] = '';
          const el = document.getElementById(`filtro-${key}`);
          if (el) el.value = '';
        }
        state.paginaAtual = 1;
        render();
      });
    });
  }

  /* ──────────────────────────────────────────────────────────────────────────
     PAGINAÇÃO
  ────────────────────────────────────────────────────────────────────────── */
  function renderPaginacao(total) {
    if (!elPaginacao) return;
    const totalPag = Math.ceil(total / state.porPagina);
    if (totalPag <= 1) { elPaginacao.innerHTML = ''; return; }

    const ini = (state.paginaAtual - 1) * state.porPagina + 1;
    const fim = Math.min(state.paginaAtual * state.porPagina, total);

    // Constrói conjunto de páginas a mostrar
    const paginas = new Set([1, totalPag, state.paginaAtual]);
    if (state.paginaAtual > 1) paginas.add(state.paginaAtual - 1);
    if (state.paginaAtual < totalPag) paginas.add(state.paginaAtual + 1);
    const sorted = Array.from(paginas).sort((a, b) => a - b);

    let btns = '';
    let prev = 0;
    sorted.forEach(p => {
      if (p - prev > 1) btns += `<span class="btn-pagina" style="pointer-events:none;border:none;background:none;">…</span>`;
      btns += `<button type="button" class="btn-pagina${p === state.paginaAtual ? ' ativa' : ''}" data-pag="${p}" aria-label="Página ${p}" ${p === state.paginaAtual ? 'aria-current="page"' : ''}>${p}</button>`;
      prev = p;
    });

    elPaginacao.innerHTML = `
      <span class="paginacao-info">Exibindo <strong>${ini}–${fim}</strong> de <strong>${total}</strong> usuários</span>
      <div class="paginacao-btns">
        <button type="button" class="btn-pagina" id="btn-pag-ant" aria-label="Página anterior" ${state.paginaAtual === 1 ? 'disabled' : ''}>
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="15 18 9 12 15 6"/></svg>
        </button>
        ${btns}
        <button type="button" class="btn-pagina" id="btn-pag-prox" aria-label="Próxima página" ${state.paginaAtual === totalPag ? 'disabled' : ''}>
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="9 18 15 12 9 6"/></svg>
        </button>
      </div>
    `;

    document.getElementById('btn-pag-ant')?.addEventListener('click', () => {
      if (state.paginaAtual > 1) { state.paginaAtual--; render(); scrollToGrid(); }
    });
    document.getElementById('btn-pag-prox')?.addEventListener('click', () => {
      if (state.paginaAtual < totalPag) { state.paginaAtual++; render(); scrollToGrid(); }
    });
    elPaginacao.querySelectorAll('.btn-pagina[data-pag]').forEach(btn => {
      btn.addEventListener('click', () => {
        state.paginaAtual = Number(btn.getAttribute('data-pag'));
        render();
        scrollToGrid();
      });
    });
  }

  function scrollToGrid() {
    elGrid?.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }

  /* ──────────────────────────────────────────────────────────────────────────
     RENDER PRINCIPAL
  ────────────────────────────────────────────────────────────────────────── */
  function render() {
    const filtrados  = filtrar();
    const ordenados  = ordenar(filtrados);
    const total      = ordenados.length;
    const inicio     = (state.paginaAtual - 1) * state.porPagina;
    const pagina     = ordenados.slice(inicio, inicio + state.porPagina);

    // Contagem
    if (elCount) {
      const totalDB = db.length;
      elCount.innerHTML = total === totalDB
        ? `<strong>${total}</strong> usuários cadastrados`
        : `<strong>${total}</strong> resultado${total !== 1 ? 's' : ''} de <strong>${totalDB}</strong> usuários`;
    }

    // Grid
    if (elGrid) {
      if (pagina.length === 0) {
        elGrid.innerHTML = `
          <div class="usuarios-empty-state">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
              <line x1="8" y1="8" x2="14" y2="14"/>
            </svg>
            <h3>Nenhum usuário encontrado</h3>
            <p>Tente ajustar os filtros ou a busca para encontrar o cidadão desejado.</p>
          </div>`;
      } else {
        elGrid.innerHTML = pagina.map(renderCard).join('');
      }
    }

    renderPaginacao(total);
    renderChips();
  }

  /* ──────────────────────────────────────────────────────────────────────────
     NAVEGAÇÃO PARA DETALHE
  ────────────────────────────────────────────────────────────────────────── */
  window.irParaDetalhe = function(id) {
    window.location.href = `perfil-cidadao-detalhe.php?id=${encodeURIComponent(id)}`;
  };

  /* ──────────────────────────────────────────────────────────────────────────
     EXPORTAR CSV
  ────────────────────────────────────────────────────────────────────────── */
  function exportarCSV() {
    const lista = ordenar(filtrar());
    const cabecalho = ['ID','Nome','E-mail','Telefone','Idade','Gênero','Deficiência','Status','Laudo','Solicitações','Membro desde'];
    const linhas = lista.map(u => [
      u.id, u.nome, u.email, u.telefone, u.idade, u.genero,
      u.deficiencia, u.status,
      u.laudoValido ? 'Válido' : 'Pendente/Vencido',
      u.solicitacoes, u.membroDesde
    ].map(v => `"${String(v).replace(/"/g, '""')}"`).join(','));

    const csv  = [cabecalho.join(','), ...linhas].join('\n');
    const blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' });
    const url  = URL.createObjectURL(blob);
    const a    = Object.assign(document.createElement('a'), { href: url, download: 'Usuarios_PasseLivre.csv' });
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
    window.showToast?.('Lista exportada: Usuarios_PasseLivre.csv');
  }

  /* ──────────────────────────────────────────────────────────────────────────
     EVENTOS
  ────────────────────────────────────────────────────────────────────────── */
  // Debounce para busca
  let debounceTimer;
  function onBusca(val) {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
      state.busca = val.trim();
      state.paginaAtual = 1;
      // Sincroniza os dois campos de busca
      if (elBusca        && elBusca.value        !== val) elBusca.value        = val;
      if (elHeaderBusca  && elHeaderBusca.value  !== val) elHeaderBusca.value  = val;
      render();
    }, 250);
  }

  elBusca?.addEventListener('input', e => onBusca(e.target.value));
  elHeaderBusca?.addEventListener('input', e => onBusca(e.target.value));

  // Filtros de select
  [
    [elDeficiencia, 'deficiencia'],
    [elGenero,      'genero'],
    [elStatus,      'status'],
    [elLaudo,       'laudo'],
    [elCidade,      'cidade'],
  ].forEach(([el, key]) => {
    el?.addEventListener('change', () => {
      state.filtros[key] = el.value;
      state.paginaAtual  = 1;
      render();
    });
  });

  // Faixa etária
  function onIdade() {
    state.filtros.idadeMin = elIdadeMin?.value || '';
    state.filtros.idadeMax = elIdadeMax?.value || '';
    state.paginaAtual = 1;
    render();
  }
  elIdadeMin?.addEventListener('change', onIdade);
  elIdadeMax?.addEventListener('change', onIdade);

  // Ordenação
  elOrdenar?.addEventListener('change', () => {
    state.ordenar     = elOrdenar.value;
    state.paginaAtual = 1;
    render();
  });

  // Limpar filtros
  elBtnLimpar?.addEventListener('click', () => {
    state.busca = '';
    state.filtros = { deficiencia:'', genero:'', status:'', laudo:'', idadeMin:'', idadeMax:'', cidade:'' };
    state.paginaAtual = 1;

    if (elBusca)       elBusca.value       = '';
    if (elHeaderBusca) elHeaderBusca.value = '';
    if (elDeficiencia) elDeficiencia.value = '';
    if (elGenero)      elGenero.value      = '';
    if (elStatus)      elStatus.value      = '';
    if (elLaudo)       elLaudo.value       = '';
    if (elIdadeMin)    elIdadeMin.value    = '';
    if (elIdadeMax)    elIdadeMax.value    = '';
    if (elCidade)      elCidade.value      = '';
    if (elOrdenar)     elOrdenar.value     = 'nome-az';

    render();
    window.showToast?.('Filtros removidos — exibindo todos os usuários.');
  });

  // Exportar
  elBtnExportar?.addEventListener('click', exportarCSV);

  /* ──────────────────────────────────────────────────────────────────────────
     PARÂMETRO DE URL: filtro de deficiência vindo de outra página
  ────────────────────────────────────────────────────────────────────────── */
  const urlParams = new URLSearchParams(window.location.search);
  const defParam  = urlParams.get('deficiencia');
  if (defParam && elDeficiencia) {
    elDeficiencia.value       = defParam;
    state.filtros.deficiencia = defParam;
  }

  /* ──────────────────────────────────────────────────────────────────────────
     CARREGAMENTO DO BANCO DE DADOS (MYSQL / API)
  ────────────────────────────────────────────────────────────────────────── */
  async function carregarUsuarios() {
    try {
      let data = null;
      if (window.PasseLivreAuth && window.PasseLivreAuth.apiFetch) {
        data = await window.PasseLivreAuth.apiFetch('api/users.php?action=list');
      } else {
        const token = localStorage.getItem('passeLivreToken') || '';
        const res = await fetch('api/users.php?action=list', {
          headers: token ? { 'X-Session-Token': token } : {}
        });
        if (res.ok) data = await res.json();
      }

      if (data && data.success && Array.isArray(data.cidadaos) && data.cidadaos.length > 0) {
        db = data.cidadaos.map(u => {
          const pcd = Array.isArray(u.pcd_categories) ? u.pcd_categories : [];
          const st = (u.status || 'ativo').toLowerCase();
          const statusTitle = st === 'ativo' ? 'Ativo' : (st === 'bloqueado' ? 'Bloqueado' : 'Pendente');
          return {
            id: u.user_code || ('USR-' + u.id),
            nome: u.name,
            nomeExibicao: u.name,
            avatar: u.avatar || 'assets/images/avatar.jpg',
            email: u.email,
            telefone: u.phone || '—',
            cpf: u.cpf || '***.***.***-**',
            nascimento: u.birth_date,
            idade: u.birth_date ? (new Date().getFullYear() - new Date(u.birth_date).getFullYear()) : 32,
            genero: u.gender || 'Não informado',
            bairro: u.bairro || 'Centro',
            cidade: u.city || 'Suzano - SP',
            deficiencia: pcd.length > 0 ? pcd[0] : 'Não informada',
            laudoValido: true,
            status: statusTitle,
            membroDesde: u.created_at ? u.created_at.slice(0, 10).split('-').reverse().join('/') : '—',
            emailVerificado: u.email_verified == 1,
            solicitacoes: 0,
            solicitacoesAprovadas: 0,
            sqlId: u.id,
            pcdCategories: pcd
          };
        });
        window.USUARIOS_DB = db;
        renderKpis();
        render();
        console.log(`[perfil-gestor MySQL] Lista carregada com ${db.length} cidadãos da API.`);
        return;
      }
    } catch (e) {
      console.warn('[perfil-gestor] Erro ao carregar cidadãos via API:', e);
    }

    if (window.buildUsuariosDB) {
      db = window.buildUsuariosDB();
      renderKpis();
      render();
    }
  }

  window.renderPage = () => { renderKpis(); render(); };
  window.carregarUsuariosGestor = carregarUsuarios;

  /* ──────────────────────────────────────────────────────────────────────────
     INIT
  ────────────────────────────────────────────────────────────────────────── */
  renderKpis();
  render();
  carregarUsuarios();

  if (window.PasseLivreDBReady) {
    window.PasseLivreDBReady.then(() => {
      carregarUsuarios();
    });
  }
});

