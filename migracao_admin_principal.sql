-- =============================================================================
-- PASSE LIVRE SUZANO — Migração: nível "Administrador Principal"
-- Arquivo: migracao_admin_principal.sql
-- Aplicação: phpMyAdmin ou CLI no banco EXISTENTE (passe_livre_suzano)
--
-- O que esta migração faz:
--   1. ADICIONA a coluna `usuarios.is_primary_admin` (TINYINT(1), NOT NULL,
--      DEFAULT 0) — fonte de verdade no banco para o nível mais alto de acesso.
--   2. PRESERVA os administradores atuais: converte para `is_admin = 1` quem hoje
--      só tinha acesso pelo texto de `role` (o código deixa de aceitar texto).
--   3. Marca exatamente UMA conta de administrador já existente como principal.
--
-- O que esta migração NÃO faz:
--   - Não apaga nem recria tabelas; não toca em senhas, sessões ou dados de cidadãos.
--   - Não remove acesso de nenhum administrador existente.
--   - Não insere usuário novo nem usa nome/e-mail fixo: a escolha do principal é
--     feita por regra de dados (administrador ativo que já possui papel 'gestor'
--     em `usuario_papeis`; em empate, o de menor ID).
--
-- Idempotente: pode ser executada mais de uma vez sem efeitos colaterais.
-- =============================================================================

USE `passe_livre_suzano`;

-- -----------------------------------------------------------------------------
-- 1. Coluna `is_primary_admin` (somente se ainda não existir)
-- -----------------------------------------------------------------------------
SET @col_primary_exists = 0;
SELECT COUNT(*) INTO @col_primary_exists
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'usuarios'
  AND COLUMN_NAME = 'is_primary_admin';

SET @alter_sql_primary = IF(@col_primary_exists = 0,
  'ALTER TABLE `usuarios` ADD COLUMN `is_primary_admin` TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_admin`',
  'SELECT "Coluna is_primary_admin já existe na tabela usuarios" AS status');
PREPARE stmt_primary FROM @alter_sql_primary;
EXECUTE stmt_primary;
DEALLOCATE PREPARE stmt_primary;

-- -----------------------------------------------------------------------------
-- 2. Preserva acessos que hoje dependem apenas do texto de `role`
--    (passa a valer somente a flag `is_admin`, que é verificada no banco)
-- -----------------------------------------------------------------------------
UPDATE `usuarios`
SET `is_admin` = 1
WHERE `is_admin` = 0
  AND `status` = 'ativo'
  AND (`role` LIKE '%admin%' OR `role` LIKE '%gestor%');

-- -----------------------------------------------------------------------------
-- 3. Define o administrador principal (apenas se ainda não houver nenhum)
-- -----------------------------------------------------------------------------
UPDATE `usuarios`
SET `is_primary_admin` = 1
WHERE `id` = (
        SELECT `pick_id` FROM (
          SELECT u.`id` AS `pick_id`
          FROM `usuarios` u
          LEFT JOIN `usuario_papeis` up
            ON up.`user_id` = u.`id` AND up.`papel` = 'gestor'
          WHERE u.`is_admin` = 1
            AND u.`status` = 'ativo'
            AND NOT EXISTS (
              SELECT 1 FROM `usuarios` p WHERE p.`is_primary_admin` = 1
            )
          ORDER BY (up.`user_id` IS NOT NULL) DESC, u.`id` ASC
          LIMIT 1
        ) AS escolha
      )
  AND `is_primary_admin` = 0;

-- -----------------------------------------------------------------------------
-- 4. Conferência (somente leitura)
-- -----------------------------------------------------------------------------
SELECT `id`, `user_code`, `name`, `email`, `status`, `is_admin`, `is_primary_admin`
FROM `usuarios`
WHERE `is_admin` = 1
ORDER BY `is_primary_admin` DESC, `id` ASC;
