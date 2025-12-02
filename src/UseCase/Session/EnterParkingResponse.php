<?php

declare(strict_types=1);

namespace ParkingSystem\UseCase\Session;

/**
 * EnterParkingResponse DTO
 * Use Case Layer - Output data transfer object for entering parking
 */
class EnterParkingResponse
{
    public function __construct(
        public readonly string $sessionId,
        public readonly string $userId,
        public readonly string $parkingId,
        public readonly ?string $reservationId,
        public readonly string $startTime,
        public readonly ?string $authorizedEndTime,
        public readonly string $status
    ) {
    }
}
