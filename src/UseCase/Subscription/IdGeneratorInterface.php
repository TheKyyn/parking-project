<?php

declare(strict_types=1);

namespace ParkingSystem\UseCase\Subscription;

/**
 * IdGeneratorInterface
 * Use Case Layer - Contract for ID generation in Subscription module
 */
interface IdGeneratorInterface
{
    public function generate(): string;
}
