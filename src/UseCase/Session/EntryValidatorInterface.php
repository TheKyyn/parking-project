<?php

declare(strict_types=1);

namespace ParkingSystem\UseCase\Session;

/**
 * EntryValidatorInterface
 * Use Case Layer - Contract for validating parking entry authorization
 */
interface EntryValidatorInterface
{
    /**
     * Check if user has active reservation for the parking at given time
     */
    public function hasActiveReservation(
        string $userId,
        string $parkingId,
        \DateTimeInterface $dateTime
    ): bool;

    /**
     * Check if user has active subscription covering the parking at given time
     */
    public function hasActiveSubscription(
        string $userId,
        string $parkingId,
        \DateTimeInterface $dateTime
    ): bool;

    /**
     * Get the reservation ID if user has active reservation
     */
    public function getActiveReservationId(
        string $userId,
        string $parkingId,
        \DateTimeInterface $dateTime
    ): ?string;

    /**
     * Get authorized end time based on reservation or subscription
     */
    public function getAuthorizedEndTime(
        string $userId,
        string $parkingId,
        \DateTimeInterface $dateTime
    ): ?\DateTimeInterface;
}
