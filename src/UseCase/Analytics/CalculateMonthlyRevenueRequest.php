<?php

declare(strict_types=1);

namespace ParkingSystem\UseCase\Analytics;

/**
 * CalculateMonthlyRevenueRequest DTO
 * Use Case Layer - Input for calculating monthly revenue
 */
class CalculateMonthlyRevenueRequest
{
    public function __construct(
        public readonly string $parkingId,
        public readonly int $year,
        public readonly int $month
    ) {
    }
}
