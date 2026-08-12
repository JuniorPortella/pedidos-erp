<?php

declare(strict_types=1);

use App\Config\AuthConfig;
use App\Config\CorsConfig;
use App\Config\Environment;
use App\Controller\AuthenticationController;
use App\Controller\ClientController;
use App\Controller\OrderController;
use App\Controller\UserController;
use App\Database\ConnectionFactory;
use App\Http\Application;
use App\Http\AuthenticationCookieService;
use App\Http\CsrfRequestValidator;
use App\Http\ErrorHandler;
use App\Logging\LoggerFactory;
use App\Middleware\AccessTokenMiddleware;
use App\Middleware\AdminAuthorization;
use App\Middleware\CorsMiddleware;
use App\Middleware\SecurityHeadersMiddleware;
use App\Repository\PdoAuthenticationRepository;
use App\Repository\PdoClientRepository;
use App\Repository\PdoOrderRepository;
use App\Repository\PdoRefreshTokenRepository;
use App\Repository\PdoTokenBlacklistRepository;
use App\Repository\PdoUserRepository;
use App\Routing\Router;
use App\Security\CsrfTokenService;
use App\Security\DataCipher;
use App\Security\LookupHasher;
use App\Security\PdoLoginRateLimiter;
use App\Service\AuthenticationService;
use App\Service\ClientInputValidator;
use App\Service\ClientService;
use App\Service\CreateUserInputValidator;
use App\Service\JwtService;
use App\Service\OrderInputValidator;
use App\Service\OrderService;
use App\Service\PasswordPolicy;
use App\Service\UpdateUserInputValidator;
use App\Service\UserService;

$logger = LoggerFactory::create();
$router = new Router();
$connection = ConnectionFactory::create();
$authConfig = AuthConfig::fromEnvironment();
$corsMiddleware = new CorsMiddleware(
    CorsConfig::fromEnvironment()
);

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
    $blacklist,
    new PdoLoginRateLimiter(
        $connection,
        $lookupHasher,
        $authConfig
    )
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

$clientRepository = new PdoClientRepository(
    $connection,
    new DataCipher(
        Environment::getRequired(
            'DATA_ENCRYPTION_KEY'
        )
    )
);

$clientController = new ClientController(
    new ClientService($clientRepository),
    new ClientInputValidator()
);

$orderController = new OrderController(
    new OrderService(
        new PdoOrderRepository(
            $connection,
            new DataCipher(
                Environment::getRequired(
                    'DATA_ENCRYPTION_KEY'
                )
            )
        ),
        $clientRepository
    ),
    new OrderInputValidator()
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
    $clientController,
    $orderController,
    $accessTokenMiddleware,
    $adminAuthorization,
    $csrfRequestValidator
);

return new Application(
    router: $router,
    errorHandler: new ErrorHandler($logger),
    logger: $logger,
    cors: $corsMiddleware,
    securityHeaders: new SecurityHeadersMiddleware(
        strtolower(Environment::getRequired('APP_ENV'))
            === 'production'
    )
);
