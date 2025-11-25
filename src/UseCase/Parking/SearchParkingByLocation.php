<?php

declare(strict_types=1);

namespace ParkingSystem\UseCase\Parking;

use ParkingSystem\Domain\Repository\ParkingRepositoryInterface;
use ParkingSystem\Domain\ValueObject\GpsCoordinates;

/**
 * SearchParkingByLocation Use Case
 * Use Case Layer - Complex search logic with GPS and availability filtering
 */
class SearchParkingByLocation
{
    public function __construct(
        private ParkingRepositoryInterface $parkingRepository,
        private AvailabilityCheckerInterface $availabilityChecker
    ) {
    }

    public function execute(SearchParkingByLocationRequest $request): SearchParkingByLocationResponse
    {
        $this->validateRequest($request);

        $searchLocation = new GpsCoordinates($request->latitude, $request->longitude);
        
        // Get parkings within radius
        $nearbyParkings = $this->parkingRepository->findNearLocation(
            $searchLocation,
            $request->radiusInKilometers,
            100 // Get more to filter later
        );

        $results = [];
        $searchTime = $request->startTime ?? new \DateTimeImmutable();

        foreach ($nearbyParkings as $parking) {
            // Apply filters
            if (!$this->passesFilters($parking, $request, $searchTime)) {
                continue;
            }

            // Calculate distance
            $parkingLocation = new GpsCoordinates(
                $parking->getLatitude(), 
                $parking->getLongitude()
            );
            $distance = $searchLocation->distanceTo($parkingLocation);

            // Get availability
            $availableSpaces = $this->getAvailableSpacesForTimeRange(
                $parking, 
                $request->startTime, 
                $request->endTime
            );

            $results[] = new ParkingSearchResult(
                $parking->getId(),
                $parking->getLatitude(),
                $parking->getLongitude(),
                $parking->getTotalSpaces(),
                $availableSpaces,
                $parking->getHourlyRate(),
                $distance,
                $parking->isOpenAt($searchTime),
                $parking->getOpeningHours()
            );
        }

        // Sort by distance
        usort($results, fn($a, $b) => $a->distanceInKilometers <=> $b->distanceInKilometers);

        // Apply limit
        $limitedResults = array_slice($results, 0, $request->limit);

        return new SearchParkingByLocationResponse(
            $limitedResults,
            count($results)
        );
    }

    private function validateRequest(SearchParkingByLocationRequest $request): void
    {
        if ($request->latitude < -90 || $request->latitude > 90) {
            throw new \InvalidArgumentException('Invalid latitude');
        }

        if ($request->longitude < -180 || $request->longitude > 180) {
            throw new \InvalidArgumentException('Invalid longitude');
        }

        if ($request->radiusInKilometers <= 0 || $request->radiusInKilometers > 100) {
            throw new \InvalidArgumentException('Radius must be between 0 and 100 kilometers');
        }

        if ($request->startTime && $request->endTime && $request->startTime >= $request->endTime) {
            throw new \InvalidArgumentException('Start time must be before end time');
        }

        if ($request->maxHourlyRate !== null && $request->maxHourlyRate < 0) {
            throw new \InvalidArgumentException('Max hourly rate cannot be negative');
        }

        if ($request->minimumSpaces !== null && $request->minimumSpaces < 1) {
            throw new \InvalidArgumentException('Minimum spaces must be at least 1');
        }

        if ($request->limit < 1 || $request->limit > 50) {
            throw new \InvalidArgumentException('Limit must be between 1 and 50');
        }
    }

    private function passesFilters($parking, SearchParkingByLocationRequest $request, \DateTimeInterface $searchTime): bool
    {
        // Rate filter
        if ($request->maxHourlyRate !== null && $parking->getHourlyRate() > $request->maxHourlyRate) {
            return false;
        }

        // Opening hours filter
        if (!$parking->isOpenAt($searchTime)) {
            return false;
        }

        // If time range specified, check availability during that period
        if ($request->startTime && $request->endTime) {
            $minimumRequired = $request->minimumSpaces ?? 1;
            
            if (!$this->availabilityChecker->hasAvailableSpacesDuring(
                $parking->getId(),
                $request->startTime,
                $request->endTime,
                $minimumRequired
            )) {
                return false;
            }
        }

        return true;
    }

    private function getAvailableSpacesForTimeRange($parking, ?\DateTimeInterface $startTime, ?\DateTimeInterface $endTime): int
    {
        $checkTime = $startTime ?? new \DateTimeImmutable();
        
        return $this->availabilityChecker->getAvailableSpaces(
            $parking->getId(),
            $checkTime
        );
    }
}