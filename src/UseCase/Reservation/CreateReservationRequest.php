<?php

declare(strict_types=1);

namespace ParkingSystem\UseCase\Reservation;

/**
 * CreateReservationRequest DTO
 * Use Case Layer - Input data transfer object
 */
class CreateReservationRequest
{
    public function __construct(
        public readonly string $userId,
        public readonly string $parkingId,
        public readonly \DateTimeInterface $startTime,
        public readonly \DateTimeInterface $endTime
    ) {
    }
}