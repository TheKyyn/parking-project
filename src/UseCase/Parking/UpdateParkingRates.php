<?php

declare(strict_types=1);

namespace ParkingSystem\UseCase\Parking;

use ParkingSystem\Domain\Repository\ParkingRepositoryInterface;

/**
 * UpdateParkingRates Use Case
 * Use Case Layer - Specialized use case for rate updates
 */
class UpdateParkingRates
{
    public function __construct(
        private ParkingRepositoryInterface $parkingRepository
    ) {
    }

    public function execute(UpdateParkingRatesRequest $request): void
    {
        $this->validateRequest($request);

        $parking = $this->parkingRepository->findById($request->parkingId);
        
        if ($parking === null) {
            throw new ParkingNotFoundException('Parking not found: ' . $request->parkingId);
        }

        // Verify ownership
        if ($parking->getOwnerId() !== $request->requesterId) {
            throw new UnauthorizedParkingAccessException(
                'User ' . $request->requesterId . ' is not authorized to update rates for parking ' . $request->parkingId
            );
        }

        $parking->updateRate($request->newHourlyRate);
        $this->parkingRepository->save($parking);
    }

    private function validateRequest(UpdateParkingRatesRequest $request): void
    {
        if (empty($request->parkingId)) {
            throw new \InvalidArgumentException('Parking ID is required');
        }

        if (empty($request->requesterId)) {
            throw new \InvalidArgumentException('Requester ID is required');
        }

        if ($request->newHourlyRate < 0) {
            throw new \InvalidArgumentException('Hourly rate cannot be negative');
        }

        if ($request->newHourlyRate > 1000) {
            throw new \InvalidArgumentException('Hourly rate cannot exceed 1000');
        }
    }
}