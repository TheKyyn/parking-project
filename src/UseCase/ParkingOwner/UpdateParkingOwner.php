<?php

declare(strict_types=1);

namespace ParkingSystem\UseCase\ParkingOwner;

use ParkingSystem\Domain\Repository\ParkingOwnerRepositoryInterface;

/**
 * UpdateParkingOwner Use Case
 * Use Case Layer - Pure business logic to update owner profile
 */
class UpdateParkingOwner
{
    public function __construct(
        private ParkingOwnerRepositoryInterface $ownerRepository
    ) {
    }

    public function execute(UpdateParkingOwnerRequest $request): CreateParkingOwnerResponse
    {
        $this->validateRequest($request);

        $owner = $this->ownerRepository->findById($request->ownerId);

        if ($owner === null) {
            throw new \InvalidArgumentException('Owner not found');
        }

        $owner->updateProfile($request->firstName, $request->lastName);

        $this->ownerRepository->save($owner);

        return new CreateParkingOwnerResponse(
            $owner->getId(),
            $owner->getEmail(),
            $owner->getFullName(),
            $owner->getCreatedAt()->format(\DateTimeInterface::ATOM)
        );
    }

    private function validateRequest(UpdateParkingOwnerRequest $request): void
    {
        if (empty(trim($request->ownerId))) {
            throw new \InvalidArgumentException('Owner ID is required');
        }

        if (empty(trim($request->firstName))) {
            throw new \InvalidArgumentException('First name is required');
        }

        if (empty(trim($request->lastName))) {
            throw new \InvalidArgumentException('Last name is required');
        }
    }
}
