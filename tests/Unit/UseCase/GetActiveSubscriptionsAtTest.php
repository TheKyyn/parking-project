<?php

declare(strict_types=1);

namespace ParkingSystem\Tests\Unit\UseCase;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use ParkingSystem\UseCase\Parking\GetActiveSubscriptionsAt;
use ParkingSystem\UseCase\Parking\GetActiveSubscriptionsAtRequest;
use ParkingSystem\UseCase\Parking\GetActiveSubscriptionsAtResponse;
use ParkingSystem\UseCase\Parking\ParkingNotFoundException;
use ParkingSystem\Domain\Repository\ParkingRepositoryInterface;
use ParkingSystem\Domain\Repository\SubscriptionRepositoryInterface;
use ParkingSystem\Domain\Entity\Subscription;

/**
 * GetActiveSubscriptionsAtTest
 * Unit tests for GetActiveSubscriptionsAt use case
 */
class GetActiveSubscriptionsAtTest extends TestCase
{
    private GetActiveSubscriptionsAt $getSubscriptions;
    private MockObject|ParkingRepositoryInterface $parkingRepository;
    private MockObject|SubscriptionRepositoryInterface $subscriptionRepository;

    protected function setUp(): void
    {
        $this->parkingRepository = $this->createMock(ParkingRepositoryInterface::class);
        $this->subscriptionRepository = $this->createMock(SubscriptionRepositoryInterface::class);

        $this->getSubscriptions = new GetActiveSubscriptionsAt(
            $this->parkingRepository,
            $this->subscriptionRepository
        );
    }

    public function testExecuteReturnsActiveSubscriptions(): void
    {
        // Arrange
        $checkTime = new \DateTimeImmutable('2025-01-15 10:00:00'); // Wednesday
        $request = new GetActiveSubscriptionsAtRequest('parking-456', $checkTime);

        $this->parkingRepository
            ->expects($this->once())
            ->method('exists')
            ->with('parking-456')
            ->willReturn(true);

        $subscription = $this->createMock(Subscription::class);
        $subscription->method('coversTimeSlot')->with($checkTime)->willReturn(true);
        $subscription->method('getId')->willReturn('sub-123');
        $subscription->method('getUserId')->willReturn('user-456');
        $subscription->method('getStartDate')->willReturn(new \DateTimeImmutable('2025-01-01'));
        $subscription->method('getEndDate')->willReturn(new \DateTimeImmutable('2025-06-01'));
        $subscription->method('getWeeklyTimeSlots')->willReturn([
            3 => [['start' => '09:00', 'end' => '17:00']] // Wednesday
        ]);
        $subscription->method('getMonthlyAmount')->willReturn(100.0);
        $subscription->method('getRemainingDays')->willReturn(137);

        $this->subscriptionRepository
            ->method('findActiveSubscriptionsForParking')
            ->with('parking-456')
            ->willReturn([$subscription]);

        // Act
        $response = $this->getSubscriptions->execute($request);

        // Assert
        $this->assertInstanceOf(GetActiveSubscriptionsAtResponse::class, $response);
        $this->assertEquals('parking-456', $response->parkingId);
        $this->assertEquals(1, $response->totalActive);
        $this->assertCount(1, $response->subscriptions);
        $this->assertEquals('sub-123', $response->subscriptions[0]->subscriptionId);
        $this->assertEquals('user-456', $response->subscriptions[0]->userId);
        $this->assertEquals(100.0, $response->subscriptions[0]->monthlyAmount);
    }

    public function testExecuteFiltersInactiveSubscriptions(): void
    {
        // Arrange
        $checkTime = new \DateTimeImmutable('2025-01-15 10:00:00');
        $request = new GetActiveSubscriptionsAtRequest('parking-456', $checkTime);

        $this->parkingRepository->method('exists')->willReturn(true);

        // Subscription that doesn't cover this time
        $subscription = $this->createMock(Subscription::class);
        $subscription->method('coversTimeSlot')->with($checkTime)->willReturn(false);

        $this->subscriptionRepository
            ->method('findActiveSubscriptionsForParking')
            ->willReturn([$subscription]);

        // Act
        $response = $this->getSubscriptions->execute($request);

        // Assert
        $this->assertEquals(0, $response->totalActive);
        $this->assertEmpty($response->subscriptions);
    }

    public function testExecuteReturnsCoveredSlotInfo(): void
    {
        // Arrange
        $checkTime = new \DateTimeImmutable('2025-01-15 14:30:00'); // Wednesday 14:30
        $request = new GetActiveSubscriptionsAtRequest('parking-456', $checkTime);

        $this->parkingRepository->method('exists')->willReturn(true);

        $subscription = $this->createMock(Subscription::class);
        $subscription->method('coversTimeSlot')->willReturn(true);
        $subscription->method('getId')->willReturn('sub-123');
        $subscription->method('getUserId')->willReturn('user-456');
        $subscription->method('getStartDate')->willReturn(new \DateTimeImmutable('2025-01-01'));
        $subscription->method('getEndDate')->willReturn(new \DateTimeImmutable('2025-06-01'));
        $subscription->method('getWeeklyTimeSlots')->willReturn([
            3 => [
                ['start' => '09:00', 'end' => '12:00'],
                ['start' => '14:00', 'end' => '17:00'] // This slot covers 14:30
            ]
        ]);
        $subscription->method('getMonthlyAmount')->willReturn(100.0);
        $subscription->method('getRemainingDays')->willReturn(137);

        $this->subscriptionRepository
            ->method('findActiveSubscriptionsForParking')
            ->willReturn([$subscription]);

        // Act
        $response = $this->getSubscriptions->execute($request);

        // Assert
        $this->assertEquals(1, $response->totalActive);
        $this->assertEquals(3, $response->subscriptions[0]->coveredSlot['day']);
        $this->assertEquals('14:00', $response->subscriptions[0]->coveredSlot['start']);
        $this->assertEquals('17:00', $response->subscriptions[0]->coveredSlot['end']);
    }

    public function testExecuteReturnsEmptyWhenNoSubscriptions(): void
    {
        // Arrange
        $checkTime = new \DateTimeImmutable('2025-01-15 10:00:00');
        $request = new GetActiveSubscriptionsAtRequest('parking-456', $checkTime);

        $this->parkingRepository->method('exists')->willReturn(true);

        $this->subscriptionRepository
            ->method('findActiveSubscriptionsForParking')
            ->willReturn([]);

        // Act
        $response = $this->getSubscriptions->execute($request);

        // Assert
        $this->assertEquals(0, $response->totalActive);
        $this->assertEmpty($response->subscriptions);
    }

    public function testExecuteThrowsExceptionForNonExistentParking(): void
    {
        // Arrange
        $request = new GetActiveSubscriptionsAtRequest(
            'invalid-parking',
            new \DateTimeImmutable()
        );

        $this->parkingRepository
            ->expects($this->once())
            ->method('exists')
            ->with('invalid-parking')
            ->willReturn(false);

        // Act & Assert
        $this->expectException(ParkingNotFoundException::class);
        $this->expectExceptionMessage('Parking not found: invalid-parking');

        $this->getSubscriptions->execute($request);
    }

    public function testExecuteValidatesEmptyParkingId(): void
    {
        // Arrange
        $request = new GetActiveSubscriptionsAtRequest('', new \DateTimeImmutable());

        // Act & Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Parking ID is required');

        $this->getSubscriptions->execute($request);
    }
}
