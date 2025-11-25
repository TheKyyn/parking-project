<?php

declare(strict_types=1);

namespace ParkingSystem\UseCase\Parking;

/**
 * UpdateParkingRatesRequest DTO
 * Use Case Layer - Input data transfer object
 */
class UpdateParkingRatesRequest
{
    public function __construct(
        public readonly string $parkingId,
        public readonly string $requesterId,
        public readonly float $newHourlyRate
    ) {
    }
}