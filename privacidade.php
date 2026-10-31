<?php
/**
 * Passe Livre Suzano — Política de Privacidade e Proteção de Dados (LGPD)
 * Em conformidade estrita com a Lei Federal nº 13.709/2018 (LGPD).
 */
require_once __DIR__ . '/api/_db_restore.php';
$authUser = get_auth_user();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Política de Privacidade e LGPD — Passe Livre Suzano</title>
  <meta name="description" content="Política de Privacidade e Proteção de Dados Pessoais do Passe Livre Suzano em conformidade com a LGPD (Lei nº 13.709/2018).">
  <link rel="stylesheet" href="assets/fonts/fonts.css">
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="css/direitos.css">
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
      <a href="direitos.php" class="crumb-link">Direitos &amp; Cidadania</a>
      <span class="crumb-sep">›</span>
      <span class="crumb-current">Privacidade &amp; LGPD</span>
    </nav>

    <div class="direitos-hero-header">
      <div class="direitos-hero-text">
        <div class="pedagogico-pill">
          <span class="pedagogico-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor">
              <path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm-2 16l-4-4 1.41-1.41L10 14.17l6.59-6.59L18 9l-8 8z"/>
            </svg>
          </span>
          <span>LGPD • LEI Nº 13.709/2018</span>
        </div>
        <h1 class="direitos-main-title">Política de Privacidade e Proteção de Dados</h1>
        <p class="direitos-main-subtitle">
          Saiba como tratamos seus dados pessoais, informações sensíveis de acessibilidade e geolocalização com total transparência e segurança.
        </p>
      </div>
    </div>

    <article class="legis-card" style="background:#fff;border-radius:16px;padding:36px;border:1px solid #e7e2d8;margin-bottom:32px;box-shadow:0 2px 8px rgba(0,0,0,.04);">
      <h2 style="font-size:20px;color:#1c1917;margin-bottom:16px;">1. Compromisso com a Privacidade</h2>
      <p style="color:#57534e;line-height:1.7;margin-bottom:20px;">
        A Prefeitura Municipal de Suzano e o SENAI Suzano têm o compromisso irrestrito de proteger a privacidade e os dados pessoais de todos os cidadãos que utilizam a plataforma <strong>Passe Livre Suzano</strong>. Esta política descreve as práticas adotadas em estrito cumprimento à Lei Geral de Proteção de Dados Pessoais (Lei Federal nº 13.709/2018).
      </p>

      <h2 style="font-size:20px;color:#1c1917;margin-bottom:16px;">2. Dados Coletados e Finalidade</h2>
      <ul style="color:#57534e;line-height:1.8;margin-bottom:20px;padding-left:24px;">
        <li><strong>Dados Cadastrais:</strong> Nome completo, e-mail, telefone, CPF e bairro residencial. Finalidade: identificação do cidadão, comunicação sobre solicitações e autenticação segura.</li>
        <li><strong>Dados de Saúde e PcD (Dados Sensíveis):</strong> Categoria de deficiência ou mobilidade reduzida e necessidades específicas de adaptação. Finalidade: personalização de rotas acessíveis e validação de requerimentos municipais.</li>
        <li><strong>Dados de Localização (GPS):</strong> Coordenadas geográficas coletadas apenas mediante autorização ativa do usuário durante a navegação. Finalidade: cálculo do trajeto acessível e identificação de obstáculos no perímetro de deslocamento.</li>
      </ul>

      <h2 style="font-size:20px;color:#1c1917;margin-bottom:16px;">3. Segurança da Informação e Criptografia</h2>
      <p style="color:#57534e;line-height:1.7;margin-bottom:20px;">
        Todas as senhas são armazenadas com hash criptográfico irreversível <code>Bcrypt</code> com custo elevado de fator de trabalho. Sessões e tokens de recuperação de senha possuem tempo de vida limitado (15 minutos) e são gerados com geradores pseudoaleatórios criptograficamente seguros (CSPRNG). Todas as comunicações utilizam proteção contra injeções SQL e trilha auditável de eventos com hash SHA-256.
      </p>

      <h2 style="font-size:20px;color:#1c1917;margin-bottom:16px;">4. Direitos do Titular de Dados</h2>
      <p style="color:#57534e;line-height:1.7;margin-bottom:20px;">
        Em conformidade com o Art. 18 da LGPD, o cidadão pode a qualquer momento:
      </p>
      <ul style="color:#57534e;line-height:1.8;margin-bottom:20px;padding-left:24px;">
        <li>Confirmar a existência de tratamento de seus dados na base municipal;</li>
        <li>Acessar seus dados pessoais através da tela de <a href="editar-perfil.php" style="color:#b45309;font-weight:700;">Editar Perfil</a>;</li>
        <li>Solicitar a correção de dados incompletos ou inexatos;</li>
        <li>Revogar o consentimento fornecido ou solicitar a exclusão definitiva de sua conta.</li>
      </ul>

      <h2 style="font-size:20px;color:#1c1917;margin-bottom:16px;">5. Contato do Encarregado de Proteção de Dados (DPO)</h2>
      <p style="color:#57534e;line-height:1.7;margin-bottom:20px;">
        Para exercer seus direitos ou esclarecer dúvidas sobre a privacidade dos seus dados, entre em contato com o Encarregado pelo e-mail <strong>dpo@suzano.sp.gov.br</strong> ou através do canal <a href="fale-conosco.php" style="color:#b45309;font-weight:700;">Fale Conosco</a>.
      </p>

      <div style="margin-top:32px;padding-top:20px;border-top:1px solid #f5f0eb;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
        <span style="font-size:12px;color:#78716c;">Em vigor desde Outubro de 2026</span>
        <div style="display:flex;gap:12px;">
          <a href="termos-de-uso.php" class="btn-primary" style="padding:8px 16px;font-size:12px;text-decoration:none;border-radius:6px;">Ver Termos de Uso</a>
          <a href="acessibilidade.php" class="nav-link" style="font-size:12px;font-weight:700;color:#b45309;">Recursos de Acessibilidade</a>
        </div>
      </div>
    </article>
  </main>

  <footer class="site-footer" role="contentinfo">
    <p class="footer-copy">
      &copy; 2026 Passe Livre Suzano. Todos os direitos reservados. Projeto em cooperação técnica com o SENAI Suzano.
    </p>
    <div class="footer-links">
      <a href="termos-de-uso.php" class="footer-link-item">Termos de Uso</a>
      <a href="privacidade.php" class="footer-link-item active">Privacidade (LGPD)</a>
      <a href="acessibilidade.php" class="footer-link-item">Acessibilidade</a>
      <a href="fale-conosco.php" class="footer-link-item">Fale Conosco</a>
    </div>
  </footer>
</body>
</html>
