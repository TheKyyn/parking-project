<?php

declare(strict_types=1);

namespace ParkingSystem\UseCase\Parking;

/**
 * GetActiveSubscriptionsAtRequest DTO
 * Use Case Layer - Input for getting active subscriptions at a time
 */
class GetActiveSubscriptionsAtRequest
{
    public function __construct(
        public readonly string $parkingId,
        public readonly \DateTimeInterface $dateTime
    ) {
    }
}
