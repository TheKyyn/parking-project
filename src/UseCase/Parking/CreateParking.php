<?php

declare(strict_types=1);

namespace ParkingSystem\UseCase\Parking;

use ParkingSystem\Domain\Entity\Parking;
use ParkingSystem\Domain\Repository\ParkingRepositoryInterface;
use ParkingSystem\Domain\Repository\ParkingOwnerRepositoryInterface;

/**
 * CreateParking Use Case
 * Use Case Layer - Pure business logic for parking creation
 */
class CreateParking
{
    public function __construct(
        private ParkingRepositoryInterface $parkingRepository,
        private ParkingOwnerRepositoryInterface $ownerRepository,
        private IdGeneratorInterface $idGenerator
    ) {
    }

    public function execute(CreateParkingRequest $request): CreateParkingResponse
    {
        $this->validateRequest($request);
        
        // Verify owner exists
        if (!$this->ownerRepository->exists($request->ownerId)) {
            throw new OwnerNotFoundException('Owner not found: ' . $request->ownerId);
        }

        $parkingId = $this->idGenerator->generate();

        $parking = new Parking(
            $parkingId,
            $request->ownerId,
            $request->latitude,
            $request->longitude,
            $request->totalSpaces,
            $request->hourlyRate,
            $request->openingHours
        );

        $this->parkingRepository->save($parking);

        // Update owner's parking list
        $owner = $this->ownerRepository->findById($request->ownerId);
        if ($owner !== null) {
            $owner->addOwnedParking($parkingId);
            $this->ownerRepository->save($owner);
        }

        return new CreateParkingResponse(
            $parking->getId(),
            $parking->getOwnerId(),
            $parking->getLatitude(),
            $parking->getLongitude(),
            $parking->getTotalSpaces(),
            $parking->getHourlyRate(),
            $parking->getCreatedAt()->format(\DateTimeInterface::ATOM)
        );
    }

    private function validateRequest(CreateParkingRequest $request): void
    {
        if (empty($request->ownerId)) {
            throw new \InvalidArgumentException('Owner ID is required');
        }

        if ($request->latitude < -90 || $request->latitude > 90) {
            throw new \InvalidArgumentException('Invalid latitude');
        }

        if ($request->longitude < -180 || $request->longitude > 180) {
            throw new \InvalidArgumentException('Invalid longitude');
        }

        if ($request->totalSpaces < 1) {
            throw new \InvalidArgumentException('Total spaces must be at least 1');
        }

        if ($request->hourlyRate < 0) {
            throw new \InvalidArgumentException('Hourly rate cannot be negative');
        }

        $this->validateOpeningHours($request->openingHours);
    }

    private function validateOpeningHours(array $openingHours): void
    {
        foreach ($openingHours as $dayOfWeek => $hours) {
            if (!is_int($dayOfWeek) || $dayOfWeek < 0 || $dayOfWeek > 6) {
                throw new \InvalidArgumentException('Invalid day of week: ' . $dayOfWeek);
            }

            if (!isset($hours['open'], $hours['close'])) {
                throw new \InvalidArgumentException('Opening hours must have open and close times');
            }

            if (!preg_match('/^([01][0-9]|2[0-3]):[0-5][0-9]$/', $hours['open'])) {
                throw new \InvalidArgumentException('Invalid open time format');
            }

            if (!preg_match('/^([01][0-9]|2[0-3]):[0-5][0-9]$/', $hours['close'])) {
                throw new \InvalidArgumentException('Invalid close time format');
            }

            if ($hours['open'] >= $hours['close']) {
                throw new \InvalidArgumentException('Open time must be before close time');
            }
        }
    }
}