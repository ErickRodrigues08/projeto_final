<?php
/**
 * Passe Livre Suzano — Página Inicial (Landing Page)
 * Arquitetura unificada PHP/MySQL
 */
require_once __DIR__ . '/includes/auth_guard.php';
$authUser = auth_user_safe();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Passe Livre — Seu caminho, sem barreira | Suzano</title>
  <meta name="description" content="Plataforma de navegação inclusiva e mobilidade acessível desenvolvida por alunos do SENAI Suzano para transformar o transporte público e rotas urbanas.">
  
  <!-- Fontes Locais e Estilos -->
  <link rel="stylesheet" href="assets/fonts/fonts.css">
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="css/home.css">
  <link rel="stylesheet" href="css/home-polish.css?v=5.5">
  <link rel="icon" type="image/x-icon" href="favicon.ico">
  <link rel="icon" type="image/png" sizes="32x32" href="assets/icons/favicon-32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="assets/icons/favicon-16.png">
  <link rel="apple-touch-icon" href="assets/icons/apple-touch-icon.png">
</head>
<body class="home-page">
  <a href="#inicio" class="skip-link">Pular para o conteúdo principal</a>

  <!-- =========================================================================
       1. NAVBAR PRINCIPAL
       ========================================================================= -->
  <header class="home-navbar" role="banner">
    <a href="index.php" class="brand-logo" title="Passe Livre Suzano">
      <img src="assets/icons/logo.png" alt="Passe Livre Logo" width="160" height="38">
    </a>

    <nav aria-label="Navegação da Página">
      <ul class="home-nav-links" id="home-nav-links">
        <li><a href="#inicio" class="active">Início</a></li>
        <li><a href="#como-funciona">Como funciona</a></li>
        <li><a href="#equipe">Comunidade</a></li>
        <li><a href="#sobre">Sobre</a></li>
        <li><a href="#contato">Contate-nos</a></li>
      </ul>
    </nav>

    <div id="home-auth-area" class="home-auth-area" style="display:flex;align-items:center;gap:12px;">
      <?php if ($authUser): ?>
        <?php 
          $targetUrl = !empty($authUser['is_admin']) ? 'dashboard.php' : 'editar-perfil.php';
        ?>
        <a href="<?= $targetUrl ?>" class="btn-home-login" title="Acessar sua conta" style="display:inline-flex;align-items:center;gap:8px;">
          <img src="<?= htmlspecialchars($authUser['avatar'] ?? 'assets/images/avatar.jpg') ?>" alt="" style="width:24px;height:24px;border-radius:50%;object-fit:cover;" onerror="this.src='assets/images/avatar.jpg'">
          <span><?= htmlspecialchars($authUser['name']) ?></span>
        </a>
      <?php else: ?>
        <a href="cadastro.php" class="btn-home-cadastro" style="font-size:13px;font-weight:700;color:#b45309;text-decoration:none;padding:6px 14px;border-radius:9999px;border:1px solid #fed7aa;background:#fff7ed;transition:all 0.15s;">Criar conta</a>
        <a href="login.php" class="btn-home-login" title="Acessar sua conta">
          <img src="assets/icons/login.svg" alt="" style="width: 14px; height: 14px;">
          <span>Login</span>
        </a>
      <?php endif; ?>
    </div>

    <button type="button" class="home-nav-toggle" id="home-nav-toggle" aria-expanded="false" aria-controls="home-nav-links" aria-label="Abrir menu de navegação">
      <span></span><span></span><span></span>
    </button>
  </header>

  <main id="main-content">
  <!-- =========================================================================
       2. HERO SECTION ("SEU CAMINHO, SEM BARREIRA")
       ========================================================================= -->
  <section class="home-hero-section" id="inicio" aria-label="Apresentação do Passe Livre">
  
    <!-- Conteúdo Textual e CTA à Direita -->
    <div class="hero-text-content">
      <span class="hero-eyebrow-tag">Transporte Inclusivo em Suzano</span>
      
      <h1 class="hero-big-title">
        SEU CAMINHO,<br>
        <span class="highlight-amber">SEM BARREIRA</span>
      </h1>

      <p class="hero-description-lead">
        Com o aplicativo Passe Livre, você tem rotas calculadas para diferentes tipos de acessibilidade. Calçadas niveladas, semáforos sonoros, elevadores funcionais e transporte público adaptado em tempo real na palma da sua mão.
      </p>

      <!-- Card de Ação Principal -->
      <div class="hero-cta-action-card">
        <span class="cta-card-label">Comece agora a navegar pela cidade com autonomia</span>

        <!-- Botão Principal direcionando para a tela de mapa em Suzano -->
        <a href="explorar.php" class="btn-hero-cta" title="Abrir o mapa acessível de Suzano">
          <span>Experimente agora</span>
          <img src="assets/icons/arrow-right.svg" alt="" style="width: 18px; height: 18px; filter: brightness(0) invert(1);">
        </a>

        <div class="cta-tags-row">
          <div style="display: flex; align-items: center; gap: 6px;">
            <img src="assets/icons/check.svg" alt="" style="width: 14px; height: 14px;">
            <span>Gratuito para todos os cidadãos</span>
          </div>

          <div style="display: flex; align-items: center; gap: 6px;">
            <img src="assets/icons/check.svg" alt="" style="width: 14px; height: 14px;">
            <span>Mapeamento certificado pelo SENAI</span>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- =========================================================================
       3. SEÇÃO: PROJETADO PARA CADA NECESSIDADE
       ========================================================================= -->
  <section class="needs-section" id="como-funciona" aria-labelledby="heading-needs">
    <div class="section-container-centered">
      <span class="section-eyebrow">Recursos Principais</span>
      <h2 class="section-main-heading" id="heading-needs">Projetado Para Cada Necessidade</h2>
      <p class="section-sub-heading">
        Personalização de rotas e alertas específicos para cada perfil de mobilidade e autonomia urbana.
      </p>

      <div class="needs-cards-grid">
        <!-- Card 1: Mobilidade Reduzida -->
        <div class="need-card-item">
          <div class="need-card-icon-wrap">
            <img src="assets/icons/pcd.png" alt="Cadeirante">
          </div>
          <h3 class="need-card-title">Mobilidade Reduzida</h3>
          <p class="need-card-desc">
            Rotas sem degraus, com rampas de inclinação adequada e calçadas niveladas sem desníveis ou obstáculos.
          </p>
          <a href="explorar.php" class="need-card-link">Conhecer rotas &rarr;</a>
        </div>

        <!-- Card 2: Deficiência Visual -->
        <div class="need-card-item">
          <div class="need-card-icon-wrap">
            <img src="assets/icons/eye.png" alt="Deficiência Visual">
          </div>
          <h3 class="need-card-title">Deficiência Visual</h3>
          <p class="need-card-desc">
            Itinerários com piso tátil direcional e de alerta contínuos, semáforos sonoros ativos e suporte por voz.
          </p>
          <a href="explorar.php" class="need-card-link">Saiba mais &rarr;</a>
        </div>

        <!-- Card 3: Deficiência Auditiva -->
        <div class="need-card-item">
          <div class="need-card-icon-wrap">
            <img src="assets/icons/ear.png" alt="Deficiência Auditiva">
          </div>
          <h3 class="need-card-title">Deficiência Auditiva</h3>
          <p class="need-card-desc">
            Informações textuais e alertas visuais em tempo real nos pontos de parada, terminais e veículos de Suzano.
          </p>
          <a href="explorar.php" class="need-card-link">Como funciona &rarr;</a>
        </div>

        <!-- Card 4: Idosos e Cuidado Especial -->
        <div class="need-card-item">
          <div class="need-card-icon-wrap">
            <img src="assets/icons/senior.png" alt="Idosos">
          </div>
          <h3 class="need-card-title">Idosos e Cuidado Especial</h3>
          <p class="need-card-desc">
            Trajetos com tempo estendido em semáforos, paradas seguras com assentos e acesso prioritário aos modais.
          </p>
          <a href="explorar.php" class="need-card-link">Ver recursos &rarr;</a>
        </div>
      </div>
    </div>
  </section>

  <!-- =========================================================================
       4. SEÇÃO: NOSSA EQUIPE (SENAI SUZANO)
       ========================================================================= -->
  <section class="team-section" id="equipe" aria-labelledby="heading-team">
    <div class="section-container-centered">
      <span class="section-eyebrow">Criadores &amp; Desenvolvedores</span>
      <h2 class="section-main-heading" id="heading-team">NOSSA EQUIPE</h2>
      <p class="section-sub-heading">
        Os idealizadores e desenvolvedores responsáveis pelo projeto Passe Livre — SENAI Suzano.
      </p>

      <div class="team-cards-grid">
        <!-- 1. Rodrigo -->
        <div class="team-card-item">
          <img src="assets/images/team/team-rodrigo.png" alt="Foto de Rodrigo" class="team-avatar-img">
          <h3 class="team-member-name">Rodrigo</h3>
          <span class="team-member-role">Líder de Projeto &amp; Articulação Social</span>
          <p class="team-member-bio">Gestão de parcerias com conselhos comunitários, órgãos municipais e articulação institucional.</p>
        </div>

        <!-- 2. Samuel -->
        <div class="team-card-item">
          <img src="assets/images/team/team-samuel.png" alt="Foto de Samuel" class="team-avatar-img">
          <h3 class="team-member-name">Samuel</h3>
          <span class="team-member-role">Desenvolvedor Front-end &amp; UX</span>
          <p class="team-member-bio">Especialista em interfaces intuitivas e acessibilidade digital para pessoas com deficiência.</p>
        </div>

        <!-- 3. Erick -->
        <div class="team-card-item">
          <img src="assets/images/team/team-erick.png" alt="Foto de Erick" class="team-avatar-img">
          <h3 class="team-member-name">Erick</h3>
          <span class="team-member-role">Desenvolvedor Full-stack &amp; Arquitetura</span>
          <p class="team-member-bio">Responsável pela infraestrutura do sistema, integração com dados da cidade e algoritmos de rota.</p>
        </div>

        <!-- 4. Gabrielly -->
        <div class="team-card-item">
          <img src="assets/images/team/team-gabrielly.png" alt="Foto de Gabrielly" class="team-avatar-img">
          <h3 class="team-member-name">Gabrielly</h3>
          <span class="team-member-role">Designer UI/UX &amp; Acessibilidade</span>
          <p class="team-member-bio">Criação dos fluxos visuais, contraste universal e adaptação inclusiva da experiência.</p>
        </div>

        <!-- 5. Ana Júlia (Centralizado) -->
        <div class="team-card-item team-card-centered">
          <img src="assets/images/team/team-anajulia.png" alt="Foto de Ana Júlia" class="team-avatar-img">
          <h3 class="team-member-name">Ana Júlia</h3>
          <span class="team-member-role">Pesquisador de Acessibilidade &amp; Dados</span>
          <p class="team-member-bio">Mapeamento em campo dos pontos críticos de calçadas e vistorias técnicas em Suzano.</p>
        </div>
      </div>
    </div>
  </section>

  <!-- =========================================================================
       5. SEÇÃO: SOBRE O PASSE LIVRE
       ========================================================================= -->
  <section class="about-section" id="sobre" aria-labelledby="heading-about">
    <span class="section-eyebrow">Conheça o Projeto</span>
    <h2 class="section-main-heading" id="heading-about">Passe Livre</h2>
    
    <p class="about-manifesto-text">
      Nossa missão é construir uma cidade mais inclusiva para que todas as pessoas possam se deslocar com segurança e independência. Desenvolvido por estudantes do SENAI de Ferraz de Vasconcelos, a plataforma mapeia, audita e disponibiliza rotas livres de obstáculos e barreiras arquitetônicas.
    </p>

    <!-- Card de Destaque: Uma ideia que virou propósito -->
    <div class="about-origin-card">
      <span class="origin-badge">Nossa Origem</span>
      <h3 class="origin-card-title">Uma ideia que virou propósito</h3>
      <p class="origin-card-desc">
        Tudo começou quando identificamos os desafios diários de quem precisa se locomover em nossa cidade. Samuel, Rodrigo, Erick, Gabrielly e Ana Julia se uniram para transformar dados sobre calçadas, transporte público e acessibilidade em uma ferramenta prática e colaborativa: o Passe Livre.
      </p>
      <div class="origin-quote-box">
        "A acessibilidade não é um favor nem privilégio, é o direito básico de ir e vir garantido para todos os cidadãos."
      </div>
    </div>
  </section>

  <!-- =========================================================================
       6. SEÇÃO: FORMULÁRIO DE CONTATO
       ========================================================================= -->
  <section class="contact-section" id="contato" aria-labelledby="heading-contact">
    <div class="contact-card-box">
      
      <!-- Formulário à Esquerda -->
      <div class="contact-left-col">
        <div class="contact-bar-header" id="heading-contact">
          Formulário de Contato
        </div>

        <form id="contact-form" novalidate>
          <div class="contact-form-row">
            <label for="contact-name" class="contact-form-label">Nome:</label>
            <input type="text" id="contact-name" class="contact-input" placeholder="Digite seu nome completo..." required>
          </div>

          <div class="contact-form-row" style="margin-top: 10px;">
            <label for="contact-email" class="contact-form-label">E-mail:</label>
            <input type="email" id="contact-email" class="contact-input" placeholder="seuemail@exemplo.com" required>
          </div>

          <div class="contact-form-row" style="margin-top: 10px;">
            <label for="contact-message" class="contact-form-label">Texto:</label>
            <textarea id="contact-message" class="contact-textarea" placeholder="Escreva sua dúvida, sugestão de melhoria ou parceria..." required></textarea>
          </div>

          <div style="margin-top: 14px;">
            <button type="submit" class="btn-contact-submit">Enviar</button>
          </div>
        </form>
      </div>

      <!-- Ilustração e Informações à Direita -->
      <div class="contact-right-col">
        <img src="assets/images/contact-illustration.png" alt="Atendimento inclusivo Passe Livre" class="contact-illustration-img">

        <div class="contact-info-list">
          <div class="contact-info-item">
            <img src="assets/icons/phone.svg" alt="" aria-hidden="true">
            <span>Telefone: (11) 4748-0000</span>
          </div>

          <div class="contact-info-item">
            <img src="assets/icons/mail.svg" alt="" aria-hidden="true">
            <span>E-mail: contato@passelivre.org.br</span>
          </div>

          <div class="contact-info-item">
            <img src="assets/icons/pin.png" alt="" aria-hidden="true">
            <span>Endereço: Av. Senador Roberto Simonsen, 550 - SENAI Suzano</span>
          </div>
        </div>

        <!-- Ícones Sociais -->
        <div class="social-links-row" role="region" aria-label="Redes sociais do projeto">
          <a href="https://www.instagram.com/somospasselivre/" class="social-icon-btn" title="Instagram do Passe Livre" aria-label="Instagram" target="_blank" rel="noopener noreferrer">
            <img src="assets/icons/instagram.svg" alt="" aria-hidden="true">
          </a>
          <a href="https://www.linkedin.com/in/passe-livre-622204448/" class="social-icon-btn" title="LinkedIn do Passe Livre" aria-label="LinkedIn" target="_blank" rel="noopener noreferrer">
            <img src="assets/icons/linkedin.svg" alt="" aria-hidden="true">
          </a>
          <a href="https://facebook.com" class="social-icon-btn" title="Facebook do Passe Livre" aria-label="Facebook" target="_blank" rel="noopener noreferrer">
            <img src="assets/icons/facebook.svg" alt="" aria-hidden="true">
          </a>
          <a href="https://youtube.com" class="social-icon-btn" title="YouTube do Passe Livre" aria-label="YouTube" target="_blank" rel="noopener noreferrer">
            <img src="assets/icons/youtube.png" alt="" aria-hidden="true">
          </a>
        </div>
      </div>
    </div>
  </section>
  </main>

  <!-- =========================================================================
       7. RODAPÉ INSTITUCIONAL
       ========================================================================= -->
  <footer class="site-footer" role="contentinfo">
    <div class="footer-credits">
      &copy; 2026 Passe Livre. Todos os direitos reservados. Projeto desenvolvido por Samuel, Rodrigo, Erick, Gabrielly e Ana Júlia — SENAI, Suzano.
    </div>
    <div class="footer-links">
      <a href="direitos.php#termos" class="footer-link-item">Termos de uso</a>
      <span>&bull;</span>
      <a href="direitos.php#privacidade" class="footer-link-item">Privacidade</a>
      <span>&bull;</span>
      <a href="ajuda.php" class="footer-link-item">Suporte</a>
    </div>
  </footer>

  <!-- Toast de Feedback -->
  <div class="toast-notice" id="app-toast">
    <img src="assets/icons/check.svg" width="18" height="18" alt="">
    <span id="toast-message">Mensagem enviada com sucesso!</span>
  </div>

  <script src="js/auth.js"></script>
  <script src="js/home.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/gsap@3.12.5/dist/gsap.min.js" defer></script>
  <script src="https://cdn.jsdelivr.net/npm/gsap@3.12.5/dist/ScrollTrigger.min.js" defer></script>
  <script src="js/entrada-animacoes.js" defer></script>
</body>
</html>
