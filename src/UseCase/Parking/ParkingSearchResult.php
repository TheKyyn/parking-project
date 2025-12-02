<?php

declare(strict_types=1);

namespace ParkingSystem\UseCase\Parking;

/**
 * ParkingSearchResult DTO
 * Use Case Layer - Search result representation
 */
class ParkingSearchResult
{
    public function __construct(
        public readonly string $parkingId,
        public readonly float $latitude,
        public readonly float $longitude,
        public readonly int $totalSpaces,
        public readonly int $availableSpaces,
        public readonly float $hourlyRate,
        public readonly float $distanceInKilometers,
        public readonly bool $isOpenNow,
        public readonly array $openingHours
    ) {
    }
}