<?php

declare(strict_types=1);

namespace ParkingSystem\UseCase\Parking;

use ParkingSystem\Domain\Repository\ParkingRepositoryInterface;
use ParkingSystem\Domain\Repository\ParkingOwnerRepositoryInterface;

/**
 * DeleteParking Use Case
 * Use Case Layer - Business logic for parking deletion
 */
class DeleteParking
{
    public function __construct(
        private ParkingRepositoryInterface $parkingRepository,
        private ParkingOwnerRepositoryInterface $ownerRepository
    ) {
    }

    public function execute(DeleteParkingRequest $request): void
    {
        $parking = $this->parkingRepository->findById($request->parkingId);

        if ($parking === null) {
            throw new ParkingNotFoundException('Parking not found: ' . $request->parkingId);
        }

        // Verify ownership
        if ($parking->getOwnerId() !== $request->requesterId) {
            throw new UnauthorizedParkingAccessException(
                'User ' . $request->requesterId . ' is not authorized to delete parking ' . $request->parkingId
            );
        }

        // Remove parking from owner's list
        $owner = $this->ownerRepository->findById($parking->getOwnerId());
        if ($owner !== null) {
            $owner->removeOwnedParking($parking->getId());
            $this->ownerRepository->save($owner);
        }

        // Delete the parking
        $this->parkingRepository->delete($parking);
    }
}
