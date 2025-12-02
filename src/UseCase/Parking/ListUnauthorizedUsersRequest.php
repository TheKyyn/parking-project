<?php

declare(strict_types=1);

namespace ParkingSystem\UseCase\Parking;

/**
 * ListUnauthorizedUsersRequest DTO
 * Use Case Layer - Input for listing unauthorized parked users
 */
class ListUnauthorizedUsersRequest
{
    public function __construct(
        public readonly string $parkingId,
        public readonly ?\DateTimeInterface $asOfTime = null
    ) {
    }
}
