/**
 * Passe Livre Suzano — Script de Login
 */
document.addEventListener('DOMContentLoaded', () => {

  // =========================================================================
  // 1. ALTERNÂNCIA DE VISIBILIDADE DA SENHA
  // =========================================================================
  document.querySelectorAll('.btn-toggle-eye').forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.preventDefault();
      const input = document.getElementById(btn.getAttribute('data-target'));
      if (!input) return;
      const isPassword = input.getAttribute('type') === 'password';
      input.setAttribute('type', isPassword ? 'text' : 'password');
      const img = btn.querySelector('img');
      if (img) {
        img.src = isPassword ? 'assets/icons/eye-off.svg' : 'assets/icons/eye.svg';
        img.alt = isPassword ? 'Ocultar senha' : 'Visualizar senha';
        btn.setAttribute('title',      isPassword ? 'Ocultar senha' : 'Visualizar senha');
        btn.setAttribute('aria-label', isPassword ? 'Ocultar senha' : 'Visualizar senha');
      }
    });
  });

  // =========================================================================
  // 2. SUBMISSÃO DO FORMULÁRIO — autenticação MySQL
  // =========================================================================
  const formLogin = document.getElementById('form-login');
  if (formLogin) {
    formLogin.addEventListener('submit', async (e) => {
      e.preventDefault();

      const emailInput = document.getElementById('log-email');
      const senhaInput = document.getElementById('log-senha');
      const email      = emailInput?.value.trim();
      const senha      = senhaInput?.value;

      if (!email || !email.includes('@')) {
        showToast('Por favor, informe um endereço de e-mail válido.', 'error');
        emailInput?.focus();
        return;
      }
      if (!senha || senha.length < 6) {
        showToast('A senha deve conter no mínimo 6 caracteres.', 'error');
        senhaInput?.focus();
        return;
      }

      const remember = document.getElementById('log-lembrar')?.checked || false;
      let loggedUser   = null;
      let sessionToken = null;

      try {
        const resp = await fetch('api/auth.php?action=login', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ email, password: senha, remember })
        });
        const result = await resp.json();

        if (result.success && result.user) {
          loggedUser   = result.user;
          sessionToken = result.session_token;
        } else {
          showToast(result.error || 'Falha ao autenticar.', 'error');
          return;
        }
      } catch (netErr) {
        showToast('Erro de conexão. Verifique sua internet.', 'error');
        return;
      }

      if (window.PasseLivreAuth) {
        window.PasseLivreAuth.setUser(loggedUser, sessionToken);
      } else {
        localStorage.setItem('passeLivreUser', JSON.stringify(loggedUser));
        if (sessionToken) localStorage.setItem('passeLivre_token', sessionToken);
      }

      const firstName  = (loggedUser.name || '').split(' ')[0];
      showToast(`Bem-vindo(a) de volta, ${firstName}!`);

      const isAdm     = loggedUser.isAdmin === true || loggedUser.is_admin === 1;
      const destination = isAdm ? 'dashboard.php' : 'adaptar-busca.php';
      setTimeout(() => { window.location.href = destination; }, 1000);
    });
  }

  // =========================================================================
  // 3. LOGIN COM GOOGLE — Google Identity Services (OAuth real)
  //    O botão abre o seletor de conta Google nativo via GSI.
  //    Após a escolha, o callback plHandleGoogleCredential recebe o JWT.
  // =========================================================================

  /**
   * Callback global chamado pelo SDK do Google após o usuário escolher a conta.
   * Recebe o credential (JWT id_token) e envia para api/auth.php?action=google_login.
   */
  window.plHandleGoogleCredential = async function (response) {
    if (!response || !response.credential) {
      showToast('Falha ao obter credencial do Google.', 'error');
      return;
    }

    showToast('Verificando conta Google…');

    try {
      const resp = await fetch('api/auth.php?action=google_login', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id_token: response.credential })
      });
      const data = await resp.json();

      if (!data.success) {
        showToast(data.error || 'Falha no login com Google.', 'error');
        return;
      }

      if (window.PasseLivreAuth) {
        window.PasseLivreAuth.setUser(data.user, data.session_token);
      } else {
        localStorage.setItem('passeLivreUser', JSON.stringify(data.user));
        if (data.session_token) localStorage.setItem('passeLivre_token', data.session_token);
      }

      const firstName = (data.user.name || '').split(' ')[0];
      showToast(`Bem-vindo(a), ${firstName}!`);

      const isAdm = data.user.is_admin === 1;
      setTimeout(() => {
        window.location.href = isAdm ? 'dashboard.php' : 'adaptar-busca.php';
      }, 900);

    } catch (err) {
      showToast('Erro de conexão ao verificar conta Google.', 'error');
    }
  };

  /**
   * Botão "Continuar com Google" — abre o seletor de conta do GSI.
   * Se o SDK ainda não carregou (sem Client ID configurado), exibe aviso.
   */
  const btnGoogle = document.getElementById('btn-google-login');
  if (btnGoogle) {
    btnGoogle.addEventListener('click', () => {
      if (typeof google === 'undefined' || !google.accounts) {
        showToast('Configure o Google Client ID para usar este recurso.', 'error');
        return;
      }
      // Abre o One Tap / seletor de conta Google
      google.accounts.id.prompt((notification) => {
        if (notification.isNotDisplayed() || notification.isSkippedMoment()) {
          // Fallback: abre popup OAuth explícito
          google.accounts.oauth2 && google.accounts.id.renderButton(
            document.getElementById('g_id_onload'),
            { theme: 'outline', size: 'large' }
          );
        }
      });
    });
  }

  // =========================================================================
  // 4. TOAST LOCAL
  // =========================================================================
  function showToast(message, type) {
    if (typeof window.showToast === 'function') { window.showToast(message, type); return; }
    const toast    = document.getElementById('app-toast');
    const toastMsg = document.getElementById('toast-message');
    if (toast && toastMsg) {
      toastMsg.textContent = message;
      toast.classList.add('visible');
      setTimeout(() => toast.classList.remove('visible'), 3600);
    }
  }

  // =========================================================================
  // ACESSO DIRETO ADMIN (botão de teste — autentica via API e redireciona)
  // =========================================================================
  const btnAdminTest = document.getElementById('btn-admin-test');
  if (btnAdminTest) {
    btnAdminTest.addEventListener('click', async () => {
      btnAdminTest.disabled = true;
      const originalHtml   = btnAdminTest.innerHTML;
      btnAdminTest.innerHTML = '<span>Autenticando...</span>';

      try {
        const resp = await fetch('api/auth.php?action=login', {
          method:  'POST',
          headers: { 'Content-Type': 'application/json' },
          body:    JSON.stringify({
            email:    'carlos.eduardo@suzano.sp.gov.br',
            password: 'admin123',
            remember: false
          })
        });
        const data = await resp.json();

        if (data.success && data.user) {
          if (window.PasseLivreAuth) {
            window.PasseLivreAuth.setUser(data.user, data.session_token);
          } else {
            localStorage.setItem('passeLivreUser', JSON.stringify(data.user));
            if (data.session_token) localStorage.setItem('passeLivre_token', data.session_token);
          }
          showToast('Acesso admin realizado. Redirecionando...');
          setTimeout(() => { window.location.href = 'dashboard.php'; }, 700);
          return;
        }

        // Fallback: redireciona direto se API falhar
        showToast(data.error || 'Redirecionando para o painel admin...', 'error');
        setTimeout(() => { window.location.href = 'dashboard.php'; }, 800);
      } catch (_) {
        // Sem conexão: redireciona direto
        window.location.href = 'dashboard.php';
      } finally {
        btnAdminTest.disabled = false;
        btnAdminTest.innerHTML = originalHtml;
      }
    });
  }

  // =========================================================================
  // 5. PARÂMETROS DE URL
  // =========================================================================
  const urlParams  = new URLSearchParams(window.location.search);
  const emailParam = urlParams.get('email');
  if (emailParam) {
    const emailInput = document.getElementById('log-email');
    if (emailInput) { emailInput.value = emailParam; document.getElementById('log-senha')?.focus(); }
  }
  if (urlParams.get('redefinida') === 'true') {
    setTimeout(() => showToast('Senha redefinida com sucesso! Faça login com a nova senha.'), 300);
  } else if (urlParams.get('verified') === 'true') {
    setTimeout(() => showToast('E-mail validado com sucesso! Acesse sua conta.'), 300);
  }
});