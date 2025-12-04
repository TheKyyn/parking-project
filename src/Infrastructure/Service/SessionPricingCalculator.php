<?php

declare(strict_types=1);

namespace ParkingSystem\Infrastructure\Service;

use ParkingSystem\UseCase\Session\PricingCalculatorInterface;

/**
 * Session pricing calculator with 15-minute billing increments
 *
 * Business Rule: All parking is billed in 15-minute increments (quarters)
 * Formula: quarters * (hourlyRate / 4)
 */
class SessionPricingCalculator implements PricingCalculatorInterface
{
    /**
     * {@inheritDoc}
     */
    public function calculateSessionPrice(
        float $hourlyRate,
        \DateTimeInterface $startTime,
        \DateTimeInterface $endTime
    ): float {
        // Calculate duration in minutes
        $durationMinutes = ($endTime->getTimestamp() - $startTime->getTimestamp()) / 60;

        if ($durationMinutes <= 0) {
            throw new \InvalidArgumentException('End time must be after start time');
        }

        // Round up to nearest 15-minute increment
        $quarters = (int) ceil($durationMinutes / 15);

        // Calculate cost: quarters * (hourlyRate / 4)
        $quarterlyRate = $hourlyRate / 4;
        $totalAmount = $quarters * $quarterlyRate;

        // Round to 2 decimal places
        return round($totalAmount, 2);
    }

    /**
     * {@inheritDoc}
     */
    public function calculateOverstayPenalty(
        float $hourlyRate,
        \DateTimeInterface $authorizedEndTime,
        \DateTimeInterface $actualEndTime
    ): float {
        if ($actualEndTime <= $authorizedEndTime) {
            return 0.0;
        }

        // Calculate overstay duration in minutes
        $overstayMinutes = ($actualEndTime->getTimestamp() - $authorizedEndTime->getTimestamp()) / 60;

        // Round up to nearest 15-minute increment
        $quarters = (int) ceil($overstayMinutes / 15);

        // Calculate additional cost for overstay time
        $quarterlyRate = $hourlyRate / 4;
        $additionalAmount = $quarters * $quarterlyRate;

        // Total penalty: €20 base penalty + additional time charged
        $penalty = 20.0 + $additionalAmount;

        // Round to 2 decimal places
        return round($penalty, 2);
    }
}
