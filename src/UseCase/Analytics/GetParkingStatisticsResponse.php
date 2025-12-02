<?php

declare(strict_types=1);

namespace ParkingSystem\UseCase\Analytics;

/**
 * GetParkingStatisticsResponse DTO
 * Use Case Layer - Output for parking statistics
 */
class GetParkingStatisticsResponse
{
    public function __construct(
        public readonly string $parkingId,
        public readonly string $periodStart,
        public readonly string $periodEnd,
        public readonly int $totalSpaces,
        public readonly int $currentlyOccupied,
        public readonly float $occupancyRate,
        public readonly int $totalReservations,
        public readonly int $completedReservations,
        public readonly int $cancelledReservations,
        public readonly int $totalSessions,
        public readonly int $overstayedSessions,
        public readonly float $averageSessionDurationMinutes,
        public readonly int $activeSubscriptions,
        public readonly float $totalRevenue,
        public readonly float $averageDailyRevenue,
        public readonly array $peakHours,
        public readonly array $occupancyByDayOfWeek
    ) {
    }
}
