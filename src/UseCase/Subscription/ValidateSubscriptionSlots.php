<?php

declare(strict_types=1);

namespace ParkingSystem\UseCase\Subscription;

use ParkingSystem\Domain\Repository\ParkingRepositoryInterface;

/**
 * ValidateSubscriptionSlots Use Case
 * Use Case Layer - Validates and provides info about subscription time slots
 */
class ValidateSubscriptionSlots
{
    public function __construct(
        private ParkingRepositoryInterface $parkingRepository,
        private SlotConflictCheckerInterface $slotConflictChecker,
        private PricingCalculatorInterface $pricingCalculator
    ) {
    }

    public function execute(
        ValidateSubscriptionSlotsRequest $request
    ): ValidateSubscriptionSlotsResponse {
        $this->validateRequest($request);

        // Verify parking exists
        $parking = $this->parkingRepository->findById($request->parkingId);
        if ($parking === null) {
            throw new ParkingNotFoundException(
                'Parking not found: ' . $request->parkingId
            );
        }

        $startDateImmutable = \DateTimeImmutable::createFromInterface(
            $request->startDate
        );
        $endDate = $startDateImmutable->add(
            new \DateInterval('P' . $request->durationMonths . 'M')
        );

        // Analyze each slot
        $validSlots = [];
        $conflictingSlots = [];
        $availabilityBySlot = [];

        foreach ($request->weeklyTimeSlots as $dayOfWeek => $slots) {
            foreach ($slots as $index => $slot) {
                $slotKey = $dayOfWeek . '_' . $index;

                // Check if slot conflicts with parking hours
                $isWithinOpeningHours = $this->isSlotWithinOpeningHours(
                    $slot,
                    $dayOfWeek,
                    $parking->getOpeningHours()
                );

                if (!$isWithinOpeningHours) {
                    $conflictingSlots[$slotKey] = [
                        'day' => $dayOfWeek,
                        'slot' => $slot,
                        'reason' => 'outside_opening_hours'
                    ];
                    $availabilityBySlot[$slotKey] = 0;
                    continue;
                }

                // Check availability
                $availableSpaces = $this->slotConflictChecker->getAvailableSpacesForSlot(
                    $request->parkingId,
                    $dayOfWeek,
                    $slot['start'],
                    $slot['end']
                );

                $availabilityBySlot[$slotKey] = $availableSpaces;

                if ($availableSpaces > 0) {
                    $validSlots[$slotKey] = [
                        'day' => $dayOfWeek,
                        'slot' => $slot,
                        'availableSpaces' => $availableSpaces
                    ];
                } else {
                    $conflictingSlots[$slotKey] = [
                        'day' => $dayOfWeek,
                        'slot' => $slot,
                        'reason' => 'no_available_spaces'
                    ];
                }
            }
        }

        // Calculate pricing for valid slots
        $validTimeSlotsForPricing = $this->extractValidTimeSlots(
            $request->weeklyTimeSlots,
            $validSlots
        );

        $monthlyPrice = 0.0;
        $totalPrice = 0.0;

        if (!empty($validTimeSlotsForPricing)) {
            $monthlyPrice = $this->pricingCalculator->calculateMonthlyPrice(
                $parking->getHourlyRate(),
                $validTimeSlotsForPricing
            );
            $totalPrice = $monthlyPrice * $request->durationMonths;
        }

        $isValid = empty($conflictingSlots) && !empty($validSlots);

        return new ValidateSubscriptionSlotsResponse(
            $isValid,
            $validSlots,
            $conflictingSlots,
            $monthlyPrice,
            $totalPrice,
            $availabilityBySlot
        );
    }

    private function validateRequest(ValidateSubscriptionSlotsRequest $request): void
    {
        if (empty($request->parkingId)) {
            throw new \InvalidArgumentException('Parking ID is required');
        }

        if (empty($request->weeklyTimeSlots)) {
            throw new InvalidTimeSlotException('At least one time slot is required');
        }

        if ($request->durationMonths < 1 || $request->durationMonths > 12) {
            throw new InvalidDurationException(
                'Duration must be between 1 and 12 months'
            );
        }
    }

    private function isSlotWithinOpeningHours(
        array $slot,
        int $dayOfWeek,
        array $openingHours
    ): bool {
        // 24/7 parking
        if (empty($openingHours)) {
            return true;
        }

        // Parking closed on this day
        if (!isset($openingHours[$dayOfWeek])) {
            return false;
        }

        $parkingHours = $openingHours[$dayOfWeek];

        return $slot['start'] >= $parkingHours['open'] &&
               $slot['end'] <= $parkingHours['close'];
    }

    private function extractValidTimeSlots(
        array $originalSlots,
        array $validSlots
    ): array {
        $result = [];

        foreach ($validSlots as $slotKey => $validSlot) {
            $dayOfWeek = $validSlot['day'];
            $slot = $validSlot['slot'];

            if (!isset($result[$dayOfWeek])) {
                $result[$dayOfWeek] = [];
            }

            $result[$dayOfWeek][] = $slot;
        }

        return $result;
    }
}
