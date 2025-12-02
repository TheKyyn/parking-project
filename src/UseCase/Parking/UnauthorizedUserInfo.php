<?php

declare(strict_types=1);

namespace ParkingSystem\UseCase\Parking;

/**
 * UnauthorizedUserInfo DTO
 * Information about an unauthorized parked user
 */
class UnauthorizedUserInfo
{
    public function __construct(
        public readonly string $sessionId,
        public readonly string $userId,
        public readonly string $startTime,
        public readonly int $durationMinutes,
        public readonly string $reason,
        public readonly float $estimatedPenalty
    ) {
    }
}
