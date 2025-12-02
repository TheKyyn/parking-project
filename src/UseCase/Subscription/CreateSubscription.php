<?php

declare(strict_types=1);

namespace ParkingSystem\UseCase\Subscription;

use ParkingSystem\Domain\Entity\Subscription;
use ParkingSystem\Domain\Repository\SubscriptionRepositoryInterface;
use ParkingSystem\Domain\Repository\ParkingRepositoryInterface;
use ParkingSystem\Domain\Repository\UserRepositoryInterface;

/**
 * CreateSubscription Use Case
 * Use Case Layer - Business logic for creating parking subscriptions
 */
class CreateSubscription
{
    public function __construct(
        private SubscriptionRepositoryInterface $subscriptionRepository,
        private ParkingRepositoryInterface $parkingRepository,
        private UserRepositoryInterface $userRepository,
        private SlotConflictCheckerInterface $slotConflictChecker,
        private PricingCalculatorInterface $pricingCalculator,
        private IdGeneratorInterface $idGenerator
    ) {
    }

    public function execute(CreateSubscriptionRequest $request): CreateSubscriptionResponse
    {
        $this->validateRequest($request);

        // Verify user exists
        if (!$this->userRepository->exists($request->userId)) {
            throw new UserNotFoundException('User not found: ' . $request->userId);
        }

        // Verify parking exists
        $parking = $this->parkingRepository->findById($request->parkingId);
        if ($parking === null) {
            throw new ParkingNotFoundException(
                'Parking not found: ' . $request->parkingId
            );
        }

        // Check for existing active subscription
        $existingSubscriptions = $this->subscriptionRepository
            ->findActiveSubscriptionsForUser($request->userId);

        foreach ($existingSubscriptions as $existing) {
            if ($existing->getParkingId() === $request->parkingId) {
                throw new ActiveSubscriptionExistsException(
                    'User already has an active subscription for this parking'
                );
            }
        }

        // Validate time slots against parking opening hours
        $this->validateTimeSlotsAgainstOpeningHours(
            $request->weeklyTimeSlots,
            $parking->getOpeningHours()
        );

        // Calculate end date
        $startDateImmutable = \DateTimeImmutable::createFromInterface($request->startDate);
        $endDate = $startDateImmutable->add(
            new \DateInterval('P' . $request->durationMonths . 'M')
        );

        // Check for slot conflicts
        if (!$this->slotConflictChecker->hasAvailableSlots(
            $request->parkingId,
            $request->weeklyTimeSlots,
            $startDateImmutable,
            $endDate
        )) {
            throw new SlotConflictException(
                'No available slots for the requested time periods'
            );
        }

        // Calculate pricing
        $monthlyAmount = $this->pricingCalculator->calculateMonthlyPrice(
            $parking->getHourlyRate(),
            $request->weeklyTimeSlots
        );

        // Create subscription
        $subscriptionId = $this->idGenerator->generate();

        $subscription = new Subscription(
            $subscriptionId,
            $request->userId,
            $request->parkingId,
            $request->weeklyTimeSlots,
            $request->durationMonths,
            $startDateImmutable,
            $monthlyAmount
        );

        // Save subscription
        $this->subscriptionRepository->save($subscription);

        return new CreateSubscriptionResponse(
            $subscription->getId(),
            $subscription->getUserId(),
            $subscription->getParkingId(),
            $subscription->getWeeklyTimeSlots(),
            $subscription->getDurationMonths(),
            $subscription->getStartDate()->format(\DateTimeInterface::ATOM),
            $subscription->getEndDate()->format(\DateTimeInterface::ATOM),
            $subscription->getMonthlyAmount(),
            $subscription->getTotalAmount(),
            $subscription->getStatus()
        );
    }

    private function validateRequest(CreateSubscriptionRequest $request): void
    {
        if (empty($request->userId)) {
            throw new \InvalidArgumentException('User ID is required');
        }

        if (empty($request->parkingId)) {
            throw new \InvalidArgumentException('Parking ID is required');
        }

        if (empty($request->weeklyTimeSlots)) {
            throw new InvalidTimeSlotException('At least one time slot is required');
        }

        $this->validateDuration($request->durationMonths);
        $this->validateTimeSlots($request->weeklyTimeSlots);
        $this->validateStartDate($request->startDate);
    }

    private function validateDuration(int $months): void
    {
        if ($months < 1) {
            throw new InvalidDurationException('Duration must be at least 1 month');
        }

        if ($months > 12) {
            throw new InvalidDurationException('Duration cannot exceed 12 months');
        }
    }

    private function validateTimeSlots(array $timeSlots): void
    {
        foreach ($timeSlots as $dayOfWeek => $slots) {
            if (!is_int($dayOfWeek) || $dayOfWeek < 0 || $dayOfWeek > 6) {
                throw new InvalidTimeSlotException(
                    'Day of week must be between 0 (Sunday) and 6 (Saturday)'
                );
            }

            if (!is_array($slots)) {
                throw new InvalidTimeSlotException(
                    'Time slots for each day must be an array'
                );
            }

            foreach ($slots as $slot) {
                $this->validateSingleSlot($slot, $dayOfWeek);
            }
        }
    }

    private function validateSingleSlot(array $slot, int $dayOfWeek): void
    {
        if (!isset($slot['start'], $slot['end'])) {
            throw new InvalidTimeSlotException(
                'Each slot must have start and end time'
            );
        }

        if (!$this->isValidTimeFormat($slot['start'])) {
            throw new InvalidTimeSlotException(
                'Invalid start time format: ' . $slot['start']
            );
        }

        if (!$this->isValidTimeFormat($slot['end'])) {
            throw new InvalidTimeSlotException(
                'Invalid end time format: ' . $slot['end']
            );
        }

        if ($slot['start'] >= $slot['end']) {
            throw new InvalidTimeSlotException(
                'Start time must be before end time for day ' . $dayOfWeek
            );
        }
    }

    private function isValidTimeFormat(string $time): bool
    {
        return preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $time) === 1;
    }

    private function validateStartDate(\DateTimeInterface $startDate): void
    {
        $today = new \DateTimeImmutable('today');
        if ($startDate < $today) {
            throw new \InvalidArgumentException(
                'Start date cannot be in the past'
            );
        }
    }

    private function validateTimeSlotsAgainstOpeningHours(
        array $timeSlots,
        array $openingHours
    ): void {
        // If parking has no specific opening hours, it's 24/7
        if (empty($openingHours)) {
            return;
        }

        foreach ($timeSlots as $dayOfWeek => $slots) {
            if (!isset($openingHours[$dayOfWeek])) {
                throw new InvalidTimeSlotException(
                    sprintf('Parking is closed on day %d', $dayOfWeek)
                );
            }

            $parkingHours = $openingHours[$dayOfWeek];

            foreach ($slots as $slot) {
                if ($slot['start'] < $parkingHours['open']) {
                    throw new InvalidTimeSlotException(sprintf(
                        'Slot starts before parking opens on day %d',
                        $dayOfWeek
                    ));
                }

                if ($slot['end'] > $parkingHours['close']) {
                    throw new InvalidTimeSlotException(sprintf(
                        'Slot ends after parking closes on day %d',
                        $dayOfWeek
                    ));
                }
            }
        }
    }
}
