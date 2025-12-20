<?php

declare(strict_types=1);

namespace ParkingSystem\Tests\Unit\UseCase\ParkingOwner;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use ParkingSystem\UseCase\ParkingOwner\GetParkingOwnerProfile;
use ParkingSystem\UseCase\ParkingOwner\CreateParkingOwnerResponse;
use ParkingSystem\Domain\Repository\ParkingOwnerRepositoryInterface;
use ParkingSystem\Domain\Entity\ParkingOwner;

/**
 * GetParkingOwnerProfileTest
 * Unit tests for GetParkingOwnerProfile use case
 */
class GetParkingOwnerProfileTest extends TestCase
{
    private GetParkingOwnerProfile $getProfile;
    private MockObject|ParkingOwnerRepositoryInterface $ownerRepository;

    protected function setUp(): void
    {
        $this->ownerRepository = $this->createMock(ParkingOwnerRepositoryInterface::class);
        $this->getProfile = new GetParkingOwnerProfile($this->ownerRepository);
    }

    public function testExecuteReturnsOwnerProfile(): void
    {
        // Arrange
        $owner = new ParkingOwner(
            'owner-123',
            'owner@example.com',
            'hashed-password',
            'John',
            'Doe'
        );

        $this->ownerRepository
            ->expects($this->once())
            ->method('findById')
            ->with('owner-123')
            ->willReturn($owner);

        // Act
        $response = $this->getProfile->execute('owner-123');

        // Assert
        $this->assertInstanceOf(CreateParkingOwnerResponse::class, $response);
        $this->assertEquals('owner-123', $response->ownerId);
        $this->assertEquals('owner@example.com', $response->email);
        $this->assertEquals('John Doe', $response->fullName);
        $this->assertMatchesRegularExpression('/\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $response->createdAt);
    }

    public function testExecuteThrowsExceptionWhenOwnerNotFound(): void
    {
        // Arrange
        $this->ownerRepository
            ->expects($this->once())
            ->method('findById')
            ->with('nonexistent-id')
            ->willReturn(null);

        // Act & Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Owner not found');

        $this->getProfile->execute('nonexistent-id');
    }

    public function testExecuteThrowsExceptionForEmptyOwnerId(): void
    {
        // Arrange
        $this->ownerRepository->expects($this->never())->method('findById');

        // Act & Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Owner ID is required');

        $this->getProfile->execute('');
    }

    public function testExecuteThrowsExceptionForWhitespaceOwnerId(): void
    {
        // Arrange
        $this->ownerRepository->expects($this->never())->method('findById');

        // Act & Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Owner ID is required');

        $this->getProfile->execute('   ');
    }
}
