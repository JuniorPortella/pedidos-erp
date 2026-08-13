<?php

declare(strict_types=1);

use App\Config\Environment;
use App\Security\DataCipher;
use App\Security\LookupHasher;
use App\Service\PhoneNormalizer;

return static function (PDO $connection): void {
    $cipher = new DataCipher(
        Environment::getRequired('DATA_ENCRYPTION_KEY')
    );
    $lookupHasher = new LookupHasher(
        Environment::getRequired('DATA_LOOKUP_KEY')
    );

    $rows = $connection->query(
        <<<'SQL'
        SELECT id, telefone_criptografado
        FROM clientes
        WHERE deleted_at IS NULL
        ORDER BY id
        SQL
    )->fetchAll();

    $phoneHashes = [];
    $seenHashes = [];

    foreach ($rows as $row) {
        $id = (int) $row['id'];
        $phone = $cipher->decrypt(
            (string) $row['telefone_criptografado'],
            'clientes.telefone'
        );
        $hash = $lookupHasher->hash(
            PhoneNormalizer::normalize($phone),
            'clientes.telefone'
        );

        if (isset($seenHashes[$hash])) {
            throw new RuntimeException(
                sprintf(
                    'Clientes ativos %d e %d possuem o mesmo telefone.',
                    $seenHashes[$hash],
                    $id
                )
            );
        }

        $seenHashes[$hash] = $id;
        $phoneHashes[$id] = $hash;
    }

    $connection->exec(
        <<<'SQL'
        ALTER TABLE clientes
            ADD COLUMN telefone_hash CHAR(64)
                CHARACTER SET ascii
                COLLATE ascii_bin
                NULL
                AFTER telefone_criptografado
        SQL
    );

    $update = $connection->prepare(
        <<<'SQL'
        UPDATE clientes
        SET telefone_hash = :phone_hash
        WHERE id = :id
        SQL
    );

    foreach ($phoneHashes as $id => $hash) {
        $update->execute([
            'id' => $id,
            'phone_hash' => $hash,
        ]);
    }

    $connection->exec(
        <<<'SQL'
        ALTER TABLE clientes
            ADD UNIQUE KEY uk_clientes_telefone_hash (
                telefone_hash
            )
        SQL
    );
};
