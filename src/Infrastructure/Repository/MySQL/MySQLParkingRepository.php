<?php

declare(strict_types=1);

namespace ParkingSystem\Infrastructure\Repository\MySQL;

use ParkingSystem\Domain\Entity\Parking;
use ParkingSystem\Domain\Repository\ParkingRepositoryInterface;
use ParkingSystem\Domain\ValueObject\GpsCoordinates;

/**
 * MySQLParkingRepository
 * Infrastructure Layer - MySQL implementation of ParkingRepositoryInterface
 */
class MySQLParkingRepository implements ParkingRepositoryInterface
{
    public function __construct(
        private MySQLConnectionInterface $connection
    ) {
    }

    public function save(Parking $parking): void
    {
        $pdo = $this->connection->getConnection();

        $sql = 'INSERT INTO parkings (id, owner_id, latitude, longitude, total_spaces, hourly_rate, opening_hours, created_at)
                VALUES (:id, :owner_id, :latitude, :longitude, :total_spaces, :hourly_rate, :opening_hours, :created_at)
                ON DUPLICATE KEY UPDATE
                owner_id = VALUES(owner_id),
                latitude = VALUES(latitude),
                longitude = VALUES(longitude),
                total_spaces = VALUES(total_spaces),
                hourly_rate = VALUES(hourly_rate),
                opening_hours = VALUES(opening_hours)';

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'id' => $parking->getId(),
            'owner_id' => $parking->getOwnerId(),
            'latitude' => $parking->getLatitude(),
            'longitude' => $parking->getLongitude(),
            'total_spaces' => $parking->getTotalSpaces(),
            'hourly_rate' => $parking->getHourlyRate(),
            'opening_hours' => json_encode($parking->getOpeningHours()),
            'created_at' => $parking->getCreatedAt()->format('Y-m-d H:i:s')
        ]);
    }

    public function findById(string $id): ?Parking
    {
        $pdo = $this->connection->getConnection();

        $stmt = $pdo->prepare('SELECT * FROM parkings WHERE id = :id');
        $stmt->execute(['id' => $id]);

        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }

        return $this->hydrateParking($row);
    }

    public function findByOwnerId(string $ownerId): array
    {
        $pdo = $this->connection->getConnection();

        $stmt = $pdo->prepare('SELECT * FROM parkings WHERE owner_id = :owner_id ORDER BY created_at DESC');
        $stmt->execute(['owner_id' => $ownerId]);

        $parkings = [];
        while ($row = $stmt->fetch()) {
            $parkings[] = $this->hydrateParking($row);
        }

        return $parkings;
    }

    public function findAll(): array
    {
        $pdo = $this->connection->getConnection();

        $stmt = $pdo->query('SELECT * FROM parkings ORDER BY created_at DESC');
        $parkings = [];

        while ($row = $stmt->fetch()) {
            $parkings[] = $this->hydrateParking($row);
        }

        return $parkings;
    }

    public function findNearLocation(
        GpsCoordinates $location,
        float $radiusKm,
        int $limit = 50
    ): array {
        $pdo = $this->connection->getConnection();

        // Haversine formula for distance calculation in SQL
        $sql = "SELECT *,
                (6371 * acos(cos(radians(:lat)) * cos(radians(latitude)) *
                cos(radians(longitude) - radians(:lng)) +
                sin(radians(:lat2)) * sin(radians(latitude)))) AS distance
                FROM parkings
                HAVING distance <= :radius
                ORDER BY distance
                LIMIT :limit";

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':lat', $location->getLatitude());
        $stmt->bindValue(':lng', $location->getLongitude());
        $stmt->bindValue(':lat2', $location->getLatitude());
        $stmt->bindValue(':radius', $radiusKm);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        $parkings = [];
        while ($row = $stmt->fetch()) {
            $parkings[] = $this->hydrateParking($row);
        }

        return $parkings;
    }

    public function delete(Parking $parking): void
    {
        $pdo = $this->connection->getConnection();

        $stmt = $pdo->prepare('DELETE FROM parkings WHERE id = :id');
        $stmt->execute(['id' => $parking->getId()]);
    }

    public function exists(string $id): bool
    {
        $pdo = $this->connection->getConnection();

        $stmt = $pdo->prepare('SELECT 1 FROM parkings WHERE id = :id');
        $stmt->execute(['id' => $id]);

        return $stmt->fetch() !== false;
    }

    public function count(): int
    {
        $pdo = $this->connection->getConnection();

        $stmt = $pdo->query('SELECT COUNT(*) as count FROM parkings');
        $result = $stmt->fetch();

        return (int)$result['count'];
    }

    public function findByIds(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        $pdo = $this->connection->getConnection();

        $placeholders = str_repeat('?,', count($ids) - 1) . '?';
        $stmt = $pdo->prepare("SELECT * FROM parkings WHERE id IN ($placeholders)");
        $stmt->execute($ids);

        $parkings = [];
        while ($row = $stmt->fetch()) {
            $parkings[] = $this->hydrateParking($row);
        }

        return $parkings;
    }

    private function hydrateParking(array $row): Parking
    {
        $openingHours = json_decode($row['opening_hours'] ?? '[]', true);
        // Convert string keys to integers for day of week
        $openingHoursFixed = [];
        foreach ($openingHours as $day => $hours) {
            $openingHoursFixed[(int)$day] = $hours;
        }

        return new Parking(
            $row['id'],
            $row['owner_id'],
            (float)$row['latitude'],
            (float)$row['longitude'],
            (int)$row['total_spaces'],
            (float)$row['hourly_rate'],
            $openingHoursFixed,
            new \DateTimeImmutable($row['created_at'])
        );
    }
}
