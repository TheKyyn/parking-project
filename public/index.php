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

// Configure CORS headers AVANT tout traitement
// Cela permet de gérer CORS même pour les 404/500
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, PATCH, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Max-Age: 86400');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Crée le router
$router = new Router();

// Middleware CORS global (backup, au cas où)
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
            'POST /api/owners' => 'Register new owner',
            'POST /api/owners/login' => 'Authenticate owner',
            'GET /api/owners/profile' => 'Get owner profile (owner auth)',
            'PUT /api/owners/profile' => 'Update owner profile (owner auth)',
            'GET /api/parkings' => 'List all parkings',
            'GET /api/parkings/:id' => 'Get parking details',
            'POST /api/parkings' => 'Create parking (owner only)',
            'PUT /api/parkings/:id' => 'Update parking (owner only)',
            'DELETE /api/parkings/:id' => 'Delete parking (owner only)',
            'POST /api/reservations' => 'Create reservation (user only)',
            'GET /api/reservations' => 'List user reservations (user only)',
            'GET /api/reservations/:id' => 'Get reservation details (user only)',
            'DELETE /api/reservations/:id' => 'Cancel reservation (user only)',
            'POST /api/sessions' => 'Start parking session (user only)',
            'PUT /api/sessions/:id/end' => 'End parking session (user only)',
            'GET /api/sessions' => 'List user sessions (user only)',
            'GET /api/sessions/:id' => 'Get session details (user only)',
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
