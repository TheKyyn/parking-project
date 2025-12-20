<?php

declare(strict_types=1);

namespace ParkingSystem\Infrastructure\Service;

/**
 * Interface for generating unique identifiers
 */
interface IdGeneratorInterface
{
    /**
     * Generate a unique identifier
     *
     * @return string The generated identifier (UUID v4)
     */
    public function generate(): string;
}
