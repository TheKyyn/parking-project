<?php

declare(strict_types=1);

namespace ParkingSystem\UseCase\Subscription;

/**
 * CreateSubscriptionResponse DTO
 * Use Case Layer - Output data transfer object for creating subscription
 */
class CreateSubscriptionResponse
{
    public function __construct(
        public readonly string $subscriptionId,
        public readonly string $userId,
        public readonly string $parkingId,
        public readonly array $weeklyTimeSlots,
        public readonly int $durationMonths,
        public readonly string $startDate,
        public readonly string $endDate,
        public readonly float $monthlyAmount,
        public readonly float $totalAmount,
        public readonly string $status
    ) {
    }
}
