<?php

declare(strict_types=1);

use App\Database\ConnectionFactory;
use App\Database\MigrationRunner;

require dirname(__DIR__) . '/vendor/autoload.php';

try {
    $connection = ConnectionFactory::create();

    $runner = new MigrationRunner(
        $connection,
        dirname(__DIR__) . '/database/migrations'
    );

    $executedVersions = $runner->run();

    if ($executedVersions === []) {
        fwrite(
            STDOUT,
            'Nenhuma migration pendente.' . PHP_EOL
        );

        exit(0);
    }

    foreach ($executedVersions as $version) {
        fwrite(
            STDOUT,
            sprintf(
                'Migration executada: %s%s',
                $version,
                PHP_EOL
            )
        );
    }

    exit(0);
} catch (Throwable $exception) {
    fwrite(
        STDERR,
        sprintf(
            'Erro ao executar migrations: %s%s',
            $exception->getMessage(),
            PHP_EOL
        )
    );

    exit(1);
}
