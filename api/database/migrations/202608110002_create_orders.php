<?php

declare(strict_types=1);

return static function (PDO $connection): void {
    $connection->exec(
        <<<'SQL'
        CREATE TABLE pedidos (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

            cliente_nome_criptografado TEXT
                CHARACTER SET ascii
                COLLATE ascii_bin
                NOT NULL,

            descricao_criptografada TEXT
                CHARACTER SET ascii
                COLLATE ascii_bin
                NOT NULL,

            status VARCHAR(20)
                CHARACTER SET ascii
                COLLATE ascii_bin
                NOT NULL
                DEFAULT 'PENDENTE',

            criado_por BIGINT UNSIGNED NOT NULL,

            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

            updated_at DATETIME NOT NULL
                DEFAULT CURRENT_TIMESTAMP
                ON UPDATE CURRENT_TIMESTAMP,

            PRIMARY KEY (id),

            KEY idx_pedidos_status (status),
            KEY idx_pedidos_criado_por (criado_por),

            CONSTRAINT chk_pedidos_status
                CHECK (
                    status IN (
                        'PENDENTE',
                        'EM_PROCESSAMENTO',
                        'CONCLUIDO'
                    )
                ),

            CONSTRAINT fk_pedidos_criado_por
                FOREIGN KEY (criado_por)
                REFERENCES usuarios (id)
                ON UPDATE RESTRICT
                ON DELETE RESTRICT
        ) ENGINE=InnoDB
          DEFAULT CHARACTER SET utf8mb4
          COLLATE utf8mb4_unicode_ci
        SQL
    );
};
