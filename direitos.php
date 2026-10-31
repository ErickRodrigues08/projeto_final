<?php
/**
 * Passe Livre Suzano — Critérios de Avaliação e Níveis de Acessibilidade
 * Guia Pedagógico Oficial: LBI e Norma ABNT NBR 9050:2020
 */
require_once __DIR__ . '/includes/auth_guard.php';
$authUser = auth_user_safe();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Critérios de Avaliação e Níveis de Acessibilidade — Passe Livre | Suzano</title>
  <meta name="description" content="Conheça como a plataforma Passe Livre e o SENAI Suzano classificam e auditam a infraestrutura urbana conforme a Lei Brasileira de Inclusão (LBI) e a norma ABNT NBR 9050.">

  <!-- Fontes Locais e Estilos -->
  <link rel="stylesheet" href="assets/fonts/fonts.css">
  <link rel="stylesheet" href="css/leaflet.css">
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="css/direitos.css">
  <link rel="icon" type="image/x-icon" href="favicon.ico">
  <link rel="icon" type="image/png" sizes="32x32" href="assets/icons/favicon-32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="assets/icons/favicon-16.png">
  <link rel="apple-touch-icon" href="assets/icons/apple-touch-icon.png">
</head>
<body class="direitos-page">
  <a href="#main-content" class="skip-link">Pular para o conteúdo principal</a>

  <!-- =========================================================================
       1. CABEÇALHO PRINCIPAL (HEADER)
       ========================================================================= -->
  <header class="site-header" role="banner">
    <div class="header-left">
      <a href="index.php" class="brand-logo" title="Voltar para a Página Inicial">
        <img src="assets/icons/logo.png" alt="Passe Livre Logo" width="160" height="38">
      </a>

      <div class="header-search-wrap">
        <img src="assets/icons/search.svg" class="search-icon" alt="" aria-hidden="true">
        <input 
          type="search" 
          id="global-search-input" 
          class="header-search-input" 
          placeholder="Buscar vias, pontos ou linhas..." 
          aria-label="Buscar vias, pontos ou linhas no sistema"
        >
      </div>
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
      <a href="direitos.php" class="nav-link active" aria-current="page">
        <img src="assets/icons/shield.svg" alt="" aria-hidden="true">
        <span>Direitos</span>
      </a>
    </nav>

    <div class="header-right">
      <a href="notificacoes.php" class="btn-icon-notify" id="btn-notifications" aria-label="Notificações e Avisos de Acessibilidade" title="Avisos do Sistema">
        <img src="assets/icons/bell.svg" alt="" aria-hidden="true">
      </a>

      <?php if ($authUser): ?>
        <a href="editar-perfil.php" class="user-profile" id="user-profile-badge" aria-label="Perfil do usuário <?= htmlspecialchars($authUser['name']) ?>">
          <img src="<?= htmlspecialchars($authUser['avatar'] ?? 'assets/images/avatar.jpg') ?>" alt="Foto de perfil" class="user-avatar-img" id="header-user-avatar" onerror="this.src='assets/images/avatar.jpg'">
          <div class="user-info">
            <span class="user-name" id="header-user-name"><?= htmlspecialchars($authUser['name']) ?></span>
            <span class="user-role" id="header-user-role"><?= htmlspecialchars($authUser['role'] ?? 'Cidadão Conectado') ?></span>
          </div>
        </a>
      <?php else: ?>
        <a href="login.php" class="btn-home-login" style="padding:6px 16px;font-size:13px;border-radius:9999px;background:#fff7ed;color:#b45309;border:1px solid #fed7aa;text-decoration:none;font-weight:700;">Login</a>
      <?php endif; ?>
    </div>
  </header>

  <!-- =========================================================================
       2. CONTEÚDO PRINCIPAL
       ========================================================================= -->
  <main class="direitos-main-content" id="main-content">

    <!-- Breadcrumb -->
    <nav class="direitos-breadcrumb" aria-label="Caminho de navegação">
      <a href="direitos.php" class="crumb-link">Direitos &amp; Cidadania</a>
      <span class="crumb-sep">›</span>
      <span class="crumb-current">Critérios &amp; Níveis</span>
    </nav>

    <!-- Cabeçalho Hero -->
    <div class="direitos-hero-header">
      <div class="direitos-hero-text">
        <div class="pedagogico-pill">
          <span class="pedagogico-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="currentColor">
              <path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm-2 16l-4-4 1.41-1.41L10 14.17l6.59-6.59L18 9l-8 8z"/>
            </svg>
          </span>
          <span>GUIA PEDAGÓGICO OFICIAL</span>
        </div>

        <h1 class="direitos-main-title">Critérios de Avaliação e Níveis de Acessibilidade</h1>
        <p class="direitos-main-subtitle">
          Conheça como a plataforma Passe Livre e o SENAI Suzano classificam e auditam a infraestrutura urbana conforme a Lei Brasileira de Inclusão (LBI) e a norma ABNT NBR 9050.
        </p>
      </div>

      <div class="direitos-hero-action">
        <a href="auditoria.php" class="btn-ver-auditoria" title="Visualizar histórico e laudos técnicos de auditoria">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
            <polyline points="14 2 14 8 20 8"></polyline>
            <line x1="16" y1="13" x2="8" y2="13"></line>
            <line x1="16" y1="17" x2="8" y2="17"></line>
            <polyline points="10 9 9 9 8 9"></polyline>
          </svg>
          <span>Ver Auditoria</span>
        </a>
      </div>
    </div>

    <!-- =========================================================================
         SEÇÃO 1: CLASSIFICAÇÃO OFICIAL DOS AMBIENTES
         ========================================================================= -->
    <section class="classification-section" aria-labelledby="heading-classification">
      <div class="classification-header-row">
        <div class="classification-title-wrap">
          <span class="classification-icon" aria-hidden="true">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <polygon points="12 2 2 7 12 12 22 7 12 2"></polygon>
              <polyline points="2 17 12 22 22 17"></polyline>
              <polyline points="2 12 12 17 22 12"></polyline>
            </svg>
          </span>
          <h2 class="classification-title" id="heading-classification">Classificação Oficial dos Ambientes</h2>
        </div>
        <span class="classification-standard-label">Baseado na ABNT NBR 9050:2020</span>
      </div>

      <div class="levels-grid">
        
        <!-- CARD 1: Alta Acessibilidade -->
        <article class="level-card" aria-label="Nível 1 - Alta Acessibilidade">
          <div class="level-card-top">
            <div class="level-badge-icon level-green" aria-hidden="true">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="20 6 9 17 4 12"></polyline>
              </svg>
            </div>
            <div class="level-tags-wrap">
              <span class="level-pill-tag pill-green">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                  <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                </svg>
                Selo NBR 9050
              </span>
              <span class="level-pill-tag pill-level-num">Nível 1</span>
            </div>
          </div>

          <h3 class="level-card-title">Alta Acessibilidade</h3>
          <p class="level-card-desc">
            Referência de nível 1 conforme a NBR 9050: calçadas rebaixadas, rampas regulamentares (&lt; 6%), piso tátil direcional e alerta, sanitários acessíveis, elevadores sonoros e sinalização em Braille. Um local só recebe este selo quando os itens abaixo foram conferidos em campo.
          </p>

          <ul class="level-checklist">
            <li class="level-check-item">
              <span class="check-bullet icon-green" aria-hidden="true">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                  <polyline points="22 4 12 14.01 9 11.01"></polyline>
                </svg>
              </span>
              <span>Rampa suave &lt; 6% com corrimão duplo contínuo</span>
            </li>
            <li class="level-check-item">
              <span class="check-bullet icon-green" aria-hidden="true">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                  <polyline points="22 4 12 14.01 9 11.01"></polyline>
                </svg>
              </span>
              <span>Piso podotátil alerta e direcional sem interrupções</span>
            </li>
            <li class="level-check-item">
              <span class="check-bullet icon-green" aria-hidden="true">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                  <polyline points="22 4 12 14.01 9 11.01"></polyline>
                </svg>
              </span>
              <span>Sanitário adaptado unissex com abertura para fora</span>
            </li>
          </ul>
        </article>

        <!-- CARD 2: Acessibilidade Parcial -->
        <article class="level-card" aria-label="Nível 2 - Acessibilidade Parcial">
          <div class="level-card-top">
            <div class="level-badge-icon level-amber" aria-hidden="true">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                <line x1="12" y1="9" x2="12" y2="13"></line>
                <line x1="12" y1="17" x2="12.01" y2="17"></line>
              </svg>
            </div>
            <div class="level-tags-wrap">
              <span class="level-pill-tag pill-amber">Atenção Necessária</span>
              <span class="level-pill-tag pill-level-num">Nível 2</span>
            </div>
          </div>

          <h3 class="level-card-title">Acessibilidade Parcial</h3>
          <p class="level-card-desc">
            Possui recursos de acessibilidade implantados, porém com ressalvas ou trechos que exigem atenção, como rampas com inclinação acentuada ou falta de guia tátil contínua.
          </p>

          <ul class="level-checklist">
            <li class="level-check-item">
              <span class="check-bullet icon-amber" aria-hidden="true">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                  <line x1="12" y1="9" x2="12" y2="13"></line>
                  <line x1="12" y1="17" x2="12.01" y2="17"></line>
                </svg>
              </span>
              <span>Rampa transitável, mas que pode requerer esforço adicional</span>
            </li>
            <li class="level-check-item">
              <span class="check-bullet icon-amber" aria-hidden="true">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                  <line x1="12" y1="9" x2="12" y2="13"></line>
                  <line x1="12" y1="17" x2="12.01" y2="17"></line>
                </svg>
              </span>
              <span>Sinalização visual presente com lacuna tátil parcial</span>
            </li>
            <li class="level-check-item">
              <span class="check-bullet icon-amber" aria-hidden="true">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                  <circle cx="12" cy="12" r="10"></circle>
                  <line x1="12" y1="16" x2="12" y2="12"></line>
                  <line x1="12" y1="8" x2="12.01" y2="8"></line>
                </svg>
              </span>
              <span>Recomenda-se acompanhamento se houver restrição severa</span>
            </li>
          </ul>
        </article>

        <!-- CARD 3: Baixa Acessibilidade / Barreiras -->
        <article class="level-card" aria-label="Nível 3 - Baixa Acessibilidade / Barreiras">
          <div class="level-card-top">
            <div class="level-badge-icon level-red" aria-hidden="true">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"></circle>
                <line x1="4.93" y1="4.93" x2="19.07" y2="19.07"></line>
              </svg>
            </div>
            <div class="level-tags-wrap">
              <span class="level-pill-tag pill-red">Barreira Arquitetônica</span>
              <span class="level-pill-tag pill-level-num">Nível 3</span>
            </div>
          </div>

          <h3 class="level-card-title">Baixa Acessibilidade / Barreiras</h3>
          <p class="level-card-desc">
            Locais com obstáculos severos, presença de escadarias sem alternativa de rampa/elevador, calçadas danificadas ou portas estreitas inadequadas para cadeirantes.
          </p>

          <ul class="level-checklist">
            <li class="level-check-item">
              <span class="check-bullet icon-red" aria-hidden="true">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                  <circle cx="12" cy="12" r="10"></circle>
                  <line x1="4.93" y1="4.93" x2="19.07" y2="19.07"></line>
                </svg>
              </span>
              <span>Degraus e desníveis sem mecanismo mecânico ou rampa</span>
            </li>
            <li class="level-check-item">
              <span class="check-bullet icon-red" aria-hidden="true">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                  <circle cx="12" cy="12" r="10"></circle>
                  <line x1="4.93" y1="4.93" x2="19.07" y2="19.07"></line>
                </svg>
              </span>
              <span>Portas e vãos livres inferiores a 80 cm</span>
            </li>
            <li class="level-check-item">
              <span class="check-bullet icon-red" aria-hidden="true">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                  <circle cx="12" cy="12" r="10"></circle>
                  <line x1="12" y1="8" x2="12" y2="12"></line>
                  <line x1="12" y1="16" x2="12.01" y2="16"></line>
                </svg>
              </span>
              <span>Alerta prioritário emitido à Secretaria de Manutenção Urbana</span>
            </li>
          </ul>
        </article>

        <!-- CARD 4: Não Avaliado / Em Auditoria -->
        <article class="level-card" aria-label="Nível Pendente - Não Avaliado / Em Auditoria">
          <div class="level-card-top">
            <div class="level-badge-icon level-gray" aria-hidden="true">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"></path>
                <rect x="8" y="2" width="8" height="4" rx="1" ry="1"></rect>
              </svg>
            </div>
            <div class="level-tags-wrap">
              <span class="level-pill-tag pill-gray">Aguardando Validação</span>
              <span class="level-pill-tag pill-level-num">Pendente</span>
            </div>
          </div>

          <h3 class="level-card-title">Não Avaliado / Em Auditoria</h3>
          <p class="level-card-desc">
            Locais recém-cadastrados ou pendentes de checagem técnica presencial por estudantes e fiscais voluntários PcD.
          </p>

          <ul class="level-checklist">
            <li class="level-check-item">
              <span class="check-bullet icon-gray" aria-hidden="true">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                  <polyline points="14 2 14 8 20 8"></polyline>
                </svg>
              </span>
              <span>Ficha cadastrada aguardando visita presencial</span>
            </li>
            <li class="level-check-item">
              <span class="check-bullet icon-gray" aria-hidden="true">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                  <circle cx="9" cy="7" r="4"></circle>
                  <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                  <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                </svg>
              </span>
              <span>Auditoria colaborativa aberta para moradores do bairro</span>
            </li>
            <li class="level-check-item">
              <span class="check-bullet icon-gray" aria-hidden="true">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M12 3v18"></path>
                  <rect x="3" y="7" width="6" height="5" rx="1"></rect>
                  <rect x="15" y="7" width="6" height="5" rx="1"></rect>
                  <path d="M6 7L12 3l6 4"></path>
                </svg>
              </span>
              <span>Validação final realizada por docentes do SENAI Suzano</span>
            </li>
          </ul>
        </article>

      </div>
    </section>

    <!-- =========================================================================
         SEÇÃO 2: DE ONDE VÊM OS NOSSOS DADOS?
         ========================================================================= -->
    <section class="data-sources-container" aria-labelledby="heading-datasources">
      <div class="data-sources-header">
        <span class="methodology-pill">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
            <polyline points="14 2 14 8 20 8"></polyline>
            <line x1="16" y1="13" x2="8" y2="13"></line>
            <line x1="16" y1="17" x2="8" y2="17"></line>
          </svg>
          Transparência e Metodologia
        </span>

        <h2 class="data-sources-title" id="heading-datasources">De onde vêm os nossos dados?</h2>
        <p class="data-sources-subtitle">
          A confiabilidade do Passe Livre Suzano é garantida por uma tríplice checagem cruzada, unindo a força dos registros institucionais, o rigor técnico acadêmico e a vivência diária de quem mais utiliza os caminhos da cidade.
        </p>
      </div>

      <!-- Grid com 3 Cards Ilustrados -->
      <div class="data-sources-grid">
        
        <!-- Card 1: Dados Oficiais da Prefeitura -->
        <article class="data-source-card">
          <div class="source-card-img-wrap">
            <img src="assets/images/terminal-ayrton-senna.jpg" alt="Terminal Urbano de Integração Ayrton Senna com infraestrutura acessível em Suzano" class="source-card-img">
            <div class="source-floating-badge">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M3 21h18"></path>
                <path d="M5 21V7l7-4 7 4v14"></path>
                <path d="M9 10h1"></path>
                <path d="M9 14h1"></path>
                <path d="M14 10h1"></path>
                <path d="M14 14h1"></path>
              </svg>
              <span>Governo Local</span>
            </div>
          </div>
          <div class="source-card-body">
            <h3 class="source-card-title">Dados Oficiais da Prefeitura</h3>
            <p class="source-card-desc">
              Endereço, bairro e município vêm do mapa aberto OpenStreetMap (busca e reverso de coordenadas). Os dados de acessibilidade da Prefeitura e do Plano Diretor entram na base quando a equipe importa e confere cada arquivo; nada é sincronizado automaticamente.
            </p>
            <div class="source-card-footer">
              <span>Importação conferida pela equipe</span>
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <polyline points="23 4 23 10 17 10"></polyline>
                <polyline points="1 20 1 14 7 14"></polyline>
                <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path>
              </svg>
            </div>
          </div>
        </article>

        <!-- Card 2: Auditoria Técnica do SENAI Suzano -->
        <article class="data-source-card">
          <div class="source-card-img-wrap">
            <img src="assets/images/rampa-interna.jpg" alt="Vistoria técnica de rampa de acessibilidade com corrimão duplo sob norma ABNT NBR 9050" class="source-card-img">
            <div class="source-floating-badge">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <polygon points="12 2 2 7 12 12 22 7 12 2"></polygon>
                <polyline points="2 17 12 22 22 17"></polyline>
                <polyline points="2 12 12 17 22 12"></polyline>
              </svg>
              <span>Rigor Técnico</span>
            </div>
          </div>
          <div class="source-card-body">
            <h3 class="source-card-title">Auditoria Técnica do SENAI Suzano</h3>
            <p class="source-card-desc">
              Medições in loco feitas pelos estudantes e corpo docente dos cursos de Edificações e Tecnologia da Informação do SENAI Suzano, aplicando rigorosamente cada norma da ABNT NBR 9050.
            </p>
            <div class="source-card-footer">
              <span>Laudo e Verificação In Loco</span>
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <line x1="4" y1="21" x2="4" y2="14"></line>
                <line x1="4" y1="10" x2="4" y2="3"></line>
                <line x1="12" y1="21" x2="12" y2="12"></line>
                <line x1="12" y1="8" x2="12" y2="3"></line>
                <line x1="20" y1="21" x2="20" y2="16"></line>
                <line x1="20" y1="12" x2="20" y2="3"></line>
                <line x1="1" y1="14" x2="7" y2="14"></line>
                <line x1="9" y1="8" x2="15" y2="8"></line>
                <line x1="17" y1="16" x2="23" y2="16"></line>
              </svg>
            </div>
          </div>
        </article>

        <!-- Card 3: Fiscalização Cidadã Colaborativa -->
        <article class="data-source-card">
          <div class="source-card-img-wrap">
            <img src="assets/images/shopping-suzano.jpg" alt="Espaço comercial e de circulação urbana com rampas e acessibilidade em Suzano" class="source-card-img">
            <div class="source-floating-badge">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                <circle cx="9" cy="7" r="4"></circle>
                <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
              </svg>
              <span>Comunidade Ativa</span>
            </div>
          </div>
          <div class="source-card-body">
            <h3 class="source-card-title">Fiscalização Cidadã Colaborativa</h3>
            <p class="source-card-desc">
              Relatos em tempo real enviados por munícipes e pessoas com deficiência através do aplicativo: fotos de calçadas obstruídas, elevadores de estação em pane ou novas rampas concluídas.
            </p>
            <div class="source-card-footer">
              <span>Comunidade Suzanense</span>
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
              </svg>
            </div>
          </div>
        </article>

      </div>

      <!-- Banner de Ação Inferior -->
      <div class="data-sources-cta-banner">
        <div class="cta-banner-left">
          <div class="cta-banner-icon" aria-hidden="true">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path>
              <circle cx="12" cy="13" r="4"></circle>
            </svg>
          </div>
          <div>
            <div class="cta-banner-title">Encontrou uma barreira ou melhoria recente no seu bairro?</div>
            <div class="cta-banner-sub">Sua contribuição ajuda a manter o mapa atualizado para todos os cidadãos.</div>
          </div>
        </div>

        <button type="button" class="btn-enviar-relato" id="btn-abrir-relato" title="Enviar um relato de acessibilidade ou barreira urbana">
          <span>Enviar Relato Cidadão</span>
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <line x1="5" y1="12" x2="19" y2="12"></line>
            <polyline points="12 5 19 12 12 19"></polyline>
          </svg>
        </button>
      </div>
    </section>

    <!-- =========================================================================
         SEÇÃO 3: MARCO LEGAL - LEI BRASILEIRA DE INCLUSÃO
         ========================================================================= -->
    <section class="lbi-banner-card" aria-labelledby="heading-lbi">
      <div class="lbi-content-col">
        <span class="lbi-tag-pill">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <circle cx="12" cy="12" r="10"></circle>
            <path d="M12 6v6l4 2"></path>
          </svg>
          Marco Legal da Inclusão
        </span>
        <h2 class="lbi-title" id="heading-lbi">Lei Brasileira de Inclusão (Lei nº 13.146/2015)</h2>
        <p class="lbi-desc">
          O Passe Livre Suzano fundamenta todos os seus parâmetros no Artigo 53 da LBI, que define a acessibilidade como condição essencial para a segurança, autonomia e pleno exercício dos direitos civis e de mobilidade.
        </p>
      </div>

      <div>
        <a href="https://www.confea.org.br/midias/acessibilidade_abnt_2022.pdf" target="_blank" rel="noopener noreferrer" class="btn-manual-pdf" title="Consultar o manual oficial da norma ABNT NBR 9050:2020 em PDF (Confea)">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
            <polyline points="7 10 12 15 17 10"></polyline>
            <line x1="12" y1="15" x2="12" y2="3"></line>
          </svg>
          <span>Manual NBR 9050 (PDF)</span>
        </a>
      </div>
    </section>

  </main>

  <!-- =========================================================================
       3. MODAL: ENVIAR RELATO CIDADÃO (Grava direto na API / MySQL)
       ========================================================================= -->
  <div class="modal-backdrop" id="modal-relato" role="dialog" aria-modal="true" aria-labelledby="modal-title">
    <div class="modal-card">
      <div class="modal-header">
        <h3 class="modal-title" id="modal-title">Enviar Relato Cidadão</h3>
        <button type="button" class="modal-close-btn" id="modal-close" aria-label="Fechar janela">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <line x1="18" y1="6" x2="6" y2="18"></line>
            <line x1="6" y1="6" x2="18" y2="18"></line>
          </svg>
        </button>
      </div>

      <form id="form-relato">
        <div class="modal-form-group">
          <label class="modal-label" for="relato-tipo">Tipo de Ocorrência:</label>
          <select id="relato-tipo" class="modal-select" required>
            <option value="">Selecione o tipo...</option>
            <option value="Barreira Arquitetônica">Barreira Arquitetônica (calçada quebrada, degrau sem rampa)</option>
            <option value="Nova Melhoria">Nova Melhoria / Rampa Regulamentada Concluída</option>
            <option value="Semáforo Sonoro Inoperante">Semáforo Sonoro Inoperante</option>
            <option value="Elevador em Manutenção">Elevador / Acesso em Manutenção</option>
            <option value="Piso Podotátil Obstruído">Piso Podotátil Obstruído ou Danificado</option>
          </select>
        </div>

        <div class="modal-form-group relato-location-section" aria-labelledby="relato-location-title">
          <p class="relato-location-title" id="relato-location-title">Onde ocorreu o problema?</p>
          <p class="relato-location-hint" id="relato-location-hint">
            Informe o endereço, o CEP ou use sua localização atual. Basta uma opção para identificar o local.
          </p>

          <div class="relato-field">
            <label class="modal-label" for="relato-endereco">Endereço do ocorrido</label>
            <input type="text" id="relato-endereco" class="modal-input" placeholder="Ex.: Rua General Francisco Glicério, próximo ao nº 450" autocomplete="street-address">
          </div>

          <div class="relato-cep-row">
            <div class="relato-field">
              <label class="modal-label" for="relato-cep">CEP <span class="relato-optional">(opcional)</span></label>
              <input type="text" id="relato-cep" class="modal-input" placeholder="00000-000" inputmode="numeric" maxlength="9" autocomplete="postal-code">
            </div>
            <button type="button" class="btn-relato-cep" id="btn-buscar-cep">Buscar CEP</button>
          </div>

          <div class="relato-location-grid">
            <div class="relato-field">
              <label class="modal-label" for="relato-bairro">Bairro</label>
              <input type="text" id="relato-bairro" class="modal-input" maxlength="100" autocomplete="address-level3">
            </div>
            <div class="relato-field">
              <label class="modal-label" for="relato-cidade">Cidade</label>
              <input type="text" id="relato-cidade" class="modal-input" maxlength="100" autocomplete="address-level2">
            </div>
            <div class="relato-field relato-field-uf">
              <label class="modal-label" for="relato-estado">UF</label>
              <input type="text" id="relato-estado" class="modal-input" maxlength="2" autocomplete="address-level1">
            </div>
          </div>

          <div class="relato-location-actions">
            <button type="button" class="btn-relato-gps" id="btn-usar-gps" aria-describedby="relato-status">
              <img src="assets/icons/gps.svg" alt="" aria-hidden="true">
              <span>Usar minha localização atual</span>
            </button>
            <button type="button" class="btn-relato-map" id="btn-abrir-mapa" aria-expanded="false" aria-controls="relato-map-wrap">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                <circle cx="12" cy="10" r="3"></circle>
              </svg>
              <span>Escolher no mapa</span>
            </button>
          </div>

          <p class="relato-location-status" id="relato-status" role="status" aria-live="polite"></p>

          <div class="relato-location-summary" id="relato-location-summary" hidden>
            <div class="relato-location-summary-text">
              <strong id="relato-location-summary-title">Localização identificada</strong>
              <span id="relato-location-address"></span>
              <span id="relato-location-coords"></span>
            </div>
            <button type="button" class="btn-relato-confirm" id="btn-confirmar-localizacao" disabled>Confirmar localização</button>
          </div>

          <div class="relato-map-wrap" id="relato-map-wrap" hidden>
            <div class="relato-map" id="relato-map" role="application" aria-label="Mapa para escolher o local do ocorrido"></div>
            <div class="relato-map-footer">
              <span class="relato-map-hint">Toque no mapa ou arraste o marcador para ajustar o ponto.</span>
              <button type="button" class="btn-relato-recenter" id="btn-mapa-centralizar">Centralizar no ponto</button>
            </div>
          </div>

          <input type="hidden" id="relato-latitude" name="latitude" value="">
          <input type="hidden" id="relato-longitude" name="longitude" value="">
        </div>

        <div class="modal-form-group">
          <label class="modal-label" for="relato-descricao">Descrição do Relato:</label>
          <textarea id="relato-descricao" class="modal-textarea" placeholder="Descreva os detalhes da condição encontrada..." required></textarea>
        </div>

        <div class="modal-actions">
          <button type="button" class="btn-modal-cancel" id="btn-cancelar-relato">Cancelar</button>
          <button type="submit" class="btn-modal-submit" id="btn-confirmar-relato">Enviar para Auditoria</button>
        </div>
      </form>
    </div>
  </div>

  <!-- =========================================================================
       4. RODAPÉ INSTITUCIONAL
       ========================================================================= -->
  <footer class="direitos-footer" role="contentinfo">
    <div class="footer-credits-text">
      &copy; 2026 Passe Livre. Todos os direitos reservados. Projeto desenvolvido por Samuel, Rodrigo, Erick, Gabrielly e Ana Júlia — SENAI, Suzano.
    </div>
    <nav class="footer-nav-links" aria-label="Links institucionais de rodapé">
      <a href="termos-de-uso.php">Termos de Uso</a>
      <span aria-hidden="true">&bull;</span>
      <a href="privacidade.php">Privacidade (LGPD)</a>
      <span aria-hidden="true">&bull;</span>
      <a href="acessibilidade.php">Acessibilidade</a>
      <span aria-hidden="true">&bull;</span>
      <a href="index.php#contato">Fale Conosco</a>
    </nav>
  </footer>

  <!-- Toast Notification -->
  <div class="toast-notice" id="app-toast" style="display: none; position: fixed; bottom: 24px; right: 24px; background: #1c1917; color: #fff; padding: 12px 20px; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.2); z-index: 10000; align-items: center; gap: 10px; font-size: 13px; font-weight: 600;">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
      <polyline points="20 6 9 17 4 12"></polyline>
    </svg>
    <span id="toast-message">Relato registrado com sucesso! Obrigado pela colaboração.</span>
  </div>

  <script src="js/auth.js"></script>
  <script src="js/leaflet.js"></script>
  <script src="js/direitos.js"></script>
</body>
</html>
