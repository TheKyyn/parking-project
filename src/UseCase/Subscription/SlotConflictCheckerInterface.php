<?php

declare(strict_types=1);

namespace ParkingSystem\UseCase\Subscription;

/**
 * SlotConflictCheckerInterface
 * Use Case Layer - Contract for checking subscription slot conflicts
 */
interface SlotConflictCheckerInterface
{
    /**
     * Check if there are enough available slots for the subscription
     */
    public function hasAvailableSlots(
        string $parkingId,
        array $weeklyTimeSlots,
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate
    ): bool;

    /**
     * Get conflicting subscriptions for given time slots
     */
    public function getConflictingSubscriptions(
        string $parkingId,
        array $weeklyTimeSlots,
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate
    ): array;

    /**
     * Count available spaces at a given time slot
     */
    public function getAvailableSpacesForSlot(
        string $parkingId,
        int $dayOfWeek,
        string $startTime,
        string $endTime
    ): int;
}
