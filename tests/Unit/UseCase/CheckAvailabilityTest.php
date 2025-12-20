<?php

declare(strict_types=1);

namespace ParkingSystem\Tests\Unit\UseCase;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use ParkingSystem\UseCase\Parking\CheckAvailability;
use ParkingSystem\UseCase\Parking\CheckAvailabilityRequest;
use ParkingSystem\UseCase\Parking\CheckAvailabilityResponse;
use ParkingSystem\UseCase\Parking\ParkingNotFoundException;
use ParkingSystem\Domain\Repository\ParkingRepositoryInterface;
use ParkingSystem\Domain\Repository\ReservationRepositoryInterface;
use ParkingSystem\Domain\Repository\ParkingSessionRepositoryInterface;
use ParkingSystem\Domain\Repository\SubscriptionRepositoryInterface;
use ParkingSystem\Domain\Entity\Parking;
use ParkingSystem\Domain\Entity\Reservation;
use ParkingSystem\Domain\Entity\Subscription;
use ParkingSystem\Domain\Entity\ParkingSession;

/**
 * CheckAvailabilityTest
 * Unit tests for CheckAvailability use case
 */
class CheckAvailabilityTest extends TestCase
{
    private CheckAvailability $checkAvailability;
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

        $this->checkAvailability = new CheckAvailability(
            $this->parkingRepository,
            $this->reservationRepository,
            $this->sessionRepository,
            $this->subscriptionRepository
        );
    }

    public function testExecuteReturnsAvailabilitySuccessfully(): void
    {
        // Arrange
        $checkTime = new \DateTimeImmutable('2025-01-15 10:00:00');

        $request = new CheckAvailabilityRequest('parking-456', $checkTime);

        $parking = new Parking(
            'parking-456',
            'owner-789',
            'Test Parking',
            'Test Address 12345',
            48.8566,
            2.3522,
            20, // 20 total spaces
            20,
            15.0
        );

        $this->parkingRepository
            ->expects($this->once())
            ->method('findById')
            ->with('parking-456')
            ->willReturn($parking);

        $this->reservationRepository
            ->method('findActiveReservationsForParking')
            ->willReturn([]);

        $this->subscriptionRepository
            ->method('findActiveSubscriptionsForParking')
            ->willReturn([]);

        $this->sessionRepository
            ->method('findActiveSessionsForParking')
            ->willReturn([]);

        // Act
        $response = $this->checkAvailability->execute($request);

        // Assert
        $this->assertInstanceOf(CheckAvailabilityResponse::class, $response);
        $this->assertEquals('parking-456', $response->parkingId);
        $this->assertEquals(20, $response->totalSpaces);
        $this->assertEquals(20, $response->availableSpaces);
        $this->assertEquals(0, $response->reservedSpaces);
        $this->assertEquals(0, $response->subscribedSpaces);
        $this->assertEquals(0, $response->activeSessionSpaces);
        $this->assertTrue($response->isOpen);
        $this->assertEquals(15.0, $response->currentHourlyRate);
    }

    public function testExecuteCalculatesOccupiedSpaces(): void
    {
        // Arrange
        $checkTime = new \DateTimeImmutable('2025-01-15 10:00:00');
        $request = new CheckAvailabilityRequest('parking-456', $checkTime);

        $parking = new Parking('parking-456', 'owner', 'Test Parking', 'Test Address 12345', 48.8566, 2.3522, 20, 20, 15.0);

        $this->parkingRepository->method('findById')->willReturn($parking);

        // Mock 3 active reservations
        $reservations = [];
        for ($i = 0; $i < 3; $i++) {
            $reservation = $this->createMock(Reservation::class);
            $reservation->method('isActiveAt')->with($checkTime)->willReturn(true);
            $reservations[] = $reservation;
        }
        $this->reservationRepository
            ->method('findActiveReservationsForParking')
            ->willReturn($reservations);

        // Mock 2 active subscriptions
        $subscriptions = [];
        for ($i = 0; $i < 2; $i++) {
            $subscription = $this->createMock(Subscription::class);
            $subscription->method('coversTimeSlot')->with($checkTime)->willReturn(true);
            $subscriptions[] = $subscription;
        }
        $this->subscriptionRepository
            ->method('findActiveSubscriptionsForParking')
            ->willReturn($subscriptions);

        // Mock 4 active sessions
        $sessions = array_fill(0, 4, $this->createMock(ParkingSession::class));
        $this->sessionRepository
            ->method('findActiveSessionsForParking')
            ->willReturn($sessions);

        // Act
        $response = $this->checkAvailability->execute($request);

        // Assert
        $this->assertEquals(3, $response->reservedSpaces);
        $this->assertEquals(2, $response->subscribedSpaces);
        $this->assertEquals(4, $response->activeSessionSpaces);
        // Available = 20 - max(3+2, 4) = 20 - 5 = 15
        $this->assertEquals(15, $response->availableSpaces);
    }

    public function testExecuteReturnsOpeningHours(): void
    {
        // Arrange - Wednesday check
        $checkTime = new \DateTimeImmutable('2025-01-15 10:00:00'); // Wednesday

        $parking = new Parking(
            'parking-456',
            'owner',
            'Test Parking',
            'Test Address 12345',
            48.8566,
            2.3522,
            20,
            20,
            15.0,
            [
                3 => ['open' => '08:00', 'close' => '20:00'] // Wednesday
            ]
        );

        $request = new CheckAvailabilityRequest('parking-456', $checkTime);

        $this->parkingRepository->method('findById')->willReturn($parking);
        $this->reservationRepository->method('findActiveReservationsForParking')->willReturn([]);
        $this->subscriptionRepository->method('findActiveSubscriptionsForParking')->willReturn([]);
        $this->sessionRepository->method('findActiveSessionsForParking')->willReturn([]);

        // Act
        $response = $this->checkAvailability->execute($request);

        // Assert
        $this->assertTrue($response->isOpen);
        $this->assertEquals('08:00', $response->openingTime);
        $this->assertEquals('20:00', $response->closingTime);
    }

    public function testExecuteDetectsClosedParking(): void
    {
        // Arrange - Sunday check when closed
        $checkTime = new \DateTimeImmutable('2025-01-19 10:00:00'); // Sunday

        $parking = new Parking(
            'parking-456',
            'owner',
            'Test Parking',
            'Test Address 12345',
            48.8566,
            2.3522,
            20,
            20,
            15.0,
            [
                1 => ['open' => '08:00', 'close' => '20:00'] // Only Monday
            ]
        );

        $request = new CheckAvailabilityRequest('parking-456', $checkTime);

        $this->parkingRepository->method('findById')->willReturn($parking);
        $this->reservationRepository->method('findActiveReservationsForParking')->willReturn([]);
        $this->subscriptionRepository->method('findActiveSubscriptionsForParking')->willReturn([]);
        $this->sessionRepository->method('findActiveSessionsForParking')->willReturn([]);

        // Act
        $response = $this->checkAvailability->execute($request);

        // Assert
        $this->assertFalse($response->isOpen);
    }

    public function testExecuteThrowsExceptionForNonExistentParking(): void
    {
        // Arrange
        $request = new CheckAvailabilityRequest(
            'invalid-parking',
            new \DateTimeImmutable()
        );

        $this->parkingRepository
            ->expects($this->once())
            ->method('findById')
            ->with('invalid-parking')
            ->willReturn(null);

        // Act & Assert
        $this->expectException(ParkingNotFoundException::class);
        $this->expectExceptionMessage('Parking not found: invalid-parking');

        $this->checkAvailability->execute($request);
    }

    public function testExecuteValidatesEmptyParkingId(): void
    {
        // Arrange
        $request = new CheckAvailabilityRequest('', new \DateTimeImmutable());

        // Act & Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Parking ID is required');

        $this->checkAvailability->execute($request);
    }
}
