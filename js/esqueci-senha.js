/**
 * Passe Livre Suzano — Recuperação de senha
 * Valida o e-mail no servidor antes de iniciar o envio do código.
 */
document.addEventListener('DOMContentLoaded', () => {
  const form    = document.getElementById('form-recuperar');
  const input   = document.getElementById('rec-email');
  const errorEl = document.getElementById('rec-error');
  const hintEl  = document.getElementById('rec-hint');
  const btn     = document.getElementById('btn-enviar-codigo');

  const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  const BTN_LABEL = 'Enviar código';

  let toastTimer = null;
  let countdown  = null;
  let submitting = false;

  function showToast(message) {
    if (typeof window.showToast === 'function') { window.showToast(message); return; }
    const toast = document.getElementById('app-toast');
    const msg   = document.getElementById('toast-message');
    if (!toast || !msg) return;
    msg.textContent = message;
    toast.classList.add('visible');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => toast.classList.remove('visible'), 3600);
  }

  function setError(message, options = {}) {
    if (!errorEl) return;
    errorEl.textContent = '';

    if (message) {
      errorEl.append(document.createTextNode(message));
      if (options.showRegisterLink) {
        errorEl.append(document.createTextNode(' '));
        const link = document.createElement('a');
        link.href = 'cadastro.php';
        link.className = 'recover-error-link';
        link.textContent = 'Criar uma conta';
        errorEl.append(link);
      }
    }

    errorEl.hidden = !message;
    if (input) {
      input.classList.toggle('has-error', !!message);
      input.setAttribute('aria-invalid', message ? 'true' : 'false');
    }
  }

  function setHint(message) {
    if (hintEl) { hintEl.textContent = message; hintEl.hidden = !message; }
  }

  function setSubmitting(loading) {
    submitting = loading;
    if (!btn) return;
    btn.disabled = loading;
    btn.textContent = loading ? 'Enviando...' : BTN_LABEL;
  }

  if (form) {
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      if (submitting) return;

      clearInterval(countdown);
      setError('');
      setHint('');

      // O campo não é limpo em caso de erro; o valor digitado é preservado.
      const email = (input?.value || '').trim();

      if (!email || !EMAIL_RE.test(email)) {
        setError('Por favor, informe um e-mail válido.');
        input?.focus();
        return;
      }

      setSubmitting(true);

      let res = null;
      try {
        const response = await fetch('api/auth.php?action=forgot_password', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ email })
        });

        const contentType = response.headers.get('content-type') || '';
        if (contentType.includes('application/json')) {
          res = await response.json();
        }
      } catch (netErr) {
        setSubmitting(false);
        setError('Não foi possível conectar ao servidor. Verifique sua conexão e tente novamente.');
        return;
      }

      if (!res || res.success !== true) {
        setSubmitting(false);
        const isNotFound = !!res && res.code === 'email_nao_cadastrado';
        setError(
          (res && res.error) || 'Não foi possível iniciar a recuperação de senha. Tente novamente.',
          { showRegisterLink: isNotFound }
        );
        input?.focus();
        return;
      }

      // Só chega aqui quando o servidor confirmou a conta e gravou o código.
      const confirmedEmail = res.email || email;
      sessionStorage.setItem('passeLivre_verify_email', confirmedEmail);
      sessionStorage.setItem('passeLivre_verify_flow',  'recuperacao');
      if (res.demo_code) {
        sessionStorage.setItem('passeLivre_verify_code', String(res.demo_code));
      } else {
        sessionStorage.removeItem('passeLivre_verify_code');
      }

      setHint(res.message || `Código enviado para ${confirmedEmail}. Verifique sua caixa de entrada.`);
      showToast('Código de recuperação enviado. Verifique seu e-mail.');

      let secs = 3;
      if (btn) btn.textContent = `Redirecionando em ${secs}s...`;
      countdown = setInterval(() => {
        secs--;
        if (btn) btn.textContent = secs > 0 ? `Redirecionando em ${secs}s...` : 'Redirecionando...';
        if (secs <= 0) {
          clearInterval(countdown);
          window.location.href = `verificar-codigo.php?email=${encodeURIComponent(confirmedEmail)}&flow=recuperacao`;
        }
      }, 1000);
    });
  }
});
