<?php

declare(strict_types=1);

namespace ParkingSystem\Tests\Unit\UseCase;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use ParkingSystem\UseCase\Session\ExitParking;
use ParkingSystem\UseCase\Session\ExitParkingRequest;
use ParkingSystem\UseCase\Session\ExitParkingResponse;
use ParkingSystem\UseCase\Session\PricingCalculatorInterface;
use ParkingSystem\UseCase\Session\EntryValidatorInterface;
use ParkingSystem\UseCase\Session\SessionNotFoundException;
use ParkingSystem\UseCase\Session\ParkingNotFoundException;
use ParkingSystem\Domain\Repository\ParkingSessionRepositoryInterface;
use ParkingSystem\Domain\Repository\ParkingRepositoryInterface;
use ParkingSystem\Domain\Repository\ReservationRepositoryInterface;
use ParkingSystem\Domain\Entity\Parking;
use ParkingSystem\Domain\Entity\ParkingSession;
use ParkingSystem\Domain\Entity\Reservation;

/**
 * ExitParkingTest
 * Unit tests for ExitParking use case with overstay detection
 */
class ExitParkingTest extends TestCase
{
    private ExitParking $exitParking;
    private MockObject|ParkingSessionRepositoryInterface $sessionRepository;
    private MockObject|ParkingRepositoryInterface $parkingRepository;
    private MockObject|ReservationRepositoryInterface $reservationRepository;
    private MockObject|PricingCalculatorInterface $pricingCalculator;
    private MockObject|EntryValidatorInterface $entryValidator;

    protected function setUp(): void
    {
        $this->sessionRepository = $this->createMock(ParkingSessionRepositoryInterface::class);
        $this->parkingRepository = $this->createMock(ParkingRepositoryInterface::class);
        $this->reservationRepository = $this->createMock(ReservationRepositoryInterface::class);
        $this->pricingCalculator = $this->createMock(PricingCalculatorInterface::class);
        $this->entryValidator = $this->createMock(EntryValidatorInterface::class);

        $this->exitParking = new ExitParking(
            $this->sessionRepository,
            $this->parkingRepository,
            $this->reservationRepository,
            $this->pricingCalculator,
            $this->entryValidator
        );
    }

    public function testExecuteExitsSuccessfullyWithoutOverstay(): void
    {
        // Arrange
        $startTime = new \DateTimeImmutable('2025-01-15 10:00:00');
        $exitTime = new \DateTimeImmutable('2025-01-15 11:30:00');

        $session = new ParkingSession(
            'session-123',
            'user-456',
            'parking-789',
            $startTime,
            'reservation-111'
        );

        $parking = new Parking(
            'parking-789',
            'owner-999',
            'Test Parking',
            'Test Address 12345',
            48.8566,
            2.3522,
            20,
            20,
            15.0
        );

        $reservationEndTime = new \DateTimeImmutable('2025-01-15 12:00:00');
        $reservation = $this->createMockReservation($reservationEndTime);

        $request = new ExitParkingRequest('session-123', $exitTime);

        $this->sessionRepository
            ->expects($this->once())
            ->method('findById')
            ->with('session-123')
            ->willReturn($session);

        $this->parkingRepository
            ->expects($this->once())
            ->method('findById')
            ->with('parking-789')
            ->willReturn($parking);

        $this->reservationRepository
            ->expects($this->exactly(2))
            ->method('findById')
            ->with('reservation-111')
            ->willReturn($reservation);

        $this->pricingCalculator
            ->expects($this->once())
            ->method('calculateSessionPrice')
            ->with(15.0, $startTime, $exitTime)
            ->willReturn(22.5); // 1.5 hours * €15

        $this->pricingCalculator
            ->expects($this->never())
            ->method('calculateOverstayPenalty');

        $this->sessionRepository
            ->expects($this->once())
            ->method('save');

        // Act
        $response = $this->exitParking->execute($request);

        // Assert
        $this->assertInstanceOf(ExitParkingResponse::class, $response);
        $this->assertEquals('session-123', $response->sessionId);
        $this->assertEquals('user-456', $response->userId);
        $this->assertEquals('parking-789', $response->parkingId);
        $this->assertEquals(90, $response->durationMinutes);
        $this->assertEquals(22.5, $response->baseAmount);
        $this->assertEquals(0.0, $response->overstayPenalty);
        $this->assertEquals(22.5, $response->totalAmount);
        $this->assertFalse($response->wasOverstayed);
        $this->assertEquals('completed', $response->status);
    }

    public function testExecuteDetectsOverstayAndAppliesPenalty(): void
    {
        // Arrange
        $startTime = new \DateTimeImmutable('2025-01-15 10:00:00');
        $exitTime = new \DateTimeImmutable('2025-01-15 13:00:00'); // 1 hour overstay

        $session = new ParkingSession(
            'session-123',
            'user-456',
            'parking-789',
            $startTime,
            'reservation-111'
        );

        $parking = new Parking(
            'parking-789',
            'owner-999',
            'Test Parking',
            'Test Address 12345',
            48.8566,
            2.3522,
            20,
            20,
            15.0
        );

        $reservationEndTime = new \DateTimeImmutable('2025-01-15 12:00:00');
        $reservation = $this->createMockReservation($reservationEndTime);

        $request = new ExitParkingRequest('session-123', $exitTime);

        $this->sessionRepository->method('findById')->willReturn($session);
        $this->parkingRepository->method('findById')->willReturn($parking);
        $this->reservationRepository->method('findById')->willReturn($reservation);

        $this->pricingCalculator
            ->expects($this->once())
            ->method('calculateSessionPrice')
            ->willReturn(45.0); // 3 hours * €15

        $this->pricingCalculator
            ->expects($this->once())
            ->method('calculateOverstayPenalty')
            ->with(15.0, $reservationEndTime, $exitTime)
            ->willReturn(35.0); // €20 penalty + 1 hour additional

        $this->sessionRepository->expects($this->once())->method('save');

        // Act
        $response = $this->exitParking->execute($request);

        // Assert
        $this->assertEquals(45.0, $response->baseAmount);
        $this->assertEquals(35.0, $response->overstayPenalty);
        $this->assertEquals(80.0, $response->totalAmount);
        $this->assertTrue($response->wasOverstayed);
    }

    public function testExecuteThrowsExceptionForNonExistentSession(): void
    {
        // Arrange
        $request = new ExitParkingRequest('invalid-session');

        $this->sessionRepository
            ->expects($this->once())
            ->method('findById')
            ->with('invalid-session')
            ->willReturn(null);

        // Act & Assert
        $this->expectException(SessionNotFoundException::class);
        $this->expectExceptionMessage('Session not found: invalid-session');

        $this->exitParking->execute($request);
    }

    public function testExecuteThrowsExceptionForInactiveSession(): void
    {
        // Arrange
        $startTime = new \DateTimeImmutable('2025-01-15 10:00:00');
        $session = new ParkingSession(
            'session-123',
            'user-456',
            'parking-789',
            $startTime
        );

        // End the session to make it inactive
        $session->endSession(
            new \DateTimeImmutable('2025-01-15 12:00:00'),
            30.0
        );

        $request = new ExitParkingRequest('session-123');

        $this->sessionRepository->method('findById')->willReturn($session);

        // Act & Assert
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Cannot exit: session is not active');

        $this->exitParking->execute($request);
    }

    public function testExecuteThrowsExceptionForNonExistentParking(): void
    {
        // Arrange
        $session = new ParkingSession(
            'session-123',
            'user-456',
            'invalid-parking',
            new \DateTimeImmutable('2025-01-15 10:00:00')
        );

        $request = new ExitParkingRequest('session-123');

        $this->sessionRepository->method('findById')->willReturn($session);
        $this->parkingRepository
            ->expects($this->once())
            ->method('findById')
            ->with('invalid-parking')
            ->willReturn(null);

        // Act & Assert
        $this->expectException(ParkingNotFoundException::class);
        $this->expectExceptionMessage('Parking not found: invalid-parking');

        $this->exitParking->execute($request);
    }

    public function testExecuteValidatesEmptySessionId(): void
    {
        // Arrange
        $request = new ExitParkingRequest('');

        // Act & Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Session ID is required');

        $this->exitParking->execute($request);
    }

    public function testExecuteExitsSessionWithoutReservation(): void
    {
        // Arrange - Session created via subscription (no reservation)
        $startTime = new \DateTimeImmutable('2025-01-15 10:00:00');
        $exitTime = new \DateTimeImmutable('2025-01-15 12:00:00');

        $session = new ParkingSession(
            'session-123',
            'user-456',
            'parking-789',
            $startTime,
            null // No reservation
        );

        $parking = new Parking(
            'parking-789',
            'owner-999',
            'Test Parking',
            'Test Address 12345',
            48.8566,
            2.3522,
            20,
            20,
            15.0
        );

        $request = new ExitParkingRequest('session-123', $exitTime);

        $this->sessionRepository->method('findById')->willReturn($session);
        $this->parkingRepository->method('findById')->willReturn($parking);
        $this->entryValidator->method('getAuthorizedEndTime')->willReturn(null);

        $this->pricingCalculator
            ->expects($this->once())
            ->method('calculateSessionPrice')
            ->willReturn(30.0);

        $this->sessionRepository->expects($this->once())->method('save');

        // Act
        $response = $this->exitParking->execute($request);

        // Assert
        $this->assertEquals(30.0, $response->totalAmount);
        $this->assertFalse($response->wasOverstayed);
        $this->assertEquals('completed', $response->status);
    }

    public function testExecuteUsesCurrentTimeWhenExitTimeNotProvided(): void
    {
        // Arrange
        $startTime = new \DateTimeImmutable('-1 hour');

        $session = new ParkingSession(
            'session-123',
            'user-456',
            'parking-789',
            $startTime
        );

        $parking = new Parking(
            'parking-789',
            'owner-999',
            'Test Parking',
            'Test Address 12345',
            48.8566,
            2.3522,
            20,
            20,
            15.0
        );

        $request = new ExitParkingRequest('session-123'); // No exit time

        $this->sessionRepository->method('findById')->willReturn($session);
        $this->parkingRepository->method('findById')->willReturn($parking);
        $this->entryValidator->method('getAuthorizedEndTime')->willReturn(null);
        $this->pricingCalculator->method('calculateSessionPrice')->willReturn(15.0);
        $this->sessionRepository->expects($this->once())->method('save');

        // Act
        $response = $this->exitParking->execute($request);

        // Assert
        $this->assertEquals('completed', $response->status);
        $this->assertGreaterThan(55, $response->durationMinutes); // At least 55 mins
    }

    private function createMockReservation(\DateTimeImmutable $endTime): MockObject
    {
        $reservation = $this->createMock(Reservation::class);
        $reservation->method('getEndTime')->willReturn($endTime);
        $reservation->method('isActive')->willReturn(true);
        return $reservation;
    }
}
