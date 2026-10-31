<?php
/**
 * Passe Livre Suzano — Página de Cadastro
 * Redireciona usuário já autenticado para sua área.
 */
require_once __DIR__ . '/includes/auth_guard.php';
auth_redirect_if_logged();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Crie sua conta — Passe Livre | Rotas e locais acessíveis</title>
  <meta name="description" content="Cadastre-se no Passe Livre Suzano para descobrir rotas acessíveis, locais adaptados e transporte público inclusivo.">
  <link rel="stylesheet" href="assets/fonts/fonts.css">
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="css/cadastro.css?v=5.5">
  <link rel="stylesheet" href="css/auth-polish.css?v=5.5">
  <link rel="icon" type="image/x-icon" href="favicon.ico">
  <link rel="icon" type="image/png" sizes="32x32" href="assets/icons/favicon-32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="assets/icons/favicon-16.png">
  <link rel="apple-touch-icon" href="assets/icons/apple-touch-icon.png">
</head>
<body class="cadastro-page">
  <a href="#form-cadastro" class="skip-link">Pular para o formulário de cadastro</a>

  <main id="main-content" class="cadastro-main-container">
    <div class="cadastro-illustration-column">
      <aside class="auth-panel auth-panel--photo" aria-label="Apresentação do Passe Livre">
        <span class="auth-panel-kicker">Passe Livre Suzano · Cidade inclusiva</span>
        <p class="auth-panel-title">Cada cadastro <em>abre um novo caminho.</em></p>
        <p class="auth-panel-text">Passe Livre — Conectando pessoas, construindo acessibilidade.</p>
      </aside>
    </div>

    <div class="cadastro-card-column">
      <div class="cadastro-form-card">
        <a href="index.php" class="auth-card-brand" title="Voltar para a página inicial">
          <img src="assets/icons/logo.png" alt="Passe Livre" onerror="this.src='assets/icons/logo.svg'">
        </a>
        <div class="cadastro-title-wrap">
          <h1 class="cadastro-card-title">Crie sua conta</h1>
          <p class="cadastro-card-subtitle">Rotas e locais acessíveis pra você</p>
        </div>

        <form id="form-cadastro" novalidate>
          <div class="form-field-item">
            <label for="cad-nome" class="form-label-visible">Nome completo</label>
            <input type="text" id="cad-nome" class="form-input-cadastro" placeholder="Ex: Maria da Silva" required autocomplete="name">
          </div>

          <div class="form-field-item">
            <label for="cad-email" class="form-label-visible">E-mail</label>
            <input type="email" id="cad-email" class="form-input-cadastro" placeholder="seuemail@exemplo.com" required autocomplete="email">
          </div>

          <div class="form-field-item">
            <label for="cad-telefone" class="form-label-visible">Telefone / WhatsApp <span style="font-weight:400;color:#78716c;">(opcional)</span></label>
            <input type="tel" id="cad-telefone" class="form-input-cadastro" placeholder="(11) 99999-9999" autocomplete="tel" maxlength="15">
          </div>

          <div class="form-fields-row-2col">
            <div class="password-input-wrap">
              <label for="cad-senha" class="sr-only">Senha</label>
              <input type="password" id="cad-senha" class="form-input-cadastro" placeholder="Senha (mín. 6 dígitos)" required autocomplete="new-password">
              <button type="button" class="btn-toggle-eye" data-target="cad-senha" title="Alternar visibilidade da senha" aria-label="Alternar visibilidade da senha">
                <img src="assets/icons/eye.svg" alt="Visualizar senha" aria-hidden="true">
              </button>
            </div>
            <div class="password-input-wrap">
              <label for="cad-confirm-senha" class="sr-only">Confirmar senha</label>
              <input type="password" id="cad-confirm-senha" class="form-input-cadastro" placeholder="Confirmar senha" required autocomplete="new-password">
              <button type="button" class="btn-toggle-eye" data-target="cad-confirm-senha" title="Alternar visibilidade da senha" aria-label="Alternar visibilidade da senha">
                <img src="assets/icons/eye.svg" alt="Visualizar senha" aria-hidden="true">
              </button>
            </div>
          </div>

          <div class="form-field-item">
            <label for="cad-estado" class="form-label-visible">Estado</label>
            <select id="cad-estado" name="estado" class="form-input-cadastro" required>
              <option value="">Selecione o estado</option>
              <option value="AC">AC — Acre</option>
              <option value="AL">AL — Alagoas</option>
              <option value="AP">AP — Amapá</option>
              <option value="AM">AM — Amazonas</option>
              <option value="BA">BA — Bahia</option>
              <option value="CE">CE — Ceará</option>
              <option value="DF">DF — Distrito Federal</option>
              <option value="ES">ES — Espírito Santo</option>
              <option value="GO">GO — Goiás</option>
              <option value="MA">MA — Maranhão</option>
              <option value="MT">MT — Mato Grosso</option>
              <option value="MS">MS — Mato Grosso do Sul</option>
              <option value="MG">MG — Minas Gerais</option>
              <option value="PA">PA — Pará</option>
              <option value="PB">PB — Paraíba</option>
              <option value="PR">PR — Paraná</option>
              <option value="PE">PE — Pernambuco</option>
              <option value="PI">PI — Piauí</option>
              <option value="RJ">RJ — Rio de Janeiro</option>
              <option value="RN">RN — Rio Grande do Norte</option>
              <option value="RS">RS — Rio Grande do Sul</option>
              <option value="RO">RO — Rondônia</option>
              <option value="RR">RR — Roraima</option>
              <option value="SC">SC — Santa Catarina</option>
              <option value="SP" selected>SP — São Paulo</option>
              <option value="SE">SE — Sergipe</option>
              <option value="TO">TO — Tocantins</option>
            </select>
          </div>

          <div class="form-field-item">
            <label for="cad-municipio" class="form-label-visible">Município</label>
            <select id="cad-municipio" name="municipio" class="form-input-cadastro" disabled required>
              <option value="">Selecione um estado primeiro</option>
            </select>
            <!-- Campo oculto compatível com o restante do sistema (mantém cad-cidade funcional) -->
            <input type="hidden" id="cad-cidade" name="cidade">
          </div>

          <div class="pcd-selection-section">
            <div class="pcd-question-title">Você é PCD ou tem mobilidade reduzida?</div>
            <div class="pcd-question-desc">Opcional — ajuda a sugerir melhores rotas</div>
            <div class="pcd-options-grid" role="group" aria-label="Opções de acessibilidade">
              <button type="button" class="pcd-option-btn" data-category="Física/motora" aria-pressed="false">Física/motora</button>
              <button type="button" class="pcd-option-btn" data-category="Visual" aria-pressed="false">Visual</button>
              <button type="button" class="pcd-option-btn" data-category="Auditiva" aria-pressed="false">Auditiva</button>
              <button type="button" class="pcd-option-btn" data-category="Intelectual/TEA" aria-pressed="false">Intelectual/TEA</button>
              <button type="button" class="pcd-option-btn" data-category="Mobilidade Reduzida" aria-pressed="false">Mob. reduzida</button>
              <button type="button" class="pcd-option-btn" data-category="Outra" aria-pressed="false">Outra</button>
            </div>
          </div>

          <label class="terms-checkbox-wrap" for="cad-termos">
            <input type="checkbox" id="cad-termos" class="custom-checkbox-input" required>
            <span>Li e aceito os <a href="termos-de-uso.php" class="pl-termos-link" data-termos-section="termos" aria-haspopup="dialog">termos de uso</a> e a <a href="privacidade.php" class="pl-termos-link" data-termos-section="privacidade" aria-haspopup="dialog">política de privacidade</a></span>
          </label>

          <button type="submit" class="btn-submit-cadastro">Criar minha conta</button>

          <div class="cadastro-card-footer">
            <span>Já tem uma conta?</span>
            <a href="login.php" class="cadastro-login-link">Entrar</a>
          </div>
        </form>
      </div>
    </div>
  </main>

  <!-- =========================================================================
       MODAL: TERMOS DE USO E POLÍTICA DE PRIVACIDADE
       Conteúdo carregado de termos-de-uso.php e privacidade.php (sem duplicar).
       ========================================================================= -->
  <div class="pl-termos-modal" id="pl-termos-modal" role="dialog" aria-modal="true" aria-labelledby="pl-termos-modal-title" aria-hidden="true">
    <div class="pl-termos-overlay" data-pl-termos-close></div>

    <div class="pl-termos-card" role="document">
      <header class="pl-termos-header">
        <div>
          <p class="pl-termos-eyebrow">Passe Livre Suzano</p>
          <h2 class="pl-termos-title" id="pl-termos-modal-title">Termos de Uso e Política de Privacidade</h2>
        </div>
        <button type="button" class="pl-termos-close" id="pl-termos-close" aria-label="Fechar termos de uso e política de privacidade">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <line x1="18" y1="6" x2="6" y2="18"></line>
            <line x1="6" y1="6" x2="18" y2="18"></line>
          </svg>
        </button>
      </header>

      <div class="pl-termos-body" id="pl-termos-body" tabindex="0" aria-label="Conteúdo dos termos de uso e da política de privacidade">
        <p class="pl-termos-loading" id="pl-termos-loading" role="status">Carregando termos...</p>

        <section class="pl-termos-section" id="pl-termos-section-termos" aria-labelledby="pl-termos-heading-termos" hidden>
          <h3 class="pl-termos-section-title" id="pl-termos-heading-termos">Termos de Uso</h3>
          <div class="pl-termos-doc" id="pl-termos-doc-termos"></div>
        </section>

        <section class="pl-termos-section" id="pl-termos-section-privacidade" aria-labelledby="pl-termos-heading-privacidade" hidden>
          <h3 class="pl-termos-section-title" id="pl-termos-heading-privacidade">Política de Privacidade</h3>
          <div class="pl-termos-doc" id="pl-termos-doc-privacidade"></div>
        </section>
      </div>

      <footer class="pl-termos-footer">
        <span class="pl-termos-footer-note">Documentos oficiais do Passe Livre Suzano</span>
        <button type="button" class="pl-termos-ok" id="pl-termos-ok">Entendi</button>
      </footer>
    </div>
  </div>

  <div class="toast-notice" id="app-toast">
    <img src="assets/icons/check.svg" width="18" height="18" alt="">
    <span id="toast-message">Operação realizada com sucesso!</span>
  </div>

  <script src="js/db.js" defer></script>
  <script src="js/db-queries.js" defer></script>
  <script src="js/mock-data.js" defer></script>
  <script src="js/auth.js"></script>
  <script src="js/cadastro.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/gsap@3.12.5/dist/gsap.min.js" defer></script>
  <script src="https://cdn.jsdelivr.net/npm/gsap@3.12.5/dist/ScrollTrigger.min.js" defer></script>
  <script src="js/entrada-animacoes.js" defer></script>
</body>
</html>
