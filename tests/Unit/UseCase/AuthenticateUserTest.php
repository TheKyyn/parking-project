<?php

declare(strict_types=1);

namespace ParkingSystem\Tests\Unit\UseCase;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use ParkingSystem\UseCase\User\AuthenticateUser;
use ParkingSystem\UseCase\User\AuthenticateUserRequest;
use ParkingSystem\UseCase\User\AuthenticateUserResponse;
use ParkingSystem\UseCase\User\InvalidCredentialsException;
use ParkingSystem\UseCase\User\PasswordHasherInterface;
use ParkingSystem\UseCase\User\JwtTokenGeneratorInterface;
use ParkingSystem\Domain\Repository\UserRepositoryInterface;
use ParkingSystem\Domain\Entity\User;

/**
 * AuthenticateUserTest
 * Unit tests for AuthenticateUser use case
 */
class AuthenticateUserTest extends TestCase
{
    private AuthenticateUser $authenticateUser;
    private MockObject|UserRepositoryInterface $userRepository;
    private MockObject|PasswordHasherInterface $passwordHasher;
    private MockObject|JwtTokenGeneratorInterface $tokenGenerator;

    protected function setUp(): void
    {
        $this->userRepository = $this->createMock(UserRepositoryInterface::class);
        $this->passwordHasher = $this->createMock(PasswordHasherInterface::class);
        $this->tokenGenerator = $this->createMock(JwtTokenGeneratorInterface::class);

        $this->authenticateUser = new AuthenticateUser(
            $this->userRepository,
            $this->passwordHasher,
            $this->tokenGenerator
        );
    }

    public function testExecuteAuthenticatesUserSuccessfully(): void
    {
        // Arrange
        $request = new AuthenticateUserRequest(
            'john.doe@example.com',
            'password123'
        );

        $user = new User(
            'user-123',
            'john.doe@example.com',
            'hashed-password',
            'John',
            'Doe'
        );

        $this->userRepository
            ->expects($this->once())
            ->method('findByEmail')
            ->with('john.doe@example.com')
            ->willReturn($user);

        $this->passwordHasher
            ->expects($this->once())
            ->method('verify')
            ->with('password123', 'hashed-password')
            ->willReturn(true);

        $this->tokenGenerator
            ->expects($this->once())
            ->method('generate')
            ->with(
                $this->callback(function (array $payload): bool {
                    return $payload['userId'] === 'user-123' &&
                           $payload['email'] === 'john.doe@example.com' &&
                           $payload['type'] === 'user' &&
                           isset($payload['iat']) &&
                           isset($payload['exp']);
                }),
                3600
            )
            ->willReturn('jwt-token-123');

        // Act
        $response = $this->authenticateUser->execute($request);

        // Assert
        $this->assertInstanceOf(AuthenticateUserResponse::class, $response);
        $this->assertEquals('user-123', $response->userId);
        $this->assertEquals('john.doe@example.com', $response->email);
        $this->assertEquals('John Doe', $response->fullName);
        $this->assertEquals('jwt-token-123', $response->token);
        $this->assertEquals(3600, $response->expiresIn);
    }

    public function testExecuteThrowsExceptionForNonExistentUser(): void
    {
        // Arrange
        $request = new AuthenticateUserRequest(
            'nonexistent@example.com',
            'password123'
        );

        $this->userRepository
            ->expects($this->once())
            ->method('findByEmail')
            ->with('nonexistent@example.com')
            ->willReturn(null);

        $this->passwordHasher->expects($this->never())->method('verify');
        $this->tokenGenerator->expects($this->never())->method('generate');

        // Act & Assert
        $this->expectException(InvalidCredentialsException::class);
        $this->expectExceptionMessage('Invalid email or password');

        $this->authenticateUser->execute($request);
    }

    public function testExecuteThrowsExceptionForInvalidPassword(): void
    {
        // Arrange
        $request = new AuthenticateUserRequest(
            'john.doe@example.com',
            'wrong-password'
        );

        $user = new User(
            'user-123',
            'john.doe@example.com',
            'hashed-password',
            'John',
            'Doe'
        );

        $this->userRepository
            ->expects($this->once())
            ->method('findByEmail')
            ->with('john.doe@example.com')
            ->willReturn($user);

        $this->passwordHasher
            ->expects($this->once())
            ->method('verify')
            ->with('wrong-password', 'hashed-password')
            ->willReturn(false);

        $this->tokenGenerator->expects($this->never())->method('generate');

        // Act & Assert
        $this->expectException(InvalidCredentialsException::class);
        $this->expectExceptionMessage('Invalid email or password');

        $this->authenticateUser->execute($request);
    }

    public function testExecuteThrowsExceptionForEmptyEmail(): void
    {
        // Arrange
        $request = new AuthenticateUserRequest('', 'password123');

        // Act & Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Email is required');

        $this->authenticateUser->execute($request);
    }

    public function testExecuteThrowsExceptionForInvalidEmailFormat(): void
    {
        // Arrange
        $request = new AuthenticateUserRequest('not-an-email', 'password123');

        // Act & Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid email format');

        $this->authenticateUser->execute($request);
    }

    public function testExecuteThrowsExceptionForEmptyPassword(): void
    {
        // Arrange
        $request = new AuthenticateUserRequest('john@example.com', '');

        // Act & Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Password is required');

        $this->authenticateUser->execute($request);
    }
}