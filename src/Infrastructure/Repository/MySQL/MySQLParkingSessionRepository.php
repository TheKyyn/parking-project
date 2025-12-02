<?php

declare(strict_types=1);

namespace ParkingSystem\Infrastructure\Repository\MySQL;

use ParkingSystem\Domain\Entity\ParkingSession;
use ParkingSystem\Domain\Repository\ParkingSessionRepositoryInterface;

/**
 * MySQLParkingSessionRepository
 * Infrastructure Layer - MySQL implementation of ParkingSessionRepositoryInterface
 */
class MySQLParkingSessionRepository implements ParkingSessionRepositoryInterface
{
    public function __construct(
        private MySQLConnectionInterface $connection
    ) {
    }

    public function save(ParkingSession $session): void
    {
        $pdo = $this->connection->getConnection();

        $sql = 'INSERT INTO parking_sessions (id, user_id, parking_id, reservation_id, start_time, end_time, total_amount, status, created_at)
                VALUES (:id, :user_id, :parking_id, :reservation_id, :start_time, :end_time, :total_amount, :status, :created_at)
                ON DUPLICATE KEY UPDATE
                end_time = VALUES(end_time),
                total_amount = VALUES(total_amount),
                status = VALUES(status)';

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'id' => $session->getId(),
            'user_id' => $session->getUserId(),
            'parking_id' => $session->getParkingId(),
            'reservation_id' => $session->getReservationId(),
            'start_time' => $session->getStartTime()->format('Y-m-d H:i:s'),
            'end_time' => $session->getEndTime()?->format('Y-m-d H:i:s'),
            'total_amount' => $session->getTotalAmount(),
            'status' => $session->getStatus(),
            'created_at' => $session->getCreatedAt()->format('Y-m-d H:i:s')
        ]);
    }

    public function findById(string $id): ?ParkingSession
    {
        $pdo = $this->connection->getConnection();

        $stmt = $pdo->prepare('SELECT * FROM parking_sessions WHERE id = :id');
        $stmt->execute(['id' => $id]);

        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }

        return $this->hydrateSession($row);
    }

    public function findAll(): array
    {
        $pdo = $this->connection->getConnection();

        $stmt = $pdo->query('SELECT * FROM parking_sessions ORDER BY start_time DESC');

        return $this->fetchAllSessions($stmt);
    }

    public function findByUserId(string $userId): array
    {
        $pdo = $this->connection->getConnection();

        $stmt = $pdo->prepare('SELECT * FROM parking_sessions WHERE user_id = :user_id ORDER BY start_time DESC');
        $stmt->execute(['user_id' => $userId]);

        return $this->fetchAllSessions($stmt);
    }

    public function findByParkingId(string $parkingId): array
    {
        $pdo = $this->connection->getConnection();

        $stmt = $pdo->prepare('SELECT * FROM parking_sessions WHERE parking_id = :parking_id ORDER BY start_time DESC');
        $stmt->execute(['parking_id' => $parkingId]);

        return $this->fetchAllSessions($stmt);
    }

    public function delete(ParkingSession $session): void
    {
        $pdo = $this->connection->getConnection();

        $stmt = $pdo->prepare('DELETE FROM parking_sessions WHERE id = :id');
        $stmt->execute(['id' => $session->getId()]);
    }

    public function exists(string $id): bool
    {
        $pdo = $this->connection->getConnection();

        $stmt = $pdo->prepare('SELECT 1 FROM parking_sessions WHERE id = :id');
        $stmt->execute(['id' => $id]);

        return $stmt->fetch() !== false;
    }

    public function count(): int
    {
        $pdo = $this->connection->getConnection();

        $stmt = $pdo->query('SELECT COUNT(*) as count FROM parking_sessions');
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
        $stmt = $pdo->prepare("SELECT * FROM parking_sessions WHERE id IN ($placeholders)");
        $stmt->execute($ids);

        return $this->fetchAllSessions($stmt);
    }

    public function findActiveSessions(): array
    {
        $pdo = $this->connection->getConnection();

        $stmt = $pdo->prepare("SELECT * FROM parking_sessions WHERE status = 'active' ORDER BY start_time");
        $stmt->execute();

        return $this->fetchAllSessions($stmt);
    }

    public function findActiveSessionsForParking(string $parkingId): array
    {
        $pdo = $this->connection->getConnection();

        $stmt = $pdo->prepare("SELECT * FROM parking_sessions WHERE parking_id = :parking_id AND status = 'active' ORDER BY start_time");
        $stmt->execute(['parking_id' => $parkingId]);

        return $this->fetchAllSessions($stmt);
    }

    public function findActiveSessionsForUser(string $userId): array
    {
        $pdo = $this->connection->getConnection();

        $stmt = $pdo->prepare("SELECT * FROM parking_sessions WHERE user_id = :user_id AND status = 'active' ORDER BY start_time");
        $stmt->execute(['user_id' => $userId]);

        return $this->fetchAllSessions($stmt);
    }

    public function findActiveSessionByUserAndParking(
        string $userId,
        string $parkingId
    ): ?ParkingSession {
        $pdo = $this->connection->getConnection();

        $stmt = $pdo->prepare("SELECT * FROM parking_sessions WHERE user_id = :user_id AND parking_id = :parking_id AND status = 'active' LIMIT 1");
        $stmt->execute(['user_id' => $userId, 'parking_id' => $parkingId]);

        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }

        return $this->hydrateSession($row);
    }

    public function findByStatus(string $status): array
    {
        $pdo = $this->connection->getConnection();

        $stmt = $pdo->prepare('SELECT * FROM parking_sessions WHERE status = :status ORDER BY start_time DESC');
        $stmt->execute(['status' => $status]);

        return $this->fetchAllSessions($stmt);
    }

    public function findByDateRange(
        \DateTimeInterface $from,
        \DateTimeInterface $to
    ): array {
        $pdo = $this->connection->getConnection();

        $stmt = $pdo->prepare('SELECT * FROM parking_sessions WHERE start_time >= :from AND start_time <= :to ORDER BY start_time');
        $stmt->execute([
            'from' => $from->format('Y-m-d H:i:s'),
            'to' => $to->format('Y-m-d H:i:s')
        ]);

        return $this->fetchAllSessions($stmt);
    }

    public function findOverstayedSessions(): array
    {
        $pdo = $this->connection->getConnection();

        $stmt = $pdo->prepare("SELECT * FROM parking_sessions WHERE status = 'overstayed' ORDER BY start_time");
        $stmt->execute();

        return $this->fetchAllSessions($stmt);
    }

    public function findSessionsWithoutReservation(): array
    {
        $pdo = $this->connection->getConnection();

        $stmt = $pdo->prepare('SELECT * FROM parking_sessions WHERE reservation_id IS NULL ORDER BY start_time DESC');
        $stmt->execute();

        return $this->fetchAllSessions($stmt);
    }

    public function findSessionsByReservationId(string $reservationId): array
    {
        $pdo = $this->connection->getConnection();

        $stmt = $pdo->prepare('SELECT * FROM parking_sessions WHERE reservation_id = :reservation_id ORDER BY start_time');
        $stmt->execute(['reservation_id' => $reservationId]);

        return $this->fetchAllSessions($stmt);
    }

    public function getTotalRevenueForParking(
        string $parkingId,
        ?\DateTimeInterface $from = null,
        ?\DateTimeInterface $to = null
    ): float {
        $pdo = $this->connection->getConnection();

        $sql = "SELECT COALESCE(SUM(total_amount), 0) as revenue FROM parking_sessions
                WHERE parking_id = :parking_id AND status IN ('completed', 'overstayed')";
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

    public function getAverageSessionDuration(
        string $parkingId,
        ?\DateTimeInterface $from = null,
        ?\DateTimeInterface $to = null
    ): float {
        $pdo = $this->connection->getConnection();

        $sql = "SELECT AVG(TIMESTAMPDIFF(MINUTE, start_time, end_time)) as avg_duration
                FROM parking_sessions
                WHERE parking_id = :parking_id AND end_time IS NOT NULL";
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
        return (float)($result['avg_duration'] ?? 0);
    }

    private function fetchAllSessions(\PDOStatement $stmt): array
    {
        $sessions = [];
        while ($row = $stmt->fetch()) {
            $sessions[] = $this->hydrateSession($row);
        }
        return $sessions;
    }

    private function hydrateSession(array $row): ParkingSession
    {
        $session = new ParkingSession(
            $row['id'],
            $row['user_id'],
            $row['parking_id'],
            new \DateTimeImmutable($row['start_time']),
            $row['reservation_id'],
            new \DateTimeImmutable($row['created_at'])
        );

        // Restore state for completed sessions
        if ($row['end_time'] !== null && $row['total_amount'] !== null) {
            if ($row['status'] === 'overstayed') {
                $session->markAsOverstayed();
            }
            $session->endSession(
                new \DateTimeImmutable($row['end_time']),
                (float)$row['total_amount']
            );
        }

        return $session;
    }
}
