<?php

declare(strict_types=1);

use App\Config\Environment;
use App\Console\CreateAdminCommand;
use App\Database\ConnectionFactory;
use App\Exception\ValidationException;
use App\Repository\PdoUserRepository;
use App\Security\DataCipher;
use App\Security\LookupHasher;
use App\Service\CreateUserInputValidator;
use App\Service\UserService;

require dirname(__DIR__) . '/vendor/autoload.php';

$isInteractiveInput = function_exists('stream_isatty')
    && stream_isatty(STDIN);

try {
    $lookupHasher = new LookupHasher(
        Environment::getRequired('DATA_LOOKUP_KEY')
    );

    $repository = new PdoUserRepository(
        ConnectionFactory::create(),
        new DataCipher(
            Environment::getRequired('DATA_ENCRYPTION_KEY')
        ),
        $lookupHasher
    );

    $command = new CreateAdminCommand(
        new CreateUserInputValidator(),
        new UserService($repository)
    );

    fwrite(
        STDOUT,
        'Criacao de administrador' . PHP_EOL
    );

    $name = promptText('Nome: ');
    $email = promptText('E-mail: ');
    $username = promptText('Usuario: ');
    $password = promptSecret(
        'Senha: ',
        $isInteractiveInput
    );
    $passwordConfirmation = promptSecret(
        'Confirme a senha: ',
        $isInteractiveInput
    );

    if (!hash_equals($password, $passwordConfirmation)) {
        fwrite(
            STDERR,
            'As senhas informadas nao conferem.' . PHP_EOL
        );

        exit(1);
    }

    $user = $command->execute([
        'nome' => $name,
        'email' => $email,
        'usuario' => $username,
        'senha' => $password,
    ]);

    fwrite(
        STDOUT,
        sprintf(
            'Administrador criado: %s (id %d)%s',
            $user->username,
            $user->id,
            PHP_EOL
        )
    );

    exit(0);
} catch (ValidationException $exception) {
    fwrite(STDERR, 'Dados invalidos:' . PHP_EOL);

    foreach ($exception->errors() as $field => $message) {
        fwrite(
            STDERR,
            sprintf('  %s: %s%s', $field, $message, PHP_EOL)
        );
    }

    exit(1);
} catch (Throwable) {
    fwrite(
        STDERR,
        'Nao foi possivel criar o administrador.' . PHP_EOL
    );

    exit(1);
}

function promptText(string $label): string
{
    fwrite(STDOUT, $label);

    $value = fgets(STDIN);

    if ($value === false) {
        throw new RuntimeException(
            'Nao foi possivel ler a entrada do terminal.'
        );
    }

    return trim($value);
}

function promptSecret(
    string $label,
    bool $isInteractive
): string {
    fwrite(STDOUT, $label);

    if (!$isInteractive) {
        $value = fgets(STDIN);

        if ($value === false) {
            throw new RuntimeException(
                'Nao foi possivel ler a senha.'
            );
        }

        return rtrim($value, "\r\n");
    }

    $terminalMode = shell_exec('stty -g 2>/dev/null');

    if (
        !is_string($terminalMode)
        || trim($terminalMode) === ''
    ) {
        throw new RuntimeException(
            'Nao foi possivel ocultar a senha no terminal.'
        );
    }

    shell_exec('stty -echo 2>/dev/null');

    try {
        $value = fgets(STDIN);

        if ($value === false) {
            throw new RuntimeException(
                'Nao foi possivel ler a senha.'
            );
        }

        return rtrim($value, "\r\n");
    } finally {
        shell_exec(
            'stty '
            . escapeshellarg(trim($terminalMode))
            . ' 2>/dev/null'
        );

        fwrite(STDOUT, PHP_EOL);
    }
}
