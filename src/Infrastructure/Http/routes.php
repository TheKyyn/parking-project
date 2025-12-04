<?php

declare(strict_types=1);

use ParkingSystem\Infrastructure\Http\Routing\Router;
use ParkingSystem\Infrastructure\Http\Controller\UserController;
use ParkingSystem\Infrastructure\Http\Controller\ParkingController;
use ParkingSystem\Infrastructure\Http\Middleware\AuthMiddleware;
use ParkingSystem\Infrastructure\Http\Middleware\JwtAuthMiddleware;
use ParkingSystem\Infrastructure\Http\Middleware\OwnerAuthMiddleware;
use ParkingSystem\Infrastructure\Service\UuidGenerator;
use ParkingSystem\Infrastructure\Service\BcryptPasswordHasher;
use ParkingSystem\Infrastructure\Service\FirebaseJwtTokenGenerator;
use ParkingSystem\Infrastructure\Repository\MySQL\MySQLUserRepository;
use ParkingSystem\Infrastructure\Repository\MySQL\MySQLParkingRepository;
use ParkingSystem\Infrastructure\Repository\MySQL\MySQLParkingOwnerRepository;
use ParkingSystem\Infrastructure\Repository\MySQL\MySQLConnection;
use ParkingSystem\UseCase\User\CreateUser;
use ParkingSystem\UseCase\User\AuthenticateUser;
use ParkingSystem\UseCase\Parking\CreateParking;
use ParkingSystem\UseCase\Parking\UpdateParking;
use ParkingSystem\UseCase\Parking\DeleteParking;

/**
 * Enregistre toutes les routes de l'application
 */
return function (Router $router): void {
    // ==========================================
    // DEPENDENCY INJECTION (simple)
    // ==========================================

    // Load environment variables
    if (file_exists(__DIR__ . '/../../.env')) {
        $envLines = file(__DIR__ . '/../../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($envLines as $line) {
            if (strpos(trim($line), '#') === 0) {
                continue;
            }
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $_ENV[trim($key)] = trim($value);
            }
        }
    }

    // Database Connection
    $dbConnection = new MySQLConnection(
        $_ENV['DB_HOST'] ?? 'localhost',
        $_ENV['DB_NAME'] ?? 'parking_system',
        $_ENV['DB_USERNAME'] ?? 'parking_dev',
        $_ENV['DB_PASSWORD'] ?? 'dev_password_2024',
        (int)($_ENV['DB_PORT'] ?? 3306)
    );

    // Repositories
    $userRepository = new MySQLUserRepository($dbConnection);
    $parkingRepository = new MySQLParkingRepository($dbConnection);
    $parkingOwnerRepository = new MySQLParkingOwnerRepository($dbConnection);

    // Services
    $idGenerator = new UuidGenerator();
    $passwordHasher = new BcryptPasswordHasher();
    $jwtSecret = $_ENV['JWT_SECRET'] ?? 'your_secret_key_at_least_32_characters_long_change_in_production';
    $jwtGenerator = new FirebaseJwtTokenGenerator($jwtSecret, (int)($_ENV['JWT_EXPIRATION'] ?? 3600));

    // Use Cases
    $createUserUseCase = new CreateUser(
        $userRepository,
        $passwordHasher,
        $idGenerator
    );

    $authenticateUserUseCase = new AuthenticateUser(
        $userRepository,
        $passwordHasher,
        $jwtGenerator
    );

    $createParkingUseCase = new CreateParking(
        $parkingRepository,
        $parkingOwnerRepository,
        $idGenerator
    );

    $updateParkingUseCase = new UpdateParking($parkingRepository);

    $deleteParkingUseCase = new DeleteParking(
        $parkingRepository,
        $parkingOwnerRepository
    );

    // Controllers
    $userController = new UserController(
        $createUserUseCase,
        $authenticateUserUseCase,
        $userRepository
    );

    $parkingController = new ParkingController(
        $createParkingUseCase,
        $updateParkingUseCase,
        $deleteParkingUseCase,
        $parkingRepository
    );

    // Middleware
    $jwtAuthMiddleware = new JwtAuthMiddleware($jwtGenerator);
    $authMiddleware = new AuthMiddleware($jwtAuthMiddleware);
    $ownerAuthMiddleware = new OwnerAuthMiddleware($jwtAuthMiddleware);

    // ==========================================
    // ROUTES
    // ==========================================

    // User routes
    $router->post('/api/users', [$userController, 'register'])
        ->name('users.register');

    $router->post('/api/auth/login', [$userController, 'login'])
        ->name('auth.login');

    $router->get('/api/users/profile', [$userController, 'getProfile'])
        ->middleware($authMiddleware)
        ->name('users.profile');

    // Parking routes
    // Public routes
    $router->get('/api/parkings', [$parkingController, 'list'])
        ->name('parkings.list');

    $router->get('/api/parkings/:id', [$parkingController, 'show'])
        ->name('parkings.show');

    // Owner-only routes
    $router->post('/api/parkings', [$parkingController, 'create'])
        ->middleware($ownerAuthMiddleware)
        ->name('parkings.create');

    $router->put('/api/parkings/:id', [$parkingController, 'update'])
        ->middleware($ownerAuthMiddleware)
        ->name('parkings.update');

    $router->delete('/api/parkings/:id', [$parkingController, 'delete'])
        ->middleware($ownerAuthMiddleware)
        ->name('parkings.delete');
};
