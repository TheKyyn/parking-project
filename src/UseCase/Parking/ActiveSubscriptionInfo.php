<?php

declare(strict_types=1);

namespace ParkingSystem\UseCase\Parking;

/**
 * ActiveSubscriptionInfo DTO
 * Information about an active subscription at a given time
 */
class ActiveSubscriptionInfo
{
    public function __construct(
        public readonly string $subscriptionId,
        public readonly string $userId,
        public readonly string $startDate,
        public readonly string $endDate,
        public readonly array $coveredSlot,
        public readonly float $monthlyAmount,
        public readonly int $remainingDays
    ) {
    }
}
