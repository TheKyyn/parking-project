<?php

declare(strict_types=1);

namespace ParkingSystem\Tests\Unit\UseCase;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use ParkingSystem\UseCase\Analytics\CalculateMonthlyRevenue;
use ParkingSystem\UseCase\Analytics\CalculateMonthlyRevenueRequest;
use ParkingSystem\UseCase\Analytics\CalculateMonthlyRevenueResponse;
use ParkingSystem\UseCase\Analytics\ParkingNotFoundException;
use ParkingSystem\Domain\Repository\ParkingRepositoryInterface;
use ParkingSystem\Domain\Repository\ReservationRepositoryInterface;
use ParkingSystem\Domain\Repository\ParkingSessionRepositoryInterface;
use ParkingSystem\Domain\Repository\SubscriptionRepositoryInterface;
use ParkingSystem\Domain\Entity\Reservation;
use ParkingSystem\Domain\Entity\ParkingSession;
use ParkingSystem\Domain\Entity\Subscription;

/**
 * CalculateMonthlyRevenueTest
 * Unit tests for CalculateMonthlyRevenue use case
 */
class CalculateMonthlyRevenueTest extends TestCase
{
    private CalculateMonthlyRevenue $calculateRevenue;
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

        $this->calculateRevenue = new CalculateMonthlyRevenue(
            $this->parkingRepository,
            $this->reservationRepository,
            $this->sessionRepository,
            $this->subscriptionRepository
        );
    }

    public function testExecuteCalculatesRevenueSuccessfully(): void
    {
        // Arrange
        $request = new CalculateMonthlyRevenueRequest(
            'parking-456',
            2025,
            1
        );

        $this->parkingRepository
            ->expects($this->once())
            ->method('exists')
            ->with('parking-456')
            ->willReturn(true);

        // Mock reservations
        $reservation = $this->createMockReservation(
            new \DateTimeImmutable('2025-01-15 10:00:00'),
            50.0,
            'completed'
        );
        $this->reservationRepository
            ->method('findByParkingId')
            ->with('parking-456')
            ->willReturn([$reservation]);

        // Mock sessions
        $session = $this->createMockSession(
            new \DateTimeImmutable('2025-01-15 10:00:00'),
            30.0,
            'completed'
        );
        $this->sessionRepository
            ->method('findByParkingId')
            ->with('parking-456')
            ->willReturn([$session]);

        // Mock subscriptions
        $subscription = $this->createMockSubscription(
            new \DateTimeImmutable('2025-01-01'),
            new \DateTimeImmutable('2025-06-01'),
            100.0
        );
        $this->subscriptionRepository
            ->method('findByParkingId')
            ->with('parking-456')
            ->willReturn([$subscription]);

        // Act
        $response = $this->calculateRevenue->execute($request);

        // Assert
        $this->assertInstanceOf(CalculateMonthlyRevenueResponse::class, $response);
        $this->assertEquals('parking-456', $response->parkingId);
        $this->assertEquals(2025, $response->year);
        $this->assertEquals(1, $response->month);
        $this->assertEquals(50.0, $response->reservationRevenue);
        $this->assertEquals(30.0, $response->sessionRevenue);
        $this->assertEquals(100.0, $response->subscriptionRevenue);
        $this->assertEquals(180.0, $response->totalRevenue);
        $this->assertEquals(1, $response->totalReservations);
        $this->assertEquals(1, $response->totalSessions);
        $this->assertEquals(1, $response->activeSubscriptions);
    }

    public function testExecuteExcludesDataOutsideDateRange(): void
    {
        // Arrange
        $request = new CalculateMonthlyRevenueRequest('parking-456', 2025, 1);

        $this->parkingRepository->method('exists')->willReturn(true);

        // Reservation outside date range (February)
        $reservation = $this->createMockReservation(
            new \DateTimeImmutable('2025-02-15 10:00:00'),
            50.0,
            'completed'
        );
        $this->reservationRepository->method('findByParkingId')->willReturn([$reservation]);
        $this->sessionRepository->method('findByParkingId')->willReturn([]);
        $this->subscriptionRepository->method('findByParkingId')->willReturn([]);

        // Act
        $response = $this->calculateRevenue->execute($request);

        // Assert
        $this->assertEquals(0.0, $response->reservationRevenue);
        $this->assertEquals(0, $response->totalReservations);
    }

    public function testExecuteThrowsExceptionForNonExistentParking(): void
    {
        // Arrange
        $request = new CalculateMonthlyRevenueRequest('invalid-parking', 2025, 1);

        $this->parkingRepository
            ->expects($this->once())
            ->method('exists')
            ->with('invalid-parking')
            ->willReturn(false);

        // Act & Assert
        $this->expectException(ParkingNotFoundException::class);
        $this->expectExceptionMessage('Parking not found: invalid-parking');

        $this->calculateRevenue->execute($request);
    }

    public function testExecuteValidatesEmptyParkingId(): void
    {
        // Arrange
        $request = new CalculateMonthlyRevenueRequest('', 2025, 1);

        // Act & Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Parking ID is required');

        $this->calculateRevenue->execute($request);
    }

    public function testExecuteValidatesInvalidMonth(): void
    {
        // Arrange
        $request = new CalculateMonthlyRevenueRequest('parking-456', 2025, 13);

        // Act & Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Month must be between 1 and 12');

        $this->calculateRevenue->execute($request);
    }

    public function testExecuteValidatesInvalidYear(): void
    {
        // Arrange
        $request = new CalculateMonthlyRevenueRequest('parking-456', 1999, 1);

        // Act & Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Year must be between 2000 and 2100');

        $this->calculateRevenue->execute($request);
    }

    public function testExecuteHandlesOverstayedSessions(): void
    {
        // Arrange
        $request = new CalculateMonthlyRevenueRequest('parking-456', 2025, 1);

        $this->parkingRepository->method('exists')->willReturn(true);
        $this->reservationRepository->method('findByParkingId')->willReturn([]);
        $this->subscriptionRepository->method('findByParkingId')->willReturn([]);

        // Overstayed session
        $session = $this->createMock(ParkingSession::class);
        $session->method('getStartTime')->willReturn(new \DateTimeImmutable('2025-01-15'));
        $session->method('getTotalAmount')->willReturn(50.0);
        $session->method('isCompleted')->willReturn(false);
        $session->method('isOverstayed')->willReturn(true);

        $this->sessionRepository->method('findByParkingId')->willReturn([$session]);

        // Act
        $response = $this->calculateRevenue->execute($request);

        // Assert
        $this->assertEquals(20.0, $response->penaltyRevenue);
        $this->assertEquals(30.0, $response->sessionRevenue);
    }

    private function createMockReservation(
        \DateTimeImmutable $startTime,
        float $amount,
        string $status
    ): MockObject {
        $reservation = $this->createMock(Reservation::class);
        $reservation->method('getStartTime')->willReturn($startTime);
        $reservation->method('getTotalAmount')->willReturn($amount);
        $reservation->method('getStatus')->willReturn($status);
        return $reservation;
    }

    private function createMockSession(
        \DateTimeImmutable $startTime,
        float $amount,
        string $status
    ): MockObject {
        $session = $this->createMock(ParkingSession::class);
        $session->method('getStartTime')->willReturn($startTime);
        $session->method('getTotalAmount')->willReturn($amount);
        $session->method('isCompleted')->willReturn($status === 'completed');
        $session->method('isOverstayed')->willReturn($status === 'overstayed');
        return $session;
    }

    private function createMockSubscription(
        \DateTimeImmutable $startDate,
        \DateTimeImmutable $endDate,
        float $monthlyAmount
    ): MockObject {
        $subscription = $this->createMock(Subscription::class);
        $subscription->method('getStartDate')->willReturn($startDate);
        $subscription->method('getEndDate')->willReturn($endDate);
        $subscription->method('getMonthlyAmount')->willReturn($monthlyAmount);
        $subscription->method('isActive')->willReturn(true);
        return $subscription;
    }
}
