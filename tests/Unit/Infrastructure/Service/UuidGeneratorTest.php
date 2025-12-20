<?php

declare(strict_types=1);

namespace ParkingSystem\Tests\Unit\Infrastructure\Service;

use ParkingSystem\Infrastructure\Service\UuidGenerator;
use PHPUnit\Framework\TestCase;

class UuidGeneratorTest extends TestCase
{
    private UuidGenerator $generator;

    protected function setUp(): void
    {
        $this->generator = new UuidGenerator();
    }

    public function testGeneratesValidUuidV4Format(): void
    {
        $uuid = $this->generator->generate();

        // Format: xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx
        $pattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';

        $this->assertMatchesRegularExpression($pattern, $uuid);
    }

    public function testGeneratesUniqueIds(): void
    {
        $uuid1 = $this->generator->generate();
        $uuid2 = $this->generator->generate();
        $uuid3 = $this->generator->generate();

        $this->assertNotEquals($uuid1, $uuid2);
        $this->assertNotEquals($uuid2, $uuid3);
        $this->assertNotEquals($uuid1, $uuid3);
    }

    public function testGenerates1000UniqueIds(): void
    {
        $uuids = [];

        for ($i = 0; $i < 1000; $i++) {
            $uuids[] = $this->generator->generate();
        }

        $uniqueUuids = array_unique($uuids);

        $this->assertCount(1000, $uniqueUuids, 'All generated UUIDs should be unique');
    }

    public function testUuidHasCorrectVersion(): void
    {
        $uuid = $this->generator->generate();

        // Version is at character 14 (index 14)
        // For UUID v4, it must be '4'
        $version = $uuid[14];

        $this->assertEquals('4', $version, 'UUID should be version 4');
    }

    public function testUuidHasCorrectVariant(): void
    {
        $uuid = $this->generator->generate();

        // Variant is at character 19 (index 19)
        // For RFC 4122, it must be '8', '9', 'a' or 'b'
        $variant = strtolower($uuid[19]);

        $this->assertContains($variant, ['8', '9', 'a', 'b'], 'UUID should have RFC 4122 variant');
    }
}
