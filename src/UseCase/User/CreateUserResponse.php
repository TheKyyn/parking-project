<?php

declare(strict_types=1);

namespace ParkingSystem\UseCase\User;

/**
 * CreateUserResponse DTO
 * Use Case Layer - Output data transfer object
 */
class CreateUserResponse
{
    public function __construct(
        public readonly string $userId,
        public readonly string $email,
        public readonly string $fullName,
        public readonly string $createdAt
    ) {
    }
}