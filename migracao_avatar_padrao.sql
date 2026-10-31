-- =============================================================================
-- Passe Livre Suzano — Migração: foto padrão do usuário
-- =============================================================================
-- Objetivo:
--   Toda conta sem foto própria passa a guardar explicitamente a foto padrão do
--   projeto ('assets/images/avatar.jpg') em vez de NULL / ''. Assim a tela de
--   perfil e o cabeçalho sempre mostram uma imagem real, e nenhuma conta fica
--   apontando para upload de teste de outra pessoa.
--
-- O que este script faz:
--   1. Garante o DEFAULT da coluna (idempotente; DDL tem commit automático).
--   2. Normaliza usuarios.avatar: NULL, '' e qualquer valor que não seja
--      assets/…, uploads/… ou https://… voltam para a foto padrão.
--      Inclui a regra explícita do upload de teste 20261022-e390acff….
--   3. Normaliza solicitacoes.citizen_avatar com as mesmas regras (o cadastro
--      antigo gravava 'assets/icons/user.svg', que é ícone, não foto).
--
-- NUNCA toca em:
--   - 'assets/images/avatar-admin.jpg' (padrão dos administradores);
--   - uploads/… de contas que realmente enviaram foto;
--   - nenhuma linha por `id` — só pelos valores da própria coluna.
--
-- Como executar (o banco vai na linha de comando; o arquivo NÃO tem USE nem
-- DROP TABLE, para não haver como cair no banco errado):
--   C:/xampp/mysql/bin/mysql.exe -h 127.0.0.1 --protocol=TCP -u root \
--     passe_livre_suzano < migracao_avatar_padrao.sql
-- =============================================================================

-- -----------------------------------------------------------------------------
-- 1. DEFAULT das colunas (já correto nas instalações atuais; cobre as outras)
-- -----------------------------------------------------------------------------
ALTER TABLE usuarios
  ALTER COLUMN avatar SET DEFAULT 'assets/images/avatar.jpg';

ALTER TABLE solicitacoes
  ALTER COLUMN citizen_avatar SET DEFAULT 'assets/images/avatar.jpg';

-- -----------------------------------------------------------------------------
-- 2. Diagnóstico: o que está prestes a mudar
-- -----------------------------------------------------------------------------
SELECT 'usuarios antes' AS etapa,
       COUNT(*)         AS linhas_a_ajustar
  FROM usuarios
 WHERE avatar IS NULL
    OR avatar = ''
    OR avatar = 'uploads/20261022-e390acff5e907722f43f3878.jpg'
    OR (avatar NOT LIKE 'assets/%'
        AND avatar NOT LIKE 'uploads/%'
        AND avatar NOT LIKE 'https://%'
        AND avatar NOT LIKE 'api/avatar.php?ref=%');

SELECT 'solicitacoes antes' AS etapa,
       COUNT(*)             AS linhas_a_ajustar
  FROM solicitacoes
 WHERE citizen_avatar IS NULL
    OR citizen_avatar = ''
    OR (citizen_avatar NOT LIKE 'assets/images/%'
        AND citizen_avatar NOT LIKE 'uploads/%'
        AND citizen_avatar NOT LIKE 'https://%'
        AND citizen_avatar NOT LIKE 'api/avatar.php?ref=%');

-- -----------------------------------------------------------------------------
-- 3. Aplicação
-- -----------------------------------------------------------------------------
START TRANSACTION;

UPDATE usuarios
   SET avatar = 'assets/images/avatar.jpg'
 WHERE avatar IS NULL
    OR avatar = ''
    OR avatar = 'uploads/20261022-e390acff5e907722f43f3878.jpg'
    OR (avatar NOT LIKE 'assets/%'
        AND avatar NOT LIKE 'uploads/%'
        AND avatar NOT LIKE 'https://%'
        AND avatar NOT LIKE 'api/avatar.php?ref=%');

UPDATE solicitacoes
   SET citizen_avatar = 'assets/images/avatar.jpg'
 WHERE citizen_avatar IS NULL
    OR citizen_avatar = ''
    OR (citizen_avatar NOT LIKE 'assets/images/%'
        AND citizen_avatar NOT LIKE 'uploads/%'
        AND citizen_avatar NOT LIKE 'https://%'
        AND citizen_avatar NOT LIKE 'api/avatar.php?ref=%');

-- Conferência: qualquer linha fora do vocabulário aceito pelo servidor
-- (pl_avatar_path_valido / pl_avatar_exibicao em api/db.php) aparece aqui.
SELECT id, user_code, name, IFNULL(avatar, '<NULL>') AS avatar
  FROM usuarios
 WHERE avatar IS NULL
    OR avatar = ''
    OR (avatar NOT LIKE 'assets/%'
        AND avatar NOT LIKE 'uploads/%'
        AND avatar NOT LIKE 'https://%'
        AND avatar NOT LIKE 'api/avatar.php?ref=%');

COMMIT;
