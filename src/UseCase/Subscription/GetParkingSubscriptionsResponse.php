<?php

declare(strict_types=1);

namespace ParkingSystem\UseCase\Subscription;

class GetParkingSubscriptionsResponse
{
    public function __construct(
        public readonly string $parkingId,
        public readonly int $totalCount,
        public readonly array $subscriptions
    ) {
    }
}
