/**
 * Passe Livre — Central de Notificações do Gestor (notificacoes.php)
 *
 * Implementação única desta tela: modal de criação, envio, histórico, filtros,
 * pesquisa e leitura administrativa. Não há handlers equivalentes no HTML, então
 * nada aqui se duplica com script inline.
 */
(function () {
  'use strict';

  const TIPOS_URGENTES = ['warning', 'alert', 'urgente'];

  const feedEl        = document.getElementById('notifications-feed');
  const resumoEl      = document.getElementById('feed-resumo');
  const limiteEl      = document.getElementById('feed-mais-registros');
  const searchEl      = document.getElementById('notif-search-input');
  const pills         = Array.from(document.querySelectorAll('.notif-pill-btn'));
  const btnNova       = document.getElementById('btn-new-broadcast');
  const btnMarcar     = document.getElementById('btn-mark-all-read');
  const modal         = document.getElementById('notif-modal');
  const form          = document.getElementById('form-send-broadcast');
  const btnFechar     = document.getElementById('btn-close-notif-modal');
  const btnCancelar   = document.getElementById('btn-cancel-broadcast');
  const btnEnviar     = document.getElementById('btn-submit-notif');
  const statusEl      = document.getElementById('broadcast-status');
  const destEl        = document.getElementById('broadcast-dest-select');
  const tipoEl        = document.getElementById('broadcast-type-select');
  const tituloEl      = document.getElementById('broadcast-title-input');
  const corpoEl       = document.getElementById('broadcast-body-textarea');
  const linkEl        = document.getElementById('broadcast-link-input');
  const contadorEl    = document.getElementById('broadcast-body-count');

  if (!feedEl || !modal || !form) return;

  const estado = {
    linhas: [],
    contagens: null,
    limite: 25,
    filtro: 'all',
    busca: '',
    modalAberto: false,
    enviando: false,
    marcando: false,
    ultimoFoco: null
  };

  // -------------------------------------------------------------------------
  // 1. REQUISIÇÕES — só dão sucesso quando o HTTP e o JSON confirmam
  // -------------------------------------------------------------------------
  function tokenSessao() {
    return window.PasseLivreAuth?.getToken?.() || '';
  }

  function tokenCsrf() {
    return document.querySelector('meta[name="csrf-token"]')?.content || '';
  }

  async function requisitar(url, options) {
    const headers = Object.assign({ 'Content-Type': 'application/json' }, options?.headers || {});
    const token = tokenSessao();
    if (token) headers['X-Session-Token'] = token;

    let resposta;
    try {
      resposta = await fetch(url, Object.assign({ credentials: 'same-origin' }, options, { headers }));
    } catch (erro) {
      throw new Error('Falha na comunicação com o servidor. Verifique a conexão e tente novamente.');
    }

    let json = null;
    try {
      json = await resposta.json();
    } catch (erro) {
      json = null;
    }

    if (!resposta.ok || !json || json.success !== true) {
      const mensagem = (json && (json.error || json.message))
        || (resposta.ok
          ? 'A resposta do servidor não confirmou a operação.'
          : `O servidor respondeu com erro (HTTP ${resposta.status}).`);
      const erro = new Error(mensagem);
      erro.status = resposta.status;
      erro.code = json ? json.code : undefined;
      throw erro;
    }

    return json;
  }

  const obterFeed = () => requisitar('api/notificacoes.php?action=admin_feed&limite=' + estado.limite);

  // -------------------------------------------------------------------------
  // 2. RENDERIZAÇÃO DO HISTÓRICO
  // -------------------------------------------------------------------------
  function ehUrgente(tipo) {
    return TIPOS_URGENTES.includes(tipo);
  }

  function combinaFiltro(n) {
    if (estado.filtro === 'urgente' && !ehUrgente(n.type)) return false;
    if (estado.filtro === 'unread' && n.lida_admin) return false;
    if (estado.busca) {
      const alvo = `${n.title} ${n.body} ${n.destino_nome}`.toLowerCase();
      if (!alvo.includes(estado.busca)) return false;
    }
    return true;
  }

  function criarCartao(n) {
    const artigo = document.createElement('article');
    artigo.className = 'notif-card-row' + (n.lida_admin ? '' : ' unread');
    artigo.dataset.id = String(n.id);

    const icone = document.createElement('div');
    icone.className = 'notif-icon-circle ' + (ehUrgente(n.type) ? 'danger' : 'info');
    const img = document.createElement('img');
    img.src = ehUrgente(n.type) ? 'assets/icons/warning.svg' : 'assets/icons/bell.svg';
    img.alt = '';
    img.style.width = '18px';
    img.style.height = '18px';
    icone.appendChild(img);

    const corpo = document.createElement('div');
    corpo.className = 'notif-card-body';

    const topo = document.createElement('div');
    topo.className = 'notif-card-top';
    const titulo = document.createElement('h4');
    titulo.className = 'notif-card-title';
    titulo.textContent = n.title;
    const data = document.createElement('span');
    data.className = 'notif-card-date';
    data.textContent = (n.criada_em || '').slice(0, 16);
    topo.append(titulo, data);

    const texto = document.createElement('p');
    texto.className = 'notif-card-desc';
    texto.textContent = n.body || '';

    const meta = document.createElement('div');
    meta.className = 'notif-card-meta';

    const destino = document.createElement('span');
    destino.className = 'notif-card-dest';
    destino.appendChild(document.createTextNode('Destinatário: '));
    const nome = document.createElement('strong');
    nome.textContent = n.destino_nome || 'Todos / Sistema';
    destino.appendChild(nome);

    const leituraAdmin = document.createElement('span');
    leituraAdmin.className = 'notif-state ' + (n.lida_admin ? 'read' : 'pending');
    leituraAdmin.textContent = n.lida_admin ? '✓ Lida no painel' : '● Não lida no painel';

    const leituraCidadao = document.createElement('span');
    leituraCidadao.className = 'notif-state ' + (n.lida_cidadao ? 'read' : 'pending');
    leituraCidadao.textContent = n.lida_cidadao ? '✓ Cidadão já leu' : '○ Aguardando o cidadão';

    meta.append(destino, leituraAdmin, leituraCidadao);
    corpo.append(topo, texto, meta);
    artigo.append(icone, corpo);
    return artigo;
  }

  function atualizarContagens(contagens) {
    if (!contagens) return;
    estado.contagens = contagens;
    document.querySelectorAll('[data-count]').forEach(el => {
      const chave = el.dataset.count;
      if (chave in contagens) el.textContent = contagens[chave];
    });
  }

  function renderizarFeed() {
    const visiveis = estado.linhas.filter(combinaFiltro);
    feedEl.textContent = '';

    if (!visiveis.length) {
      const vazio = document.createElement('p');
      vazio.className = 'feed-placeholder';
      vazio.textContent = estado.linhas.length
        ? 'Nenhuma notificação corresponde ao filtro ou à pesquisa atual.'
        : 'Você ainda não possui notificações registradas no sistema.';
      feedEl.appendChild(vazio);
    } else {
      const frag = document.createDocumentFragment();
      visiveis.forEach(n => frag.appendChild(criarCartao(n)));
      feedEl.appendChild(frag);
    }

    const total = estado.contagens ? estado.contagens.total : estado.linhas.length;
    const filtrando = estado.filtro !== 'all' || estado.busca !== '';

    if (resumoEl) {
      resumoEl.textContent = filtrando
        ? `Histórico de Notificações no MySQL (${visiveis.length} de ${estado.linhas.length} exibidas no filtro)`
        : `Histórico de Notificações no MySQL (${visiveis.length} de ${total} exibidas)`;
    }
    if (limiteEl) {
      const temMaisParaTras = !filtrando && total > estado.linhas.length;
      limiteEl.hidden = !temMaisParaTras;
      if (temMaisParaTras) {
        limiteEl.textContent = `Exibindo as ${estado.linhas.length} mensagens mais recentes do banco — ${total} no total.`;
      }
    }
  }

  function aplicarDados(dados) {
    if (Array.isArray(dados.linhas)) estado.linhas = dados.linhas;
    if (dados.limite) estado.limite = Number(dados.limite) || estado.limite;
    atualizarContagens(dados.contagens);
    renderizarFeed();
  }

  // -------------------------------------------------------------------------
  // 3. FEEDBACK AO USUÁRIO
  // -------------------------------------------------------------------------
  function toast(msg, tipo) {
    (window.showToast || window.PasseLivreAuth?.showToast || (() => {}))(msg, tipo);
  }

  function mostrarErroModal(mensagem) {
    if (!statusEl) return;
    statusEl.textContent = mensagem;
    statusEl.classList.add('is-error');
    statusEl.hidden = false;
  }

  function limparErroModal() {
    if (!statusEl) return;
    statusEl.hidden = true;
    statusEl.textContent = '';
    statusEl.classList.remove('is-error');
  }

  function marcarCampoInvalido(campo, invalido) {
    if (!campo) return;
    campo.classList.toggle('is-invalid', Boolean(invalido));
  }

  // -------------------------------------------------------------------------
  // 4. MODAL
  // -------------------------------------------------------------------------
  const SEL_FOCOS = 'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])';

  function elementosFocaveis() {
    return Array.from(modal.querySelectorAll(SEL_FOCOS))
      .filter(el => !el.disabled && el.offsetParent !== null);
  }

  function abrirModal() {
    if (estado.modalAberto) return;
    const focoAtual = document.activeElement;
    estado.ultimoFoco = (focoAtual && focoAtual !== document.body) ? focoAtual : (btnNova || modal);
    estado.modalAberto = true;
    limparErroModal();
    modal.hidden = false;
    document.body.classList.add('notif-modal-open');
    // Lê o layout antes de ligar a classe: força o navegador a pintar o estado
    // "aberto" na próxima frame da transição sem depender de requestAnimationFrame
    // (que não dispara em aba oculta e deixaria o modal com opacity 0).
    void modal.offsetWidth;
    modal.classList.add('is-open');
    (destEl || tituloEl || btnFechar)?.focus();
  }

  function fecharModal() {
    if (!estado.modalAberto || estado.enviando) return;
    estado.modalAberto = false;
    modal.classList.remove('is-open');
    document.body.classList.remove('notif-modal-open');
    window.setTimeout(() => {
      if (!estado.modalAberto) modal.hidden = true;
    }, 200);
    if (estado.ultimoFoco && typeof estado.ultimoFoco.focus === 'function') {
      estado.ultimoFoco.focus();
    }
  }

  document.addEventListener('keydown', ev => {
    if (!estado.modalAberto) return;

    if (ev.key === 'Escape') {
      if (estado.enviando) return;
      ev.preventDefault();
      fecharModal();
      return;
    }

    if (ev.key === 'Tab') {
      const focos = elementosFocaveis();
      if (!focos.length) return;
      const primeiro = focos[0];
      const ultimo = focos[focos.length - 1];
      if (ev.shiftKey && document.activeElement === primeiro) {
        ev.preventDefault();
        ultimo.focus();
      } else if (!ev.shiftKey && document.activeElement === ultimo) {
        ev.preventDefault();
        primeiro.focus();
      }
    }
  });

  btnNova?.addEventListener('click', abrirModal);
  btnFechar?.addEventListener('click', fecharModal);
  btnCancelar?.addEventListener('click', fecharModal);

  modal.addEventListener('mousedown', ev => {
    if (ev.target === modal && !estado.enviando) fecharModal();
  });

  corpoEl?.addEventListener('input', () => {
    if (contadorEl) contadorEl.textContent = String(corpoEl.value.length);
  });

  // -------------------------------------------------------------------------
  // 5. ENVIO — send_individual, com validação antes de bater no servidor
  // -------------------------------------------------------------------------
  function validarEnvio() {
    const problemas = [];

    if (!destEl?.value) {
      problemas.push('escolha o cidadão destinatário');
      marcarCampoInvalido(destEl, true);
    } else {
      marcarCampoInvalido(destEl, false);
    }

    const titulo = (tituloEl?.value || '').trim();
    if (!titulo) {
      problemas.push('escreva um título');
      marcarCampoInvalido(tituloEl, true);
    } else {
      marcarCampoInvalido(tituloEl, false);
    }

    const corpo = (corpoEl?.value || '').trim();
    if (!corpo) {
      problemas.push('escreva a mensagem');
      marcarCampoInvalido(corpoEl, true);
    } else if (corpo.length > 500) {
      problemas.push('a mensagem ultrapassa 500 caracteres');
      marcarCampoInvalido(corpoEl, true);
    } else {
      marcarCampoInvalido(corpoEl, false);
    }

    const link = (linkEl?.value || '').trim();
    if (link.length > 250 || /^(?:javascript|data|vbscript):/i.test(link)) {
      problemas.push('o link de redirecionamento é inválido');
      marcarCampoInvalido(linkEl, true);
    } else {
      marcarCampoInvalido(linkEl, false);
    }

    return problemas;
  }

  function definirEnviando(enviando) {
    estado.enviando = enviando;
    if (btnEnviar) {
      btnEnviar.disabled = enviando;
      const rotulo = btnEnviar.querySelector('span');
      if (rotulo) rotulo.textContent = enviando ? 'Gravando no MySQL…' : 'Confirmar e Gravar no MySQL';
    }
    [destEl, tipoEl, tituloEl, corpoEl, linkEl].forEach(campo => { if (campo) campo.disabled = enviando; });
    btnCancelar.disabled = enviando;
    btnFechar.disabled = enviando;
  }

  form.addEventListener('submit', async ev => {
    ev.preventDefault();
    if (estado.enviando) return;

    limparErroModal();
    const problemas = validarEnvio();
    if (problemas.length) {
      mostrarErroModal('Revise a notificação: ' + problemas.join(', ') + '.');
      return;
    }

    definirEnviando(true);
    try {
      await requisitar('api/notificacoes.php?action=send_individual', {
        method: 'POST',
        body: JSON.stringify({
          user_id: parseInt(destEl.value, 10),
          title: tituloEl.value.trim(),
          body: corpoEl.value.trim(),
          message: corpoEl.value.trim(),
          type: tipoEl?.value || 'info',
          link: (linkEl?.value || '').trim()
        })
      });

      const dados = await obterFeed();
      definirEnviando(false);
      form.reset();
      if (contadorEl) contadorEl.textContent = '0';
      fecharModal();
      aplicarDados(dados);
      toast('Notificação gravada no MySQL e entregue ao cidadão.');
    } catch (erro) {
      // Falhou: modal continua aberto, texto preservado, botão reabilitado.
      definirEnviando(false);
      mostrarErroModal(erro.message || 'Não foi possível gravar a notificação.');
      toast(erro.message || 'Falha ao enviar a notificação.', 'error');
    }
  });

  // -------------------------------------------------------------------------
  // 6. MARCAR TODAS COMO LIDAS (estado administrativo)
  // -------------------------------------------------------------------------
  btnMarcar?.addEventListener('click', async () => {
    if (estado.marcando) return;
    estado.marcando = true;
    btnMarcar.disabled = true;
    const rotuloOriginal = btnMarcar.textContent;
    btnMarcar.textContent = 'Marcando…';

    try {
      const dados = await requisitar('api/notificacoes.php?action=admin_mark_all_read', {
        method: 'POST',
        headers: { 'X-CSRF-Token': tokenCsrf() },
        body: JSON.stringify({ csrf_token: tokenCsrf() })
      });
      aplicarDados(dados);
      toast(dados.message || 'Histórico marcado como lido no painel.');
    } catch (erro) {
      toast(erro.message || 'Falha ao marcar as notificações como lidas.', 'error');
    } finally {
      estado.marcando = false;
      btnMarcar.disabled = false;
      btnMarcar.textContent = rotuloOriginal;
    }
  });

  // -------------------------------------------------------------------------
  // 7. FILTROS E PESQUISA
  // -------------------------------------------------------------------------
  pills.forEach(pill => {
    pill.addEventListener('click', () => {
      pills.forEach(p => {
        p.classList.remove('active');
        p.setAttribute('aria-pressed', 'false');
      });
      pill.classList.add('active');
      pill.setAttribute('aria-pressed', 'true');
      estado.filtro = pill.dataset.filter || 'all';
      renderizarFeed();
    });
  });

  searchEl?.addEventListener('input', () => {
    estado.busca = searchEl.value.trim().toLowerCase();
    renderizarFeed();
  });

  // -------------------------------------------------------------------------
  // 8. PARTIDA
  // -------------------------------------------------------------------------
  let inicial = null;
  try {
    inicial = JSON.parse(document.getElementById('notif-feed-inicial')?.textContent || 'null');
  } catch (erro) {
    console.warn('[Notificações] Falha ao ler o histórico embutido:', erro);
  }

  if (inicial) {
    aplicarDados(inicial);
  } else {
    feedEl.textContent = '';
    const aviso = document.createElement('p');
    aviso.className = 'feed-placeholder';
    aviso.textContent = 'Não foi possível carregar o histórico do banco de dados.';
    feedEl.appendChild(aviso);
  }
})();
