<?php

declare(strict_types=1);

namespace ParkingSystem\UseCase\Reservation;

/**
 * IdGeneratorInterface
 * Use Case Layer - Service contract for ID generation
 */
interface IdGeneratorInterface
{
    public function generate(): string;
}