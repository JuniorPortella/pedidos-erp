<?php

declare(strict_types=1);

return static function (PDO $connection): void {
    $connection->exec(
        <<<'SQL'
        CREATE TABLE token_blacklist (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            usuario_id BIGINT UNSIGNED NOT NULL,

            jti_hash CHAR(64)
                CHARACTER SET ascii
                COLLATE ascii_bin
                NOT NULL,

            token_type VARCHAR(20)
                CHARACTER SET ascii
                COLLATE ascii_bin
                NOT NULL,

            motivo VARCHAR(50)
                CHARACTER SET ascii
                COLLATE ascii_bin
                NOT NULL,

            expires_at DATETIME NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

            PRIMARY KEY (id),

            UNIQUE KEY uk_token_blacklist_jti_hash (jti_hash),

            KEY idx_token_blacklist_usuario (usuario_id),
            KEY idx_token_blacklist_expires_at (expires_at),

            CONSTRAINT chk_token_blacklist_type
                CHECK (token_type IN ('access', 'refresh')),

            CONSTRAINT fk_token_blacklist_usuario
                FOREIGN KEY (usuario_id)
                REFERENCES usuarios (id)
                ON UPDATE RESTRICT
                ON DELETE RESTRICT
        ) ENGINE=InnoDB
          DEFAULT CHARACTER SET utf8mb4
          COLLATE utf8mb4_unicode_ci
        SQL
    );
};
