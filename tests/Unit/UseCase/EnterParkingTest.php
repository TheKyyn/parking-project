<?php

declare(strict_types=1);

namespace ParkingSystem\Tests\Unit\UseCase;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use ParkingSystem\UseCase\Session\EnterParking;
use ParkingSystem\UseCase\Session\EnterParkingRequest;
use ParkingSystem\UseCase\Session\EnterParkingResponse;
use ParkingSystem\UseCase\Session\EntryValidatorInterface;
use ParkingSystem\UseCase\Session\IdGeneratorInterface;
use ParkingSystem\UseCase\Session\NoAuthorizationException;
use ParkingSystem\UseCase\Session\UserNotFoundException;
use ParkingSystem\UseCase\Session\ParkingNotFoundException;
use ParkingSystem\UseCase\Session\ActiveSessionExistsException;
use ParkingSystem\UseCase\Session\ParkingClosedException;
use ParkingSystem\Domain\Repository\ParkingSessionRepositoryInterface;
use ParkingSystem\Domain\Repository\ParkingRepositoryInterface;
use ParkingSystem\Domain\Repository\UserRepositoryInterface;
use ParkingSystem\Domain\Entity\Parking;
use ParkingSystem\Domain\Entity\ParkingSession;

/**
 * EnterParkingTest
 * Unit tests for EnterParking use case
 */
class EnterParkingTest extends TestCase
{
    private EnterParking $enterParking;
    private MockObject|ParkingSessionRepositoryInterface $sessionRepository;
    private MockObject|ParkingRepositoryInterface $parkingRepository;
    private MockObject|UserRepositoryInterface $userRepository;
    private MockObject|EntryValidatorInterface $entryValidator;
    private MockObject|IdGeneratorInterface $idGenerator;

    protected function setUp(): void
    {
        $this->sessionRepository = $this->createMock(ParkingSessionRepositoryInterface::class);
        $this->parkingRepository = $this->createMock(ParkingRepositoryInterface::class);
        $this->userRepository = $this->createMock(UserRepositoryInterface::class);
        $this->entryValidator = $this->createMock(EntryValidatorInterface::class);
        $this->idGenerator = $this->createMock(IdGeneratorInterface::class);

        $this->enterParking = new EnterParking(
            $this->sessionRepository,
            $this->parkingRepository,
            $this->userRepository,
            $this->entryValidator,
            $this->idGenerator
        );
    }

    public function testExecuteEntersWithReservationSuccessfully(): void
    {
        // Arrange
        $entryTime = new \DateTimeImmutable('2025-01-15 10:00:00');
        $authorizedEndTime = new \DateTimeImmutable('2025-01-15 12:00:00');

        $request = new EnterParkingRequest(
            'user-123',
            'parking-456',
            $entryTime
        );

        $parking = new Parking(
            'parking-456',
            'owner-789',
            48.8566,
            2.3522,
            20,
            15.0
        );

        $this->userRepository
            ->expects($this->once())
            ->method('exists')
            ->with('user-123')
            ->willReturn(true);

        $this->parkingRepository
            ->expects($this->once())
            ->method('findById')
            ->with('parking-456')
            ->willReturn($parking);

        $this->sessionRepository
            ->expects($this->once())
            ->method('findActiveSessionByUserAndParking')
            ->with('user-123', 'parking-456')
            ->willReturn(null);

        $this->entryValidator
            ->expects($this->once())
            ->method('hasActiveReservation')
            ->with('user-123', 'parking-456', $entryTime)
            ->willReturn(true);

        $this->entryValidator
            ->method('hasActiveSubscription')
            ->willReturn(false);

        $this->entryValidator
            ->expects($this->once())
            ->method('getActiveReservationId')
            ->with('user-123', 'parking-456', $entryTime)
            ->willReturn('reservation-111');

        $this->entryValidator
            ->expects($this->once())
            ->method('getAuthorizedEndTime')
            ->with('user-123', 'parking-456', $entryTime)
            ->willReturn($authorizedEndTime);

        $this->idGenerator
            ->expects($this->once())
            ->method('generate')
            ->willReturn('session-999');

        $this->sessionRepository
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(function (ParkingSession $session) {
                return $session->getId() === 'session-999' &&
                       $session->getUserId() === 'user-123' &&
                       $session->getParkingId() === 'parking-456' &&
                       $session->getReservationId() === 'reservation-111' &&
                       $session->getStatus() === 'active';
            }));

        // Act
        $response = $this->enterParking->execute($request);

        // Assert
        $this->assertInstanceOf(EnterParkingResponse::class, $response);
        $this->assertEquals('session-999', $response->sessionId);
        $this->assertEquals('user-123', $response->userId);
        $this->assertEquals('parking-456', $response->parkingId);
        $this->assertEquals('reservation-111', $response->reservationId);
        $this->assertEquals('active', $response->status);
    }

    public function testExecuteEntersWithSubscriptionSuccessfully(): void
    {
        // Arrange
        $entryTime = new \DateTimeImmutable('2025-01-15 10:00:00');

        $request = new EnterParkingRequest('user-123', 'parking-456', $entryTime);

        $parking = new Parking('parking-456', 'owner-789', 48.8566, 2.3522, 20, 15.0);

        $this->userRepository->method('exists')->willReturn(true);
        $this->parkingRepository->method('findById')->willReturn($parking);
        $this->sessionRepository->method('findActiveSessionByUserAndParking')->willReturn(null);

        $this->entryValidator->method('hasActiveReservation')->willReturn(false);
        $this->entryValidator->method('hasActiveSubscription')->willReturn(true);
        $this->entryValidator->method('getActiveReservationId')->willReturn(null);
        $this->entryValidator->method('getAuthorizedEndTime')->willReturn(null);

        $this->idGenerator->method('generate')->willReturn('session-888');
        $this->sessionRepository->expects($this->once())->method('save');

        // Act
        $response = $this->enterParking->execute($request);

        // Assert
        $this->assertEquals('session-888', $response->sessionId);
        $this->assertNull($response->reservationId);
        $this->assertEquals('active', $response->status);
    }

    public function testExecuteThrowsExceptionForNonExistentUser(): void
    {
        // Arrange
        $request = new EnterParkingRequest(
            'invalid-user',
            'parking-456',
            new \DateTimeImmutable()
        );

        $this->userRepository
            ->expects($this->once())
            ->method('exists')
            ->with('invalid-user')
            ->willReturn(false);

        // Act & Assert
        $this->expectException(UserNotFoundException::class);
        $this->expectExceptionMessage('User not found: invalid-user');

        $this->enterParking->execute($request);
    }

    public function testExecuteThrowsExceptionForNonExistentParking(): void
    {
        // Arrange
        $request = new EnterParkingRequest(
            'user-123',
            'invalid-parking',
            new \DateTimeImmutable()
        );

        $this->userRepository->method('exists')->willReturn(true);
        $this->parkingRepository
            ->expects($this->once())
            ->method('findById')
            ->with('invalid-parking')
            ->willReturn(null);

        // Act & Assert
        $this->expectException(ParkingNotFoundException::class);
        $this->expectExceptionMessage('Parking not found: invalid-parking');

        $this->enterParking->execute($request);
    }

    public function testExecuteThrowsExceptionForClosedParking(): void
    {
        // Arrange
        $entryTime = new \DateTimeImmutable('2025-01-15 06:00:00'); // Early morning
        $request = new EnterParkingRequest('user-123', 'parking-456', $entryTime);

        // Parking only open 8AM-8PM on weekdays
        $parking = new Parking(
            'parking-456',
            'owner-789',
            48.8566,
            2.3522,
            20,
            15.0,
            [
                1 => ['open' => '08:00', 'close' => '20:00'],
                2 => ['open' => '08:00', 'close' => '20:00'],
                3 => ['open' => '08:00', 'close' => '20:00'],
                4 => ['open' => '08:00', 'close' => '20:00'],
                5 => ['open' => '08:00', 'close' => '20:00']
            ]
        );

        $this->userRepository->method('exists')->willReturn(true);
        $this->parkingRepository->method('findById')->willReturn($parking);

        // Act & Assert
        $this->expectException(ParkingClosedException::class);
        $this->expectExceptionMessage('Parking is closed at the requested time');

        $this->enterParking->execute($request);
    }

    public function testExecuteThrowsExceptionForExistingActiveSession(): void
    {
        // Arrange
        $entryTime = new \DateTimeImmutable('2025-01-15 10:00:00');
        $request = new EnterParkingRequest('user-123', 'parking-456', $entryTime);

        $parking = new Parking('parking-456', 'owner-789', 48.8566, 2.3522, 20, 15.0);

        $existingSession = new ParkingSession(
            'session-existing',
            'user-123',
            'parking-456',
            new \DateTimeImmutable('2025-01-15 09:00:00')
        );

        $this->userRepository->method('exists')->willReturn(true);
        $this->parkingRepository->method('findById')->willReturn($parking);
        $this->sessionRepository
            ->expects($this->once())
            ->method('findActiveSessionByUserAndParking')
            ->with('user-123', 'parking-456')
            ->willReturn($existingSession);

        // Act & Assert
        $this->expectException(ActiveSessionExistsException::class);
        $this->expectExceptionMessage('User already has an active session in this parking');

        $this->enterParking->execute($request);
    }

    public function testExecuteThrowsExceptionWithoutAuthorization(): void
    {
        // Arrange
        $entryTime = new \DateTimeImmutable('2025-01-15 10:00:00');
        $request = new EnterParkingRequest('user-123', 'parking-456', $entryTime);

        $parking = new Parking('parking-456', 'owner-789', 48.8566, 2.3522, 20, 15.0);

        $this->userRepository->method('exists')->willReturn(true);
        $this->parkingRepository->method('findById')->willReturn($parking);
        $this->sessionRepository->method('findActiveSessionByUserAndParking')->willReturn(null);

        $this->entryValidator
            ->expects($this->once())
            ->method('hasActiveReservation')
            ->willReturn(false);

        $this->entryValidator
            ->expects($this->once())
            ->method('hasActiveSubscription')
            ->willReturn(false);

        // Act & Assert
        $this->expectException(NoAuthorizationException::class);
        $this->expectExceptionMessage('Entry denied: no active reservation or subscription');

        $this->enterParking->execute($request);
    }

    public function testExecuteValidatesEmptyUserId(): void
    {
        // Arrange
        $request = new EnterParkingRequest('', 'parking-456');

        // Act & Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('User ID is required');

        $this->enterParking->execute($request);
    }

    public function testExecuteValidatesEmptyParkingId(): void
    {
        // Arrange
        $request = new EnterParkingRequest('user-123', '');

        // Act & Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Parking ID is required');

        $this->enterParking->execute($request);
    }
}
