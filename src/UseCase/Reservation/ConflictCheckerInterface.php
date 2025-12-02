<?php

declare(strict_types=1);

namespace ParkingSystem\UseCase\Reservation;

/**
 * ConflictCheckerInterface
 * Use Case Layer - Service contract for checking reservation conflicts
 */
interface ConflictCheckerInterface
{
    /**
     * Check if there are conflicting reservations for a parking space
     * 
     * @param string $parkingId Parking to check
     * @param \DateTimeInterface $startTime Proposed start time
     * @param \DateTimeInterface $endTime Proposed end time
     * @param string|null $excludeReservationId Reservation to exclude (for updates)
     * @return bool True if there are conflicts
     */
    public function hasConflicts(
        string $parkingId,
        \DateTimeInterface $startTime,
        \DateTimeInterface $endTime,
        ?string $excludeReservationId = null
    ): bool;

    /**
     * Get available spaces at a specific time considering reservations and subscriptions
     * 
     * @param string $parkingId Parking to check
     * @param \DateTimeInterface $dateTime Time to check
     * @return int Number of available spaces
     */
    public function getAvailableSpacesAt(
        string $parkingId,
        \DateTimeInterface $dateTime
    ): int;

    /**
     * Check if minimum spaces are available during entire time range
     * 
     * @param string $parkingId Parking to check
     * @param \DateTimeInterface $startTime Range start
     * @param \DateTimeInterface $endTime Range end
     * @param int $requiredSpaces Number of spaces needed
     * @return bool True if spaces available throughout range
     */
    public function hasAvailableSpacesDuring(
        string $parkingId,
        \DateTimeInterface $startTime,
        \DateTimeInterface $endTime,
        int $requiredSpaces = 1
    ): bool;
}