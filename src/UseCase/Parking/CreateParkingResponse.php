<?php

declare(strict_types=1);

namespace ParkingSystem\UseCase\Parking;

/**
 * CreateParkingResponse DTO
 * Use Case Layer - Output data transfer object
 */
class CreateParkingResponse
{
    public function __construct(
        public readonly string $parkingId,
        public readonly string $ownerId,
        public readonly float $latitude,
        public readonly float $longitude,
        public readonly int $totalSpaces,
        public readonly float $hourlyRate,
        public readonly string $createdAt
    ) {
    }
}