<?php

declare(strict_types=1);

namespace ParkingSystem\UseCase\Reservation;

use ParkingSystem\Domain\Repository\ReservationRepositoryInterface;

/**
 * CancelReservation Use Case
 * Use Case Layer - Business logic for cancelling reservations
 *
 * Business Rules:
 * - Only the reservation owner can cancel it
 * - Cannot cancel a reservation that has already started
 * - Cannot cancel an already cancelled reservation
 */
class CancelReservation
{
    public function __construct(
        private ReservationRepositoryInterface $reservationRepository
    ) {
    }

    /**
     * @throws \InvalidArgumentException
     */
    public function execute(string $reservationId, string $userId): void
    {
        // Récupère la réservation
        $reservation = $this->reservationRepository->findById($reservationId);

        if ($reservation === null) {
            throw new \InvalidArgumentException('Reservation not found');
        }

        // Vérifie que c'est bien l'utilisateur de la réservation
        if ($reservation->getUserId() !== $userId) {
            throw new \InvalidArgumentException('Unauthorized: This is not your reservation');
        }

        // Vérifie que la réservation n'a pas encore commencé
        $now = new \DateTimeImmutable();
        if ($reservation->getStartTime() <= $now) {
            throw new \InvalidArgumentException('Cannot cancel a reservation that has already started');
        }

        // Vérifie que la réservation n'est pas déjà annulée
        if ($reservation->getStatus() === 'cancelled') {
            throw new \InvalidArgumentException('Reservation is already cancelled');
        }

        // Annule la réservation
        $reservation->cancel();

        // Sauvegarde
        $this->reservationRepository->save($reservation);
    }
}
