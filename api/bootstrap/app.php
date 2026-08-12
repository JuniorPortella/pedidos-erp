<?php

declare(strict_types=1);

use App\Config\AuthConfig;
use App\Config\Environment;
use App\Controller\AuthenticationController;
use App\Database\ConnectionFactory;
use App\Http\Application;
use App\Http\AuthenticationCookieService;
use App\Http\CsrfRequestValidator;
use App\Http\ErrorHandler;
use App\Logging\LoggerFactory;
use App\Repository\PdoAuthenticationRepository;
use App\Repository\PdoRefreshTokenRepository;
use App\Repository\PdoTokenBlacklistRepository;
use App\Repository\PdoUserRepository;
use App\Routing\Router;
use App\Security\CsrfTokenService;
use App\Security\DataCipher;
use App\Security\LookupHasher;
use App\Service\AuthenticationService;
use App\Service\JwtService;

$logger = LoggerFactory::create();
$router = new Router();
$connection = ConnectionFactory::create();
$authConfig = AuthConfig::fromEnvironment();

$lookupHasher = new LookupHasher(
    Environment::getRequired('DATA_LOOKUP_KEY')
);

$userRepository = new PdoUserRepository(
    $connection,
    new DataCipher(
        Environment::getRequired('DATA_ENCRYPTION_KEY')
    ),
    $lookupHasher
);

$refreshTokens = new PdoRefreshTokenRepository(
    $connection,
    $lookupHasher
);

$blacklist = new PdoTokenBlacklistRepository(
    $connection,
    $lookupHasher
);

$csrfTokens = new CsrfTokenService();

$authenticationService = new AuthenticationService(
    new PdoAuthenticationRepository(
        $connection,
        $userRepository
    ),
    new JwtService($authConfig),
    $csrfTokens,
    $refreshTokens,
    $userRepository,
    $blacklist
);

$authenticationController = new AuthenticationController(
    $authenticationService,
    new AuthenticationCookieService($authConfig),
    new CsrfRequestValidator(
        $authConfig,
        $csrfTokens
    )
);

$registerRoutes = require dirname(__DIR__)
    . '/routes/api.php';

if (!is_callable($registerRoutes)) {
    throw new LogicException(
        'O arquivo de rotas deve retornar uma funcao.'
    );
}

$registerRoutes($router, $authenticationController);

return new Application(
    router: $router,
    errorHandler: new ErrorHandler($logger),
    logger: $logger
);
