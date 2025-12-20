<?php

declare(strict_types=1);

namespace ParkingSystem\UseCase\Subscription;

class SubscriptionInfo
{
    public function __construct(
        public readonly string $id,
        public readonly string $userId,
        public readonly string $parkingId,
        public readonly array $weeklyTimeSlots,
        public readonly int $durationMonths,
        public readonly string $startDate,
        public readonly string $endDate,
        public readonly float $monthlyAmount,
        public readonly string $status
    ) {
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'userId' => $this->userId,
            'parkingId' => $this->parkingId,
            'weeklyTimeSlots' => $this->weeklyTimeSlots,
            'durationMonths' => $this->durationMonths,
            'startDate' => $this->startDate,
            'endDate' => $this->endDate,
            'monthlyAmount' => $this->monthlyAmount,
            'status' => $this->status
        ];
    }
}
