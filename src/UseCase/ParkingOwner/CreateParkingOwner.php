<?php

declare(strict_types=1);

namespace ParkingSystem\UseCase\ParkingOwner;

use ParkingSystem\Domain\Entity\ParkingOwner;
use ParkingSystem\Domain\Repository\ParkingOwnerRepositoryInterface;
use ParkingSystem\UseCase\User\PasswordHasherInterface;
use ParkingSystem\UseCase\User\IdGeneratorInterface;

/**
 * CreateParkingOwner Use Case
 * Use Case Layer - Pure business logic with NO external dependencies
 */
class CreateParkingOwner
{
    public function __construct(
        private ParkingOwnerRepositoryInterface $ownerRepository,
        private PasswordHasherInterface $passwordHasher,
        private IdGeneratorInterface $idGenerator
    ) {
    }

    public function execute(CreateParkingOwnerRequest $request): CreateParkingOwnerResponse
    {
        $this->validateRequest($request);

        if ($this->ownerRepository->emailExists($request->email)) {
            throw new OwnerAlreadyExistsException('Email already registered: ' . $request->email);
        }

        $ownerId = $this->idGenerator->generate();
        $passwordHash = $this->passwordHasher->hash($request->password);

        $owner = new ParkingOwner(
            $ownerId,
            $request->email,
            $passwordHash,
            $request->firstName,
            $request->lastName
        );

        $this->ownerRepository->save($owner);

        return new CreateParkingOwnerResponse(
            $owner->getId(),
            $owner->getEmail(),
            $owner->getFullName(),
            $owner->getCreatedAt()->format(\DateTimeInterface::ATOM)
        );
    }

    private function validateRequest(CreateParkingOwnerRequest $request): void
    {
        if (empty(trim($request->email))) {
            throw new \InvalidArgumentException('Email is required');
        }

        if (empty(trim($request->password))) {
            throw new \InvalidArgumentException('Password is required');
        }

        if (strlen($request->password) < 8) {
            throw new \InvalidArgumentException('Password must be at least 8 characters');
        }

        if (empty(trim($request->firstName))) {
            throw new \InvalidArgumentException('First name is required');
        }

        if (empty(trim($request->lastName))) {
            throw new \InvalidArgumentException('Last name is required');
        }
    }
}
