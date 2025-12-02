<?php

declare(strict_types=1);

namespace ParkingSystem\UseCase\Session;

/**
 * ActiveSessionExistsException
 * Thrown when user already has active session in the parking
 */
class ActiveSessionExistsException extends \DomainException
{
}
