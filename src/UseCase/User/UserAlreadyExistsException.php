<?php

declare(strict_types=1);

namespace ParkingSystem\UseCase\User;

/**
 * UserAlreadyExistsException
 * Use Case Layer - Domain exception for business rule violation
 */
class UserAlreadyExistsException extends \DomainException
{
}