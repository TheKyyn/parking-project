<?php

declare(strict_types=1);

namespace ParkingSystem\UseCase\Session;

/**
 * EnterParkingRequest DTO
 * Use Case Layer - Input data transfer object for entering parking
 */
class EnterParkingRequest
{
    public function __construct(
        public readonly string $userId,
        public readonly string $parkingId,
        public readonly ?\DateTimeInterface $entryTime = null
    ) {
    }
}
