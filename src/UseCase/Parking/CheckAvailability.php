<?php

declare(strict_types=1);

namespace ParkingSystem\UseCase\Parking;

use ParkingSystem\Domain\Repository\ParkingRepositoryInterface;
use ParkingSystem\Domain\Repository\ReservationRepositoryInterface;
use ParkingSystem\Domain\Repository\ParkingSessionRepositoryInterface;
use ParkingSystem\Domain\Repository\SubscriptionRepositoryInterface;

/**
 * CheckAvailability Use Case
 * Use Case Layer - Complete availability calculation for a parking
 */
class CheckAvailability
{
    public function __construct(
        private ParkingRepositoryInterface $parkingRepository,
        private ReservationRepositoryInterface $reservationRepository,
        private ParkingSessionRepositoryInterface $sessionRepository,
        private SubscriptionRepositoryInterface $subscriptionRepository
    ) {
    }

    public function execute(CheckAvailabilityRequest $request): CheckAvailabilityResponse
    {
        $this->validateRequest($request);

        // Verify parking exists
        $parking = $this->parkingRepository->findById($request->parkingId);
        if ($parking === null) {
            throw new ParkingNotFoundException(
                'Parking not found: ' . $request->parkingId
            );
        }

        $checkTime = \DateTimeImmutable::createFromInterface($request->dateTime);
        $dayOfWeek = (int)$checkTime->format('w');

        // Check if parking is open
        $isOpen = $parking->isOpenAt($checkTime);
        $openingHours = $parking->getOpeningHours();
        $openingTime = null;
        $closingTime = null;

        if (!empty($openingHours) && isset($openingHours[$dayOfWeek])) {
            $openingTime = $openingHours[$dayOfWeek]['open'] ?? null;
            $closingTime = $openingHours[$dayOfWeek]['close'] ?? null;
        }

        // Count reserved spaces at the given time
        $reservedSpaces = $this->countReservedSpaces(
            $request->parkingId,
            $checkTime
        );

        // Count subscribed spaces at the given time
        $subscribedSpaces = $this->countSubscribedSpaces(
            $request->parkingId,
            $checkTime
        );

        // Count active session spaces
        $activeSessionSpaces = $this->countActiveSessionSpaces(
            $request->parkingId
        );

        // Calculate available spaces
        $occupiedSpaces = $reservedSpaces + $subscribedSpaces + $activeSessionSpaces;

        // Avoid double-counting: active sessions might be from reservations
        // So we take the max of (reserved + subscribed) and active sessions
        $effectiveOccupied = max(
            $reservedSpaces + $subscribedSpaces,
            $activeSessionSpaces
        );

        $availableSpaces = max(0, $parking->getTotalSpaces() - $effectiveOccupied);

        return new CheckAvailabilityResponse(
            $parking->getId(),
            $checkTime->format(\DateTimeInterface::ATOM),
            $parking->getTotalSpaces(),
            $availableSpaces,
            $reservedSpaces,
            $subscribedSpaces,
            $activeSessionSpaces,
            $isOpen,
            $parking->getHourlyRate(),
            $openingTime,
            $closingTime
        );
    }

    private function validateRequest(CheckAvailabilityRequest $request): void
    {
        if (empty($request->parkingId)) {
            throw new \InvalidArgumentException('Parking ID is required');
        }
    }

    private function countReservedSpaces(
        string $parkingId,
        \DateTimeImmutable $dateTime
    ): int {
        $reservations = $this->reservationRepository
            ->findActiveReservationsForParking($parkingId);

        $count = 0;
        foreach ($reservations as $reservation) {
            if ($reservation->isActiveAt($dateTime)) {
                $count++;
            }
        }

        return $count;
    }

    private function countSubscribedSpaces(
        string $parkingId,
        \DateTimeImmutable $dateTime
    ): int {
        $subscriptions = $this->subscriptionRepository
            ->findActiveSubscriptionsForParking($parkingId);

        $count = 0;
        foreach ($subscriptions as $subscription) {
            if ($subscription->coversTimeSlot($dateTime)) {
                $count++;
            }
        }

        return $count;
    }

    private function countActiveSessionSpaces(string $parkingId): int
    {
        $sessions = $this->sessionRepository
            ->findActiveSessionsForParking($parkingId);

        return count($sessions);
    }
}
