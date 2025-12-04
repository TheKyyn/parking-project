<?php

declare(strict_types=1);

namespace ParkingSystem\UseCase\Parking;

use ParkingSystem\Domain\Repository\ParkingRepositoryInterface;

/**
 * UpdateParking Use Case
 * Use Case Layer - Business logic for updating parking information
 */
class UpdateParking
{
    public function __construct(
        private ParkingRepositoryInterface $parkingRepository
    ) {
    }

    public function execute(UpdateParkingRequest $request): void
    {
        $parking = $this->parkingRepository->findById($request->parkingId);
        
        if ($parking === null) {
            throw new ParkingNotFoundException('Parking not found: ' . $request->parkingId);
        }

        // Verify ownership
        if ($parking->getOwnerId() !== $request->requesterId) {
            throw new UnauthorizedParkingAccessException(
                'User ' . $request->requesterId . ' is not authorized to update parking ' . $request->parkingId
            );
        }

        // Update fields if provided
        if ($request->name !== null) {
            $this->validateName($request->name);
            $parking->updateName($request->name);
        }

        if ($request->address !== null) {
            $this->validateAddress($request->address);
            $parking->updateAddress($request->address);
        }

        if ($request->totalSpaces !== null) {
            $this->validateTotalSpaces($request->totalSpaces);
            $parking->updateTotalSpaces($request->totalSpaces);
        }

        if ($request->hourlyRate !== null) {
            $this->validateHourlyRate($request->hourlyRate);
            $parking->updateRate($request->hourlyRate);
        }

        if ($request->openingHours !== null) {
            $this->validateOpeningHours($request->openingHours);
            $parking->updateOpeningHours($request->openingHours);
        }

        $this->parkingRepository->save($parking);
    }

    private function validateTotalSpaces(int $totalSpaces): void
    {
        if ($totalSpaces < 1) {
            throw new \InvalidArgumentException('Total spaces must be at least 1');
        }
    }

    private function validateHourlyRate(float $hourlyRate): void
    {
        if ($hourlyRate < 0) {
            throw new \InvalidArgumentException('Hourly rate cannot be negative');
        }
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

    private function validateName(string $name): void
    {
        if (empty(trim($name))) {
            throw new \InvalidArgumentException('Name cannot be empty');
        }

        if (strlen(trim($name)) < 3) {
            throw new \InvalidArgumentException('Name must be at least 3 characters');
        }
    }

    private function validateAddress(string $address): void
    {
        if (empty(trim($address))) {
            throw new \InvalidArgumentException('Address cannot be empty');
        }

        if (strlen(trim($address)) < 5) {
            throw new \InvalidArgumentException('Address must be at least 5 characters');
        }
    }
}