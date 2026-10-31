-- =============================================================================
-- PASSE LIVRE — Migração: estado de leitura ADMINISTRATIVA das notificações
-- Arquivo: migracao_leitura_admin_notificacoes.sql
-- Aplicação: phpMyAdmin ou CLI no banco EXISTENTE (passe_livre_suzano)
--
-- O que esta migração faz:
--   CRIA a tabela `notificacoes_leitura_admin`, que guarda "este administrador
--   já viu esta notificação?". Uma linha por par (notificação, administrador),
--   com a data da leitura.
--
-- Por que uma tabela nova e não o `notificacoes.is_read`:
--   `is_read` pertence ao CIDADÃO destinatário — é ele quem lê a mensagem no app.
--   O botão "Marcar todas como lidas" do painel serve para o gestor organizar a
--   própria triagem. Gravar 1 em `is_read` a partir do painel faria a mensagem
--   sumir do "não lida" do cidadão sem que ele tenha lido nada.
--
-- O que esta migração NÃO faz:
--   - Não altera, não recria e não apaga nada em `notificacoes` (nem `is_read`,
--     nem `read_at`, nem os registros existentes).
--   - Não marca nenhuma notificação como lida: o estado administrativo só é
--     escrito quando o administrador usa o botão.
--
-- Idempotente: pode ser executada mais de uma vez sem efeitos colaterais.
--
-- IMPORTANTE: execute com cliente utf8mb4 (phpMyAdmin já faz isso; na CLI use
-- `mysql --default-character-set=utf8mb4`).
-- =============================================================================

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `notificacoes_leitura_admin` (
  `id`             INT NOT NULL AUTO_INCREMENT,
  `notificacao_id` INT NOT NULL,
  `admin_id`       INT NOT NULL COMMENT 'Conta de gestor (usuarios.is_admin = 1) que leu',
  `read_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_notif_leitura_admin` (`notificacao_id`, `admin_id`),
  KEY `idx_notif_leitura_admin_por_admin` (`admin_id`, `read_at`),
  CONSTRAINT `fk_notif_leitura_admin_notificacao` FOREIGN KEY (`notificacao_id`)
    REFERENCES `notificacoes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_notif_leitura_admin_usuario` FOREIGN KEY (`admin_id`)
    REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
