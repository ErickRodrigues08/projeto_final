SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS usuarios_avatares (
    user_id INT NOT NULL,
    conteudo MEDIUMBLOB NOT NULL,
    mime VARCHAR(32) NOT NULL,
    tamanho INT UNSIGNED NOT NULL,
    versao CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id),
    CONSTRAINT fk_usuarios_avatares_usuario FOREIGN KEY (user_id) REFERENCES usuarios (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
