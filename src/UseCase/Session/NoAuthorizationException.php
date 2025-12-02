<?php

declare(strict_types=1);

namespace ParkingSystem\UseCase\Session;

/**
 * NoAuthorizationException
 * Thrown when user tries to enter parking without reservation/subscription
 */
class NoAuthorizationException extends \DomainException
{
}
