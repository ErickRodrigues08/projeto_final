/**
 * Passe Livre Suzano — Detalhe Individual do Cidadão
 * perfil-cidadao-detalhe.php  ·  js/perfil-cidadao-detalhe.js
 *
 * Lê ?id=USR-XXX da URL, busca o usuário em USUARIOS_DB e preenche toda a página.
 */

document.addEventListener('DOMContentLoaded', async () => {

  /* ──────────────────────────────────────────────────────────────────────────
     BUSCA DO USUÁRIO (MYSQL / CACHE)
  ────────────────────────────────────────────────────────────────────────── */
  const params = new URLSearchParams(window.location.search);
  const userId = params.get('id');

  if (window.PasseLivreDBReady) {
    try { await window.PasseLivreDBReady; } catch (_) {}
  }

  let db = window.USUARIOS_DB || [];
  let u  = db.find(x => x.id === userId || String(x.sqlId) === String(userId));

  // Se não estiver no cache da página, busca no MySQL via API
  if (!u && userId) {
    try {
      let data = null;
      if (window.PasseLivreAuth && window.PasseLivreAuth.apiFetch) {
        data = await window.PasseLivreAuth.apiFetch(`api/users.php?action=detail&id=${encodeURIComponent(userId)}`);
      } else {
        const token = localStorage.getItem('passeLivreToken') || '';
        const res = await fetch(`api/users.php?action=detail&id=${encodeURIComponent(userId)}`, {
          headers: token ? { 'X-Session-Token': token } : {}
        });
        if (res.ok) data = await res.json();
      }

      if (data && data.success && data.cidadao) {
        const c = data.cidadao;
        const pcd = Array.isArray(c.pcd_categories) ? c.pcd_categories : [];
        const st = (c.status || 'ativo').toLowerCase();
        const statusTitle = st === 'ativo' ? 'Ativo' : (st === 'bloqueado' ? 'Bloqueado' : 'Pendente');

        const listaSols = (data.solicitacoes || []).map(s => ({
          id: s.protocol || ('SOL-' + s.id),
          titulo: s.title || 'Solicitação de Acessibilidade',
          data: s.created_at ? s.created_at.slice(0, 10).split('-').reverse().join('/') : 'Recente',
          status: s.status === 'approved' ? 'Aprovada' : (s.status === 'completed' ? 'Concluído' : (s.status === 'rejected' ? 'Rejeitada' : 'Em Análise')),
          local: s.address || s.region_name || 'Suzano - SP'
        }));

        const listAudit = (data.auditorias || []).map(a => ({
          titulo: a.action_title || a.action || 'Ação Registrada',
          data: a.created_at ? a.created_at.slice(0, 16).replace('T', ' ') : 'Recente',
          extra: a.action_desc || a.details || 'Registrado no MySQL',
          destaque: (a.risk_level === 'alto' || a.risk_level === 'medio')
        }));

        u = {
          id: c.user_code || ('USR-' + c.id),
          nome: c.name,
          nomeExibicao: c.name,
          avatar: c.avatar || 'assets/images/avatar.jpg',
          email: c.email,
          telefone: c.phone || '—',
          cpf: c.cpf || '***.***.***-**',
          rg: '—',
          nascimento: c.birth_date,
          idade: c.birth_date ? (new Date().getFullYear() - new Date(c.birth_date).getFullYear()) : 32,
          genero: c.gender || 'Não informado',
          estadoCivil: 'Solteiro(a)',
          endereco: c.address || '—',
          bairro: c.bairro || 'Centro',
          cidade: c.city || 'Suzano - SP',
          cep: '08674-000',
          deficiencia: pcd.length > 0 ? pcd[0] : 'Não informada',
          cid: '—',
          locomocao: pcd.some(p => (p||'').toLowerCase().includes('física') || (p||'').toLowerCase().includes('motora')) ? 'Cadeira de Rodas' : '—',
          laudoValido: true,
          status: statusTitle,
          membroDesde: c.created_at ? c.created_at.slice(0,10).split('-').reverse().join('/') : '—',
          ultimoAcesso: c.updated_at ? c.updated_at.slice(0,16).replace('T',' ') : '—',
          emailVerificado: c.email_verified == 1,
          telefoneVerificado: false,
          solicitacoes: listaSols.length,
          solicitacoesAprovadas: listaSols.filter(s => s.status === 'Aprovada' || s.status === 'Concluído').length,
          solicitacoesAnalise: listaSols.filter(s => s.status === 'Em Análise').length,
          parecer: c.description || 'Cidadão cadastrado no sistema municipal Passe Livre Suzano.',
          sqlId: c.id,
          pcdCategories: pcd,
          listaSolicitacoes: listaSols,
          auditoria: listAudit
        };
      }
    } catch (err) {
      console.warn('[perfil-cidadao-detalhe] Falha ao carregar do MySQL:', err);
    }
  }

  // Fallback PLDB se ainda não encontrou
  if (!u && window.PLDB && userId) {
    try {
      const dbUser = window.PLDB.users.findByCode(userId) || window.PLDB.users.findById(userId);
      if (dbUser) {
        let pcd = [];
        try { pcd = JSON.parse(dbUser.pcd_categories || '[]'); } catch (_) {}
        const st = (dbUser.status || 'ativo').toLowerCase();
        u = {
          id: dbUser.user_code || ('USR-' + dbUser.id),
          nome: dbUser.name,
          nomeExibicao: dbUser.name,
          avatar: dbUser.avatar || 'assets/images/avatar.jpg',
          email: dbUser.email,
          telefone: dbUser.phone || '—',
          cpf: dbUser.cpf || '***.***.***-**',
          rg: '—',
          nascimento: dbUser.birth_date,
          idade: dbUser.birth_date ? (new Date().getFullYear() - new Date(dbUser.birth_date).getFullYear()) : 30,
          genero: dbUser.gender || 'Não informado',
          estadoCivil: 'Solteiro(a)',
          endereco: dbUser.address || '—',
          bairro: dbUser.bairro || 'Centro',
          cidade: dbUser.city || 'Suzano - SP',
          cep: '08674-000',
          deficiencia: pcd.length > 0 ? pcd[0] : 'Não informada',
          cid: '—',
          locomocao: '—',
          laudoValido: true,
          status: st === 'ativo' ? 'Ativo' : (st === 'bloqueado' ? 'Bloqueado' : 'Pendente'),
          membroDesde: dbUser.created_at ? dbUser.created_at.slice(0,10).split('-').reverse().join('/') : '—',
          ultimoAcesso: dbUser.updated_at ? dbUser.updated_at.slice(0,16).replace('T',' ') : '—',
          emailVerificado: dbUser.email_verified == 1,
          telefoneVerificado: false,
          solicitacoes: 0,
          solicitacoesAprovadas: 0,
          solicitacoesAnalise: 0,
          parecer: 'Cidadão cadastrado na base municipal.',
          sqlId: dbUser.id,
          pcdCategories: pcd,
          listaSolicitacoes: [],
          auditoria: []
        };
      }
    } catch (_) {}
  }

  if (!u) {
    document.getElementById('main-content').innerHTML = `
      <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;padding:80px 20px;text-align:center;gap:16px;">
        <svg width="56" height="56" viewBox="0 0 24 24" fill="none" stroke="#d6cbbe" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
        </svg>
        <h2 style="font-size:18px;font-weight:700;color:#1c1917;">Usuário não encontrado</h2>
        <p style="font-size:13px;color:#78716c;">O ID <strong>${userId || '—'}</strong> não corresponde a nenhum usuário cadastrado no banco MySQL.</p>
        <a href="perfil-gestor.php" style="display:inline-flex;align-items:center;gap:6px;font-size:13px;font-weight:700;color:#b45309;text-decoration:none;margin-top:8px;">
          ← Voltar para a lista
        </a>
      </div>`;
    return;
  }

  u.listaSolicitacoes = u.listaSolicitacoes || [];
  u.auditoria         = u.auditoria || [];
  u.rg                = u.rg || '—';
  u.estadoCivil       = u.estadoCivil || 'Não informado';

  /* ──────────────────────────────────────────────────────────────────────────
     TÍTULO E BREADCRUMB
  ────────────────────────────────────────────────────────────────────────── */
  document.title = `${u.nomeExibicao} — Painel do Gestor | Passe Livre`;
  const elBreadNome = document.getElementById('breadcrumb-nome');
  const elPageTitle = document.getElementById('page-title');
  if (elBreadNome) elBreadNome.textContent = u.nomeExibicao;
  if (elPageTitle) elPageTitle.textContent = u.nomeExibicao;

  /* ──────────────────────────────────────────────────────────────────────────
     HELPERS
  ────────────────────────────────────────────────────────────────────────── */
  function esc(str) {
    if (!str) return '—';
    return String(str)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;')
      .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

  function statusBadge(status) {
    const mapa = {
      'Ativo':     ['#ecfdf5','#065f46','#a7f3d0'],
      'Pendente':  ['#fffbeb','#92400e','#fde68a'],
      'Bloqueado': ['#fef2f2','#991b1b','#fecaca'],
    };
    const [bg, txt, border] = mapa[status] || mapa['Pendente'];
    return `<span style="display:inline-flex;align-items:center;gap:4px;background:${bg};color:${txt};border:1px solid ${border};border-radius:9999px;padding:3px 10px;font-size:11px;font-weight:700;">${status}</span>`;
  }

  function badgeLaudo() {
    return u.laudoValido
      ? `<span style="display:inline-flex;align-items:center;gap:4px;background:#ecfdf5;color:#065f46;border:1px solid #a7f3d0;border-radius:9999px;padding:2px 9px;font-size:11px;font-weight:700;">
           <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>Laudo válido
         </span>`
      : `<span style="display:inline-flex;align-items:center;gap:4px;background:#fffbeb;color:#92400e;border:1px solid #fde68a;border-radius:9999px;padding:2px 9px;font-size:11px;font-weight:700;">
           ⚠ Laudo pendente/vencido
         </span>`;
  }

  /* ──────────────────────────────────────────────────────────────────────────
     HERO CARD
  ────────────────────────────────────────────────────────────────────────── */
  const heroEl = document.getElementById('hero-card');
  if (heroEl) {
    heroEl.innerHTML = `
      <div class="user-hero-left">
        <div class="hero-avatar-wrap">
          <img src="${esc(u.avatar)}" alt="Foto de ${esc(u.nomeExibicao)}" class="hero-avatar-img"
               onerror="this.src='assets/images/avatar.jpg'">
          <div class="hero-avatar-check-badge" title="${u.status}">
            <img src="assets/icons/check.svg" alt="">
          </div>
        </div>
        <div class="hero-user-details">
          <h2 class="hero-user-name">${esc(u.nome)}</h2>
          <div class="hero-tags-row">
            ${statusBadge(u.status)}
            <span class="status-pcd-pill">
              <img src="assets/icons/pcd.svg" alt="" style="width:12px;height:12px;">
              ${esc(u.deficiencia)}
            </span>
            <span class="member-since-text">Membro desde ${esc(u.membroDesde)}</span>
          </div>
        </div>
      </div>

      <div class="hero-stats-group">
        <div class="stat-metric-card">
          <div class="stat-icon-circle">
            <img src="assets/icons/clipboard-check.svg" alt="">
          </div>
          <div class="stat-metric-texts">
            <span class="stat-metric-val">${u.solicitacoes} solicitaç${u.solicitacoes === 1 ? 'ão' : 'ões'}</span>
            <span class="stat-metric-sub">${u.solicitacoesAprovadas} Aprovada${u.solicitacoesAprovadas !== 1 ? 's' : ''} &bull; ${u.solicitacoesAnalise} Em Análise</span>
          </div>
        </div>
        <div class="stat-metric-card">
          <div class="stat-icon-circle">
            <img src="assets/icons/pin.svg" alt="">
          </div>
          <div class="stat-metric-texts">
            <span class="stat-metric-val">${esc(u.bairro)}</span>
            <span class="stat-metric-sub">${esc(u.cidade)}</span>
          </div>
        </div>
        <div class="stat-metric-card">
          <div class="stat-icon-circle">
            <img src="assets/icons/clock.svg" alt="">
          </div>
          <div class="stat-metric-texts">
            <span class="stat-metric-val">${esc(u.ultimoAcesso ? u.ultimoAcesso.split(' ')[0] : 'Hoje')}</span>
            <span class="stat-metric-sub">Último acesso</span>
          </div>
        </div>
      </div>
    `;
  }

  /* ──────────────────────────────────────────────────────────────────────────
     ABA: INFORMAÇÕES PESSOAIS
  ────────────────────────────────────────────────────────────────────────── */
  const gridPessoal = document.getElementById('grid-pessoal');
  if (gridPessoal) {
    gridPessoal.innerHTML = `
      <!-- Coluna 1: Dados cadastrais + Acessibilidade médica -->
      <div style="display:flex;flex-direction:column;gap:20px;">

        <section class="gestor-section-card" aria-labelledby="heading-cadastrais">
          <div class="section-card-header">
            <div class="section-card-header-left">
              <img src="assets/icons/user.svg" alt="">
              <h3 class="section-title" id="heading-cadastrais">Dados Cadastrais</h3>
            </div>
            <div style="display:flex;gap:8px;align-items:center;">
              ${statusBadge(u.status)}
            </div>
          </div>

          <div class="cadastrais-data-grid">
            <div class="data-item-col">
              <span class="data-item-label">Nome Completo</span>
              <span class="data-item-value">${esc(u.nome)}</span>
            </div>
            <div class="data-item-col">
              <span class="data-item-label">Data de Nascimento</span>
              <span class="data-item-value">
                ${u.nascimento ? new Date(u.nascimento + (u.nascimento.includes('T') ? '' : 'T12:00:00')).toLocaleDateString('pt-BR') : 'Não informada'}
                <span style="font-size:11px;font-weight:normal;color:#78716c;">${u.idade ? `(${u.idade} anos)` : ''}</span>
              </span>
            </div>
            <div class="data-item-col">
              <span class="data-item-label">CPF</span>
              <span class="data-item-value">${esc(u.cpf)}</span>
            </div>
            <div class="data-item-col">
              <span class="data-item-label">RG</span>
              <span class="data-item-value">${esc(u.rg)}</span>
            </div>
            <div class="data-item-col">
              <span class="data-item-label">Gênero</span>
              <span class="data-item-value">${esc(u.genero)}</span>
            </div>
            <div class="data-item-col">
              <span class="data-item-label">Estado Civil</span>
              <span class="data-item-value">${esc(u.estadoCivil)}</span>
            </div>
          </div>

          <div class="contato-sub-block">
            <span class="contato-title-label">Contato e Localização</span>
            <div class="contato-row-item">
              <span style="color:#78716c;">E-mail</span>
              <div style="display:flex;align-items:center;gap:8px;">
                <span style="font-weight:600;">${esc(u.email)}</span>
                ${u.emailVerificado
                  ? `<span class="contato-verified-pill"><img src="assets/icons/check.svg" style="width:10px;height:10px;" alt=""> Verificado</span>`
                  : `<span style="font-size:10.5px;color:#b91c1c;font-weight:700;">Não verificado</span>`}
              </div>
            </div>
            <div class="contato-row-item">
              <span style="color:#78716c;">Celular / WhatsApp</span>
              <div style="display:flex;align-items:center;gap:8px;">
                <span style="font-weight:600;">${esc(u.telefone)}</span>
                ${u.telefoneVerificado
                  ? `<span class="contato-verified-pill"><img src="assets/icons/check.svg" style="width:10px;height:10px;" alt=""> Verificado</span>`
                  : `<span style="font-size:10.5px;color:#b91c1c;font-weight:700;">Não verificado</span>`}
              </div>
            </div>
            <div class="address-box-detailed">
              <img src="assets/icons/pin.svg" alt="" style="width:16px;height:16px;margin-top:1px;">
              <div>
                <div style="font-size:11px;color:#78716c;">Endereço Residencial</div>
                <div style="font-weight:700;color:#1c1917;margin:2px 0;">${esc(u.endereco)}</div>
                <div style="font-size:11px;color:#78716c;">${esc(u.bairro)}, ${esc(u.cidade)} &bull; CEP ${esc(u.cep)}</div>
              </div>
            </div>
          </div>
        </section>

        <section class="gestor-section-card" aria-labelledby="heading-medica">
          <div class="section-card-header">
            <div class="section-card-header-left">
              <img src="assets/icons/pcd.svg" alt="">
              <h3 class="section-title" id="heading-medica">Acessibilidade &amp; Documentação Médica</h3>
            </div>
            ${badgeLaudo()}
          </div>

          <div class="medical-two-boxes">
            <div class="medical-sub-box">
              <span class="medical-sub-box-title">Classificação CID-10</span>
              <span class="medical-code-highlight">${esc(u.cid)}</span>
            </div>
            <div class="medical-sub-box">
              <span class="medical-sub-box-title">Meio de Locomoção</span>
              <span class="medical-code-highlight" style="color:#c2410c;">${esc(u.locomocao)}</span>
            </div>
          </div>

          ${u.laudoArquivo ? `
          <div class="pdf-document-card">
            <div class="pdf-icon-wrap">
              <img src="assets/icons/pdf.svg" alt="PDF">
            </div>
            <div class="pdf-doc-texts">
              <div class="pdf-doc-filename">${esc(u.laudoArquivo)}</div>
              <div class="pdf-doc-meta">
                Emitido em: <strong>${esc(u.laudoEmissao)}</strong>
                &bull; Validade: <span class="${u.laudoValido ? 'pdf-doc-status-tag' : ''}" style="${u.laudoValido ? '' : 'color:#b91c1c;font-weight:700;'}">${esc(u.laudoValidade)}</span>
              </div>
            </div>
          </div>` : `
          <div style="background:#fef2f2;border:1px dashed #fca5a5;border-radius:12px;padding:12px 16px;font-size:12.5px;color:#b91c1c;font-weight:600;">
            ⚠ Nenhum laudo médico cadastrado. Documentação pendente.
          </div>`}

          ${u.parecer ? `
          <div class="junta-medica-box">
            <img src="assets/icons/shield.svg" alt="" style="width:16px;height:16px;flex-shrink:0;margin-top:1px;">
            <span><strong>Parecer Municipal:</strong> ${esc(u.parecer)}</span>
          </div>` : ''}
        </section>
      </div>

      <!-- Coluna 2: Solicitações + Auditoria preview -->
      <div style="display:flex;flex-direction:column;gap:20px;">

        <section class="gestor-section-card" aria-labelledby="heading-sol-prev">
          <div class="section-card-header">
            <div class="section-card-header-left">
              <img src="assets/icons/file-text.svg" alt="">
              <h3 class="section-title" id="heading-sol-prev">Solicitações Vinculadas</h3>
            </div>
            <span class="section-tag-updated" style="color:#d97706;font-weight:700;">${u.solicitacoes} no total</span>
          </div>

          <div class="requests-list-wrap">
            ${u.listaSolicitacoes.length === 0
              ? `<p style="font-size:13px;color:#78716c;text-align:center;padding:24px 0;">Nenhuma solicitação registrada.</p>`
              : u.listaSolicitacoes.map(s => {
                  const badgeCls = s.status === 'Concluído' ? 'badge-concluido'
                                 : s.status === 'Aprovada'  ? 'badge-concluido'
                                 : 'badge-analise';
                  return `
                  <div class="request-card-item">
                    <div class="request-header-row">
                      <span>${esc(s.id)} &bull; ${esc(s.data)}</span>
                      <span class="request-badge-status ${badgeCls}">${esc(s.status)}</span>
                    </div>
                    <div class="request-title-item">${esc(s.titulo)}</div>
                    <div class="request-location-item">📍 ${esc(s.local)}</div>
                    <a href="solicitacao-detalhe.php" class="request-link-details">Ver detalhes →</a>
                  </div>`;
                }).join('')
            }
          </div>
        </section>

        <section class="gestor-section-card" aria-labelledby="heading-aud-prev">
          <div class="section-card-header">
            <div class="section-card-header-left">
              <img src="assets/icons/shield.svg" alt="">
              <h3 class="section-title" id="heading-aud-prev">Auditoria de Segurança</h3>
            </div>
            <img src="assets/icons/lock.svg" alt="" style="width:14px;height:14px;opacity:.6;">
          </div>
          <div class="timeline-audit-list">
            ${(u.auditoria || []).map(ev => `
              <div class="timeline-event-item">
                <span class="timeline-dot${ev.destaque ? ' highlight' : ''}"></span>
                <span class="timeline-event-title">${esc(ev.titulo)}</span>
                <span class="timeline-event-meta">${esc(ev.data)} &bull; ${esc(ev.extra)}</span>
              </div>`).join('')}
          </div>
        </section>
      </div>
    `;
  }

  /* ──────────────────────────────────────────────────────────────────────────
     ABA: SOLICITAÇÕES (conteúdo completo)
  ────────────────────────────────────────────────────────────────────────── */
  const cardSol = document.getElementById('card-solicitacoes');
  if (cardSol) {
    const tabLabel = document.getElementById('tab-sol-label');
    if (tabLabel) tabLabel.textContent = `Solicitações (${u.solicitacoes})`;

    cardSol.innerHTML = `
      <div class="section-card-header">
        <div class="section-card-header-left">
          <img src="assets/icons/file-text.svg" alt="">
          <h3 class="section-title">Todas as Solicitações</h3>
        </div>
        <span class="section-tag-updated" style="color:#d97706;font-weight:700;">${u.solicitacoes} registrada${u.solicitacoes !== 1 ? 's' : ''}</span>
      </div>
      <div class="requests-list-wrap">
        ${u.listaSolicitacoes.length === 0
          ? `<p style="font-size:13px;color:#78716c;text-align:center;padding:40px 0;">Este cidadão ainda não registrou solicitações.</p>`
          : u.listaSolicitacoes.map(s => {
              const badgeCls = (s.status === 'Concluído' || s.status === 'Aprovada') ? 'badge-concluido' : 'badge-analise';
              return `
              <div class="request-card-item">
                <div class="request-header-row">
                  <span>${esc(s.id)} &bull; ${esc(s.data)}</span>
                  <span class="request-badge-status ${badgeCls}">${esc(s.status)}</span>
                </div>
                <div class="request-title-item">${esc(s.titulo)}</div>
                <div class="request-location-item">📍 ${esc(s.local)}</div>
                <a href="solicitacao-detalhe.php" class="request-link-details">Ver detalhes da solicitação →</a>
              </div>`;
            }).join('')
        }
      </div>`;
  }

  /* ──────────────────────────────────────────────────────────────────────────
     ABA: AUDITORIA (log completo)
  ────────────────────────────────────────────────────────────────────────── */
  const cardAud = document.getElementById('card-auditoria');
  if (cardAud) {
    cardAud.innerHTML = `
      <div class="section-card-header">
        <div class="section-card-header-left">
          <img src="assets/icons/history.svg" alt="">
          <h3 class="section-title">Histórico de Acessos &amp; Auditoria</h3>
        </div>
        <img src="assets/icons/lock.svg" alt="" style="width:14px;height:14px;opacity:.6;">
      </div>
      <div class="timeline-audit-list">
        ${(u.auditoria || []).map(ev => `
          <div class="timeline-event-item">
            <span class="timeline-dot${ev.destaque ? ' highlight' : ''}"></span>
            <span class="timeline-event-title">${esc(ev.titulo)}</span>
            <span class="timeline-event-meta">${esc(ev.data)} &bull; ${esc(ev.extra)}</span>
          </div>`).join('')}
      </div>`;
  }

  /* ──────────────────────────────────────────────────────────────────────────
     ABAS — navegação
  ────────────────────────────────────────────────────────────────────────── */
  const tabBtns     = document.querySelectorAll('.tab-nav-btn');
  const tabContents = document.querySelectorAll('.tab-content');

  tabBtns.forEach(btn => {
    btn.addEventListener('click', () => {
      const alvo = btn.getAttribute('data-tab');
      tabBtns.forEach(b => b.classList.remove('active'));
      tabContents.forEach(c => c.style.display = 'none');
      btn.classList.add('active');
      const el = document.getElementById(`tab-${alvo}`);
      if (el) el.style.display = 'block';
    });
  });

  /* ──────────────────────────────────────────────────────────────────────────
     AÇÕES: notificação + bloqueio
  ────────────────────────────────────────────────────────────────────────── */
  // Notificações pré-definidas por tipo de deficiência
  const NOTIF_PREDEFINIDAS = [
    {
      icone: '📋', tipo: 'gestor',
      titulo: 'Sua solicitação foi atualizada',
      corpo: `Olá, ${u.nomeExibicao}! O gestor Carlos Eduardo registrou um despacho técnico na sua solicitação. Acesse a área de solicitações para ver os detalhes.`
    },
    {
      icone: '✅', tipo: 'gestor',
      titulo: 'Solicitação aprovada e encaminhada',
      corpo: `Boa notícia, ${u.nomeExibicao}! Sua solicitação foi aprovada e encaminhada para a Secretaria de Manutenção Urbana de Suzano.`
    },
    {
      icone: '⚠️', tipo: 'alerta',
      titulo: 'Obstáculo na sua rota habitual',
      corpo: `Identificamos uma barreira arquitetônica na rota que você costuma usar. Acesse o mapa para ver a rota alternativa certificada.`
    },
    {
      icone: '🗺️', tipo: 'rota',
      titulo: 'Nova rota acessível certificada',
      corpo: `Uma nova rota com certificação NBR 9050 foi disponibilizada na sua região. Confira no mapa interativo do Passe Livre.`
    },
    {
      icone: '📄', tipo: 'sistema',
      titulo: 'Laudo médico com vencimento próximo',
      corpo: `Seu laudo médico vencerá em breve. Atualize a documentação no seu perfil para manter os benefícios de acessibilidade ativos.`
    },
    {
      icone: 'ℹ️', tipo: 'info',
      titulo: 'Manutenção programada no Terminal',
      corpo: `O elevador do Terminal Central ficará fora de operação temporariamente para manutenção preventiva. Confira os horários alternativos.`
    },
  ];

  function abrirModalNotificacao() {
    const modal    = document.getElementById('modal-notificacao');
    const destEl   = document.getElementById('modal-notif-destinatario');
    const predefEl = document.getElementById('notif-predefinidas');
    if (!modal) return;

    if (destEl) destEl.textContent = `Para: ${u.nomeExibicao} (${u.email})`;

    // Renderiza pré-definidas
    if (predefEl) {
      predefEl.innerHTML = NOTIF_PREDEFINIDAS.map((n, idx) => `
        <button type="button"
          data-idx="${idx}"
          style="
            display:flex;align-items:flex-start;gap:8px;padding:10px 12px;
            background:#fdfaf6;border:1.5px solid #ede4d8;border-radius:12px;
            font-family:var(--font-main);font-size:12px;color:#1c1917;
            cursor:pointer;text-align:left;transition:all .15s;
          "
          onmouseenter="this.style.borderColor='#d97706';this.style.background='#fff7ed';"
          onmouseleave="this.style.borderColor='#ede4d8';this.style.background='#fdfaf6';"
          onclick="window._selecionarPredefinida(${idx})"
        >
          <span style="font-size:18px;line-height:1.2;flex-shrink:0;">${n.icone}</span>
          <span>
            <strong style="display:block;font-size:12px;font-weight:700;color:#1c1917;margin-bottom:2px;">${n.titulo}</strong>
            <span style="font-size:11px;color:#78716c;overflow:hidden;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;">${n.corpo.substring(0, 80)}…</span>
          </span>
        </button>
      `).join('');
    }

    // Mostra modal
    modal.style.display = 'flex';
    setTimeout(() => { modal.style.opacity = '1'; modal.style.pointerEvents = 'auto'; }, 10);

    // Limpa form
    ['notif-titulo-input','notif-corpo-input'].forEach(id => {
      const el = document.getElementById(id);
      if (el) el.value = '';
    });
    ['notif-titulo-count','notif-corpo-count'].forEach(id => {
      const el = document.getElementById(id);
      if (el) el.textContent = '0';
    });
    document.getElementById('notif-preview-wrap').style.display = 'none';
    document.getElementById('notif-tipo-select').value = 'gestor';
  }

  function fecharModalNotificacao() {
    const modal = document.getElementById('modal-notificacao');
    if (!modal) return;
    modal.style.opacity = '0';
    modal.style.pointerEvents = 'none';
    setTimeout(() => { modal.style.display = 'none'; }, 250);
  }

  // Selecionar pré-definida
  window._selecionarPredefinida = function(idx) {
    const n = NOTIF_PREDEFINIDAS[idx];
    if (!n) return;
    const tituloEl = document.getElementById('notif-titulo-input');
    const corpoEl  = document.getElementById('notif-corpo-input');
    const tipoEl   = document.getElementById('notif-tipo-select');
    if (tituloEl) { tituloEl.value = n.titulo; document.getElementById('notif-titulo-count').textContent = n.titulo.length; }
    if (corpoEl)  { corpoEl.value  = n.corpo;  document.getElementById('notif-corpo-count').textContent  = n.corpo.length;  }
    if (tipoEl)   tipoEl.value = n.tipo;
    atualizarPreview();
    // Destaca o botão selecionado
    document.querySelectorAll('#notif-predefinidas button').forEach((btn, i) => {
      btn.style.borderColor = i === idx ? '#d97706' : '#ede4d8';
      btn.style.background  = i === idx ? '#fff7ed' : '#fdfaf6';
    });
  };

  function atualizarPreview() {
    const titulo = document.getElementById('notif-titulo-input')?.value.trim();
    const corpo  = document.getElementById('notif-corpo-input')?.value.trim();
    const tipo   = document.getElementById('notif-tipo-select')?.value || 'gestor';
    const wrap   = document.getElementById('notif-preview-wrap');

    const TIPO_EMOJI = { gestor:'📋', alerta:'⚠️', info:'ℹ️', sistema:'⚙️', rota:'🗺️' };
    const TIPO_ICON_BG = { gestor:'#fff7ed', alerta:'#fef2f2', info:'#eff6ff', sistema:'#f5f3ff', rota:'#f0fdf4' };

    if (titulo || corpo) {
      wrap.style.display = 'block';
      document.getElementById('preview-icon').textContent = TIPO_EMOJI[tipo] || '📋';
      document.getElementById('preview-icon').style.background = TIPO_ICON_BG[tipo] || '#fff7ed';
      document.getElementById('preview-titulo').textContent = titulo || '(sem título)';
      document.getElementById('preview-corpo').textContent  = corpo  || '(sem mensagem)';
    } else {
      wrap.style.display = 'none';
    }
  }

  // Eventos do modal
  document.getElementById('btn-send-notification')?.addEventListener('click', abrirModalNotificacao);
  document.getElementById('btn-close-modal-notif')?.addEventListener('click', fecharModalNotificacao);
  document.getElementById('btn-cancelar-notif')?.addEventListener('click', fecharModalNotificacao);

  document.getElementById('modal-notificacao')?.addEventListener('click', e => {
    if (e.target === document.getElementById('modal-notificacao')) fecharModalNotificacao();
  });

  document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && document.getElementById('modal-notificacao')?.style.display === 'flex') {
      fecharModalNotificacao();
    }
  });

  // Live preview ao digitar
  ['notif-titulo-input','notif-corpo-input','notif-tipo-select'].forEach(id => {
    document.getElementById(id)?.addEventListener('input', atualizarPreview);
    document.getElementById(id)?.addEventListener('change', atualizarPreview);
  });

  // Hover no botão enviar
  const btnEnviar = document.getElementById('btn-enviar-notif');
  if (btnEnviar) {
    btnEnviar.addEventListener('mouseenter', () => { btnEnviar.style.background = '#b45309'; btnEnviar.style.transform = 'translateY(-1px)'; });
    btnEnviar.addEventListener('mouseleave', () => { btnEnviar.style.background = '#d97706'; btnEnviar.style.transform = ''; });
  }

  // Ajusta rótulo do botão de bloqueio de acordo com o status atual
  const btnBlock = document.getElementById('btn-block-account');
  if (btnBlock) {
    btnBlock.textContent = (u.status || '').toLowerCase() === 'bloqueado' ? 'Desbloquear Conta' : 'Bloquear Conta';
  }

  // Enviar Notificação (Persistência no MySQL)
  document.getElementById('btn-enviar-notif')?.addEventListener('click', async () => {
    const titulo = document.getElementById('notif-titulo-input')?.value.trim();
    const corpo  = document.getElementById('notif-corpo-input')?.value.trim();
    const tipo   = document.getElementById('notif-tipo-select')?.value || 'gestor';

    if (!titulo) {
      document.getElementById('notif-titulo-input')?.focus();
      window.showToast?.('Por favor, preencha o título da notificação.', 'error');
      return;
    }
    if (!corpo) {
      document.getElementById('notif-corpo-input')?.focus();
      window.showToast?.('Por favor, escreva a mensagem da notificação.', 'error');
      return;
    }

    try {
      const payload = {
        user_id: u.sqlId || u.id,
        title: titulo,
        message: corpo,
        type: tipo
      };

      let resp;
      if (window.PasseLivreAuth && window.PasseLivreAuth.apiFetch) {
        resp = await window.PasseLivreAuth.apiFetch('api/notificacoes.php?action=send_individual', {
          method: 'POST',
          body: JSON.stringify(payload)
        });
      } else {
        const token = localStorage.getItem('passeLivre_token') || localStorage.getItem('passeLivreToken') || '';
        const res = await fetch('api/notificacoes.php?action=send_individual', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            ...(token ? { 'X-Session-Token': token } : {})
          },
          body: JSON.stringify(payload)
        });
        resp = await res.json();
      }

      // Limpa os campos do formulário
      const inputTit = document.getElementById('notif-titulo-input');
      const inputCorpo = document.getElementById('notif-corpo-input');
      if (inputTit) inputTit.value = '';
      if (inputCorpo) inputCorpo.value = '';

      fecharModalNotificacao();
      window.PasseLivreAuth?.showToast?.(`Notificação enviada com sucesso para ${u.nomeExibicao}!`);
    } catch (err) {
      console.error('[notificacao MySQL] Erro ao enviar:', err);
      window.PasseLivreAuth?.showToast?.(err.message || 'Erro ao enviar notificação no banco de dados.', 'error');
    }
  });

  /* ──────────────────────────────────────────────────────────────────────────
     BLOQUEIO / DESBLOQUEIO DE CONTA (Persistência no MySQL)
  ────────────────────────────────────────────────────────────────────────── */
  btnBlock?.addEventListener('click', async () => {
    const isBloqueado = (u.status || '').toLowerCase() === 'bloqueado';
    const novoStatus  = isBloqueado ? 'ativo' : 'bloqueado';
    const msgConfirm  = isBloqueado
      ? `Confirma o desbloqueio da conta de ${u.nomeExibicao}? O cidadão poderá acessar a plataforma novamente.`
      : `Confirma o bloqueio preventivo da conta de ${u.nomeExibicao}? Suas sessões ativas serão encerradas.`;

    if (!confirm(msgConfirm)) return;

    try {
      const payload = { id: u.sqlId || u.id, status: novoStatus };
      let resp = null;

      if (window.PasseLivreAuth && window.PasseLivreAuth.apiFetch) {
        resp = await window.PasseLivreAuth.apiFetch('api/users.php?action=update_status', {
          method: 'POST',
          body: JSON.stringify(payload)
        });
      } else {
        const token = localStorage.getItem('passeLivreToken') || '';
        const r = await fetch('api/users.php?action=update_status', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            ...(token ? { 'X-Session-Token': token } : {})
          },
          body: JSON.stringify(payload)
        });
        if (r.ok) resp = await r.json();
      }

      u.status = novoStatus === 'ativo' ? 'Ativo' : 'Bloqueado';
      btnBlock.textContent = u.status === 'Ativo' ? 'Bloquear Conta' : 'Desbloquear Conta';
      window.showToast?.(`Conta de ${u.nomeExibicao} alterada para "${u.status}" no MySQL.`, u.status === 'Ativo' ? 'success' : 'warning');
      setTimeout(() => location.reload(), 1000);
    } catch (e) {
      window.showToast?.('Erro ao atualizar status da conta: ' + e.message, 'error');
    }
  });

  /* ──────────────────────────────────────────────────────────────────────────
     NAVEGAÇÃO ENTRE USUÁRIOS (anterior / próximo)
  ────────────────────────────────────────────────────────────────────────── */
  const idx  = db.findIndex(x => x.id === userId || String(x.sqlId) === String(userId));
  const prev = idx > 0 ? db[idx - 1] : null;
  const next = idx >= 0 && idx < db.length - 1 ? db[idx + 1] : null;

  if (prev || next) {
    const navHtml = `
      <div style="display:flex;align-items:center;justify-content:space-between;margin-top:8px;flex-wrap:wrap;gap:10px;">
        ${prev
          ? `<a href="perfil-cidadao-detalhe.php?id=${prev.id}"
                style="display:inline-flex;align-items:center;gap:6px;font-size:12.5px;font-weight:700;color:#b45309;text-decoration:none;">
               ← ${prev.nomeExibicao}
             </a>`
          : '<span></span>'}
        ${next
          ? `<a href="perfil-cidadao-detalhe.php?id=${next.id}"
                style="display:inline-flex;align-items:center;gap:6px;font-size:12.5px;font-weight:700;color:#b45309;text-decoration:none;">
               ${next.nomeExibicao} →
             </a>`
          : '<span></span>'}
      </div>`;

    const toolbar = document.querySelector('.gestor-action-toolbar');
    if (toolbar) toolbar.insertAdjacentHTML('afterend', navHtml);
  }

  // Registra visualização na auditoria MySQL
  if (window.PLDBAuditLog) {
    try {
      await window.PLDBAuditLog(
        'usuarios',
        'Perfil de Cidadão Visualizado',
        `Perfil de ${u.nome} (${u.id}) acessado pelo gestor`,
        'baixo'
      );
    } catch (_) {}
  }
});
