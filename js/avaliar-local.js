/**
 * Passe Livre Suzano — Avaliação de Local (Fiscalização Colaborativa)
 * Integração SQL: INSERT na tabela `avaliacoes` via PLDB.avaliacoes.create()
 */

/* URLs devolvidas por api/upload.php para as fotos escolhidas nesta sessão */
const fotosDaAvaliacao = [];
/* Promessas de upload em andamento: o envio aguarda todas antes de salvar */
const uploadsEmAndamento = [];

/* Grava a imagem no servidor; em qualquer falha segue só com a pré-visualização local */
async function enviarFotoParaServidor(file, card) {
  try {
    const form = new FormData();
    form.append('foto', file);
    const resposta = await fetch('api/upload.php', { method: 'POST', body: form });
    const dados = await resposta.json();
    if (!resposta.ok || !dados || !dados.success || !dados.url) return;
    if (card) card.dataset.uploadUrl = dados.url;
    fotosDaAvaliacao.push(dados.url);
  } catch (err) {
    /* silencioso: a avaliação continua funcionando sem o upload */
  }
}

document.addEventListener('DOMContentLoaded', () => {
  initCriteriaSelection();
  initRatingStars();
  initBarrierToggleAndCounter();
  initReviewCommentCounter();
  initPhotoUploadAndDropzone();
  initFormSubmission();
  initGlobalSearch();
});

/* ==========================================================================
   1. SELEÇÃO DE STATUS DOS CRITÉRIOS (NBR 9050)
   ========================================================================== */
function initCriteriaSelection() {
  document.querySelectorAll('.criteria-card').forEach(card => {
    const pills = card.querySelectorAll('.btn-criteria-pill');
    pills.forEach(pill => {
      pill.addEventListener('click', () => {
        pills.forEach(p => { p.classList.remove('active-orange','active-red','active-gray'); p.setAttribute('aria-checked','false'); });
        const value = pill.getAttribute('data-value');
        pill.classList.add(value === 'inacessivel' ? 'active-red' : value === 'nao-se-aplica' ? 'active-gray' : 'active-orange');
        pill.setAttribute('aria-checked','true');
      });
    });
  });
}

/* ==========================================================================
   1.1 NOTA GERAL (1 A 5 ESTRELAS)
   ========================================================================== */
function initRatingStars() {
  const stars = Array.from(document.querySelectorAll('.btn-star-input'));
  const input = document.getElementById('review-rating-value');
  if (!stars.length || !input) return;

  function paint(value) {
    stars.forEach(star => {
      const starValue = Number(star.dataset.value);
      const selected = starValue === value;
      star.classList.toggle('active', starValue <= value);
      star.setAttribute('aria-checked', String(selected));
      star.tabIndex = selected ? 0 : -1;
    });
    input.value = String(value);
  }

  stars.forEach(star => {
    star.addEventListener('click', () => paint(Number(star.dataset.value)));
    star.addEventListener('keydown', event => {
      const current = Number(star.dataset.value);
      let next = null;
      if (event.key === 'ArrowRight' || event.key === 'ArrowUp') next = Math.min(5, current + 1);
      if (event.key === 'ArrowLeft' || event.key === 'ArrowDown') next = Math.max(1, current - 1);
      if (next === null) return;
      event.preventDefault();
      paint(next);
      stars[next - 1]?.focus();
    });
  });

  paint(Math.min(5, Math.max(1, Number(input.value) || 5)));
}

/* ==========================================================================
   2. TOGGLE DE BARREIRA + CONTADOR DE CARACTERES
   ========================================================================== */
function initBarrierToggleAndCounter() {
  const toggleBarrier   = document.getElementById('toggle-barrier');
  const barrierBox      = document.getElementById('barrier-box');
  const barrierTextarea = document.getElementById('barrier-description');
  const charCounter     = document.getElementById('barrier-chars-count');

  if (toggleBarrier && barrierBox) {
    toggleBarrier.addEventListener('change', () => {
      const show = toggleBarrier.checked;
      barrierBox.classList.toggle('is-hidden', !show);
      barrierBox.style.display = show ? 'flex' : 'none';
    });
  }
  if (barrierTextarea && charCounter) {
    charCounter.textContent = barrierTextarea.value.length;
    barrierTextarea.addEventListener('input', () => { charCounter.textContent = barrierTextarea.value.length; });
  }
}

function initReviewCommentCounter() {
  const commentTextarea = document.getElementById('review-comment');
  const commentCounter  = document.getElementById('review-comment-chars-count');
  if (!commentTextarea || !commentCounter) return;
  commentCounter.textContent = commentTextarea.value.length;
  commentTextarea.addEventListener('input', () => { commentCounter.textContent = commentTextarea.value.length; });
}

/* ==========================================================================
   3. UPLOAD DE FOTOS (DRAG & DROP + INPUT)
   ========================================================================== */
function initPhotoUploadAndDropzone() {
  const dropzone   = document.getElementById('photo-dropzone');
  const fileInput  = document.getElementById('file-input-photos');
  const browseBtn  = document.getElementById('btn-browse-photos');
  const photosList = document.getElementById('uploaded-photos-list');
  if (!dropzone || !fileInput) return;

  dropzone.addEventListener('click', e => { if (e.target !== fileInput) fileInput.click(); });
  if (browseBtn) browseBtn.addEventListener('click', e => { e.stopPropagation(); fileInput.click(); });

  ['dragenter','dragover'].forEach(ev => dropzone.addEventListener(ev, e => { e.preventDefault(); e.stopPropagation(); dropzone.classList.add('dragover'); }));
  ['dragleave','drop'].forEach(ev => dropzone.addEventListener(ev, e => { e.preventDefault(); e.stopPropagation(); dropzone.classList.remove('dragover'); }));
  dropzone.addEventListener('drop', e => { if (e.dataTransfer?.files?.length) handleFiles(e.dataTransfer.files); });
  fileInput.addEventListener('change', () => { if (fileInput.files?.length) handleFiles(fileInput.files); });

  if (photosList) {
    photosList.addEventListener('click', e => {
      const del = e.target.closest('.btn-delete-photo');
      if (del) {
        const card = del.closest('.uploaded-photo-card');
        if (card) {
          const indice = fotosDaAvaliacao.indexOf(card.dataset.uploadUrl);
          if (indice >= 0) fotosDaAvaliacao.splice(indice, 1);
          card.style.opacity='0'; card.style.transform='scale(0.95)'; card.style.transition='all 0.2s'; setTimeout(() => card.remove(), 200);
        }
      }
    });
  }

  function handleFiles(files) {
    Array.from(files).forEach(file => {
      if (!file.type.startsWith('image/')) return;
      const reader = new FileReader();
      reader.onload = ev => {
        const card = document.createElement('div');
        card.className = 'uploaded-photo-card';
        card.innerHTML = `
          <div class="uploaded-photo-left">
            <img src="${ev.target.result}" alt="Miniatura de ${file.name}" class="uploaded-photo-thumb">
            <div class="uploaded-photo-info">
              <span class="uploaded-photo-name">${file.name}</span>
              <span class="uploaded-photo-meta">${(file.size/1048576).toFixed(1)} MB • <span class="meta-geo-verified"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg> Metadados verificados</span></span>
            </div>
          </div>
          <button type="button" class="btn-delete-photo" title="Remover" aria-label="Remover imagem ${file.name}">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
          </button>`;
        photosList?.appendChild(card);
        uploadsEmAndamento.push(enviarFotoParaServidor(file, card));
      };
      reader.readAsDataURL(file);
    });
  }
}

/* ==========================================================================
   4. ENVIO DO FORMULÁRIO — POST api/avaliacoes.php (usuário da sessão)
   ========================================================================== */
function initFormSubmission() {
  const form         = document.getElementById('form-eval-location');
  const successModal = document.getElementById('success-modal');
  const btnSubmit    = document.getElementById('btn-submit-form');
  if (!form || !successModal) return;

  let enviando = false;

  function notify(message, type) {
    if (typeof window.showToast === 'function') {
      window.showToast(message, type);
    }
  }

  // Lê os critérios pelos data-criterion (independe da ordem visual dos cards)
  function getStatus(key) {
    const pill = document.querySelector(`.criteria-card[data-criterion="${key}"] .btn-criteria-pill[aria-checked="true"]`);
    return pill ? pill.getAttribute('data-value') : null;
  }

  form.addEventListener('submit', async e => {
    e.preventDefault();
    if (enviando) return;

    const localId = parseInt(form.dataset.localId || '0', 10);
    const localCode = form.dataset.localCode || '';
    const rating = parseInt(document.getElementById('review-rating-value')?.value || '0', 10);
    const comment = document.getElementById('review-comment')?.value.trim() || '';
    const barrierDesc = document.getElementById('barrier-description')?.value.trim() || '';

    if (!localId || localId <= 0) {
      notify('Não foi possível identificar o local avaliado. Recarregue a página e tente novamente.', 'error');
      return;
    }
    if (!Number.isInteger(rating) || rating < 1 || rating > 5) {
      notify('Escolha uma nota de 1 a 5 estrelas.', 'error');
      return;
    }

    enviando = true;
    const originalHtml = btnSubmit ? btnSubmit.innerHTML : '';
    if (btnSubmit) {
      btnSubmit.innerHTML = `<svg style="animation:spin 0.8s linear infinite;width:16px;height:16px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="2" x2="12" y2="6"/><line x1="12" y1="18" x2="12" y2="22"/><line x1="4.93" y1="4.93" x2="7.76" y2="7.76"/><line x1="16.24" y1="16.24" x2="19.07" y2="19.07"/></svg><span>Salvando no banco...</span>`;
      btnSubmit.disabled = true;
    }

    // Fotos escolhidas podem ainda estar subindo: aguarda antes de salvar
    if (uploadsEmAndamento.length) {
      try { await Promise.allSettled(uploadsEmAndamento.slice()); } catch (_) {}
    }

    const payload = {
      local_id:               localId,
      local_code:             localCode,
      rating,
      comment,
      barrier_desc:           barrierDesc,
      entrada_status:         getStatus('entrada'),
      rampas_status:          getStatus('rampas'),
      elevadores_status:      getStatus('elevadores'),
      banheiros_status:       getStatus('banheiros'),
      calcadas_status:        getStatus('calcadas'),
      estacionamento_status:  getStatus('estacionamento'),
      photos:                 fotosDaAvaliacao.slice()
    };

    try {
      const headers = { 'Content-Type': 'application/json', 'Accept': 'application/json' };
      const token = window.PasseLivreAuth?.getToken?.();
      if (token) headers['X-Session-Token'] = token;

      const response = await fetch('api/avaliacoes.php?action=create', {
        method: 'POST',
        headers,
        credentials: 'same-origin',
        body: JSON.stringify(payload)
      });

      let result = null;
      try { result = await response.json(); } catch (_) {}

      if (!response.ok || !result || result.success !== true) {
        throw new Error(result?.message || 'Não foi possível salvar sua avaliação. Tente novamente.');
      }
    } catch (err) {
      enviando = false;
      if (btnSubmit) {
        btnSubmit.innerHTML = originalHtml;
        btnSubmit.disabled = false;
      }
      notify(err.message || 'Falha ao salvar a avaliação. Tente novamente.', 'error');
      return;
    }

    const detailsLink = document.getElementById('btn-modal-return-nav');
    if (detailsLink) detailsLink.href = `detalhes-local.php?id=${localId}#reviews-heading`;

    successModal.style.display = '';
    successModal.hidden = false;
    successModal.classList.add('active');
    notify('Avaliação salva e publicada com sucesso!');
  });

  successModal.addEventListener('click', e => { if (e.target === successModal) successModal.classList.remove('active'); });
}

/* ==========================================================================
   5. BUSCA GLOBAL
   ========================================================================== */
function initGlobalSearch() {
  const searchInput = document.getElementById('global-search-input');
  if (searchInput) {
    searchInput.addEventListener('keydown', e => {
      if (e.key === 'Enter' && searchInput.value.trim()) {
        window.location.href = 'lugares-encontrados.php?q=' + encodeURIComponent(searchInput.value.trim());
      }
    });
  }
}
