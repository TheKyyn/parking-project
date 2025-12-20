<?php

declare(strict_types=1);

namespace ParkingSystem\Infrastructure\Service;

use ParkingSystem\UseCase\Reservation\ConflictCheckerInterface;
use ParkingSystem\Domain\Repository\ReservationRepositoryInterface;
use ParkingSystem\Domain\Repository\ParkingRepositoryInterface;

/**
 * Simple conflict checker for reservation availability
 *
 * Checks if parking has available spaces during a time period
 * Considers active reservations (confirmed status)
 */
class SimpleConflictChecker implements ConflictCheckerInterface
{
    public function __construct(
        private ReservationRepositoryInterface $reservationRepository,
        private ParkingRepositoryInterface $parkingRepository
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function hasConflicts(
        string $parkingId,
        \DateTimeInterface $startTime,
        \DateTimeInterface $endTime,
        ?string $excludeReservationId = null
    ): bool {
        // Get conflicting reservations
        $conflicts = $this->reservationRepository->findConflictingReservations(
            $parkingId,
            $startTime,
            $endTime
        );

        // Filter out excluded reservation if provided
        if ($excludeReservationId !== null) {
            $conflicts = array_filter($conflicts, function ($reservation) use ($excludeReservationId) {
                return $reservation->getId() !== $excludeReservationId;
            });
        }

        // Get parking total capacity
        $parking = $this->parkingRepository->findById($parkingId);
        if ($parking === null) {
            throw new \InvalidArgumentException('Parking not found: ' . $parkingId);
        }

        $totalSpaces = $parking->getTotalSpaces();

        // If conflicts >= total spaces, no availability
        return count($conflicts) >= $totalSpaces;
    }

    /**
     * {@inheritDoc}
     */
    public function getAvailableSpacesAt(
        string $parkingId,
        \DateTimeInterface $dateTime
    ): int {
        // Get parking
        $parking = $this->parkingRepository->findById($parkingId);
        if ($parking === null) {
            throw new \InvalidArgumentException('Parking not found: ' . $parkingId);
        }

        $totalSpaces = $parking->getTotalSpaces();

        // Count active reservations at this time
        $activeReservations = $this->reservationRepository->findActiveReservationsForParking($parkingId);
        $occupiedSpaces = 0;

        foreach ($activeReservations as $reservation) {
            if ($reservation->isActiveAt($dateTime)) {
                $occupiedSpaces++;
            }
        }

        return max(0, $totalSpaces - $occupiedSpaces);
    }

    /**
     * {@inheritDoc}
     */
    public function hasAvailableSpacesDuring(
        string $parkingId,
        \DateTimeInterface $startTime,
        \DateTimeInterface $endTime,
        int $requiredSpaces = 1
    ): bool {
        // Get parking
        $parking = $this->parkingRepository->findById($parkingId);
        if ($parking === null) {
            throw new \InvalidArgumentException('Parking not found: ' . $parkingId);
        }

        $totalSpaces = $parking->getTotalSpaces();

        // Get all conflicting reservations
        $conflicts = $this->reservationRepository->findConflictingReservations(
            $parkingId,
            $startTime,
            $endTime
        );

        // Available spaces = total - conflicts
        $availableSpaces = $totalSpaces - count($conflicts);

        return $availableSpaces >= $requiredSpaces;
    }
}
