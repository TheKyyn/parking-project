<?php

declare(strict_types=1);

namespace ParkingSystem\Tests\Unit\UseCase;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use ParkingSystem\UseCase\Subscription\CreateSubscription;
use ParkingSystem\UseCase\Subscription\CreateSubscriptionRequest;
use ParkingSystem\UseCase\Subscription\CreateSubscriptionResponse;
use ParkingSystem\UseCase\Subscription\SlotConflictCheckerInterface;
use ParkingSystem\UseCase\Subscription\PricingCalculatorInterface;
use ParkingSystem\UseCase\Subscription\IdGeneratorInterface;
use ParkingSystem\UseCase\Subscription\UserNotFoundException;
use ParkingSystem\UseCase\Subscription\ParkingNotFoundException;
use ParkingSystem\UseCase\Subscription\SlotConflictException;
use ParkingSystem\UseCase\Subscription\InvalidTimeSlotException;
use ParkingSystem\UseCase\Subscription\InvalidDurationException;
use ParkingSystem\UseCase\Subscription\ActiveSubscriptionExistsException;
use ParkingSystem\Domain\Repository\SubscriptionRepositoryInterface;
use ParkingSystem\Domain\Repository\ParkingRepositoryInterface;
use ParkingSystem\Domain\Repository\UserRepositoryInterface;
use ParkingSystem\Domain\Entity\Parking;
use ParkingSystem\Domain\Entity\Subscription;

/**
 * CreateSubscriptionTest
 * Unit tests for CreateSubscription use case
 */
class CreateSubscriptionTest extends TestCase
{
    private CreateSubscription $createSubscription;
    private MockObject|SubscriptionRepositoryInterface $subscriptionRepository;
    private MockObject|ParkingRepositoryInterface $parkingRepository;
    private MockObject|UserRepositoryInterface $userRepository;
    private MockObject|SlotConflictCheckerInterface $slotConflictChecker;
    private MockObject|PricingCalculatorInterface $pricingCalculator;
    private MockObject|IdGeneratorInterface $idGenerator;

    protected function setUp(): void
    {
        $this->subscriptionRepository = $this->createMock(SubscriptionRepositoryInterface::class);
        $this->parkingRepository = $this->createMock(ParkingRepositoryInterface::class);
        $this->userRepository = $this->createMock(UserRepositoryInterface::class);
        $this->slotConflictChecker = $this->createMock(SlotConflictCheckerInterface::class);
        $this->pricingCalculator = $this->createMock(PricingCalculatorInterface::class);
        $this->idGenerator = $this->createMock(IdGeneratorInterface::class);

        $this->createSubscription = new CreateSubscription(
            $this->subscriptionRepository,
            $this->parkingRepository,
            $this->userRepository,
            $this->slotConflictChecker,
            $this->pricingCalculator,
            $this->idGenerator
        );
    }

    public function testExecuteCreatesSubscriptionSuccessfully(): void
    {
        // Arrange
        $startDate = new \DateTimeImmutable('tomorrow');
        $weeklyTimeSlots = [
            1 => [['start' => '09:00', 'end' => '17:00']], // Monday
            3 => [['start' => '09:00', 'end' => '17:00']], // Wednesday
            5 => [['start' => '09:00', 'end' => '17:00']]  // Friday
        ];

        $request = new CreateSubscriptionRequest(
            'user-123',
            'parking-456',
            $weeklyTimeSlots,
            3, // 3 months
            $startDate
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

        $this->subscriptionRepository
            ->expects($this->once())
            ->method('findActiveSubscriptionsForUser')
            ->with('user-123')
            ->willReturn([]);

        $this->slotConflictChecker
            ->expects($this->once())
            ->method('hasAvailableSlots')
            ->willReturn(true);

        $this->pricingCalculator
            ->expects($this->once())
            ->method('calculateMonthlyPrice')
            ->with(15.0, $weeklyTimeSlots)
            ->willReturn(180.0); // 24 hours/week * €15/hour * discount

        $this->idGenerator
            ->expects($this->once())
            ->method('generate')
            ->willReturn('subscription-999');

        $this->subscriptionRepository
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(function (Subscription $subscription) {
                return $subscription->getId() === 'subscription-999' &&
                       $subscription->getUserId() === 'user-123' &&
                       $subscription->getParkingId() === 'parking-456' &&
                       $subscription->getDurationMonths() === 3 &&
                       $subscription->getStatus() === 'active';
            }));

        // Act
        $response = $this->createSubscription->execute($request);

        // Assert
        $this->assertInstanceOf(CreateSubscriptionResponse::class, $response);
        $this->assertEquals('subscription-999', $response->subscriptionId);
        $this->assertEquals('user-123', $response->userId);
        $this->assertEquals('parking-456', $response->parkingId);
        $this->assertEquals(3, $response->durationMonths);
        $this->assertEquals(180.0, $response->monthlyAmount);
        $this->assertEquals(540.0, $response->totalAmount);
        $this->assertEquals('active', $response->status);
    }

    public function testExecuteThrowsExceptionForNonExistentUser(): void
    {
        // Arrange
        $request = new CreateSubscriptionRequest(
            'invalid-user',
            'parking-456',
            [1 => [['start' => '09:00', 'end' => '17:00']]],
            3,
            new \DateTimeImmutable('tomorrow')
        );

        $this->userRepository
            ->expects($this->once())
            ->method('exists')
            ->with('invalid-user')
            ->willReturn(false);

        // Act & Assert
        $this->expectException(UserNotFoundException::class);
        $this->expectExceptionMessage('User not found: invalid-user');

        $this->createSubscription->execute($request);
    }

    public function testExecuteThrowsExceptionForNonExistentParking(): void
    {
        // Arrange
        $request = new CreateSubscriptionRequest(
            'user-123',
            'invalid-parking',
            [1 => [['start' => '09:00', 'end' => '17:00']]],
            3,
            new \DateTimeImmutable('tomorrow')
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

        $this->createSubscription->execute($request);
    }

    public function testExecuteThrowsExceptionForExistingActiveSubscription(): void
    {
        // Arrange
        $request = new CreateSubscriptionRequest(
            'user-123',
            'parking-456',
            [1 => [['start' => '09:00', 'end' => '17:00']]],
            3,
            new \DateTimeImmutable('tomorrow')
        );

        $parking = new Parking('parking-456', 'owner', 48.8566, 2.3522, 20, 15.0);

        $existingSubscription = new Subscription(
            'sub-existing',
            'user-123',
            'parking-456',
            [1 => [['start' => '08:00', 'end' => '12:00']]],
            6,
            new \DateTimeImmutable(),
            100.0
        );

        $this->userRepository->method('exists')->willReturn(true);
        $this->parkingRepository->method('findById')->willReturn($parking);
        $this->subscriptionRepository
            ->expects($this->once())
            ->method('findActiveSubscriptionsForUser')
            ->with('user-123')
            ->willReturn([$existingSubscription]);

        // Act & Assert
        $this->expectException(ActiveSubscriptionExistsException::class);
        $this->expectExceptionMessage('User already has an active subscription for this parking');

        $this->createSubscription->execute($request);
    }

    public function testExecuteThrowsExceptionForSlotConflict(): void
    {
        // Arrange
        $request = new CreateSubscriptionRequest(
            'user-123',
            'parking-456',
            [1 => [['start' => '09:00', 'end' => '17:00']]],
            3,
            new \DateTimeImmutable('tomorrow')
        );

        $parking = new Parking('parking-456', 'owner', 48.8566, 2.3522, 20, 15.0);

        $this->userRepository->method('exists')->willReturn(true);
        $this->parkingRepository->method('findById')->willReturn($parking);
        $this->subscriptionRepository->method('findActiveSubscriptionsForUser')->willReturn([]);

        $this->slotConflictChecker
            ->expects($this->once())
            ->method('hasAvailableSlots')
            ->willReturn(false);

        // Act & Assert
        $this->expectException(SlotConflictException::class);
        $this->expectExceptionMessage('No available slots for the requested time periods');

        $this->createSubscription->execute($request);
    }

    public function testExecuteValidatesEmptyTimeSlots(): void
    {
        // Arrange
        $request = new CreateSubscriptionRequest(
            'user-123',
            'parking-456',
            [], // Empty slots
            3,
            new \DateTimeImmutable('tomorrow')
        );

        // Act & Assert
        $this->expectException(InvalidTimeSlotException::class);
        $this->expectExceptionMessage('At least one time slot is required');

        $this->createSubscription->execute($request);
    }

    public function testExecuteValidatesInvalidDuration(): void
    {
        // Arrange - duration 0
        $request = new CreateSubscriptionRequest(
            'user-123',
            'parking-456',
            [1 => [['start' => '09:00', 'end' => '17:00']]],
            0,
            new \DateTimeImmutable('tomorrow')
        );

        // Act & Assert
        $this->expectException(InvalidDurationException::class);
        $this->expectExceptionMessage('Duration must be at least 1 month');

        $this->createSubscription->execute($request);
    }

    public function testExecuteValidatesMaxDuration(): void
    {
        // Arrange - duration exceeds 12 months
        $request = new CreateSubscriptionRequest(
            'user-123',
            'parking-456',
            [1 => [['start' => '09:00', 'end' => '17:00']]],
            13,
            new \DateTimeImmutable('tomorrow')
        );

        // Act & Assert
        $this->expectException(InvalidDurationException::class);
        $this->expectExceptionMessage('Duration cannot exceed 12 months');

        $this->createSubscription->execute($request);
    }

    public function testExecuteValidatesInvalidDayOfWeek(): void
    {
        // Arrange - invalid day 7
        $request = new CreateSubscriptionRequest(
            'user-123',
            'parking-456',
            [7 => [['start' => '09:00', 'end' => '17:00']]],
            3,
            new \DateTimeImmutable('tomorrow')
        );

        // Act & Assert
        $this->expectException(InvalidTimeSlotException::class);
        $this->expectExceptionMessage('Day of week must be between 0 (Sunday) and 6 (Saturday)');

        $this->createSubscription->execute($request);
    }

    public function testExecuteValidatesInvalidTimeFormat(): void
    {
        // Arrange - invalid time format
        $request = new CreateSubscriptionRequest(
            'user-123',
            'parking-456',
            [1 => [['start' => '25:00', 'end' => '17:00']]],
            3,
            new \DateTimeImmutable('tomorrow')
        );

        // Act & Assert
        $this->expectException(InvalidTimeSlotException::class);
        $this->expectExceptionMessage('Invalid start time format');

        $this->createSubscription->execute($request);
    }

    public function testExecuteValidatesStartBeforeEnd(): void
    {
        // Arrange - start after end
        $request = new CreateSubscriptionRequest(
            'user-123',
            'parking-456',
            [1 => [['start' => '17:00', 'end' => '09:00']]],
            3,
            new \DateTimeImmutable('tomorrow')
        );

        // Act & Assert
        $this->expectException(InvalidTimeSlotException::class);
        $this->expectExceptionMessage('Start time must be before end time');

        $this->createSubscription->execute($request);
    }

    public function testExecuteValidatesTimeSlotsAgainstOpeningHours(): void
    {
        // Arrange
        $request = new CreateSubscriptionRequest(
            'user-123',
            'parking-456',
            [1 => [['start' => '06:00', 'end' => '08:00']]], // Before opening
            3,
            new \DateTimeImmutable('tomorrow')
        );

        // Parking opens at 08:00
        $parking = new Parking(
            'parking-456',
            'owner',
            48.8566,
            2.3522,
            20,
            15.0,
            [
                1 => ['open' => '08:00', 'close' => '20:00']
            ]
        );

        $this->userRepository->method('exists')->willReturn(true);
        $this->parkingRepository->method('findById')->willReturn($parking);
        $this->subscriptionRepository->method('findActiveSubscriptionsForUser')->willReturn([]);

        // Act & Assert
        $this->expectException(InvalidTimeSlotException::class);
        $this->expectExceptionMessage('Slot starts before parking opens');

        $this->createSubscription->execute($request);
    }
}
