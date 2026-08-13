<?php

declare(strict_types=1);

return static function (PDO $connection): void {
    $connection->exec(
        <<<'SQL'
        ALTER TABLE pedidos
            ADD COLUMN valor_total DECIMAL(12, 2)
                NOT NULL
                DEFAULT 0.00
                AFTER descricao_criptografada,

            ADD CONSTRAINT chk_pedidos_valor_total
                CHECK (valor_total >= 0)
        SQL
    );
};
