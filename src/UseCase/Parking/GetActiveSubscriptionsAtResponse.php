<?php

declare(strict_types=1);

namespace ParkingSystem\UseCase\Parking;

/**
 * GetActiveSubscriptionsAtResponse DTO
 * Use Case Layer - Output for active subscriptions at a time
 */
class GetActiveSubscriptionsAtResponse
{
    /**
     * @param ActiveSubscriptionInfo[] $subscriptions
     */
    public function __construct(
        public readonly string $parkingId,
        public readonly string $checkedAt,
        public readonly int $totalActive,
        public readonly array $subscriptions
    ) {
    }
}
