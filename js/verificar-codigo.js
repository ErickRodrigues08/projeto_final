/**
 * Passe Livre Suzano — Verificação de Código OTP
 * Lê e-mail da query string ou sessionStorage (conforme cadastro.js / esqueci-senha.js),
 * envia o código para api/auth.php e redireciona após verificação bem-sucedida.
 */
document.addEventListener('DOMContentLoaded', () => {

  // ---------------------------------------------------------------------------
  // 1. Leitura do contexto (e-mail e flow)
  // ---------------------------------------------------------------------------
  const params    = new URLSearchParams(window.location.search);
  const email     = params.get('email') || sessionStorage.getItem('passeLivre_verify_email') || '';
  const flow      = params.get('flow')  || sessionStorage.getItem('passeLivre_verify_flow')  || 'cadastro';
  const demoCodeFromSession = sessionStorage.getItem('passeLivre_verify_code') || '';

  // Atualiza elementos de exibição de e-mail
  const emailDisplay     = document.getElementById('email-display');
  const cardEmailTarget  = document.getElementById('card-email-target');
  if (emailDisplay    && email) emailDisplay.textContent    = email;
  if (cardEmailTarget && email) cardEmailTarget.textContent = email;

  // ---------------------------------------------------------------------------
  // 2. Referências aos elementos do DOM
  // ---------------------------------------------------------------------------
  const inputs        = Array.from(document.querySelectorAll('.otp-digit-input'));
  const feedbackEl    = document.getElementById('otp-feedback');
  const btnVerify     = document.getElementById('btn-verify');
  const btnResend     = document.getElementById('btn-resend');
  const resendTimerEl = document.getElementById('resend-timer-text');
  const countdownEl   = document.getElementById('resend-countdown');
  const demoPill      = document.getElementById('demo-code-pill');
  const demoCodeEl    = document.getElementById('demo-code-value');
  const toastEl       = document.getElementById('verify-toast');
  const toastMsgEl    = document.getElementById('verify-toast-message');

  // ---------------------------------------------------------------------------
  // 3. Toast helper
  // ---------------------------------------------------------------------------
  let toastTimer = null;
  function showToast(message) {
    if (!toastEl || !toastMsgEl) return;
    toastMsgEl.textContent = message;
    toastEl.classList.add('visible');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => toastEl.classList.remove('visible'), 3600);
  }

  // ---------------------------------------------------------------------------
  // 4. Modo demo — exibe código da sessão (apenas em dev, pill existe no PHP)
  // ---------------------------------------------------------------------------
  if (demoPill && demoCodeEl && demoCodeFromSession) {
    demoCodeEl.textContent = demoCodeFromSession;
    demoPill.addEventListener('click', () => {
      const code = demoCodeEl.textContent.replace(/\s/g, '');
      if (code.length === 6) {
        inputs.forEach((inp, i) => {
          inp.value = code[i] || '';
          inp.classList.toggle('filled', !!inp.value);
        });
        updateSubmitButton();
        inputs[5] && inputs[5].focus();
      }
    });
  }

  // ---------------------------------------------------------------------------
  // 5. Lógica de navegação entre os campos OTP
  // ---------------------------------------------------------------------------
  function getCode() {
    return inputs.map(i => i.value).join('');
  }

  function updateSubmitButton() {
    if (!btnVerify) return;
    const full = getCode().length === 6;
    btnVerify.disabled = !full;
    btnVerify.classList.toggle('ready', full);
  }

  inputs.forEach((inp, idx) => {
    inp.addEventListener('input', (e) => {
      // Aceita apenas dígitos
      inp.value = inp.value.replace(/\D/g, '').slice(-1);
      inp.classList.toggle('filled', !!inp.value);

      // Remove estado de erro ao digitar
      inp.classList.remove('is-error');
      setFeedback('', '');

      if (inp.value && idx < inputs.length - 1) {
        inputs[idx + 1].focus();
      }
      updateSubmitButton();
    });

    inp.addEventListener('keydown', (e) => {
      if (e.key === 'Backspace' && !inp.value && idx > 0) {
        inputs[idx - 1].value = '';
        inputs[idx - 1].classList.remove('filled');
        inputs[idx - 1].focus();
        updateSubmitButton();
      }
      if (e.key === 'ArrowLeft' && idx > 0) {
        inputs[idx - 1].focus();
      }
      if (e.key === 'ArrowRight' && idx < inputs.length - 1) {
        inputs[idx + 1].focus();
      }
    });

    // Suporte a colar código de 6 dígitos
    inp.addEventListener('paste', (e) => {
      e.preventDefault();
      const pasted = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '');
      if (pasted.length >= 6) {
        inputs.forEach((field, i) => {
          field.value = pasted[i] || '';
          field.classList.toggle('filled', !!field.value);
        });
        inputs[5].focus();
        updateSubmitButton();
      }
    });
  });

  // Foca no primeiro campo ao carregar
  if (inputs[0]) inputs[0].focus();

  // ---------------------------------------------------------------------------
  // 6. Feedback visual
  // ---------------------------------------------------------------------------
  function setFeedback(message, type) {
    if (!feedbackEl) return;
    feedbackEl.textContent = message;
    feedbackEl.className = 'otp-feedback-msg' + (type ? ' ' + type : '');
  }

  function setInputsState(state) {
    inputs.forEach(inp => {
      inp.classList.remove('is-error', 'is-success');
      if (state) inp.classList.add(state);
    });
  }

  // ---------------------------------------------------------------------------
  // 7. Contador de reenvio — cooldown de 60 s do e-mail informado (medido no servidor)
  // ---------------------------------------------------------------------------
  const cooldownSeconds = (typeof window.plOtpResendCooldown === 'number' && window.plOtpResendCooldown > 0)
    ? window.plOtpResendCooldown
    : 60;

  let resendSeconds = 0;
  let resendInterval = null;

  function formatCountdown(seconds) {
    const m = Math.floor(seconds / 60);
    const s = seconds % 60;
    return m > 0
      ? `${m}:${String(s).padStart(2, '0')}`
      : `${s}s`;
  }

  function showResendButton() {
    clearInterval(resendInterval);
    if (resendTimerEl) resendTimerEl.hidden = true;
    if (btnResend) {
      btnResend.hidden = false;
      btnResend.disabled = false;
    }
  }

  function startResendCountdown(seconds) {
    resendSeconds = Math.max(0, Math.floor(Number(seconds) || 0));
    if (resendSeconds <= 0) {
      showResendButton();
      return;
    }

    if (btnResend) btnResend.hidden = true;
    if (resendTimerEl) resendTimerEl.hidden = false;

    clearInterval(resendInterval);
    if (countdownEl) countdownEl.textContent = formatCountdown(resendSeconds);

    resendInterval = setInterval(() => {
      resendSeconds--;
      if (countdownEl) countdownEl.textContent = formatCountdown(resendSeconds);
      if (resendSeconds <= 0) {
        clearInterval(resendInterval);
        if (resendTimerEl) resendTimerEl.hidden = true;
        if (btnResend) btnResend.hidden = false;
      }
    }, 1000);
  }

  // O tempo restante vem do PHP, calculado pelo último código enviado PARA ESTE e-mail.
  // Assim, trocar de e-mail não herda o contador anterior e um F5 conserva o tempo real.
  const remainingOnLoad = (typeof window.plOtpResendRemaining === 'number') ? window.plOtpResendRemaining : 0;
  if (remainingOnLoad > 0) {
    startResendCountdown(remainingOnLoad);
  } else {
    showResendButton();
  }

  // ---------------------------------------------------------------------------
  // 7b. Timer de expiração do código OTP (usa o tempo real do backend)
  // ---------------------------------------------------------------------------
  const expiryTimerEl = document.getElementById('otp-expiry-timer');

  function formatExpiry(seconds) {
    if (seconds <= 0) return null;
    const m = Math.floor(seconds / 60);
    const s = seconds % 60;
    return m > 0 ? (m + 'm ' + String(s).padStart(2, '0') + 's') : (s + 's');
  }

  let _expiryInterval = null;

  function _runExpiryTimer(initialSeconds) {
    if (!expiryTimerEl) return;
    let remaining = initialSeconds;

    function _update() {
      if (remaining <= 0) {
        clearInterval(_expiryInterval);
        expiryTimerEl.textContent = 'Tempo até o código expirar: Código expirado';
        expiryTimerEl.classList.add('expired');
        if (btnVerify) btnVerify.disabled = true;
        setFeedback('Este código expirou. Solicite um novo código abaixo.', 'error');
        return;
      }
      expiryTimerEl.textContent = 'Tempo até o código expirar: ' + formatExpiry(remaining);
      expiryTimerEl.classList.remove('expired');
      remaining--;
    }

    _update();
    _expiryInterval = setInterval(_update, 1000);
  }

  function startExpiryCountdown() {
    if (!expiryTimerEl) return;
    const expiresAt = (typeof window.plOtpExpiresAt === 'number') ? window.plOtpExpiresAt : null;
    const nowSeconds = Math.floor(Date.now() / 1000);
    const remaining = expiresAt ? Math.max(0, expiresAt - nowSeconds) : 15 * 60;
    _runExpiryTimer(remaining);
  }

  startExpiryCountdown();

  // ---------------------------------------------------------------------------
  // 8. Reenvio de código
  // ---------------------------------------------------------------------------
  if (btnResend) {
    btnResend.addEventListener('click', async () => {
      btnResend.disabled = true;
      setFeedback('Enviando novo código…', '');

      try {
        const resp = await fetch('api/auth.php?action=resend_code', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ email, flow })
        });
        const data = await resp.json();

        if (data.success) {
          if (data.demo_code) {
            sessionStorage.setItem('passeLivre_verify_code', String(data.demo_code));
            if (demoCodeEl) demoCodeEl.textContent = String(data.demo_code);
          }
          showToast('Novo código enviado! Verifique seu e-mail.');
          setFeedback('', '');
          startResendCountdown(cooldownSeconds);
          // Reinicia o timer de expiração com novo código (15 min)
          clearInterval(_expiryInterval);
          if (expiryTimerEl) expiryTimerEl.classList.remove('expired');
          _runExpiryTimer(15 * 60);
        } else {
          setFeedback(data.error || 'Não foi possível reenviar o código.', 'error');
          if (typeof data.retry_after === 'number' && data.retry_after > 0) {
            // O servidor mantém o controle pelo e-mail: alinha o contador ao tempo real.
            startResendCountdown(data.retry_after);
          } else {
            showResendButton();
          }
        }
      } catch (err) {
        setFeedback('Erro de conexão. Tente novamente.', 'error');
        btnResend.disabled = false;
      }
    });
  }

  // ---------------------------------------------------------------------------
  // 9. Submissão do formulário de verificação
  // ---------------------------------------------------------------------------
  const otpForm = document.getElementById('otp-form');
  if (otpForm) {
    otpForm.addEventListener('submit', async (e) => {
      e.preventDefault();

      const code = getCode();
      if (code.length !== 6) {
        setFeedback('Preencha todos os 6 dígitos do código.', 'error');
        setInputsState('is-error');
        inputs[0].focus();
        return;
      }

      // Estado de carregamento
      if (btnVerify) {
        btnVerify.disabled = true;
        btnVerify.classList.add('is-loading');
      }
      setFeedback('Verificando…', '');

      try {
        const resp = await fetch('api/auth.php?action=verify_code', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ email, code, flow })
        });
        const data = await resp.json();

        if (data.success) {
          setInputsState('is-success');
          setFeedback(data.message || 'Código verificado com sucesso!', 'success');
          showToast('Código verificado com sucesso!');

          // Limpa dados temporários de sessão
          sessionStorage.removeItem('passeLivre_verify_code');
          sessionStorage.removeItem('passeLivre_verify_email');
          sessionStorage.removeItem('passeLivre_verify_flow');

          // Armazena usuário autenticado se retornado
          if (data.user && window.PasseLivreAuth) {
            window.PasseLivreAuth.setUser(data.user, data.session_token);
          } else if (data.user) {
            localStorage.setItem('passeLivreUser', JSON.stringify(data.user));
          }

          // Redireciona conforme o fluxo
          setTimeout(() => {
            if (flow === 'recuperacao' || flow === 'recuperacao_senha') {
              const token = data.reset_token || '';
              window.location.href = 'redefinir-senha.php?email=' + encodeURIComponent(email) + (token ? '&token=' + encodeURIComponent(token) : '');
            } else {
              // flow === 'cadastro' ou qualquer outro
              window.location.href = 'adaptar-busca.php';
            }
          }, 1200);
        } else {
          setInputsState('is-error');
          setFeedback(data.error || 'Código incorreto. Verifique e tente novamente.', 'error');

          if (btnVerify) {
            btnVerify.disabled = false;
            btnVerify.classList.remove('is-loading');
          }
        }
      } catch (err) {
        // Fallback offline: compara com código salvo na sessionStorage
        const savedCode = sessionStorage.getItem('passeLivre_verify_code');
        if (savedCode && code === String(savedCode)) {
          setInputsState('is-success');
          setFeedback('Código verificado (modo offline).', 'success');
          showToast('Código verificado com sucesso!');

          sessionStorage.removeItem('passeLivre_verify_code');
          sessionStorage.removeItem('passeLivre_verify_email');
          sessionStorage.removeItem('passeLivre_verify_flow');

          setTimeout(() => {
            if (flow === 'recuperacao' || flow === 'recuperacao_senha') {
              window.location.href = 'redefinir-senha.php?email=' + encodeURIComponent(email);
            } else {
              window.location.href = 'adaptar-busca.php';
            }
          }, 1200);
        } else {
          setInputsState('is-error');
          setFeedback('Erro de conexão. Verifique sua internet e tente novamente.', 'error');
          if (btnVerify) {
            btnVerify.disabled = false;
            btnVerify.classList.remove('is-loading');
          }
        }
      }
    });
  }
});