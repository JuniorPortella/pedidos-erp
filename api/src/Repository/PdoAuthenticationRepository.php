<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use PDO;

final class PdoAuthenticationRepository implements
    AuthenticationRepository
{
    public function __construct(
        private readonly PDO $connection,
        private readonly UserRepository $userRepository
    ) {
    }

    public function authenticate(
        string $username,
        string $password
    ): ?User {
        $statement = $this->connection->prepare(
            <<<'SQL'
            SELECT
                id,
                senha_hash
            FROM usuarios
            WHERE usuario = :username
              AND ativo = 1
              AND deleted_at IS NULL
            LIMIT 1
            SQL
        );

        $statement->execute([
            'username' => $username,
        ]);

        $credentials = $statement->fetch();

        if ($credentials === false) {
            return null;
        }

        if (
            !password_verify(
                $password,
                (string) $credentials['senha_hash']
            )
        ) {
            return null;
        }

        return $this->userRepository->findById(
            (int) $credentials['id']
        );
    }
}
