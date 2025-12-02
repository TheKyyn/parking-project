<?php

declare(strict_types=1);

namespace ParkingSystem\Infrastructure\Repository\File;

use ParkingSystem\Domain\Entity\Subscription;
use ParkingSystem\Domain\Repository\SubscriptionRepositoryInterface;

/**
 * FileSubscriptionRepository
 * Infrastructure Layer - File-based implementation of SubscriptionRepositoryInterface
 */
class FileSubscriptionRepository extends AbstractFileRepository implements SubscriptionRepositoryInterface
{
    protected function getFileName(): string
    {
        return 'subscriptions.json';
    }

    public function save(Subscription $subscription): void
    {
        $data = $this->loadData();
        $data[$subscription->getId()] = $this->serializeSubscription($subscription);
        $this->saveData($data);
    }

    public function findById(string $id): ?Subscription
    {
        $data = $this->loadData();

        if (!isset($data[$id])) {
            return null;
        }

        return $this->hydrateSubscription($data[$id]);
    }

    public function findAll(): array
    {
        $data = $this->loadData();
        $results = [];

        foreach ($data as $item) {
            $results[] = $this->hydrateSubscription($item);
        }

        return $this->sortByStartDateDesc($results);
    }

    public function findByUserId(string $userId): array
    {
        $data = $this->loadData();
        $results = [];

        foreach ($data as $item) {
            if ($item['user_id'] === $userId) {
                $results[] = $this->hydrateSubscription($item);
            }
        }

        return $this->sortByStartDateDesc($results);
    }

    public function findByParkingId(string $parkingId): array
    {
        $data = $this->loadData();
        $results = [];

        foreach ($data as $item) {
            if ($item['parking_id'] === $parkingId) {
                $results[] = $this->hydrateSubscription($item);
            }
        }

        return $this->sortByStartDateDesc($results);
    }

    public function delete(Subscription $subscription): void
    {
        $data = $this->loadData();
        unset($data[$subscription->getId()]);
        $this->saveData($data);
    }

    public function exists(string $id): bool
    {
        $data = $this->loadData();
        return isset($data[$id]);
    }

    public function count(): int
    {
        return count($this->loadData());
    }

    public function findByIds(array $ids): array
    {
        $data = $this->loadData();
        $results = [];

        foreach ($ids as $id) {
            if (isset($data[$id])) {
                $results[] = $this->hydrateSubscription($data[$id]);
            }
        }

        return $results;
    }

    public function findActiveSubscriptions(): array
    {
        $data = $this->loadData();
        $results = [];

        foreach ($data as $item) {
            if ($item['status'] === 'active') {
                $results[] = $this->hydrateSubscription($item);
            }
        }

        return $results;
    }

    public function findActiveSubscriptionsForParking(string $parkingId): array
    {
        $data = $this->loadData();
        $results = [];

        foreach ($data as $item) {
            if ($item['parking_id'] === $parkingId && $item['status'] === 'active') {
                $results[] = $this->hydrateSubscription($item);
            }
        }

        return $results;
    }

    public function findActiveSubscriptionsForUser(string $userId): array
    {
        $data = $this->loadData();
        $results = [];

        foreach ($data as $item) {
            if ($item['user_id'] === $userId && $item['status'] === 'active') {
                $results[] = $this->hydrateSubscription($item);
            }
        }

        return $results;
    }

    public function findByStatus(string $status): array
    {
        $data = $this->loadData();
        $results = [];

        foreach ($data as $item) {
            if ($item['status'] === $status) {
                $results[] = $this->hydrateSubscription($item);
            }
        }

        return $this->sortByStartDateDesc($results);
    }

    public function findExpiringSubscriptions(int $daysBeforeExpiry = 7): array
    {
        $data = $this->loadData();
        $results = [];

        $expiryDate = (new \DateTimeImmutable())
            ->add(new \DateInterval('P' . $daysBeforeExpiry . 'D'))
            ->format('Y-m-d');

        foreach ($data as $item) {
            if ($item['status'] === 'active' && $item['end_date'] <= $expiryDate) {
                $results[] = $this->hydrateSubscription($item);
            }
        }

        return $results;
    }

    public function findExpiredSubscriptions(): array
    {
        $data = $this->loadData();
        $results = [];

        $today = (new \DateTimeImmutable())->format('Y-m-d');

        foreach ($data as $item) {
            if ($item['status'] === 'active' && $item['end_date'] < $today) {
                $results[] = $this->hydrateSubscription($item);
            }
        }

        return $results;
    }

    public function findConflictingSubscriptions(
        string $parkingId,
        array $weeklyTimeSlots
    ): array {
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
        $data = $this->loadData();
        $results = [];

        $date = $dateTime->format('Y-m-d');

        foreach ($data as $item) {
            if ($item['parking_id'] !== $parkingId) {
                continue;
            }

            if ($item['status'] !== 'active') {
                continue;
            }

            if ($item['start_date'] <= $date && $item['end_date'] >= $date) {
                $results[] = $this->hydrateSubscription($item);
            }
        }

        return $results;
    }

    public function getTotalRevenueForParking(
        string $parkingId,
        ?\DateTimeInterface $from = null,
        ?\DateTimeInterface $to = null
    ): float {
        $data = $this->loadData();
        $revenue = 0.0;

        foreach ($data as $item) {
            if ($item['parking_id'] !== $parkingId) {
                continue;
            }

            $startDate = $item['start_date'];

            if ($from !== null && $startDate < $from->format('Y-m-d')) {
                continue;
            }

            if ($to !== null && $startDate > $to->format('Y-m-d')) {
                continue;
            }

            $revenue += $item['monthly_amount'] * $item['duration_months'];
        }

        return $revenue;
    }

    public function getAverageSubscriptionDuration(): float
    {
        $data = $this->loadData();

        if (empty($data)) {
            return 0.0;
        }

        $totalDuration = 0;
        foreach ($data as $item) {
            $totalDuration += $item['duration_months'];
        }

        return $totalDuration / count($data);
    }

    private function serializeSubscription(Subscription $subscription): array
    {
        return [
            'id' => $subscription->getId(),
            'user_id' => $subscription->getUserId(),
            'parking_id' => $subscription->getParkingId(),
            'weekly_time_slots' => $subscription->getWeeklyTimeSlots(),
            'duration_months' => $subscription->getDurationMonths(),
            'start_date' => $subscription->getStartDate()->format('Y-m-d'),
            'end_date' => $subscription->getEndDate()->format('Y-m-d'),
            'monthly_amount' => $subscription->getMonthlyAmount(),
            'status' => $subscription->getStatus(),
            'created_at' => $subscription->getCreatedAt()->format(\DateTimeInterface::ATOM)
        ];
    }

    private function hydrateSubscription(array $data): Subscription
    {
        // Ensure integer keys for weekly time slots
        $weeklyTimeSlots = [];
        foreach ($data['weekly_time_slots'] as $day => $slots) {
            $weeklyTimeSlots[(int)$day] = $slots;
        }

        return new Subscription(
            $data['id'],
            $data['user_id'],
            $data['parking_id'],
            $weeklyTimeSlots,
            (int)$data['duration_months'],
            new \DateTimeImmutable($data['start_date']),
            (float)$data['monthly_amount'],
            $data['status'],
            new \DateTimeImmutable($data['created_at'])
        );
    }

    private function sortByStartDateDesc(array $subscriptions): array
    {
        usort($subscriptions, function (Subscription $a, Subscription $b) {
            return $b->getStartDate() <=> $a->getStartDate();
        });

        return $subscriptions;
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
