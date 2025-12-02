<?php

declare(strict_types=1);

namespace ParkingSystem\Infrastructure\Repository\MySQL;

use ParkingSystem\Domain\Entity\Subscription;
use ParkingSystem\Domain\Repository\SubscriptionRepositoryInterface;

/**
 * MySQLSubscriptionRepository
 * Infrastructure Layer - MySQL implementation of SubscriptionRepositoryInterface
 */
class MySQLSubscriptionRepository implements SubscriptionRepositoryInterface
{
    public function __construct(
        private MySQLConnectionInterface $connection
    ) {
    }

    public function save(Subscription $subscription): void
    {
        $pdo = $this->connection->getConnection();

        $sql = 'INSERT INTO subscriptions (id, user_id, parking_id, weekly_time_slots, duration_months, start_date, end_date, monthly_amount, status, created_at)
                VALUES (:id, :user_id, :parking_id, :weekly_time_slots, :duration_months, :start_date, :end_date, :monthly_amount, :status, :created_at)
                ON DUPLICATE KEY UPDATE
                weekly_time_slots = VALUES(weekly_time_slots),
                status = VALUES(status)';

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'id' => $subscription->getId(),
            'user_id' => $subscription->getUserId(),
            'parking_id' => $subscription->getParkingId(),
            'weekly_time_slots' => json_encode($subscription->getWeeklyTimeSlots()),
            'duration_months' => $subscription->getDurationMonths(),
            'start_date' => $subscription->getStartDate()->format('Y-m-d'),
            'end_date' => $subscription->getEndDate()->format('Y-m-d'),
            'monthly_amount' => $subscription->getMonthlyAmount(),
            'status' => $subscription->getStatus(),
            'created_at' => $subscription->getCreatedAt()->format('Y-m-d H:i:s')
        ]);
    }

    public function findById(string $id): ?Subscription
    {
        $pdo = $this->connection->getConnection();

        $stmt = $pdo->prepare('SELECT * FROM subscriptions WHERE id = :id');
        $stmt->execute(['id' => $id]);

        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }

        return $this->hydrateSubscription($row);
    }

    public function findAll(): array
    {
        $pdo = $this->connection->getConnection();

        $stmt = $pdo->query('SELECT * FROM subscriptions ORDER BY created_at DESC');

        return $this->fetchAllSubscriptions($stmt);
    }

    public function findByUserId(string $userId): array
    {
        $pdo = $this->connection->getConnection();

        $stmt = $pdo->prepare('SELECT * FROM subscriptions WHERE user_id = :user_id ORDER BY start_date DESC');
        $stmt->execute(['user_id' => $userId]);

        return $this->fetchAllSubscriptions($stmt);
    }

    public function findByParkingId(string $parkingId): array
    {
        $pdo = $this->connection->getConnection();

        $stmt = $pdo->prepare('SELECT * FROM subscriptions WHERE parking_id = :parking_id ORDER BY start_date DESC');
        $stmt->execute(['parking_id' => $parkingId]);

        return $this->fetchAllSubscriptions($stmt);
    }

    public function delete(Subscription $subscription): void
    {
        $pdo = $this->connection->getConnection();

        $stmt = $pdo->prepare('DELETE FROM subscriptions WHERE id = :id');
        $stmt->execute(['id' => $subscription->getId()]);
    }

    public function exists(string $id): bool
    {
        $pdo = $this->connection->getConnection();

        $stmt = $pdo->prepare('SELECT 1 FROM subscriptions WHERE id = :id');
        $stmt->execute(['id' => $id]);

        return $stmt->fetch() !== false;
    }

    public function count(): int
    {
        $pdo = $this->connection->getConnection();

        $stmt = $pdo->query('SELECT COUNT(*) as count FROM subscriptions');
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
        $stmt = $pdo->prepare("SELECT * FROM subscriptions WHERE id IN ($placeholders)");
        $stmt->execute($ids);

        return $this->fetchAllSubscriptions($stmt);
    }

    public function findActiveSubscriptions(): array
    {
        $pdo = $this->connection->getConnection();

        $stmt = $pdo->prepare("SELECT * FROM subscriptions WHERE status = 'active' ORDER BY start_date");
        $stmt->execute();

        return $this->fetchAllSubscriptions($stmt);
    }

    public function findActiveSubscriptionsForParking(string $parkingId): array
    {
        $pdo = $this->connection->getConnection();

        $stmt = $pdo->prepare("SELECT * FROM subscriptions WHERE parking_id = :parking_id AND status = 'active' ORDER BY start_date");
        $stmt->execute(['parking_id' => $parkingId]);

        return $this->fetchAllSubscriptions($stmt);
    }

    public function findActiveSubscriptionsForUser(string $userId): array
    {
        $pdo = $this->connection->getConnection();

        $stmt = $pdo->prepare("SELECT * FROM subscriptions WHERE user_id = :user_id AND status = 'active' ORDER BY start_date");
        $stmt->execute(['user_id' => $userId]);

        return $this->fetchAllSubscriptions($stmt);
    }

    public function findByStatus(string $status): array
    {
        $pdo = $this->connection->getConnection();

        $stmt = $pdo->prepare('SELECT * FROM subscriptions WHERE status = :status ORDER BY start_date DESC');
        $stmt->execute(['status' => $status]);

        return $this->fetchAllSubscriptions($stmt);
    }

    public function findExpiringSubscriptions(int $daysBeforeExpiry = 7): array
    {
        $pdo = $this->connection->getConnection();

        $expiryDate = (new \DateTimeImmutable())->add(new \DateInterval('P' . $daysBeforeExpiry . 'D'));

        $stmt = $pdo->prepare("SELECT * FROM subscriptions WHERE status = 'active' AND end_date <= :expiry_date ORDER BY end_date");
        $stmt->execute(['expiry_date' => $expiryDate->format('Y-m-d')]);

        return $this->fetchAllSubscriptions($stmt);
    }

    public function findExpiredSubscriptions(): array
    {
        $pdo = $this->connection->getConnection();

        $today = (new \DateTimeImmutable())->format('Y-m-d');

        $stmt = $pdo->prepare("SELECT * FROM subscriptions WHERE status = 'active' AND end_date < :today ORDER BY end_date");
        $stmt->execute(['today' => $today]);

        return $this->fetchAllSubscriptions($stmt);
    }

    public function findConflictingSubscriptions(
        string $parkingId,
        array $weeklyTimeSlots
    ): array {
        // This requires application-level checking due to JSON field
        $activeSubscriptions = $this->findActiveSubscriptionsForParking($parkingId);

        $conflicting = [];
        foreach ($activeSubscriptions as $subscription) {
            if ($this->hasSlotOverlap($subscription->getWeeklyTimeSlots(), $weeklyTimeSlots)) {
                $conflicting[] = $subscription;
            }
        }

        return $conflicting;
    }

    public function findSubscriptionsActiveAt(
        string $parkingId,
        \DateTimeInterface $dateTime
    ): array {
        $pdo = $this->connection->getConnection();

        $date = $dateTime->format('Y-m-d');

        $stmt = $pdo->prepare("SELECT * FROM subscriptions WHERE parking_id = :parking_id AND status = 'active' AND start_date <= :date AND end_date >= :date2");
        $stmt->execute([
            'parking_id' => $parkingId,
            'date' => $date,
            'date2' => $date
        ]);

        return $this->fetchAllSubscriptions($stmt);
    }

    public function getTotalRevenueForParking(
        string $parkingId,
        ?\DateTimeInterface $from = null,
        ?\DateTimeInterface $to = null
    ): float {
        $pdo = $this->connection->getConnection();

        $sql = "SELECT COALESCE(SUM(monthly_amount * duration_months), 0) as revenue FROM subscriptions
                WHERE parking_id = :parking_id";
        $params = ['parking_id' => $parkingId];

        if ($from !== null) {
            $sql .= ' AND start_date >= :from';
            $params['from'] = $from->format('Y-m-d');
        }

        if ($to !== null) {
            $sql .= ' AND start_date <= :to';
            $params['to'] = $to->format('Y-m-d');
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        $result = $stmt->fetch();
        return (float)$result['revenue'];
    }

    public function getAverageSubscriptionDuration(): float
    {
        $pdo = $this->connection->getConnection();

        $stmt = $pdo->query('SELECT AVG(duration_months) as avg_duration FROM subscriptions');
        $result = $stmt->fetch();

        return (float)($result['avg_duration'] ?? 0);
    }

    private function fetchAllSubscriptions(\PDOStatement $stmt): array
    {
        $subscriptions = [];
        while ($row = $stmt->fetch()) {
            $subscriptions[] = $this->hydrateSubscription($row);
        }
        return $subscriptions;
    }

    private function hydrateSubscription(array $row): Subscription
    {
        $weeklyTimeSlots = json_decode($row['weekly_time_slots'], true);
        // Convert string keys to integers
        $weeklyTimeSlotsFixed = [];
        foreach ($weeklyTimeSlots as $day => $slots) {
            $weeklyTimeSlotsFixed[(int)$day] = $slots;
        }

        return new Subscription(
            $row['id'],
            $row['user_id'],
            $row['parking_id'],
            $weeklyTimeSlotsFixed,
            (int)$row['duration_months'],
            new \DateTimeImmutable($row['start_date']),
            (float)$row['monthly_amount'],
            $row['status'],
            new \DateTimeImmutable($row['created_at'])
        );
    }

    private function hasSlotOverlap(array $slots1, array $slots2): bool
    {
        foreach ($slots1 as $day => $daySlots1) {
            if (!isset($slots2[$day])) {
                continue;
            }

            foreach ($daySlots1 as $slot1) {
                foreach ($slots2[$day] as $slot2) {
                    if ($slot1['start'] < $slot2['end'] && $slot1['end'] > $slot2['start']) {
                        return true;
                    }
                }
            }
        }

        return false;
    }
}
