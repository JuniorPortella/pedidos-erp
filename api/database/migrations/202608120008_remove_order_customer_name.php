<?php

declare(strict_types=1);

return static function (PDO $connection): void {
    $connection->exec(
        <<<'SQL'
        ALTER TABLE pedidos
            MODIFY cliente_id BIGINT UNSIGNED NOT NULL,
            DROP COLUMN cliente_nome_criptografado
        SQL
    );
};
