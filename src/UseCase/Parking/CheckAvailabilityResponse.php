<?php

declare(strict_types=1);

namespace ParkingSystem\UseCase\Parking;

/**
 * CheckAvailabilityResponse DTO
 * Use Case Layer - Output for parking availability check
 */
class CheckAvailabilityResponse
{
    public function __construct(
        public readonly string $parkingId,
        public readonly string $checkedAt,
        public readonly int $totalSpaces,
        public readonly int $availableSpaces,
        public readonly int $reservedSpaces,
        public readonly int $subscribedSpaces,
        public readonly int $activeSessionSpaces,
        public readonly bool $isOpen,
        public readonly float $currentHourlyRate,
        public readonly ?string $openingTime,
        public readonly ?string $closingTime
    ) {
    }
}
