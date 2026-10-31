<?php
/**
 * Passe Livre Suzano — Fale Conosco / Contato Oficial
 * Integração direta com api/contato.php e tabela mensagens_contato do MySQL.
 */
require_once __DIR__ . '/api/_db_restore.php';
$authUser = get_auth_user();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Fale Conosco — Passe Livre Suzano</title>
  <meta name="description" content="Entre em contato com a equipe técnica do Passe Livre Suzano, Ouvidoria Municipal e Coordenação do SENAI Suzano.">
  <link rel="stylesheet" href="assets/fonts/fonts.css">
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="css/direitos.css">
  <style>
    .contact-grid {
      display: grid;
      grid-template-columns: 1fr 340px;
      gap: 32px;
      margin-top: 24px;
    }
    @media (max-width: 860px) {
      .contact-grid { grid-template-columns: 1fr; }
    }
    .contact-form-card {
      background: #fff;
      border: 1px solid #e7e2d8;
      border-radius: 16px;
      padding: 32px;
      box-shadow: 0 2px 8px rgba(0,0,0,.04);
    }
    .contact-info-card {
      background: #fdfaf6;
      border: 1px solid #ebd9c8;
      border-radius: 16px;
      padding: 28px;
    }
    .form-group {
      margin-bottom: 20px;
    }
    .form-group label {
      display: block;
      font-size: 13px;
      font-weight: 700;
      color: #44403c;
      margin-bottom: 6px;
    }
    .form-control {
      width: 100%;
      padding: 12px 14px;
      border: 1.5px solid #d6cfc7;
      border-radius: 8px;
      font-size: 14px;
      color: #1c1917;
      background: #fff;
      font-family: inherit;
      box-sizing: border-box;
      transition: border-color .15s, box-shadow .15s;
    }
    .form-control:focus {
      outline: none;
      border-color: #b45309;
      box-shadow: 0 0 0 3px rgba(180,83,9,.15);
    }
    .btn-submit-contact {
      background: #b45309;
      color: #fff;
      border: none;
      padding: 14px 28px;
      border-radius: 8px;
      font-size: 14px;
      font-weight: 700;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      transition: background .15s, transform .1s;
    }
    .btn-submit-contact:hover {
      background: #92400e;
    }
    .btn-submit-contact:disabled {
      opacity: .6;
      cursor: not-allowed;
    }
    .info-item {
      display: flex;
      gap: 14px;
      margin-bottom: 20px;
    }
    .info-icon-box {
      width: 40px;
      height: 40px;
      background: #fff;
      border: 1px solid #ebd9c8;
      border-radius: 10px;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
    }
    .feedback-banner {
      padding: 16px;
      border-radius: 8px;
      margin-bottom: 20px;
      font-size: 14px;
      display: none;
    }
    .feedback-banner.success {
      background: #dcfce7;
      color: #166534;
      border: 1px solid #86efac;
      display: block;
    }
    .feedback-banner.error {
      background: #fee2e2;
      color: #991b1b;
      border: 1px solid #fca5a5;
      display: block;
    }
  </style>
  <link rel="icon" type="image/x-icon" href="favicon.ico">
  <link rel="icon" type="image/png" sizes="32x32" href="assets/icons/favicon-32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="assets/icons/favicon-16.png">
  <link rel="apple-touch-icon" href="assets/icons/apple-touch-icon.png">
</head>
<body class="direitos-page">
  <a href="#main-content" class="skip-link">Pular para o conteúdo principal</a>

  <header class="site-header" role="banner">
    <div class="header-left">
      <a href="index.php" class="brand-logo" title="Voltar para a Página Inicial">
        <img src="assets/icons/logo.png" alt="Passe Livre Suzano" width="160" height="38">
      </a>
    </div>

    <nav class="header-nav" aria-label="Navegação Principal">
      <a href="explorar.php" class="nav-link">
        <img src="assets/icons/globe.svg" alt="" aria-hidden="true">
        <span>Explorar Mundo</span>
      </a>
      <a href="configuracao-rota.php" class="nav-link">
        <img src="assets/icons/routes.svg" alt="" aria-hidden="true">
        <span>Rotas</span>
      </a>
      <a href="lugares-encontrados.php" class="nav-link">
        <img src="assets/icons/support.svg" alt="" aria-hidden="true">
        <span>Pontos de Apoio</span>
      </a>
      <a href="direitos.php" class="nav-link">
        <img src="assets/icons/shield.svg" alt="" aria-hidden="true">
        <span>Direitos</span>
      </a>
    </nav>

    <div class="header-right">
      <?php if ($authUser): ?>
        <a href="central-notificacoes.php" class="btn-icon-notify" aria-label="Notificações">
          <img src="assets/icons/bell.svg" alt="" aria-hidden="true">
        </a>
        <a href="editar-perfil.php" class="user-profile" aria-label="Meu Perfil">
          <img src="<?= htmlspecialchars($authUser['avatar'] ?? 'assets/images/avatar.jpg') ?>" alt="Foto de <?= htmlspecialchars($authUser['name']) ?>" class="user-avatar-img">
          <div class="user-info">
            <span class="user-name"><?= htmlspecialchars($authUser['name']) ?></span>
            <span class="user-role"><?= htmlspecialchars($authUser['role'] ?? 'Cidadão') ?></span>
          </div>
        </a>
      <?php else: ?>
        <a href="login.php" class="nav-link" style="font-weight:700;color:var(--primary,#b45309);">Entrar</a>
        <a href="cadastro.php" class="btn-primary" style="padding:8px 16px;font-size:13px;border-radius:8px;text-decoration:none;">Cadastrar-se</a>
      <?php endif; ?>
    </div>
  </header>

  <main class="direitos-main-content" id="main-content">
    <nav class="direitos-breadcrumb" aria-label="Caminho de navegação">
      <a href="index.php" class="crumb-link">Início</a>
      <span class="crumb-sep">›</span>
      <span class="crumb-current">Fale Conosco</span>
    </nav>

    <div class="direitos-hero-header">
      <div class="direitos-hero-text">
        <div class="pedagogico-pill">
          <span class="pedagogico-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor">
              <path d="M20 2H4c-1.1 0-1.99.9-1.99 2L2 22l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2z"/>
            </svg>
          </span>
          <span>CANAL DE ATENDIMENTO AO CIDADÃO</span>
        </div>
        <h1 class="direitos-main-title">Fale Conosco e Ouvidoria de Mobilidade</h1>
        <p class="direitos-main-subtitle">
          Envie sua dúvida, sugestão de melhoria ou reporte uma barreira de acessibilidade diretamente para a equipe técnica do Passe Livre e SENAI Suzano.
        </p>
      </div>
    </div>

    <div class="contact-grid">
      <!-- Formulário de Contato -->
      <section class="contact-form-card" aria-labelledby="form-heading">
        <h2 id="form-heading" style="font-size:20px;color:#1c1917;margin-bottom:8px;">Envie uma mensagem</h2>
        <p style="font-size:13px;color:#78716c;margin-bottom:24px;">Preencha o formulário abaixo. Retornaremos em até 48 horas úteis.</p>

        <div id="contact-banner" class="feedback-banner" role="alert"></div>

        <form id="contact-form" novalidate>
          <div class="form-group">
            <label for="contact-name">Seu Nome Completo *</label>
            <input 
              type="text" 
              id="contact-name" 
              class="form-control" 
              required 
              value="<?= htmlspecialchars($authUser['name'] ?? '') ?>"
              placeholder="Ex.: Maria Souza"
            >
          </div>

          <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
            <div class="form-group">
              <label for="contact-email">E-mail para Retorno *</label>
              <input 
                type="email" 
                id="contact-email" 
                class="form-control" 
                required 
                value="<?= htmlspecialchars($authUser['email'] ?? '') ?>"
                placeholder="seu.email@exemplo.com"
              >
            </div>
            <div class="form-group">
              <label for="contact-phone">Telefone / WhatsApp</label>
              <input 
                type="tel" 
                id="contact-phone" 
                class="form-control" 
                value="<?= htmlspecialchars($authUser['phone'] ?? '') ?>"
                placeholder="(11) 98765-4321"
              >
            </div>
          </div>

          <div class="form-group">
            <label for="contact-subject">Assunto *</label>
            <select id="contact-subject" class="form-control" required>
              <option value="Dúvida Geral" selected>Dúvida sobre a plataforma</option>
              <option value="Sugestão de Rota Acessível">Sugestão de rota acessível em Suzano</option>
              <option value="Reporte de Barreira Digital">Problema técnico ou barreira digital no site</option>
              <option value="Solicitação Municipal de Acessibilidade">Dúvida sobre protocolo de fiscalização</option>
              <option value="Elogio / Parceria">Elogio ou proposta de cooperação</option>
            </select>
          </div>

          <div class="form-group">
            <label for="contact-message">Sua Mensagem *</label>
            <textarea 
              id="contact-message" 
              class="form-control" 
              rows="5" 
              required 
              placeholder="Descreva detalhadamente sua dúvida, sugestão ou informação sobre acessibilidade em Suzano..."
            ></textarea>
          </div>

          <button type="submit" class="btn-submit-contact" id="btn-submit-contact">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
              <line x1="22" y1="2" x2="11" y2="13"/>
              <polygon points="22 2 15 22 11 13 2 9 22 2"/>
            </svg>
            <span>Enviar Mensagem</span>
          </button>
        </form>
      </section>

      <!-- Cartão de Informações Institucionais -->
      <aside class="contact-info-card" aria-label="Informações Institucionais">
        <h3 style="font-size:17px;color:#1c1917;margin-bottom:16px;">Canais Institucionais</h3>

        <div class="info-item">
          <div class="info-icon-box">
            <img src="assets/icons/pin.svg" alt="" width="20" height="20">
          </div>
          <div>
            <strong style="font-size:13px;color:#1c1917;display:block;">SENAI Suzano — CFP Luís Eulálio</strong>
            <span style="font-size:12px;color:#57534e;line-height:1.5;">Rua Barão de Piratininga, 250 — Vila Amorim, Suzano - SP</span>
          </div>
        </div>

        <div class="info-item">
          <div class="info-icon-box">
            <img src="assets/icons/shield.svg" alt="" width="20" height="20">
          </div>
          <div>
            <strong style="font-size:13px;color:#1c1917;display:block;">Prefeitura Municipal de Suzano</strong>
            <span style="font-size:12px;color:#57534e;line-height:1.5;">Secretaria de Transporte e Mobilidade Urbana (SMTU)<br>Rua Baruel, 501 — Centro</span>
          </div>
        </div>

        <div class="info-item">
          <div class="info-icon-box">
            <img src="assets/icons/phone.svg" alt="" width="20" height="20" onerror="this.src='assets/icons/support.svg'">
          </div>
          <div>
            <strong style="font-size:13px;color:#1c1917;display:block;">Telefone e Atendimento</strong>
            <span style="font-size:12px;color:#57534e;line-height:1.5;">(11) 4745-2000<br>Segunda a Sexta, das 08h às 17h</span>
          </div>
        </div>

        <div style="background:#fff;border-radius:12px;padding:16px;border:1px solid #ebd9c8;margin-top:24px;">
          <strong style="font-size:12px;color:#b45309;display:block;margin-bottom:4px;">Atendimento Inclusivo</strong>
          <p style="font-size:11px;color:#78716c;line-height:1.5;margin:0;">
            A Prefeitura de Suzano disponibiliza atendimento com acessibilidade em Libras e equipe treinada para acolhimento de pessoas com deficiência.
          </p>
        </div>
      </aside>
    </div>
  </main>

  <footer class="site-footer" role="contentinfo">
    <p class="footer-copy">
      &copy; 2026 Passe Livre Suzano. Todos os direitos reservados. Projeto em cooperação técnica com o SENAI Suzano.
    </p>
    <div class="footer-links">
      <a href="termos-de-uso.php" class="footer-link-item">Termos de Uso</a>
      <a href="privacidade.php" class="footer-link-item">Privacidade (LGPD)</a>
      <a href="acessibilidade.php" class="footer-link-item">Acessibilidade</a>
      <a href="fale-conosco.php" class="footer-link-item active">Fale Conosco</a>
    </div>
  </footer>

  <script>
    document.getElementById('contact-form')?.addEventListener('submit', async (e) => {
      e.preventDefault();
      const btn = document.getElementById('btn-submit-contact');
      const banner = document.getElementById('contact-banner');
      
      const name = document.getElementById('contact-name').value.trim();
      const email = document.getElementById('contact-email').value.trim();
      const phone = document.getElementById('contact-phone').value.trim();
      const subject = document.getElementById('contact-subject').value;
      const message = document.getElementById('contact-message').value.trim();

      if (!name || !email || !message) {
        banner.className = 'feedback-banner error';
        banner.textContent = 'Por favor preencha todos os campos obrigatórios (*).';
        return;
      }

      btn.disabled = true;
      btn.querySelector('span').textContent = 'Enviando...';

      try {
        const res = await fetch('api/contato.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ name, email, phone, subject, message })
        });
        const data = await res.json();
        if (data.success) {
          banner.className = 'feedback-banner success';
          banner.textContent = data.message || 'Mensagem enviada com sucesso! Retornaremos em breve.';
          document.getElementById('contact-message').value = '';
        } else {
          banner.className = 'feedback-banner error';
          banner.textContent = data.error || data.message || 'Erro ao enviar mensagem. Tente novamente.';
        }
      } catch (err) {
        banner.className = 'feedback-banner error';
        banner.textContent = 'Falha na conexão com o servidor. Verifique sua internet.';
      } finally {
        btn.disabled = false;
        btn.querySelector('span').textContent = 'Enviar Mensagem';
      }
    });
  </script>
</body>
</html>
