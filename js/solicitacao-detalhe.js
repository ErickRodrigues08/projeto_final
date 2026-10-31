/**
 * Passe Livre Suzano — Dossiê Individual da Solicitação
 * Comunicação direta: Frontend → API (api/solicitacoes.php) → MySQL
 * Eliminação total de mock-data.js
 */

document.addEventListener('DOMContentLoaded', () => {

  // =========================================================================
  // 1. LIGHTBOX DE EVIDÊNCIAS FOTOGRÁFICAS
  // =========================================================================
  const lightboxModal = document.getElementById('modal-evidence-lightbox');
  const lightboxImg   = document.getElementById('lightbox-img');
  const lightboxCap   = document.getElementById('lightbox-caption-text');

  window.openLightbox = function(src, caption) {
    if (!lightboxModal) return;
    if (lightboxImg) lightboxImg.src = src;
    if (lightboxCap) lightboxCap.textContent = caption || 'Evidência fotográfica';
    lightboxModal.classList.add('active');
  };

  window.closeLightbox = function() {
    if (lightboxModal) lightboxModal.classList.remove('active');
  };

  if (lightboxModal) {
    lightboxModal.addEventListener('click', (e) => {
      if (e.target === lightboxModal) window.closeLightbox();
    });
  }

  // =========================================================================
  // 2. MODELOS RÁPIDOS DE DESPACHO
  // =========================================================================
  window.applyTemplate = function(tipo) {
    const notesArea = document.getElementById('dispatch-notes');
    if (!notesArea) return;

    const templates = {
      troca: 'Vistoria técnica preliminar confirmou defeito no sinal sonoro APS. Recomendada a substituição emergencial do módulo acústico e revisão da fiação conforme NBR 9050.',
      vistoria: 'Encaminhar equipe de engenharia viária para vistoria presencial no trecho informado. Emitir laudo de declividade e largura útil do passeio público em até 48 horas.',
      rebaixamento: 'Trecho necessita de obra de rebaixamento de guia para travessia acessível de cadeirantes e pessoas com mobilidade reduzida. Encaminhar para programação de serviços da SEMU.'
    };

    if (templates[tipo]) {
      notesArea.value = templates[tipo];
      notesArea.focus();
    }
  };

  // =========================================================================
  // 3. COPIAR CONTATO
  // =========================================================================
  window.copyContact = function(tipo) {
    let text = '';
    if (tipo === 'email') {
      text = document.getElementById('user-display-email')?.textContent?.trim() || '';
    } else if (tipo === 'phone') {
      text = document.getElementById('user-display-phone')?.textContent?.trim() || '';
    }

    if (text) {
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(() => {
          showToast(`Copiado para a área de transferência: ${text}`);
        }).catch(() => {
          showToast(`Texto: ${text}`);
        });
      } else {
        showToast(`Texto: ${text}`);
      }
    }
  };

  // =========================================================================
  // 4. AÇÕES DE TRIAGEM (API → MySQL)
  // =========================================================================
  window.triageSolicitacao = async function(actionType) {
    const solId = document.getElementById('solicitacao-id-hidden')?.value;
    if (!solId) {
      showToast('ID da solicitação não encontrado.');
      return;
    }

    const statusMap = {
      'aprovar':   'approved',
      'reanalise': 'analyzing',
      'recusar':   'rejected'
    };

    const newStatus = statusMap[actionType];
    if (!newStatus) return;

    const notes = document.getElementById('dispatch-notes')?.value?.trim() || '';

    // Confirmação para indeferimento
    if (actionType === 'recusar' && !confirm('Tem certeza que deseja indeferir esta solicitação?')) {
      return;
    }

    try {
      const response = await fetch('api/solicitacoes.php?action=update_status', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          id: parseInt(solId, 10),
          status: newStatus,
          notes: notes
        })
      });

      const data = await response.json();

      if (data.success) {
        showToast(data.message || 'Status atualizado com sucesso!');

        // Atualiza a pílula de status na interface
        const statusContainer = document.getElementById('req-display-status');
        if (statusContainer) {
          const names = {
            'approved':  'Aprovada / O.S. Expedida',
            'analyzing': 'Em Análise Técnica',
            'rejected':  'Indeferida',
            'pending':   'Pendente de Triagem'
          };
          statusContainer.innerHTML = `<span class="status-badge-pill ${newStatus}">${names[newStatus] || newStatus}</span>`;
        }

        // Se foi aprovada, orienta a despachar a Ordem de Serviço
        if (actionType === 'aprovar') {
          const dispatchCard = document.querySelector('.dispatch-action-card');
          if (dispatchCard) {
            dispatchCard.scrollIntoView({ behavior: 'smooth' });
          }
        }
      } else {
        showToast('Erro: ' + (data.error || 'Falha ao atualizar status.'));
      }
    } catch (err) {
      console.error('[solicitacao-detalhe] Erro na API update_status:', err);
      showToast('Erro de comunicação com o servidor MySQL.');
    }
  };

  // Mantém compatibilidade com chamadas legado
  window.triageAction = window.triageSolicitacao;

  // =========================================================================
  // 5. DESPACHO DE ORDEM DE SERVIÇO (API → MySQL)
  // =========================================================================
  window.despacharOrdemServico = async function() {
    const solId     = document.getElementById('solicitacao-id-hidden')?.value;
    const orgao     = document.getElementById('dispatch-department')?.value;
    const fiscal    = document.getElementById('dispatch-tech')?.value;
    const prioridade= document.getElementById('dispatch-priority')?.value;
    const parecer   = document.getElementById('dispatch-notes')?.value?.trim();

    if (!solId) {
      showToast('ID da solicitação inválido.');
      return;
    }

    if (!parecer) {
      showToast('Por favor, informe o parecer pericial para emitir a Ordem de Serviço.');
      document.getElementById('dispatch-notes')?.focus();
      return;
    }

    const btnSubmit = document.getElementById('btn-save-dispatch');
    if (btnSubmit) {
      btnSubmit.disabled = true;
      btnSubmit.innerHTML = '<span>Emitindo Ordem de Serviço...</span>';
    }

    try {
      const response = await fetch('api/solicitacoes.php?action=despachar_os', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          solicitacao_id: parseInt(solId, 10),
          orgao_responsavel: orgao,
          fiscal_responsavel: fiscal,
          prioridade: prioridade,
          parecer: parecer
        })
      });

      const data = await response.json();

      if (data.success) {
        showToast(data.message || 'Ordem de Serviço expedida com sucesso!');
        setTimeout(() => {
          window.location.reload();
        }, 1200);
      } else {
        showToast('Erro: ' + (data.error || 'Falha ao despachar Ordem de Serviço.'));
        if (btnSubmit) {
          btnSubmit.disabled = false;
          btnSubmit.innerHTML = '<span>Despachar Ordem de Serviço &rarr;</span>';
        }
      }
    } catch (err) {
      console.error('[solicitacao-detalhe] Erro na API despachar_os:', err);
      showToast('Erro de comunicação com o servidor.');
      if (btnSubmit) {
        btnSubmit.disabled = false;
        btnSubmit.innerHTML = '<span>Despachar Ordem de Serviço &rarr;</span>';
      }
    }
  };

  // =========================================================================
  // 6. BUSCA NO HEADER (Por Protocolo ou ID)
  // =========================================================================
  const searchInput = document.getElementById('input-search-dossier');
  if (searchInput) {
    searchInput.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') {
        const val = searchInput.value.trim();
        if (val) {
          if (!isNaN(val)) {
            window.location.href = `solicitacao-detalhe.php?id=${encodeURIComponent(val)}`;
          } else {
            window.location.href = `solicitacao-detalhe.php?protocol=${encodeURIComponent(val)}`;
          }
        }
      }
    });
  }

  // =========================================================================
  // 7. MINIMAPA DO PONTO DA SOLICITAÇÃO
  // =========================================================================
  // Só existe mapa quando a solicitação tem coordenadas no banco. Sem elas a
  // própria página já mostra o aviso de endereço não localizado.
  const minimapEl = document.getElementById('sol-minimap');
  if (minimapEl && typeof window.L !== 'undefined') {
    const lat = parseFloat(minimapEl.dataset.lat);
    const lng = parseFloat(minimapEl.dataset.lng);
    if (Number.isFinite(lat) && Number.isFinite(lng)) {
      const mini = L.map(minimapEl, {
        scrollWheelZoom: false,
        zoomControl: true,
        attributionControl: true
      }).setView([lat, lng], 17);

      // Raster do OpenStreetMap: a camada CARTO deste projeto perdeu o acesso
      // sem chave e respondia "API KEY REQUIRED" em todos os tiles.
      L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
      }).addTo(mini);

      const marcador = L.marker([lat, lng]).addTo(mini);
      const endereco = document.getElementById('req-display-address');
      if (endereco && endereco.textContent.trim() !== '') {
        marcador.bindPopup(endereco.textContent.trim());
      }

      setTimeout(() => mini.invalidateSize(), 200);
    }
  }

  // =========================================================================
  // 8. TOAST GLOBAL
  // =========================================================================
  function showToast(msg) {
    const toast = document.getElementById('app-toast');
    const msgEl = document.getElementById('toast-message');
    if (toast && msgEl) {
      msgEl.textContent = msg;
      toast.classList.add('visible');
      setTimeout(() => toast.classList.remove('visible'), 3800);
    } else {
      alert(msg);
    }
  }
  window.showToast = showToast;
});
