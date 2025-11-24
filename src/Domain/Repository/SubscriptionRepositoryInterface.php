<?php

declare(strict_types=1);

namespace ParkingSystem\Domain\Repository;

use ParkingSystem\Domain\Entity\Subscription;

/**
 * SubscriptionRepositoryInterface
 * Domain Layer - Repository contract with NO implementation details
 */
interface SubscriptionRepositoryInterface
{
    public function save(Subscription $subscription): void;

    public function findById(string $id): ?Subscription;

    public function findAll(): array;

    public function findByUserId(string $userId): array;

    public function findByParkingId(string $parkingId): array;

    public function delete(Subscription $subscription): void;

    public function exists(string $id): bool;

    public function count(): int;

    public function findByIds(array $ids): array;

    public function findActiveSubscriptions(): array;

    public function findActiveSubscriptionsForParking(string $parkingId): array;

    public function findActiveSubscriptionsForUser(string $userId): array;

    public function findByStatus(string $status): array;

    public function findExpiringSubscriptions(int $daysBeforeExpiry = 7): array;

    public function findExpiredSubscriptions(): array;

    public function findConflictingSubscriptions(
        string $parkingId,
        array $weeklyTimeSlots
    ): array;

    public function findSubscriptionsActiveAt(
        string $parkingId, 
        \DateTimeInterface $dateTime
    ): array;

    public function getTotalRevenueForParking(
        string $parkingId, 
        ?\DateTimeInterface $from = null, 
        ?\DateTimeInterface $to = null
    ): float;

    public function getAverageSubscriptionDuration(): float;
}