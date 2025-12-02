<?php

declare(strict_types=1);

namespace ParkingSystem\UseCase\Parking;

/**
 * AvailabilityCheckerInterface
 * Use Case Layer - Service contract for checking parking availability
 */
interface AvailabilityCheckerInterface
{
    /**
     * Calculate available spaces for a parking at a specific time
     * considering reservations and active sessions
     */
    public function getAvailableSpaces(
        string $parkingId, 
        \DateTimeInterface $dateTime
    ): int;

    /**
     * Check if parking has minimum available spaces during time range
     */
    public function hasAvailableSpacesDuring(
        string $parkingId,
        \DateTimeInterface $startTime,
        \DateTimeInterface $endTime,
        int $minimumSpaces = 1
    ): bool;
}