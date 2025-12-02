<?php

declare(strict_types=1);

namespace ParkingSystem\UseCase\Parking;

/**
 * SearchParkingByLocationResponse DTO
 * Use Case Layer - Output data transfer object
 */
class SearchParkingByLocationResponse
{
    /**
     * @param ParkingSearchResult[] $parkings
     */
    public function __construct(
        public readonly array $parkings,
        public readonly int $totalFound
    ) {
    }
}