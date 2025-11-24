<?php

declare(strict_types=1);

namespace ParkingSystem\Domain\Entity;

/**
 * Subscription Entity (Abonnement)
 * Domain Layer - Pure business entity with NO external dependencies
 */
class Subscription
{
    private string $id;
    private string $userId;
    private string $parkingId;
    private array $weeklyTimeSlots;
    private int $durationMonths;
    private \DateTimeImmutable $startDate;
    private \DateTimeImmutable $endDate;
    private float $monthlyAmount;
    private string $status; // active, expired, cancelled
    private \DateTimeImmutable $createdAt;

    public function __construct(
        string $id,
        string $userId,
        string $parkingId,
        array $weeklyTimeSlots,
        int $durationMonths,
        \DateTimeImmutable $startDate,
        float $monthlyAmount,
        string $status = 'active',
        ?\DateTimeImmutable $createdAt = null
    ) {
        $this->validateDuration($durationMonths);
        $this->validateWeeklyTimeSlots($weeklyTimeSlots);
        $this->validateAmount($monthlyAmount);
        $this->validateStatus($status);
        
        $this->id = $id;
        $this->userId = $userId;
        $this->parkingId = $parkingId;
        $this->weeklyTimeSlots = $weeklyTimeSlots;
        $this->durationMonths = $durationMonths;
        $this->startDate = $startDate;
        $this->endDate = $this->calculateEndDate($startDate, $durationMonths);
        $this->monthlyAmount = $monthlyAmount;
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

    public function getWeeklyTimeSlots(): array
    {
        return $this->weeklyTimeSlots;
    }

    public function getDurationMonths(): int
    {
        return $this->durationMonths;
    }

    public function getStartDate(): \DateTimeImmutable
    {
        return $this->startDate;
    }

    public function getEndDate(): \DateTimeImmutable
    {
        return $this->endDate;
    }

    public function getMonthlyAmount(): float
    {
        return $this->monthlyAmount;
    }

    public function getTotalAmount(): float
    {
        return $this->monthlyAmount * $this->durationMonths;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isExpired(): bool
    {
        return $this->status === 'expired';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function cancel(): void
    {
        if (!$this->isActive()) {
            throw new \DomainException('Only active subscriptions can be cancelled');
        }
        
        $this->status = 'cancelled';
    }

    public function expire(): void
    {
        if (!$this->isActive()) {
            throw new \DomainException('Only active subscriptions can be expired');
        }
        
        $this->status = 'expired';
    }

    public function isValidAt(\DateTimeInterface $dateTime): bool
    {
        if (!$this->isActive()) {
            return false;
        }
        
        return $dateTime >= $this->startDate && $dateTime <= $this->endDate;
    }

    public function coversTimeSlot(\DateTimeInterface $dateTime): bool
    {
        if (!$this->isValidAt($dateTime)) {
            return false;
        }
        
        $dayOfWeek = (int)$dateTime->format('w'); // 0 = Sunday, 1 = Monday, etc.
        $timeOfDay = $dateTime->format('H:i');
        
        if (!isset($this->weeklyTimeSlots[$dayOfWeek])) {
            return false;
        }
        
        foreach ($this->weeklyTimeSlots[$dayOfWeek] as $slot) {
            if (!isset($slot['start'], $slot['end'])) {
                continue;
            }
            
            if ($timeOfDay >= $slot['start'] && $timeOfDay <= $slot['end']) {
                return true;
            }
        }
        
        return false;
    }

    public function updateTimeSlots(array $newWeeklyTimeSlots): void
    {
        if (!$this->isActive()) {
            throw new \DomainException('Cannot update time slots of inactive subscription');
        }
        
        $this->validateWeeklyTimeSlots($newWeeklyTimeSlots);
        $this->weeklyTimeSlots = $newWeeklyTimeSlots;
    }

    public function getRemainingDays(): int
    {
        if (!$this->isActive()) {
            return 0;
        }
        
        $now = new \DateTimeImmutable();
        if ($now > $this->endDate) {
            return 0;
        }
        
        return $now->diff($this->endDate)->days;
    }

    private function calculateEndDate(\DateTimeImmutable $startDate, int $months): \DateTimeImmutable
    {
        return $startDate->add(new \DateInterval('P' . $months . 'M'));
    }

    private function validateDuration(int $months): void
    {
        if ($months < 1 || $months > 12) {
            throw new \InvalidArgumentException('Duration must be between 1 and 12 months');
        }
    }

    private function validateWeeklyTimeSlots(array $timeSlots): void
    {
        foreach ($timeSlots as $dayOfWeek => $slots) {
            if (!is_int($dayOfWeek) || $dayOfWeek < 0 || $dayOfWeek > 6) {
                throw new \InvalidArgumentException('Day of week must be 0-6');
            }
            
            if (!is_array($slots)) {
                throw new \InvalidArgumentException('Time slots must be an array');
            }
            
            foreach ($slots as $slot) {
                if (!isset($slot['start'], $slot['end'])) {
                    throw new \InvalidArgumentException('Each slot must have start and end time');
                }
                
                if ($slot['start'] >= $slot['end']) {
                    throw new \InvalidArgumentException('Start time must be before end time');
                }
            }
        }
    }

    private function validateAmount(float $amount): void
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Monthly amount must be positive');
        }
    }

    private function validateStatus(string $status): void
    {
        $validStatuses = ['active', 'expired', 'cancelled'];
        if (!in_array($status, $validStatuses, true)) {
            throw new \InvalidArgumentException('Invalid status: ' . $status);
        }
    }
}