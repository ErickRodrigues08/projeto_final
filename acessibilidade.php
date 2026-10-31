<?php
/**
 * Passe Livre Suzano — Declaração de Acessibilidade e Recursos Assistivos
 * Conformidade com as Diretrizes WCAG 2.1 Nível AA e norma ABNT NBR 9050.
 */
require_once __DIR__ . '/api/_db_restore.php';
$authUser = get_auth_user();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Acessibilidade do Sistema — Passe Livre Suzano</title>
  <meta name="description" content="Recursos de acessibilidade digital, atalhos de teclado, compatibilidade com leitores de tela e compromisso com o padrão WCAG 2.1 AA no Passe Livre Suzano.">
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
      <span class="crumb-current">Acessibilidade Digital</span>
    </nav>

    <div class="direitos-hero-header">
      <div class="direitos-hero-text">
        <div class="pedagogico-pill">
          <span class="pedagogico-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor">
              <path d="M12 2c1.1 0 2 .9 2 2s-.9 2-2 2-2-.9-2-2 .9-2 2-2zm9 7h-6v13h-2v-6h-2v6H9V9H3V7h18v2z"/>
            </svg>
          </span>
          <span>ACESSIBILIDADE UNIVERSAL • WCAG 2.1 AA</span>
        </div>
        <h1 class="direitos-main-title">Declaração e Recursos de Acessibilidade</h1>
        <p class="direitos-main-subtitle">
          Construído para todas as pessoas: navegação por teclado, compatibilidade com leitores de tela e design inclusivo.
        </p>
      </div>
    </div>

    <article class="legis-card" style="background:#fff;border-radius:16px;padding:36px;border:1px solid #e7e2d8;margin-bottom:32px;box-shadow:0 2px 8px rgba(0,0,0,.04);">
      <h2 style="font-size:20px;color:#1c1917;margin-bottom:16px;">1. Compromisso com a Inclusão Digital</h2>
      <p style="color:#57534e;line-height:1.7;margin-bottom:20px;">
        O projeto <strong>Passe Livre Suzano</strong> segue como princípio fundacional a acessibilidade universal. Nosso objetivo é garantir que todas as pessoas, independentemente de suas capacidades visuais, motoras, auditivas ou cognitivas, possam consultar rotas, reportar barreiras e exercer sua plena cidadania no município.
      </p>

      <h2 style="font-size:20px;color:#1c1917;margin-bottom:16px;">2. Recursos de Navegação por Teclado</h2>
      <p style="color:#57534e;line-height:1.7;margin-bottom:12px;">
        Toda a interface pode ser operada sem o auxílio de mouse:
      </p>
      <ul style="color:#57534e;line-height:1.8;margin-bottom:20px;padding-left:24px;">
        <li><kbd style="background:#f5f0eb;padding:2px 8px;border-radius:4px;border:1px solid #d6cfc7;font-family:monospace;">Tab</kbd>: Avança o foco para o próximo elemento interativo.</li>
        <li><kbd style="background:#f5f0eb;padding:2px 8px;border-radius:4px;border:1px solid #d6cfc7;font-family:monospace;">Shift + Tab</kbd>: Retorna o foco ao elemento anterior.</li>
        <li><kbd style="background:#f5f0eb;padding:2px 8px;border-radius:4px;border:1px solid #d6cfc7;font-family:monospace;">Enter</kbd> ou <kbd style="background:#f5f0eb;padding:2px 8px;border-radius:4px;border:1px solid #d6cfc7;font-family:monospace;">Espaço</kbd>: Ativa botões, links e caixas de seleção.</li>
        <li><kbd style="background:#f5f0eb;padding:2px 8px;border-radius:4px;border:1px solid #d6cfc7;font-family:monospace;">Esc</kbd>: Fecha janelas modais, gavetas de menu e alertas flutuantes, retornando o foco ao disparador.</li>
      </ul>

      <h2 style="font-size:20px;color:#1c1917;margin-bottom:16px;">3. Leitores de Tela e Semântica W3C</h2>
      <p style="color:#57534e;line-height:1.7;margin-bottom:20px;">
        A aplicação foi estruturada com elementos semânticos HTML5 (<code>header</code>, <code>nav</code>, <code>main</code>, <code>aside</code>, <code>footer</code>, <code>article</code>) e atributos ARIA (<code>aria-live="polite"</code> para notificações em tempo real, <code>aria-label</code> descritivos para botões de ícone e <code>role="alert"</code> para desvios viários). Testado e homologado para uso com <strong>NVDA</strong> (Windows), <strong>TalkBack</strong> (Android) e <strong>VoiceOver</strong> (iOS/macOS).
      </p>

      <h2 style="font-size:20px;color:#1c1917;margin-bottom:16px;">4. Contraste Visual e Escala de Tipografia</h2>
      <p style="color:#57534e;line-height:1.7;margin-bottom:20px;">
        As relações de contraste de cor atendem ou superam a proporção mínima de <strong>4.5:1</strong> para textos normais e <strong>3:1</strong> para elementos gráficos conforme WCAG 2.1 AA. A interface suporta ampliação de zoom de até <strong>400%</strong> sem perda de legibilidade ou quebra de fluxo de informações.
      </p>

      <h2 style="font-size:20px;color:#1c1917;margin-bottom:16px;">5. Reporte de Barreiras Digitais</h2>
      <p style="color:#57534e;line-height:1.7;margin-bottom:20px;">
        Se você encontrar qualquer barreira de acessibilidade digital nesta plataforma, por favor relate à nossa equipe através do <a href="fale-conosco.php" style="color:#b45309;font-weight:700;">Fale Conosco</a>. Tratamos melhorias de acessibilidade com prioridade máxima.
      </p>

      <div style="margin-top:32px;padding-top:20px;border-top:1px solid #f5f0eb;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
        <span style="font-size:12px;color:#78716c;">Homologado pelo SENAI Suzano</span>
        <div style="display:flex;gap:12px;">
          <a href="direitos.php" class="btn-primary" style="padding:8px 16px;font-size:12px;text-decoration:none;border-radius:6px;">Guia de Direitos PcD</a>
          <a href="adaptar-busca.php" class="nav-link" style="font-size:12px;font-weight:700;color:#b45309;">Configurar Preferências</a>
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
      <a href="privacidade.php" class="footer-link-item">Privacidade (LGPD)</a>
      <a href="acessibilidade.php" class="footer-link-item active">Acessibilidade</a>
      <a href="fale-conosco.php" class="footer-link-item">Fale Conosco</a>
    </div>
  </footer>
</body>
</html>
