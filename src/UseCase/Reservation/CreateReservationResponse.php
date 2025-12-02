<?php

declare(strict_types=1);

namespace ParkingSystem\UseCase\Reservation;

/**
 * CreateReservationResponse DTO
 * Use Case Layer - Output data transfer object
 */
class CreateReservationResponse
{
    public function __construct(
        public readonly string $reservationId,
        public readonly string $userId,
        public readonly string $parkingId,
        public readonly string $startTime,
        public readonly string $endTime,
        public readonly float $totalAmount,
        public readonly int $durationMinutes,
        public readonly string $status
    ) {
    }
}