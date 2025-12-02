<?php

declare(strict_types=1);

namespace ParkingSystem\UseCase\Subscription;

/**
 * ValidateSubscriptionSlotsRequest DTO
 * Use Case Layer - Input for validating subscription time slots
 */
class ValidateSubscriptionSlotsRequest
{
    public function __construct(
        public readonly string $parkingId,
        public readonly array $weeklyTimeSlots,
        public readonly \DateTimeInterface $startDate,
        public readonly int $durationMonths
    ) {
    }
}
