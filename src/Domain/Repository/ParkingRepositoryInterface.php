<?php

declare(strict_types=1);

namespace ParkingSystem\Domain\Repository;

use ParkingSystem\Domain\Entity\Parking;
use ParkingSystem\Domain\ValueObject\GpsCoordinates;

/**
 * ParkingRepositoryInterface
 * Domain Layer - Repository contract with NO implementation details
 */
interface ParkingRepositoryInterface
{
    public function save(Parking $parking): void;

    public function findById(string $id): ?Parking;

    public function findAll(): array;

    public function findByOwnerId(string $ownerId): array;

    public function delete(Parking $parking): void;

    public function exists(string $id): bool;

    public function count(): int;

    public function findByIds(array $ids): array;

    public function findNearLocation(
        GpsCoordinates $location, 
        float $radiusInKilometers, 
        int $limit = 10
    ): array;

    public function findAvailableAt(
        \DateTimeInterface $dateTime, 
        int $limit = 10
    ): array;

    public function findByMinimumSpaces(int $minimumSpaces): array;

    public function findByRateRange(float $minRate, float $maxRate): array;

    public function findMostPopular(int $limit = 10): array;

    public function searchByCriteria(array $criteria): array;

    public function updateAvailableSpots(string $parkingId, int $availableSpots): void;
}