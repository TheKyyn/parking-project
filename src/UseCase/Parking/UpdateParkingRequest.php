<?php

declare(strict_types=1);

namespace ParkingSystem\UseCase\Parking;

/**
 * UpdateParkingRequest DTO
 * Use Case Layer - Input data transfer object
 */
class UpdateParkingRequest
{
    public function __construct(
        public readonly string $parkingId,
        public readonly string $requesterId,
        public readonly ?string $name = null,
        public readonly ?string $address = null,
        public readonly ?int $totalSpaces = null,
        public readonly ?float $hourlyRate = null,
        public readonly ?array $openingHours = null
    ) {
    }
}
