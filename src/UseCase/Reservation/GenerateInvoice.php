<?php

declare(strict_types=1);

namespace ParkingSystem\UseCase\Reservation;

use ParkingSystem\Domain\Repository\ReservationRepositoryInterface;
use ParkingSystem\Domain\Repository\ParkingRepositoryInterface;
use ParkingSystem\Domain\Repository\UserRepositoryInterface;
use ParkingSystem\Domain\Repository\ParkingSessionRepositoryInterface;

/**
 * GenerateInvoice Use Case
 * Generates invoice for a completed reservation (PDF or HTML)
 */
class GenerateInvoice
{
    private const OVERSTAY_PENALTY = 20.0;

    public function __construct(
        private ReservationRepositoryInterface $reservationRepository,
        private ParkingRepositoryInterface $parkingRepository,
        private UserRepositoryInterface $userRepository,
        private ParkingSessionRepositoryInterface $sessionRepository
    ) {
    }

    public function execute(GenerateInvoiceRequest $request): GenerateInvoiceResponse
    {
        $this->validateRequest($request);

        $reservation = $this->reservationRepository->findById($request->reservationId);
        if ($reservation === null) {
            throw new ReservationNotFoundException(
                'Reservation not found: ' . $request->reservationId
            );
        }

        // Verify user owns this reservation
        if ($reservation->getUserId() !== $request->userId) {
            throw new UnauthorizedReservationAccessException(
                'User not authorized to access this reservation'
            );
        }

        $user = $this->userRepository->findById($reservation->getUserId());
        $parking = $this->parkingRepository->findById($reservation->getParkingId());

        if ($user === null || $parking === null) {
            throw new \RuntimeException('Related user or parking not found');
        }

        // Find associated session if exists
        $sessions = $this->sessionRepository->findSessionsByReservationId($reservation->getId());
        $session = !empty($sessions) ? $sessions[0] : null;

        // Calculate invoice details
        $invoiceDetails = $this->calculateInvoiceDetails(
            $reservation,
            $parking,
            $session
        );

        return new GenerateInvoiceResponse(
            invoiceNumber: $this->generateInvoiceNumber($reservation->getId()),
            reservationId: $reservation->getId(),
            userId: $user->getId(),
            userName: $user->getFullName(),
            userEmail: $user->getEmail(),
            parkingId: $parking->getId(),
            parkingName: $parking->getName(),
            parkingAddress: $parking->getAddress(),
            reservationStart: $reservation->getStartTime()->format(\DateTimeInterface::ATOM),
            reservationEnd: $reservation->getEndTime()->format(\DateTimeInterface::ATOM),
            reservedDurationMinutes: $reservation->getDurationInMinutes(),
            actualDurationMinutes: $invoiceDetails['actualDuration'],
            hourlyRate: $parking->getHourlyRate(),
            baseAmount: $invoiceDetails['baseAmount'],
            overstayMinutes: $invoiceDetails['overstayMinutes'],
            overstayAmount: $invoiceDetails['overstayAmount'],
            penaltyAmount: $invoiceDetails['penaltyAmount'],
            totalAmount: $invoiceDetails['totalAmount'],
            status: $reservation->getStatus(),
            generatedAt: (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM)
        );
    }

    private function calculateInvoiceDetails($reservation, $parking, $session): array
    {
        $baseAmount = $reservation->getTotalAmount();
        $actualDuration = $reservation->getDurationInMinutes();
        $overstayData = ['minutes' => 0, 'amount' => 0.0, 'penalty' => 0.0];

        if ($session !== null && $session->getEndTime() !== null) {
            $overstayData = $this->calculateOverstay($session, $reservation, $parking);
            $actualDuration = $this->calculateDurationMinutes($session->getStartTime(), $session->getEndTime());
        }

        return [
            'baseAmount' => $baseAmount,
            'actualDuration' => $actualDuration,
            'overstayMinutes' => $overstayData['minutes'],
            'overstayAmount' => $overstayData['amount'],
            'penaltyAmount' => $overstayData['penalty'],
            'totalAmount' => $baseAmount + $overstayData['amount'] + $overstayData['penalty']
        ];
    }

    private function calculateOverstay($session, $reservation, $parking): array
    {
        $sessionEnd = $session->getEndTime();
        $reservationEnd = $reservation->getEndTime();

        if ($sessionEnd <= $reservationEnd) {
            return ['minutes' => 0, 'amount' => 0.0, 'penalty' => 0.0];
        }

        $overstayMinutes = $this->calculateDurationMinutes($reservationEnd, $sessionEnd);
        $billedMinutes = ceil($overstayMinutes / 15) * 15;
        $overstayAmount = ($billedMinutes / 60) * $parking->getHourlyRate();

        return ['minutes' => $overstayMinutes, 'amount' => $overstayAmount, 'penalty' => self::OVERSTAY_PENALTY];
    }

    private function calculateDurationMinutes(\DateTimeInterface $start, \DateTimeInterface $end): int
    {
        $interval = $start->diff($end);
        return ($interval->days * 24 * 60) + ($interval->h * 60) + $interval->i;
    }

    private function generateInvoiceNumber(string $reservationId): string
    {
        $date = date('Ymd');
        $shortId = strtoupper(substr($reservationId, 0, 8));
        return "INV-{$date}-{$shortId}";
    }

    private function validateRequest(GenerateInvoiceRequest $request): void
    {
        if (empty($request->reservationId)) {
            throw new \InvalidArgumentException('Reservation ID is required');
        }

        if (empty($request->userId)) {
            throw new \InvalidArgumentException('User ID is required');
        }
    }
}
