/**
 * Passe Livre — Lógica do Painel do Gestor (Perfil do Usuário)
 */

document.addEventListener('DOMContentLoaded', () => {
  // 1. Alternância de Abas
  const tabButtons = document.querySelectorAll('.tab-nav-btn');
  tabButtons.forEach(btn => {
    btn.addEventListener('click', () => {
      tabButtons.forEach(b => b.classList.remove('active'));
      btn.classList.add('active');

      const tabId = btn.getAttribute('data-tab');
      if (tabId === 'solicitacoes') {
        showToast('Exibindo 4 solicitações de acessibilidade em Suzano');
      } else if (tabId === 'auditoria') {
        showToast('Exibindo trilha de auditoria e registros de segurança');
      } else {
        showToast('Exibindo dados cadastrais e laudos médicos');
      }
    });
  });

  // 3. Bloquear Conta
  const btnBlock = document.getElementById('btn-block-account');
  if (btnBlock) {
    btnBlock.addEventListener('click', () => {
      const confirmBlock = confirm('Tem certeza que deseja bloquear preventivamente esta conta no sistema Passe Livre Suzano?');
      if (confirmBlock) {
        showToast('Conta bloqueada temporariamente para análise pericial.');
      }
    });
  }

  // 4. Log Completo de Auditoria
  const btnAudit = document.getElementById('btn-full-audit');
  if (btnAudit) {
    btnAudit.addEventListener('click', (e) => {
      e.preventDefault();
      window.location.href = 'api/auditoria.php?action=export_csv';
    });
  }

  // 5. Botões de Notificação do Header / Sidebar
  const btnTopNotify = document.getElementById('btn-notify-top');
  const btnSidebarNotify = document.getElementById('btn-sidebar-notify');
  
  if (btnTopNotify) {
    btnTopNotify.addEventListener('click', () => {
      window.location.href = 'notificacoes.php';
    });
  }
  if (btnSidebarNotify) {
    btnSidebarNotify.addEventListener('click', () => {
      window.location.href = 'notificacoes.php';
    });
  }

  // 6. Logout
  const btnLogout = document.getElementById('btn-logout');
  if (btnLogout) {
    btnLogout.addEventListener('click', () => {
      if (confirm('Deseja encerrar a sessão do Painel do Gestor?')) {
        if (window.PasseLivreAuth) {
          window.PasseLivreAuth.clearUser();
        }
        window.location.href = 'index.php';
      }
    });
  }

  // 7. Toast Feedback
  let toastTimer = null;
  function showToast(message) {
    const toast = document.getElementById('app-toast');
    const toastMsg = document.getElementById('toast-message');
    if (!toast || !toastMsg) return;

    toastMsg.textContent = message;
    toast.classList.add('visible');

    if (toastTimer) clearTimeout(toastTimer);
    toastTimer = setTimeout(() => {
      toast.classList.remove('visible');
    }, 3600);
  }
});

/* ==========================================================================
   ENVIO REAL DE NOTIFICAÇÃO AO CIDADÃO (api/notificacoes.php)
   ========================================================================== */
document.addEventListener('DOMContentLoaded', () => {
  const btnNotifyCitizen = document.getElementById('btn-send-notification');
  if (!btnNotifyCitizen) return;

  function notify(message) {
    if (typeof window.showToast === 'function') { window.showToast(message); return; }
    const toast = document.getElementById('app-toast');
    const toastMsg = document.getElementById('toast-message');
    if (toast && toastMsg) {
      toastMsg.textContent = message;
      toast.classList.add('visible');
      setTimeout(() => toast.classList.remove('visible'), 3600);
    }
  }

  btnNotifyCitizen.addEventListener('click', async () => {
    const msg = prompt('Digite a mensagem a ser enviada ao cidadão:');
    if (!msg || !msg.trim()) return;

    const destId = new URLSearchParams(window.location.search).get('id');
    if (!destId) {
      notify('Não foi possível identificar o cidadão destinatário.');
      return;
    }

    btnNotifyCitizen.disabled = true;
    try {
      const res = await fetch('api/notificacoes.php?action=send_individual', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          user_id: destId,
          title:   'Comunicado do Gestor Municipal',
          message: msg.trim(),
          type:    'gestor'
        })
      });
      const json = await res.json().catch(() => ({}));
      if (!res.ok || !json.success) {
        throw new Error(json.error || 'Erro ao gravar a notificação.');
      }
      notify('Notificação registrada e disponível para o cidadão.');
    } catch (err) {
      notify('Não foi possível enviar a notificação: ' + err.message);
    } finally {
      btnNotifyCitizen.disabled = false;
    }
  });
});
