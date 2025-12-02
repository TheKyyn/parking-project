<?php

declare(strict_types=1);

namespace ParkingSystem\UseCase\Session;

/**
 * PricingCalculatorInterface
 * Use Case Layer - Contract for session pricing calculations
 */
interface PricingCalculatorInterface
{
    /**
     * Calculate session price using 15-minute increments
     */
    public function calculateSessionPrice(
        float $hourlyRate,
        \DateTimeInterface $startTime,
        \DateTimeInterface $endTime
    ): float;

    /**
     * Calculate overstay penalty (€20 base + additional time charged)
     */
    public function calculateOverstayPenalty(
        float $hourlyRate,
        \DateTimeInterface $authorizedEndTime,
        \DateTimeInterface $actualEndTime
    ): float;
}
