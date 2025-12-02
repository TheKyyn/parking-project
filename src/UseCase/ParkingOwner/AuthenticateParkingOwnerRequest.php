<?php

declare(strict_types=1);

namespace ParkingSystem\UseCase\ParkingOwner;

/**
 * AuthenticateParkingOwnerRequest DTO
 * Use Case Layer - Input data transfer object
 */
class AuthenticateParkingOwnerRequest
{
    public function __construct(
        public readonly string $email,
        public readonly string $password
    ) {
    }
}
