<?php

declare(strict_types=1);

namespace ParkingSystem\Infrastructure\Service;

use ParkingSystem\UseCase\Subscription\SlotConflictCheckerInterface;
use ParkingSystem\Domain\Repository\SubscriptionRepositoryInterface;

class SubscriptionSlotConflictChecker implements SlotConflictCheckerInterface
{
    public function __construct(
        private SubscriptionRepositoryInterface $subscriptionRepository
    ) {
    }

    public function hasAvailableSlots(
        string $parkingId,
        array $weeklyTimeSlots,
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate
    ): bool {
        $conflicting = $this->getConflictingSubscriptions(
            $parkingId,
            $weeklyTimeSlots,
            $startDate,
            $endDate
        );

        return empty($conflicting);
    }

    public function getConflictingSubscriptions(
        string $parkingId,
        array $weeklyTimeSlots,
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate
    ): array {
        $activeSubscriptions = $this->subscriptionRepository
            ->findActiveSubscriptionsForParking($parkingId);

        $conflicts = [];

        foreach ($activeSubscriptions as $subscription) {
            if ($this->hasTimeOverlap($subscription, $weeklyTimeSlots, $startDate, $endDate)) {
                $conflicts[] = $subscription;
            }
        }

        return $conflicts;
    }

    public function getAvailableSpacesForSlot(
        string $parkingId,
        int $dayOfWeek,
        string $startTime,
        string $endTime
    ): int {
        $activeSubscriptions = $this->subscriptionRepository
            ->findActiveSubscriptionsForParking($parkingId);

        $occupiedSlots = 0;

        foreach ($activeSubscriptions as $subscription) {
            $slots = $subscription->getWeeklyTimeSlots();
            if (isset($slots[$dayOfWeek])) {
                foreach ($slots[$dayOfWeek] as $slot) {
                    if ($this->timesOverlap($startTime, $endTime, $slot['start'], $slot['end'])) {
                        $occupiedSlots++;
                        break;
                    }
                }
            }
        }

        return max(0, 100 - $occupiedSlots);
    }

    private function hasTimeOverlap(
        $subscription,
        array $weeklyTimeSlots,
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate
    ): bool {
        if (!$this->datesOverlap(
            $subscription->getStartDate(),
            $subscription->getEndDate(),
            $startDate,
            $endDate
        )) {
            return false;
        }

        $existingSlots = $subscription->getWeeklyTimeSlots();

        foreach ($weeklyTimeSlots as $dayOfWeek => $slots) {
            if (!isset($existingSlots[$dayOfWeek])) {
                continue;
            }

            foreach ($slots as $newSlot) {
                foreach ($existingSlots[$dayOfWeek] as $existingSlot) {
                    if ($this->timesOverlap(
                        $newSlot['start'],
                        $newSlot['end'],
                        $existingSlot['start'],
                        $existingSlot['end']
                    )) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    private function datesOverlap(
        \DateTimeInterface $start1,
        \DateTimeInterface $end1,
        \DateTimeInterface $start2,
        \DateTimeInterface $end2
    ): bool {
        return $start1 <= $end2 && $end1 >= $start2;
    }

    private function timesOverlap(
        string $start1,
        string $end1,
        string $start2,
        string $end2
    ): bool {
        return $start1 < $end2 && $end1 > $start2;
    }
}
