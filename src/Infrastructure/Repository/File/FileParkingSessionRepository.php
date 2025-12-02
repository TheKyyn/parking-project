<?php

declare(strict_types=1);

namespace ParkingSystem\Infrastructure\Repository\File;

use ParkingSystem\Domain\Entity\ParkingSession;
use ParkingSystem\Domain\Repository\ParkingSessionRepositoryInterface;

/**
 * FileParkingSessionRepository
 * Infrastructure Layer - File-based implementation of ParkingSessionRepositoryInterface
 */
class FileParkingSessionRepository extends AbstractFileRepository implements ParkingSessionRepositoryInterface
{
    protected function getFileName(): string
    {
        return 'parking_sessions.json';
    }

    public function save(ParkingSession $session): void
    {
        $data = $this->loadData();
        $data[$session->getId()] = $this->serializeSession($session);
        $this->saveData($data);
    }

    public function findById(string $id): ?ParkingSession
    {
        $data = $this->loadData();

        if (!isset($data[$id])) {
            return null;
        }

        return $this->hydrateSession($data[$id]);
    }

    public function findAll(): array
    {
        $data = $this->loadData();
        $results = [];

        foreach ($data as $item) {
            $results[] = $this->hydrateSession($item);
        }

        return $this->sortByStartTimeDesc($results);
    }

    public function findByUserId(string $userId): array
    {
        $data = $this->loadData();
        $results = [];

        foreach ($data as $item) {
            if ($item['user_id'] === $userId) {
                $results[] = $this->hydrateSession($item);
            }
        }

        return $this->sortByStartTimeDesc($results);
    }

    public function findByParkingId(string $parkingId): array
    {
        $data = $this->loadData();
        $results = [];

        foreach ($data as $item) {
            if ($item['parking_id'] === $parkingId) {
                $results[] = $this->hydrateSession($item);
            }
        }

        return $this->sortByStartTimeDesc($results);
    }

    public function delete(ParkingSession $session): void
    {
        $data = $this->loadData();
        unset($data[$session->getId()]);
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
                $results[] = $this->hydrateSession($data[$id]);
            }
        }

        return $results;
    }

    public function findActiveSessions(): array
    {
        $data = $this->loadData();
        $results = [];

        foreach ($data as $item) {
            if ($item['status'] === 'active') {
                $results[] = $this->hydrateSession($item);
            }
        }

        return $results;
    }

    public function findActiveSessionsForParking(string $parkingId): array
    {
        $data = $this->loadData();
        $results = [];

        foreach ($data as $item) {
            if ($item['parking_id'] === $parkingId && $item['status'] === 'active') {
                $results[] = $this->hydrateSession($item);
            }
        }

        return $results;
    }

    public function findActiveSessionsForUser(string $userId): array
    {
        $data = $this->loadData();
        $results = [];

        foreach ($data as $item) {
            if ($item['user_id'] === $userId && $item['status'] === 'active') {
                $results[] = $this->hydrateSession($item);
            }
        }

        return $results;
    }

    public function findActiveSessionByUserAndParking(
        string $userId,
        string $parkingId
    ): ?ParkingSession {
        $data = $this->loadData();

        foreach ($data as $item) {
            if ($item['user_id'] === $userId &&
                $item['parking_id'] === $parkingId &&
                $item['status'] === 'active') {
                return $this->hydrateSession($item);
            }
        }

        return null;
    }

    public function findByStatus(string $status): array
    {
        $data = $this->loadData();
        $results = [];

        foreach ($data as $item) {
            if ($item['status'] === $status) {
                $results[] = $this->hydrateSession($item);
            }
        }

        return $this->sortByStartTimeDesc($results);
    }

    public function findByDateRange(
        \DateTimeInterface $from,
        \DateTimeInterface $to
    ): array {
        $data = $this->loadData();
        $results = [];

        $fromTs = $from->getTimestamp();
        $toTs = $to->getTimestamp();

        foreach ($data as $item) {
            $startTs = strtotime($item['start_time']);
            if ($startTs >= $fromTs && $startTs <= $toTs) {
                $results[] = $this->hydrateSession($item);
            }
        }

        return $results;
    }

    public function findOverstayedSessions(): array
    {
        return $this->findByStatus('overstayed');
    }

    public function findSessionsWithoutReservation(): array
    {
        $data = $this->loadData();
        $results = [];

        foreach ($data as $item) {
            if ($item['reservation_id'] === null) {
                $results[] = $this->hydrateSession($item);
            }
        }

        return $this->sortByStartTimeDesc($results);
    }

    public function findSessionsByReservationId(string $reservationId): array
    {
        $data = $this->loadData();
        $results = [];

        foreach ($data as $item) {
            if ($item['reservation_id'] === $reservationId) {
                $results[] = $this->hydrateSession($item);
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

            if (!in_array($item['status'], ['completed', 'overstayed'], true)) {
                continue;
            }

            if ($item['total_amount'] === null) {
                continue;
            }

            $itemStart = strtotime($item['start_time']);

            if ($from !== null && $itemStart < $from->getTimestamp()) {
                continue;
            }

            if ($to !== null && $itemStart > $to->getTimestamp()) {
                continue;
            }

            $revenue += $item['total_amount'];
        }

        return $revenue;
    }

    public function getAverageSessionDuration(
        string $parkingId,
        ?\DateTimeInterface $from = null,
        ?\DateTimeInterface $to = null
    ): float {
        $data = $this->loadData();
        $totalDuration = 0;
        $count = 0;

        foreach ($data as $item) {
            if ($item['parking_id'] !== $parkingId) {
                continue;
            }

            if ($item['end_time'] === null) {
                continue;
            }

            $itemStart = strtotime($item['start_time']);

            if ($from !== null && $itemStart < $from->getTimestamp()) {
                continue;
            }

            if ($to !== null && $itemStart > $to->getTimestamp()) {
                continue;
            }

            $duration = strtotime($item['end_time']) - $itemStart;
            $totalDuration += $duration / 60; // Convert to minutes
            $count++;
        }

        return $count > 0 ? $totalDuration / $count : 0.0;
    }

    private function serializeSession(ParkingSession $session): array
    {
        return [
            'id' => $session->getId(),
            'user_id' => $session->getUserId(),
            'parking_id' => $session->getParkingId(),
            'reservation_id' => $session->getReservationId(),
            'start_time' => $session->getStartTime()->format(\DateTimeInterface::ATOM),
            'end_time' => $session->getEndTime()?->format(\DateTimeInterface::ATOM),
            'total_amount' => $session->getTotalAmount(),
            'status' => $session->getStatus(),
            'created_at' => $session->getCreatedAt()->format(\DateTimeInterface::ATOM)
        ];
    }

    private function hydrateSession(array $data): ParkingSession
    {
        $session = new ParkingSession(
            $data['id'],
            $data['user_id'],
            $data['parking_id'],
            new \DateTimeImmutable($data['start_time']),
            $data['reservation_id'],
            new \DateTimeImmutable($data['created_at'])
        );

        // Restore state for completed sessions
        if ($data['end_time'] !== null && $data['total_amount'] !== null) {
            if ($data['status'] === 'overstayed') {
                $session->markAsOverstayed();
            }
            $session->endSession(
                new \DateTimeImmutable($data['end_time']),
                (float)$data['total_amount']
            );
        }

        return $session;
    }

    private function sortByStartTimeDesc(array $sessions): array
    {
        usort($sessions, function (ParkingSession $a, ParkingSession $b) {
            return $b->getStartTime() <=> $a->getStartTime();
        });

        return $sessions;
    }
}
