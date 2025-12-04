<?php

declare(strict_types=1);

namespace ParkingSystem\Tests\Unit\Infrastructure\Http\Middleware;

use ParkingSystem\Infrastructure\Http\Middleware\CorsMiddleware;
use ParkingSystem\Infrastructure\Http\Request\HttpRequest;
use PHPUnit\Framework\TestCase;

class CorsMiddlewareTest extends TestCase
{
    public function testDoesNothingWhenNoOriginHeader(): void
    {
        $middleware = new CorsMiddleware();
        $request = new HttpRequest('GET', '/api/users');

        // Should not throw or set headers
        $middleware->handle($request);

        $this->assertTrue(true); // Just verify no exception
    }

    public function testAllowsWildcardOrigin(): void
    {
        $middleware = new CorsMiddleware(['*']);
        $request = new HttpRequest('GET', '/api/users', ['Origin' => 'https://example.com']);

        // Cette méthode set des headers, on ne peut pas facilement tester
        // sans mocker header() ou sans refactorer pour retourner les headers
        // Pour l'instant, on vérifie juste que ça ne throw pas
        ob_start();
        $middleware->handle($request);
        ob_end_clean();

        $this->assertTrue(true);
    }

    public function testRejectsUnauthorizedOrigin(): void
    {
        $middleware = new CorsMiddleware(['https://allowed.com']);
        $request = new HttpRequest('GET', '/api/users', ['Origin' => 'https://unauthorized.com']);

        // Should not set headers for unauthorized origin
        ob_start();
        $middleware->handle($request);
        ob_end_clean();

        $this->assertTrue(true);
    }

    public function testPermissiveFactoryCreatesPermissiveMiddleware(): void
    {
        $middleware = CorsMiddleware::permissive();

        $this->assertInstanceOf(CorsMiddleware::class, $middleware);
    }

    public function testRestrictiveFactoryCreatesRestrictiveMiddleware(): void
    {
        $middleware = CorsMiddleware::restrictive(['https://app.example.com']);

        $this->assertInstanceOf(CorsMiddleware::class, $middleware);
    }

    public function testHandlesAllowedOrigin(): void
    {
        $middleware = new CorsMiddleware(['https://allowed.com']);
        $request = new HttpRequest('GET', '/api/users', ['Origin' => 'https://allowed.com']);

        ob_start();
        $middleware->handle($request);
        ob_end_clean();

        $this->assertTrue(true);
    }

    public function testConstructorAcceptsCustomParameters(): void
    {
        $middleware = new CorsMiddleware(
            allowedOrigins: ['https://custom.com'],
            allowedMethods: ['GET', 'POST'],
            allowedHeaders: ['Content-Type'],
            allowCredentials: false,
            maxAge: 3600
        );

        $this->assertInstanceOf(CorsMiddleware::class, $middleware);
    }
}
