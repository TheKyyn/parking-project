<?php

declare(strict_types=1);

namespace ParkingSystem\UseCase\ParkingOwner;

/**
 * CreateParkingOwnerResponse DTO
 * Use Case Layer - Output data transfer object
 */
class CreateParkingOwnerResponse
{
    public function __construct(
        public readonly string $ownerId,
        public readonly string $email,
        public readonly string $fullName,
        public readonly string $createdAt
    ) {
    }
}
