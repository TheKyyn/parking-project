<?php

declare(strict_types=1);

namespace ParkingSystem\UseCase\Session;

/**
 * ExitParkingResponse DTO
 * Use Case Layer - Output data transfer object for exiting parking
 */
class ExitParkingResponse
{
    public function __construct(
        public readonly string $sessionId,
        public readonly string $userId,
        public readonly string $parkingId,
        public readonly string $startTime,
        public readonly string $endTime,
        public readonly int $durationMinutes,
        public readonly float $baseAmount,
        public readonly float $overstayPenalty,
        public readonly float $totalAmount,
        public readonly bool $wasOverstayed,
        public readonly string $status
    ) {
    }
}
