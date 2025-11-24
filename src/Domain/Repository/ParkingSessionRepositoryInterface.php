<?php

declare(strict_types=1);

namespace ParkingSystem\Domain\Repository;

use ParkingSystem\Domain\Entity\ParkingSession;

/**
 * ParkingSessionRepositoryInterface
 * Domain Layer - Repository contract with NO implementation details
 */
interface ParkingSessionRepositoryInterface
{
    public function save(ParkingSession $session): void;

    public function findById(string $id): ?ParkingSession;

    public function findAll(): array;

    public function findByUserId(string $userId): array;

    public function findByParkingId(string $parkingId): array;

    public function delete(ParkingSession $session): void;

    public function exists(string $id): bool;

    public function count(): int;

    public function findByIds(array $ids): array;

    public function findActiveSessions(): array;

    public function findActiveSessionsForParking(string $parkingId): array;

    public function findActiveSessionsForUser(string $userId): array;

    public function findActiveSessionByUserAndParking(
        string $userId, 
        string $parkingId
    ): ?ParkingSession;

    public function findByStatus(string $status): array;

    public function findByDateRange(
        \DateTimeInterface $from, 
        \DateTimeInterface $to
    ): array;

    public function findOverstayedSessions(): array;

    public function findSessionsWithoutReservation(): array;

    public function findSessionsByReservationId(string $reservationId): array;

    public function getTotalRevenueForParking(
        string $parkingId, 
        ?\DateTimeInterface $from = null, 
        ?\DateTimeInterface $to = null
    ): float;

    public function getAverageSessionDuration(
        string $parkingId, 
        ?\DateTimeInterface $from = null, 
        ?\DateTimeInterface $to = null
    ): float;
}