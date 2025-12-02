<?php

declare(strict_types=1);

namespace ParkingSystem\UseCase\Subscription;

/**
 * CreateSubscriptionRequest DTO
 * Use Case Layer - Input data transfer object for creating subscription
 */
class CreateSubscriptionRequest
{
    public function __construct(
        public readonly string $userId,
        public readonly string $parkingId,
        public readonly array $weeklyTimeSlots,
        public readonly int $durationMonths,
        public readonly \DateTimeInterface $startDate
    ) {
    }
}
