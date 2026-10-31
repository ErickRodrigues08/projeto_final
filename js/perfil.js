/**
 * Passe Livre — Lógica da Tela de Editar Perfil
 * Integração SQL: UPDATE na tabela `users` via PLDB.users.update()
 */

document.addEventListener('DOMContentLoaded', () => {

  // =========================================================================
  // 0. PRÉ-CARREGA DADOS DO USUÁRIO DO BANCO SQL
  // =========================================================================
  async function preloadUserData() {
    const sessionUser = window.PasseLivreAuth?.getUser();
    if (!sessionUser) return;

    let dbUser = null;
    if (window.PasseLivreDBReady) {
      try {
        await window.PasseLivreDBReady;
        if (window.PLDB) {
          dbUser = window.PLDB.users.findByEmail(sessionUser.email);
        }
      } catch (_) {}
    }

    const user = dbUser
      ? { name: dbUser.name, email: dbUser.email, phone: dbUser.phone, city: dbUser.city, avatar: dbUser.avatar, pcd_categories: dbUser.pcd_categories, saved_prefs: dbUser.saved_prefs }
      : sessionUser;

    // Preenche campos do formulário
    _setVal('input-profile-name',  user.name  || '');
    _setVal('input-profile-email', user.email || '');
    _setVal('input-profile-phone', user.phone || '');
    _setVal('input-profile-city',  user.city  || 'Suzano - SP');

    // Avatar lido do banco — não sobrescreve uma alteração já confirmada nesta tela.
    nomeUsuario = user.name || '';
    if (!avatarAlteradoLocal) {
      avatarAtual = _normalizaAvatar(user.avatar);
      desenharAvatar();
      if (!avatarOperacaoOcupada) marcarStatusAvatar();
    }

    // Pílulas PCD
    let pcd = [];
    try { pcd = JSON.parse(user.pcd_categories || '[]'); } catch (_) {}
    document.querySelectorAll('.pill-option-btn').forEach(pill => {
      const v = pill.getAttribute('data-value');
      if (pcd.includes(v)) pill.classList.add('selected');
    });

    console.log('[Perfil SQL] Dados do usuário carregados:', user.name);
    ultimoEstado = estadoDoPerfil();
    updateA11yEmptyState();
  }

  const btnProfileLogout = document.getElementById('btn-profile-logout');
  if (btnProfileLogout) {
    btnProfileLogout.addEventListener('click', async () => {
      btnProfileLogout.disabled = true;
      await window.PasseLivreAuth.clearUser();
      window.location.replace('login.php');
    });
  }

  // =========================================================================
  // 1. FOTO DE PERFIL: UPLOAD PERSISTENTE + ENQUADRAMENTO
  //     A foto só é dada como concluída depois de api/upload.php confirmar que os
  //     bytes e a referência do avatar foram gravados no banco. Nada de
  //     depender de `data:` URL do navegador: ela não sobrevive ao F5.
  // =========================================================================
  const badgeOptim       = document.getElementById('badge-optim-enquadramento');
  const fileInput        = document.getElementById('input-file-avatar');
  const statusTag        = document.getElementById('optim-status-tag');

  // Estado compartilhado entre o avatar grande, o cabeçalho e o modal de corte
  const AVATAR_PADRAO = (window.PasseLivreAuth && window.PasseLivreAuth.avatarPadrao)
    || 'assets/images/avatar.jpg';
  // O rótulo original do botão, para voltar ao normal depois de salvar.
  const rotuloAplicarCrop = (() => {
    const alvo = document.querySelector('#btn-apply-crop span');
    return alvo ? alvo.textContent : '';
  })();

  let nomeUsuario      = '';
  let avatarAtual      = AVATAR_PADRAO;   // '' nunca: sem foto própria = foto padrão
  let uploadEmAndamento = Promise.resolve();
  let avatarOperacaoOcupada = false;
  let avatarAlteradoLocal = false;

  function _normalizaAvatar(valor) {
    const src = window.PasseLivreAuth?.avatarSrc;
    if (typeof src === 'function') return src(valor);
    const s = (valor === null || valor === undefined) ? '' : String(valor).trim();
    return (s === '' || s === 'null' || s === 'undefined') ? AVATAR_PADRAO : s;
  }

  // A conta tem uma foto dela mesma quando o avatar não é a foto padrão do
  // projeto — vale tanto para o upload feito nesta tela quanto para a foto do Google.
  // A comparação ignora a query string usada para versionar a imagem.
  function temFotoPropria() {
    return _normalizaAvatar(avatarAtual).split('?')[0] !== AVATAR_PADRAO.split('?')[0];
  }

  function marcarStatusAvatar() {
    marcarStatus(temFotoPropria() ? 'Otimizado' : 'Padrão', temFotoPropria() ? '#059669' : '#78716c');
  }

  function _renderAvatarNo(container, seletor, html, keepId) {
    const antigo = container && container.querySelector(seletor);
    if (!antigo || !antigo.parentNode) return;
    const tmp  = document.createElement('div');
    tmp.innerHTML = html;
    const novo = tmp.firstElementChild;
    if (!novo) return;
    if (keepId) novo.id = keepId;
    antigo.parentNode.replaceChild(novo, antigo);
  }

  // Desenha a foto da conta — sempre como <img>: foto própria, foto padrão do
  // projeto e, só se até esse arquivo falhar, o círculo com a inicial (onerror).
  // `versao` acrescenta cache-busting apenas à URL escrita no DOM.
  function desenharAvatar(versao) {
    const auth = window.PasseLivreAuth;
    if (!auth || typeof auth.avatarHtml !== 'function') return;
    const usuario = { avatar: avatarAtual, name: nomeUsuario };

    const contenedorGrande = document.querySelector('.photo-avatar-container');
    if (contenedorGrande) {
      _renderAvatarNo(
        contenedorGrande,
        '.profile-large-avatar, .pl-avatar-initial',
        auth.avatarHtml(usuario, 80, 'profile-large-avatar'),
        'avatar-preview'
      );
    }

    const contenedorHeader = document.querySelector('.header-right .user-profile');
    if (contenedorHeader) {
      _renderAvatarNo(
        contenedorHeader,
        '.user-avatar-img',
        auth.avatarHtml(usuario, 36),
        'header-user-avatar'
      );
    }

    // Varredura geral: qualquer outro avatar já presente na página (menu do
    // usuário, listas, blocos reaproveitados) recebe a mesma foto.
    if (typeof auth.setPageAvatar === 'function') auth.setPageAvatar(usuario, versao);
  }

  function marcarStatus(texto, cor) {
    if (!statusTag) return;
    statusTag.textContent = texto;
    statusTag.style.color = cor;
  }

  function sincronizarSessao() {
    const sessao = window.PasseLivreAuth?.getUser();
    if (!sessao || !window.PasseLivreAuth) return;
    window.PasseLivreAuth.setUser(Object.assign({}, sessao, { avatar: avatarAtual }));
  }

  function headersDaFoto() {
    const token = window.PasseLivreAuth?.getToken?.();
    return token ? { 'X-Session-Token': token } : {};
  }

  function enviarFotoDePerfil(blob) {
    const form = new FormData();
    form.append('foto', blob, 'perfil.jpg');
    form.append('contexto', 'perfil');
    return fetch('api/upload.php', { method: 'POST', body: form, credentials: 'same-origin', headers: headersDaFoto() })
      .then(res => res.json().catch(() => null).then(dados => ({ res, dados })))
      .then(({ res, dados }) => {
        // `avatar: true` confirma a gravação dos bytes e da referência no banco.
        // Sem isso e uma URL válida, a foto nova não pode aparecer na interface.
        const url = typeof dados?.url === 'string' ? dados.url.trim() : '';
        const normalizar = window.PasseLivreAuth?.avatarSrc;
        // Sem o helper do auth.js a página está rodando um bundle incompleto/antigo;
        // isso não é falha de envio, e a mensagem genérica escondia o real motivo.
        if (typeof normalizar !== 'function') {
          throw new Error('Os scripts da página não carregaram por completo. Recarregue a tela e tente de novo.');
        }
        if (!res.ok || !dados || !dados.success || dados.avatar !== true || !url ||
            normalizar(url) !== url || url === AVATAR_PADRAO) {
          throw new Error(dados && dados.error ? dados.error : 'Não foi possível enviar sua foto. Tente novamente.');
        }
        return url;
      });
  }

  function blobDoCanvas(canvas) {
    return new Promise((resolve, reject) => {
      canvas.toBlob(b => (b ? resolve(b) : reject(new Error('Não foi possível gerar a imagem.'))), 'image/jpeg', 0.92);
    });
  }

  // Elementos do Modal de Enquadramento
  const btnAdjustFraming = document.getElementById('btn-adjust-framing');
  const btnRemovePhoto   = document.getElementById('btn-remove-photo');
  const cropModal        = document.getElementById('crop-modal');
  const btnCloseCrop     = document.getElementById('btn-close-crop-modal');
  const btnCancelCrop    = document.getElementById('btn-cancel-crop');
  const btnApplyCrop     = document.getElementById('btn-apply-crop');
  const cropViewport     = document.getElementById('crop-viewport');
  const cropCanvas       = document.getElementById('crop-canvas');
  const cropMiniPreview  = document.getElementById('crop-mini-preview');
  const cropZoomRange    = document.getElementById('crop-zoom-range');
  const cropZoomLabel    = document.getElementById('crop-zoom-label');
  const btnZoomIn        = document.getElementById('btn-zoom-in');
  const btnZoomOut       = document.getElementById('btn-zoom-out');
  const btnCropRotate    = document.getElementById('btn-crop-rotate');
  const btnCropReset     = document.getElementById('btn-crop-reset');

  function definirFotoOcupada(ocupada) {
    avatarOperacaoOcupada = ocupada;
    isDragging = false;
    [fileInput, btnAdjustFraming, btnRemovePhoto, btnApplyCrop, btnCloseCrop,
      btnCancelCrop, cropZoomRange, btnZoomIn, btnZoomOut, btnCropRotate, btnCropReset]
      .forEach(el => { if (el) el.disabled = ocupada; });
    const rotulo = btnApplyCrop && btnApplyCrop.querySelector('span');
    if (rotulo) rotulo.textContent = ocupada ? 'Salvando foto…' : rotuloAplicarCrop;
    if (badgeOptim) badgeOptim.setAttribute('aria-disabled', String(ocupada));
    if (cropViewport) cropViewport.setAttribute('aria-disabled', String(ocupada));
    if (cropModal) cropModal.setAttribute('aria-busy', String(ocupada));
  }

  // Estado interativo do Crop
  let cropImg = new Image();
  let isImageReady = false;
  let panX = 0;
  let panY = 0;
  let zoom = 1.0;
  let rotation = 0;
  let baseScale = 1.0;
  const viewportSize = 280;
  const cropRadius = 120; // 240px de diâmetro útil
  let isDragging = false;
  let dragStartX = 0;
  let dragStartY = 0;

  function renderCrop() {
    if (!isImageReady || !cropCanvas || !cropMiniPreview) return;

    const w = cropImg.naturalWidth || cropImg.width;
    const h = cropImg.naturalHeight || cropImg.height;

    // 1. Canvas principal do viewport
    const ctx = cropCanvas.getContext('2d');
    ctx.clearRect(0, 0, viewportSize, viewportSize);
    ctx.save();
    ctx.translate(viewportSize / 2 + panX, viewportSize / 2 + panY);
    ctx.rotate((rotation * Math.PI) / 180);
    const s = baseScale * zoom;
    ctx.scale(s, s);
    ctx.drawImage(cropImg, -w / 2, -h / 2, w, h);
    ctx.restore();

    // 2. Mini prévia redonda (64x64)
    const mCtx = cropMiniPreview.getContext('2d');
    const mSize = 64;
    mCtx.clearRect(0, 0, mSize, mSize);
    mCtx.save();
    mCtx.beginPath();
    mCtx.arc(mSize / 2, mSize / 2, mSize / 2, 0, Math.PI * 2);
    mCtx.clip();
    const factor = mSize / (cropRadius * 2);
    mCtx.translate(mSize / 2 + panX * factor, mSize / 2 + panY * factor);
    mCtx.rotate((rotation * Math.PI) / 180);
    const ms = baseScale * zoom * factor;
    mCtx.scale(ms, ms);
    mCtx.drawImage(cropImg, -w / 2, -h / 2, w, h);
    mCtx.restore();
  }

  function updateBaseScale() {
    const isPerp = rotation % 180 !== 0;
    const w = cropImg.naturalWidth || cropImg.width;
    const h = cropImg.naturalHeight || cropImg.height;
    const effW = isPerp ? h : w;
    const effH = isPerp ? w : h;
    baseScale = Math.max((cropRadius * 2) / effW, (cropRadius * 2) / effH);
  }

  function openCropModal(sourceSrc) {
    if (avatarOperacaoOcupada || !cropModal) return;
    const src = sourceSrc || avatarAtual || AVATAR_PADRAO;

    isImageReady = false;
    panX = 0;
    panY = 0;
    zoom = 1.0;
    rotation = 0;

    if (cropZoomRange) cropZoomRange.value = 1;
    if (cropZoomLabel) cropZoomLabel.textContent = '100%';

    cropModal.classList.add('active');
    cropModal.setAttribute('aria-hidden', 'false');

    cropImg = new Image();
    cropImg.crossOrigin = 'anonymous';
    cropImg.onload = () => {
      isImageReady = true;
      updateBaseScale();
      renderCrop();
    };
    cropImg.onerror = () => {
      if (cropImg.crossOrigin) {
        cropImg.crossOrigin = null;
        cropImg.src = src;
      } else {
        showToast('Não foi possível carregar a imagem para ajuste.', 'error');
        closeCropModal();
      }
    };
    cropImg.src = src;
  }

  function closeCropModal() {
    if (avatarOperacaoOcupada || !cropModal) return;
    cropModal.classList.remove('active');
    cropModal.setAttribute('aria-hidden', 'true');
  }

  function ajustarEnquadramento() {
    if (avatarOperacaoOcupada) return uploadEmAndamento;
    if (!isImageReady) return Promise.resolve();

    definirFotoOcupada(true);
    marcarStatus('Salvando…', '#b45309');
    let sucesso = false;

    // Falhas síncronas do canvas também precisam liberar a trava.
    uploadEmAndamento = Promise.resolve().then(() => {
      const exportSize = 500;
      const expCanvas = document.createElement('canvas');
      expCanvas.width = exportSize;
      expCanvas.height = exportSize;
      const eCtx = expCanvas.getContext('2d');

      // Fundo branco sólido
      eCtx.fillStyle = '#ffffff';
      eCtx.fillRect(0, 0, exportSize, exportSize);

      eCtx.save();
      const exportScale = exportSize / (cropRadius * 2);
      eCtx.translate(exportSize / 2 + panX * exportScale, exportSize / 2 + panY * exportScale);
      eCtx.rotate((rotation * Math.PI) / 180);
      const s = baseScale * zoom * exportScale;
      eCtx.scale(s, s);
      const w = cropImg.naturalWidth || cropImg.width;
      const h = cropImg.naturalHeight || cropImg.height;
      eCtx.drawImage(cropImg, -w / 2, -h / 2, w, h);
      eCtx.restore();

      return blobDoCanvas(expCanvas);
    })
      .then(enviarFotoDePerfil)
      .then(url => {
        avatarAtual = url;
        avatarAlteradoLocal = true;
        sucesso = true;
        desenharAvatar(Date.now());
        sincronizarSessao();
        marcarStatus('Otimizado', '#059669');
        showToast('Foto de perfil salva no servidor.');
      })
      .catch(err => {
        marcarStatusAvatar();
        showToast(err && err.message ? err.message : 'Não foi possível enviar sua foto. Tente novamente.', 'error');
      })
      .finally(() => {
        definirFotoOcupada(false);
        if (sucesso) closeCropModal();
      });
    return uploadEmAndamento;
  }

  // Listeners do enquadramento
  function abrirSeletorDeArquivo() {
    if (!avatarOperacaoOcupada && fileInput) fileInput.click();
  }

  if (btnAdjustFraming) {
    btnAdjustFraming.addEventListener('click', () => (temFotoPropria() ? openCropModal() : abrirSeletorDeArquivo()));
  }
  if (badgeOptim) {
    badgeOptim.addEventListener('click', () => (temFotoPropria() ? openCropModal() : abrirSeletorDeArquivo()));
    badgeOptim.addEventListener('keydown', e => {
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        temFotoPropria() ? openCropModal() : abrirSeletorDeArquivo();
      }
    });
  }
  if (btnCloseCrop)  btnCloseCrop.addEventListener('click', closeCropModal);
  if (btnCancelCrop) btnCancelCrop.addEventListener('click', closeCropModal);
  if (btnApplyCrop)  btnApplyCrop.addEventListener('click', ajustarEnquadramento);

  if (cropModal) {
    cropModal.addEventListener('click', e => {
      if (e.target === cropModal) closeCropModal();
    });
  }
  document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && cropModal?.classList.contains('active')) {
      closeCropModal();
    }
  });

  // Zoom range e botões
  if (cropZoomRange) {
    cropZoomRange.addEventListener('input', e => {
      if (avatarOperacaoOcupada) return;
      zoom = parseFloat(e.target.value);
      if (cropZoomLabel) cropZoomLabel.textContent = `${Math.round(zoom * 100)}%`;
      renderCrop();
    });
  }
  if (btnZoomIn) {
    btnZoomIn.addEventListener('click', () => {
      if (avatarOperacaoOcupada) return;
      zoom = Math.min(3, +(zoom + 0.1).toFixed(2));
      if (cropZoomRange) cropZoomRange.value = zoom;
      if (cropZoomLabel) cropZoomLabel.textContent = `${Math.round(zoom * 100)}%`;
      renderCrop();
    });
  }
  if (btnZoomOut) {
    btnZoomOut.addEventListener('click', () => {
      if (avatarOperacaoOcupada) return;
      zoom = Math.max(1, +(zoom - 0.1).toFixed(2));
      if (cropZoomRange) cropZoomRange.value = zoom;
      if (cropZoomLabel) cropZoomLabel.textContent = `${Math.round(zoom * 100)}%`;
      renderCrop();
    });
  }
  if (btnCropRotate) {
    btnCropRotate.addEventListener('click', () => {
      if (avatarOperacaoOcupada) return;
      rotation = (rotation + 90) % 360;
      updateBaseScale();
      renderCrop();
    });
  }
  if (btnCropReset) {
    btnCropReset.addEventListener('click', () => {
      if (avatarOperacaoOcupada) return;
      rotation = 0;
      zoom = 1.0;
      panX = 0;
      panY = 0;
      updateBaseScale();
      if (cropZoomRange) cropZoomRange.value = 1;
      if (cropZoomLabel) cropZoomLabel.textContent = '100%';
      renderCrop();
    });
  }

  // Pan / arrastar foto no viewport
  if (cropViewport) {
    cropViewport.addEventListener('mousedown', e => {
      if (avatarOperacaoOcupada) return;
      isDragging = true;
      dragStartX = e.clientX;
      dragStartY = e.clientY;
    });

    window.addEventListener('mousemove', e => {
      if (avatarOperacaoOcupada || !isDragging) return;
      const dx = e.clientX - dragStartX;
      const dy = e.clientY - dragStartY;
      dragStartX = e.clientX;
      dragStartY = e.clientY;
      panX += dx;
      panY += dy;
      renderCrop();
    });

    window.addEventListener('mouseup', () => { isDragging = false; });

    // Touch
    cropViewport.addEventListener('touchstart', e => {
      if (avatarOperacaoOcupada) return;
      if (e.touches.length === 1) {
        isDragging = true;
        dragStartX = e.touches[0].clientX;
        dragStartY = e.touches[0].clientY;
      }
    }, { passive: true });

    cropViewport.addEventListener('touchmove', e => {
      if (avatarOperacaoOcupada) return;
      if (isDragging && e.touches.length === 1) {
        const dx = e.touches[0].clientX - dragStartX;
        const dy = e.touches[0].clientY - dragStartY;
        dragStartX = e.touches[0].clientX;
        dragStartY = e.touches[0].clientY;
        panX += dx;
        panY += dy;
        renderCrop();
      }
    }, { passive: true });

    cropViewport.addEventListener('touchend', () => { isDragging = false; });

    // Wheel zoom
    cropViewport.addEventListener('wheel', e => {
      e.preventDefault();
      if (avatarOperacaoOcupada) return;
      const delta = e.deltaY < 0 ? 0.08 : -0.08;
      zoom = Math.max(1, Math.min(3, +(zoom + delta).toFixed(2)));
      if (cropZoomRange) cropZoomRange.value = zoom;
      if (cropZoomLabel) cropZoomLabel.textContent = `${Math.round(zoom * 100)}%`;
      renderCrop();
    }, { passive: false });
  }

  // Seleção de foto pelo ícone/avatar: pré-visualiza no modal e grava só após confirmar
  const FORMATOS_PERMITIDOS = ['image/jpeg', 'image/png', 'image/webp'];

  if (fileInput) {
    fileInput.addEventListener('change', e => {
      if (avatarOperacaoOcupada) { fileInput.value = ''; return; }
      const file = e.target.files[0];
      if (!file) return;
      if (FORMATOS_PERMITIDOS.indexOf(file.type) === -1) {
        showToast('Formato de imagem inválido. Envie uma imagem JPG, PNG ou WebP.', 'error');
        fileInput.value = '';
        return;
      }
      if (file.size > 5 * 1024 * 1024) {
        showToast('O arquivo enviado ultrapassa o tamanho máximo permitido (5MB).', 'error');
        fileInput.value = '';
        return;
      }
      const reader = new FileReader();
      reader.onload = ev => {
        // A dataURL vive apenas dentro do modal; ela nunca vira o avatar da página
        openCropModal(ev.target.result);
      };
      reader.onerror = () => showToast('Não foi possível ler o arquivo escolhido. Tente novamente.', 'error');
      reader.readAsDataURL(file);
      fileInput.value = '';
    });
  }

  // Remover foto: grava a volta à foto padrão e remove os bytes do avatar no banco.
  if (btnRemovePhoto) {
    btnRemovePhoto.addEventListener('click', () => {
      if (avatarOperacaoOcupada) return uploadEmAndamento;
      if (!temFotoPropria()) {
        showToast('Você já está usando a foto padrão do Passe Livre.');
        return;
      }
      if (!confirm('Deseja remover sua foto e voltar à foto padrão?')) return;

      definirFotoOcupada(true);
      marcarStatus('Removendo…', '#b45309');
      uploadEmAndamento = Promise.resolve().then(async () => {
        const res  = await fetch('api/users.php?action=remove_avatar', { method: 'POST', credentials: 'same-origin', headers: headersDaFoto() });
        const dados = await res.json().catch(() => null);
        if (!res.ok || !dados || !dados.success) {
          throw new Error(dados && dados.error ? dados.error : 'Não foi possível remover sua foto de perfil.');
        }
        const avatar = _normalizaAvatar(dados.avatar);
        if (typeof dados.avatar !== 'string' || avatar !== dados.avatar.trim() ||
            avatar.split('?')[0] !== AVATAR_PADRAO.split('?')[0]) {
          throw new Error('Não foi possível remover sua foto de perfil.');
        }
        avatarAtual = avatar;
        avatarAlteradoLocal = true;
        desenharAvatar();
        sincronizarSessao();
        marcarStatusAvatar();
        showToast('Foto removida. A foto padrão voltou a ser usada.');
      }).catch(err => {
        marcarStatusAvatar();
        showToast(err && err.message ? err.message : 'Não foi possível remover sua foto de perfil.', 'error');
      }).finally(() => {
        definirFotoOcupada(false);
      });
      return uploadEmAndamento;
    });
  }

  // =========================================================================
  // 2. PÍLULAS DE ACESSIBILIDADE
  // =========================================================================
  const a11yEmptyState = document.getElementById('accessibility-empty-state');
  const needsTextarea   = document.getElementById('textarea-needs');

  function updateA11yEmptyState() {
    if (!a11yEmptyState) return;
    const hasPills = !!document.querySelector('.pill-option-btn.selected');
    const hasDesc  = !!(needsTextarea && needsTextarea.value.trim());
    a11yEmptyState.hidden = hasPills || hasDesc;
  }

  document.querySelectorAll('.pill-option-btn').forEach(pill => {
    pill.addEventListener('click', () => {
      const value = pill.getAttribute('data-value');
      if (value === 'nao-informar') {
        document.querySelectorAll('.pill-option-btn').forEach(p => p.classList.remove('selected'));
        pill.classList.add('selected');
      } else {
        document.querySelector('.pill-option-btn[data-value="nao-informar"]')?.classList.remove('selected');
        pill.classList.toggle('selected');
      }
      updateA11yEmptyState();
      salvarPerfil(false);
    });
  });

  if (needsTextarea) {
    needsTextarea.addEventListener('input', updateA11yEmptyState);
  }
  updateA11yEmptyState();

  // =========================================================================
  // 3. PREFERÊNCIAS URBANAS
  // =========================================================================
  document.querySelectorAll('.preference-pill-check').forEach(pref => {
    pref.addEventListener('click', () => {
      pref.classList.toggle('active');
      salvarPerfil(false);
    });
  });

  // =========================================================================
  // 4. CHECKBOXES DE PRIVACIDADE LGPD
  // =========================================================================
  document.querySelectorAll('.privacy-checkbox-custom').forEach(cb => {
    cb.addEventListener('click', () => {
      const checked = cb.classList.toggle('checked');
      cb.setAttribute('aria-checked', checked ? 'true' : 'false');
    });
    cb.addEventListener('keydown', e => { if (e.key === ' ' || e.key === 'Enter') { e.preventDefault(); cb.click(); } });
  });

  // =========================================================================
  // 5. SALVAMENTO AUTOMÁTICO — UPDATE SQL
  //    Reaproveita o endpoint que era do botão "Salvar alterações"
  //    (api/users.php?action=update_profile), enviando só os campos que
  //    mudaram. A foto não passa por aqui: seus bytes e referência já foram
  //    gravados no banco por api/upload.php.
  // =========================================================================
  const CAMPOS_TEXTO = ['input-fullname', 'input-phone', 'input-city', 'input-address', 'textarea-needs'];
  const syncTag      = document.querySelector('.last-sync-tag span');
  const DIGITACAO_MS = 700;

  let ultimoEstado     = {};
  let estadoDoEnvio    = null;
  let deltaDoEnvio     = null;
  let salvandoPerfil   = false;
  let salvamentoNaFila = false;
  let timerDigitacao   = null;

  function _val(id) {
    const el = document.getElementById(id);
    return el ? String(el.value).trim() : '';
  }

  function estadoDoPerfil() {
    const pcd = [];
    document.querySelectorAll('.pill-option-btn.selected').forEach(p => {
      const v = p.getAttribute('data-value');
      if (v && v !== 'nao-informar') pcd.push(v);
    });

    const prefs = [];
    document.querySelectorAll('.preference-pill-check.active').forEach(p => {
      const v = p.getAttribute('data-pref');
      if (v) prefs.push(v);
    });

    return {
      name:        _val('input-fullname') || _val('input-profile-name'),
      phone:       _val('input-phone')    || _val('input-profile-phone'),
      city:        _val('input-city')     || _val('input-profile-city') || 'Suzano - SP',
      bairro:      _val('input-address'),
      description: _val('textarea-needs'),
      pcd_categories: pcd,
      saved_prefs:    prefs
    };
  }

  function diferencias(antes, depois) {
    const delta = {};
    Object.keys(depois).forEach(campo => {
      if (JSON.stringify(antes[campo]) !== JSON.stringify(depois[campo])) delta[campo] = depois[campo];
    });
    return delta;
  }

  function marcarSincronizacao(texto) {
    if (syncTag) syncTag.textContent = texto;
  }

  function horaAtual() {
    const agora = new Date();
    return `${String(agora.getHours()).padStart(2,'0')}h${String(agora.getMinutes()).padStart(2,'0')}`;
  }

  async function salvarPerfil(aoSair) {
    clearTimeout(timerDigitacao);
    timerDigitacao = null;

    // Duas alterações durante um request em aberto viram um único reenvio com os
    // valores mais recentes, em vez de duas escritas concorrentes no mesmo banco.
    if (salvandoPerfil) {
      salvamentoNaFila = true;
      return;
    }

    const depois = estadoDoPerfil();
    const delta  = diferencias(ultimoEstado, depois);
    if (!Object.keys(delta).length) return;

    salvandoPerfil = true;
    estadoDoEnvio  = depois;
    deltaDoEnvio   = delta;
    marcarSincronizacao('Salvando…');

    try {
      const res  = await fetch('api/users.php?action=update_profile', {
        method:      'POST',
        credentials: 'same-origin',
        headers:     { 'Content-Type': 'application/json' },
        body:        JSON.stringify(delta),
        keepalive:   aoSair === true
      });
      const resJson = await res.json().catch(() => ({}));
      if (resJson.success) {
        // Só o que foi realmente gravado sai da lista de pendências: se o usuário
        // mexeu no mesmo campo durante o envio, a alteração continua na fila.
        Object.keys(deltaDoEnvio).forEach(campo => { ultimoEstado[campo] = estadoDoEnvio[campo]; });
        marcarSincronizacao(`Alterações salvas às ${horaAtual()}`);
      } else {
        marcarSincronizacao('Não foi possível salvar');
        if (resJson.error) showToast(resJson.error, 'error');
      }
    } catch (apiErr) {
      console.warn('[Perfil] api/users.php falhou:', apiErr);
      marcarSincronizacao('Não foi possível salvar');
    } finally {
      estadoDoEnvio  = null;
      deltaDoEnvio   = null;
      salvandoPerfil = false;
      if (salvamentoNaFila) {
        salvamentoNaFila = false;
        salvarPerfil(false);
      }
    }
  }

  function agendarSalvamento() {
    clearTimeout(timerDigitacao);
    timerDigitacao = setTimeout(() => salvarPerfil(false), DIGITACAO_MS);
  }

  CAMPOS_TEXTO.forEach(id => {
    const campo = document.getElementById(id);
    if (!campo) return;
    campo.addEventListener('input', agendarSalvamento);
    campo.addEventListener('change', () => salvarPerfil(false));
  });

  // Sair da tela com texto ainda no debounce: keepalive entrega o último valor
  // antes de o navegador descartar a página.
  window.addEventListener('pagehide', () => {
    if (timerDigitacao !== null) salvarPerfil(true);
  });

  ultimoEstado = estadoDoPerfil();

  // =========================================================================
  // 7. NOTIFICAÇÕES
  // =========================================================================
  document.getElementById('btn-notify-profile')?.addEventListener('click', () => showToast('Cadastro aprovado sem pendências documentais.'));

  // =========================================================================
  // HELPERS
  // =========================================================================
  function _setVal(id, val) { const el = document.getElementById(id); if (el) el.value = val; }

  // Reaproveita o toast global do auth.js. Não delegar para `window.showToast`:
  // esta função também é atribuída a esse nome e a delegação viraria recursão
  // infinita (RangeError), silenciosamente engolida pelos .catch() do upload.
  function showToast(msg, type) {
    const global = window.PasseLivreAuth && window.PasseLivreAuth.showToast;
    if (typeof global === 'function') { global(msg, type); return; }
    const t = document.getElementById('app-toast'), m = document.getElementById('toast-message');
    if (t && m) { m.textContent = msg; t.classList.add('visible'); setTimeout(()=>t.classList.remove('visible'),3800); }
  }

  preloadUserData();
});
