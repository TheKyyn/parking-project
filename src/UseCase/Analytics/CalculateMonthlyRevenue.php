<?php

declare(strict_types=1);

namespace ParkingSystem\UseCase\Analytics;

use ParkingSystem\Domain\Repository\ParkingRepositoryInterface;
use ParkingSystem\Domain\Repository\ReservationRepositoryInterface;
use ParkingSystem\Domain\Repository\ParkingSessionRepositoryInterface;
use ParkingSystem\Domain\Repository\SubscriptionRepositoryInterface;

/**
 * CalculateMonthlyRevenue Use Case
 * Use Case Layer - Calculates monthly revenue for a parking
 */
class CalculateMonthlyRevenue
{
    public function __construct(
        private ParkingRepositoryInterface $parkingRepository,
        private ReservationRepositoryInterface $reservationRepository,
        private ParkingSessionRepositoryInterface $sessionRepository,
        private SubscriptionRepositoryInterface $subscriptionRepository
    ) {
    }

    public function execute(
        CalculateMonthlyRevenueRequest $request
    ): CalculateMonthlyRevenueResponse {
        $this->validateRequest($request);

        // Verify parking exists
        if (!$this->parkingRepository->exists($request->parkingId)) {
            throw new ParkingNotFoundException(
                'Parking not found: ' . $request->parkingId
            );
        }

        // Calculate date range for the month
        $startDate = new \DateTimeImmutable(
            sprintf('%d-%02d-01 00:00:00', $request->year, $request->month)
        );
        $endDate = $startDate->modify('last day of this month 23:59:59');

        // Calculate reservation revenue
        $reservationData = $this->calculateReservationRevenue(
            $request->parkingId,
            $startDate,
            $endDate
        );

        // Calculate session revenue and penalties
        $sessionData = $this->calculateSessionRevenue(
            $request->parkingId,
            $startDate,
            $endDate
        );

        // Calculate subscription revenue
        $subscriptionData = $this->calculateSubscriptionRevenue(
            $request->parkingId,
            $startDate,
            $endDate
        );

        $totalRevenue = $reservationData['revenue'] +
                        $sessionData['revenue'] +
                        $subscriptionData['revenue'] +
                        $sessionData['penalties'];

        return new CalculateMonthlyRevenueResponse(
            $request->parkingId,
            $request->year,
            $request->month,
            $reservationData['revenue'],
            $sessionData['revenue'],
            $subscriptionData['revenue'],
            $sessionData['penalties'],
            $totalRevenue,
            $reservationData['count'],
            $sessionData['count'],
            $subscriptionData['count']
        );
    }

    private function validateRequest(CalculateMonthlyRevenueRequest $request): void
    {
        if (empty($request->parkingId)) {
            throw new \InvalidArgumentException('Parking ID is required');
        }

        if ($request->year < 2000 || $request->year > 2100) {
            throw new \InvalidArgumentException('Year must be between 2000 and 2100');
        }

        if ($request->month < 1 || $request->month > 12) {
            throw new \InvalidArgumentException('Month must be between 1 and 12');
        }
    }

    private function calculateReservationRevenue(
        string $parkingId,
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate
    ): array {
        $reservations = $this->reservationRepository->findByParkingId($parkingId);

        $revenue = 0.0;
        $count = 0;

        foreach ($reservations as $reservation) {
            // Only count completed reservations in the date range
            if ($reservation->getStatus() !== 'completed' &&
                $reservation->getStatus() !== 'confirmed') {
                continue;
            }

            $reservationStart = $reservation->getStartTime();
            if ($reservationStart >= $startDate && $reservationStart <= $endDate) {
                $revenue += $reservation->getTotalAmount();
                $count++;
            }
        }

        return ['revenue' => $revenue, 'count' => $count];
    }

    private function calculateSessionRevenue(
        string $parkingId,
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate
    ): array {
        $sessions = $this->sessionRepository->findByParkingId($parkingId);

        $revenue = 0.0;
        $penalties = 0.0;
        $count = 0;

        foreach ($sessions as $session) {
            // Only count completed sessions in the date range
            if (!$session->isCompleted() && !$session->isOverstayed()) {
                continue;
            }

            $sessionStart = $session->getStartTime();
            if ($sessionStart >= $startDate && $sessionStart <= $endDate) {
                $totalAmount = $session->getTotalAmount() ?? 0.0;

                if ($session->isOverstayed()) {
                    // Session revenue includes penalty, separate it
                    // Assume penalty starts after base amount
                    $penalties += 20.0; // Base penalty
                    $revenue += $totalAmount - 20.0;
                } else {
                    $revenue += $totalAmount;
                }
                $count++;
            }
        }

        return ['revenue' => $revenue, 'penalties' => $penalties, 'count' => $count];
    }

    private function calculateSubscriptionRevenue(
        string $parkingId,
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate
    ): array {
        $subscriptions = $this->subscriptionRepository->findByParkingId($parkingId);

        $revenue = 0.0;
        $count = 0;

        foreach ($subscriptions as $subscription) {
            if (!$subscription->isActive()) {
                continue;
            }

            // Check if subscription was active during this month
            $subStart = $subscription->getStartDate();
            $subEnd = $subscription->getEndDate();

            if ($subStart <= $endDate && $subEnd >= $startDate) {
                // Subscription is active during this month
                $revenue += $subscription->getMonthlyAmount();
                $count++;
            }
        }

        return ['revenue' => $revenue, 'count' => $count];
    }
}
