<?php

declare(strict_types=1);

use App\Config\AuthConfig;
use App\Config\Environment;
use App\Controller\AuthenticationController;
use App\Controller\UserController;
use App\Database\ConnectionFactory;
use App\Http\Application;
use App\Http\AuthenticationCookieService;
use App\Http\CsrfRequestValidator;
use App\Http\ErrorHandler;
use App\Logging\LoggerFactory;
use App\Middleware\AccessTokenMiddleware;
use App\Middleware\AdminAuthorization;
use App\Repository\PdoAuthenticationRepository;
use App\Repository\PdoRefreshTokenRepository;
use App\Repository\PdoTokenBlacklistRepository;
use App\Repository\PdoUserRepository;
use App\Routing\Router;
use App\Security\CsrfTokenService;
use App\Security\DataCipher;
use App\Security\LookupHasher;
use App\Service\AuthenticationService;
use App\Service\CreateUserInputValidator;
use App\Service\JwtService;
use App\Service\PasswordPolicy;
use App\Service\UpdateUserInputValidator;
use App\Service\UserService;

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
$jwtService = new JwtService($authConfig);
$csrfRequestValidator = new CsrfRequestValidator(
    $authConfig,
    $csrfTokens
);

$authenticationService = new AuthenticationService(
    new PdoAuthenticationRepository(
        $connection,
        $userRepository
    ),
    $jwtService,
    $csrfTokens,
    $refreshTokens,
    $userRepository,
    $blacklist
);

$authenticationController = new AuthenticationController(
    $authenticationService,
    new AuthenticationCookieService($authConfig),
    $csrfRequestValidator
);

$accessTokenMiddleware = new AccessTokenMiddleware(
    $jwtService,
    $blacklist,
    $userRepository
);

$adminAuthorization = new AdminAuthorization();

$userController = new UserController(
    new UserService(
        $userRepository,
        $refreshTokens
    ),
    new CreateUserInputValidator(),
    new UpdateUserInputValidator(
        new PasswordPolicy()
    )
);

$registerRoutes = require dirname(__DIR__)
    . '/routes/api.php';

if (!is_callable($registerRoutes)) {
    throw new LogicException(
        'O arquivo de rotas deve retornar uma funcao.'
    );
}

$registerRoutes(
    $router,
    $authenticationController,
    $userController,
    $accessTokenMiddleware,
    $adminAuthorization,
    $csrfRequestValidator
);

return new Application(
    router: $router,
    errorHandler: new ErrorHandler($logger),
    logger: $logger
);
