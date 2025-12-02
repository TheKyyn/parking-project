<?php

declare(strict_types=1);

namespace ParkingSystem\UseCase\Session;

use ParkingSystem\Domain\Entity\ParkingSession;
use ParkingSystem\Domain\Repository\ParkingSessionRepositoryInterface;
use ParkingSystem\Domain\Repository\ParkingRepositoryInterface;
use ParkingSystem\Domain\Repository\UserRepositoryInterface;

/**
 * EnterParking Use Case
 * Use Case Layer - Business logic for entering a parking
 */
class EnterParking
{
    public function __construct(
        private ParkingSessionRepositoryInterface $sessionRepository,
        private ParkingRepositoryInterface $parkingRepository,
        private UserRepositoryInterface $userRepository,
        private EntryValidatorInterface $entryValidator,
        private IdGeneratorInterface $idGenerator
    ) {
    }

    public function execute(EnterParkingRequest $request): EnterParkingResponse
    {
        $this->validateRequest($request);

        $entryTime = $request->entryTime ?? new \DateTimeImmutable();

        // Verify user exists
        if (!$this->userRepository->exists($request->userId)) {
            throw new UserNotFoundException('User not found: ' . $request->userId);
        }

        // Verify parking exists
        $parking = $this->parkingRepository->findById($request->parkingId);
        if ($parking === null) {
            throw new ParkingNotFoundException(
                'Parking not found: ' . $request->parkingId
            );
        }

        // Check if parking is open
        if (!$parking->isOpenAt($entryTime)) {
            throw new ParkingClosedException(
                'Parking is closed at the requested time'
            );
        }

        // Check for existing active session
        $existingSession = $this->sessionRepository->findActiveSessionByUserAndParking(
            $request->userId,
            $request->parkingId
        );

        if ($existingSession !== null) {
            throw new ActiveSessionExistsException(
                'User already has an active session in this parking'
            );
        }

        // Validate authorization (reservation or subscription)
        $hasReservation = $this->entryValidator->hasActiveReservation(
            $request->userId,
            $request->parkingId,
            $entryTime
        );

        $hasSubscription = $this->entryValidator->hasActiveSubscription(
            $request->userId,
            $request->parkingId,
            $entryTime
        );

        if (!$hasReservation && !$hasSubscription) {
            throw new NoAuthorizationException(
                'Entry denied: no active reservation or subscription'
            );
        }

        // Get reservation ID if applicable
        $reservationId = $this->entryValidator->getActiveReservationId(
            $request->userId,
            $request->parkingId,
            $entryTime
        );

        // Get authorized end time
        $authorizedEndTime = $this->entryValidator->getAuthorizedEndTime(
            $request->userId,
            $request->parkingId,
            $entryTime
        );

        // Create session
        $sessionId = $this->idGenerator->generate();
        $entryTimeImmutable = \DateTimeImmutable::createFromInterface($entryTime);

        $session = new ParkingSession(
            $sessionId,
            $request->userId,
            $request->parkingId,
            $entryTimeImmutable,
            $reservationId
        );

        // Save session
        $this->sessionRepository->save($session);

        return new EnterParkingResponse(
            $session->getId(),
            $session->getUserId(),
            $session->getParkingId(),
            $session->getReservationId(),
            $session->getStartTime()->format(\DateTimeInterface::ATOM),
            $authorizedEndTime?->format(\DateTimeInterface::ATOM),
            $session->getStatus()
        );
    }

    private function validateRequest(EnterParkingRequest $request): void
    {
        if (empty($request->userId)) {
            throw new \InvalidArgumentException('User ID is required');
        }

        if (empty($request->parkingId)) {
            throw new \InvalidArgumentException('Parking ID is required');
        }
    }
}
