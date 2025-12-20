<?php

declare(strict_types=1);

namespace ParkingSystem\Infrastructure\Service;

use ParkingSystem\UseCase\Subscription\PricingCalculatorInterface;

class SubscriptionPricingCalculator implements PricingCalculatorInterface
{
    private const SUBSCRIPTION_DISCOUNT = 0.20;
    private const WEEKS_PER_MONTH = 4.33;

    public function calculateMonthlyPrice(
        float $hourlyRate,
        array $weeklyTimeSlots
    ): float {
        $weeklyHours = $this->calculateWeeklyHours($weeklyTimeSlots);
        $weeklyPrice = $weeklyHours * $hourlyRate;
        $monthlyPrice = $weeklyPrice * self::WEEKS_PER_MONTH;

        $discountedPrice = $monthlyPrice * (1 - self::SUBSCRIPTION_DISCOUNT);

        return round($discountedPrice, 2);
    }

    public function calculateTotalPrice(
        float $hourlyRate,
        array $weeklyTimeSlots,
        int $durationMonths
    ): float {
        $monthlyPrice = $this->calculateMonthlyPrice($hourlyRate, $weeklyTimeSlots);

        return round($monthlyPrice * $durationMonths, 2);
    }

    private function calculateWeeklyHours(array $weeklyTimeSlots): float
    {
        $totalMinutes = 0;

        foreach ($weeklyTimeSlots as $slots) {
            foreach ($slots as $slot) {
                $totalMinutes += $this->getSlotDurationMinutes($slot);
            }
        }

        return $totalMinutes / 60;
    }

    private function getSlotDurationMinutes(array $slot): int
    {
        $start = \DateTime::createFromFormat('H:i', $slot['start']);
        $end = \DateTime::createFromFormat('H:i', $slot['end']);

        if (!$start || !$end) {
            return 0;
        }

        $diff = $end->getTimestamp() - $start->getTimestamp();

        return (int)($diff / 60);
    }
}
