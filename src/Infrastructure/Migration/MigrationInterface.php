<?php

declare(strict_types=1);

namespace ParkingSystem\Infrastructure\Migration;

/**
 * MigrationInterface
 * Infrastructure Layer - Contract for database migrations
 */
interface MigrationInterface
{
    /**
     * Get migration version identifier
     */
    public function getVersion(): string;

    /**
     * Get migration description
     */
    public function getDescription(): string;

    /**
     * Apply the migration (migrate up)
     */
    public function up(\PDO $connection): void;

    /**
     * Revert the migration (rollback)
     */
    public function down(\PDO $connection): void;
}
