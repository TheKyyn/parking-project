<?php

declare(strict_types=1);

namespace ParkingSystem\Tests\Unit\UseCase;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use ParkingSystem\UseCase\Parking\SearchParkingByLocation;
use ParkingSystem\UseCase\Parking\SearchParkingByLocationRequest;
use ParkingSystem\UseCase\Parking\SearchParkingByLocationResponse;
use ParkingSystem\UseCase\Parking\AvailabilityCheckerInterface;
use ParkingSystem\Domain\Repository\ParkingRepositoryInterface;
use ParkingSystem\Domain\Entity\Parking;
use ParkingSystem\Domain\ValueObject\GpsCoordinates;

/**
 * SearchParkingByLocationTest
 * Unit tests for SearchParkingByLocation use case
 */
class SearchParkingByLocationTest extends TestCase
{
    private SearchParkingByLocation $searchParking;
    private MockObject|ParkingRepositoryInterface $parkingRepository;
    private MockObject|AvailabilityCheckerInterface $availabilityChecker;

    protected function setUp(): void
    {
        $this->parkingRepository = $this->createMock(ParkingRepositoryInterface::class);
        $this->availabilityChecker = $this->createMock(AvailabilityCheckerInterface::class);

        $this->searchParking = new SearchParkingByLocation(
            $this->parkingRepository,
            $this->availabilityChecker
        );
    }

    public function testExecuteFindsAndSortsParkingsByDistance(): void
    {
        // Arrange - Paris coordinates
        $request = new SearchParkingByLocationRequest(
            48.8566, // Latitude
            2.3522,  // Longitude  
            5.0,     // 5km radius
            limit: 10
        );

        // Create mock parkings at different distances
        $closerParking = new Parking(
            'parking-close',
            'owner-1',
            'Close Parking',
            'Address Close 12345',
            48.8570, // Closer
            2.3525,
            10,
            10,
            15.0
        );

        $fartherParking = new Parking(
            'parking-far',
            'owner-2',
            'Far Parking',
            'Address Far 12345',
            48.8600, // Farther
            2.3600,
            20,
            20,
            12.0
        );

        $this->parkingRepository
            ->expects($this->once())
            ->method('findNearLocation')
            ->with(
                $this->callback(function (GpsCoordinates $coords) {
                    return abs($coords->getLatitude() - 48.8566) < 0.0001 &&
                           abs($coords->getLongitude() - 2.3522) < 0.0001;
                }),
                5.0,
                100
            )
            ->willReturn([$fartherParking, $closerParking]); // Return in wrong order

        $this->availabilityChecker
            ->method('getAvailableSpaces')
            ->willReturnCallback(function (string $parkingId) {
                return match ($parkingId) {
                    'parking-close' => 8,
                    'parking-far' => 15,
                    default => 0
                };
            });

        // Act
        $response = $this->searchParking->execute($request);

        // Assert
        $this->assertInstanceOf(SearchParkingByLocationResponse::class, $response);
        $this->assertCount(2, $response->parkings);
        $this->assertEquals(2, $response->totalFound);

        // Results should be sorted by distance (closer first)
        $this->assertEquals('parking-close', $response->parkings[0]->parkingId);
        $this->assertEquals('parking-far', $response->parkings[1]->parkingId);

        // Check distance calculation is reasonable
        $this->assertLessThan($response->parkings[1]->distanceInKilometers, 
                             $response->parkings[0]->distanceInKilometers);
    }

    public function testExecuteFiltersClosedParkings(): void
    {
        // Arrange
        $request = new SearchParkingByLocationRequest(48.8566, 2.3522, 5.0);

        // Create parking that's closed
        $closedParking = new Parking(
            'parking-closed',
            'owner-1',
            'Closed Parking',
            'Test Address 12345',
            48.8570,
            2.3525,
            10,
            10,
            15.0,
            [1 => ['open' => '08:00', 'close' => '18:00']] // Only open Monday 8-18
        );

        $this->parkingRepository
            ->method('findNearLocation')
            ->willReturn([$closedParking]);

        // Act - Search on Sunday (day 0)
        $response = $this->searchParking->execute($request);

        // Assert - No results because parking is closed
        $this->assertEmpty($response->parkings);
        $this->assertEquals(0, $response->totalFound);
    }

    public function testExecuteFiltersUnavailableParkings(): void
    {
        // Arrange
        $startTime = new \DateTimeImmutable('2024-12-01 10:00:00');
        $endTime = new \DateTimeImmutable('2024-12-01 12:00:00');
        
        $request = new SearchParkingByLocationRequest(
            48.8566, 
            2.3522, 
            5.0,
            $startTime,
            $endTime,
            minimumSpaces: 2
        );

        $parking = new Parking('parking-1', 'owner-1', 'Test Parking', 'Test Address 12345', 48.8570, 2.3525, 10, 10, 15.0);

        $this->parkingRepository
            ->method('findNearLocation')
            ->willReturn([$parking]);

        // Mock that parking doesn't have enough spaces during the time range
        $this->availabilityChecker
            ->expects($this->once())
            ->method('hasAvailableSpacesDuring')
            ->with('parking-1', $startTime, $endTime, 2)
            ->willReturn(false);

        // Act
        $response = $this->searchParking->execute($request);

        // Assert
        $this->assertEmpty($response->parkings);
    }

    public function testExecuteFiltersExpensiveParkings(): void
    {
        // Arrange
        $request = new SearchParkingByLocationRequest(
            48.8566, 
            2.3522, 
            5.0,
            maxHourlyRate: 10.0
        );

        $cheapParking = new Parking('cheap', 'owner-1', 'Cheap Parking', 'Test Address 12345', 48.8570, 2.3525, 10, 10, 8.0);
        $expensiveParking = new Parking('expensive', 'owner-2', 'Expensive Parking', 'Test Address 12345', 48.8575, 2.3530, 10, 10, 15.0);

        $this->parkingRepository
            ->method('findNearLocation')
            ->willReturn([$cheapParking, $expensiveParking]);

        $this->availabilityChecker
            ->method('getAvailableSpaces')
            ->willReturn(5);

        // Act
        $response = $this->searchParking->execute($request);

        // Assert
        $this->assertCount(1, $response->parkings);
        $this->assertEquals('cheap', $response->parkings[0]->parkingId);
        $this->assertEquals(8.0, $response->parkings[0]->hourlyRate);
    }

    public function testExecuteValidatesInput(): void
    {
        // Test invalid latitude
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid latitude');
        
        $request = new SearchParkingByLocationRequest(91.0, 2.3522, 5.0);
        $this->searchParking->execute($request);
    }

    public function testExecuteValidatesRadius(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Radius must be between 0 and 100 kilometers');
        
        $request = new SearchParkingByLocationRequest(48.8566, 2.3522, 150.0);
        $this->searchParking->execute($request);
    }

    public function testExecuteValidatesTimeRange(): void
    {
        $start = new \DateTimeImmutable('2024-12-01 12:00:00');
        $end = new \DateTimeImmutable('2024-12-01 10:00:00'); // Before start
        
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Start time must be before end time');
        
        $request = new SearchParkingByLocationRequest(48.8566, 2.3522, 5.0, $start, $end);
        $this->searchParking->execute($request);
    }

    public function testExecuteRespectsLimit(): void
    {
        // Arrange
        $request = new SearchParkingByLocationRequest(48.8566, 2.3522, 5.0, limit: 1);

        $parking1 = new Parking('p1', 'o1', 'Parking 1', 'Test Address 12345', 48.8570, 2.3525, 10, 10, 15.0);
        $parking2 = new Parking('p2', 'o2', 'Parking 2', 'Test Address 12345', 48.8580, 2.3535, 10, 10, 15.0);

        $this->parkingRepository
            ->method('findNearLocation')
            ->willReturn([$parking1, $parking2]);

        $this->availabilityChecker
            ->method('getAvailableSpaces')
            ->willReturn(5);

        // Act
        $response = $this->searchParking->execute($request);

        // Assert
        $this->assertCount(1, $response->parkings);
        $this->assertEquals(2, $response->totalFound); // But total found is still 2
    }
}