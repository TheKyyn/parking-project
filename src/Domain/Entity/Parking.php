<?php

declare(strict_types=1);

namespace ParkingSystem\Domain\Entity;

/**
 * Parking Entity
 * Domain Layer - Pure business entity with NO external dependencies
 */
class Parking
{
    private string $id;
    private string $ownerId;
    private string $name;
    private string $address;
    private float $latitude;
    private float $longitude;
    private int $totalSpaces;
    private int $availableSpots;
    private float $hourlyRate;
    private array $openingHours;
    private array $reservations;
    private array $activeParkingSessions;
    private \DateTimeImmutable $createdAt;

    public function __construct(
        string $id,
        string $ownerId,
        string $name,
        string $address,
        float $latitude,
        float $longitude,
        int $totalSpaces,
        int $availableSpots,
        float $hourlyRate,
        array $openingHours = [],
        ?\DateTimeImmutable $createdAt = null
    ) {
        $this->validateName($name);
        $this->validateAddress($address);
        $this->validateCoordinates($latitude, $longitude);
        $this->validateTotalSpaces($totalSpaces);
        $this->validateAvailableSpots($availableSpots, $totalSpaces);
        $this->validateHourlyRate($hourlyRate);

        $this->id = $id;
        $this->ownerId = $ownerId;
        $this->name = trim($name);
        $this->address = trim($address);
        $this->latitude = $latitude;
        $this->longitude = $longitude;
        $this->totalSpaces = $totalSpaces;
        $this->availableSpots = $availableSpots;
        $this->hourlyRate = $hourlyRate;
        $this->openingHours = $openingHours;
        $this->reservations = [];
        $this->activeParkingSessions = [];
        $this->createdAt = $createdAt ?? new \DateTimeImmutable();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getOwnerId(): string
    {
        return $this->ownerId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getAddress(): string
    {
        return $this->address;
    }

    public function getLatitude(): float
    {
        return $this->latitude;
    }

    public function getLongitude(): float
    {
        return $this->longitude;
    }

    public function getTotalSpaces(): int
    {
        return $this->totalSpaces;
    }

    public function getAvailableSpots(): int
    {
        return $this->availableSpots;
    }

    public function getHourlyRate(): float
    {
        return $this->hourlyRate;
    }

    public function getOpeningHours(): array
    {
        return $this->openingHours;
    }

    public function getReservations(): array
    {
        return $this->reservations;
    }

    public function getActiveParkingSessions(): array
    {
        return $this->activeParkingSessions;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updateRate(float $newHourlyRate): void
    {
        $this->validateHourlyRate($newHourlyRate);
        $this->hourlyRate = $newHourlyRate;
    }

    public function updateTotalSpaces(int $newTotalSpaces): void
    {
        $this->validateTotalSpaces($newTotalSpaces);
        $this->totalSpaces = $newTotalSpaces;
    }

    public function updateOpeningHours(array $newOpeningHours): void
    {
        $this->openingHours = $newOpeningHours;
    }

    public function updateName(string $newName): void
    {
        $this->validateName($newName);
        $this->name = trim($newName);
    }

    public function updateAddress(string $newAddress): void
    {
        $this->validateAddress($newAddress);
        $this->address = trim($newAddress);
    }

    public function reserveSpot(): void
    {
        if ($this->availableSpots <= 0) {
            throw new \DomainException('No available spots');
        }
        $this->availableSpots--;
    }

    public function releaseSpot(): void
    {
        if ($this->availableSpots >= $this->totalSpaces) {
            throw new \DomainException('Cannot release more spots than total');
        }
        $this->availableSpots++;
    }

    public function getAvailableSpacesAt(\DateTimeInterface $dateTime): int
    {
        // This method provides a simple calculation based on currently tracked sessions
        // For complete availability including reservations, use the CheckAvailability use case
        $occupiedSpaces = count($this->activeParkingSessions);
        
        return max(0, $this->totalSpaces - $occupiedSpaces);
    }

    public function isOpenAt(\DateTimeInterface $dateTime): bool
    {
        if (empty($this->openingHours)) {
            return true; // 24/7 by default
        }
        
        $dayOfWeek = (int)$dateTime->format('w'); // 0 = Sunday, 1 = Monday, etc.
        $timeOfDay = $dateTime->format('H:i');
        
        if (!isset($this->openingHours[$dayOfWeek])) {
            return false; // Closed on this day
        }
        
        $hours = $this->openingHours[$dayOfWeek];
        if (!isset($hours['open'], $hours['close'])) {
            return false;
        }
        
        return $timeOfDay >= $hours['open'] && $timeOfDay <= $hours['close'];
    }

    public function calculateDistance(float $userLatitude, float $userLongitude): float
    {
        // Haversine formula for distance calculation
        $earthRadius = 6371; // km
        
        $latDelta = deg2rad($userLatitude - $this->latitude);
        $lonDelta = deg2rad($userLongitude - $this->longitude);
        
        $a = sin($latDelta / 2) * sin($latDelta / 2) +
             cos(deg2rad($this->latitude)) * cos(deg2rad($userLatitude)) *
             sin($lonDelta / 2) * sin($lonDelta / 2);
             
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        
        return $earthRadius * $c;
    }

    private function validateCoordinates(float $latitude, float $longitude): void
    {
        if ($latitude < -90 || $latitude > 90) {
            throw new \InvalidArgumentException('Latitude must be between -90 and 90');
        }
        
        if ($longitude < -180 || $longitude > 180) {
            throw new \InvalidArgumentException('Longitude must be between -180 and 180');
        }
    }

    private function validateTotalSpaces(int $totalSpaces): void
    {
        if ($totalSpaces < 1) {
            throw new \InvalidArgumentException('Total spaces must be at least 1');
        }
    }

    private function validateAvailableSpots(int $availableSpots, int $totalSpaces): void
    {
        if ($availableSpots < 0) {
            throw new \InvalidArgumentException('Available spots cannot be negative');
        }

        if ($availableSpots > $totalSpaces) {
            throw new \InvalidArgumentException('Available spots cannot exceed total spaces');
        }
    }

    private function validateHourlyRate(float $hourlyRate): void
    {
        if ($hourlyRate < 0) {
            throw new \InvalidArgumentException('Hourly rate cannot be negative');
        }
    }

    private function validateName(string $name): void
    {
        if (empty(trim($name))) {
            throw new \InvalidArgumentException('Name cannot be empty');
        }

        if (strlen(trim($name)) < 3) {
            throw new \InvalidArgumentException('Name must be at least 3 characters');
        }
    }

    private function validateAddress(string $address): void
    {
        if (empty(trim($address))) {
            throw new \InvalidArgumentException('Address cannot be empty');
        }

        if (strlen(trim($address)) < 5) {
            throw new \InvalidArgumentException('Address must be at least 5 characters');
        }
    }
}