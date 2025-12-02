<?php

declare(strict_types=1);

namespace ParkingSystem\UseCase\Reservation;

/**
 * PricingCalculatorInterface
 * Use Case Layer - Service contract for pricing calculations
 */
interface PricingCalculatorInterface
{
    /**
     * Calculate price for parking duration
     * Must implement 15-minute increment billing as per business rules
     * 
     * @param float $hourlyRate Base hourly rate
     * @param \DateTimeInterface $startTime Reservation start time
     * @param \DateTimeInterface $endTime Reservation end time
     * @return float Total amount to charge
     */
    public function calculateReservationPrice(
        float $hourlyRate,
        \DateTimeInterface $startTime,
        \DateTimeInterface $endTime
    ): float;

    /**
     * Calculate price with progressive rates if applicable
     * 
     * @param string $parkingId Parking identifier
     * @param \DateTimeInterface $startTime Start time
     * @param \DateTimeInterface $endTime End time
     * @return float Total amount with any progressive pricing
     */
    public function calculateWithProgressiveRates(
        string $parkingId,
        \DateTimeInterface $startTime,
        \DateTimeInterface $endTime
    ): float;
}