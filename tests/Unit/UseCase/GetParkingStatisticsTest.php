<?php

declare(strict_types=1);

namespace ParkingSystem\Tests\Unit\UseCase;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use ParkingSystem\UseCase\Analytics\GetParkingStatistics;
use ParkingSystem\UseCase\Analytics\GetParkingStatisticsRequest;
use ParkingSystem\UseCase\Analytics\GetParkingStatisticsResponse;
use ParkingSystem\UseCase\Analytics\ParkingNotFoundException;
use ParkingSystem\Domain\Repository\ParkingRepositoryInterface;
use ParkingSystem\Domain\Repository\ReservationRepositoryInterface;
use ParkingSystem\Domain\Repository\ParkingSessionRepositoryInterface;
use ParkingSystem\Domain\Repository\SubscriptionRepositoryInterface;
use ParkingSystem\Domain\Entity\Parking;
use ParkingSystem\Domain\Entity\ParkingSession;

/**
 * GetParkingStatisticsTest
 * Unit tests for GetParkingStatistics use case
 */
class GetParkingStatisticsTest extends TestCase
{
    private GetParkingStatistics $getStatistics;
    private MockObject|ParkingRepositoryInterface $parkingRepository;
    private MockObject|ReservationRepositoryInterface $reservationRepository;
    private MockObject|ParkingSessionRepositoryInterface $sessionRepository;
    private MockObject|SubscriptionRepositoryInterface $subscriptionRepository;

    protected function setUp(): void
    {
        $this->parkingRepository = $this->createMock(ParkingRepositoryInterface::class);
        $this->reservationRepository = $this->createMock(ReservationRepositoryInterface::class);
        $this->sessionRepository = $this->createMock(ParkingSessionRepositoryInterface::class);
        $this->subscriptionRepository = $this->createMock(SubscriptionRepositoryInterface::class);

        $this->getStatistics = new GetParkingStatistics(
            $this->parkingRepository,
            $this->reservationRepository,
            $this->sessionRepository,
            $this->subscriptionRepository
        );
    }

    public function testExecuteReturnsStatisticsSuccessfully(): void
    {
        // Arrange
        $fromDate = new \DateTimeImmutable('2025-01-01');
        $toDate = new \DateTimeImmutable('2025-01-31');

        $request = new GetParkingStatisticsRequest(
            'parking-456',
            $fromDate,
            $toDate
        );

        $parking = new Parking(
            'parking-456',
            'owner-789',
            'Test Parking',
            'Test Address 12345',
            48.8566,
            2.3522,
            20,
            20,
            15.0
        );

        $this->parkingRepository
            ->expects($this->once())
            ->method('findById')
            ->with('parking-456')
            ->willReturn($parking);

        $this->reservationRepository
            ->method('findByParkingId')
            ->willReturn([]);

        $this->sessionRepository
            ->method('findByParkingId')
            ->willReturn([]);

        $this->sessionRepository
            ->method('findActiveSessionsForParking')
            ->with('parking-456')
            ->willReturn([]);

        $this->subscriptionRepository
            ->method('findByParkingId')
            ->willReturn([]);

        // Act
        $response = $this->getStatistics->execute($request);

        // Assert
        $this->assertInstanceOf(GetParkingStatisticsResponse::class, $response);
        $this->assertEquals('parking-456', $response->parkingId);
        $this->assertEquals(20, $response->totalSpaces);
        $this->assertEquals(0, $response->currentlyOccupied);
        $this->assertEquals(0.0, $response->occupancyRate);
    }

    public function testExecuteCalculatesOccupancyRate(): void
    {
        // Arrange
        $request = new GetParkingStatisticsRequest(
            'parking-456',
            new \DateTimeImmutable('-30 days'),
            new \DateTimeImmutable()
        );

        $parking = new Parking('parking-456', 'owner', 'Test Parking', 'Test Address 12345', 48.8566, 2.3522, 20, 20, 15.0);

        $this->parkingRepository->method('findById')->willReturn($parking);
        $this->reservationRepository->method('findByParkingId')->willReturn([]);
        $this->sessionRepository->method('findByParkingId')->willReturn([]);
        $this->subscriptionRepository->method('findByParkingId')->willReturn([]);

        // 5 active sessions = 25% occupancy
        $activeSessions = array_fill(0, 5, $this->createMock(ParkingSession::class));
        $this->sessionRepository
            ->method('findActiveSessionsForParking')
            ->willReturn($activeSessions);

        // Act
        $response = $this->getStatistics->execute($request);

        // Assert
        $this->assertEquals(5, $response->currentlyOccupied);
        $this->assertEquals(25.0, $response->occupancyRate);
    }

    public function testExecuteCalculatesPeakHours(): void
    {
        // Arrange
        $request = new GetParkingStatisticsRequest('parking-456');

        $parking = new Parking('parking-456', 'owner', 'Test Parking', 'Test Address 12345', 48.8566, 2.3522, 20, 20, 15.0);

        $this->parkingRepository->method('findById')->willReturn($parking);
        $this->reservationRepository->method('findByParkingId')->willReturn([]);
        $this->subscriptionRepository->method('findByParkingId')->willReturn([]);
        $this->sessionRepository->method('findActiveSessionsForParking')->willReturn([]);

        // Create sessions at different hours
        $sessions = [];
        // 5 sessions at 10:00
        for ($i = 0; $i < 5; $i++) {
            $session = $this->createMock(ParkingSession::class);
            $session->method('getStartTime')->willReturn(new \DateTimeImmutable('-' . $i . ' days 10:00:00'));
            $session->method('isCompleted')->willReturn(true);
            $session->method('isOverstayed')->willReturn(false);
            $session->method('getTotalAmount')->willReturn(15.0);
            $session->method('getDurationInMinutes')->willReturn(60);
            $sessions[] = $session;
        }
        // 3 sessions at 14:00
        for ($i = 0; $i < 3; $i++) {
            $session = $this->createMock(ParkingSession::class);
            $session->method('getStartTime')->willReturn(new \DateTimeImmutable('-' . $i . ' days 14:00:00'));
            $session->method('isCompleted')->willReturn(true);
            $session->method('isOverstayed')->willReturn(false);
            $session->method('getTotalAmount')->willReturn(15.0);
            $session->method('getDurationInMinutes')->willReturn(60);
            $sessions[] = $session;
        }

        $this->sessionRepository->method('findByParkingId')->willReturn($sessions);

        // Act
        $response = $this->getStatistics->execute($request);

        // Assert
        $this->assertNotEmpty($response->peakHours);
        $this->assertEquals(10, $response->peakHours[0]['hour']);
        $this->assertEquals(5, $response->peakHours[0]['sessionCount']);
    }

    public function testExecuteThrowsExceptionForNonExistentParking(): void
    {
        // Arrange
        $request = new GetParkingStatisticsRequest('invalid-parking');

        $this->parkingRepository
            ->expects($this->once())
            ->method('findById')
            ->with('invalid-parking')
            ->willReturn(null);

        // Act & Assert
        $this->expectException(ParkingNotFoundException::class);
        $this->expectExceptionMessage('Parking not found: invalid-parking');

        $this->getStatistics->execute($request);
    }

    public function testExecuteValidatesEmptyParkingId(): void
    {
        // Arrange
        $request = new GetParkingStatisticsRequest('');

        // Act & Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Parking ID is required');

        $this->getStatistics->execute($request);
    }

    public function testExecuteValidatesDateRange(): void
    {
        // Arrange
        $request = new GetParkingStatisticsRequest(
            'parking-456',
            new \DateTimeImmutable('2025-02-01'),
            new \DateTimeImmutable('2025-01-01') // Before from date
        );

        // Act & Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('From date must be before to date');

        $this->getStatistics->execute($request);
    }

    public function testExecuteUsesDefaultDateRangeWhenNotProvided(): void
    {
        // Arrange
        $request = new GetParkingStatisticsRequest('parking-456');

        $parking = new Parking('parking-456', 'owner', 'Test Parking', 'Test Address 12345', 48.8566, 2.3522, 20, 20, 15.0);

        $this->parkingRepository->method('findById')->willReturn($parking);
        $this->reservationRepository->method('findByParkingId')->willReturn([]);
        $this->sessionRepository->method('findByParkingId')->willReturn([]);
        $this->sessionRepository->method('findActiveSessionsForParking')->willReturn([]);
        $this->subscriptionRepository->method('findByParkingId')->willReturn([]);

        // Act
        $response = $this->getStatistics->execute($request);

        // Assert - default is last 30 days
        $this->assertNotEmpty($response->periodStart);
        $this->assertNotEmpty($response->periodEnd);
    }

    public function testExecuteCalculatesOccupancyByDayOfWeek(): void
    {
        // Arrange
        $request = new GetParkingStatisticsRequest('parking-456');

        $parking = new Parking('parking-456', 'owner', 'Test Parking', 'Test Address 12345', 48.8566, 2.3522, 20, 20, 15.0);

        $this->parkingRepository->method('findById')->willReturn($parking);
        $this->reservationRepository->method('findByParkingId')->willReturn([]);
        $this->subscriptionRepository->method('findByParkingId')->willReturn([]);
        $this->sessionRepository->method('findActiveSessionsForParking')->willReturn([]);

        $sessions = [];
        // Create session on Monday
        $session = $this->createMock(ParkingSession::class);
        $session->method('getStartTime')->willReturn(new \DateTimeImmutable('monday this week 10:00:00'));
        $session->method('isCompleted')->willReturn(true);
        $session->method('isOverstayed')->willReturn(false);
        $session->method('getTotalAmount')->willReturn(15.0);
        $session->method('getDurationInMinutes')->willReturn(60);
        $sessions[] = $session;

        $this->sessionRepository->method('findByParkingId')->willReturn($sessions);

        // Act
        $response = $this->getStatistics->execute($request);

        // Assert
        $this->assertCount(7, $response->occupancyByDayOfWeek);
        $this->assertEquals('Sunday', $response->occupancyByDayOfWeek[0]['name']);
    }
}
