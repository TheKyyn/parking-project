<?php

declare(strict_types=1);

namespace ParkingSystem\UseCase\Parking;

/**
 * DeleteParkingRequest DTO
 * Use Case Layer - Input data transfer object
 */
class DeleteParkingRequest
{
    public function __construct(
        public readonly string $parkingId,
        public readonly string $requesterId
    ) {
    }
}
