<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use ParkingSystem\Infrastructure\Http\Request\HttpRequest;
use ParkingSystem\Infrastructure\Http\Response\JsonResponse;
use ParkingSystem\Infrastructure\Http\Routing\Router;
use ParkingSystem\Infrastructure\Http\Routing\RouteNotFoundException;
use ParkingSystem\Infrastructure\Http\Middleware\CorsMiddleware;

// Gestion des erreurs
error_reporting(E_ALL);
ini_set('display_errors', '0'); // Pas d'affichage direct des erreurs en prod

// Crée le router
$router = new Router();

// Middleware CORS global
$corsMiddleware = CorsMiddleware::permissive();
$router->addGlobalMiddleware(function ($request) use ($corsMiddleware) {
    $corsMiddleware->handle($request);
    // Ne retourne rien, laisse continuer
});

// ==========================================
// DÉFINITION DES ROUTES
// ==========================================

// Route de base
$router->get('/', function () {
    return JsonResponse::success([
        'name' => 'Parking System API',
        'version' => '1.0.0',
        'status' => 'running',
        'endpoints' => [
            'POST /api/users' => 'Register new user',
            'POST /api/auth/login' => 'Authenticate user',
            'GET /api/users/profile' => 'Get user profile (auth required)',
        ]
    ], 'Welcome to Parking System API');
});

// Health check
$router->get('/health', function () {
    return JsonResponse::success([
        'status' => 'healthy',
        'timestamp' => time()
    ]);
});

// Charge les routes depuis le fichier dédié
$routesLoader = require __DIR__ . '/../src/Infrastructure/Http/routes.php';
$routesLoader($router);

// ==========================================
// DISPATCH
// ==========================================

try {
    // Crée la requête depuis les superglobals
    $request = HttpRequest::fromGlobals();

    // Dispatch vers le handler approprié
    $response = $router->dispatch($request);

    // Envoie la réponse
    $response->send();
} catch (RouteNotFoundException $e) {
    // 404 Not Found
    $response = JsonResponse::notFound($e->getMessage());
    $response->send();
} catch (\Exception $e) {
    // 500 Internal Server Error
    $response = JsonResponse::serverError(
        getenv('APP_ENV') === 'development'
            ? $e->getMessage()
            : 'An error occurred'
    );
    $response->send();
}
