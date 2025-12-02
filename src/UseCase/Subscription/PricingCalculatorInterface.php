<?php

declare(strict_types=1);

namespace ParkingSystem\UseCase\Subscription;

/**
 * PricingCalculatorInterface
 * Use Case Layer - Contract for subscription pricing calculations
 */
interface PricingCalculatorInterface
{
    /**
     * Calculate monthly subscription price based on time slots and hourly rate
     */
    public function calculateMonthlyPrice(
        float $hourlyRate,
        array $weeklyTimeSlots
    ): float;

    /**
     * Calculate total subscription price for duration
     */
    public function calculateTotalPrice(
        float $hourlyRate,
        array $weeklyTimeSlots,
        int $durationMonths
    ): float;
}
