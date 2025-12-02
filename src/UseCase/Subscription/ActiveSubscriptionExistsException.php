<?php

declare(strict_types=1);

namespace ParkingSystem\UseCase\Subscription;

/**
 * ActiveSubscriptionExistsException
 * Thrown when user already has active subscription for the parking
 */
class ActiveSubscriptionExistsException extends \DomainException
{
}
