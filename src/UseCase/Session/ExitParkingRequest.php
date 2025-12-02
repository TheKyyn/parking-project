<?php

declare(strict_types=1);

namespace ParkingSystem\UseCase\Session;

/**
 * ExitParkingRequest DTO
 * Use Case Layer - Input data transfer object for exiting parking
 */
class ExitParkingRequest
{
    public function __construct(
        public readonly string $sessionId,
        public readonly ?\DateTimeInterface $exitTime = null
    ) {
    }
}
