<?php

declare(strict_types=1);

return static function (PDO $connection): void {
    $connection->exec(
        <<<'SQL'
        ALTER TABLE pedidos
            MODIFY cliente_nome_criptografado TEXT
                CHARACTER SET ascii
                COLLATE ascii_bin
                NULL,

            ADD COLUMN cliente_id BIGINT UNSIGNED NULL
                AFTER id,

            ADD KEY idx_pedidos_cliente_id (cliente_id),

            ADD CONSTRAINT fk_pedidos_cliente
                FOREIGN KEY (cliente_id)
                REFERENCES clientes (id)
                ON UPDATE RESTRICT
                ON DELETE RESTRICT
        SQL
    );
};
