<?php

declare(strict_types=1);

namespace ParkingSystem\Domain\Entity;

/**
 * ParkingSession Entity (Stationnement)
 * Domain Layer - Pure business entity with NO external dependencies
 */
class ParkingSession
{
    private string $id;
    private string $userId;
    private string $parkingId;
    private ?string $reservationId;
    private \DateTimeImmutable $startTime;
    private ?\DateTimeImmutable $endTime;
    private ?float $totalAmount;
    private string $status; // active, completed, overstayed
    private \DateTimeImmutable $createdAt;

    public function __construct(
        string $id,
        string $userId,
        string $parkingId,
        \DateTimeImmutable $startTime,
        ?string $reservationId = null,
        ?\DateTimeImmutable $createdAt = null
    ) {
        $this->id = $id;
        $this->userId = $userId;
        $this->parkingId = $parkingId;
        $this->reservationId = $reservationId;
        $this->startTime = $startTime;
        $this->endTime = null;
        $this->totalAmount = null;
        $this->status = 'active';
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

    public function getReservationId(): ?string
    {
        return $this->reservationId;
    }

    public function getStartTime(): \DateTimeImmutable
    {
        return $this->startTime;
    }

    public function getEndTime(): ?\DateTimeImmutable
    {
        return $this->endTime;
    }

    public function getTotalAmount(): ?float
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

    public function getDurationInMinutes(): ?int
    {
        if ($this->endTime === null) {
            return null;
        }
        
        $interval = $this->startTime->diff($this->endTime);
        return ($interval->h * 60) + $interval->i + ($interval->days * 24 * 60);
    }

    public function getCurrentDurationInMinutes(): int
    {
        $now = new \DateTimeImmutable();
        $interval = $this->startTime->diff($now);
        return ($interval->h * 60) + $interval->i + ($interval->days * 24 * 60);
    }

    public function endSession(\DateTimeImmutable $endTime, float $totalAmount): void
    {
        if ($this->status !== 'active') {
            throw new \DomainException('Only active sessions can be ended');
        }
        
        if ($endTime <= $this->startTime) {
            throw new \InvalidArgumentException('End time must be after start time');
        }
        
        if ($totalAmount < 0) {
            throw new \InvalidArgumentException('Total amount cannot be negative');
        }
        
        $this->endTime = $endTime;
        $this->totalAmount = $totalAmount;
        $this->status = 'completed';
    }

    public function markAsOverstayed(): void
    {
        if ($this->status !== 'active') {
            throw new \DomainException('Only active sessions can be marked as overstayed');
        }
        
        $this->status = 'overstayed';
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isOverstayed(): bool
    {
        return $this->status === 'overstayed';
    }

    public function hasReservation(): bool
    {
        return $this->reservationId !== null;
    }

    public function calculateAmount(float $hourlyRate, \DateTimeInterface $endTime): float
    {
        $endTimeImmutable = \DateTimeImmutable::createFromInterface($endTime);
        $durationMinutes = $this->startTime->diff($endTimeImmutable)->i +
                          ($this->startTime->diff($endTimeImmutable)->h * 60) +
                          ($this->startTime->diff($endTimeImmutable)->days * 24 * 60);
        
        // Billing by 15-minute increments (round up)
        $billingMinutes = ceil($durationMinutes / 15) * 15;
        $billingHours = $billingMinutes / 60;
        
        return $billingHours * $hourlyRate;
    }

    public function calculateOverstayPenalty(
        float $hourlyRate,
        \DateTimeInterface $authorizedEndTime,
        \DateTimeInterface $actualEndTime
    ): float {
        if ($actualEndTime <= $authorizedEndTime) {
            return 0;
        }
        
        $overstayMinutes = \DateTimeImmutable::createFromInterface($authorizedEndTime)
            ->diff(\DateTimeImmutable::createFromInterface($actualEndTime))->i +
            (\DateTimeImmutable::createFromInterface($authorizedEndTime)
                ->diff(\DateTimeImmutable::createFromInterface($actualEndTime))->h * 60);
        
        // €20 penalty + additional time charged
        $additionalTime = ceil($overstayMinutes / 15) * 15;
        $additionalAmount = ($additionalTime / 60) * $hourlyRate;
        
        return 20.0 + $additionalAmount;
    }
}