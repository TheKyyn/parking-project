<?php

declare(strict_types=1);

namespace ParkingSystem\Infrastructure\Http\Middleware;

/**
 * Interface for authentication middleware
 */
interface AuthMiddlewareInterface
{
    /**
     * Authenticate an HTTP request
     *
     * Extracts and validates JWT token from Authorization header
     *
     * @param array<string, string> $headers HTTP request headers
     * @return array{userId: string, email: string} Authenticated user data
     * @throws \InvalidArgumentException If authentication fails
     */
    public function authenticate(array $headers): array;

    /**
     * Check if a request is authenticated
     *
     * @param array<string, string> $headers HTTP headers
     * @return bool True if authenticated
     */
    public function isAuthenticated(array $headers): bool;

    /**
     * Extract token from Authorization header
     *
     * Expected format: "Authorization: Bearer <token>"
     *
     * @param array<string, string> $headers HTTP headers
     * @return string|null Token or null if absent/invalid
     */
    public function extractToken(array $headers): ?string;
}
