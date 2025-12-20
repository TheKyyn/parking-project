<?php

declare(strict_types=1);

namespace ParkingSystem\UseCase\Subscription;

class GetParkingSubscriptionsRequest
{
    public function __construct(
        public readonly string $parkingId,
        public readonly bool $activeOnly = false
    ) {
    }
}
