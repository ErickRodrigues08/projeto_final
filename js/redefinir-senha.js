/**
 * Passe Livre Suzano — Redefinição de Senha (Etapa Final 2 de 2)
 * Validação dos 3 requisitos de segurança com contador dinâmico e alternância de visibilidade.
 */

document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('form-redefinir');
  const novaSenhaInput = document.getElementById('nova-senha');
  const confirmSenhaInput = document.getElementById('confirm-senha');
  const submitBtn = document.getElementById('btn-redefinir');
  const confirmError = document.getElementById('confirm-error');

  const reqCounter = document.getElementById('req-counter');
  const reqLen = document.getElementById('req-len');
  const reqMix = document.getElementById('req-mix');
  const reqSpec = document.getElementById('req-spec');

  const toastEl = document.getElementById('reset-toast');
  const toastMsg = document.getElementById('reset-toast-message');

  // 1. Alternância de Visibilidade das Senhas (olho)
  const eyeButtons = document.querySelectorAll('.btn-eye-toggle');
  eyeButtons.forEach(btn => {
    btn.addEventListener('click', () => {
      const targetId = btn.getAttribute('data-target');
      const input = document.getElementById(targetId);
      if (!input) return;

      const isPassword = input.type === 'password';
      input.type = isPassword ? 'text' : 'password';

      btn.innerHTML = isPassword
        ? `<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>`
        : `<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>`;
    });
  });

  // 2. Validação Dinâmica dos 3 Requisitos
  function validateRequirements(val) {
    // Requisito 1: Mínimo 8 caracteres
    const hasLength = val.length >= 8;
    // Requisito 2: Pelo menos uma maiúscula e um número
    const hasMix = /[A-Z]/.test(val) && /[0-9]/.test(val);
    // Requisito 3: Caractere especial
    const hasSpecial = /[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(val);

    updateReqItem(reqLen, hasLength);
    updateReqItem(reqMix, hasMix);
    updateReqItem(reqSpec, hasSpecial);

    const score = [hasLength, hasMix, hasSpecial].filter(Boolean).length;
    
    if (reqCounter) {
      reqCounter.textContent = `${score} de 3 requisitos`;
    }

    return score === 3;
  }

  function updateReqItem(el, isValid) {
    if (!el) return;
    el.classList.toggle('valid', isValid);
  }

  // 3. Verificação de Correspondência das Senhas
  function checkMatch() {
    const senha = novaSenhaInput.value;
    const confirm = confirmSenhaInput.value;

    if (!confirm) {
      confirmSenhaInput.classList.remove('has-error');
      if (confirmError) confirmError.hidden = true;
      return true;
    }

    const matches = senha === confirm;
    confirmSenhaInput.classList.toggle('has-error', !matches);

    if (confirmError) {
      confirmError.textContent = matches ? '' : 'As senhas digitadas não coincidem.';
      confirmError.hidden = matches;
    }

    return matches;
  }

  novaSenhaInput.addEventListener('input', () => {
    validateRequirements(novaSenhaInput.value);
    novaSenhaInput.classList.remove('has-error');
    if (confirmSenhaInput.value) {
      checkMatch();
    }
  });

  confirmSenhaInput.addEventListener('input', () => {
    checkMatch();
  });

  // 4. Toast Feedback
  let toastTimer = null;
  function showToast(message) {
    if (!toastEl || !toastMsg) return;
    toastMsg.textContent = message;
    toastEl.classList.add('visible');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => toastEl.classList.remove('visible'), 3600);
  }

  // 5. Submissão do Formulário
  form.addEventListener('submit', async (e) => {
    e.preventDefault();

    const senha = novaSenhaInput.value;
    const confirm = confirmSenhaInput.value;

    const isScoreValid = validateRequirements(senha);
    if (!isScoreValid) {
      novaSenhaInput.classList.add('has-error');
      novaSenhaInput.focus();
      showToast('Por favor, atenda aos 3 requisitos de segurança.');
      return;
    }

    if (senha !== confirm) {
      confirmSenhaInput.classList.add('has-error');
      confirmSenhaInput.focus();
      if (confirmError) {
        confirmError.textContent = 'As senhas digitadas não coincidem.';
        confirmError.hidden = false;
      }
      showToast('As senhas não coincidem.');
      return;
    }

    // Recupera parâmetros da sessão de recuperação
    const urlParams = new URLSearchParams(window.location.search);
    const demoPill = document.getElementById('demo-code-pill');
    const demoEmail = demoPill ? (demoPill.getAttribute('data-demo-email') || '') : '';
    const demoTok = demoPill ? (demoPill.getAttribute('data-demo-token') || '') : '';

    const userEmail = sessionStorage.getItem('passeLivre_verify_email') || urlParams.get('email') || demoEmail || '';
    const resetToken = sessionStorage.getItem('passeLivre_reset_token') || urlParams.get('token') || demoTok || '';

    if (!resetToken) {
      showToast('Autorização de recuperação inválida ou expirada. Solicite um novo código.', 'error');
      setTimeout(() => {
        window.location.href = 'esqueci-senha.php';
      }, 2000);
      return;
    }

    // Estado de envio
    submitBtn.disabled = true;
    submitBtn.innerHTML = `<span>Salvando nova senha...</span>`;

    let resetOk = false;
    try {
      const resp = await fetch('api/auth.php?action=reset_password', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          reset_token: resetToken,
          email: userEmail,
          password: senha,
          confirm_password: confirm
        })
      });
      const res = await resp.json();
      if (res.success) {
        resetOk = true;
        // Invalida token na sessão do cliente após uso
        sessionStorage.removeItem('passeLivre_reset_token');
        sessionStorage.removeItem('passeLivre_verify_email');
      } else {
        showToast(res.error || 'Erro ao redefinir senha.', 'error');
        submitBtn.disabled = false;
        submitBtn.innerHTML = `<span>Redefinir senha</span>`;
        return;
      }
    } catch (err) {
      console.error('[RedefinirSenha] Erro na API:', err);
      showToast('Erro de conexão ao salvar nova senha. Tente novamente.', 'error');
      submitBtn.disabled = false;
      submitBtn.innerHTML = `<span>Redefinir senha</span>`;
      return;
    }

    // Registra redefinição de senha no log de auditoria
    if (window.PLDBAuditLog) {
      window.PLDBAuditLog(
        'autenticacao',
        'Senha Redefinida',
        `Senha redefinida com sucesso para o e-mail: ${userEmail || 'cidadão'}`,
        'medio'
      );
    }

    try {
      const stored = localStorage.getItem('passeLivreUser');
      let user = stored ? JSON.parse(stored) : null;
      if (user) {
        delete user.password;
        user.passwordUpdated = true;
        if (userEmail) user.email = userEmail;
        if (!user.avatar) user.avatar = 'assets/images/avatar.jpg';
      } else {
        user = {
          name: userEmail ? userEmail.split('@')[0] : 'Usuário',
          email: userEmail || '',
          role: 'Cidadão Conectado',
          avatar: 'assets/images/avatar.jpg',
          passwordUpdated: true
        };
      }
      localStorage.setItem('passeLivreUser', JSON.stringify(user));
    } catch (err) {}

    showToast('Senha redefinida com sucesso! Redirecionando...');

    setTimeout(() => {
      const targetUrl = userEmail
        ? `login.php?redefinida=true&email=${encodeURIComponent(userEmail)}`
        : 'login.php?redefinida=true';
      window.location.href = targetUrl;
    }, 1200);
  });
});
