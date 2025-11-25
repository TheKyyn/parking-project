<?php

declare(strict_types=1);

namespace ParkingSystem\UseCase\User;

use ParkingSystem\Domain\Repository\UserRepositoryInterface;

/**
 * AuthenticateUser Use Case
 * Use Case Layer - Pure business logic for user authentication
 */
class AuthenticateUser
{
    private const TOKEN_EXPIRATION_SECONDS = 3600; // 1 hour

    public function __construct(
        private UserRepositoryInterface $userRepository,
        private PasswordHasherInterface $passwordHasher,
        private JwtTokenGeneratorInterface $tokenGenerator
    ) {
    }

    public function execute(AuthenticateUserRequest $request): AuthenticateUserResponse
    {
        $this->validateRequest($request);

        $user = $this->userRepository->findByEmail($request->email);
        
        if ($user === null) {
            throw new InvalidCredentialsException('Invalid email or password');
        }

        if (!$this->passwordHasher->verify($request->password, $user->getPasswordHash())) {
            throw new InvalidCredentialsException('Invalid email or password');
        }

        $tokenPayload = [
            'userId' => $user->getId(),
            'email' => $user->getEmail(),
            'type' => 'user',
            'iat' => time(),
            'exp' => time() + self::TOKEN_EXPIRATION_SECONDS
        ];

        $token = $this->tokenGenerator->generate(
            $tokenPayload, 
            self::TOKEN_EXPIRATION_SECONDS
        );

        return new AuthenticateUserResponse(
            $user->getId(),
            $user->getEmail(),
            $user->getFullName(),
            $token,
            self::TOKEN_EXPIRATION_SECONDS
        );
    }

    private function validateRequest(AuthenticateUserRequest $request): void
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