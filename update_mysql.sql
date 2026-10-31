-- =============================================================================
-- PASSE LIVRE SUZANO — Atualização Incremental do Banco de Dados MySQL
-- Arquivo: update_mysql.sql
-- Aplicação: Executar no phpMyAdmin no banco de dados existente (passe_livre_suzano)
-- Segurança: NÃO apaga tabelas, NÃO apaga o banco e PRESERVA todos os dados atuais.
-- =============================================================================

USE `passe_livre_suzano`;

-- 1. Garante que a tabela `notificacoes` existe com a estrutura completa
CREATE TABLE IF NOT EXISTS `notificacoes` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `user_id` INT NOT NULL,
  `sender_id` INT DEFAULT NULL COMMENT 'ID do gestor/administrador que disparou a mensagem',
  `title` VARCHAR(200) NOT NULL,
  `body` TEXT DEFAULT NULL,
  `type` VARCHAR(20) NOT NULL DEFAULT 'info' COMMENT 'info | warning | alert | success | gestor | rota',
  `is_read` TINYINT(1) NOT NULL DEFAULT 0,
  `local_code` VARCHAR(30) DEFAULT NULL,
  `link` VARCHAR(255) DEFAULT NULL COMMENT 'URL ou rota de ação vinculada à notificação',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_notif_user` (`user_id`, `is_read`),
  CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Adiciona a coluna `link` caso a tabela já existisse sem ela
SET @col_link_exists = 0;
SELECT COUNT(*) INTO @col_link_exists
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'notificacoes'
  AND COLUMN_NAME = 'link';

SET @alter_sql_link = IF(@col_link_exists = 0,
  'ALTER TABLE `notificacoes` ADD COLUMN `link` VARCHAR(255) DEFAULT NULL AFTER `local_code`',
  'SELECT "Coluna link já existe na tabela notificacoes" AS status');
PREPARE stmt_link FROM @alter_sql_link;
EXECUTE stmt_link;
DEALLOCATE PREPARE stmt_link;

-- 3. Adiciona a coluna `sender_id` caso a tabela já existisse sem ela
SET @col_sender_exists = 0;
SELECT COUNT(*) INTO @col_sender_exists
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'notificacoes'
  AND COLUMN_NAME = 'sender_id';

SET @alter_sql_sender = IF(@col_sender_exists = 0,
  'ALTER TABLE `notificacoes` ADD COLUMN `sender_id` INT DEFAULT NULL AFTER `user_id`',
  'SELECT "Coluna sender_id já existe na tabela notificacoes" AS status');
PREPARE stmt_sender FROM @alter_sql_sender;
EXECUTE stmt_sender;
DEALLOCATE PREPARE stmt_sender;

-- 4. Adiciona índice para listagem ordenada rápida por usuário
SET @idx_created_exists = 0;
SELECT COUNT(*) INTO @idx_created_exists
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'notificacoes'
  AND INDEX_NAME = 'idx_notif_user_created';

SET @idx_sql = IF(@idx_created_exists = 0,
  'ALTER TABLE `notificacoes` ADD INDEX `idx_notif_user_created` (`user_id`, `created_at` DESC)',
  'SELECT "Índice idx_notif_user_created já existe" AS status');
PREPARE stmt_idx FROM @idx_sql;
EXECUTE stmt_idx;
DEALLOCATE PREPARE stmt_idx;

-- =============================================================================
-- Atualização 2: Adiciona coluna `read_at` na tabela `notificacoes` (se não existir)
-- Necessária para compatibilidade com db-queries.js que registrava leitura com timestamp
-- =============================================================================

SET @col_read_at_exists = 0;
SELECT COUNT(*) INTO @col_read_at_exists
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'notificacoes'
  AND COLUMN_NAME = 'read_at';

SET @alter_read_at = IF(@col_read_at_exists = 0,
  'ALTER TABLE `notificacoes` ADD COLUMN `read_at` DATETIME DEFAULT NULL COMMENT "Timestamp de quando a notificação foi lida" AFTER `is_read`',
  'SELECT "Coluna read_at já existe em notificacoes" AS status');
PREPARE stmt_read_at FROM @alter_read_at;
EXECUTE stmt_read_at;
DEALLOCATE PREPARE stmt_read_at;

-- =============================================================================
-- Atualização 3: Adiciona colunas para recuperação de senha segura em `codigos_verificacao`
-- Permite emissão de token de autorização de uso único com expiração (15 min)
-- =============================================================================

SET @col_token_auth_exists = 0;
SELECT COUNT(*) INTO @col_token_auth_exists
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'codigos_verificacao'
  AND COLUMN_NAME = 'token_autorizacao';

SET @alter_token_auth = IF(@col_token_auth_exists = 0,
  'ALTER TABLE `codigos_verificacao` ADD COLUMN `token_autorizacao` VARCHAR(128) DEFAULT NULL COMMENT "Token temporário de uso único pós-OTP para autorizar reset_password" AFTER `usado`',
  'SELECT "Coluna token_autorizacao já existe em codigos_verificacao" AS status');
PREPARE stmt_token_auth FROM @alter_token_auth;
EXECUTE stmt_token_auth;
DEALLOCATE PREPARE stmt_token_auth;

SET @col_token_exp_exists = 0;
SELECT COUNT(*) INTO @col_token_exp_exists
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'codigos_verificacao'
  AND COLUMN_NAME = 'token_expires_at';

SET @alter_token_exp = IF(@col_token_exp_exists = 0,
  'ALTER TABLE `codigos_verificacao` ADD COLUMN `token_expires_at` DATETIME DEFAULT NULL COMMENT "Expiração do token de autorização" AFTER `token_autorizacao`',
  'SELECT "Coluna token_expires_at já existe em codigos_verificacao" AS status');
PREPARE stmt_token_exp FROM @alter_token_exp;
EXECUTE stmt_token_exp;
DEALLOCATE PREPARE stmt_token_exp;

SET @idx_token_exists = 0;
SELECT COUNT(*) INTO @idx_token_exists
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'codigos_verificacao'
  AND INDEX_NAME = 'idx_codigos_token';

SET @idx_token_sql = IF(@idx_token_exists = 0,
  'ALTER TABLE `codigos_verificacao` ADD INDEX `idx_codigos_token` (`token_autorizacao`)',
  'SELECT "Índice idx_codigos_token já existe" AS status');
PREPARE stmt_token_idx FROM @idx_token_sql;
EXECUTE stmt_token_idx;
DEALLOCATE PREPARE stmt_token_idx;

-- =============================================================================
-- Atualização 4: Adiciona coluna `acao_desvio` em `navegacoes` (se não existir)
-- Permite vincular escolha de ação de obstáculo à sessão de navegação do usuário
-- =============================================================================

SET @col_desvio_exists = 0;
SELECT COUNT(*) INTO @col_desvio_exists
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'navegacoes'
  AND COLUMN_NAME = 'acao_desvio';

SET @alter_desvio = IF(@col_desvio_exists = 0,
  'ALTER TABLE `navegacoes` ADD COLUMN `acao_desvio` VARCHAR(50) DEFAULT NULL COMMENT "Escolha de ação do usuário na navegação (desviar | continuar)" AFTER `recalculos_count`',
  'SELECT "Coluna acao_desvio já existe em navegacoes" AS status');
PREPARE stmt_desvio FROM @alter_desvio;
EXECUTE stmt_desvio;
DEALLOCATE PREPARE stmt_desvio;

-- =============================================================================
-- Atualização 5: Adiciona coluna `dados_pendentes` em `codigos_verificacao`
-- Guarda o pré-cadastro em JSON até a verificação do e-mail (register/resend/verify)
-- =============================================================================

SET @col_dados_pendentes_exists = 0;
SELECT COUNT(*) INTO @col_dados_pendentes_exists
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'codigos_verificacao'
  AND COLUMN_NAME = 'dados_pendentes';

SET @alter_dados_pendentes = IF(@col_dados_pendentes_exists = 0,
  'ALTER TABLE `codigos_verificacao` ADD COLUMN `dados_pendentes` LONGTEXT DEFAULT NULL COMMENT "JSON com os dados do pré-cadastro até a verificação do e-mail" AFTER `tipo`',
  'SELECT "Coluna dados_pendentes já existe em codigos_verificacao" AS status');
PREPARE stmt_dados_pendentes FROM @alter_dados_pendentes;
EXECUTE stmt_dados_pendentes;
DEALLOCATE PREPARE stmt_dados_pendentes;

-- =============================================================================
-- Atualização 6: Adiciona coluna `comment` em `avaliacoes` (se não existir)
-- Separa o comentário público da avaliação do relato de barreira temporária
-- (barrier_desc), que continua alimentando a tabela `ocorrencias`.
-- =============================================================================

SET @col_comment_exists = 0;
SELECT COUNT(*) INTO @col_comment_exists
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'avaliacoes'
  AND COLUMN_NAME = 'comment';

SET @alter_comment = IF(@col_comment_exists = 0,
  'ALTER TABLE `avaliacoes` ADD COLUMN `comment` TEXT DEFAULT NULL COMMENT "Comentário público da avaliação" AFTER `barrier_desc`',
  'SELECT "Coluna comment já existe em avaliacoes" AS status');
PREPARE stmt_comment FROM @alter_comment;
EXECUTE stmt_comment;
DEALLOCATE PREPARE stmt_comment;


-- =============================================================================
-- Atualização 7: Localização estruturada nos relatos cidadãos (`solicitacoes`)
-- CEP, bairro, cidade, UF e coordenadas permitem enviar relatos sem endereço
-- completo e, futuramente, exibir os ocorridos em um mapa.
-- =============================================================================

SET @col_sol_cep_exists = 0;
SELECT COUNT(*) INTO @col_sol_cep_exists
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'solicitacoes'
  AND COLUMN_NAME = 'cep';

SET @alter_sol_cep = IF(@col_sol_cep_exists = 0,
  'ALTER TABLE `solicitacoes` ADD COLUMN `cep` VARCHAR(9) DEFAULT NULL AFTER `complement`',
  'SELECT "Coluna cep já existe em solicitacoes" AS status');
PREPARE stmt_sol_cep FROM @alter_sol_cep;
EXECUTE stmt_sol_cep;
DEALLOCATE PREPARE stmt_sol_cep;

SET @col_sol_bairro_exists = 0;
SELECT COUNT(*) INTO @col_sol_bairro_exists
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'solicitacoes'
  AND COLUMN_NAME = 'bairro';

SET @alter_sol_bairro = IF(@col_sol_bairro_exists = 0,
  'ALTER TABLE `solicitacoes` ADD COLUMN `bairro` VARCHAR(100) DEFAULT NULL AFTER `cep`',
  'SELECT "Coluna bairro já existe em solicitacoes" AS status');
PREPARE stmt_sol_bairro FROM @alter_sol_bairro;
EXECUTE stmt_sol_bairro;
DEALLOCATE PREPARE stmt_sol_bairro;

SET @col_sol_cidade_exists = 0;
SELECT COUNT(*) INTO @col_sol_cidade_exists
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'solicitacoes'
  AND COLUMN_NAME = 'cidade';

SET @alter_sol_cidade = IF(@col_sol_cidade_exists = 0,
  'ALTER TABLE `solicitacoes` ADD COLUMN `cidade` VARCHAR(100) DEFAULT NULL AFTER `bairro`',
  'SELECT "Coluna cidade já existe em solicitacoes" AS status');
PREPARE stmt_sol_cidade FROM @alter_sol_cidade;
EXECUTE stmt_sol_cidade;
DEALLOCATE PREPARE stmt_sol_cidade;

SET @col_sol_estado_exists = 0;
SELECT COUNT(*) INTO @col_sol_estado_exists
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'solicitacoes'
  AND COLUMN_NAME = 'estado';

SET @alter_sol_estado = IF(@col_sol_estado_exists = 0,
  'ALTER TABLE `solicitacoes` ADD COLUMN `estado` CHAR(2) DEFAULT NULL AFTER `cidade`',
  'SELECT "Coluna estado já existe em solicitacoes" AS status');
PREPARE stmt_sol_estado FROM @alter_sol_estado;
EXECUTE stmt_sol_estado;
DEALLOCATE PREPARE stmt_sol_estado;

SET @col_sol_lat_exists = 0;
SELECT COUNT(*) INTO @col_sol_lat_exists
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'solicitacoes'
  AND COLUMN_NAME = 'latitude';

SET @alter_sol_lat = IF(@col_sol_lat_exists = 0,
  'ALTER TABLE `solicitacoes` ADD COLUMN `latitude` DECIMAL(10,7) DEFAULT NULL AFTER `estado`',
  'SELECT "Coluna latitude já existe em solicitacoes" AS status');
PREPARE stmt_sol_lat FROM @alter_sol_lat;
EXECUTE stmt_sol_lat;
DEALLOCATE PREPARE stmt_sol_lat;

SET @col_sol_lng_exists = 0;
SELECT COUNT(*) INTO @col_sol_lng_exists
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'solicitacoes'
  AND COLUMN_NAME = 'longitude';

SET @alter_sol_lng = IF(@col_sol_lng_exists = 0,
  'ALTER TABLE `solicitacoes` ADD COLUMN `longitude` DECIMAL(10,7) DEFAULT NULL AFTER `latitude`',
  'SELECT "Coluna longitude já existe em solicitacoes" AS status');
PREPARE stmt_sol_lng FROM @alter_sol_lng;
EXECUTE stmt_sol_lng;
DEALLOCATE PREPARE stmt_sol_lng;

SET @idx_sol_coords_exists = 0;
SELECT COUNT(*) INTO @idx_sol_coords_exists
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'solicitacoes'
  AND INDEX_NAME = 'idx_sol_coords';

SET @idx_sol_coords_sql = IF(@idx_sol_coords_exists = 0,
  'ALTER TABLE `solicitacoes` ADD INDEX `idx_sol_coords` (`latitude`, `longitude`)',
  'SELECT "Índice idx_sol_coords já existe" AS status');
PREPARE stmt_sol_coords_idx FROM @idx_sol_coords_sql;
EXECUTE stmt_sol_coords_idx;
DEALLOCATE PREPARE stmt_sol_coords_idx;
