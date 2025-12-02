<?php

declare(strict_types=1);

namespace ParkingSystem\UseCase\Parking;

use ParkingSystem\Domain\Repository\ParkingRepositoryInterface;
use ParkingSystem\Domain\Repository\ParkingSessionRepositoryInterface;
use ParkingSystem\Domain\Repository\ReservationRepositoryInterface;
use ParkingSystem\Domain\Repository\SubscriptionRepositoryInterface;

/**
 * ListUnauthorizedUsers Use Case
 * Use Case Layer - Lists users parked without valid authorization
 */
class ListUnauthorizedUsers
{
    private const OVERSTAY_PENALTY_BASE = 20.0;

    public function __construct(
        private ParkingRepositoryInterface $parkingRepository,
        private ParkingSessionRepositoryInterface $sessionRepository,
        private ReservationRepositoryInterface $reservationRepository,
        private SubscriptionRepositoryInterface $subscriptionRepository
    ) {
    }

    public function execute(
        ListUnauthorizedUsersRequest $request
    ): ListUnauthorizedUsersResponse {
        $this->validateRequest($request);

        // Verify parking exists
        $parking = $this->parkingRepository->findById($request->parkingId);
        if ($parking === null) {
            throw new ParkingNotFoundException(
                'Parking not found: ' . $request->parkingId
            );
        }

        $checkTime = $request->asOfTime !== null
            ? \DateTimeImmutable::createFromInterface($request->asOfTime)
            : new \DateTimeImmutable();

        // Get all active sessions for the parking
        $activeSessions = $this->sessionRepository
            ->findActiveSessionsForParking($request->parkingId);

        $unauthorizedUsers = [];

        foreach ($activeSessions as $session) {
            $authStatus = $this->checkAuthorization(
                $session,
                $parking->getHourlyRate(),
                $checkTime
            );

            if (!$authStatus['authorized']) {
                $durationMinutes = $session->getCurrentDurationInMinutes();

                $unauthorizedUsers[] = new UnauthorizedUserInfo(
                    $session->getId(),
                    $session->getUserId(),
                    $session->getStartTime()->format(\DateTimeInterface::ATOM),
                    $durationMinutes,
                    $authStatus['reason'],
                    $authStatus['penalty']
                );
            }
        }

        return new ListUnauthorizedUsersResponse(
            $request->parkingId,
            $checkTime->format(\DateTimeInterface::ATOM),
            count($unauthorizedUsers),
            $unauthorizedUsers
        );
    }

    private function validateRequest(ListUnauthorizedUsersRequest $request): void
    {
        if (empty($request->parkingId)) {
            throw new \InvalidArgumentException('Parking ID is required');
        }
    }

    private function checkAuthorization(
        $session,
        float $hourlyRate,
        \DateTimeImmutable $checkTime
    ): array {
        // Check if session has a reservation
        if ($session->getReservationId() !== null) {
            $reservation = $this->reservationRepository->findById(
                $session->getReservationId()
            );

            if ($reservation !== null) {
                // Check if reservation has expired
                if ($checkTime > $reservation->getEndTime()) {
                    $overstayMinutes = $reservation->getEndTime()
                        ->diff($checkTime)->i +
                        ($reservation->getEndTime()->diff($checkTime)->h * 60);

                    return [
                        'authorized' => false,
                        'reason' => 'reservation_expired',
                        'penalty' => $this->calculatePenalty(
                            $hourlyRate,
                            $overstayMinutes
                        )
                    ];
                }

                return ['authorized' => true, 'reason' => '', 'penalty' => 0.0];
            }
        }

        // Check if user has active subscription
        $subscriptions = $this->subscriptionRepository
            ->findActiveSubscriptionsForUser($session->getUserId());

        foreach ($subscriptions as $subscription) {
            if ($subscription->getParkingId() === $session->getParkingId() &&
                $subscription->coversTimeSlot($checkTime)) {
                return ['authorized' => true, 'reason' => '', 'penalty' => 0.0];
            }
        }

        // No valid authorization found
        $sessionDurationMinutes = $session->getCurrentDurationInMinutes();

        return [
            'authorized' => false,
            'reason' => 'no_reservation_or_subscription',
            'penalty' => $this->calculatePenalty($hourlyRate, $sessionDurationMinutes)
        ];
    }

    private function calculatePenalty(float $hourlyRate, int $minutes): float
    {
        // €20 base penalty + time charged in 15-min increments
        $billingMinutes = ceil($minutes / 15) * 15;
        $additionalCharge = ($billingMinutes / 60) * $hourlyRate;

        return self::OVERSTAY_PENALTY_BASE + $additionalCharge;
    }
}
