<?php

declare(strict_types=1);

namespace ParkingSystem\Tests\Unit\UseCase\ParkingOwner;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use ParkingSystem\UseCase\ParkingOwner\AuthenticateParkingOwner;
use ParkingSystem\UseCase\ParkingOwner\AuthenticateParkingOwnerRequest;
use ParkingSystem\UseCase\ParkingOwner\AuthenticateParkingOwnerResponse;
use ParkingSystem\UseCase\ParkingOwner\InvalidOwnerCredentialsException;
use ParkingSystem\UseCase\User\PasswordHasherInterface;
use ParkingSystem\UseCase\User\JwtTokenGeneratorInterface;
use ParkingSystem\Domain\Repository\ParkingOwnerRepositoryInterface;
use ParkingSystem\Domain\Entity\ParkingOwner;

/**
 * AuthenticateParkingOwnerTest
 * Unit tests for AuthenticateParkingOwner use case
 */
class AuthenticateParkingOwnerTest extends TestCase
{
    private AuthenticateParkingOwner $authenticateOwner;
    private MockObject|ParkingOwnerRepositoryInterface $ownerRepository;
    private MockObject|PasswordHasherInterface $passwordHasher;
    private MockObject|JwtTokenGeneratorInterface $tokenGenerator;

    protected function setUp(): void
    {
        $this->ownerRepository = $this->createMock(ParkingOwnerRepositoryInterface::class);
        $this->passwordHasher = $this->createMock(PasswordHasherInterface::class);
        $this->tokenGenerator = $this->createMock(JwtTokenGeneratorInterface::class);

        $this->authenticateOwner = new AuthenticateParkingOwner(
            $this->ownerRepository,
            $this->passwordHasher,
            $this->tokenGenerator
        );
    }

    public function testExecuteAuthenticatesOwnerSuccessfully(): void
    {
        // Arrange
        $request = new AuthenticateParkingOwnerRequest(
            'owner@example.com',
            'password123'
        );

        $owner = new ParkingOwner(
            'owner-123',
            'owner@example.com',
            'hashed-password',
            'John',
            'Doe'
        );

        $this->ownerRepository
            ->expects($this->once())
            ->method('findByEmail')
            ->with('owner@example.com')
            ->willReturn($owner);

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
                    return $payload['ownerId'] === 'owner-123' &&
                           $payload['email'] === 'owner@example.com' &&
                           $payload['type'] === 'owner' &&
                           isset($payload['iat']) &&
                           isset($payload['exp']);
                }),
                3600
            )
            ->willReturn('jwt-token-123');

        // Act
        $response = $this->authenticateOwner->execute($request);

        // Assert
        $this->assertInstanceOf(AuthenticateParkingOwnerResponse::class, $response);
        $this->assertEquals('owner-123', $response->ownerId);
        $this->assertEquals('owner@example.com', $response->email);
        $this->assertEquals('John Doe', $response->fullName);
        $this->assertEquals('jwt-token-123', $response->token);
        $this->assertEquals(3600, $response->expiresIn);
    }

    public function testExecuteThrowsExceptionForNonExistentOwner(): void
    {
        // Arrange
        $request = new AuthenticateParkingOwnerRequest(
            'nonexistent@example.com',
            'password123'
        );

        $this->ownerRepository
            ->expects($this->once())
            ->method('findByEmail')
            ->with('nonexistent@example.com')
            ->willReturn(null);

        $this->passwordHasher->expects($this->never())->method('verify');
        $this->tokenGenerator->expects($this->never())->method('generate');

        // Act & Assert
        $this->expectException(InvalidOwnerCredentialsException::class);
        $this->expectExceptionMessage('Invalid email or password');

        $this->authenticateOwner->execute($request);
    }

    public function testExecuteThrowsExceptionForInvalidPassword(): void
    {
        // Arrange
        $request = new AuthenticateParkingOwnerRequest(
            'owner@example.com',
            'wrong-password'
        );

        $owner = new ParkingOwner(
            'owner-123',
            'owner@example.com',
            'hashed-password',
            'John',
            'Doe'
        );

        $this->ownerRepository
            ->expects($this->once())
            ->method('findByEmail')
            ->with('owner@example.com')
            ->willReturn($owner);

        $this->passwordHasher
            ->expects($this->once())
            ->method('verify')
            ->with('wrong-password', 'hashed-password')
            ->willReturn(false);

        $this->tokenGenerator->expects($this->never())->method('generate');

        // Act & Assert
        $this->expectException(InvalidOwnerCredentialsException::class);
        $this->expectExceptionMessage('Invalid email or password');

        $this->authenticateOwner->execute($request);
    }

    public function testExecuteThrowsExceptionForEmptyEmail(): void
    {
        // Arrange
        $request = new AuthenticateParkingOwnerRequest('', 'password123');

        // Act & Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Email is required');

        $this->authenticateOwner->execute($request);
    }

    public function testExecuteThrowsExceptionForInvalidEmailFormat(): void
    {
        // Arrange
        $request = new AuthenticateParkingOwnerRequest('not-an-email', 'password123');

        // Act & Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid email format');

        $this->authenticateOwner->execute($request);
    }

    public function testExecuteThrowsExceptionForEmptyPassword(): void
    {
        // Arrange
        $request = new AuthenticateParkingOwnerRequest('owner@example.com', '');

        // Act & Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Password is required');

        $this->authenticateOwner->execute($request);
    }
}
