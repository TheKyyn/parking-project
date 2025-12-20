<?php

declare(strict_types=1);

namespace ParkingSystem\Infrastructure\Service;

use ParkingSystem\UseCase\User\PasswordHasherInterface;

/**
 * Password hasher using Bcrypt
 *
 * Bcrypt is an adaptive hashing algorithm that:
 * - Resists brute force attacks
 * - Automatically integrates a salt
 * - Can increase its complexity over time
 */
class BcryptPasswordHasher implements PasswordHasherInterface
{
    private const ALGORITHM = PASSWORD_BCRYPT;
    private const DEFAULT_COST = 12;

    private int $cost;

    /**
     * @param int $cost Algorithm cost (4-31). Higher = more secure but slower.
     *                  Default: 12 (good security/performance trade-off)
     */
    public function __construct(int $cost = self::DEFAULT_COST)
    {
        if ($cost < 4 || $cost > 31) {
            throw new \InvalidArgumentException('Bcrypt cost must be between 4 and 31');
        }

        $this->cost = $cost;
    }

    /**
     * {@inheritDoc}
     */
    public function hash(string $plainPassword): string
    {
        if (empty($plainPassword)) {
            throw new \InvalidArgumentException('Password cannot be empty');
        }

        $hash = password_hash($plainPassword, self::ALGORITHM, [
            'cost' => $this->cost,
        ]);

        if ($hash === false) {
            throw new \RuntimeException('Failed to hash password');
        }

        return $hash;
    }

    /**
     * {@inheritDoc}
     */
    public function verify(string $plainPassword, string $hashedPassword): bool
    {
        return password_verify($plainPassword, $hashedPassword);
    }

    /**
     * Check if a hash needs to be rehashed (outdated algorithm)
     *
     * @param string $hashedPassword Hash to verify
     * @return bool True if needs rehashing
     */
    public function needsRehash(string $hashedPassword): bool
    {
        return password_needs_rehash($hashedPassword, self::ALGORITHM, [
            'cost' => $this->cost,
        ]);
    }
}
