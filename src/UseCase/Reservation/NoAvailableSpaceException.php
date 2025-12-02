<?php

declare(strict_types=1);

namespace ParkingSystem\UseCase\Reservation;

/**
 * NoAvailableSpaceException
 * Use Case Layer - Domain exception for no parking space available
 */
class NoAvailableSpaceException extends \DomainException
{
}