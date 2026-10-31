-- =============================================================================
-- PASSE LIVRE SUZANO — Banco de Dados MySQL 8.0+
-- Versão: 3.5 (Schema Relacional Completo Auditado e Adaptado)
-- Charset: utf8mb4 / Collation: utf8mb4_unicode_ci
-- =============================================================================

SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;
SET time_zone = '-03:00';

CREATE DATABASE IF NOT EXISTS `passe_livre_suzano`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `passe_livre_suzano`;

SET FOREIGN_KEY_CHECKS = 0;

-- -----------------------------------------------------------------------------
-- 1. TABELA: usuarios
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `usuarios`;
CREATE TABLE `usuarios` (
  `id`                    INT NOT NULL AUTO_INCREMENT,
  `user_code`             VARCHAR(30) NOT NULL,
  `name`                  VARCHAR(120) NOT NULL,
  `email`                 VARCHAR(200) NOT NULL,
  `password_hash`         VARCHAR(255) DEFAULT NULL,
  `phone`                 VARCHAR(30) DEFAULT NULL,
  `cpf`                   VARCHAR(14) DEFAULT NULL,
  `birth_date`            DATE DEFAULT NULL,
  `gender`                VARCHAR(30) DEFAULT 'Não informado',
  `city`                  VARCHAR(100) DEFAULT 'Suzano - SP',
  `bairro`                VARCHAR(100) DEFAULT NULL,
  `address`               VARCHAR(255) DEFAULT NULL,
  `description`           TEXT DEFAULT NULL,
  `role`                  VARCHAR(100) DEFAULT 'Cidadão Conectado',
  `is_admin`              TINYINT(1) NOT NULL DEFAULT 0,
  `is_primary_admin`      TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = único administrador principal (gerencia de acessos)',
  `avatar`                VARCHAR(255) DEFAULT 'assets/images/avatar.jpg',
  `initials`              VARCHAR(5) DEFAULT NULL,
  `status`                VARCHAR(30) NOT NULL DEFAULT 'ativo' COMMENT 'ativo | bloqueado | pendente',
  `email_verified`        TINYINT(1) NOT NULL DEFAULT 0,
  `failed_login_attempts` INT NOT NULL DEFAULT 0,
  `locked_until`          DATETIME DEFAULT NULL,
  `pcd_categories`        JSON DEFAULT NULL,
  `saved_prefs`           JSON DEFAULT NULL,
  `is_google`             TINYINT(1) NOT NULL DEFAULT 0,
  `created_at`            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_usuarios_email` (`email`),
  UNIQUE KEY `uq_usuarios_code` (`user_code`),
  KEY `idx_usuarios_email` (`email`),
  KEY `idx_usuarios_status` (`status`),
  KEY `idx_usuarios_role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `usuarios_avatares`;
CREATE TABLE `usuarios_avatares` (
  `user_id` INT NOT NULL,
  `conteudo` MEDIUMBLOB NOT NULL,
  `mime` VARCHAR(32) NOT NULL,
  `tamanho` INT UNSIGNED NOT NULL,
  `versao` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  CONSTRAINT `fk_usuarios_avatares_usuario` FOREIGN KEY (`user_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- View de compatibilidade para código legado que consulta `users`
DROP VIEW IF EXISTS `users`;
DROP TABLE IF EXISTS `users`;
CREATE OR REPLACE VIEW `users` AS SELECT * FROM `usuarios`;

-- -----------------------------------------------------------------------------
-- 2. TABELA: sessoes
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `sessoes`;
CREATE TABLE `sessoes` (
  `id`            INT NOT NULL AUTO_INCREMENT,
  `user_id`       INT NOT NULL,
  `session_token` VARCHAR(128) NOT NULL,
  `ip_address`    VARCHAR(45) DEFAULT NULL,
  `user_agent`    TEXT DEFAULT NULL,
  `is_remember`   TINYINT(1) NOT NULL DEFAULT 0,
  `expires_at`    DATETIME NOT NULL,
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sessao_token` (`session_token`),
  KEY `idx_sessao_user` (`user_id`),
  KEY `idx_sessao_expires` (`expires_at`),
  CONSTRAINT `fk_sessoes_user` FOREIGN KEY (`user_id`)
    REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 3. TABELA: mensagens_contato
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `mensagens_contato`;
CREATE TABLE `mensagens_contato` (
  `id`         INT NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(150) NOT NULL,
  `email`      VARCHAR(200) NOT NULL,
  `phone`      VARCHAR(30) DEFAULT NULL,
  `subject`    VARCHAR(200) DEFAULT 'Contato pelo site',
  `message`    TEXT NOT NULL,
  `user_id`    INT DEFAULT NULL,
  `status`     VARCHAR(50) NOT NULL DEFAULT 'pendente' COMMENT 'pendente | respondida | arquivada',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_contato_user` (`user_id`),
  KEY `idx_contato_status` (`status`),
  CONSTRAINT `fk_contato_user` FOREIGN KEY (`user_id`)
    REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 4. TABELA: usuario_necessidades
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `usuario_necessidades`;
CREATE TABLE `usuario_necessidades` (
  `id`            INT NOT NULL AUTO_INCREMENT,
  `user_id`       INT NOT NULL,
  `categoria_pcd` VARCHAR(100) NOT NULL,
  `descricao`     TEXT DEFAULT NULL,
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_un_user` (`user_id`),
  CONSTRAINT `fk_un_user` FOREIGN KEY (`user_id`)
    REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 5. TABELA: usuario_consentimentos
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `usuario_consentimentos`;
CREATE TABLE `usuario_consentimentos` (
  `id`                 INT NOT NULL AUTO_INCREMENT,
  `user_id`            INT NOT NULL,
  `tipo_consentimento` VARCHAR(100) NOT NULL,
  `aceito`             TINYINT(1) NOT NULL DEFAULT 1,
  `ip_address`         VARCHAR(45) DEFAULT NULL,
  `data_aceite`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_uc_user` (`user_id`),
  CONSTRAINT `fk_uc_user` FOREIGN KEY (`user_id`)
    REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 6. TABELA: codigos_verificacao
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `codigos_verificacao`;
CREATE TABLE `codigos_verificacao` (
  `id`         INT NOT NULL AUTO_INCREMENT,
  `user_id`    INT DEFAULT NULL,
  `email`      VARCHAR(200) NOT NULL,
  `codigo`     VARCHAR(10) NOT NULL,
  `tipo`       VARCHAR(50) NOT NULL COMMENT 'cadastro | recuperacao_senha',
  `dados_pendentes` LONGTEXT DEFAULT NULL COMMENT 'JSON com os dados do pré-cadastro até a verificação do e-mail',
  `tentativas` INT NOT NULL DEFAULT 0,
  `usado`      TINYINT(1) NOT NULL DEFAULT 0,
  `token_autorizacao` VARCHAR(128) DEFAULT NULL COMMENT 'Token temporário de uso único pós-OTP para autorizar reset_password',
  `token_expires_at`  DATETIME DEFAULT NULL COMMENT 'Expiração do token de autorização',
  `expires_at` DATETIME NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cv_email_tipo` (`email`, `tipo`, `usado`),
  KEY `idx_cv_codigo` (`codigo`),
  KEY `idx_codigos_token` (`token_autorizacao`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 7. TABELA: usuario_identidades_externas
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `usuario_identidades_externas`;
CREATE TABLE `usuario_identidades_externas` (
  `id`               INT NOT NULL AUTO_INCREMENT,
  `user_id`          INT NOT NULL,
  `provider`         VARCHAR(50) NOT NULL DEFAULT 'google',
  `provider_user_id` VARCHAR(255) NOT NULL,
  `email`            VARCHAR(200) NOT NULL,
  `dados_adicionais` JSON DEFAULT NULL,
  `created_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ident_provider` (`provider`, `provider_user_id`),
  KEY `idx_ident_user` (`user_id`),
  CONSTRAINT `fk_ident_user` FOREIGN KEY (`user_id`)
    REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 8. TABELA: usuario_preferencias_busca
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `usuario_preferencias_busca`;
CREATE TABLE `usuario_preferencias_busca` (
  `id`                       INT NOT NULL AUTO_INCREMENT,
  `user_id`                  INT NOT NULL,
  `raio_km`                  DOUBLE NOT NULL DEFAULT 5.0,
  `nivel_acessibilidade`     VARCHAR(50) NOT NULL DEFAULT 'todos',
  `recursos`                 JSON DEFAULT NULL,
  `usar_preferencias_salvas` TINYINT(1) NOT NULL DEFAULT 1,
  `updated_at`               DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_upb_user` (`user_id`),
  CONSTRAINT `fk_upb_user` FOREIGN KEY (`user_id`)
    REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 9. TABELA: buscas_historico
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `buscas_historico`;
CREATE TABLE `buscas_historico` (
  `id`               INT NOT NULL AUTO_INCREMENT,
  `user_id`          INT DEFAULT NULL,
  `origem_texto`     VARCHAR(255) DEFAULT NULL,
  `origem_lat`       DOUBLE DEFAULT NULL,
  `origem_lng`       DOUBLE DEFAULT NULL,
  `categoria_key`    VARCHAR(100) DEFAULT NULL,
  `raio_km`          DOUBLE DEFAULT NULL,
  `filtros`          JSON DEFAULT NULL,
  `total_resultados` INT NOT NULL DEFAULT 0,
  `created_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_bh_user` (`user_id`),
  CONSTRAINT `fk_bh_user` FOREIGN KEY (`user_id`)
    REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 10. TABELA: locais
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `locais`;
CREATE TABLE `locais` (
  `id`              INT NOT NULL AUTO_INCREMENT,
  `local_code`      VARCHAR(30) NOT NULL,
  `name`            VARCHAR(200) NOT NULL,
  `category`        VARCHAR(100) NOT NULL,
  `category_key`    VARCHAR(50) NOT NULL,
  `address`         VARCHAR(255) NOT NULL,
  `bairro`          VARCHAR(100) DEFAULT NULL,
  `municipio`       VARCHAR(120) DEFAULT NULL
                    COMMENT 'Município do endereço; NULL = não informado (nunca deduzido por padrão)',
  `uf`              VARCHAR(2) DEFAULT NULL,
  `access_status`   VARCHAR(20) NOT NULL DEFAULT 'unverified'
                    COMMENT 'verified | partial | unverified | critical',
  `access_text`     VARCHAR(100) DEFAULT NULL,
  `rating`          DOUBLE NOT NULL DEFAULT 0.0,
  `reviews_count`   INT NOT NULL DEFAULT 0,
  `image`           VARCHAR(255) DEFAULT 'assets/images/map-background.jpg',
  `lat`             DOUBLE DEFAULT NULL,
  `lng`             DOUBLE DEFAULT NULL,
  `features`        JSON DEFAULT NULL COMMENT 'JSON array',
  `opening_hours`   VARCHAR(150) DEFAULT 'Segunda a Sexta: 08:00 - 18:00',
  `bus_lines`       TEXT DEFAULT 'Linha 01TR, Linha 02TR, Linha 14TR',
  `useful_count`    INT NOT NULL DEFAULT 0,
  `is_archived`     TINYINT(1) NOT NULL DEFAULT 0,
  `last_audit`      VARCHAR(80) DEFAULT NULL,
  `avatar_color`    VARCHAR(20) DEFAULT 'orange',
  `avatar_icon`     VARCHAR(255) DEFAULT 'assets/icons/pin.svg',
  `incidents_count` INT NOT NULL DEFAULT 0,
  `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_locais_code` (`local_code`),
  KEY `idx_locais_code` (`local_code`),
  KEY `idx_locais_status` (`access_status`),
  KEY `idx_locais_category` (`category_key`),
  KEY `idx_locais_archived` (`is_archived`),
  KEY `idx_locais_municipio` (`municipio`, `uf`),
  KEY `idx_locais_geo` (`lat`, `lng`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 11. TABELA: local_recursos
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `local_recursos`;
CREATE TABLE `local_recursos` (
  `id`           INT NOT NULL AUTO_INCREMENT,
  `local_id`     INT NOT NULL,
  `recurso_key`  VARCHAR(80) NOT NULL,
  `nome_recurso` VARCHAR(150) NOT NULL,
  `status`       VARCHAR(50) NOT NULL DEFAULT 'disponivel' COMMENT 'disponivel | parcial | indisponivel',
  `detalhes`     TEXT DEFAULT NULL,
  `updated_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_lr_local` (`local_id`),
  KEY `idx_lr_key` (`recurso_key`),
  CONSTRAINT `fk_lr_local` FOREIGN KEY (`local_id`)
    REFERENCES `locais` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 12. TABELA: avaliacoes
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `avaliacoes`;
CREATE TABLE `avaliacoes` (
  `id`                    INT NOT NULL AUTO_INCREMENT,
  `local_id`              INT DEFAULT NULL,
  `local_code`            VARCHAR(30) DEFAULT NULL,
  `user_id`               INT DEFAULT NULL,
  `user_name`             VARCHAR(120) DEFAULT NULL,
  `entrada_status`        VARCHAR(30) DEFAULT NULL,
  `rampas_status`         VARCHAR(30) DEFAULT NULL,
  `elevadores_status`     VARCHAR(30) DEFAULT NULL,
  `banheiros_status`      VARCHAR(30) DEFAULT NULL,
  `calcadas_status`       VARCHAR(30) DEFAULT NULL,
  `estacionamento_status` VARCHAR(30) DEFAULT NULL,
  `barrier_desc`          TEXT DEFAULT NULL,
  `comment`               TEXT DEFAULT NULL COMMENT 'Comentário público da avaliação',
  `photos`                JSON DEFAULT NULL COMMENT 'JSON array',
  `rating`                DOUBLE NOT NULL DEFAULT 5.0,
  `verified`              TINYINT(1) NOT NULL DEFAULT 0,
  `is_useful_count`       INT NOT NULL DEFAULT 0,
  `created_at`            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_aval_local` (`local_id`),
  KEY `idx_aval_code` (`local_code`),
  KEY `idx_aval_user` (`user_id`),
  CONSTRAINT `fk_aval_local` FOREIGN KEY (`local_id`)
    REFERENCES `locais` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_aval_user` FOREIGN KEY (`user_id`)
    REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 13. TABELA: avaliacao_criterios
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `avaliacao_criterios`;
CREATE TABLE `avaliacao_criterios` (
  `id`           INT NOT NULL AUTO_INCREMENT,
  `avaliacao_id` INT NOT NULL,
  `criterio_key` VARCHAR(80) NOT NULL,
  `nota`         INT NOT NULL DEFAULT 5,
  `comentario`   TEXT DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_ac_avaliacao` (`avaliacao_id`),
  CONSTRAINT `fk_ac_avaliacao` FOREIGN KEY (`avaliacao_id`)
    REFERENCES `avaliacoes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 14. TABELA: arquivos
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `arquivos`;
CREATE TABLE `arquivos` (
  `id`            INT NOT NULL AUTO_INCREMENT,
  `user_id`       INT DEFAULT NULL,
  `nome_original` VARCHAR(255) NOT NULL,
  `nome_arquivo`  VARCHAR(255) NOT NULL,
  `caminho`       VARCHAR(255) NOT NULL,
  `tipo_mime`     VARCHAR(100) DEFAULT 'image/jpeg',
  `tamanho_bytes` BIGINT DEFAULT 0,
  `tipo_origem`   VARCHAR(50) DEFAULT 'avaliacao',
  `referencia_id` INT DEFAULT NULL,
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_arq_user` (`user_id`),
  CONSTRAINT `fk_arq_user` FOREIGN KEY (`user_id`)
    REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 15. TABELA: avaliacao_fotos
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `avaliacao_fotos`;
CREATE TABLE `avaliacao_fotos` (
  `id`           INT NOT NULL AUTO_INCREMENT,
  `avaliacao_id` INT NOT NULL,
  `arquivo_id`   INT DEFAULT NULL,
  `url_foto`     VARCHAR(255) NOT NULL,
  `foto_lat`     DOUBLE DEFAULT NULL,
  `foto_lng`     DOUBLE DEFAULT NULL,
  `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_af_avaliacao` (`avaliacao_id`),
  CONSTRAINT `fk_af_avaliacao` FOREIGN KEY (`avaliacao_id`)
    REFERENCES `avaliacoes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_af_arquivo` FOREIGN KEY (`arquivo_id`)
    REFERENCES `arquivos` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 16. TABELA: ocorrencias (Barreiras e Obstáculos)
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `ocorrencias`;
CREATE TABLE `ocorrencias` (
  `id`                        INT NOT NULL AUTO_INCREMENT,
  `user_id`                   INT DEFAULT NULL,
  `local_id`                  INT DEFAULT NULL,
  `protocolo`                 VARCHAR(30) DEFAULT NULL,
  `titulo`                    VARCHAR(200) NOT NULL,
  `descricao`                 TEXT NOT NULL,
  `categoria`                 VARCHAR(100) NOT NULL,
  `status`                    VARCHAR(50) NOT NULL DEFAULT 'ativa' COMMENT 'ativa | em_analise | resolvida | cancelada',
  `gravidade`                 VARCHAR(50) NOT NULL DEFAULT 'media' COMMENT 'baixa | media | alta | urgente',
  `lat`                       DOUBLE DEFAULT NULL,
  `lng`                       DOUBLE DEFAULT NULL,
  `endereco`                  VARCHAR(255) NOT NULL,
  `foto_url`                  VARCHAR(255) DEFAULT NULL,
  `reporter_nome`             VARCHAR(120) DEFAULT NULL,
  `tempo_decorrido`           VARCHAR(100) DEFAULT 'Poucos minutos atrás',
  `rota_alternativa_sugerida` TEXT DEFAULT NULL,
  `escolha_acao`              VARCHAR(50) DEFAULT NULL COMMENT 'desviar | continuar',
  `created_at`                DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`                DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ocorr_proto` (`protocolo`),
  KEY `idx_ocorr_status` (`status`),
  KEY `idx_ocorr_user` (`user_id`),
  KEY `idx_ocorr_local` (`local_id`),
  CONSTRAINT `fk_ocorr_user` FOREIGN KEY (`user_id`)
    REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_ocorr_local` FOREIGN KEY (`local_id`)
    REFERENCES `locais` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 17. TABELA: rotas
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `rotas`;
CREATE TABLE `rotas` (
  `id`               INT NOT NULL AUTO_INCREMENT,
  `user_id`          INT DEFAULT NULL,
  `origin_text`      VARCHAR(255) NOT NULL,
  `destination_text` VARCHAR(255) NOT NULL,
  `origin_lat`       DOUBLE DEFAULT NULL,
  `origin_lng`       DOUBLE DEFAULT NULL,
  `dest_lat`         DOUBLE DEFAULT NULL,
  `dest_lng`         DOUBLE DEFAULT NULL,
  `route_type`       VARCHAR(30) NOT NULL DEFAULT 'acessivel' COMMENT 'acessivel | rapida | transporte',
  `distance_km`      DOUBLE DEFAULT NULL,
  `duration_min`     INT DEFAULT NULL,
  `status`           VARCHAR(50) NOT NULL DEFAULT 'planejada',
  `created_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_rotas_user` (`user_id`),
  CONSTRAINT `fk_rotas_user` FOREIGN KEY (`user_id`)
    REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 18. TABELA: rota_opcoes
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `rota_opcoes`;
CREATE TABLE `rota_opcoes` (
  `id`                  INT NOT NULL AUTO_INCREMENT,
  `rota_id`             INT NOT NULL,
  `tipo_opcao`          VARCHAR(50) NOT NULL COMMENT 'acessivel | rapida | transporte',
  `distancia_km`        DOUBLE NOT NULL,
  `duracao_min`         INT NOT NULL,
  `compatibilidade_pct` INT NOT NULL DEFAULT 100,
  `detalhes`            JSON DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_ro_rota` (`rota_id`),
  CONSTRAINT `fk_ro_rota` FOREIGN KEY (`rota_id`)
    REFERENCES `rotas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 19. TABELA: rota_passos
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `rota_passos`;
CREATE TABLE `rota_passos` (
  `id`               INT NOT NULL AUTO_INCREMENT,
  `rota_id`          INT NOT NULL,
  `ordem`            INT NOT NULL DEFAULT 1,
  `instrucao`        TEXT NOT NULL,
  `icone`            VARCHAR(100) DEFAULT 'turn-right',
  `distancia_m`      INT NOT NULL DEFAULT 0,
  `acessivel`        TINYINT(1) NOT NULL DEFAULT 1,
  `alerta_obstaculo` TEXT DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_rp_rota` (`rota_id`),
  CONSTRAINT `fk_rp_rota` FOREIGN KEY (`rota_id`)
    REFERENCES `rotas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 20. TABELA: trechos_viarios
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `trechos_viarios`;
CREATE TABLE `trechos_viarios` (
  `id`                INT NOT NULL AUTO_INCREMENT,
  `nome_rua`          VARCHAR(200) NOT NULL,
  `bairro`            VARCHAR(100) NOT NULL,
  `piso_tatil`        TINYINT(1) NOT NULL DEFAULT 0,
  `rampas`            TINYINT(1) NOT NULL DEFAULT 0,
  `semaforos_sonoros` TINYINT(1) NOT NULL DEFAULT 0,
  `calcada_nivelada`  TINYINT(1) NOT NULL DEFAULT 0,
  `qualidade_calcada` VARCHAR(50) NOT NULL DEFAULT 'regular' COMMENT 'boa | regular | ruim',
  `geometria`         JSON DEFAULT NULL,
  `updated_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tv_bairro` (`bairro`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 21. TABELA: navegacoes
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `navegacoes`;
CREATE TABLE `navegacoes` (
  `id`               INT NOT NULL AUTO_INCREMENT,
  `user_id`          INT NOT NULL,
  `rota_id`          INT NOT NULL,
  `status`           VARCHAR(50) NOT NULL DEFAULT 'iniciada' COMMENT 'iniciada | recalculada | finalizada | cancelada',
  `passo_atual`      INT NOT NULL DEFAULT 0,
  `recalculos_count` INT NOT NULL DEFAULT 0,
  `iniciada_em`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `finalizada_em`    DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_nav_user` (`user_id`),
  KEY `idx_nav_rota` (`rota_id`),
  CONSTRAINT `fk_nav_user` FOREIGN KEY (`user_id`)
    REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_nav_rota` FOREIGN KEY (`rota_id`)
    REFERENCES `rotas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 22. TABELA: solicitacoes
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `solicitacoes`;
CREATE TABLE `solicitacoes` (
  `id`             INT NOT NULL AUTO_INCREMENT,
  `protocol`       VARCHAR(30) NOT NULL,
  `citizen_id`     INT DEFAULT NULL,
  `citizen_name`   VARCHAR(120) NOT NULL,
  `citizen_email`  VARCHAR(200) DEFAULT NULL,
  `citizen_phone`  VARCHAR(30) DEFAULT NULL,
  `citizen_avatar` VARCHAR(255) DEFAULT 'assets/images/avatar.jpg',
  `pcd_category`   VARCHAR(150) DEFAULT NULL,
  `badges`         JSON DEFAULT NULL COMMENT 'JSON array',
  `type_key`       VARCHAR(50) NOT NULL,
  `type_name`      VARCHAR(100) DEFAULT NULL,
  `type_icon`      VARCHAR(255) DEFAULT NULL,
  `title`          VARCHAR(200) NOT NULL,
  `address`        VARCHAR(255) NOT NULL,
  `region`         VARCHAR(80) DEFAULT NULL,
  `region_name`    VARCHAR(100) DEFAULT NULL,
  `complement`     VARCHAR(255) DEFAULT NULL,
  `cep`            VARCHAR(9) DEFAULT NULL,
  `bairro`         VARCHAR(100) DEFAULT NULL,
  `cidade`         VARCHAR(100) DEFAULT NULL,
  `estado`         CHAR(2) DEFAULT NULL,
  `latitude`       DECIMAL(10,7) DEFAULT NULL,
  `longitude`      DECIMAL(10,7) DEFAULT NULL,
  `description`    TEXT DEFAULT NULL,
  `photo`          VARCHAR(255) DEFAULT NULL,
  `audio_url`      VARCHAR(255) DEFAULT NULL,
  `status`         VARCHAR(30) NOT NULL DEFAULT 'pending'
                   COMMENT 'pending | analyzing | approved | rejected',
  `status_name`    VARCHAR(80) DEFAULT 'Pendente de Triagem',
  `priority`       VARCHAR(20) NOT NULL DEFAULT 'media'
                   COMMENT 'urgente | alta | media | baixa',
  `priority_name`  VARCHAR(50) DEFAULT 'Média',
  `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sol_protocol` (`protocol`),
  KEY `idx_sol_status` (`status`),
  KEY `idx_sol_priority` (`priority`),
  KEY `idx_sol_region` (`region`),
  KEY `idx_sol_citizen` (`citizen_id`),
  KEY `idx_sol_coords` (`latitude`, `longitude`),
  CONSTRAINT `fk_sol_citizen` FOREIGN KEY (`citizen_id`)
    REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 23. TABELA: ordens_servico
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `ordens_servico`;
CREATE TABLE `ordens_servico` (
  `id`                 INT NOT NULL AUTO_INCREMENT,
  `solicitacao_id`     INT NOT NULL,
  `gestor_id`          INT DEFAULT NULL,
  `numero_os`          VARCHAR(50) NOT NULL,
  `orgao_responsavel`  VARCHAR(150) NOT NULL,
  `fiscal_responsavel` VARCHAR(150) NOT NULL,
  `prioridade`         VARCHAR(50) NOT NULL DEFAULT 'media',
  `parecer`            TEXT NOT NULL,
  `status`             VARCHAR(50) NOT NULL DEFAULT 'aberta' COMMENT 'aberta | em_execucao | concluida',
  `data_despacho`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_os_numero` (`numero_os`),
  KEY `idx_os_sol` (`solicitacao_id`),
  KEY `idx_os_gestor` (`gestor_id`),
  CONSTRAINT `fk_os_sol` FOREIGN KEY (`solicitacao_id`)
    REFERENCES `solicitacoes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_os_gestor` FOREIGN KEY (`gestor_id`)
    REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 24. TABELA: relatorios_gerados
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `relatorios_gerados`;
CREATE TABLE `relatorios_gerados` (
  `id`             INT NOT NULL AUTO_INCREMENT,
  `gestor_id`      INT DEFAULT NULL,
  `titulo`         VARCHAR(200) NOT NULL,
  `tipo_relatorio` VARCHAR(100) NOT NULL,
  `periodo_inicio` DATE DEFAULT NULL,
  `periodo_fim`    DATE DEFAULT NULL,
  `parametros`     JSON DEFAULT NULL,
  `arquivo_id`     INT DEFAULT NULL,
  `formato`        VARCHAR(20) NOT NULL DEFAULT 'PDF',
  `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_rg_gestor` (`gestor_id`),
  CONSTRAINT `fk_rg_gestor` FOREIGN KEY (`gestor_id`)
    REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_rg_arquivo` FOREIGN KEY (`arquivo_id`)
    REFERENCES `arquivos` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 25. TABELA: comunicados
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `comunicados`;
CREATE TABLE `comunicados` (
  `id`                  INT NOT NULL AUTO_INCREMENT,
  `gestor_id`           INT DEFAULT NULL,
  `titulo`              VARCHAR(200) NOT NULL,
  `mensagem`            TEXT NOT NULL,
  `tipo`                VARCHAR(50) NOT NULL DEFAULT 'comunicado',
  `publico_alvo`        VARCHAR(100) NOT NULL DEFAULT 'todos',
  `total_destinatarios` INT NOT NULL DEFAULT 0,
  `total_entregues`     INT NOT NULL DEFAULT 0,
  `total_lidos`         INT NOT NULL DEFAULT 0,
  `enviado_em`          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_com_gestor` (`gestor_id`),
  CONSTRAINT `fk_com_gestor` FOREIGN KEY (`gestor_id`)
    REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 26. TABELA: comunicado_canais
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `comunicado_canais`;
CREATE TABLE `comunicado_canais` (
  `id`            INT NOT NULL AUTO_INCREMENT,
  `comunicado_id` INT NOT NULL,
  `canal`         VARCHAR(50) NOT NULL COMMENT 'app | email | sms | push',
  `status_envio`  VARCHAR(50) NOT NULL DEFAULT 'enviado',
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cc_comunicado` (`comunicado_id`),
  CONSTRAINT `fk_cc_comunicado` FOREIGN KEY (`comunicado_id`)
    REFERENCES `comunicados` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 27. TABELA: logs_auditoria
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `logs_auditoria`;
CREATE TABLE `logs_auditoria` (
  `id`            INT NOT NULL AUTO_INCREMENT,
  `user_id`       INT DEFAULT NULL,
  `operator_name` VARCHAR(120) NOT NULL,
  `operator_role` VARCHAR(100) DEFAULT NULL,
  `action_module` VARCHAR(50) NOT NULL,
  `action_title`  VARCHAR(200) NOT NULL,
  `action_desc`   TEXT DEFAULT NULL,
  `risk_level`    VARCHAR(20) NOT NULL DEFAULT 'baixo'
                  COMMENT 'baixo | medio | alto | critico',
  `ip_address`    VARCHAR(45) DEFAULT '127.0.0.1',
  `location_desc` VARCHAR(100) DEFAULT 'Suzano, SP — Brasil',
  `hash_sha256`   VARCHAR(64) DEFAULT NULL,
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_audit_module` (`action_module`),
  KEY `idx_audit_risk` (`risk_level`),
  KEY `idx_audit_date` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- View de compatibilidade para código legado que consulta `auditoria`
DROP VIEW IF EXISTS `auditoria`;
DROP TABLE IF EXISTS `auditoria`;
CREATE OR REPLACE VIEW `auditoria` AS SELECT * FROM `logs_auditoria`;

-- -----------------------------------------------------------------------------
-- 28. TABELA: integracoes
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `integracoes`;
CREATE TABLE `integracoes` (
  `id`            INT NOT NULL AUTO_INCREMENT,
  `nome`          VARCHAR(150) NOT NULL,
  `slug`          VARCHAR(100) NOT NULL,
  `tipo`          VARCHAR(50) NOT NULL,
  `endpoint_url`  VARCHAR(255) NOT NULL,
  `api_key`       VARCHAR(255) DEFAULT NULL,
  `status`        VARCHAR(50) NOT NULL DEFAULT 'ativo' COMMENT 'ativo | inativo | instavel',
  `configuracoes` JSON DEFAULT NULL,
  `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_int_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 29. TABELA: integracao_logs
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `integracao_logs`;
CREATE TABLE `integracao_logs` (
  `id`            INT NOT NULL AUTO_INCREMENT,
  `integracao_id` INT NOT NULL,
  `metodo`        VARCHAR(10) NOT NULL DEFAULT 'GET',
  `endpoint`      VARCHAR(255) NOT NULL,
  `status_http`   INT NOT NULL DEFAULT 200,
  `sucesso`       TINYINT(1) NOT NULL DEFAULT 1,
  `payload_envio` TEXT DEFAULT NULL,
  `resposta`      TEXT DEFAULT NULL,
  `tempo_ms`      INT NOT NULL DEFAULT 120,
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_il_integracao` (`integracao_id`),
  CONSTRAINT `fk_il_integracao` FOREIGN KEY (`integracao_id`)
    REFERENCES `integracoes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 30. TABELA: usuario_papeis
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `usuario_papeis`;
CREATE TABLE `usuario_papeis` (
  `id`           INT NOT NULL AUTO_INCREMENT,
  `user_id`      INT NOT NULL,
  `papel`        VARCHAR(50) NOT NULL COMMENT 'gestor | fiscal | atendente | cidadao',
  `departamento` VARCHAR(100) DEFAULT 'Secretaria de Transporte e Mobilidade Urbana',
  `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_up_user` (`user_id`),
  CONSTRAINT `fk_up_user` FOREIGN KEY (`user_id`)
    REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 31. TABELA: papel_permissoes
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `papel_permissoes`;
CREATE TABLE `papel_permissoes` (
  `id`             INT NOT NULL AUTO_INCREMENT,
  `papel`          VARCHAR(50) NOT NULL,
  `modulo`         VARCHAR(50) NOT NULL,
  `pode_ler`       TINYINT(1) NOT NULL DEFAULT 1,
  `pode_criar`     TINYINT(1) NOT NULL DEFAULT 0,
  `pode_editar`    TINYINT(1) NOT NULL DEFAULT 0,
  `pode_excluir`   TINYINT(1) NOT NULL DEFAULT 0,
  `pode_despachar` TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_papel_mod` (`papel`, `modulo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 32. TABELA: configuracoes
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `configuracoes`;
CREATE TABLE `configuracoes` (
  `id`           INT NOT NULL AUTO_INCREMENT,
  `config_key`   VARCHAR(80) NOT NULL,
  `config_value` TEXT NOT NULL,
  `config_group` VARCHAR(50) NOT NULL DEFAULT 'geral',
  `label`        VARCHAR(200) DEFAULT NULL,
  `updated_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cfg_key` (`config_key`),
  KEY `idx_cfg_group` (`config_group`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 33. TABELA: notificacoes
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `notificacoes`;
CREATE TABLE `notificacoes` (
  `id`         INT NOT NULL AUTO_INCREMENT,
  `user_id`    INT NOT NULL,
  `sender_id`  INT DEFAULT NULL COMMENT 'ID do gestor/administrador que disparou a mensagem',
  `title`      VARCHAR(200) NOT NULL,
  `body`       TEXT DEFAULT NULL,
  `type`       VARCHAR(20) NOT NULL DEFAULT 'info'
               COMMENT 'info | warning | alert | success | gestor | urgente | laudo | sistema',
  `is_read`    TINYINT(1) NOT NULL DEFAULT 0,
  `read_at`    DATETIME DEFAULT NULL COMMENT 'Timestamp de quando a notificação foi lida',
  `local_code` VARCHAR(30) DEFAULT NULL,
  `link`       VARCHAR(255) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_notif_user` (`user_id`, `is_read`),
  KEY `idx_notif_user_created` (`user_id`, `created_at`),
  CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`)
    REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- VIEWS ESPECIAIS
-- -----------------------------------------------------------------------------

-- View: "Suzano em Dados" (Agregações para mapa e indicadores do município)
DROP VIEW IF EXISTS `view_suzano_em_dados`;
CREATE OR REPLACE VIEW `view_suzano_em_dados` AS
SELECT
  (SELECT COUNT(*) FROM `locais` WHERE `is_archived` = 0) AS total_locais,
  (SELECT COUNT(*) FROM `locais` WHERE `access_status` = 'verified' AND `is_archived` = 0) AS locais_verificados,
  (SELECT COUNT(*) FROM `locais` WHERE `access_status` = 'partial' AND `is_archived` = 0) AS locais_parciais,
  (SELECT COUNT(*) FROM `trechos_viarios` WHERE `piso_tatil` = 1) AS trechos_piso_tatil,
  (SELECT COUNT(*) FROM `trechos_viarios` WHERE `rampas` = 1) AS trechos_rampas,
  (SELECT COUNT(*) FROM `trechos_viarios` WHERE `semaforos_sonoros` = 1) AS trechos_semaforos_sonoros,
  (SELECT COUNT(*) FROM `ocorrencias` WHERE `status` = 'ativa') AS ocorrencias_ativas,
  (SELECT COUNT(*) FROM `solicitacoes` WHERE `status` = 'pending') AS solicitacoes_pendentes;

-- View: Resumo das solicitações por status e prioridade
DROP VIEW IF EXISTS `view_solicitacoes_resumo`;
CREATE OR REPLACE VIEW `view_solicitacoes_resumo` AS
SELECT
  `status`,
  `status_name`,
  COUNT(*) AS total,
  SUM(CASE WHEN `priority` = 'urgente' THEN 1 ELSE 0 END) AS total_urgentes,
  SUM(CASE WHEN `priority` = 'alta' THEN 1 ELSE 0 END) AS total_altas
FROM `solicitacoes`
GROUP BY `status`, `status_name`;

-- -----------------------------------------------------------------------------
-- TRIGGERS
-- -----------------------------------------------------------------------------
DROP TRIGGER IF EXISTS `trg_after_avaliacao_insert`;
DELIMITER $$
CREATE TRIGGER `trg_after_avaliacao_insert`
AFTER INSERT ON `avaliacoes`
FOR EACH ROW
BEGIN
  IF NEW.local_id IS NOT NULL THEN
    UPDATE `locais`
    SET
      `reviews_count` = (SELECT COUNT(*) FROM `avaliacoes` WHERE `local_id` = NEW.local_id),
      `rating` = ROUND((SELECT AVG(`rating`) FROM `avaliacoes` WHERE `local_id` = NEW.local_id), 1)
    WHERE `id` = NEW.local_id;
  END IF;
END$$
DELIMITER ;

DROP TRIGGER IF EXISTS `trg_after_ocorrencia_insert`;
DELIMITER $$
CREATE TRIGGER `trg_after_ocorrencia_insert`
AFTER INSERT ON `ocorrencias`
FOR EACH ROW
BEGIN
  IF NEW.local_id IS NOT NULL THEN
    UPDATE `locais`
    SET `incidents_count` = `incidents_count` + 1
    WHERE `id` = NEW.local_id;
  END IF;
END$$
DELIMITER ;

-- =============================================================================
-- SEED DATA (DADOS INICIAIS)
-- =============================================================================

-- Senhas geradas com bcrypt (custo 10):
-- '123456'   -> $2y$10$DW1F87mv/nkg25jRxXUezeeKz8c8d3NDA0jfCldF3vPi6Gh3rYJRK
-- 'admin123' -> $2y$10$u3/PXAboMfm9Ooaz1PdzqOUGQbwgULW.FMbtQaYuhPMQNmzbzmeIu

INSERT INTO `usuarios`
  (`id`, `user_code`, `name`, `email`, `password_hash`, `phone`, `cpf`, `gender`, `city`, `bairro`, `address`, `description`, `role`, `is_admin`, `avatar`, `initials`, `status`, `email_verified`, `pcd_categories`, `saved_prefs`, `is_google`, `created_at`)
VALUES
  (1, 'USR-2026-001', 'João Silva', 'joao.silva@gmail.com', '$2y$10$DW1F87mv/nkg25jRxXUezeeKz8c8d3NDA0jfCldF3vPi6Gh3rYJRK', '(11) 98765-4321', '123.456.789-00', 'Masculino', 'Suzano - SP', 'Centro', 'Rua General Francisco Glicério, 450', 'Cadeirante, uso diário de rotas acessíveis', 'Cidadão Conectado', 0, 'assets/images/avatar.jpg', 'JS', 'ativo', 1, '["Física/motora"]', '["cadeirante","rampas","calcadas_niveladas"]', 1, '2026-01-15 09:30:00'),
  (2, 'ADM-2026-007', 'Carlos Eduardo', 'carlos.eduardo@suzano.sp.gov.br', '$2y$10$u3/PXAboMfm9Ooaz1PdzqOUGQbwgULW.FMbtQaYuhPMQNmzbzmeIu', '(11) 3402-0100', '234.567.890-11', 'Masculino', 'Suzano - SP', 'Vila Amorim', 'Rua Baruel, 501', 'Gestor Municipal de Mobilidade Urbana', 'Administrador Municipal', 1, 'assets/images/avatar-admin.jpg', 'CE', 'ativo', 1, '[]', '[]', 0, '2026-01-01 08:00:00'),
  (3, 'USR-2026-002', 'Mariana Souza', 'mariana.souza@email.com', '$2y$10$DW1F87mv/nkg25jRxXUezeeKz8c8d3NDA0jfCldF3vPi6Gh3rYJRK', '(11) 98765-4321', '345.678.901-22', 'Feminino', 'Suzano - SP', 'Jardim Imperador', 'Av. Armando Salles de Oliveira, 200', 'Deficiência visual com cão-guia', 'Cidadã Verificada', 0, 'assets/images/mariana-souza.jpg', 'MS', 'ativo', 1, '["Visual"]', '["piso_tatil","semaforo_sonoro"]', 0, '2026-02-10 10:00:00'),
  (4, 'USR-2026-003', 'Lucas Mendes', 'lucas.mendes@mobilidade.org', '$2y$10$DW1F87mv/nkg25jRxXUezeeKz8c8d3NDA0jfCldF3vPi6Gh3rYJRK', '(11) 91234-5678', '456.789.012-33', 'Masculino', 'Suzano - SP', 'Centro', 'Rua Benjamin Constant, 120', 'Fiscal Voluntário de Acessibilidade', 'Fiscal Cidadão', 0, 'assets/images/avatar.jpg', 'LM', 'ativo', 1, '["Auditiva"]', '["libras","sinalizacao_visual"]', 0, '2026-02-15 14:00:00'),
  (5, 'USR-2026-004', 'Beatriz Castro', 'b.castro@suzano.sp.gov.br', '$2y$10$DW1F87mv/nkg25jRxXUezeeKz8c8d3NDA0jfCldF3vPi6Gh3rYJRK', '(11) 3402-0200', '567.890.123-44', 'Feminino', 'Suzano - SP', 'Parque Suzano', 'Av. Brasil, 800', 'Engenheira de Tráfego e Obras', 'Servidora Municipal', 1, 'assets/images/avatar.jpg', 'BC', 'ativo', 1, '[]', '[]', 0, '2026-02-20 11:30:00'),
  (6, 'USR-2026-005', 'Roberto Almeida', 'roberto.almeida@gmail.com', '$2y$10$DW1F87mv/nkg25jRxXUezeeKz8c8d3NDA0jfCldF3vPi6Gh3rYJRK', '(11) 97654-3210', '678.901.234-55', 'Masculino', 'Suzano - SP', 'Vila Urupês', 'Rua Prudente de Moraes, 330', 'Idoso com mobilidade reduzida temporária', 'Cidadão Conectado', 0, 'assets/images/avatar.jpg', 'RA', 'ativo', 1, '["Mobilidade Reduzida"]', '["sem_escadas","tempo_semaforo_estendido"]', 0, '2026-03-01 16:00:00');

-- 4. Inserção de Necessidades PcD dos usuários
INSERT INTO `usuario_necessidades` (`user_id`, `categoria_pcd`, `descricao`) VALUES
  (1, 'Física/motora', 'Uso permanente de cadeira de rodas motorizada'),
  (3, 'Visual', 'Baixa visão severa, dependência de piso tátil e auxílio sonoro'),
  (4, 'Auditiva', 'Perda auditiva bilateral profunda, comunicação em Libras'),
  (6, 'Mobilidade Reduzida', 'Dificuldade de locomoção e equilíbrio com degraus');

-- 5. Inserção de Consentimentos LGPD
INSERT INTO `usuario_consentimentos` (`user_id`, `tipo_consentimento`, `aceito`, `ip_address`) VALUES
  (1, 'termos_uso', 1, '127.0.0.1'),
  (1, 'privacidade_lgpd', 1, '127.0.0.1'),
  (2, 'termos_uso', 1, '127.0.0.1'),
  (3, 'termos_uso', 1, '127.0.0.1'),
  (3, 'privacidade_lgpd', 1, '127.0.0.1'),
  (4, 'termos_uso', 1, '127.0.0.1'),
  (5, 'termos_uso', 1, '127.0.0.1'),
  (6, 'termos_uso', 1, '127.0.0.1');

-- 7. Identidade externa Google
INSERT INTO `usuario_identidades_externas` (`user_id`, `provider`, `provider_user_id`, `email`, `dados_adicionais`) VALUES
  (1, 'google', 'google-oauth2|109283746501928374650', 'joao.silva@gmail.com', '{"hd": null, "locale": "pt-BR"}');

-- 8. Preferências de Busca
INSERT INTO `usuario_preferencias_busca` (`user_id`, `raio_km`, `nivel_acessibilidade`, `recursos`, `usar_preferencias_salvas`) VALUES
  (1, 3.5, 'verified', '["rampas","calcadas_niveladas","banheiro_adaptado"]', 1),
  (3, 2.0, 'verified', '["piso_tatil","semaforo_sonoro","braille"]', 1),
  (4, 5.0, 'todos', '["libras","sinalizacao_visual"]', 1),
  (6, 1.5, 'partial', '["sem_escadas","elevador","apoio_braco"]', 1);

-- 10. Locais cadastrados
INSERT INTO `locais`
  (`id`, `local_code`, `name`, `category`, `category_key`, `address`, `bairro`, `access_status`, `access_text`, `rating`, `reviews_count`, `image`, `lat`, `lng`, `features`, `opening_hours`, `bus_lines`, `useful_count`, `last_audit`, `avatar_color`, `avatar_icon`, `incidents_count`)
VALUES
  (1, 'LOC-001', 'Estação CPTM Suzano (Linha 11-Coral)', 'Transporte Público', 'transporte', 'Rua Prudente de Moraes, s/n', 'Centro', 'verified', 'Totalmente Acessível', 4.8, 124, 'assets/images/map-background.jpg', -23.5325, -46.3112, '["rampas","elevador","piso_tatil","banheiro_adaptado","embarque_nivel"]', 'Todos os dias: 04:00 - 00:00', 'Linha 11-Coral CPTM, Linhas Municipais 01TR, 02TR, 14TR', 87, '28/08/2026', 'blue', 'assets/icons/routes.svg', 0),
  (2, 'LOC-002', 'Terminal de Ônibus Ayrton Senna', 'Transporte Público', 'transporte', 'Av. Brasil, 500', 'Parque Suzano', 'verified', 'Totalmente Acessível', 4.5, 89, 'assets/images/terminal-ayrton-senna.jpg', -23.5358, -46.3089, '["rampas","piso_tatil","sinalizacao_sonora","banheiro_adaptado"]', 'Todos os dias: 04:30 - 23:30', 'Terminal Central de todas as linhas municipais', 64, '15/08/2026', 'blue', 'assets/icons/routes.svg', 1),
  (3, 'LOC-003', 'Suzano Shopping', 'Comércio & Serviços', 'comercio', 'Rua Sete de Setembro, 555', 'Jardim Iraí', 'verified', 'Totalmente Acessível', 4.7, 210, 'assets/images/shopping-suzano.jpg', -23.5412, -46.3165, '["rampas","elevador","vagas_pcd","banheiro_adaptado","cadeira_emprestimo"]', 'Segunda a Sábado: 10:00 - 22:00, Domingo: 14:00 - 20:00', 'Linha 04TR, 08TR', 112, '02/09/2026', 'green', 'assets/icons/pin.svg', 0),
  (4, 'LOC-004', 'Prefeitura Municipal de Suzano', 'Órgão Público', 'publico', 'Rua Baruel, 501', 'Vila Amorim', 'verified', 'Totalmente Acessível', 4.3, 45, 'assets/images/map-background.jpg', -23.5380, -46.3130, '["rampas","elevador","piso_tatil","atendimento_prioritario"]', 'Segunda a Sexta: 08:00 - 17:00', 'Linha 02TR, 03TR', 39, '10/07/2026', 'orange', 'assets/icons/shield.svg', 0),
  (5, 'LOC-005', 'Parque Municipal Max Feffer', 'Parque & Lazer', 'parque', 'Av. Senador Roberto Simonsen, 90', 'Jardim Imperador', 'verified', 'Totalmente Acessível', 4.9, 340, 'assets/images/map-background.jpg', -23.5290, -46.3190, '["pistas_niveladas","banheiro_adaptado","estacionamento_pcd","brinquedos_inclusivos"]', 'Terça a Domingo: 06:00 - 18:00', 'Linha 05TR, 15TR', 145, '20/08/2026', 'green', 'assets/icons/pin.svg', 0),
  (6, 'LOC-006', 'Hospital das Clínicas de Suzano', 'Saúde & Emergência', 'saude', 'Av. Antônio Marques Figueira, 1861', 'Vila Figueira', 'verified', 'Totalmente Acessível', 4.2, 78, 'assets/images/map-background.jpg', -23.5460, -46.3050, '["rampas_normatizadas","elevador_maca","banheiro_adaptado","vagas_ambulancia"]', 'Pronto-Socorro 24 horas', 'Linha 03TR, 11TR', 92, '05/06/2026', 'red', 'assets/icons/support.svg', 0),
  (7, 'LOC-007', 'SENAI Suzano — Escola Luiza Maria Alves', 'Educação', 'educacao', 'Av. Senador Roberto Simonsen, 550', 'Jardim Imperador', 'verified', 'Totalmente Acessível', 4.9, 156, 'assets/images/map-background.jpg', -23.5310, -46.3175, '["rampas","elevador","piso_tatil","banheiro_adaptado","laboratorio_inclusivo"]', 'Segunda a Sexta: 07:30 - 22:00, Sábado: 08:00 - 17:00', 'Linha 05TR', 130, '01/09/2026', 'blue', 'assets/icons/shield.svg', 0),
  (8, 'LOC-008', 'Câmara Municipal de Suzano', 'Órgão Público', 'publico', 'Rua dos Três Poderes, 65', 'Jardim Rosa de França', 'partial', 'Acessibilidade Parcial', 3.4, 22, 'assets/images/map-background.jpg', -23.5365, -46.3145, '["rampa_acesso","elevador"]', 'Segunda a Sexta: 08:00 - 17:00', 'Linha 02TR', 18, '18/03/2026', 'yellow', 'assets/icons/shield.svg', 1);

-- 11. Recursos detalhados dos Locais
INSERT INTO `local_recursos` (`local_id`, `recurso_key`, `nome_recurso`, `status`, `detalhes`) VALUES
  (1, 'rampas', 'Rampa de acesso à plataforma', 'disponivel', 'Inclinação normatizada ABNT NBR 9050 com corrimão duplo'),
  (1, 'elevador', 'Elevador panorâmico de acesso', 'disponivel', 'Com aviso sonoro e botoeira em Braille'),
  (1, 'piso_tatil', 'Piso tátil direcional e de alerta', 'disponivel', 'Contínuo desde a entrada até a linha de bloqueio'),
  (1, 'banheiro_adaptado', 'Sanitário PcD Unissex', 'disponivel', 'Com barra de apoio e alarme de emergência'),
  (2, 'rampas', 'Rampa de transbordo', 'disponivel', 'Conecta todas as 4 plataformas de embarque'),
  (2, 'piso_tatil', 'Piso tátil integrado', 'disponivel', 'Guia o usuário até as baias de ônibus adaptados'),
  (2, 'sinalizacao_sonora', 'Painel com chamada por voz', 'parcial', 'Em manutenção preventiva na plataforma 2'),
  (3, 'rampas', 'Acessos nivelados', 'disponivel', 'Todas as entradas com portas automáticas'),
  (3, 'cadeira_emprestimo', 'Cadeiras de rodas motorizadas e manuais', 'disponivel', 'Disponíveis no balcão de informações'),
  (8, 'rampa_acesso', 'Rampa principal', 'parcial', 'Inclinação de 9%, acima dos 8,33% recomendados pela NBR 9050'),
  (8, 'banheiro_adaptado', 'Sanitário acessível', 'indisponivel', 'Em reforma estrutural');

-- 12. Avaliações
INSERT INTO `avaliacoes`
  (`id`, `local_id`, `local_code`, `user_id`, `user_name`, `entrada_status`, `rampas_status`, `elevadores_status`, `banheiros_status`, `calcadas_status`, `estacionamento_status`, `barrier_desc`, `photos`, `rating`, `verified`, `is_useful_count`, `created_at`)
VALUES
  (1, 1, 'LOC-001', 1, 'João Silva', 'disponivel', 'disponivel', 'disponivel', 'disponivel', 'disponivel', 'disponivel', NULL, '["assets/images/rampa-interna.jpg"]', 5.0, 1, 14, '2026-08-30 14:22:00'),
  (2, 2, 'LOC-002', 3, 'Mariana Souza', 'disponivel', 'disponivel', 'parcial', 'disponivel', 'disponivel', 'disponivel', 'Sinalizador sonoro baixo no terminal oeste.', '[]', 4.0, 1, 9, '2026-09-01 10:15:00'),
  (3, 8, 'LOC-008', 1, 'João Silva', 'parcial', 'parcial', 'disponivel', 'indisponivel', 'parcial', 'disponivel', 'A rampa externa exige muito esforço e o banheiro estava trancado para manutenção.', '["assets/images/semaforo-evidencia.jpg"]', 2.5, 1, 18, '2026-09-02 11:40:00');

-- 13. Critérios de Avaliação
INSERT INTO `avaliacao_criterios` (`avaliacao_id`, `criterio_key`, `nota`, `comentario`) VALUES
  (1, 'rampas', 5, 'Rampa excelente, inclinação suave e corrimão duplo perfeito.'),
  (1, 'banheiros', 5, 'Banheiro amplo com alarme sonoro funcional.'),
  (3, 'rampas', 3, 'Rampa íngreme demais para cadeira manual.'),
  (3, 'banheiros', 1, 'Banheiro interditado no dia da visita.');

-- 16. Ocorrências (Barreiras e Obstáculos Urbanos)
INSERT INTO `ocorrencias`
  (`id`, `user_id`, `local_id`, `protocolo`, `titulo`, `descricao`, `categoria`, `status`, `gravidade`, `lat`, `lng`, `endereco`, `foto_url`, `reporter_nome`, `tempo_decorrido`, `rota_alternativa_sugerida`, `escolha_acao`, `created_at`)
VALUES
  (1, 3, 2, 'OCO-2026-0081', 'Semáforo Sonoro Inoperante', 'O botão sonoro de travessia para deficientes visuais não emite sinal sonoro há 3 dias.', 'Sinalização', 'ativa', 'urgente', -23.5358, -46.3089, 'Av. Brasil cruzamento com Av. Armando Salles', 'assets/images/semaforo-evidencia.jpg', 'Mariana Souza', 'Há 2 horas', 'Utilizar a faixa de pedestres da Rua Benjamin Constant com semáforo monitorado', 'desviar', '2026-10-02 13:10:00'),
  (2, 1, 8, 'OCO-2026-0082', 'Calçada com Desnível e Raiz Exposta', 'Raiz de árvore rompeu o calçamento e bloqueou passagem de cadeira de rodas.', 'Pavimentação', 'ativa', 'alta', -23.5370, -46.3140, 'Rua Baruel, altura do nº 420', 'assets/images/map-background.jpg', 'João Silva', 'Há 1 dia', 'Acessar pela Rua Presidente Nereu Ramos', 'desviar', '2026-10-01 16:30:00'),
  (3, 4, NULL, 'OCO-2026-0083', 'Obra bloqueando rampa de esquina', 'Caçamba de entulho colocada exatamente na rampa de rebaixamento de guia.', 'Bloqueio de Rampa', 'resolvida', 'media', -23.5330, -46.3120, 'Rua General Francisco Glicério com Rua Monsenhor Nuno', 'assets/images/map-background.jpg', 'Lucas Mendes', 'Resolvida', NULL, 'continuar', '2026-09-28 09:00:00');

-- 20. Trechos Viários de Suzano
INSERT INTO `trechos_viarios`
  (`id`, `nome_rua`, `bairro`, `piso_tatil`, `rampas`, `semaforos_sonoros`, `calcada_nivelada`, `qualidade_calcada`, `geometria`)
VALUES
  (1, 'Rua General Francisco Glicério', 'Centro', 1, 1, 1, 1, 'boa', '{"type":"LineString","coordinates":[[-46.3112,-23.5325],[-46.3130,-23.5350]]}'),
  (2, 'Rua Benjamin Constant', 'Centro', 1, 1, 1, 1, 'boa', '{"type":"LineString","coordinates":[[-46.3120,-23.5330],[-46.3140,-23.5360]]}'),
  (3, 'Rua Baruel', 'Vila Amorim', 1, 1, 0, 0, 'regular', '{"type":"LineString","coordinates":[[-46.3130,-23.5380],[-46.3150,-23.5410]]}'),
  (4, 'Av. Senador Roberto Simonsen', 'Jardim Imperador', 1, 1, 1, 1, 'boa', '{"type":"LineString","coordinates":[[-46.3175,-23.5310],[-46.3190,-23.5290]]}'),
  (5, 'Av. Armando Salles de Oliveira', 'Jardim Imperador', 0, 1, 0, 0, 'regular', '{"type":"LineString","coordinates":[[-46.3089,-23.5358],[-46.3165,-23.5412]]}');

-- 22. Solicitações de Intervenção
INSERT INTO `solicitacoes`
  (`id`, `protocol`, `citizen_id`, `citizen_name`, `citizen_email`, `citizen_phone`, `citizen_avatar`, `pcd_category`, `badges`, `type_key`, `type_name`, `type_icon`, `title`, `address`, `region`, `region_name`, `complement`, `description`, `photo`, `audio_url`, `status`, `status_name`, `priority`, `priority_name`, `created_at`)
VALUES
  (1, 'SOL-2026-0891', 3, 'Mariana Souza', 'mariana.souza@email.com', '(11) 98765-4321', 'assets/images/mariana-souza.jpg', 'Visual', '["Laudo Aprovado","Deficiência Visual"]', 'semaforo', 'Semáforo Sonoro', 'assets/icons/traffic-light.svg', 'Semáforo Sonoro Inoperante', 'Av. Armando Salles, 840', 'centro', 'Região Central', 'Próximo ao Hospital Santa Maria', 'O semáforo sonoro não emite o sinal na travessia de pedestres, colocando pessoas com deficiência visual em alto risco de atropelamento.', 'assets/images/semaforo-evidencia.jpg', NULL, 'pending', 'Pendente de Triagem', 'urgente', 'Urgente', '2026-09-03 08:30:00'),
  (2, 'SOL-2026-0887', 1, 'João Silva', 'joao.silva@gmail.com', '(11) 98765-4321', 'assets/images/avatar.jpg', 'Física/motora', '["Laudo Aprovado","Cadeirante"]', 'rampa', 'Rampa de Acesso', 'assets/icons/ramp.svg', 'Rampa com Degrau Rompido', 'Rua General Francisco Glicério, 450', 'centro', 'Região Central', 'Em frente à farmácia municipal', 'A rampa de acesso está quebrada com desnível de mais de 8cm, impossibilitando cadeirantes de subirem até a calçada.', 'assets/images/rampa-interna.jpg', NULL, 'pending', 'Pendente de Triagem', 'alta', 'Alta', '2026-09-02 14:15:00'),
  (3, 'SOL-2026-0882', 4, 'Lucas Mendes', 'lucas.mendes@mobilidade.org', '(11) 91234-5678', 'assets/images/avatar.jpg', 'Auditiva', '["Fiscal Cidadão"]', 'piso_tatil', 'Piso Tátil', 'assets/icons/tactile-paving.svg', 'Piso Tátil Interrompido', 'Rua Benjamin Constant, 120', 'centro', 'Região Central', 'Esquina com Rua Campos Salles', 'Piso tátil direcional termina bruscamente em um poste metálico, causando risco de colisão.', 'assets/images/map-background.jpg', NULL, 'analyzing', 'Em Análise', 'alta', 'Alta', '2026-08-29 11:00:00'),
  (4, 'SOL-2026-0879', 6, 'Roberto Almeida', 'roberto.almeida@gmail.com', '(11) 97654-3210', 'assets/images/avatar.jpg', 'Mobilidade Reduzida', '["Idoso Conectado"]', 'calcada', 'Calçada Inacessível', 'assets/icons/traffic-light.svg', 'Buraco e desnível na calçada', 'Rua Baruel, 700', 'sul', 'Região Sul', 'Próximo ao posto de saúde', 'Calçada destruída pelas chuvas, idosos e cadeirantes precisam disputar espaço com carros no asfalto.', 'assets/images/map-background.jpg', NULL, 'analyzing', 'Em Análise', 'media', 'Média', '2026-08-28 09:40:00'),
  (5, 'SOL-2026-0875', 1, 'João Silva', 'joao.silva@gmail.com', '(11) 98765-4321', 'assets/images/avatar.jpg', 'Física/motora', '["Laudo Aprovado"]', 'transporte', 'Ponto de Ônibus', 'assets/icons/routes.svg', 'Ponto de Ônibus sem Plataforma Elevada', 'Av. Brasil, 1200', 'norte', 'Região Norte', 'Ponto da linha 01TR', 'O abrigo de ônibus não tem rampa de acesso, deixando o cadeirante exposto à chuva sem conseguir embarcar.', 'assets/images/map-background.jpg', NULL, 'approved', 'Aprovada', 'alta', 'Alta', '2026-08-25 16:20:00'),
  (6, 'SOL-2026-0860', 6, 'Roberto Almeida', 'roberto.almeida@gmail.com', '(11) 97654-3210', 'assets/images/avatar.jpg', 'Mobilidade Reduzida', '["Idoso"]', 'outro', 'Solicitação Geral', 'assets/icons/traffic-light.svg', 'Pintura de guia rebaixada particular', 'Rua Prudente de Moraes, 90', 'centro', 'Região Central', 'Garagem particular', 'Solicitação indeferida por se tratar de guia de garagem residencial privativa, fora do escopo de mobilidade pública.', 'assets/images/map-background.jpg', NULL, 'rejected', 'Recusada', 'baixa', 'Baixa', '2026-08-20 10:00:00');

-- 23. Ordens de Serviço
INSERT INTO `ordens_servico`
  (`id`, `solicitacao_id`, `gestor_id`, `numero_os`, `orgao_responsavel`, `fiscal_responsavel`, `prioridade`, `parecer`, `status`, `data_despacho`)
VALUES
  (1, 5, 2, 'OS-2026-0412', 'Secretaria Municipal de Manutenção e Serviços Urbanos', 'Beatriz Castro (Eng. Tráfego)', 'alta', 'Vistoria in loco confirmou a ausência de rampa de embarque. Encaminhado para adequação imediata na programação de obras do mês.', 'em_execucao', '2026-08-26 10:00:00');

-- 27. Logs de Auditoria
INSERT INTO `logs_auditoria`
  (`id`, `user_id`, `operator_name`, `operator_role`, `action_module`, `action_title`, `action_desc`, `risk_level`, `ip_address`, `location_desc`, `hash_sha256`, `created_at`)
VALUES
  (1, 2, 'Carlos Eduardo', 'Administrador Municipal', 'triagem', 'Homologação de Laudo PCD nº 0324', 'Laudo médico pericial de João Silva deferido com categoria física permanente.', 'baixo', '177.102.240.11', 'Suzano, SP — Prefeitura', 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855', '2026-09-02 09:12:00'),
  (2, 2, 'Carlos Eduardo', 'Administrador Municipal', 'solicitacoes', 'Despacho de Ordem de Serviço OS-2026-0412', 'Aprovada intervenção para rampa em ponto de ônibus da Av. Brasil', 'medio', '177.102.240.11', 'Suzano, SP — Prefeitura', '9f83c6051414fa5124b0a841c69b329a3b610fa78e1215b4c4892c10b45309', '2026-08-26 10:05:00'),
  (3, 2, 'Carlos Eduardo', 'Administrador Municipal', 'seguranca', 'Acesso Administrativo ao Painel do Gestor', 'Autenticação bem-sucedida com credenciais governamentais de dois fatores.', 'baixo', '177.102.240.11', 'Suzano, SP — Brasil', 'd5a83582e17eee8025340648a46c02f23022dec641969780f692442821e3f8b5', '2026-10-02 08:00:00');

-- 28. Integrações
INSERT INTO `integracoes` (`id`, `nome`, `slug`, `tipo`, `endpoint_url`, `api_key`, `status`, `configuracoes`) VALUES
  (1, 'SIG Suzano — Geoprocessamento Municipal', 'sig-suzano', 'sig_suzano', 'https://sig.suzano.sp.gov.br/api/v2/mobilidade', 'sig_live_99a8b7c6d5e4f3a2', 'ativo', '{"timeout": 5000, "cache_hours": 12}'),
  (2, 'OpenStreetMap / Overpass API', 'osm-overpass', 'openstreetmap', 'https://overpass-api.de/api/interpreter', NULL, 'ativo', '{"bbox": [-23.60, -46.38, -23.48, -46.25]}'),
  (3, 'SPTrans / EMTU — Monitoramento de Frotas', 'emtu-frotas', 'sp_trans', 'https://api.emtu.sp.gov.br/v1/onibus/suzano', 'emtu_token_77665544', 'ativo', '{"intervalo_seg": 30}');

-- 30. Papéis de Usuários
INSERT INTO `usuario_papeis` (`id`, `user_id`, `papel`, `departamento`) VALUES
  (1, 2, 'gestor', 'Secretaria Municipal de Transporte e Mobilidade Urbana'),
  (2, 5, 'fiscal', 'Secretaria Municipal de Manutenção e Obras'),
  (3, 4, 'fiscal', 'Conselho Municipal dos Direitos da Pessoa com Deficiência');

-- 31. Permissões de Papéis
INSERT INTO `papel_permissoes` (`papel`, `modulo`, `pode_ler`, `pode_criar`, `pode_editar`, `pode_excluir`, `pode_despachar`) VALUES
  ('gestor', 'dashboard', 1, 1, 1, 1, 1),
  ('gestor', 'solicitacoes', 1, 1, 1, 1, 1),
  ('gestor', 'locais', 1, 1, 1, 1, 1),
  ('gestor', 'auditoria', 1, 0, 0, 0, 0),
  ('gestor', 'configuracoes', 1, 1, 1, 1, 1),
  ('fiscal', 'dashboard', 1, 0, 0, 0, 0),
  ('fiscal', 'solicitacoes', 1, 0, 1, 0, 1),
  ('fiscal', 'locais', 1, 1, 1, 0, 0),
  ('cidadao', 'rotas', 1, 1, 1, 1, 0),
  ('cidadao', 'avaliacoes', 1, 1, 0, 0, 0);

-- 32. Configurações do Sistema
INSERT INTO `configuracoes` (`config_key`, `config_value`, `config_group`, `label`) VALUES
  ('municipio_nome', 'Suzano', 'municipio', 'Nome do Município'),
  ('municipio_uf', 'SP', 'municipio', 'Unidade Federativa'),
  ('municipio_prefeito', 'Rodrigo Ashiuchi', 'municipio', 'Prefeito Municipal'),
  ('raio_padrao_km', '3.0', 'busca', 'Raio padrão de busca em quilômetros'),
  ('sla_triagem_horas', '48', 'solicitacoes', 'SLA máximo para triagem inicial (horas)'),
  ('sla_atendimento_dias', '15', 'solicitacoes', 'SLA máximo de conclusão de obra (dias)'),
  ('notificar_email_cidadao', '1', 'notificacoes', 'Disparar e-mail a cada atualização de status'),
  ('otp_expiracao_minutos', '5', 'seguranca', 'Tempo de expiração do código OTP (minutos)'),
  ('bloqueio_tentativas_falhas', '5', 'seguranca', 'Número máximo de tentativas antes do bloqueio');

-- 33. Notificações Iniciais
INSERT INTO `notificacoes` (`id`, `user_id`, `title`, `body`, `type`, `is_read`, `local_code`, `created_at`) VALUES
  (1, 1, 'Solicitação em Análise', 'Sua solicitação SOL-2026-0887 foi encaminhada para perícia técnica.', 'info', 0, 'LOC-001', '2026-09-02 14:30:00'),
  (2, 1, 'Nova Rota Acessível Disponível', 'O trecho da Rua General Francisco Glicério foi reclassificado como 100% acessível.', 'success', 1, 'LOC-001', '2026-09-01 09:00:00'),
  (3, 3, 'Alerta de Obstáculo na sua Rota', 'Semáforo sonoro reportado inoperante na Av. Armando Salles.', 'warning', 0, 'LOC-002', '2026-10-02 13:15:00');

SET FOREIGN_KEY_CHECKS = 1;
