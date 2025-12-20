<?php

declare(strict_types=1);

namespace ParkingSystem\Tests\Unit\UseCase\ParkingOwner;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use ParkingSystem\UseCase\ParkingOwner\UpdateParkingOwner;
use ParkingSystem\UseCase\ParkingOwner\UpdateParkingOwnerRequest;
use ParkingSystem\UseCase\ParkingOwner\CreateParkingOwnerResponse;
use ParkingSystem\Domain\Repository\ParkingOwnerRepositoryInterface;
use ParkingSystem\Domain\Entity\ParkingOwner;

/**
 * UpdateParkingOwnerTest
 * Unit tests for UpdateParkingOwner use case
 */
class UpdateParkingOwnerTest extends TestCase
{
    private UpdateParkingOwner $updateOwner;
    private MockObject|ParkingOwnerRepositoryInterface $ownerRepository;

    protected function setUp(): void
    {
        $this->ownerRepository = $this->createMock(ParkingOwnerRepositoryInterface::class);
        $this->updateOwner = new UpdateParkingOwner($this->ownerRepository);
    }

    public function testExecuteUpdatesOwnerSuccessfully(): void
    {
        // Arrange
        $owner = new ParkingOwner(
            'owner-123',
            'owner@example.com',
            'hashed-password',
            'John',
            'Doe'
        );

        $request = new UpdateParkingOwnerRequest(
            'owner-123',
            'Jane',
            'Smith'
        );

        $this->ownerRepository
            ->expects($this->once())
            ->method('findById')
            ->with('owner-123')
            ->willReturn($owner);

        $this->ownerRepository
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(function (ParkingOwner $savedOwner): bool {
                return $savedOwner->getId() === 'owner-123' &&
                       $savedOwner->getFirstName() === 'Jane' &&
                       $savedOwner->getLastName() === 'Smith' &&
                       $savedOwner->getFullName() === 'Jane Smith';
            }));

        // Act
        $response = $this->updateOwner->execute($request);

        // Assert
        $this->assertInstanceOf(CreateParkingOwnerResponse::class, $response);
        $this->assertEquals('owner-123', $response->ownerId);
        $this->assertEquals('owner@example.com', $response->email);
        $this->assertEquals('Jane Smith', $response->fullName);
    }

    public function testExecuteThrowsExceptionWhenOwnerNotFound(): void
    {
        // Arrange
        $request = new UpdateParkingOwnerRequest(
            'nonexistent-id',
            'Jane',
            'Smith'
        );

        $this->ownerRepository
            ->expects($this->once())
            ->method('findById')
            ->with('nonexistent-id')
            ->willReturn(null);

        $this->ownerRepository->expects($this->never())->method('save');

        // Act & Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Owner not found');

        $this->updateOwner->execute($request);
    }

    public function testExecuteThrowsExceptionForEmptyOwnerId(): void
    {
        // Arrange
        $request = new UpdateParkingOwnerRequest(
            '',
            'Jane',
            'Smith'
        );

        $this->ownerRepository->expects($this->never())->method('findById');

        // Act & Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Owner ID is required');

        $this->updateOwner->execute($request);
    }

    public function testExecuteThrowsExceptionForEmptyFirstName(): void
    {
        // Arrange
        $request = new UpdateParkingOwnerRequest(
            'owner-123',
            '',
            'Smith'
        );

        $this->ownerRepository->expects($this->never())->method('findById');

        // Act & Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('First name is required');

        $this->updateOwner->execute($request);
    }

    public function testExecuteThrowsExceptionForEmptyLastName(): void
    {
        // Arrange
        $request = new UpdateParkingOwnerRequest(
            'owner-123',
            'Jane',
            ''
        );

        $this->ownerRepository->expects($this->never())->method('findById');

        // Act & Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Last name is required');

        $this->updateOwner->execute($request);
    }

    public function testExecuteValidatesNameLengthInEntity(): void
    {
        // Arrange
        $owner = new ParkingOwner(
            'owner-123',
            'owner@example.com',
            'hashed-password',
            'John',
            'Doe'
        );

        $request = new UpdateParkingOwnerRequest(
            'owner-123',
            'J', // Too short (less than 2 characters)
            'Smith'
        );

        $this->ownerRepository
            ->expects($this->once())
            ->method('findById')
            ->with('owner-123')
            ->willReturn($owner);

        $this->ownerRepository->expects($this->never())->method('save');

        // Act & Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Name must be at least 2 characters');

        $this->updateOwner->execute($request);
    }

    public function testExecuteTrimsWhitespaceFromNames(): void
    {
        // Arrange
        $owner = new ParkingOwner(
            'owner-123',
            'owner@example.com',
            'hashed-password',
            'John',
            'Doe'
        );

        $request = new UpdateParkingOwnerRequest(
            'owner-123',
            '  Jane  ',
            '  Smith  '
        );

        $this->ownerRepository
            ->expects($this->once())
            ->method('findById')
            ->with('owner-123')
            ->willReturn($owner);

        $this->ownerRepository
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(function (ParkingOwner $savedOwner): bool {
                return $savedOwner->getFirstName() === 'Jane' &&
                       $savedOwner->getLastName() === 'Smith';
            }));

        // Act
        $response = $this->updateOwner->execute($request);

        // Assert
        $this->assertEquals('Jane Smith', $response->fullName);
    }
}
