# Passe Livre Suzano 🚍♿

Plataforma Web para Mobilidade Acessível e Rotas Inclusivas do município de Suzano - SP.

---

## 🛠️ Tecnologias Utilizadas

- **Backend:** PHP 8.2+ com PDO (MySQL/MariaDB)
- **Banco de Dados:** MySQL 8.0+ / MariaDB 10.4+
- **Frontend:** HTML5 Semântico, CSS3 Moderno, JavaScript Vanilla (ES6+)
- **Mapas:** Leaflet.js com tiles OpenStreetMap / CartoDB

---

## 🚀 Como Executar o Projeto Localmente

### 1. Pré-requisitos
- PHP 8.2 ou superior instalado (ex.: XAMPP ou PHP CLI)
- Servidor MySQL ou MariaDB ativo na porta 3306

### 2. Importação do Banco de Dados

1. Certifique-se de que o serviço MySQL está em execução.
2. Crie o banco de dados `passe_livre_suzano` e importe os scripts SQL na seguinte ordem:

```bash
# 1. Criação do banco e estrutura principal (com dados de carga inicial)
mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS passe_livre_suzano CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p passe_livre_suzano < banco_mysql.sql

# 2. Atualizações adicionais de esquema e índices
mysql -u root -p passe_livre_suzano < update_mysql.sql
```

*(No XAMPP phpMyAdmin, basta criar a base `passe_livre_suzano` e importar primeiro `banco_mysql.sql` e em seguida `update_mysql.sql`).*

### Fotos de perfil armazenadas no banco

Para atualizar uma instalação existente, faça backup do banco e de `uploads/`. **Não reimporte `banco_mysql.sql`: ele apaga tabelas.** Execute somente a migração incremental, selecionando explicitamente o banco correto:

```bash
mysql -h 127.0.0.1 -u root -p passe_livre_suzano < migracao_avatar_binario.sql
php scripts/migrar_avatares.php
```

No XAMPP, use `C:/xampp/mysql/bin/mysql.exe` e `C:/xampp/php/php.exe`. O importador CLI usa a conexão do `.env` ou as variáveis de ambiente do processo. É idempotente, valida cada imagem antiga em `uploads/`, trava a conta durante a gravação e preserva o caminho anterior se houver erro ou alteração concorrente. Não apaga arquivos. Fotos padrão e imagens externas continuam compatíveis; fotos novas e uploads antigos migrados são recuperados do banco, sem depender de `uploads/`.

`usuarios_avatares` contém uma `MEDIUMBLOB`, MIME validado, tamanho e SHA-256, com uma linha por usuário e chave estrangeira para `usuarios`. `usuarios.avatar` permanece textual e referencia `api/avatar.php?ref=<id>-<sha256>`; nenhum binário/Base64 é incluído no JSON ou no armazenamento do navegador. A leitura exige sessão válida e permite somente a própria conta ou gestores já autorizados. A resposta usa `Cache-Control: private, no-store`; a versão muda quando o conteúdo muda.

O MariaDB/MySQL precisa de `max_allowed_packet` de pelo menos **16 MB** para transportar os arquivos de até 5 MB. No XAMPP, ajuste `max_allowed_packet=16M` na seção `[mysqld]` de `C:/xampp/mysql/bin/my.ini` para persistir após reiniciar. Um administrador pode aplicar `SET GLOBAL max_allowed_packet = 16777216` para as novas conexões sem reiniciar. Não altere instâncias compartilhadas sem autorização. Configure também PHP com `upload_max_filesize=5M` e `post_max_size=8M`.

Ao confirmar o enquadramento, o canvas exporta o recorte final, o backend valida os bytes e grava foto + referência na mesma transação. Apenas após o commit os avatares e o cache de dados da conta são atualizados. Erros preservam a foto anterior e o recorte para repetir o envio. A remoção exclui a BLOB e restaura o avatar padrão em transação, sem apagar arquivos antigos nem alterar os demais dados do perfil.

#### Verificação isolada

A suíte `scripts/test_avatar.php` exige um banco descartável cujo nome comece com `pl_avatar_test_`, a mesma estrutura da instalação e um servidor PHP apontado para esse banco. Ela cria e limpa suas próprias contas, sessões, imagens e falhas controladas. Nunca execute as suítes antigas diretamente no banco compartilhado.

```bash
# Após criar e copiar somente o schema para pl_avatar_test_SEU_ID:
mysql -h 127.0.0.1 -u root -p pl_avatar_test_SEU_ID < migracao_avatar_binario.sql
DB_NAME=pl_avatar_test_SEU_ID MAIL_HOST= MAIL_INTERNAL_SECRET= php -d upload_max_filesize=5M -d post_max_size=8M -S 127.0.0.1:8917 -t .
# Em outro terminal, usando o mesmo banco:
DB_NAME=pl_avatar_test_SEU_ID MAIL_HOST= MAIL_INTERNAL_SECRET= php scripts/test_avatar.php http://127.0.0.1:8917
```

No navegador, valide também seleção, zoom, arraste, rotação, confirmação sem salvar o formulário, igualdade entre os dois avatares, cancelamento, tentativa repetida após falha, bloqueio de duplicatas, recarga, logout/login, nova sessão e remoção. A foto anterior deve permanecer enquanto a confirmação do backend não chegar.

---

### 3. Configuração de Variáveis de Ambiente

Copie o arquivo de exemplo para criar seu `.env` local:

```bash
cp .env.example .env
```

Ou configure as seguintes variáveis no seu ambiente / virtualhost:

| Variável | Padrão | Descrição |
| :--- | :--- | :--- |
| `APP_ENV` | `production` | Ambiente da aplicação (`development` ou `production`) |
| `DB_HOST` | `127.0.0.1` | Host do banco de dados MySQL |
| `DB_PORT` | `3306` | Porta de conexão do MySQL |
| `DB_NAME` | `passe_livre_suzano` | Nome do banco de dados |
| `DB_USER` | `root` | Usuário do MySQL |
| `DB_PASS` | *(vazio)* | Senha do usuário do MySQL |

#### Variáveis das APIs externas (proxies em `api/`)

As chaves **nunca** aparecem no HTML ou nos arquivos de `js/`: o navegador chama apenas os endpoints PHP do projeto, que leem o ambiente com `pl_get_env()` em `api/db.php`. **Todas são opcionais** — com o valor vazio, cada página continua usando o comportamento estático atual (fallback silencioso).

| Variável | Padrão | Onde obter |
| :--- | :--- | :--- |
| `GEOCODER_PROVIDER` | `nominatim` | Escolha `nominatim` (gratuito, sem chave) ou `geoapify`. |
| `GEOAPIFY_KEY` | *(vazio)* | [geoapify.com/get-an-api-key](https://www.geoapify.com/get-an-api-key/) — exigida apenas se `GEOCODER_PROVIDER=geoapify`. |
| `ORS_API_KEY` | *(vazio)* | [openrouteservice.org/dev/#/api-key](https://openrouteservice.org/dev/#/api-key) — habilita rotas reais a pé / cadeira de rodas. |
| `OVERPASS_URL` | `https://overpass-api.de/api/interpreter` | Qualquer instância Overpass; aponte para uma própria em produção. |
| `MAIL_HOST` | *(vazio)* | Servidor SMTP (ex.: `smtp.gmail.com`). Vazio = nenhum e-mail é enviado. |
| `MAIL_PORT` | `587` | Porta SMTP (587 com TLS, 465 com SSL). |
| `MAIL_USER` | *(vazio)* | Usuário/login do SMTP. |
| `MAIL_PASS` | *(vazio)* | Senha ou *app password* do SMTP. |
| `MAIL_FROM` | *(vazio)* | Remetente exibido nos e-mails de verificação/recuperação. |
| `MAIL_CA_FILE` | *(vazio)* | Caminho opcional para um arquivo PEM com a CA confiável do SMTP, caso ela não esteja no bundle de certificados do PHP/XAMPP. A validação TLS permanece habilitada. |

> O cache das respostas externas fica em `api/cache/` (bloqueado por `.htaccess` e ignorado pelo git). Basta criar a pasta com permissão de escrita para o PHP; ela é criada automaticamente no primeiro uso.

| Endpoint | Uso | Sem configuração |
| :--- | :--- | :--- |
| `api/geocode.php` | Busca de endereço (Nominatim/Geoapify) restrita a Suzano e região. | Autocomplete usa apenas a lista estática. |
| `api/rota.php` | Traçado, distância, tempo e passos a pé / cadeira de rodas (OpenRouteService). | Mapa mantém o traçado cartográfico pré-auditado. |
| `api/osm_pontos.php` | Camada opcional de pontos de acessibilidade do OpenStreetMap (Overpass), com cache de 24 h e rótulo "fonte: OpenStreetMap". | O mapa segue só com os locais cadastrados. |
| `api/upload.php` | Perfil: grava os bytes do recorte no banco. Avaliações: conserva upload em arquivo. PNG/JPEG/WebP até 5 MB, validados com `finfo` e `getimagesize`. | Exige sessão, banco e migração de avatar aplicada. |

Os três proxies de API externa (`geocode.php`, `rota.php`, `osm_pontos.php`) exigem sessão válida — sem login respondem `401` em JSON e o front-end cai no conteúdo estático — e aplicam limite de **60 chamadas por minuto por usuário/IP** (`429` quando excedido). `api/http.php` (cliente cURL, cache e limite) e `api/mailer.php` (SMTP) são módulos internos chamados pelos endpoints acima e por `api/auth.php`; não são rotas públicas.

Os marcadores de `mapa-geral.php` e os números do card/laudo do terminal em `mapa-gestor.php` têm como fonte primária a tabela `locais` via `api/locais.php`; o conteúdo estático impresso na página continua sendo o fallback quando o banco ou o endpoint falha.

---

### 4. Modo de Demonstração / Verificação (`APP_ENV=development`)

O envio real de e-mail já está implementado: `api/mailer.php` fala SMTP diretamente (sem Composer/dependências), usando `MAIL_HOST`, `MAIL_PORT`, `MAIL_USER`, `MAIL_PASS` e `MAIL_FROM` — STARTTLS na porta 587, SSL na 465. O certificado do servidor é sempre validado; se a CA não estiver no bundle do PHP/XAMPP, configure `MAIL_CA_FILE` com o caminho de um PEM confiável. Falhas SMTP são registradas no log do PHP, e o fluxo da aplicação continua normal. **Com `MAIL_HOST` vazio nenhum e-mail é enviado**, e para testar sem SMTP use o modo de demonstração:

1. Defina `APP_ENV=development` no arquivo `.env` ou no terminal:
   - **PowerShell:** `$env:APP_ENV="development"`
   - **Bash:** `export APP_ENV=development`
2. **Efeitos do Modo Demo** (só existem em `development`; em `production` o `demo_code` nunca é retornado):
   - Em `verificar-codigo.php`: um banner interativo (pill) exibirá o código OTP de 6 dígitos gerado recentemente para o e-mail informado. Clicar no banner preenche automaticamente todos os campos de código.
   - Em `redefinir-senha.php`: a interface exibe o código de validação e permite testar a gravação da nova senha.
   - Nas respostas das APIs `api/auth.php` para as ações `register`, `resend_code` e `forgot_password`, o campo `demo_code` é retornado no JSON com o código OTP gerado.

---

### 5. Executando o Servidor Embutido do PHP

Na pasta raiz do projeto, execute:

```bash
# Executar no terminal (Windows/Linux/Mac)
php -S localhost:8000
```

Se o PHP não estiver no PATH global (ex.: no XAMPP Windows):

```powershell
c:\xampp\php\php.exe -S localhost:8000
```

Acesse no navegador:
👉 **http://localhost:8000**

---

## 👥 Credenciais Pré-configuradas para Testes

O banco inicial contém usuários de teste prontos para uso:

| Perfil | E-mail | Senha | Destino após Login |
| :--- | :--- | :--- | :--- |
| **Gestor / Administrador** | `admin@suzano.sp.gov.br` | `Admin@123` | `dashboard.php` |
| **Cidadão PCD** | `maria.silva@exemplo.com` | `Cidadao@123` | `adaptar-busca.php` |
| **Cidadão Comum** | `joao.santos@exemplo.com` | `Cidadao@123` | `adaptar-busca.php` |

---

## 📁 Estrutura das Páginas

- **Públicas / Acesso:** `index.php`, `login.php`, `cadastro.php`, `esqueci-senha.php`, `verificar-codigo.php`, `redefinir-senha.php`, `direitos.php`, `ajuda.php`, `fale-conosco.php`, `termos-de-uso.php`, `privacidade.php`.
- **Área do Cidadão:** `adaptar-busca.php`, `lugares-encontrados.php`, `detalhes-local.php`, `avaliar-local.php`, `configuracao-rota.php`, `navegacao.php`, `explorar.php`, `mapa-geral.php`, `alerta-obstaculo.php`, `editar-perfil.php`, `notificacoes.php`.
- **Painel de Gestão (Admin):** `dashboard.php`, `mapa-gestor.php`, `solicitacoes.php`, `solicitacao-detalhe.php`, `relatorios.php`, `auditoria.php`, `locais-cadastrados.php`.
- **APIs REST:** `api/auditoria.php`, `api/auth.php`, `api/avaliacoes.php`, `api/configuracoes.php`, `api/contato.php`, `api/dashboard.php`, `api/estatisticas.php`, `api/geocode.php`, `api/http.php` (módulo), `api/locais.php`, `api/locations.php`, `api/mailer.php` (módulo), `api/notificacoes.php`, `api/notifications.php`, `api/obstacle-confirmation.php`, `api/obstacles.php`, `api/ocorrencias.php`, `api/osm_pontos.php`, `api/relatorios.php`, `api/reviews.php`, `api/rota.php`, `api/rotas.php`, `api/routes.php`, `api/solicitacoes.php`, `api/upload.php`, `api/user-status.php`, `api/user.php`, `api/users.php`.
- **Uploads de usuários:** `uploads/` (criada no primeiro envio, ignorada pelo git e protegida por `uploads/.htaccess`, que desativa listagem e execução de script).
