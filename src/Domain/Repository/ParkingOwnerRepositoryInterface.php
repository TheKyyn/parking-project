<?php

declare(strict_types=1);

namespace ParkingSystem\Domain\Repository;

use ParkingSystem\Domain\Entity\ParkingOwner;

/**
 * ParkingOwnerRepositoryInterface
 * Domain Layer - Repository contract with NO implementation details
 */
interface ParkingOwnerRepositoryInterface
{
    public function save(ParkingOwner $parkingOwner): void;

    public function findById(string $id): ?ParkingOwner;

    public function findByEmail(string $email): ?ParkingOwner;

    public function findAll(): array;

    public function delete(ParkingOwner $parkingOwner): void;

    public function exists(string $id): bool;

    public function emailExists(string $email): bool;

    public function count(): int;

    public function findByIds(array $ids): array;

    public function findRecentlyCreated(int $limit = 10): array;

    public function findWithMostParkings(int $limit = 10): array;
}