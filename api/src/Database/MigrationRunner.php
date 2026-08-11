<?php

declare(strict_types=1);

namespace App\Database;

use PDO;
use RuntimeException;
use Throwable;

final class MigrationRunner
{
    private readonly string $directory;

    public function __construct(
        private readonly PDO $connection,
        string $directory
    ) {
        $directory = rtrim($directory, DIRECTORY_SEPARATOR);

        if (!is_dir($directory) || !is_readable($directory)) {
            throw new RuntimeException(
                sprintf(
                    'Diretorio de migrations invalido: %s',
                    $directory
                )
            );
        }

        $this->directory = $directory;
    }

    public function run(): array
    {
        $this->ensureMigrationsTable();

        $files = glob(
            $this->directory
            . DIRECTORY_SEPARATOR
            . '*.php'
        );

        if ($files === false) {
            throw new RuntimeException(
                'Nao foi possivel localizar as migrations.'
            );
        }

        sort($files, SORT_STRING);

        $appliedVersions = $this->getAppliedVersions();
        $executedVersions = [];

        foreach ($files as $file) {
            $version = pathinfo($file, PATHINFO_FILENAME);

            if (isset($appliedVersions[$version])) {
                continue;
            }

            $migration = require $file;

            if (!is_callable($migration)) {
                throw new RuntimeException(
                    sprintf(
                        'Migration invalida: %s',
                        $version
                    )
                );
            }

            try {
                $migration($this->connection);
                $this->recordMigration($version);
            } catch (Throwable $exception) {
                throw new RuntimeException(
                    sprintf(
                        'Falha ao executar a migration: %s',
                        $version
                    ),
                    0,
                    $exception
                );
            }

            $executedVersions[] = $version;
        }

        return $executedVersions;
    }

    private function ensureMigrationsTable(): void
    {
        $this->connection->exec(
            <<<'SQL'
            CREATE TABLE IF NOT EXISTS schema_migrations (
                version VARCHAR(180) NOT NULL PRIMARY KEY,
                executed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB
              DEFAULT CHARACTER SET utf8mb4
              COLLATE utf8mb4_unicode_ci
            SQL
        );
    }

    private function getAppliedVersions(): array
    {
        $statement = $this->connection->query(
            'SELECT version FROM schema_migrations'
        );

        $versions = $statement->fetchAll(PDO::FETCH_COLUMN);

        return array_fill_keys($versions, true);
    }

    private function recordMigration(string $version): void
    {
        $statement = $this->connection->prepare(
            <<<'SQL'
            INSERT INTO schema_migrations (version)
            VALUES (:version)
            SQL
        );

        $statement->execute([
            'version' => $version,
        ]);
    }
}
