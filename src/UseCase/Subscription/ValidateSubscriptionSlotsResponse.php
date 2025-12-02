<?php

declare(strict_types=1);

namespace ParkingSystem\UseCase\Subscription;

/**
 * ValidateSubscriptionSlotsResponse DTO
 * Use Case Layer - Output for validating subscription time slots
 */
class ValidateSubscriptionSlotsResponse
{
    public function __construct(
        public readonly bool $isValid,
        public readonly array $validSlots,
        public readonly array $conflictingSlots,
        public readonly float $estimatedMonthlyPrice,
        public readonly float $estimatedTotalPrice,
        public readonly array $availabilityBySlot
    ) {
    }
}
