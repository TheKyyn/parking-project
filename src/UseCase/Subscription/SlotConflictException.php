<?php

declare(strict_types=1);

namespace ParkingSystem\UseCase\Subscription;

/**
 * SlotConflictException
 * Thrown when subscription time slots conflict with existing subscriptions
 */
class SlotConflictException extends \DomainException
{
}
