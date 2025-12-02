<?php

declare(strict_types=1);

namespace ParkingSystem\UseCase\Analytics;

/**
 * GetParkingStatisticsRequest DTO
 * Use Case Layer - Input for getting parking statistics
 */
class GetParkingStatisticsRequest
{
    public function __construct(
        public readonly string $parkingId,
        public readonly ?\DateTimeInterface $fromDate = null,
        public readonly ?\DateTimeInterface $toDate = null
    ) {
    }
}
