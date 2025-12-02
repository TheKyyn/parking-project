<?php

declare(strict_types=1);

namespace ParkingSystem\Tests\Unit\Infrastructure\Service;

use ParkingSystem\Infrastructure\Service\BcryptPasswordHasher;
use PHPUnit\Framework\TestCase;

class BcryptPasswordHasherTest extends TestCase
{
    private BcryptPasswordHasher $hasher;

    protected function setUp(): void
    {
        $this->hasher = new BcryptPasswordHasher();
    }

    public function testHashesPassword(): void
    {
        $plainPassword = 'my_secure_password_123';
        $hash = $this->hasher->hash($plainPassword);

        $this->assertNotEquals($plainPassword, $hash);
        $this->assertStringStartsWith('$2y$', $hash); // Bcrypt identifier
    }

    public function testVerifiesCorrectPassword(): void
    {
        $plainPassword = 'my_secure_password_123';
        $hash = $this->hasher->hash($plainPassword);

        $this->assertTrue($this->hasher->verify($plainPassword, $hash));
    }

    public function testRejectsIncorrectPassword(): void
    {
        $plainPassword = 'my_secure_password_123';
        $wrongPassword = 'wrong_password';
        $hash = $this->hasher->hash($plainPassword);

        $this->assertFalse($this->hasher->verify($wrongPassword, $hash));
    }

    public function testGeneratesDifferentHashesForSamePassword(): void
    {
        $plainPassword = 'my_secure_password_123';
        $hash1 = $this->hasher->hash($plainPassword);
        $hash2 = $this->hasher->hash($plainPassword);

        // Hashes must be different (random salt)
        $this->assertNotEquals($hash1, $hash2);

        // But both must verify the same password
        $this->assertTrue($this->hasher->verify($plainPassword, $hash1));
        $this->assertTrue($this->hasher->verify($plainPassword, $hash2));
    }

    public function testThrowsExceptionForEmptyPassword(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Password cannot be empty');

        $this->hasher->hash('');
    }

    public function testThrowsExceptionForInvalidCost(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Bcrypt cost must be between 4 and 31');

        new BcryptPasswordHasher(3); // Too low
    }

    public function testThrowsExceptionForInvalidCostTooHigh(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Bcrypt cost must be between 4 and 31');

        new BcryptPasswordHasher(32); // Too high
    }

    public function testNeedsRehashReturnsFalseForCurrentAlgorithm(): void
    {
        $plainPassword = 'my_secure_password_123';
        $hash = $this->hasher->hash($plainPassword);

        $this->assertFalse($this->hasher->needsRehash($hash));
    }

    public function testNeedsRehashReturnsTrueForDifferentCost(): void
    {
        // Hash with cost = 4
        $hasherLowCost = new BcryptPasswordHasher(4);
        $hash = $hasherLowCost->hash('password123');

        // Verify with cost = 12 (default)
        $hasherHighCost = new BcryptPasswordHasher(12);

        $this->assertTrue($hasherHighCost->needsRehash($hash));
    }

    public function testHandlesSpecialCharactersInPassword(): void
    {
        $plainPassword = 'p@ssw0rd!#$%^&*()_+-=[]{}|;:",.<>?/~`';
        $hash = $this->hasher->hash($plainPassword);

        $this->assertTrue($this->hasher->verify($plainPassword, $hash));
    }

    public function testHandlesUnicodePassword(): void
    {
        $plainPassword = 'مرحبا_世界_🔒_пароль';
        $hash = $this->hasher->hash($plainPassword);

        $this->assertTrue($this->hasher->verify($plainPassword, $hash));
    }

    public function testHashLengthIsConsistent(): void
    {
        $hash1 = $this->hasher->hash('short');
        $hash2 = $this->hasher->hash('this_is_a_very_long_password_with_many_characters_1234567890');

        $this->assertEquals(60, strlen($hash1)); // Bcrypt always produces 60 characters
        $this->assertEquals(60, strlen($hash2));
    }
}
