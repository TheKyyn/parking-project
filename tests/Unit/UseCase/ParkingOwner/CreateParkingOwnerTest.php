<?php

declare(strict_types=1);

namespace ParkingSystem\Tests\Unit\UseCase\ParkingOwner;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use ParkingSystem\UseCase\ParkingOwner\CreateParkingOwner;
use ParkingSystem\UseCase\ParkingOwner\CreateParkingOwnerRequest;
use ParkingSystem\UseCase\ParkingOwner\CreateParkingOwnerResponse;
use ParkingSystem\UseCase\ParkingOwner\OwnerAlreadyExistsException;
use ParkingSystem\UseCase\User\PasswordHasherInterface;
use ParkingSystem\UseCase\User\IdGeneratorInterface;
use ParkingSystem\Domain\Repository\ParkingOwnerRepositoryInterface;
use ParkingSystem\Domain\Entity\ParkingOwner;

/**
 * CreateParkingOwnerTest
 * Unit tests for CreateParkingOwner use case - Testing business logic with mocked dependencies
 */
class CreateParkingOwnerTest extends TestCase
{
    private CreateParkingOwner $createOwner;
    private MockObject|ParkingOwnerRepositoryInterface $ownerRepository;
    private MockObject|PasswordHasherInterface $passwordHasher;
    private MockObject|IdGeneratorInterface $idGenerator;

    protected function setUp(): void
    {
        $this->ownerRepository = $this->createMock(ParkingOwnerRepositoryInterface::class);
        $this->passwordHasher = $this->createMock(PasswordHasherInterface::class);
        $this->idGenerator = $this->createMock(IdGeneratorInterface::class);

        $this->createOwner = new CreateParkingOwner(
            $this->ownerRepository,
            $this->passwordHasher,
            $this->idGenerator
        );
    }

    public function testExecuteCreatesOwnerSuccessfully(): void
    {
        // Arrange
        $request = new CreateParkingOwnerRequest(
            'owner@example.com',
            'password123',
            'John',
            'Doe'
        );

        $this->ownerRepository
            ->expects($this->once())
            ->method('emailExists')
            ->with('owner@example.com')
            ->willReturn(false);

        $this->idGenerator
            ->expects($this->once())
            ->method('generate')
            ->willReturn('owner-123');

        $this->passwordHasher
            ->expects($this->once())
            ->method('hash')
            ->with('password123')
            ->willReturn('hashed-password');

        $this->ownerRepository
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(function (ParkingOwner $owner): bool {
                return $owner->getId() === 'owner-123' &&
                       $owner->getEmail() === 'owner@example.com' &&
                       $owner->getPasswordHash() === 'hashed-password' &&
                       $owner->getFirstName() === 'John' &&
                       $owner->getLastName() === 'Doe';
            }));

        // Act
        $response = $this->createOwner->execute($request);

        // Assert
        $this->assertInstanceOf(CreateParkingOwnerResponse::class, $response);
        $this->assertEquals('owner-123', $response->ownerId);
        $this->assertEquals('owner@example.com', $response->email);
        $this->assertEquals('John Doe', $response->fullName);
        $this->assertMatchesRegularExpression('/\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $response->createdAt);
    }

    public function testExecuteThrowsExceptionWhenEmailAlreadyExists(): void
    {
        // Arrange
        $request = new CreateParkingOwnerRequest(
            'existing@example.com',
            'password123',
            'John',
            'Doe'
        );

        $this->ownerRepository
            ->expects($this->once())
            ->method('emailExists')
            ->with('existing@example.com')
            ->willReturn(true);

        $this->idGenerator->expects($this->never())->method('generate');
        $this->passwordHasher->expects($this->never())->method('hash');
        $this->ownerRepository->expects($this->never())->method('save');

        // Act & Assert
        $this->expectException(OwnerAlreadyExistsException::class);
        $this->expectExceptionMessage('Email already registered: existing@example.com');

        $this->createOwner->execute($request);
    }

    public function testExecuteThrowsExceptionForEmptyEmail(): void
    {
        // Arrange
        $request = new CreateParkingOwnerRequest(
            '',
            'password123',
            'John',
            'Doe'
        );

        // Act & Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Email is required');

        $this->createOwner->execute($request);
    }

    public function testExecuteThrowsExceptionForEmptyPassword(): void
    {
        // Arrange
        $request = new CreateParkingOwnerRequest(
            'owner@example.com',
            '',
            'John',
            'Doe'
        );

        // Act & Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Password is required');

        $this->createOwner->execute($request);
    }

    public function testExecuteThrowsExceptionForShortPassword(): void
    {
        // Arrange
        $request = new CreateParkingOwnerRequest(
            'owner@example.com',
            '123',
            'John',
            'Doe'
        );

        // Act & Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Password must be at least 8 characters');

        $this->createOwner->execute($request);
    }

    public function testExecuteThrowsExceptionForEmptyFirstName(): void
    {
        // Arrange
        $request = new CreateParkingOwnerRequest(
            'owner@example.com',
            'password123',
            '',
            'Doe'
        );

        // Act & Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('First name is required');

        $this->createOwner->execute($request);
    }

    public function testExecuteThrowsExceptionForEmptyLastName(): void
    {
        // Arrange
        $request = new CreateParkingOwnerRequest(
            'owner@example.com',
            'password123',
            'John',
            ''
        );

        // Act & Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Last name is required');

        $this->createOwner->execute($request);
    }

    public function testExecuteNormalizesEmailToLowercase(): void
    {
        // Arrange
        $request = new CreateParkingOwnerRequest(
            'Owner.DOE@EXAMPLE.COM',
            'password123',
            'John',
            'Doe'
        );

        $this->ownerRepository
            ->expects($this->once())
            ->method('emailExists')
            ->with('Owner.DOE@EXAMPLE.COM')
            ->willReturn(false);

        $this->idGenerator->method('generate')->willReturn('owner-123');
        $this->passwordHasher->method('hash')->willReturn('hashed-password');

        $this->ownerRepository
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(function (ParkingOwner $owner): bool {
                return $owner->getEmail() === 'owner.doe@example.com';
            }));

        // Act
        $response = $this->createOwner->execute($request);

        // Assert
        $this->assertEquals('owner.doe@example.com', $response->email);
    }
}
