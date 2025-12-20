<?php

declare(strict_types=1);

namespace ParkingSystem\Tests\Unit\Infrastructure\Http\Middleware;

use ParkingSystem\Infrastructure\Http\Middleware\JwtAuthMiddleware;
use ParkingSystem\Infrastructure\Service\FirebaseJwtTokenGenerator;
use PHPUnit\Framework\TestCase;

class JwtAuthMiddlewareTest extends TestCase
{
    private const SECRET_KEY = 'test_secret_key_at_least_32_characters_long_for_security';
    private JwtAuthMiddleware $middleware;
    private FirebaseJwtTokenGenerator $jwtGenerator;

    protected function setUp(): void
    {
        $this->jwtGenerator = new FirebaseJwtTokenGenerator(self::SECRET_KEY);
        $this->middleware = new JwtAuthMiddleware($this->jwtGenerator);
    }

    public function testAuthenticatesWithValidToken(): void
    {
        $payload = [
            'userId' => 'user-123',
            'email' => 'user@example.com',
            'iat' => time(),
            'exp' => time() + 3600,
        ];

        $token = $this->jwtGenerator->generate($payload, 3600);
        $headers = ['Authorization' => 'Bearer ' . $token];

        $user = $this->middleware->authenticate($headers);

        $this->assertEquals('user-123', $user['userId']);
        $this->assertEquals('user@example.com', $user['email']);
    }

    public function testAuthenticatesCaseInsensitiveHeader(): void
    {
        $payload = [
            'userId' => 'user-123',
            'email' => 'user@example.com',
            'iat' => time(),
            'exp' => time() + 3600,
        ];

        $token = $this->jwtGenerator->generate($payload, 3600);
        $headers = ['authorization' => 'Bearer ' . $token]; // lowercase

        $user = $this->middleware->authenticate($headers);

        $this->assertEquals('user-123', $user['userId']);
    }

    public function testThrowsExceptionWhenAuthorizationHeaderMissing(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing or invalid Authorization header');

        $this->middleware->authenticate([]);
    }

    public function testThrowsExceptionWhenTokenMissing(): void
    {
        $headers = ['Authorization' => 'Bearer ']; // Empty token

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing or invalid Authorization header');

        $this->middleware->authenticate($headers);
    }

    public function testThrowsExceptionWhenBearerPrefixMissing(): void
    {
        $payload = [
            'userId' => 'user-123',
            'email' => 'user@example.com',
            'iat' => time(),
            'exp' => time() + 3600,
        ];

        $token = $this->jwtGenerator->generate($payload, 3600);
        $headers = ['Authorization' => $token]; // No "Bearer"

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing or invalid Authorization header');

        $this->middleware->authenticate($headers);
    }

    public function testThrowsExceptionForInvalidToken(): void
    {
        $headers = ['Authorization' => 'Bearer invalid.token.here'];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Authentication failed');

        $this->middleware->authenticate($headers);
    }

    public function testThrowsExceptionForExpiredToken(): void
    {
        $payload = [
            'userId' => 'user-123',
            'email' => 'user@example.com',
            'iat' => time() - 7200,
            'exp' => time() - 3600, // Expired
        ];

        $token = $this->jwtGenerator->generate($payload, 3600);
        $headers = ['Authorization' => 'Bearer ' . $token];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Authentication failed');

        $this->middleware->authenticate($headers);
    }

    public function testIsAuthenticatedReturnsTrueForValidToken(): void
    {
        $payload = [
            'userId' => 'user-123',
            'email' => 'user@example.com',
            'iat' => time(),
            'exp' => time() + 3600,
        ];

        $token = $this->jwtGenerator->generate($payload, 3600);
        $headers = ['Authorization' => 'Bearer ' . $token];

        $this->assertTrue($this->middleware->isAuthenticated($headers));
    }

    public function testIsAuthenticatedReturnsFalseForInvalidToken(): void
    {
        $headers = ['Authorization' => 'Bearer invalid.token'];

        $this->assertFalse($this->middleware->isAuthenticated($headers));
    }

    public function testIsAuthenticatedReturnsFalseWhenHeaderMissing(): void
    {
        $this->assertFalse($this->middleware->isAuthenticated([]));
    }

    public function testExtractsTokenFromValidHeader(): void
    {
        $token = 'valid.jwt.token';
        $headers = ['Authorization' => 'Bearer ' . $token];

        $extractedToken = $this->middleware->extractToken($headers);

        $this->assertEquals($token, $extractedToken);
    }

    public function testExtractsTokenCaseInsensitive(): void
    {
        $token = 'valid.jwt.token';
        $headers = ['AUTHORIZATION' => 'Bearer ' . $token];

        $extractedToken = $this->middleware->extractToken($headers);

        $this->assertEquals($token, $extractedToken);
    }

    public function testExtractTokenReturnsNullWhenHeaderMissing(): void
    {
        $this->assertNull($this->middleware->extractToken([]));
    }

    public function testExtractTokenReturnsNullForInvalidFormat(): void
    {
        $headers = ['Authorization' => 'InvalidFormat token'];

        $this->assertNull($this->middleware->extractToken($headers));
    }

    public function testExtractTokenHandlesBearerWithDifferentCasing(): void
    {
        $token = 'valid.jwt.token';
        $headers = ['Authorization' => 'bearer ' . $token]; // lowercase bearer

        $extractedToken = $this->middleware->extractToken($headers);

        $this->assertEquals($token, $extractedToken);
    }

    public function testExtractTokenHandlesExtraSpaces(): void
    {
        $token = 'valid.jwt.token';
        $headers = ['Authorization' => 'Bearer  ' . $token]; // Double space

        $extractedToken = $this->middleware->extractToken($headers);

        $this->assertNotNull($extractedToken);
    }
}
