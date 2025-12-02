<?php

declare(strict_types=1);

namespace ParkingSystem\Infrastructure\Http\Middleware;

use ParkingSystem\UseCase\User\JwtTokenGeneratorInterface;

/**
 * JWT authentication middleware
 */
class JwtAuthMiddleware implements AuthMiddlewareInterface
{
    private JwtTokenGeneratorInterface $jwtGenerator;

    public function __construct(JwtTokenGeneratorInterface $jwtGenerator)
    {
        $this->jwtGenerator = $jwtGenerator;
    }

    /**
     * {@inheritDoc}
     */
    public function authenticate(array $headers): array
    {
        $token = $this->extractToken($headers);

        if ($token === null) {
            throw new \InvalidArgumentException('Missing or invalid Authorization header');
        }

        try {
            $payload = $this->jwtGenerator->verify($token);

            // Verify required fields are present
            if (!isset($payload['userId'], $payload['email'])) {
                throw new \InvalidArgumentException('Invalid JWT payload: missing required fields');
            }

            return [
                'userId' => $payload['userId'],
                'email' => $payload['email'],
            ];

        } catch (\InvalidArgumentException $e) {
            throw new \InvalidArgumentException('Authentication failed: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function isAuthenticated(array $headers): bool
    {
        try {
            $this->authenticate($headers);
            return true;
        } catch (\InvalidArgumentException $e) {
            return false;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function extractToken(array $headers): ?string
    {
        // Normalize headers (case-insensitive)
        $normalizedHeaders = array_change_key_case($headers, CASE_LOWER);

        // Look for Authorization header
        $authHeader = $normalizedHeaders['authorization'] ?? null;

        if ($authHeader === null) {
            return null;
        }

        // Expected format: "Bearer <token>"
        if (!preg_match('/^Bearer\s+(.+)$/i', $authHeader, $matches)) {
            return null;
        }

        return $matches[1];
    }
}
