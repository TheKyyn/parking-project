<?php

require_once __DIR__ . '/vendor/autoload.php';

use ParkingSystem\Infrastructure\Http\Request\HttpRequest;
use ParkingSystem\Infrastructure\Http\Response\JsonResponse;
use ParkingSystem\Infrastructure\Http\Routing\Router;
use ParkingSystem\Infrastructure\Http\Routing\RouteNotFoundException;

echo "🧪 Test Routing System\n\n";

// 1. Test Router basique
echo "1️⃣ Router - Routes simples:\n";
$router = new Router();

$router->get('/users', fn() => JsonResponse::success(['users' => ['Alice', 'Bob']]));
$router->post('/users', fn() => JsonResponse::created(['id' => 123], 'User created'));
$router->get('/users/:id', fn($req) => JsonResponse::success(['id' => $req->getPathParam('id')]));

echo "   ✅ Routes enregistrées: " . count($router->getRoutes()) . "\n";

// Test dispatch GET /users
$request1 = new HttpRequest('GET', '/users');
$response1 = $router->dispatch($request1);
echo "   ✅ GET /users: " . $response1->getStatusCode() . "\n";

// Test dispatch POST /users
$request2 = new HttpRequest('POST', '/users', [], ['name' => 'Charlie']);
$response2 = $router->dispatch($request2);
echo "   ✅ POST /users: " . $response2->getStatusCode() . "\n";

// Test dispatch GET /users/123
$request3 = new HttpRequest('GET', '/users/123');
$response3 = $router->dispatch($request3);
$body3 = $response3->getBody();
echo "   ✅ GET /users/123: ID = {$body3['data']['id']}\n\n";

// 2. Test Route avec paramètres multiples
echo "2️⃣ Router - Paramètres multiples:\n";
$router2 = new Router();
$router2->get('/parkings/:parkingId/sessions/:sessionId', function ($req) {
    return JsonResponse::success([
        'parkingId' => $req->getPathParam('parkingId'),
        'sessionId' => $req->getPathParam('sessionId')
    ]);
});

$request4 = new HttpRequest('GET', '/parkings/park-123/sessions/sess-456');
$response4 = $router2->dispatch($request4);
$body4 = $response4->getBody();
echo "   ✅ parkingId: {$body4['data']['parkingId']}\n";
echo "   ✅ sessionId: {$body4['data']['sessionId']}\n\n";

// 3. Test Middleware
echo "3️⃣ Router - Middleware:\n";
$router3 = new Router();
$executionLog = [];

$logMiddleware = function ($req) use (&$executionLog) {
    $executionLog[] = 'middleware';
};

$router3->get('/test', function ($req) use (&$executionLog) {
    $executionLog[] = 'handler';
    return JsonResponse::success();
})->middleware($logMiddleware);

$request5 = new HttpRequest('GET', '/test');
$router3->dispatch($request5);
echo "   ✅ Ordre d'exécution: " . implode(' → ', $executionLog) . "\n\n";

// 4. Test 404
echo "4️⃣ Router - 404 Not Found:\n";
$router4 = new Router();
$router4->get('/exists', fn() => JsonResponse::success());

try {
    $request6 = new HttpRequest('GET', '/does-not-exist');
    $router4->dispatch($request6);
    echo "   ❌ Devrait lancer RouteNotFoundException\n";
} catch (RouteNotFoundException $e) {
    echo "   ✅ RouteNotFoundException levée: {$e->getMessage()}\n\n";
}

// 5. Test Global Middleware
echo "5️⃣ Router - Global Middleware:\n";
$router5 = new Router();
$globalLog = [];

$router5->addGlobalMiddleware(function ($req) use (&$globalLog) {
    $globalLog[] = 'global';
});

$router5->get('/test', function ($req) use (&$globalLog) {
    $globalLog[] = 'handler';
    return JsonResponse::success();
});

$request7 = new HttpRequest('GET', '/test');
$router5->dispatch($request7);
echo "   ✅ Global middleware exécuté: " . implode(' → ', $globalLog) . "\n\n";

echo "🎉 Tous les tests de routing sont passés !\n";
