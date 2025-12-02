<?php

declare(strict_types=1);

namespace ParkingSystem\UseCase\ParkingOwner;

use ParkingSystem\Domain\Repository\ParkingOwnerRepositoryInterface;
use ParkingSystem\UseCase\User\PasswordHasherInterface;
use ParkingSystem\UseCase\User\JwtTokenGeneratorInterface;

/**
 * AuthenticateParkingOwner Use Case
 * Use Case Layer - Pure business logic for owner authentication
 */
class AuthenticateParkingOwner
{
    private const TOKEN_EXPIRATION_SECONDS = 3600; // 1 hour

    public function __construct(
        private ParkingOwnerRepositoryInterface $ownerRepository,
        private PasswordHasherInterface $passwordHasher,
        private JwtTokenGeneratorInterface $tokenGenerator
    ) {
    }

    public function execute(AuthenticateParkingOwnerRequest $request): AuthenticateParkingOwnerResponse
    {
        $this->validateRequest($request);

        $owner = $this->ownerRepository->findByEmail($request->email);

        if ($owner === null) {
            throw new InvalidOwnerCredentialsException('Invalid email or password');
        }

        if (!$this->passwordHasher->verify($request->password, $owner->getPasswordHash())) {
            throw new InvalidOwnerCredentialsException('Invalid email or password');
        }

        $tokenPayload = [
            'ownerId' => $owner->getId(),
            'email' => $owner->getEmail(),
            'type' => 'owner',
            'iat' => time(),
            'exp' => time() + self::TOKEN_EXPIRATION_SECONDS
        ];

        $token = $this->tokenGenerator->generate(
            $tokenPayload,
            self::TOKEN_EXPIRATION_SECONDS
        );

        return new AuthenticateParkingOwnerResponse(
            $owner->getId(),
            $owner->getEmail(),
            $owner->getFullName(),
            $token,
            self::TOKEN_EXPIRATION_SECONDS
        );
    }

    private function validateRequest(AuthenticateParkingOwnerRequest $request): void
    {
        if (empty(trim($request->email))) {
            throw new \InvalidArgumentException('Email is required');
        }

        if (!filter_var($request->email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Invalid email format');
        }

        if (empty($request->password)) {
            throw new \InvalidArgumentException('Password is required');
        }
    }
}
