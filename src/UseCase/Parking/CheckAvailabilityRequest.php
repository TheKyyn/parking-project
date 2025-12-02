<?php

declare(strict_types=1);

namespace ParkingSystem\UseCase\Parking;

/**
 * CheckAvailabilityRequest DTO
 * Use Case Layer - Input for checking parking availability
 */
class CheckAvailabilityRequest
{
    public function __construct(
        public readonly string $parkingId,
        public readonly \DateTimeInterface $dateTime
    ) {
    }
}
