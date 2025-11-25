<?php

declare(strict_types=1);

namespace ParkingSystem\UseCase\User;

/**
 * CreateUserRequest DTO
 * Use Case Layer - Input data transfer object
 */
class CreateUserRequest
{
    public function __construct(
        public readonly string $email,
        public readonly string $password,
        public readonly string $firstName,
        public readonly string $lastName
    ) {
    }
}