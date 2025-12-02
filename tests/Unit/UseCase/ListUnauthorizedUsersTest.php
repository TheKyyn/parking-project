<?php

declare(strict_types=1);

namespace ParkingSystem\Tests\Unit\UseCase;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use ParkingSystem\UseCase\Parking\ListUnauthorizedUsers;
use ParkingSystem\UseCase\Parking\ListUnauthorizedUsersRequest;
use ParkingSystem\UseCase\Parking\ListUnauthorizedUsersResponse;
use ParkingSystem\UseCase\Parking\ParkingNotFoundException;
use ParkingSystem\Domain\Repository\ParkingRepositoryInterface;
use ParkingSystem\Domain\Repository\ParkingSessionRepositoryInterface;
use ParkingSystem\Domain\Repository\ReservationRepositoryInterface;
use ParkingSystem\Domain\Repository\SubscriptionRepositoryInterface;
use ParkingSystem\Domain\Entity\Parking;
use ParkingSystem\Domain\Entity\ParkingSession;
use ParkingSystem\Domain\Entity\Reservation;
use ParkingSystem\Domain\Entity\Subscription;

/**
 * ListUnauthorizedUsersTest
 * Unit tests for ListUnauthorizedUsers use case
 */
class ListUnauthorizedUsersTest extends TestCase
{
    private ListUnauthorizedUsers $listUnauthorized;
    private MockObject|ParkingRepositoryInterface $parkingRepository;
    private MockObject|ParkingSessionRepositoryInterface $sessionRepository;
    private MockObject|ReservationRepositoryInterface $reservationRepository;
    private MockObject|SubscriptionRepositoryInterface $subscriptionRepository;

    protected function setUp(): void
    {
        $this->parkingRepository = $this->createMock(ParkingRepositoryInterface::class);
        $this->sessionRepository = $this->createMock(ParkingSessionRepositoryInterface::class);
        $this->reservationRepository = $this->createMock(ReservationRepositoryInterface::class);
        $this->subscriptionRepository = $this->createMock(SubscriptionRepositoryInterface::class);

        $this->listUnauthorized = new ListUnauthorizedUsers(
            $this->parkingRepository,
            $this->sessionRepository,
            $this->reservationRepository,
            $this->subscriptionRepository
        );
    }

    public function testExecuteReturnsEmptyWhenNoUnauthorized(): void
    {
        // Arrange
        $request = new ListUnauthorizedUsersRequest('parking-456');

        $parking = new Parking('parking-456', 'owner', 48.8566, 2.3522, 20, 15.0);

        $this->parkingRepository
            ->expects($this->once())
            ->method('findById')
            ->with('parking-456')
            ->willReturn($parking);

        $this->sessionRepository
            ->method('findActiveSessionsForParking')
            ->willReturn([]);

        // Act
        $response = $this->listUnauthorized->execute($request);

        // Assert
        $this->assertInstanceOf(ListUnauthorizedUsersResponse::class, $response);
        $this->assertEquals('parking-456', $response->parkingId);
        $this->assertEquals(0, $response->totalUnauthorized);
        $this->assertEmpty($response->unauthorizedUsers);
    }

    public function testExecuteDetectsUserWithExpiredReservation(): void
    {
        // Arrange
        $checkTime = new \DateTimeImmutable('2025-01-15 14:00:00');
        $request = new ListUnauthorizedUsersRequest('parking-456', $checkTime);

        $parking = new Parking('parking-456', 'owner', 48.8566, 2.3522, 20, 15.0);

        $this->parkingRepository->method('findById')->willReturn($parking);

        // Session with expired reservation
        $session = $this->createMock(ParkingSession::class);
        $session->method('getId')->willReturn('session-123');
        $session->method('getUserId')->willReturn('user-456');
        $session->method('getReservationId')->willReturn('reservation-789');
        $session->method('getStartTime')->willReturn(new \DateTimeImmutable('2025-01-15 10:00:00'));
        $session->method('getCurrentDurationInMinutes')->willReturn(240); // 4 hours

        $this->sessionRepository
            ->method('findActiveSessionsForParking')
            ->willReturn([$session]);

        // Expired reservation (ended at 12:00)
        $reservation = $this->createMock(Reservation::class);
        $reservation->method('getEndTime')->willReturn(new \DateTimeImmutable('2025-01-15 12:00:00'));

        $this->reservationRepository
            ->method('findById')
            ->with('reservation-789')
            ->willReturn($reservation);

        // Act
        $response = $this->listUnauthorized->execute($request);

        // Assert
        $this->assertEquals(1, $response->totalUnauthorized);
        $this->assertEquals('session-123', $response->unauthorizedUsers[0]->sessionId);
        $this->assertEquals('user-456', $response->unauthorizedUsers[0]->userId);
        $this->assertEquals('reservation_expired', $response->unauthorizedUsers[0]->reason);
        $this->assertGreaterThan(20.0, $response->unauthorizedUsers[0]->estimatedPenalty);
    }

    public function testExecuteDetectsUserWithoutReservationOrSubscription(): void
    {
        // Arrange
        $checkTime = new \DateTimeImmutable('2025-01-15 10:00:00');
        $request = new ListUnauthorizedUsersRequest('parking-456', $checkTime);

        $parking = new Parking('parking-456', 'owner', 48.8566, 2.3522, 20, 15.0);

        $this->parkingRepository->method('findById')->willReturn($parking);

        // Session without reservation
        $session = $this->createMock(ParkingSession::class);
        $session->method('getId')->willReturn('session-123');
        $session->method('getUserId')->willReturn('user-456');
        $session->method('getParkingId')->willReturn('parking-456');
        $session->method('getReservationId')->willReturn(null);
        $session->method('getStartTime')->willReturn(new \DateTimeImmutable('2025-01-15 09:00:00'));
        $session->method('getCurrentDurationInMinutes')->willReturn(60);

        $this->sessionRepository
            ->method('findActiveSessionsForParking')
            ->willReturn([$session]);

        // No subscriptions
        $this->subscriptionRepository
            ->method('findActiveSubscriptionsForUser')
            ->with('user-456')
            ->willReturn([]);

        // Act
        $response = $this->listUnauthorized->execute($request);

        // Assert
        $this->assertEquals(1, $response->totalUnauthorized);
        $this->assertEquals('no_reservation_or_subscription', $response->unauthorizedUsers[0]->reason);
    }

    public function testExecuteAllowsUserWithValidSubscription(): void
    {
        // Arrange
        $checkTime = new \DateTimeImmutable('2025-01-15 10:00:00');
        $request = new ListUnauthorizedUsersRequest('parking-456', $checkTime);

        $parking = new Parking('parking-456', 'owner', 48.8566, 2.3522, 20, 15.0);

        $this->parkingRepository->method('findById')->willReturn($parking);

        // Session without reservation but with subscription
        $session = $this->createMock(ParkingSession::class);
        $session->method('getId')->willReturn('session-123');
        $session->method('getUserId')->willReturn('user-456');
        $session->method('getParkingId')->willReturn('parking-456');
        $session->method('getReservationId')->willReturn(null);
        $session->method('getStartTime')->willReturn(new \DateTimeImmutable('2025-01-15 09:00:00'));

        $this->sessionRepository
            ->method('findActiveSessionsForParking')
            ->willReturn([$session]);

        // Active subscription that covers this time
        $subscription = $this->createMock(Subscription::class);
        $subscription->method('getParkingId')->willReturn('parking-456');
        $subscription->method('coversTimeSlot')->with($checkTime)->willReturn(true);

        $this->subscriptionRepository
            ->method('findActiveSubscriptionsForUser')
            ->with('user-456')
            ->willReturn([$subscription]);

        // Act
        $response = $this->listUnauthorized->execute($request);

        // Assert
        $this->assertEquals(0, $response->totalUnauthorized);
    }

    public function testExecuteThrowsExceptionForNonExistentParking(): void
    {
        // Arrange
        $request = new ListUnauthorizedUsersRequest('invalid-parking');

        $this->parkingRepository
            ->expects($this->once())
            ->method('findById')
            ->with('invalid-parking')
            ->willReturn(null);

        // Act & Assert
        $this->expectException(ParkingNotFoundException::class);
        $this->expectExceptionMessage('Parking not found: invalid-parking');

        $this->listUnauthorized->execute($request);
    }

    public function testExecuteValidatesEmptyParkingId(): void
    {
        // Arrange
        $request = new ListUnauthorizedUsersRequest('');

        // Act & Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Parking ID is required');

        $this->listUnauthorized->execute($request);
    }
}
