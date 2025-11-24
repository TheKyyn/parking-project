<?php

declare(strict_types=1);

namespace ParkingSystem\Domain\Entity;

/**
 * ParkingOwner Entity
 * Domain Layer - Pure business entity with NO external dependencies
 */
class ParkingOwner
{
    private string $id;
    private string $email;
    private string $passwordHash;
    private string $firstName;
    private string $lastName;
    private array $ownedParkings;
    private \DateTimeImmutable $createdAt;

    public function __construct(
        string $id,
        string $email,
        string $passwordHash,
        string $firstName,
        string $lastName,
        ?\DateTimeImmutable $createdAt = null
    ) {
        $this->validateEmail($email);
        $this->validateName($firstName);
        $this->validateName($lastName);
        
        $this->id = $id;
        $this->email = strtolower(trim($email));
        $this->passwordHash = $passwordHash;
        $this->firstName = trim($firstName);
        $this->lastName = trim($lastName);
        $this->ownedParkings = [];
        $this->createdAt = $createdAt ?? new \DateTimeImmutable();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getPasswordHash(): string
    {
        return $this->passwordHash;
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    public function getFullName(): string
    {
        return $this->firstName . ' ' . $this->lastName;
    }

    public function getOwnedParkings(): array
    {
        return $this->ownedParkings;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function changePassword(string $newPasswordHash): void
    {
        $this->passwordHash = $newPasswordHash;
    }

    public function updateProfile(string $firstName, string $lastName): void
    {
        $this->validateName($firstName);
        $this->validateName($lastName);
        
        $this->firstName = trim($firstName);
        $this->lastName = trim($lastName);
    }

    public function addOwnedParking(string $parkingId): void
    {
        if (!in_array($parkingId, $this->ownedParkings, true)) {
            $this->ownedParkings[] = $parkingId;
        }
    }

    public function removeOwnedParking(string $parkingId): void
    {
        $index = array_search($parkingId, $this->ownedParkings, true);
        if ($index !== false) {
            unset($this->ownedParkings[$index]);
            $this->ownedParkings = array_values($this->ownedParkings);
        }
    }

    private function validateEmail(string $email): void
    {
        if (empty(trim($email))) {
            throw new \InvalidArgumentException('Email cannot be empty');
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Invalid email format');
        }
    }

    private function validateName(string $name): void
    {
        if (empty(trim($name))) {
            throw new \InvalidArgumentException('Name cannot be empty');
        }
        
        if (strlen(trim($name)) < 2) {
            throw new \InvalidArgumentException('Name must be at least 2 characters');
        }
    }
}