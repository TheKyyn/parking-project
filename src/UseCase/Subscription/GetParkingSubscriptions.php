<?php

declare(strict_types=1);

namespace ParkingSystem\UseCase\Subscription;

use ParkingSystem\Domain\Repository\SubscriptionRepositoryInterface;
use ParkingSystem\Domain\Repository\ParkingRepositoryInterface;

class GetParkingSubscriptions
{
    public function __construct(
        private SubscriptionRepositoryInterface $subscriptionRepository,
        private ParkingRepositoryInterface $parkingRepository
    ) {
    }

    public function execute(GetParkingSubscriptionsRequest $request): GetParkingSubscriptionsResponse
    {
        $this->validateRequest($request);

        $parking = $this->parkingRepository->findById($request->parkingId);
        if ($parking === null) {
            throw new ParkingNotFoundException('Parking not found: ' . $request->parkingId);
        }

        $subscriptions = $this->subscriptionRepository->findByParkingId($request->parkingId);

        if ($request->activeOnly) {
            $subscriptions = array_filter($subscriptions, fn($s) => $s->isActive());
        }

        $subscriptionData = array_map(function ($subscription) {
            return new SubscriptionInfo(
                $subscription->getId(),
                $subscription->getUserId(),
                $subscription->getParkingId(),
                $subscription->getWeeklyTimeSlots(),
                $subscription->getDurationMonths(),
                $subscription->getStartDate()->format(\DateTimeInterface::ATOM),
                $subscription->getEndDate()->format(\DateTimeInterface::ATOM),
                $subscription->getMonthlyAmount(),
                $subscription->getStatus()
            );
        }, $subscriptions);

        return new GetParkingSubscriptionsResponse(
            $request->parkingId,
            count($subscriptionData),
            array_values($subscriptionData)
        );
    }

    private function validateRequest(GetParkingSubscriptionsRequest $request): void
    {
        if (empty($request->parkingId)) {
            throw new \InvalidArgumentException('Parking ID is required');
        }
    }
}
