<?php

declare(strict_types=1);

namespace ParkingSystem\Domain\Repository;

use ParkingSystem\Domain\Entity\User;

/**
 * UserRepositoryInterface
 * Domain Layer - Repository contract with NO implementation details
 */
interface UserRepositoryInterface
{
    public function save(User $user): void;

    public function findById(string $id): ?User;

    public function findByEmail(string $email): ?User;

    public function findAll(): array;

    public function delete(User $user): void;

    public function exists(string $id): bool;

    public function emailExists(string $email): bool;

    public function count(): int;

    public function findByIds(array $ids): array;

    public function findRecentlyCreated(int $limit = 10): array;
}