<?php

declare(strict_types=1);

namespace ParkingSystem\UseCase\Reservation;

/**
 * Exception thrown when a user tries to access a reservation they don't own
 */
class UnauthorizedReservationAccessException extends \Exception
{
}
