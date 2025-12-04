<?php

declare(strict_types=1);

namespace ParkingSystem\Infrastructure\Service;

use ParkingSystem\UseCase\Reservation\PricingCalculatorInterface;
use ParkingSystem\Domain\Repository\ParkingRepositoryInterface;

/**
 * Simple pricing calculator with 15-minute billing increments
 *
 * Business Rule: All parking is billed in 15-minute increments (quarters)
 * Formula: quarters * (hourlyRate / 4)
 */
class SimplePricingCalculator implements PricingCalculatorInterface
{
    public function __construct(
        private ParkingRepositoryInterface $parkingRepository
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function calculateReservationPrice(
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
    public function calculateWithProgressiveRates(
        string $parkingId,
        \DateTimeInterface $startTime,
        \DateTimeInterface $endTime
    ): float {
        // Get parking to retrieve hourly rate
        $parking = $this->parkingRepository->findById($parkingId);

        if ($parking === null) {
            throw new \InvalidArgumentException('Parking not found: ' . $parkingId);
        }

        // For now, use simple pricing (no progressive rates implemented yet)
        return $this->calculateReservationPrice(
            $parking->getHourlyRate(),
            $startTime,
            $endTime
        );
    }
}
