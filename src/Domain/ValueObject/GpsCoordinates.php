<?php

declare(strict_types=1);

namespace ParkingSystem\Domain\ValueObject;

/**
 * GpsCoordinates Value Object
 * Domain Layer - Immutable value object with NO external dependencies
 */
class GpsCoordinates
{
    private float $latitude;
    private float $longitude;

    public function __construct(float $latitude, float $longitude)
    {
        $this->validateLatitude($latitude);
        $this->validateLongitude($longitude);
        
        $this->latitude = $latitude;
        $this->longitude = $longitude;
    }

    public function getLatitude(): float
    {
        return $this->latitude;
    }

    public function getLongitude(): float
    {
        return $this->longitude;
    }

    public function distanceTo(GpsCoordinates $other): float
    {
        // Haversine formula for distance calculation in kilometers
        $earthRadius = 6371;
        
        $latDelta = deg2rad($other->latitude - $this->latitude);
        $lonDelta = deg2rad($other->longitude - $this->longitude);
        
        $a = sin($latDelta / 2) * sin($latDelta / 2) +
             cos(deg2rad($this->latitude)) * cos(deg2rad($other->latitude)) *
             sin($lonDelta / 2) * sin($lonDelta / 2);
             
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        
        return $earthRadius * $c;
    }

    public function equals(GpsCoordinates $other): bool
    {
        return abs($this->latitude - $other->latitude) < 0.000001 &&
               abs($this->longitude - $other->longitude) < 0.000001;
    }

    public function toString(): string
    {
        return sprintf('%.6f,%.6f', $this->latitude, $this->longitude);
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    public function toArray(): array
    {
        return [
            'latitude' => $this->latitude,
            'longitude' => $this->longitude
        ];
    }

    public static function fromString(string $coordinates): self
    {
        $parts = explode(',', $coordinates);
        if (count($parts) !== 2) {
            throw new \InvalidArgumentException('Invalid coordinates format. Expected "latitude,longitude"');
        }
        
        return new self((float)trim($parts[0]), (float)trim($parts[1]));
    }

    public static function fromArray(array $coordinates): self
    {
        if (!isset($coordinates['latitude'], $coordinates['longitude'])) {
            throw new \InvalidArgumentException('Array must contain latitude and longitude keys');
        }
        
        return new self((float)$coordinates['latitude'], (float)$coordinates['longitude']);
    }

    private function validateLatitude(float $latitude): void
    {
        if ($latitude < -90 || $latitude > 90) {
            throw new \InvalidArgumentException(
                sprintf('Latitude must be between -90 and 90, got: %.6f', $latitude)
            );
        }
    }

    private function validateLongitude(float $longitude): void
    {
        if ($longitude < -180 || $longitude > 180) {
            throw new \InvalidArgumentException(
                sprintf('Longitude must be between -180 and 180, got: %.6f', $longitude)
            );
        }
    }
}