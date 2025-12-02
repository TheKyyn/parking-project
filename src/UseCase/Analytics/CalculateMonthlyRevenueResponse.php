<?php

declare(strict_types=1);

namespace ParkingSystem\UseCase\Analytics;

/**
 * CalculateMonthlyRevenueResponse DTO
 * Use Case Layer - Output for monthly revenue calculation
 */
class CalculateMonthlyRevenueResponse
{
    public function __construct(
        public readonly string $parkingId,
        public readonly int $year,
        public readonly int $month,
        public readonly float $reservationRevenue,
        public readonly float $sessionRevenue,
        public readonly float $subscriptionRevenue,
        public readonly float $penaltyRevenue,
        public readonly float $totalRevenue,
        public readonly int $totalReservations,
        public readonly int $totalSessions,
        public readonly int $activeSubscriptions
    ) {
    }
}
