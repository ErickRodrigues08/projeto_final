<?php
/**
 * Passe Livre Suzano — Central de Ajuda
 * Base de conhecimento, busca, categorias e suporte.
 */
require_once __DIR__ . '/includes/auth_guard.php';
$authUser = auth_user_safe();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Central de Ajuda — Passe Livre | Suzano</title>
  <meta name="description" content="Central de Ajuda do Passe Livre: respostas sobre cadastro, preferências de acessibilidade, rotas inclusivas e fiscalização cidadã em Suzano.">

  <!-- Fontes Locais e Estilos -->
  <link rel="stylesheet" href="assets/fonts/fonts.css">
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="css/home.css">
  <link rel="stylesheet" href="css/ajuda.css">
  <link rel="icon" type="image/x-icon" href="favicon.ico">
  <link rel="icon" type="image/png" sizes="32x32" href="assets/icons/favicon-32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="assets/icons/favicon-16.png">
  <link rel="apple-touch-icon" href="assets/icons/apple-touch-icon.png">
</head>
<body class="home-page help-page">
  <a href="#main-content" class="skip-link">Pular para o conteúdo principal</a>

  <!-- =========================================================================
       1. NAVBAR PRINCIPAL
       ========================================================================= -->
  <header class="home-navbar" role="banner">
    <a href="index.php" class="brand-logo" title="Passe Livre">
      <img src="assets/icons/logo.png" alt="Passe Livre Logo" width="160" height="38">
    </a>

    <nav aria-label="Navegação da Página">
      <ul class="home-nav-links" id="home-nav-links">
        <li><a href="index.php#inicio">Início</a></li>
        <li><a href="index.php#como-funciona">Como funciona</a></li>
        <li><a href="index.php#equipe">Comunidade</a></li>
        <li><a href="index.php#sobre">Sobre</a></li>
        <li><a href="index.php#contato">Contate-nos</a></li>
      </ul>
    </nav>

    <div id="home-auth-area" style="display: flex; align-items: center; gap: 12px;">
      <?php if ($authUser): ?>
        <?php 
          $targetUrl = !empty($authUser['is_admin']) ? 'dashboard.php' : 'editar-perfil.php';
        ?>
        <a href="<?= $targetUrl ?>" class="btn-home-login" title="Acessar sua conta" style="display:inline-flex;align-items:center;gap:8px;">
          <img src="<?= htmlspecialchars($authUser['avatar'] ?? 'assets/images/avatar.jpg') ?>" alt="" style="width:24px;height:24px;border-radius:50%;object-fit:cover;" onerror="this.src='assets/images/avatar.jpg'">
          <span><?= htmlspecialchars($authUser['name']) ?></span>
        </a>
      <?php else: ?>
        <a href="cadastro.php" class="btn-home-cadastro" style="font-size: 13px; font-weight: 700; color: #b45309; text-decoration: none; padding: 6px 14px; border-radius: 9999px; border: 1px solid #fed7aa; background: #fff7ed; transition: all 0.15s;">Criar conta</a>
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
         2. HERO + BUSCA
         ========================================================================= -->
    <section class="help-hero" id="topo" aria-labelledby="heading-help">
      <div class="help-hero-inner">
        <span class="section-eyebrow">Central de Ajuda</span>
        <h1 class="section-main-heading help-hero-title" id="heading-help">Como podemos ajudar você hoje?</h1>
        <p class="section-sub-heading help-hero-sub">
          Encontre respostas rápidas sobre sua conta, preferências PcD, traçado de rotas acessíveis e fiscalização em Suzano.
        </p>

        <form class="help-search" id="help-search-form" role="search" autocomplete="off">
          <img src="assets/icons/search.svg" alt="" class="help-search-icon">
          <label for="help-search-input" class="sr-only">Buscar na Central de Ajuda</label>
          <input type="search" id="help-search-input" class="help-search-input"
                 placeholder="Buscar por dúvida, palavra-chave (ex: rampa, piso tátil, obstáculo, rota)...">
          <button type="submit" class="help-search-btn">Buscar</button>
        </form>

        <div class="help-chips" aria-label="Mais buscados">
          <span class="help-chips-label">Mais buscados:</span>
          <button type="button" class="help-chip" data-query="senha">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 12a9 9 0 1 0 3-6.7"/><polyline points="3 4 3 9 8 9"/></svg>
            Recuperar senha
          </button>
          <button type="button" class="help-chip" data-query="rota">
            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="6" cy="19" r="3"/><path d="M9 19h8.5a3.5 3.5 0 0 0 0-7h-11a3.5 3.5 0 0 1 0-7H15"/><circle cx="18" cy="5" r="3"/></svg>
            Rotas acessíveis
          </button>
          <button type="button" class="help-chip" data-query="obstaculo">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
            Reportar obstáculo
          </button>
          <button type="button" class="help-chip" data-query="auditoria">
            <svg viewBox="0 0 24 24" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
            Normas NBR 9050
          </button>
          <button type="button" class="help-chip" data-query="apoio">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
            Pontos de apoio
          </button>
        </div>
      </div>
    </section>

    <!-- =========================================================================
         3. CATEGORIAS DE ATENDIMENTO
         ========================================================================= -->
    <section class="help-categories" aria-labelledby="heading-categories">
      <div class="help-container">
        <div class="help-section-head">
          <div>
            <h2 class="help-h2" id="heading-categories">Categorias de Atendimento</h2>
            <p class="help-h2-sub">Selecione o assunto da sua dúvida para orientações detalhadas</p>
          </div>
          <a href="#faq" class="help-link-all" data-action="show-all">Ver todos os tópicos &rarr;</a>
        </div>

        <div class="help-cat-grid">
          <button type="button" class="help-cat-card" data-cat="acesso">
            <span class="need-card-icon-wrap">
              <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="7.5" cy="15.5" r="4.5"/><path d="M10.7 12.3 21 2"/><path d="m16 7 3 3"/><path d="m18 5 2 2"/></svg>
            </span>
            <h3 class="help-cat-title">Acesso &amp; Conta</h3>
            <p class="help-cat-desc">Problemas com login, redefinição de senha, preferências de acessibilidade e segurança LGPD.</p>
            <span class="help-cat-link">Guia de Acesso <span aria-hidden="true">&rsaquo;</span></span>
          </button>

          <button type="button" class="help-cat-card" data-cat="beneficio">
            <span class="need-card-icon-wrap">
              <svg viewBox="0 0 24 24" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
            </span>
            <h3 class="help-cat-title">Rotas &amp; Normas NBR 9050</h3>
            <p class="help-cat-desc">Como funcionam as rotas acessíveis, critérios de auditoria técnica do SENAI Suzano e pisos táteis.</p>
            <span class="help-cat-link">Critérios e Normas <span aria-hidden="true">&rsaquo;</span></span>
          </button>

          <button type="button" class="help-cat-card" data-cat="uso">
            <span class="need-card-icon-wrap">
              <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
            </span>
            <h3 class="help-cat-title">Fiscalização &amp; Obstáculos</h3>
            <p class="help-cat-desc">Como reportar calçadas esburacadas, semáforos sem áudio, obras e barreiras na cidade de Suzano.</p>
            <span class="help-cat-link">Reportar Barreira <span aria-hidden="true">&rsaquo;</span></span>
          </button>

          <button type="button" class="help-cat-card" data-cat="via">
            <span class="need-card-icon-wrap">
              <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
            </span>
            <h3 class="help-cat-title">Locais &amp; Pontos de Apoio</h3>
            <p class="help-cat-desc">Como encontrar pontos de apoio com sanitários adaptados, rampas e atendimento inclusivo em Suzano.</p>
            <span class="help-cat-link">Ver Locais <span aria-hidden="true">&rsaquo;</span></span>
          </button>
        </div>
      </div>
    </section>

    <!-- =========================================================================
         4. PERGUNTAS FREQUENTES
         ========================================================================= -->
    <section class="help-faq" id="faq" aria-labelledby="heading-faq">
      <div class="help-faq-inner">
        <span class="help-kb-badge">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
          Base de Conhecimento
        </span>
        <h2 class="section-main-heading" id="heading-faq">Perguntas Frequentes</h2>
        <p class="section-sub-heading help-faq-sub">As dúvidas mais comuns resolvidas passo a passo para o estudante</p>

        <!-- Barra de filtro ativo -->
        <div class="help-filter-bar" id="help-filter-bar" hidden>
          <span id="help-filter-text"></span>
          <button type="button" id="help-filter-clear">Limpar filtro &times;</button>
        </div>

        <div class="help-faq-list" id="help-faq-list">

          <!-- 1 -->
          <article class="help-faq-item open" data-cat="acesso"
                   data-keywords="código verificação e-mail email não chegou otp spam token validação">
            <h3 class="help-faq-q">
              <button type="button" class="help-faq-toggle" aria-expanded="true" aria-controls="faq-a1" id="faq-q1">
                <span class="help-faq-num">1</span>
                <span class="help-faq-text">O código de verificação por e-mail não chegou. O que devo fazer?</span>
                <svg class="help-faq-chevron" viewBox="0 0 24 24" aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg>
              </button>
            </h3>
            <div class="help-faq-a" id="faq-a1" role="region" aria-labelledby="faq-q1">
              <div class="help-faq-a-inner">
                <div class="help-faq-a-content">
                  <p>A entrega de códigos de segurança pode sofrer breves oscilações no servidor de autenticação. Recomendamos seguir os seguintes passos práticos:</p>
                  <ul class="help-check-list">
                    <li><b>Verifique sua caixa de Spam e Lixo Eletrônico:</b> Muitas vezes mensagens institucionais automáticas são filtradas pelo provedor (Gmail, Outlook, Yahoo).</li>
                    <li><b>Confirme a digitação do e-mail:</b> Certifique-se de que não houve erro no domínio (ex: @gmail.com em vez de @gmai.com).</li>
                    <li><b>Aguarde o temporizador de segurança:</b> O botão "Reenviar código" fica liberado após 30 segundos. Evite solicitações múltiplas consecutivas.</li>
                  </ul>
                  <div class="help-note">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 8h.01"/><path d="M11 12h1v4h1"/></svg>
                    <span>Caso o problema persista, você poderá solicitar envio por SMS cadastrado ou acionar o chat online.</span>
                  </div>
                </div>
              </div>
            </div>
          </article>

          <!-- 2 -->
          <article class="help-faq-item" data-cat="acesso"
                   data-keywords="senha redefinir recuperar esqueci acesso login trocar segurança">
            <h3 class="help-faq-q">
              <button type="button" class="help-faq-toggle" aria-expanded="false" aria-controls="faq-a2" id="faq-q2">
                <span class="help-faq-num">2</span>
                <span class="help-faq-text">Como redefinir minha senha de acesso com segurança?</span>
                <svg class="help-faq-chevron" viewBox="0 0 24 24" aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg>
              </button>
            </h3>
            <div class="help-faq-a" id="faq-a2" role="region" aria-labelledby="faq-q2">
              <div class="help-faq-a-inner">
                <div class="help-faq-a-content">
                  <p>A redefinição é feita em poucos minutos, diretamente pela tela de login:</p>
                  <ul class="help-check-list">
                    <li><b>Acesse "Esqueci minha senha":</b> Na tela de <a href="login.php">login</a>, clique no link de recuperação e informe o e-mail cadastrado.</li>
                    <li><b>Informe o código recebido:</b> Enviamos um código de verificação para confirmar que a conta é sua.</li>
                    <li><b>Crie uma nova senha forte:</b> Use letras, números e símbolos, e evite repetir senhas de outros serviços.</li>
                  </ul>
                  <div class="help-note">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 8h.01"/><path d="M11 12h1v4h1"/></svg>
                    <span>Você pode iniciar agora mesmo em <a href="esqueci-senha.php">Recuperar senha</a>.</span>
                  </div>
                </div>
              </div>
            </div>
          </article>

          <!-- 3 -->
          <article class="help-faq-item" data-cat="beneficio"
                   data-keywords="rotas acessíveis nbr 9050 auditoria calçadas rampas inclinação piso tátil trajeto">
            <h3 class="help-faq-q">
              <button type="button" class="help-faq-toggle" aria-expanded="false" aria-controls="faq-a3" id="faq-q3">
                <span class="help-faq-num">3</span>
                <span class="help-faq-text">Como são auditadas e calculadas as rotas acessíveis em Suzano?</span>
                <svg class="help-faq-chevron" viewBox="0 0 24 24" aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg>
              </button>
            </h3>
            <div class="help-faq-a" id="faq-a3" role="region" aria-labelledby="faq-q3">
              <div class="help-faq-a-inner">
                <div class="help-faq-a-content">
                  <p>O algoritmo do Passe Livre analisa critérios físicos da malha urbana conforme a norma técnica ABNT NBR 9050:</p>
                  <ul class="help-check-list">
                    <li><b>Declividade e Rampas:</b> Prioriza vias planas e calçadas com inclinação suave (&le; 8,33%) e guias rebaixadas com transição contínua.</li>
                    <li><b>Piso Podotátil:</b> Mapeia rotas com piso direcional e de alerta para orientação de pessoas cegas ou com baixa visão.</li>
                    <li><b>Ausência de Degraus:</b> Rotas para cadeirantes eliminam travessias com desníveis acentuados ou barreiras intransponíveis.</li>
                  </ul>
                  <div class="help-note">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 8h.01"/><path d="M11 12h1v4h1"/></svg>
                    <span>Você pode consultar a cartilha completa em <a href="direitos.php">Critérios e Direitos</a>.</span>
                  </div>
                </div>
              </div>
            </div>
          </article>

          <!-- 4 -->
          <article class="help-faq-item" data-cat="via"
                   data-keywords="pontos apoio locais estabelecimentos sanitários adaptados vagas pcd comércios">
            <h3 class="help-faq-q">
              <button type="button" class="help-faq-toggle" aria-expanded="false" aria-controls="faq-a4" id="faq-q4">
                <span class="help-faq-num">4</span>
                <span class="help-faq-text">Como encontrar pontos de apoio e serviços com acessibilidade certificada?</span>
                <svg class="help-faq-chevron" viewBox="0 0 24 24" aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg>
              </button>
            </h3>
            <div class="help-faq-a" id="faq-a4" role="region" aria-labelledby="faq-q4">
              <div class="help-faq-a-inner">
                <div class="help-faq-a-content">
                  <p>A plataforma georreferencia locais públicos e comerciais em Suzano com infraestrutura inclusiva:</p>
                  <ul class="help-check-list">
                    <li><b>Filtros Específicos:</b> Filtre por banheiros adaptados, elevadores com áudio, vagas PcD demarcadas e entradas niveladas.</li>
                    <li><b>Detalhes e Fotos:</b> Consulte evidências fotográficas e avaliações feitas por pessoas com deficiência da comunidade.</li>
                    <li><b>Traçado Direto:</b> Clique em "Ativar Rota" para iniciar navegação orientada até a entrada acessível do local.</li>
                  </ul>
                  <div class="help-note">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 8h.01"/><path d="M11 12h1v4h1"/></svg>
                    <span>Confira todos os locais mapeados na página de <a href="lugares-encontrados.php">Lugares Encontrados</a>.</span>
                  </div>
                </div>
              </div>
            </div>
          </article>

          <!-- 5 -->
          <article class="help-faq-item" data-cat="beneficio"
                   data-keywords="senai suzano técnicos auditoria vistorias medições trena estudantes campo">
            <h3 class="help-faq-q">
              <button type="button" class="help-faq-toggle" aria-expanded="false" aria-controls="faq-a5" id="faq-q5">
                <span class="help-faq-num">5</span>
                <span class="help-faq-text">Qual é o papel da equipe do SENAI Suzano na validação técnica?</span>
                <svg class="help-faq-chevron" viewBox="0 0 24 24" aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg>
              </button>
            </h3>
            <div class="help-faq-a" id="faq-a5" role="region" aria-labelledby="faq-q5">
              <div class="help-faq-a-inner">
                <div class="help-faq-a-content">
                  <p>O Passe Livre nasceu como projeto prático desenvolvido pelos estudantes do SENAI Suzano:</p>
                  <ul class="help-check-list">
                    <li><b>Vistorias de Campo:</b> Medição in loco de largura de calçadas, ângulos de rampas e funcionamento de elevadores urbanos.</li>
                    <li><b>Aplicação da NBR 9050:</b> Rigor técnico na classificação entre alta acessibilidade, parcial e baixa acessibilidade.</li>
                    <li><b>Parceria com a Cidade:</b> Cruzamento de dados de fiscalização com o Plano Diretor de Suzano.</li>
                  </ul>
                  <div class="help-note">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 8h.01"/><path d="M11 12h1v4h1"/></svg>
                    <span>Conheça os estudantes e o projeto na seção <a href="index.php#equipe">Comunidade</a>.</span>
                  </div>
                </div>
              </div>
            </div>
          </article>

          <!-- 6 -->
          <article class="help-faq-item" data-cat="uso"
                   data-keywords="reportar barreira obstáculo fiscalização calçada buraco semáforo defeito foto">
            <h3 class="help-faq-q">
              <button type="button" class="help-faq-toggle" aria-expanded="false" aria-controls="faq-a6" id="faq-q6">
                <span class="help-faq-num">6</span>
                <span class="help-faq-text">Como relatar calçadas quebradas, falta de rampa ou semáforo com defeito?</span>
                <svg class="help-faq-chevron" viewBox="0 0 24 24" aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg>
              </button>
            </h3>
            <div class="help-faq-a" id="faq-a6" role="region" aria-labelledby="faq-q6">
              <div class="help-faq-a-inner">
                <div class="help-faq-a-content">
                  <p>Qualquer cidadão pode contribuir para a mobilidade de Suzano de forma simples e rápida:</p>
                  <ul class="help-check-list">
                    <li><b>Reporte Rápido:</b> Clique no botão "Reportar Barreira" no mapa ou na navegação ativa.</li>
                    <li><b>Fotografia &amp; Endereço:</b> Anexe uma foto do local e selecione o tipo de obstáculo (calçada, guia, semáforo, obra).</li>
                    <li><b>Triagem e Atualização:</b> O relato entra para o mapa colaborativo alertando outros munícipes imediatamente.</li>
                  </ul>
                  <div class="help-note">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 8h.01"/><path d="M11 12h1v4h1"/></svg>
                    <span>Você pode simular o envio na tela <a href="alerta-obstaculo.php">Alerta de Obstáculo</a>.</span>
                  </div>
                </div>
              </div>
            </div>
          </article>
        </div>

        <!-- Estado vazio da busca -->
        <div class="help-empty" id="help-empty" hidden>
          <h3>Nenhum resultado encontrado</h3>
          <p>Não achamos respostas para <b id="help-empty-term"></b>. Tente outras palavras ou fale com nossa equipe.</p>
          <a href="#suporte" class="help-empty-link">Ver canais de atendimento &rarr;</a>
        </div>
      </div>
    </section>

    <!-- =========================================================================
         5. AINDA PRECISA DE SUPORTE?
         ========================================================================= -->
    <section class="help-support" id="suporte" aria-labelledby="heading-support">
      <div class="help-container help-support-inner">
        <h2 class="section-main-heading" id="heading-support">Ainda precisa de suporte?</h2>
        <p class="section-sub-heading help-support-sub">
          Nossa equipe especializada de acolhimento acadêmico está pronta para atender você nos canais oficiais.
        </p>

        <div class="help-support-grid">
          <div class="help-support-card">
            <span class="need-card-icon-wrap">
              <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/><path d="M8 9h8"/><path d="M8 13h5"/></svg>
            </span>
            <h3 class="help-support-title">Atendimento via Chat / WhatsApp</h3>
            <p class="help-support-desc">Respostas e triagem em tempo real com assistente virtual e atendentes humanos em dias úteis das 08h às 18h.</p>
            <span class="help-support-badge"><i class="help-dot"></i> Tempo médio de resposta: 4 min</span>
            <button type="button" class="help-support-btn" id="btn-chat">Iniciar Conversa</button>
          </div>

          <div class="help-support-card">
            <span class="need-card-icon-wrap">
              <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><polyline points="3 7 12 13 21 7"/></svg>
            </span>
            <h3 class="help-support-title">E-mail de Suporte</h3>
            <p class="help-support-desc">Envie sua solicitação detalhada com anexos de documentos para análise aprofundada pela equipe de triagem.</p>
            <span class="help-support-badge">
              <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><polyline points="12 7 12 12 15 14"/></svg>
              Resposta em até 24 horas úteis
            </span>
            <a href="mailto:contato@passelivre.org.br?subject=Suporte%20Passe%20Livre" class="help-support-btn" id="btn-email">Enviar E-mail</a>
          </div>

          <div class="help-support-card">
            <span class="need-card-icon-wrap">
              <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 1.9.7 2.8a2 2 0 0 1-.5 2.1L8.1 9.9a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.8.7a2 2 0 0 1 1.7 2z"/></svg>
            </span>
            <h3 class="help-support-title">Telefone &amp; Ouvidoria</h3>
            <p class="help-support-desc">Central telefônica gratuita para emergências, bloqueios imediatos de cartões extraviados e manifestações.</p>
            <span class="help-support-badge">
              <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 18v-6a9 9 0 0 1 18 0v6"/><path d="M21 19a2 2 0 0 1-2 2h-1v-7h3z"/><path d="M3 19a2 2 0 0 0 2 2h1v-7H3z"/></svg>
              Central 0800 728 0000
            </span>
            <button type="button" class="help-support-btn" id="btn-phone">Ver Telefones</button>
          </div>
        </div>
      </div>
    </section>

    <!-- =========================================================================
         6. STATUS DO SISTEMA
         ========================================================================= -->
    <section class="help-status-wrap" aria-label="Status dos sistemas">
      <div class="help-status">
        <span class="help-status-icon">
          <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="m8.5 12.5 2.5 2.5 4.5-5"/></svg>
        </span>
        <div class="help-status-text">
          <strong>Todos os sistemas operando normalmente <i class="help-dot"></i></strong>
          <span>Validação de cadastros, rotas com NBR 9050 e canais de atendimento disponíveis sem interrupções.</span>
        </div>
        <div class="help-status-badges">
          <span>
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2 4 5v6c0 5 3.4 9.3 8 11 4.6-1.7 8-6 8-11V5z"/><path d="m9 12 2 2 4-4"/></svg>
            Ambiente Seguro SSL 256-bit
          </span>
          <span>
            <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
            Conformidade LGPD
          </span>
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
      <a href="ajuda.php#suporte" class="footer-link-item">Suporte</a>
    </div>
  </footer>

  <!-- Toast de Feedback -->
  <div class="toast-notice" id="app-toast">
    <img src="assets/icons/check.svg" width="18" height="18" alt="">
    <span id="toast-message">Mensagem</span>
  </div>

    <script src="js/db.js"></script>
  <script src="js/db-queries.js"></script>
  <script src="js/auth.js"></script>
  <script src="js/ajuda.js"></script>
</body>
</html>
