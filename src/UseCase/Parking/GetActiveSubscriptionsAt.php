<?php

declare(strict_types=1);

namespace ParkingSystem\UseCase\Parking;

use ParkingSystem\Domain\Repository\ParkingRepositoryInterface;
use ParkingSystem\Domain\Repository\SubscriptionRepositoryInterface;

/**
 * GetActiveSubscriptionsAt Use Case
 * Use Case Layer - Gets subscriptions active at a specific time
 */
class GetActiveSubscriptionsAt
{
    public function __construct(
        private ParkingRepositoryInterface $parkingRepository,
        private SubscriptionRepositoryInterface $subscriptionRepository
    ) {
    }

    public function execute(
        GetActiveSubscriptionsAtRequest $request
    ): GetActiveSubscriptionsAtResponse {
        $this->validateRequest($request);

        // Verify parking exists
        if (!$this->parkingRepository->exists($request->parkingId)) {
            throw new ParkingNotFoundException(
                'Parking not found: ' . $request->parkingId
            );
        }

        $checkTime = \DateTimeImmutable::createFromInterface($request->dateTime);
        $dayOfWeek = (int)$checkTime->format('w');
        $timeOfDay = $checkTime->format('H:i');

        // Get all subscriptions for the parking
        $subscriptions = $this->subscriptionRepository
            ->findActiveSubscriptionsForParking($request->parkingId);

        $activeSubscriptions = [];

        foreach ($subscriptions as $subscription) {
            // Check if subscription covers this time slot
            if (!$subscription->coversTimeSlot($checkTime)) {
                continue;
            }

            // Find the specific slot that covers this time
            $coveredSlot = $this->findCoveredSlot(
                $subscription->getWeeklyTimeSlots(),
                $dayOfWeek,
                $timeOfDay
            );

            $activeSubscriptions[] = new ActiveSubscriptionInfo(
                $subscription->getId(),
                $subscription->getUserId(),
                $subscription->getStartDate()->format(\DateTimeInterface::ATOM),
                $subscription->getEndDate()->format(\DateTimeInterface::ATOM),
                $coveredSlot,
                $subscription->getMonthlyAmount(),
                $subscription->getRemainingDays()
            );
        }

        return new GetActiveSubscriptionsAtResponse(
            $request->parkingId,
            $checkTime->format(\DateTimeInterface::ATOM),
            count($activeSubscriptions),
            $activeSubscriptions
        );
    }

    private function validateRequest(GetActiveSubscriptionsAtRequest $request): void
    {
        if (empty($request->parkingId)) {
            throw new \InvalidArgumentException('Parking ID is required');
        }
    }

    private function findCoveredSlot(
        array $weeklyTimeSlots,
        int $dayOfWeek,
        string $timeOfDay
    ): array {
        if (!isset($weeklyTimeSlots[$dayOfWeek])) {
            return [];
        }

        foreach ($weeklyTimeSlots[$dayOfWeek] as $slot) {
            if ($timeOfDay >= $slot['start'] && $timeOfDay <= $slot['end']) {
                return [
                    'day' => $dayOfWeek,
                    'start' => $slot['start'],
                    'end' => $slot['end']
                ];
            }
        }

        return [];
    }
}
