<?php

declare(strict_types=1);

namespace ParkingSystem\Domain\Entity;

/**
 * Reservation Entity
 * Domain Layer - Pure business entity with NO external dependencies
 */
class Reservation
{
    private string $id;
    private string $userId;
    private string $parkingId;
    private \DateTimeImmutable $startTime;
    private \DateTimeImmutable $endTime;
    private float $totalAmount;
    private string $status; // pending, confirmed, cancelled, completed
    private \DateTimeImmutable $createdAt;

    public function __construct(
        string $id,
        string $userId,
        string $parkingId,
        \DateTimeImmutable $startTime,
        \DateTimeImmutable $endTime,
        float $totalAmount,
        string $status = 'pending',
        ?\DateTimeImmutable $createdAt = null
    ) {
        $this->validateTimeRange($startTime, $endTime);
        $this->validateAmount($totalAmount);
        $this->validateStatus($status);
        
        $this->id = $id;
        $this->userId = $userId;
        $this->parkingId = $parkingId;
        $this->startTime = $startTime;
        $this->endTime = $endTime;
        $this->totalAmount = $totalAmount;
        $this->status = $status;
        $this->createdAt = $createdAt ?? new \DateTimeImmutable();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function getParkingId(): string
    {
        return $this->parkingId;
    }

    public function getStartTime(): \DateTimeImmutable
    {
        return $this->startTime;
    }

    public function getEndTime(): \DateTimeImmutable
    {
        return $this->endTime;
    }

    public function getTotalAmount(): float
    {
        return $this->totalAmount;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getDurationInMinutes(): int
    {
        $interval = $this->startTime->diff($this->endTime);
        return ($interval->h * 60) + $interval->i;
    }

    public function getDurationInHours(): float
    {
        return $this->getDurationInMinutes() / 60;
    }

    public function confirm(): void
    {
        if ($this->status !== 'pending') {
            throw new \DomainException('Only pending reservations can be confirmed');
        }
        
        $this->status = 'confirmed';
    }

    public function cancel(): void
    {
        if (!in_array($this->status, ['pending', 'confirmed'], true)) {
            throw new \DomainException('Cannot cancel reservation in status: ' . $this->status);
        }
        
        $this->status = 'cancelled';
    }

    public function complete(): void
    {
        if ($this->status !== 'confirmed') {
            throw new \DomainException('Only confirmed reservations can be completed');
        }
        
        $this->status = 'completed';
    }

    public function isActive(): bool
    {
        return in_array($this->status, ['confirmed'], true);
    }

    public function isActiveAt(\DateTimeInterface $dateTime): bool
    {
        if (!$this->isActive()) {
            return false;
        }
        
        return $dateTime >= $this->startTime && $dateTime <= $this->endTime;
    }

    public function overlaps(\DateTimeInterface $startTime, \DateTimeInterface $endTime): bool
    {
        if (!$this->isActive()) {
            return false;
        }
        
        return $startTime < $this->endTime && $endTime > $this->startTime;
    }

    private function validateTimeRange(\DateTimeImmutable $startTime, \DateTimeImmutable $endTime): void
    {
        if ($startTime >= $endTime) {
            throw new \InvalidArgumentException('Start time must be before end time');
        }
        
        $now = new \DateTimeImmutable();
        if ($startTime < $now) {
            throw new \InvalidArgumentException('Start time cannot be in the past');
        }
        
        // Maximum reservation duration: 24 hours
        $maxDuration = $startTime->add(new \DateInterval('P1D'));
        if ($endTime > $maxDuration) {
            throw new \InvalidArgumentException('Reservation cannot exceed 24 hours');
        }
    }

    private function validateAmount(float $amount): void
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Amount must be positive');
        }
    }

    private function validateStatus(string $status): void
    {
        $validStatuses = ['pending', 'confirmed', 'cancelled', 'completed'];
        if (!in_array($status, $validStatuses, true)) {
            throw new \InvalidArgumentException('Invalid status: ' . $status);
        }
    }
}