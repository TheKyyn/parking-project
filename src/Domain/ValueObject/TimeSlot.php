<?php

declare(strict_types=1);

namespace ParkingSystem\Domain\ValueObject;

/**
 * TimeSlot Value Object
 * Domain Layer - Immutable value object with NO external dependencies
 */
class TimeSlot
{
    private \DateTimeImmutable $startTime;
    private \DateTimeImmutable $endTime;

    public function __construct(\DateTimeImmutable $startTime, \DateTimeImmutable $endTime)
    {
        $this->validateTimeRange($startTime, $endTime);
        
        $this->startTime = $startTime;
        $this->endTime = $endTime;
    }

    public function getStartTime(): \DateTimeImmutable
    {
        return $this->startTime;
    }

    public function getEndTime(): \DateTimeImmutable
    {
        return $this->endTime;
    }

    public function getDurationInMinutes(): int
    {
        $interval = $this->startTime->diff($this->endTime);
        return ($interval->h * 60) + $interval->i + ($interval->days * 24 * 60);
    }

    public function getDurationInHours(): float
    {
        return $this->getDurationInMinutes() / 60;
    }

    public function contains(\DateTimeInterface $dateTime): bool
    {
        return $dateTime >= $this->startTime && $dateTime <= $this->endTime;
    }

    public function overlaps(TimeSlot $other): bool
    {
        return $this->startTime < $other->endTime && $this->endTime > $other->startTime;
    }

    public function touches(TimeSlot $other): bool
    {
        return $this->startTime == $other->endTime || $this->endTime == $other->startTime;
    }

    public function equals(TimeSlot $other): bool
    {
        return $this->startTime == $other->startTime && $this->endTime == $other->endTime;
    }

    public function isBefore(TimeSlot $other): bool
    {
        return $this->endTime <= $other->startTime;
    }

    public function isAfter(TimeSlot $other): bool
    {
        return $this->startTime >= $other->endTime;
    }

    public function merge(TimeSlot $other): TimeSlot
    {
        if (!$this->overlaps($other) && !$this->touches($other)) {
            throw new \InvalidArgumentException('Cannot merge non-overlapping and non-touching time slots');
        }
        
        $earliestStart = $this->startTime < $other->startTime ? $this->startTime : $other->startTime;
        $latestEnd = $this->endTime > $other->endTime ? $this->endTime : $other->endTime;
        
        return new self($earliestStart, $latestEnd);
    }

    public function extendBy(\DateInterval $duration): TimeSlot
    {
        return new self($this->startTime, $this->endTime->add($duration));
    }

    public function shrinkBy(\DateInterval $duration): TimeSlot
    {
        $newEndTime = $this->endTime->sub($duration);
        
        if ($newEndTime <= $this->startTime) {
            throw new \InvalidArgumentException('Cannot shrink time slot to zero or negative duration');
        }
        
        return new self($this->startTime, $newEndTime);
    }

    public function toString(string $format = 'Y-m-d H:i'): string
    {
        return $this->startTime->format($format) . ' - ' . $this->endTime->format($format);
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    public function toArray(): array
    {
        return [
            'startTime' => $this->startTime->format(\DateTimeInterface::ATOM),
            'endTime' => $this->endTime->format(\DateTimeInterface::ATOM),
            'durationMinutes' => $this->getDurationInMinutes()
        ];
    }

    public static function fromArray(array $data): self
    {
        if (!isset($data['startTime'], $data['endTime'])) {
            throw new \InvalidArgumentException('Array must contain startTime and endTime');
        }
        
        $startTime = new \DateTimeImmutable($data['startTime']);
        $endTime = new \DateTimeImmutable($data['endTime']);
        
        return new self($startTime, $endTime);
    }

    public static function fromTimes(string $startTime, string $endTime): self
    {
        return new self(
            new \DateTimeImmutable($startTime),
            new \DateTimeImmutable($endTime)
        );
    }

    public static function fromDuration(\DateTimeImmutable $startTime, \DateInterval $duration): self
    {
        return new self($startTime, $startTime->add($duration));
    }

    private function validateTimeRange(\DateTimeImmutable $startTime, \DateTimeImmutable $endTime): void
    {
        if ($startTime >= $endTime) {
            throw new \InvalidArgumentException('Start time must be before end time');
        }
    }
}