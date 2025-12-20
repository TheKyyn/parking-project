<?php

declare(strict_types=1);

namespace ParkingSystem\Tests\Unit\UseCase;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use ParkingSystem\UseCase\Subscription\ValidateSubscriptionSlots;
use ParkingSystem\UseCase\Subscription\ValidateSubscriptionSlotsRequest;
use ParkingSystem\UseCase\Subscription\ValidateSubscriptionSlotsResponse;
use ParkingSystem\UseCase\Subscription\SlotConflictCheckerInterface;
use ParkingSystem\UseCase\Subscription\PricingCalculatorInterface;
use ParkingSystem\UseCase\Subscription\ParkingNotFoundException;
use ParkingSystem\UseCase\Subscription\InvalidTimeSlotException;
use ParkingSystem\UseCase\Subscription\InvalidDurationException;
use ParkingSystem\Domain\Repository\ParkingRepositoryInterface;
use ParkingSystem\Domain\Entity\Parking;

/**
 * ValidateSubscriptionSlotsTest
 * Unit tests for ValidateSubscriptionSlots use case
 */
class ValidateSubscriptionSlotsTest extends TestCase
{
    private ValidateSubscriptionSlots $validateSlots;
    private MockObject|ParkingRepositoryInterface $parkingRepository;
    private MockObject|SlotConflictCheckerInterface $slotConflictChecker;
    private MockObject|PricingCalculatorInterface $pricingCalculator;

    protected function setUp(): void
    {
        $this->parkingRepository = $this->createMock(ParkingRepositoryInterface::class);
        $this->slotConflictChecker = $this->createMock(SlotConflictCheckerInterface::class);
        $this->pricingCalculator = $this->createMock(PricingCalculatorInterface::class);

        $this->validateSlots = new ValidateSubscriptionSlots(
            $this->parkingRepository,
            $this->slotConflictChecker,
            $this->pricingCalculator
        );
    }

    public function testExecuteValidatesAllSlotsSuccessfully(): void
    {
        // Arrange
        $weeklyTimeSlots = [
            1 => [['start' => '09:00', 'end' => '17:00']],
            3 => [['start' => '09:00', 'end' => '17:00']]
        ];

        $request = new ValidateSubscriptionSlotsRequest(
            'parking-456',
            $weeklyTimeSlots,
            new \DateTimeImmutable('tomorrow'),
            3
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

        $this->slotConflictChecker
            ->expects($this->exactly(2))
            ->method('getAvailableSpacesForSlot')
            ->willReturn(10);

        $this->pricingCalculator
            ->expects($this->once())
            ->method('calculateMonthlyPrice')
            ->with(15.0, $this->anything())
            ->willReturn(120.0);

        // Act
        $response = $this->validateSlots->execute($request);

        // Assert
        $this->assertInstanceOf(ValidateSubscriptionSlotsResponse::class, $response);
        $this->assertTrue($response->isValid);
        $this->assertCount(2, $response->validSlots);
        $this->assertEmpty($response->conflictingSlots);
        $this->assertEquals(120.0, $response->estimatedMonthlyPrice);
        $this->assertEquals(360.0, $response->estimatedTotalPrice);
    }

    public function testExecuteDetectsConflictingSlots(): void
    {
        // Arrange
        $weeklyTimeSlots = [
            1 => [['start' => '09:00', 'end' => '12:00']],
            3 => [['start' => '09:00', 'end' => '12:00']]
        ];

        $request = new ValidateSubscriptionSlotsRequest(
            'parking-456',
            $weeklyTimeSlots,
            new \DateTimeImmutable('tomorrow'),
            3
        );

        $parking = new Parking('parking-456', 'owner', 'Test Parking', 'Test Address 12345', 48.8566, 2.3522, 20, 20, 15.0);

        $this->parkingRepository->method('findById')->willReturn($parking);

        // First slot has space, second doesn't
        $this->slotConflictChecker
            ->expects($this->exactly(2))
            ->method('getAvailableSpacesForSlot')
            ->willReturnOnConsecutiveCalls(5, 0);

        $this->pricingCalculator
            ->method('calculateMonthlyPrice')
            ->willReturn(60.0);

        // Act
        $response = $this->validateSlots->execute($request);

        // Assert
        $this->assertFalse($response->isValid);
        $this->assertCount(1, $response->validSlots);
        $this->assertCount(1, $response->conflictingSlots);
        $this->assertEquals('no_available_spaces', array_values($response->conflictingSlots)[0]['reason']);
    }

    public function testExecuteDetectsSlotsOutsideOpeningHours(): void
    {
        // Arrange
        $weeklyTimeSlots = [
            1 => [['start' => '06:00', 'end' => '08:00']], // Before opening
            3 => [['start' => '09:00', 'end' => '12:00']]  // Valid
        ];

        $request = new ValidateSubscriptionSlotsRequest(
            'parking-456',
            $weeklyTimeSlots,
            new \DateTimeImmutable('tomorrow'),
            3
        );

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
                1 => ['open' => '08:00', 'close' => '20:00'],
                3 => ['open' => '08:00', 'close' => '20:00']
            ]
        );

        $this->parkingRepository->method('findById')->willReturn($parking);

        $this->slotConflictChecker
            ->expects($this->once()) // Only called for valid slot
            ->method('getAvailableSpacesForSlot')
            ->willReturn(10);

        $this->pricingCalculator
            ->method('calculateMonthlyPrice')
            ->willReturn(60.0);

        // Act
        $response = $this->validateSlots->execute($request);

        // Assert
        $this->assertFalse($response->isValid);
        $this->assertCount(1, $response->conflictingSlots);
        $this->assertEquals('outside_opening_hours', array_values($response->conflictingSlots)[0]['reason']);
    }

    public function testExecuteThrowsExceptionForNonExistentParking(): void
    {
        // Arrange
        $request = new ValidateSubscriptionSlotsRequest(
            'invalid-parking',
            [1 => [['start' => '09:00', 'end' => '17:00']]],
            new \DateTimeImmutable('tomorrow'),
            3
        );

        $this->parkingRepository
            ->expects($this->once())
            ->method('findById')
            ->with('invalid-parking')
            ->willReturn(null);

        // Act & Assert
        $this->expectException(ParkingNotFoundException::class);
        $this->expectExceptionMessage('Parking not found: invalid-parking');

        $this->validateSlots->execute($request);
    }

    public function testExecuteThrowsExceptionForEmptyTimeSlots(): void
    {
        // Arrange
        $request = new ValidateSubscriptionSlotsRequest(
            'parking-456',
            [],
            new \DateTimeImmutable('tomorrow'),
            3
        );

        // Act & Assert
        $this->expectException(InvalidTimeSlotException::class);
        $this->expectExceptionMessage('At least one time slot is required');

        $this->validateSlots->execute($request);
    }

    public function testExecuteThrowsExceptionForInvalidDuration(): void
    {
        // Arrange
        $request = new ValidateSubscriptionSlotsRequest(
            'parking-456',
            [1 => [['start' => '09:00', 'end' => '17:00']]],
            new \DateTimeImmutable('tomorrow'),
            13 // Invalid
        );

        // Act & Assert
        $this->expectException(InvalidDurationException::class);
        $this->expectExceptionMessage('Duration must be between 1 and 12 months');

        $this->validateSlots->execute($request);
    }

    public function testExecuteReturnsAvailabilityBySlot(): void
    {
        // Arrange
        $weeklyTimeSlots = [
            1 => [
                ['start' => '09:00', 'end' => '12:00'],
                ['start' => '14:00', 'end' => '17:00']
            ]
        ];

        $request = new ValidateSubscriptionSlotsRequest(
            'parking-456',
            $weeklyTimeSlots,
            new \DateTimeImmutable('tomorrow'),
            3
        );

        $parking = new Parking('parking-456', 'owner', 'Test Parking', 'Test Address 12345', 48.8566, 2.3522, 20, 20, 15.0);

        $this->parkingRepository->method('findById')->willReturn($parking);

        $this->slotConflictChecker
            ->expects($this->exactly(2))
            ->method('getAvailableSpacesForSlot')
            ->willReturnOnConsecutiveCalls(15, 8);

        $this->pricingCalculator->method('calculateMonthlyPrice')->willReturn(90.0);

        // Act
        $response = $this->validateSlots->execute($request);

        // Assert
        $this->assertCount(2, $response->availabilityBySlot);
        $this->assertEquals(15, $response->availabilityBySlot['1_0']);
        $this->assertEquals(8, $response->availabilityBySlot['1_1']);
    }
}
