<?php

declare(strict_types=1);

namespace ParkingSystem\UseCase\User;

/**
 * JwtTokenGeneratorInterface
 * Use Case Layer - Service contract for JWT token generation
 */
interface JwtTokenGeneratorInterface
{
    public function generate(array $payload, int $expirationSeconds): string;
    
    public function verify(string $token): array;
    
    public function decode(string $token): array;
}