<?php

declare(strict_types=1);

namespace ParkingSystem\Tests\Unit\Infrastructure\Service;

use ParkingSystem\Infrastructure\Service\FirebaseJwtTokenGenerator;
use PHPUnit\Framework\TestCase;

class FirebaseJwtTokenGeneratorTest extends TestCase
{
    private const SECRET_KEY = 'test_secret_key_at_least_32_characters_long_for_security';
    private FirebaseJwtTokenGenerator $generator;

    protected function setUp(): void
    {
        $this->generator = new FirebaseJwtTokenGenerator(self::SECRET_KEY);
    }

    public function testGeneratesValidToken(): void
    {
        $payload = [
            'userId' => 'user-123',
            'email' => 'user@example.com',
            'iat' => time(),
            'exp' => time() + 3600,
        ];

        $token = $this->generator->generate($payload, 3600);

        $this->assertIsString($token);
        $this->assertNotEmpty($token);

        // JWT format: header.payload.signature
        $parts = explode('.', $token);
        $this->assertCount(3, $parts);
    }

    public function testVerifiesCorrectToken(): void
    {
        $userId = 'user-123';
        $email = 'user@example.com';
        $iat = time();
        $exp = time() + 3600;

        $payload = [
            'userId' => $userId,
            'email' => $email,
            'iat' => $iat,
            'exp' => $exp,
        ];

        $token = $this->generator->generate($payload, 3600);
        $decoded = $this->generator->verify($token);

        $this->assertEquals($userId, $decoded['userId']);
        $this->assertEquals($email, $decoded['email']);
        $this->assertEquals($exp, $decoded['exp']);
        $this->assertEquals($iat, $decoded['iat']);
    }

    public function testTokenExpirationIsCorrect(): void
    {
        $exp = time() + 3600;
        $payload = [
            'userId' => 'user-123',
            'email' => 'user@example.com',
            'iat' => time(),
            'exp' => $exp,
        ];

        $token = $this->generator->generate($payload, 3600);
        $decoded = $this->generator->verify($token);

        $this->assertEquals($exp, $decoded['exp']);
    }

    public function testRejectsTokenWithInvalidSignature(): void
    {
        $payload = [
            'userId' => 'user-123',
            'email' => 'user@example.com',
            'iat' => time(),
            'exp' => time() + 3600,
        ];

        $token = $this->generator->generate($payload, 3600);

        // Tamper with token (change last character)
        $tamperedToken = substr($token, 0, -1) . 'X';

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('JWT signature verification failed');

        $this->generator->verify($tamperedToken);
    }

    public function testRejectsExpiredToken(): void
    {
        $payload = [
            'userId' => 'user-123',
            'email' => 'user@example.com',
            'iat' => time() - 7200, // 2 hours ago
            'exp' => time() - 3600, // Expired 1 hour ago
        ];

        $token = $this->generator->generate($payload, 3600);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('JWT token has expired');

        $this->generator->verify($token);
    }

    public function testRejectsTokenFromDifferentSecret(): void
    {
        $payload = [
            'userId' => 'user-123',
            'email' => 'user@example.com',
            'iat' => time(),
            'exp' => time() + 3600,
        ];

        $token = $this->generator->generate($payload, 3600);

        // Generate with different key
        $otherGenerator = new FirebaseJwtTokenGenerator('different_secret_key_32_chars_min');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('JWT signature verification failed');

        $otherGenerator->verify($token);
    }

    public function testRejectsMalformedToken(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid JWT token');

        $this->generator->verify('not.a.valid.jwt.token');
    }

    public function testRejectsEmptyToken(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->generator->verify('');
    }

    public function testThrowsExceptionForShortSecretKey(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('JWT secret key must be at least 32 characters long');

        new FirebaseJwtTokenGenerator('short_key');
    }

    public function testThrowsExceptionForTooShortExpiration(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('JWT expiration must be at least 60 seconds');

        $payload = [
            'userId' => 'user-123',
            'email' => 'user@example.com',
            'iat' => time(),
            'exp' => time() + 30,
        ];

        $this->generator->generate($payload, 30);
    }

    public function testGeneratesTokenWithAdditionalClaims(): void
    {
        $payload = [
            'userId' => 'user-123',
            'email' => 'user@example.com',
            'iat' => time(),
            'exp' => time() + 3600,
            'role' => 'admin',
            'permissions' => ['read', 'write'],
        ];

        $token = $this->generator->generate($payload, 3600);
        $decoded = $this->generator->verify($token);

        $this->assertEquals('admin', $decoded['role']);
        $this->assertEquals(['read', 'write'], $decoded['permissions']);
    }

    public function testDecodesTokenWithoutVerification(): void
    {
        $userId = 'user-123';
        $payload = [
            'userId' => $userId,
            'email' => 'user@example.com',
            'iat' => time(),
            'exp' => time() + 3600,
        ];

        $token = $this->generator->generate($payload, 3600);
        $decoded = $this->generator->decode($token);

        $this->assertEquals($userId, $decoded['userId']);
    }

    public function testExtractsUserIdFromValidToken(): void
    {
        $userId = 'user-123';
        $payload = [
            'userId' => $userId,
            'email' => 'user@example.com',
            'iat' => time(),
            'exp' => time() + 3600,
        ];

        $token = $this->generator->generate($payload, 3600);
        $extractedUserId = $this->generator->extractUserId($token);

        $this->assertEquals($userId, $extractedUserId);
    }

    public function testExtractsUserIdFromExpiredToken(): void
    {
        // Even expired tokens can have userId extracted
        $userId = 'user-123';
        $payload = [
            'userId' => $userId,
            'email' => 'user@example.com',
            'iat' => time() - 7200,
            'exp' => time() - 3600, // Expired
        ];

        $token = $this->generator->generate($payload, 3600);
        $extractedUserId = $this->generator->extractUserId($token);

        $this->assertEquals($userId, $extractedUserId);
    }

    public function testExtractUserIdReturnsNullForInvalidToken(): void
    {
        $userId = $this->generator->extractUserId('invalid.token');

        $this->assertNull($userId);
    }

    public function testGeneratesDifferentTokensForSamePayload(): void
    {
        $time1 = time();
        $payload1 = [
            'userId' => 'user-123',
            'email' => 'user@example.com',
            'iat' => $time1,
            'exp' => $time1 + 3600,
        ];

        $token1 = $this->generator->generate($payload1, 3600);

        // Wait for time to change
        sleep(1);

        $time2 = time();
        $payload2 = [
            'userId' => 'user-123',
            'email' => 'user@example.com',
            'iat' => $time2,
            'exp' => $time2 + 3600,
        ];

        $token2 = $this->generator->generate($payload2, 3600);

        $this->assertNotEquals($token1, $token2);
    }

    public function testTokenIsUrlSafe(): void
    {
        $payload = [
            'userId' => 'user-123',
            'email' => 'user@example.com',
            'iat' => time(),
            'exp' => time() + 3600,
        ];

        $token = $this->generator->generate($payload, 3600);

        // JWT uses base64url encoding (no +, /, =)
        $this->assertStringNotContainsString('+', $token);
        $this->assertStringNotContainsString('/', $token);
    }
}
