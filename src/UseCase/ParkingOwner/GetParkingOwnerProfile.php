<?php

declare(strict_types=1);

namespace ParkingSystem\UseCase\ParkingOwner;

use ParkingSystem\Domain\Repository\ParkingOwnerRepositoryInterface;

/**
 * GetParkingOwnerProfile Use Case
 * Use Case Layer - Pure business logic to retrieve owner profile
 */
class GetParkingOwnerProfile
{
    public function __construct(
        private ParkingOwnerRepositoryInterface $ownerRepository
    ) {
    }

    public function execute(string $ownerId): CreateParkingOwnerResponse
    {
        if (empty(trim($ownerId))) {
            throw new \InvalidArgumentException('Owner ID is required');
        }

        $owner = $this->ownerRepository->findById($ownerId);

        if ($owner === null) {
            throw new \InvalidArgumentException('Owner not found');
        }

        return new CreateParkingOwnerResponse(
            $owner->getId(),
            $owner->getEmail(),
            $owner->getFullName(),
            $owner->getCreatedAt()->format(\DateTimeInterface::ATOM)
        );
    }
}
