<?php

declare(strict_types=1);

namespace ParkingSystem\UseCase\User;

/**
 * PasswordHasherInterface
 * Use Case Layer - Service contract for password hashing
 */
interface PasswordHasherInterface
{
    public function hash(string $plainPassword): string;

    public function verify(string $plainPassword, string $hashedPassword): bool;
}