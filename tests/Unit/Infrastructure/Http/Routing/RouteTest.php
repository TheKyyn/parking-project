<?php

declare(strict_types=1);

namespace ParkingSystem\Tests\Unit\Infrastructure\Http\Routing;

use ParkingSystem\Infrastructure\Http\Routing\Route;
use PHPUnit\Framework\TestCase;

class RouteTest extends TestCase
{
    public function testConstructorSetsProperties(): void
    {
        $handler = fn() => 'response';
        $route = new Route('GET', '/users', $handler);

        $this->assertEquals('GET', $route->getMethod());
        $this->assertEquals('/users', $route->getPath());
        $this->assertEquals($handler, $route->getHandler());
    }

    public function testMethodIsUppercased(): void
    {
        $route = new Route('get', '/users', fn() => null);

        $this->assertEquals('GET', $route->getMethod());
    }

    public function testMatchesSimpleRoute(): void
    {
        $route = new Route('GET', '/users', fn() => null);

        $this->assertTrue($route->matches('GET', '/users'));
        $this->assertFalse($route->matches('POST', '/users'));
        $this->assertFalse($route->matches('GET', '/users/123'));
    }

    public function testMatchesRouteWithSingleParameter(): void
    {
        $route = new Route('GET', '/users/:id', fn() => null);

        $this->assertTrue($route->matches('GET', '/users/123'));
        $this->assertTrue($route->matches('GET', '/users/abc'));
        $this->assertFalse($route->matches('GET', '/users'));
        $this->assertFalse($route->matches('GET', '/users/123/posts'));
    }

    public function testMatchesRouteWithMultipleParameters(): void
    {
        $route = new Route('GET', '/parkings/:parkingId/sessions/:sessionId', fn() => null);

        $this->assertTrue($route->matches('GET', '/parkings/123/sessions/456'));
        $this->assertTrue($route->matches('GET', '/parkings/abc/sessions/xyz'));
        $this->assertFalse($route->matches('GET', '/parkings/123'));
        $this->assertFalse($route->matches('GET', '/parkings/123/sessions'));
    }

    public function testExtractsSingleParameter(): void
    {
        $route = new Route('GET', '/users/:id', fn() => null);

        $params = $route->extractParameters('/users/123');

        $this->assertNotNull($params);
        $this->assertArrayHasKey('id', $params);
        $this->assertEquals('123', $params['id']);
    }

    public function testExtractsMultipleParameters(): void
    {
        $route = new Route('GET', '/parkings/:parkingId/sessions/:sessionId', fn() => null);

        $params = $route->extractParameters('/parkings/abc123/sessions/xyz789');

        $this->assertNotNull($params);
        $this->assertArrayHasKey('parkingId', $params);
        $this->assertArrayHasKey('sessionId', $params);
        $this->assertEquals('abc123', $params['parkingId']);
        $this->assertEquals('xyz789', $params['sessionId']);
    }

    public function testExtractParametersReturnsNullWhenNoMatch(): void
    {
        $route = new Route('GET', '/users/:id', fn() => null);

        $params = $route->extractParameters('/posts/123');

        $this->assertNull($params);
    }

    public function testMiddlewareChaining(): void
    {
        $middleware1 = fn() => 'middleware1';
        $middleware2 = fn() => 'middleware2';

        $route = new Route('GET', '/users', fn() => null);
        $result = $route->middleware($middleware1)->middleware($middleware2);

        $this->assertSame($route, $result); // Fluent interface
        $this->assertCount(2, $route->getMiddleware());
        $this->assertEquals([$middleware1, $middleware2], $route->getMiddleware());
    }

    public function testMiddlewaresArray(): void
    {
        $middleware1 = fn() => 'middleware1';
        $middleware2 = fn() => 'middleware2';

        $route = new Route('GET', '/users', fn() => null);
        $route->middlewares([$middleware1, $middleware2]);

        $this->assertCount(2, $route->getMiddleware());
    }

    public function testNameChaining(): void
    {
        $route = new Route('GET', '/users', fn() => null);
        $result = $route->name('users.index');

        $this->assertSame($route, $result); // Fluent interface
        $this->assertEquals('users.index', $route->getName());
    }

    public function testParametersAreEmptyByDefault(): void
    {
        $route = new Route('GET', '/users', fn() => null);

        $this->assertEquals([], $route->getParameters());
    }

    public function testSetParameters(): void
    {
        $route = new Route('GET', '/users/:id', fn() => null);
        $route->setParameters(['id' => '123']);

        $this->assertEquals(['id' => '123'], $route->getParameters());
    }

    public function testMatchesCaseInsensitiveMethod(): void
    {
        $route = new Route('POST', '/users', fn() => null);

        $this->assertTrue($route->matches('post', '/users'));
        $this->assertTrue($route->matches('POST', '/users'));
        $this->assertTrue($route->matches('Post', '/users'));
    }

    public function testMatchesExactPath(): void
    {
        $route = new Route('GET', '/api/v1/users', fn() => null);

        $this->assertTrue($route->matches('GET', '/api/v1/users'));
        $this->assertFalse($route->matches('GET', '/api/v1/users/'));
        $this->assertFalse($route->matches('GET', '/api/v1/user'));
    }

    public function testParameterNamesWithUnderscores(): void
    {
        $route = new Route('GET', '/users/:user_id/posts/:post_id', fn() => null);

        $params = $route->extractParameters('/users/123/posts/456');

        $this->assertNotNull($params);
        $this->assertEquals('123', $params['user_id']);
        $this->assertEquals('456', $params['post_id']);
    }

    public function testParameterValuesCanContainNumbers(): void
    {
        $route = new Route('GET', '/users/:id', fn() => null);

        $this->assertTrue($route->matches('GET', '/users/123abc'));

        $params = $route->extractParameters('/users/123abc');
        $this->assertEquals('123abc', $params['id']);
    }

    public function testParameterValuesCannotContainSlashes(): void
    {
        $route = new Route('GET', '/users/:id', fn() => null);

        $this->assertFalse($route->matches('GET', '/users/123/456'));
    }
}
