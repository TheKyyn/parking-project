<?php

declare(strict_types=1);

namespace ParkingSystem\Domain\Repository;

use ParkingSystem\Domain\Entity\Reservation;

/**
 * ReservationRepositoryInterface
 * Domain Layer - Repository contract with NO implementation details
 */
interface ReservationRepositoryInterface
{
    public function save(Reservation $reservation): void;

    public function findById(string $id): ?Reservation;

    public function findAll(): array;

    public function findByUserId(string $userId): array;

    public function findByParkingId(string $parkingId): array;

    public function delete(Reservation $reservation): void;

    public function exists(string $id): bool;

    public function count(): int;

    public function findByIds(array $ids): array;

    public function findActiveReservations(): array;

    public function findActiveReservationsForParking(string $parkingId): array;

    public function findActiveReservationsForUser(string $userId): array;

    public function findReservationsInTimeRange(
        \DateTimeInterface $startTime, 
        \DateTimeInterface $endTime
    ): array;

    public function findConflictingReservations(
        string $parkingId,
        \DateTimeInterface $startTime,
        \DateTimeInterface $endTime
    ): array;

    public function findByStatus(string $status): array;

    public function findByDateRange(
        \DateTimeInterface $from, 
        \DateTimeInterface $to
    ): array;

    public function findExpiredReservations(): array;

    public function getTotalRevenueForParking(
        string $parkingId, 
        ?\DateTimeInterface $from = null, 
        ?\DateTimeInterface $to = null
    ): float;
}