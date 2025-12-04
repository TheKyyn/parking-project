<?php

declare(strict_types=1);

namespace ParkingSystem\UseCase\Reservation;

use ParkingSystem\Domain\Entity\Reservation;
use ParkingSystem\Domain\Repository\ReservationRepositoryInterface;
use ParkingSystem\Domain\Repository\ParkingRepositoryInterface;
use ParkingSystem\Domain\Repository\UserRepositoryInterface;

/**
 * CreateReservation Use Case
 * Use Case Layer - Business logic for creating parking reservations
 */
class CreateReservation
{
    public function __construct(
        private ReservationRepositoryInterface $reservationRepository,
        private ParkingRepositoryInterface $parkingRepository,
        private UserRepositoryInterface $userRepository,
        private ConflictCheckerInterface $conflictChecker,
        private PricingCalculatorInterface $pricingCalculator,
        private IdGeneratorInterface $idGenerator
    ) {
    }

    public function execute(CreateReservationRequest $request): CreateReservationResponse
    {
        $this->validateRequest($request);

        // Verify user exists
        if (!$this->userRepository->exists($request->userId)) {
            throw new UserNotFoundException('User not found: ' . $request->userId);
        }

        // Verify parking exists
        $parking = $this->parkingRepository->findById($request->parkingId);
        if ($parking === null) {
            throw new ParkingNotFoundException('Parking not found: ' . $request->parkingId);
        }

        // Check if parking is open during reservation period
        $this->validateParkingOpenHours($parking, $request->startTime, $request->endTime);

        // Check real-time availability
        if ($parking->getAvailableSpots() <= 0) {
            throw new NoAvailableSpaceException(
                'No available spots in this parking'
            );
        }

        // Check availability conflicts
        if (!$this->conflictChecker->hasAvailableSpacesDuring(
            $request->parkingId,
            $request->startTime,
            $request->endTime
        )) {
            throw new NoAvailableSpaceException(
                'No available spaces for the requested time period'
            );
        }

        // Calculate price using 15-minute increments
        $totalAmount = $this->pricingCalculator->calculateReservationPrice(
            $parking->getHourlyRate(),
            $request->startTime,
            $request->endTime
        );

        // Create reservation
        $reservationId = $this->idGenerator->generate();
        
        $reservation = new Reservation(
            $reservationId,
            $request->userId,
            $request->parkingId,
            \DateTimeImmutable::createFromInterface($request->startTime),
            \DateTimeImmutable::createFromInterface($request->endTime),
            $totalAmount
        );

        // Confirm reservation immediately
        $reservation->confirm();

        // Decrement available spots
        $parking->reserveSpot();
        $this->parkingRepository->updateAvailableSpots(
            $parking->getId(),
            $parking->getAvailableSpots()
        );

        // Save reservation
        $this->reservationRepository->save($reservation);

        return new CreateReservationResponse(
            $reservation->getId(),
            $reservation->getUserId(),
            $reservation->getParkingId(),
            $reservation->getStartTime()->format(\DateTimeInterface::ATOM),
            $reservation->getEndTime()->format(\DateTimeInterface::ATOM),
            $reservation->getTotalAmount(),
            $reservation->getDurationInMinutes(),
            $reservation->getStatus()
        );
    }

    private function validateRequest(CreateReservationRequest $request): void
    {
        if (empty($request->userId)) {
            throw new \InvalidArgumentException('User ID is required');
        }

        if (empty($request->parkingId)) {
            throw new \InvalidArgumentException('Parking ID is required');
        }

        $now = new \DateTimeImmutable();
        
        if ($request->startTime < $now) {
            throw new InvalidReservationTimeException(
                'Reservation start time cannot be in the past'
            );
        }

        if ($request->startTime >= $request->endTime) {
            throw new InvalidReservationTimeException(
                'Start time must be before end time'
            );
        }

        $maxReservationHours = 24;
        $maxEndTime = (clone $request->startTime)->add(
            new \DateInterval('PT' . $maxReservationHours . 'H')
        );

        if ($request->endTime > $maxEndTime) {
            throw new InvalidReservationTimeException(
                sprintf('Reservation cannot exceed %d hours', $maxReservationHours)
            );
        }

        // Minimum reservation duration: 15 minutes
        $durationMinutes = ($request->endTime->getTimestamp() - 
                           $request->startTime->getTimestamp()) / 60;
        
        if ($durationMinutes < 15) {
            throw new InvalidReservationTimeException(
                'Minimum reservation duration is 15 minutes'
            );
        }
    }

    private function validateParkingOpenHours($parking, \DateTimeInterface $startTime, \DateTimeInterface $endTime): void
    {
        // Check if parking is open at start time
        if (!$parking->isOpenAt($startTime)) {
            throw new InvalidReservationTimeException(
                'Parking is closed at the requested start time'
            );
        }

        // Check if parking is open at end time
        if (!$parking->isOpenAt($endTime)) {
            throw new InvalidReservationTimeException(
                'Parking is closed at the requested end time'
            );
        }

        // For multi-day reservations, check each day
        $current = \DateTimeImmutable::createFromInterface($startTime);
        $end = \DateTimeImmutable::createFromInterface($endTime);
        
        while ($current < $end) {
            if (!$parking->isOpenAt($current)) {
                throw new InvalidReservationTimeException(
                    sprintf('Parking is closed at %s', $current->format('Y-m-d H:i'))
                );
            }
            $current = $current->add(new \DateInterval('PT1H'));
        }
    }
}