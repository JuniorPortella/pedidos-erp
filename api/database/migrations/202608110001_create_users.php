<?php

declare(strict_types=1);

return static function (PDO $connection): void {
    $connection->exec(
        <<<'SQL'
        CREATE TABLE usuarios (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

            nome_criptografado TEXT
                CHARACTER SET ascii
                COLLATE ascii_bin
                NOT NULL,

            email_criptografado TEXT
                CHARACTER SET ascii
                COLLATE ascii_bin
                NOT NULL,

            email_hash CHAR(64)
                CHARACTER SET ascii
                COLLATE ascii_bin
                NOT NULL,

            usuario VARCHAR(60) NOT NULL,

            senha_hash VARCHAR(255)
                CHARACTER SET ascii
                COLLATE ascii_bin
                NOT NULL,

            perfil VARCHAR(20)
                CHARACTER SET ascii
                COLLATE ascii_bin
                NOT NULL
                DEFAULT 'OPERADOR',

            ativo TINYINT(1) NOT NULL DEFAULT 1,

            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

            updated_at DATETIME NOT NULL
                DEFAULT CURRENT_TIMESTAMP
                ON UPDATE CURRENT_TIMESTAMP,

            deleted_at DATETIME NULL,

            PRIMARY KEY (id),

            UNIQUE KEY uk_usuarios_email_hash (email_hash),
            UNIQUE KEY uk_usuarios_usuario (usuario),

            KEY idx_usuarios_perfil (perfil),

            CONSTRAINT chk_usuarios_perfil
                CHECK (perfil IN ('ADMIN', 'OPERADOR')),

            CONSTRAINT chk_usuarios_ativo
                CHECK (ativo IN (0, 1))
        ) ENGINE=InnoDB
          DEFAULT CHARACTER SET utf8mb4
          COLLATE utf8mb4_unicode_ci
        SQL
    );
};
