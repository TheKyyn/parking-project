<?php

declare(strict_types=1);

namespace ParkingSystem\UseCase\Session;

/**
 * ParkingClosedException
 * Thrown when attempting to enter closed parking
 */
class ParkingClosedException extends \DomainException
{
}
