<?php

declare(strict_types=1);

namespace ParkingSystem\Infrastructure\Repository\File;

use ParkingSystem\Domain\Entity\Reservation;
use ParkingSystem\Domain\Repository\ReservationRepositoryInterface;

/**
 * FileReservationRepository
 * Infrastructure Layer - File-based implementation of ReservationRepositoryInterface
 */
class FileReservationRepository extends AbstractFileRepository implements ReservationRepositoryInterface
{
    protected function getFileName(): string
    {
        return 'reservations.json';
    }

    public function save(Reservation $reservation): void
    {
        $data = $this->loadData();
        $data[$reservation->getId()] = $this->serializeReservation($reservation);
        $this->saveData($data);
    }

    public function findById(string $id): ?Reservation
    {
        $data = $this->loadData();

        if (!isset($data[$id])) {
            return null;
        }

        return $this->hydrateReservation($data[$id]);
    }

    public function findByUserId(string $userId): array
    {
        $data = $this->loadData();
        $results = [];

        foreach ($data as $item) {
            if ($item['user_id'] === $userId) {
                $results[] = $this->hydrateReservation($item);
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
                $results[] = $this->hydrateReservation($item);
            }
        }

        return $this->sortByStartTimeDesc($results);
    }

    public function findAll(): array
    {
        $data = $this->loadData();
        $results = [];

        foreach ($data as $item) {
            $results[] = $this->hydrateReservation($item);
        }

        return $this->sortByStartTimeDesc($results);
    }

    public function delete(Reservation $reservation): void
    {
        $data = $this->loadData();
        unset($data[$reservation->getId()]);
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
                $results[] = $this->hydrateReservation($data[$id]);
            }
        }

        return $results;
    }

    public function findActiveReservations(): array
    {
        $data = $this->loadData();
        $results = [];

        foreach ($data as $item) {
            if (in_array($item['status'], ['pending', 'confirmed'], true)) {
                $results[] = $this->hydrateReservation($item);
            }
        }

        return $results;
    }

    public function findActiveReservationsForParking(string $parkingId): array
    {
        $data = $this->loadData();
        $results = [];

        foreach ($data as $item) {
            if ($item['parking_id'] === $parkingId &&
                in_array($item['status'], ['pending', 'confirmed'], true)) {
                $results[] = $this->hydrateReservation($item);
            }
        }

        return $results;
    }

    public function findActiveReservationsForUser(string $userId): array
    {
        $data = $this->loadData();
        $results = [];

        foreach ($data as $item) {
            if ($item['user_id'] === $userId &&
                in_array($item['status'], ['pending', 'confirmed'], true)) {
                $results[] = $this->hydrateReservation($item);
            }
        }

        return $results;
    }

    public function findReservationsInTimeRange(
        \DateTimeInterface $startTime,
        \DateTimeInterface $endTime
    ): array {
        $data = $this->loadData();
        $results = [];

        $start = $startTime->getTimestamp();
        $end = $endTime->getTimestamp();

        foreach ($data as $item) {
            $itemStart = strtotime($item['start_time']);
            $itemEnd = strtotime($item['end_time']);

            if ($itemStart >= $start && $itemEnd <= $end) {
                $results[] = $this->hydrateReservation($item);
            }
        }

        return $results;
    }

    public function findConflictingReservations(
        string $parkingId,
        \DateTimeInterface $startTime,
        \DateTimeInterface $endTime
    ): array {
        $data = $this->loadData();
        $results = [];

        $start = $startTime->getTimestamp();
        $end = $endTime->getTimestamp();

        foreach ($data as $item) {
            if ($item['parking_id'] !== $parkingId) {
                continue;
            }

            if (!in_array($item['status'], ['pending', 'confirmed'], true)) {
                continue;
            }

            $itemStart = strtotime($item['start_time']);
            $itemEnd = strtotime($item['end_time']);

            // Check for overlap
            if ($itemStart < $end && $itemEnd > $start) {
                $results[] = $this->hydrateReservation($item);
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
                $results[] = $this->hydrateReservation($item);
            }
        }

        return $this->sortByStartTimeDesc($results);
    }

    public function findByDateRange(
        \DateTimeInterface $from,
        \DateTimeInterface $to
    ): array {
        return $this->findReservationsInTimeRange($from, $to);
    }

    public function findExpiredReservations(): array
    {
        $data = $this->loadData();
        $results = [];
        $now = time();

        foreach ($data as $item) {
            if ($item['status'] === 'confirmed' &&
                strtotime($item['end_time']) < $now) {
                $results[] = $this->hydrateReservation($item);
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

            if (!in_array($item['status'], ['confirmed', 'completed'], true)) {
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

    private function serializeReservation(Reservation $reservation): array
    {
        return [
            'id' => $reservation->getId(),
            'user_id' => $reservation->getUserId(),
            'parking_id' => $reservation->getParkingId(),
            'start_time' => $reservation->getStartTime()->format(\DateTimeInterface::ATOM),
            'end_time' => $reservation->getEndTime()->format(\DateTimeInterface::ATOM),
            'total_amount' => $reservation->getTotalAmount(),
            'status' => $reservation->getStatus(),
            'created_at' => $reservation->getCreatedAt()->format(\DateTimeInterface::ATOM)
        ];
    }

    private function hydrateReservation(array $data): Reservation
    {
        return new Reservation(
            $data['id'],
            $data['user_id'],
            $data['parking_id'],
            new \DateTimeImmutable($data['start_time']),
            new \DateTimeImmutable($data['end_time']),
            (float)$data['total_amount'],
            $data['status'],
            new \DateTimeImmutable($data['created_at'])
        );
    }

    private function sortByStartTimeDesc(array $reservations): array
    {
        usort($reservations, function (Reservation $a, Reservation $b) {
            return $b->getStartTime() <=> $a->getStartTime();
        });

        return $reservations;
    }
}
