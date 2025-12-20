<?php

declare(strict_types=1);

namespace ParkingSystem\Tests\Unit\UseCase;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use ParkingSystem\UseCase\Parking\CreateParking;
use ParkingSystem\UseCase\Parking\CreateParkingRequest;
use ParkingSystem\UseCase\Parking\CreateParkingResponse;
use ParkingSystem\UseCase\User\IdGeneratorInterface;
use ParkingSystem\UseCase\Parking\OwnerNotFoundException;
use ParkingSystem\Domain\Repository\ParkingRepositoryInterface;
use ParkingSystem\Domain\Repository\ParkingOwnerRepositoryInterface;
use ParkingSystem\Domain\Entity\Parking;
use ParkingSystem\Domain\Entity\ParkingOwner;

/**
 * CreateParkingTest
 * Unit tests for CreateParking use case
 */
class CreateParkingTest extends TestCase
{
    private CreateParking $createParking;
    private MockObject|ParkingRepositoryInterface $parkingRepository;
    private MockObject|ParkingOwnerRepositoryInterface $ownerRepository;
    private MockObject|IdGeneratorInterface $idGenerator;

    protected function setUp(): void
    {
        $this->parkingRepository = $this->createMock(ParkingRepositoryInterface::class);
        $this->ownerRepository = $this->createMock(ParkingOwnerRepositoryInterface::class);
        $this->idGenerator = $this->createMock(IdGeneratorInterface::class);

        $this->createParking = new CreateParking(
            $this->parkingRepository,
            $this->ownerRepository,
            $this->idGenerator
        );
    }

    public function testExecuteCreatesParkingSuccessfully(): void
    {
        // Arrange
        $openingHours = [
            1 => ['open' => '08:00', 'close' => '18:00'], // Monday
            2 => ['open' => '08:00', 'close' => '18:00'], // Tuesday
        ];
        
        $request = new CreateParkingRequest(
            'owner-123',
            'Test Parking Lot',
            'Test Address 12345',
            48.8566,
            2.3522,
            50,
            15.5,
            $openingHours
        );

        $owner = new ParkingOwner(
            'owner-123',
            'owner@example.com',
            'hashed-password',
            'John',
            'Owner'
        );

        $this->ownerRepository
            ->expects($this->once())
            ->method('exists')
            ->with('owner-123')
            ->willReturn(true);

        $this->ownerRepository
            ->expects($this->once())
            ->method('findById')
            ->with('owner-123')
            ->willReturn($owner);

        $this->idGenerator
            ->expects($this->once())
            ->method('generate')
            ->willReturn('parking-456');

        $this->parkingRepository
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(function (Parking $parking): bool {
                return $parking->getId() === 'parking-456' &&
                       $parking->getOwnerId() === 'owner-123' &&
                       $parking->getLatitude() === 48.8566 &&
                       $parking->getLongitude() === 2.3522 &&
                       $parking->getTotalSpaces() === 50 &&
                       $parking->getHourlyRate() === 15.5;
            }));

        $this->ownerRepository
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(function (ParkingOwner $owner): bool {
                return in_array('parking-456', $owner->getOwnedParkings());
            }));

        // Act
        $response = $this->createParking->execute($request);

        // Assert
        $this->assertInstanceOf(CreateParkingResponse::class, $response);
        $this->assertEquals('parking-456', $response->parkingId);
        $this->assertEquals('owner-123', $response->ownerId);
        $this->assertEquals(48.8566, $response->latitude);
        $this->assertEquals(2.3522, $response->longitude);
        $this->assertEquals(50, $response->totalSpaces);
        $this->assertEquals(15.5, $response->hourlyRate);
    }

    public function testExecuteThrowsExceptionForNonExistentOwner(): void
    {
        // Arrange
        $request = new CreateParkingRequest(
            'nonexistent-owner',
            'Test Parking',
            'Test Address 12345',
            48.8566,
            2.3522,
            50,
            15.5
        );

        $this->ownerRepository
            ->expects($this->once())
            ->method('exists')
            ->with('nonexistent-owner')
            ->willReturn(false);

        // Act & Assert
        $this->expectException(OwnerNotFoundException::class);
        $this->expectExceptionMessage('Owner not found: nonexistent-owner');

        $this->createParking->execute($request);
    }

    public function testExecuteValidatesLatitude(): void
    {
        // Arrange
        $request = new CreateParkingRequest(
            'owner-123',
            'Test Parking',
            'Test Address 12345',
            91.0, // Invalid latitude
            2.3522,
            50,
            15.5
        );

        // Act & Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid latitude');

        $this->createParking->execute($request);
    }

    public function testExecuteValidatesOpeningHours(): void
    {
        // Arrange
        $invalidHours = [
            1 => ['open' => '08:00', 'close' => '02:00'], // Close before open
        ];
        
        $request = new CreateParkingRequest(
            'owner-123',
            'Test Parking',
            'Test Address 12345',
            48.8566,
            2.3522,
            50,
            15.5,
            $invalidHours
        );

        // Act & Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Open time must be before close time');

        $this->createParking->execute($request);
    }
}