<?php

declare(strict_types=1);

namespace ParkingSystem\Infrastructure\Repository\MySQL;

use ParkingSystem\Domain\Entity\Reservation;
use ParkingSystem\Domain\Repository\ReservationRepositoryInterface;

/**
 * MySQLReservationRepository
 * Infrastructure Layer - MySQL implementation of ReservationRepositoryInterface
 */
class MySQLReservationRepository implements ReservationRepositoryInterface
{
    public function __construct(
        private MySQLConnectionInterface $connection
    ) {
    }

    public function save(Reservation $reservation): void
    {
        $pdo = $this->connection->getConnection();

        $sql = 'INSERT INTO reservations (id, user_id, parking_id, start_time, end_time, total_amount, status, created_at)
                VALUES (:id, :user_id, :parking_id, :start_time, :end_time, :total_amount, :status, :created_at)
                ON DUPLICATE KEY UPDATE
                start_time = VALUES(start_time),
                end_time = VALUES(end_time),
                total_amount = VALUES(total_amount),
                status = VALUES(status)';

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'id' => $reservation->getId(),
            'user_id' => $reservation->getUserId(),
            'parking_id' => $reservation->getParkingId(),
            'start_time' => $reservation->getStartTime()->format('Y-m-d H:i:s'),
            'end_time' => $reservation->getEndTime()->format('Y-m-d H:i:s'),
            'total_amount' => $reservation->getTotalAmount(),
            'status' => $reservation->getStatus(),
            'created_at' => $reservation->getCreatedAt()->format('Y-m-d H:i:s')
        ]);
    }

    public function findById(string $id): ?Reservation
    {
        $pdo = $this->connection->getConnection();

        $stmt = $pdo->prepare('SELECT * FROM reservations WHERE id = :id');
        $stmt->execute(['id' => $id]);

        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }

        return $this->hydrateReservation($row);
    }

    public function findByUserId(string $userId): array
    {
        $pdo = $this->connection->getConnection();

        $stmt = $pdo->prepare('SELECT * FROM reservations WHERE user_id = :user_id ORDER BY start_time DESC');
        $stmt->execute(['user_id' => $userId]);

        return $this->fetchAllReservations($stmt);
    }

    public function findByParkingId(string $parkingId): array
    {
        $pdo = $this->connection->getConnection();

        $stmt = $pdo->prepare('SELECT * FROM reservations WHERE parking_id = :parking_id ORDER BY start_time DESC');
        $stmt->execute(['parking_id' => $parkingId]);

        return $this->fetchAllReservations($stmt);
    }

    public function findAll(): array
    {
        $pdo = $this->connection->getConnection();

        $stmt = $pdo->query('SELECT * FROM reservations ORDER BY start_time DESC');

        return $this->fetchAllReservations($stmt);
    }

    public function delete(Reservation $reservation): void
    {
        $pdo = $this->connection->getConnection();

        $stmt = $pdo->prepare('DELETE FROM reservations WHERE id = :id');
        $stmt->execute(['id' => $reservation->getId()]);
    }

    public function exists(string $id): bool
    {
        $pdo = $this->connection->getConnection();

        $stmt = $pdo->prepare('SELECT 1 FROM reservations WHERE id = :id');
        $stmt->execute(['id' => $id]);

        return $stmt->fetch() !== false;
    }

    public function count(): int
    {
        $pdo = $this->connection->getConnection();

        $stmt = $pdo->query('SELECT COUNT(*) as count FROM reservations');
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
        $stmt = $pdo->prepare("SELECT * FROM reservations WHERE id IN ($placeholders)");
        $stmt->execute($ids);

        return $this->fetchAllReservations($stmt);
    }

    public function findActiveReservations(): array
    {
        $pdo = $this->connection->getConnection();

        $stmt = $pdo->prepare("SELECT * FROM reservations WHERE status IN ('pending', 'confirmed') ORDER BY start_time");
        $stmt->execute();

        return $this->fetchAllReservations($stmt);
    }

    public function findActiveReservationsForParking(string $parkingId): array
    {
        $pdo = $this->connection->getConnection();

        $stmt = $pdo->prepare("SELECT * FROM reservations WHERE parking_id = :parking_id AND status IN ('pending', 'confirmed') ORDER BY start_time");
        $stmt->execute(['parking_id' => $parkingId]);

        return $this->fetchAllReservations($stmt);
    }

    public function findActiveReservationsForUser(string $userId): array
    {
        $pdo = $this->connection->getConnection();

        $stmt = $pdo->prepare("SELECT * FROM reservations WHERE user_id = :user_id AND status IN ('pending', 'confirmed') ORDER BY start_time");
        $stmt->execute(['user_id' => $userId]);

        return $this->fetchAllReservations($stmt);
    }

    public function findReservationsInTimeRange(
        \DateTimeInterface $startTime,
        \DateTimeInterface $endTime
    ): array {
        $pdo = $this->connection->getConnection();

        $stmt = $pdo->prepare('SELECT * FROM reservations WHERE start_time >= :start AND end_time <= :end ORDER BY start_time');
        $stmt->execute([
            'start' => $startTime->format('Y-m-d H:i:s'),
            'end' => $endTime->format('Y-m-d H:i:s')
        ]);

        return $this->fetchAllReservations($stmt);
    }

    public function findConflictingReservations(
        string $parkingId,
        \DateTimeInterface $startTime,
        \DateTimeInterface $endTime
    ): array {
        $pdo = $this->connection->getConnection();

        $stmt = $pdo->prepare("SELECT * FROM reservations
                WHERE parking_id = :parking_id
                AND status IN ('pending', 'confirmed')
                AND start_time < :end
                AND end_time > :start
                ORDER BY start_time");
        $stmt->execute([
            'parking_id' => $parkingId,
            'start' => $startTime->format('Y-m-d H:i:s'),
            'end' => $endTime->format('Y-m-d H:i:s')
        ]);

        return $this->fetchAllReservations($stmt);
    }

    public function findByStatus(string $status): array
    {
        $pdo = $this->connection->getConnection();

        $stmt = $pdo->prepare('SELECT * FROM reservations WHERE status = :status ORDER BY start_time DESC');
        $stmt->execute(['status' => $status]);

        return $this->fetchAllReservations($stmt);
    }

    public function findByDateRange(
        \DateTimeInterface $from,
        \DateTimeInterface $to
    ): array {
        return $this->findReservationsInTimeRange($from, $to);
    }

    public function findByParkingIds(array $parkingIds): array
    {
        if (empty($parkingIds)) {
            return [];
        }

        $pdo = $this->connection->getConnection();

        $placeholders = implode(',', array_fill(0, count($parkingIds), '?'));

        $stmt = $pdo->prepare("SELECT * FROM reservations
                WHERE parking_id IN ($placeholders)
                ORDER BY start_time DESC");

        $stmt->execute($parkingIds);

        return $this->fetchAllReservations($stmt);
    }

    public function findExpiredReservations(): array
    {
        $pdo = $this->connection->getConnection();

        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        $stmt = $pdo->prepare("SELECT * FROM reservations WHERE status = 'confirmed' AND end_time < :now ORDER BY end_time");
        $stmt->execute(['now' => $now]);

        return $this->fetchAllReservations($stmt);
    }

    public function getTotalRevenueForParking(
        string $parkingId,
        ?\DateTimeInterface $from = null,
        ?\DateTimeInterface $to = null
    ): float {
        $pdo = $this->connection->getConnection();

        $sql = "SELECT COALESCE(SUM(total_amount), 0) as revenue FROM reservations
                WHERE parking_id = :parking_id AND status IN ('confirmed', 'completed')";
        $params = ['parking_id' => $parkingId];

        if ($from !== null) {
            $sql .= ' AND start_time >= :from';
            $params['from'] = $from->format('Y-m-d H:i:s');
        }

        if ($to !== null) {
            $sql .= ' AND start_time <= :to';
            $params['to'] = $to->format('Y-m-d H:i:s');
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        $result = $stmt->fetch();
        return (float)$result['revenue'];
    }

    private function fetchAllReservations(\PDOStatement $stmt): array
    {
        $reservations = [];
        while ($row = $stmt->fetch()) {
            $reservations[] = $this->hydrateReservation($row);
        }
        return $reservations;
    }

    private function hydrateReservation(array $row): Reservation
    {
        return new Reservation(
            $row['id'],
            $row['user_id'],
            $row['parking_id'],
            new \DateTimeImmutable($row['start_time']),
            new \DateTimeImmutable($row['end_time']),
            (float)$row['total_amount'],
            $row['status'],
            new \DateTimeImmutable($row['created_at'])
        );
    }
}
