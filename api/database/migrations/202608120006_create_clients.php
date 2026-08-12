<?php

declare(strict_types=1);

return static function (PDO $connection): void {
    $connection->exec(
        <<<'SQL'
        CREATE TABLE clientes (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

            nome_criptografado TEXT
                CHARACTER SET ascii
                COLLATE ascii_bin
                NOT NULL,

            telefone_criptografado TEXT
                CHARACTER SET ascii
                COLLATE ascii_bin
                NOT NULL,

            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

            updated_at DATETIME NOT NULL
                DEFAULT CURRENT_TIMESTAMP
                ON UPDATE CURRENT_TIMESTAMP,

            deleted_at DATETIME NULL,

            PRIMARY KEY (id)
        ) ENGINE=InnoDB
          DEFAULT CHARACTER SET utf8mb4
          COLLATE utf8mb4_unicode_ci
        SQL
    );
};
