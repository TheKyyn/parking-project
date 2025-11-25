<?php

declare(strict_types=1);

namespace ParkingSystem\UseCase\User;

/**
 * AuthenticateUserRequest DTO
 * Use Case Layer - Input data transfer object
 */
class AuthenticateUserRequest
{
    public function __construct(
        public readonly string $email,
        public readonly string $password
    ) {
    }
}