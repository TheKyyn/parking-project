<?php

declare(strict_types=1);

namespace ParkingSystem\Tests\Unit\UseCase;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use ParkingSystem\UseCase\Reservation\CreateReservation;
use ParkingSystem\UseCase\Reservation\CreateReservationRequest;
use ParkingSystem\UseCase\Reservation\CreateReservationResponse;
use ParkingSystem\UseCase\Reservation\ConflictCheckerInterface;
use ParkingSystem\UseCase\Reservation\PricingCalculatorInterface;
use ParkingSystem\UseCase\Reservation\IdGeneratorInterface;
use ParkingSystem\UseCase\Reservation\NoAvailableSpaceException;
use ParkingSystem\UseCase\Reservation\InvalidReservationTimeException;
use ParkingSystem\UseCase\Reservation\UserNotFoundException;
use ParkingSystem\UseCase\Reservation\ParkingNotFoundException;
use ParkingSystem\Domain\Repository\ReservationRepositoryInterface;
use ParkingSystem\Domain\Repository\ParkingRepositoryInterface;
use ParkingSystem\Domain\Repository\UserRepositoryInterface;
use ParkingSystem\Domain\Entity\Parking;
use ParkingSystem\Domain\Entity\Reservation;

/**
 * CreateReservationTest
 * Unit tests for CreateReservation use case with business rules validation
 */
class CreateReservationTest extends TestCase
{
    private CreateReservation $createReservation;
    private MockObject|ReservationRepositoryInterface $reservationRepository;
    private MockObject|ParkingRepositoryInterface $parkingRepository;
    private MockObject|UserRepositoryInterface $userRepository;
    private MockObject|ConflictCheckerInterface $conflictChecker;
    private MockObject|PricingCalculatorInterface $pricingCalculator;
    private MockObject|IdGeneratorInterface $idGenerator;

    protected function setUp(): void
    {
        $this->reservationRepository = $this->createMock(ReservationRepositoryInterface::class);
        $this->parkingRepository = $this->createMock(ParkingRepositoryInterface::class);
        $this->userRepository = $this->createMock(UserRepositoryInterface::class);
        $this->conflictChecker = $this->createMock(ConflictCheckerInterface::class);
        $this->pricingCalculator = $this->createMock(PricingCalculatorInterface::class);
        $this->idGenerator = $this->createMock(IdGeneratorInterface::class);

        $this->createReservation = new CreateReservation(
            $this->reservationRepository,
            $this->parkingRepository,
            $this->userRepository,
            $this->conflictChecker,
            $this->pricingCalculator,
            $this->idGenerator
        );
    }

    public function testExecuteCreatesReservationSuccessfully(): void
    {
        // Arrange
        $startTime = new \DateTimeImmutable('tomorrow 10:00');
        $endTime = new \DateTimeImmutable('tomorrow 12:00');
        
        $request = new CreateReservationRequest(
            'user-123',
            'parking-456',
            $startTime,
            $endTime
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
            15.0 // €15/hour
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

        $this->conflictChecker
            ->expects($this->once())
            ->method('hasAvailableSpacesDuring')
            ->with('parking-456', $startTime, $endTime)
            ->willReturn(true);

        $this->pricingCalculator
            ->expects($this->once())
            ->method('calculateReservationPrice')
            ->with(15.0, $startTime, $endTime)
            ->willReturn(30.0); // 2 hours * €15

        $this->idGenerator
            ->expects($this->once())
            ->method('generate')
            ->willReturn('reservation-999');

        $this->reservationRepository
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(function (Reservation $reservation) {
                return $reservation->getId() === 'reservation-999' &&
                       $reservation->getUserId() === 'user-123' &&
                       $reservation->getParkingId() === 'parking-456' &&
                       $reservation->getTotalAmount() === 30.0 &&
                       $reservation->getStatus() === 'confirmed';
            }));

        $this->parkingRepository
            ->expects($this->once())
            ->method('updateAvailableSpots')
            ->with('parking-456', 19);

        // Act
        $response = $this->createReservation->execute($request);

        // Assert
        $this->assertInstanceOf(CreateReservationResponse::class, $response);
        $this->assertEquals('reservation-999', $response->reservationId);
        $this->assertEquals('user-123', $response->userId);
        $this->assertEquals('parking-456', $response->parkingId);
        $this->assertEquals(30.0, $response->totalAmount);
        $this->assertEquals(120, $response->durationMinutes);
        $this->assertEquals('confirmed', $response->status);
    }

    public function testExecuteThrowsExceptionForNonExistentUser(): void
    {
        // Arrange
        $request = new CreateReservationRequest(
            'invalid-user',
            'parking-456',
            new \DateTimeImmutable('tomorrow 10:00'),
            new \DateTimeImmutable('tomorrow 12:00')
        );

        $this->userRepository
            ->expects($this->once())
            ->method('exists')
            ->with('invalid-user')
            ->willReturn(false);

        // Act & Assert
        $this->expectException(UserNotFoundException::class);
        $this->expectExceptionMessage('User not found: invalid-user');

        $this->createReservation->execute($request);
    }

    public function testExecuteThrowsExceptionForNoAvailableSpace(): void
    {
        // Arrange
        $startTime = new \DateTimeImmutable('tomorrow 10:00');
        $endTime = new \DateTimeImmutable('tomorrow 12:00');
        
        $request = new CreateReservationRequest('user-123', 'parking-456', $startTime, $endTime);

        $this->userRepository->method('exists')->willReturn(true);
        $this->parkingRepository->method('findById')
            ->willReturn(new Parking('parking-456', 'owner', 'Test Parking', 'Test Address 12345', 48.8566, 2.3522, 10, 10, 15.0));

        $this->conflictChecker
            ->expects($this->once())
            ->method('hasAvailableSpacesDuring')
            ->willReturn(false);

        // Act & Assert
        $this->expectException(NoAvailableSpaceException::class);
        $this->expectExceptionMessage('No available spaces for the requested time period');

        $this->createReservation->execute($request);
    }

    public function testExecuteValidatesPastStartTime(): void
    {
        // Arrange
        $request = new CreateReservationRequest(
            'user-123',
            'parking-456',
            new \DateTimeImmutable('-1 hour'),
            new \DateTimeImmutable('+1 hour')
        );

        // Act & Assert
        $this->expectException(InvalidReservationTimeException::class);
        $this->expectExceptionMessage('Reservation start time cannot be in the past');

        $this->createReservation->execute($request);
    }

    public function testExecuteValidatesMinimumDuration(): void
    {
        // Arrange - 10 minutes duration (less than 15 min minimum)
        $request = new CreateReservationRequest(
            'user-123',
            'parking-456',
            new \DateTimeImmutable('tomorrow 10:00'),
            new \DateTimeImmutable('tomorrow 10:10')
        );

        // Act & Assert
        $this->expectException(InvalidReservationTimeException::class);
        $this->expectExceptionMessage('Minimum reservation duration is 15 minutes');

        $this->createReservation->execute($request);
    }

    public function testExecuteValidatesMaximumDuration(): void
    {
        // Arrange - 25 hours duration (exceeds 24 hour maximum)
        $request = new CreateReservationRequest(
            'user-123',
            'parking-456',
            new \DateTimeImmutable('tomorrow 10:00'),
            new \DateTimeImmutable('tomorrow 10:00')->add(new \DateInterval('PT25H'))
        );

        // Act & Assert
        $this->expectException(InvalidReservationTimeException::class);
        $this->expectExceptionMessage('Reservation cannot exceed 24 hours');

        $this->createReservation->execute($request);
    }

    public function testExecuteValidatesParkingOpenHours(): void
    {
        // Arrange
        $startTime = new \DateTimeImmutable('tomorrow 06:00'); // Early morning
        $endTime = new \DateTimeImmutable('tomorrow 08:00');
        
        $request = new CreateReservationRequest('user-123', 'parking-456', $startTime, $endTime);

        // Parking only open 8AM-8PM
        $parking = new Parking(
            'parking-456',
            'owner-789',
            'Test Parking',
            'Test Address 12345',
            48.8566,
            2.3522,
            20,
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
        $this->expectException(InvalidReservationTimeException::class);
        $this->expectExceptionMessage('Parking is closed at the requested start time');

        $this->createReservation->execute($request);
    }

    public function testExecuteCalculatesPriceWith15MinuteIncrements(): void
    {
        // Arrange - 1 hour 20 minutes = 80 minutes
        $startTime = new \DateTimeImmutable('tomorrow 10:00');
        $endTime = new \DateTimeImmutable('tomorrow 11:20');
        
        $request = new CreateReservationRequest('user-123', 'parking-456', $startTime, $endTime);

        $parking = new Parking('parking-456', 'owner', 'Test Parking', 'Test Address 12345', 48.8566, 2.3522, 20, 20, 15.0);

        $this->userRepository->method('exists')->willReturn(true);
        $this->parkingRepository->method('findById')->willReturn($parking);
        $this->conflictChecker->method('hasAvailableSpacesDuring')->willReturn(true);
        $this->idGenerator->method('generate')->willReturn('res-123');

        // Should round up to 90 minutes (6 x 15-minute increments)
        $this->pricingCalculator
            ->expects($this->once())
            ->method('calculateReservationPrice')
            ->with(15.0, $startTime, $endTime)
            ->willReturn(22.5); // 1.5 hours * €15

        $this->reservationRepository->expects($this->once())->method('save');
        $this->parkingRepository->expects($this->once())->method('updateAvailableSpots');

        // Act
        $response = $this->createReservation->execute($request);

        // Assert
        $this->assertEquals(22.5, $response->totalAmount);
        $this->assertEquals(80, $response->durationMinutes); // Actual duration
    }
}