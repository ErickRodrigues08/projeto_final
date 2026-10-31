<?php
/**
 * Passe Livre Suzano — Termos de Uso da Plataforma
 * Documento Oficial de Termos e Condições de Uso do Sistema Municipal de Acessibilidade
 */
require_once __DIR__ . '/api/_db_restore.php';
$authUser = get_auth_user();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Termos de Uso — Passe Livre Suzano</title>
  <meta name="description" content="Termos e condições de uso da plataforma Passe Livre Suzano, desenvolvida em parceria com o SENAI Suzano e a Prefeitura Municipal.">
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

  <!-- Cabeçalho -->
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
      <span class="crumb-current">Termos de Uso</span>
    </nav>

    <div class="direitos-hero-header">
      <div class="direitos-hero-text">
        <div class="pedagogico-pill">
          <span class="pedagogico-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor">
              <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/>
            </svg>
          </span>
          <span>DOCUMENTO OFICIAL MUNICIPAL</span>
        </div>
        <h1 class="direitos-main-title">Termos de Uso do Sistema Passe Livre Suzano</h1>
        <p class="direitos-main-subtitle">
          Condições gerais de navegação, colaboração cidadã e utilização dos recursos assistivos da plataforma em conformidade com a Lei Federal nº 13.146/2015 (LBI).
        </p>
      </div>
    </div>

    <!-- Conteúdo dos Termos -->
    <article class="legis-card" style="background:#fff;border-radius:16px;padding:36px;border:1px solid #e7e2d8;margin-bottom:32px;box-shadow:0 2px 8px rgba(0,0,0,.04);">
      <h2 style="font-size:20px;color:#1c1917;margin-bottom:16px;">1. Objeto e Finalidade da Plataforma</h2>
      <p style="color:#57534e;line-height:1.7;margin-bottom:20px;">
        O <strong>Passe Livre Suzano</strong> é uma solução tecnológica voltada à promoção da mobilidade urbana inclusiva, mapeamento de rotas acessíveis, auditoria colaborativa de calçadas e fiscalização de barreiras arquitetônicas no território do município de Suzano, desenvolvida em parceria acadêmica com o SENAI Suzano.
      </p>

      <h2 style="font-size:20px;color:#1c1917;margin-bottom:16px;">2. Cadastro e Responsabilidade do Usuário</h2>
      <p style="color:#57534e;line-height:1.7;margin-bottom:20px;">
        Ao realizar o cadastro, o cidadão compromete-se a fornecer informações verdadeiras e atualizadas. É vedada a utilização de contas falsas, bem como o reporte deliberado de obstáculos ou barreiras inverídicas. O cidadão é responsável pela confidencialidade de sua senha de acesso.
      </p>

      <h2 style="font-size:20px;color:#1c1917;margin-bottom:16px;">3. Reporte Comunitário e Avaliações de Acessibilidade</h2>
      <p style="color:#57534e;line-height:1.7;margin-bottom:20px;">
        As avaliações e imagens enviadas por usuários sobre estabelecimentos, calçadas e travessias integram o banco público de acessibilidade do município. O conteúdo submetido não deve conter material difamatório, ofensivo ou violação de direitos autorais de terceiros. A equipe técnica municipal e o SENAI Suzano reservam-se o direito de auditar e moderar as informações.
      </p>

      <h2 style="font-size:20px;color:#1c1917;margin-bottom:16px;">4. Cálculo de Rotas e Navegação Assistida</h2>
      <p style="color:#57534e;line-height:1.7;margin-bottom:20px;">
        As rotas sugeridas pela plataforma priorizam a eliminação de barreiras arquitetônicas (como degraus e desníveis sem rampa) com base nas informações geográficas disponíveis e nos laudos da comunidade. Em casos de obras emergenciais não reportadas, o cidadão deve sempre priorizar sua integridade física e utilizar a função "Desviar de Rota".
      </p>

      <h2 style="font-size:20px;color:#1c1917;margin-bottom:16px;">5. Legislação Aplicável e Foro</h2>
      <p style="color:#57534e;line-height:1.7;margin-bottom:20px;">
        Estes Termos são regidos pelas leis da República Federativa do Brasil, em especial pela Lei Brasileira de Inclusão da Pessoa com Deficiência (Lei nº 13.146/2015), pelo Marco Civil da Internet (Lei nº 12.965/2014) e pela LGPD (Lei nº 13.709/2018). Fica eleito o Foro da Comarca de Suzano, Estado de São Paulo, para dirimir eventuais controvérsias.
      </p>

      <div style="margin-top:32px;padding-top:20px;border-top:1px solid #f5f0eb;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
        <span style="font-size:12px;color:#78716c;">Última atualização: Outubro de 2026 — Versão 2.6</span>
        <div style="display:flex;gap:12px;">
          <a href="privacidade.php" class="btn-primary" style="padding:8px 16px;font-size:12px;text-decoration:none;border-radius:6px;">Ver Política de Privacidade</a>
          <a href="fale-conosco.php" class="nav-link" style="font-size:12px;font-weight:700;color:#b45309;">Dúvidas? Fale Conosco</a>
        </div>
      </div>
    </article>
  </main>

  <!-- Rodapé Institucional -->
  <footer class="site-footer" role="contentinfo">
    <p class="footer-copy">
      &copy; 2026 Passe Livre Suzano. Todos os direitos reservados. Projeto em cooperação técnica com o SENAI Suzano.
    </p>
    <div class="footer-links">
      <a href="termos-de-uso.php" class="footer-link-item active">Termos de Uso</a>
      <a href="privacidade.php" class="footer-link-item">Privacidade (LGPD)</a>
      <a href="acessibilidade.php" class="footer-link-item">Acessibilidade</a>
      <a href="fale-conosco.php" class="footer-link-item">Fale Conosco</a>
    </div>
  </footer>
</body>
</html>
