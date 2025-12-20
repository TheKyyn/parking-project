<?php

declare(strict_types=1);

namespace ParkingSystem\UseCase\ParkingOwner;

/**
 * CreateParkingOwnerRequest DTO
 * Use Case Layer - Input data transfer object
 */
class CreateParkingOwnerRequest
{
    public function __construct(
        public readonly string $email,
        public readonly string $password,
        public readonly string $firstName,
        public readonly string $lastName
    ) {
    }
}
