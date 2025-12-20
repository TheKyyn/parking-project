<?php

declare(strict_types=1);

namespace ParkingSystem\UseCase\ParkingOwner;

/**
 * UpdateParkingOwnerRequest DTO
 * Use Case Layer - Input data transfer object
 */
class UpdateParkingOwnerRequest
{
    public function __construct(
        public readonly string $ownerId,
        public readonly string $firstName,
        public readonly string $lastName
    ) {
    }
}
