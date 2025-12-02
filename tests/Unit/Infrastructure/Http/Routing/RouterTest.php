<?php

declare(strict_types=1);

namespace ParkingSystem\Tests\Unit\Infrastructure\Http\Routing;

use ParkingSystem\Infrastructure\Http\Routing\Router;
use ParkingSystem\Infrastructure\Http\Routing\RouteNotFoundException;
use ParkingSystem\Infrastructure\Http\Request\HttpRequest;
use ParkingSystem\Infrastructure\Http\Response\JsonResponse;
use PHPUnit\Framework\TestCase;

class RouterTest extends TestCase
{
    private Router $router;

    protected function setUp(): void
    {
        $this->router = new Router();
    }

    public function testRegisterGetRoute(): void
    {
        $route = $this->router->get('/users', fn() => 'users');

        $this->assertCount(1, $this->router->getRoutes());
        $this->assertEquals('GET', $route->getMethod());
        $this->assertEquals('/users', $route->getPath());
    }

    public function testRegisterPostRoute(): void
    {
        $route = $this->router->post('/users', fn() => 'create');

        $this->assertEquals('POST', $route->getMethod());
    }

    public function testRegisterPutRoute(): void
    {
        $route = $this->router->put('/users/:id', fn() => 'update');

        $this->assertEquals('PUT', $route->getMethod());
    }

    public function testRegisterDeleteRoute(): void
    {
        $route = $this->router->delete('/users/:id', fn() => 'delete');

        $this->assertEquals('DELETE', $route->getMethod());
    }

    public function testRegisterPatchRoute(): void
    {
        $route = $this->router->patch('/users/:id', fn() => 'patch');

        $this->assertEquals('PATCH', $route->getMethod());
    }

    public function testRegisterAnyRoute(): void
    {
        $this->router->any('/test', fn() => 'any');

        // Any enregistre plusieurs routes
        $this->assertGreaterThanOrEqual(5, count($this->router->getRoutes()));
    }

    public function testDispatchSimpleRoute(): void
    {
        $this->router->get('/users', fn() => JsonResponse::success(['users' => []]));

        $request = new HttpRequest('GET', '/users');
        $response = $this->router->dispatch($request);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testDispatchRouteWithParameter(): void
    {
        $this->router->get('/users/:id', function ($request) {
            $id = $request->getPathParam('id');
            return JsonResponse::success(['id' => $id]);
        });

        $request = new HttpRequest('GET', '/users/123');
        $response = $this->router->dispatch($request);

        $this->assertEquals(200, $response->getStatusCode());
        $body = $response->getBody();
        $this->assertEquals('123', $body['data']['id']);
    }

    public function testDispatchRouteWithMultipleParameters(): void
    {
        $this->router->get('/parkings/:parkingId/sessions/:sessionId', function ($request) {
            return JsonResponse::success([
                'parkingId' => $request->getPathParam('parkingId'),
                'sessionId' => $request->getPathParam('sessionId')
            ]);
        });

        $request = new HttpRequest('GET', '/parkings/abc/sessions/xyz');
        $response = $this->router->dispatch($request);

        $body = $response->getBody();
        $this->assertEquals('abc', $body['data']['parkingId']);
        $this->assertEquals('xyz', $body['data']['sessionId']);
    }

    public function testDispatchThrowsNotFoundForUnknownRoute(): void
    {
        $this->router->get('/users', fn() => JsonResponse::success());

        $this->expectException(RouteNotFoundException::class);
        $this->expectExceptionMessage('Route not found: GET /posts');

        $request = new HttpRequest('GET', '/posts');
        $this->router->dispatch($request);
    }

    public function testDispatchThrowsNotFoundForWrongMethod(): void
    {
        $this->router->get('/users', fn() => JsonResponse::success());

        $this->expectException(RouteNotFoundException::class);

        $request = new HttpRequest('POST', '/users');
        $this->router->dispatch($request);
    }

    public function testMiddlewareIsExecutedBeforeHandler(): void
    {
        $executionOrder = [];

        $middleware = function ($request) use (&$executionOrder) {
            $executionOrder[] = 'middleware';
            // Ne retourne rien, continue vers le handler
        };

        $this->router->get('/test', function ($request) use (&$executionOrder) {
            $executionOrder[] = 'handler';
            return JsonResponse::success();
        })->middleware($middleware);

        $request = new HttpRequest('GET', '/test');
        $this->router->dispatch($request);

        $this->assertEquals(['middleware', 'handler'], $executionOrder);
    }

    public function testMiddlewareCanShortCircuit(): void
    {
        $middleware = function ($request) {
            return JsonResponse::unauthorized('Not allowed');
        };

        $this->router->get('/protected', fn() => JsonResponse::success())
            ->middleware($middleware);

        $request = new HttpRequest('GET', '/protected');
        $response = $this->router->dispatch($request);

        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testGlobalMiddlewareIsExecuted(): void
    {
        $executionOrder = [];

        $this->router->addGlobalMiddleware(function ($request) use (&$executionOrder) {
            $executionOrder[] = 'global';
        });

        $this->router->get('/test', function ($request) use (&$executionOrder) {
            $executionOrder[] = 'handler';
            return JsonResponse::success();
        });

        $request = new HttpRequest('GET', '/test');
        $this->router->dispatch($request);

        $this->assertEquals(['global', 'handler'], $executionOrder);
    }

    public function testGlobalMiddlewareExecutesBeforeRouteMiddleware(): void
    {
        $executionOrder = [];

        $this->router->addGlobalMiddleware(function ($request) use (&$executionOrder) {
            $executionOrder[] = 'global';
        });

        $this->router->get('/test', function ($request) use (&$executionOrder) {
            $executionOrder[] = 'handler';
            return JsonResponse::success();
        })->middleware(function ($request) use (&$executionOrder) {
            $executionOrder[] = 'route';
        });

        $request = new HttpRequest('GET', '/test');
        $this->router->dispatch($request);

        $this->assertEquals(['global', 'route', 'handler'], $executionOrder);
    }

    public function testHandlerCanReturnArray(): void
    {
        $this->router->get('/test', fn() => ['message' => 'Hello']);

        $request = new HttpRequest('GET', '/test');
        $response = $this->router->dispatch($request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testHandlerCanReturnString(): void
    {
        $this->router->get('/test', fn() => 'Hello World');

        $request = new HttpRequest('GET', '/test');
        $response = $this->router->dispatch($request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $body = $response->getBody();
        $this->assertEquals('Hello World', $body['message']);
    }

    public function testRouteWithName(): void
    {
        $route = $this->router->get('/users', fn() => JsonResponse::success())
            ->name('users.index');

        $this->assertEquals('users.index', $route->getName());
    }

    public function testMultipleRoutesCanBeRegistered(): void
    {
        $this->router->get('/users', fn() => JsonResponse::success());
        $this->router->post('/users', fn() => JsonResponse::success());
        $this->router->get('/users/:id', fn() => JsonResponse::success());
        $this->router->delete('/users/:id', fn() => JsonResponse::success());

        $this->assertCount(4, $this->router->getRoutes());
    }

    public function testRouteMatchingIsPrecise(): void
    {
        $this->router->get('/users', fn() => JsonResponse::success(['route' => 'list']));
        $this->router->get('/users/:id', fn($req) => JsonResponse::success([
            'route' => 'show',
            'id' => $req->getPathParam('id')
        ]));

        // Test /users
        $request1 = new HttpRequest('GET', '/users');
        $response1 = $this->router->dispatch($request1);
        $this->assertEquals('list', $response1->getBody()['data']['route']);

        // Test /users/123
        $request2 = new HttpRequest('GET', '/users/123');
        $response2 = $this->router->dispatch($request2);
        $this->assertEquals('show', $response2->getBody()['data']['route']);
        $this->assertEquals('123', $response2->getBody()['data']['id']);
    }
}
