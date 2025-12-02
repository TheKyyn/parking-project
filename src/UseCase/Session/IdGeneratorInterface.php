<?php

declare(strict_types=1);

namespace ParkingSystem\UseCase\Session;

/**
 * IdGeneratorInterface
 * Use Case Layer - Contract for ID generation in Session module
 */
interface IdGeneratorInterface
{
    public function generate(): string;
}
