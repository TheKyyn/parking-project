<?php

declare(strict_types=1);

namespace ParkingSystem\UseCase\Parking;

/**
 * CreateParkingRequest DTO
 * Use Case Layer - Input data transfer object
 */
class CreateParkingRequest
{
    public function __construct(
        public readonly string $ownerId,
        public readonly float $latitude,
        public readonly float $longitude,
        public readonly int $totalSpaces,
        public readonly float $hourlyRate,
        public readonly array $openingHours = []
    ) {
    }
}