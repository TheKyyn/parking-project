<?php

declare(strict_types=1);

namespace ParkingSystem\UseCase\User;

use ParkingSystem\Domain\Entity\User;
use ParkingSystem\Domain\Repository\UserRepositoryInterface;

/**
 * CreateUser Use Case
 * Use Case Layer - Pure business logic with NO external dependencies
 */
class CreateUser
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private PasswordHasherInterface $passwordHasher,
        private IdGeneratorInterface $idGenerator
    ) {
    }

    public function execute(CreateUserRequest $request): CreateUserResponse
    {
        $this->validateRequest($request);
        
        if ($this->userRepository->emailExists($request->email)) {
            throw new UserAlreadyExistsException('Email already registered: ' . $request->email);
        }

        $userId = $this->idGenerator->generate();
        $passwordHash = $this->passwordHasher->hash($request->password);

        $user = new User(
            $userId,
            $request->email,
            $passwordHash,
            $request->firstName,
            $request->lastName
        );

        $this->userRepository->save($user);

        return new CreateUserResponse(
            $user->getId(),
            $user->getEmail(),
            $user->getFullName(),
            $user->getCreatedAt()->format(\DateTimeInterface::ATOM)
        );
    }

    private function validateRequest(CreateUserRequest $request): void
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