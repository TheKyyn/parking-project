<?php

declare(strict_types=1);

namespace ParkingSystem\Infrastructure\Service;

use ParkingSystem\UseCase\User\JwtTokenGeneratorInterface;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;
use Firebase\JWT\BeforeValidException;

/**
 * JWT token generator using firebase/php-jwt
 */
class FirebaseJwtTokenGenerator implements JwtTokenGeneratorInterface
{
    private const ALGORITHM = 'HS256';

    private string $secretKey;

    /**
     * @param string $secretKey Secret key for signing tokens (min 32 characters recommended)
     */
    public function __construct(string $secretKey)
    {
        if (strlen($secretKey) < 32) {
            throw new \InvalidArgumentException('JWT secret key must be at least 32 characters long');
        }

        $this->secretKey = $secretKey;
    }

    /**
     * {@inheritDoc}
     */
    public function generate(array $payload, int $expirationSeconds): string
    {
        if ($expirationSeconds < 60) {
            throw new \InvalidArgumentException('JWT expiration must be at least 60 seconds');
        }

        // The payload already contains iat and exp from the use case
        // Just encode it
        return JWT::encode($payload, $this->secretKey, self::ALGORITHM);
    }

    /**
     * {@inheritDoc}
     */
    public function verify(string $token): array
    {
        try {
            $decoded = JWT::decode($token, new Key($this->secretKey, self::ALGORITHM));

            // Convert stdClass to array
            return (array) $decoded;

        } catch (ExpiredException $e) {
            throw new \InvalidArgumentException('JWT token has expired', 0, $e);
        } catch (SignatureInvalidException $e) {
            throw new \InvalidArgumentException('JWT signature verification failed', 0, $e);
        } catch (BeforeValidException $e) {
            throw new \InvalidArgumentException('JWT token not yet valid', 0, $e);
        } catch (\Exception $e) {
            throw new \InvalidArgumentException('Invalid JWT token: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function decode(string $token): array
    {
        // Decode without verification (for middleware extraction)
        try {
            $parts = explode('.', $token);

            if (count($parts) !== 3) {
                throw new \InvalidArgumentException('Invalid JWT format');
            }

            $payload = json_decode(base64_decode($parts[1]), true);

            if ($payload === null) {
                throw new \InvalidArgumentException('Invalid JWT payload');
            }

            return $payload;

        } catch (\Exception $e) {
            throw new \InvalidArgumentException('Failed to decode JWT token: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Extract userId from token without full validation
     * Useful for logging before authentication
     *
     * @param string $token JWT token
     * @return string|null UserId or null if extraction impossible
     */
    public function extractUserId(string $token): ?string
    {
        try {
            $payload = $this->decode($token);
            return $payload['userId'] ?? null;
        } catch (\Exception $e) {
            return null;
        }
    }
}
