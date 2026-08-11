<?php

declare(strict_types=1);

return static function (PDO $connection): void {
    $connection->exec(
        <<<'SQL'
        CREATE TABLE refresh_tokens (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            usuario_id BIGINT UNSIGNED NOT NULL,

            jti_hash CHAR(64)
                CHARACTER SET ascii
                COLLATE ascii_bin
                NOT NULL,

            family_hash CHAR(64)
                CHARACTER SET ascii
                COLLATE ascii_bin
                NOT NULL,

            replaced_by_jti_hash CHAR(64)
                CHARACTER SET ascii
                COLLATE ascii_bin
                NULL,

            expires_at DATETIME NOT NULL,
            used_at DATETIME NULL,
            revoked_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

            PRIMARY KEY (id),

            UNIQUE KEY uk_refresh_tokens_jti_hash (jti_hash),

            KEY idx_refresh_tokens_usuario (usuario_id),
            KEY idx_refresh_tokens_family (family_hash),
            KEY idx_refresh_tokens_expires_at (expires_at),

            CONSTRAINT fk_refresh_tokens_usuario
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
