<?php

declare(strict_types=1);

namespace ParkingSystem\UseCase\Parking;

/**
 * SearchParkingByLocationRequest DTO
 * Use Case Layer - Input data transfer object
 */
class SearchParkingByLocationRequest
{
    public function __construct(
        public readonly float $latitude,
        public readonly float $longitude,
        public readonly float $radiusInKilometers,
        public readonly ?\DateTimeInterface $startTime = null,
        public readonly ?\DateTimeInterface $endTime = null,
        public readonly ?float $maxHourlyRate = null,
        public readonly ?int $minimumSpaces = null,
        public readonly int $limit = 10
    ) {
    }
}