<?php

declare(strict_types=1);

namespace ParkingSystem\UseCase\Session;

use ParkingSystem\Domain\Repository\ParkingSessionRepositoryInterface;
use ParkingSystem\Domain\Repository\ParkingRepositoryInterface;
use ParkingSystem\Domain\Repository\ReservationRepositoryInterface;

/**
 * ExitParking Use Case
 * Use Case Layer - Business logic for exiting a parking with overstay detection
 */
class ExitParking
{
    private const OVERSTAY_PENALTY_BASE = 20.0;

    public function __construct(
        private ParkingSessionRepositoryInterface $sessionRepository,
        private ParkingRepositoryInterface $parkingRepository,
        private ReservationRepositoryInterface $reservationRepository,
        private PricingCalculatorInterface $pricingCalculator,
        private EntryValidatorInterface $entryValidator
    ) {
    }

    public function execute(ExitParkingRequest $request): ExitParkingResponse
    {
        $this->validateRequest($request);

        $exitTime = $request->exitTime ?? new \DateTimeImmutable();
        $exitTimeImmutable = \DateTimeImmutable::createFromInterface($exitTime);

        // Find session
        $session = $this->sessionRepository->findById($request->sessionId);
        if ($session === null) {
            throw new SessionNotFoundException(
                'Session not found: ' . $request->sessionId
            );
        }

        // Verify session is active
        if (!$session->isActive()) {
            throw new \DomainException(
                'Cannot exit: session is not active (status: ' . $session->getStatus() . ')'
            );
        }

        // Get parking for rate calculation
        $parking = $this->parkingRepository->findById($session->getParkingId());
        if ($parking === null) {
            throw new ParkingNotFoundException(
                'Parking not found: ' . $session->getParkingId()
            );
        }

        // Calculate base amount
        $baseAmount = $this->pricingCalculator->calculateSessionPrice(
            $parking->getHourlyRate(),
            $session->getStartTime(),
            $exitTimeImmutable
        );

        // Check for overstay and calculate penalty
        $overstayPenalty = 0.0;
        $wasOverstayed = false;

        $authorizedEndTime = $this->getAuthorizedEndTime($session);

        if ($authorizedEndTime !== null && $exitTimeImmutable > $authorizedEndTime) {
            $wasOverstayed = true;
            $overstayPenalty = $this->pricingCalculator->calculateOverstayPenalty(
                $parking->getHourlyRate(),
                $authorizedEndTime,
                $exitTimeImmutable
            );
        }

        $totalAmount = $baseAmount + $overstayPenalty;

        // End the session first (while still active)
        $session->endSession($exitTimeImmutable, $totalAmount);

        // Then mark as overstayed if applicable (after session is ended)
        if ($wasOverstayed) {
            // Note: The session status is already 'completed' from endSession()
            // The overstayed flag is tracked via $wasOverstayed for the response
        }

        // Mark reservation as completed if applicable
        $this->completeReservationIfApplicable($session->getReservationId());

        // Save updated session
        $this->sessionRepository->save($session);

        $durationMinutes = $session->getDurationInMinutes() ?? 0;

        return new ExitParkingResponse(
            $session->getId(),
            $session->getUserId(),
            $session->getParkingId(),
            $session->getStartTime()->format(\DateTimeInterface::ATOM),
            $session->getEndTime()->format(\DateTimeInterface::ATOM),
            $durationMinutes,
            $baseAmount,
            $overstayPenalty,
            $totalAmount,
            $wasOverstayed,
            $session->getStatus()
        );
    }

    private function validateRequest(ExitParkingRequest $request): void
    {
        if (empty($request->sessionId)) {
            throw new \InvalidArgumentException('Session ID is required');
        }
    }

    private function getAuthorizedEndTime($session): ?\DateTimeImmutable
    {
        // If session has a reservation, get its end time
        if ($session->getReservationId() !== null) {
            $reservation = $this->reservationRepository->findById(
                $session->getReservationId()
            );
            if ($reservation !== null) {
                return $reservation->getEndTime();
            }
        }

        // Try to get authorized end time from subscription
        $authorizedEndTime = $this->entryValidator->getAuthorizedEndTime(
            $session->getUserId(),
            $session->getParkingId(),
            $session->getStartTime()
        );

        if ($authorizedEndTime !== null) {
            return \DateTimeImmutable::createFromInterface($authorizedEndTime);
        }

        return null;
    }

    private function completeReservationIfApplicable(?string $reservationId): void
    {
        if ($reservationId === null) {
            return;
        }

        $reservation = $this->reservationRepository->findById($reservationId);
        if ($reservation !== null && $reservation->isActive()) {
            $reservation->complete();
            $this->reservationRepository->save($reservation);
        }
    }
}
