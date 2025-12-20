<?php

declare(strict_types=1);

namespace ParkingSystem\Infrastructure\Service;

use ParkingSystem\UseCase\Session\EntryValidatorInterface;
use ParkingSystem\Domain\Repository\ReservationRepositoryInterface;

/**
 * Simple implementation of EntryValidatorInterface using reservations
 */
class SimpleEntryValidator implements EntryValidatorInterface
{
    public function __construct(
        private ReservationRepositoryInterface $reservationRepository
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function hasActiveReservation(
        string $userId,
        string $parkingId,
        \DateTimeInterface $dateTime
    ): bool {
        $reservations = $this->reservationRepository->findByUserId($userId);

        foreach ($reservations as $reservation) {
            if ($reservation->getParkingId() !== $parkingId) {
                continue;
            }

            if ($reservation->getStatus() === 'cancelled') {
                continue;
            }

            // Check if the dateTime falls within the reservation window
            if ($dateTime >= $reservation->getStartTime() && $dateTime <= $reservation->getEndTime()) {
                return true;
            }
        }

        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function hasActiveSubscription(
        string $userId,
        string $parkingId,
        \DateTimeInterface $dateTime
    ): bool {
        // Subscriptions not implemented yet
        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function getActiveReservationId(
        string $userId,
        string $parkingId,
        \DateTimeInterface $dateTime
    ): ?string {
        $reservations = $this->reservationRepository->findByUserId($userId);

        foreach ($reservations as $reservation) {
            if ($reservation->getParkingId() !== $parkingId) {
                continue;
            }

            if ($reservation->getStatus() === 'cancelled') {
                continue;
            }

            // Check if the dateTime falls within the reservation window
            if ($dateTime >= $reservation->getStartTime() && $dateTime <= $reservation->getEndTime()) {
                return $reservation->getId();
            }
        }

        return null;
    }

    /**
     * {@inheritDoc}
     */
    public function getAuthorizedEndTime(
        string $userId,
        string $parkingId,
        \DateTimeInterface $dateTime
    ): ?\DateTimeInterface {
        $reservations = $this->reservationRepository->findByUserId($userId);

        foreach ($reservations as $reservation) {
            if ($reservation->getParkingId() !== $parkingId) {
                continue;
            }

            if ($reservation->getStatus() === 'cancelled') {
                continue;
            }

            // Check if the dateTime falls within the reservation window
            if ($dateTime >= $reservation->getStartTime() && $dateTime <= $reservation->getEndTime()) {
                return $reservation->getEndTime();
            }
        }

        return null;
    }
}
