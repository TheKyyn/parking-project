<?php

declare(strict_types=1);

namespace ParkingSystem\Infrastructure\Migration;

/**
 * Version20250101000003_CreateParkingsTable
 * Creates the parkings table
 */
class Version20250101000003_CreateParkingsTable implements MigrationInterface
{
    public function getVersion(): string
    {
        return '20250101000003';
    }

    public function getDescription(): string
    {
        return 'Create parkings table';
    }

    public function up(\PDO $connection): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS parkings (
            id VARCHAR(36) PRIMARY KEY,
            owner_id VARCHAR(36) NOT NULL,
            latitude DECIMAL(10, 8) NOT NULL,
            longitude DECIMAL(11, 8) NOT NULL,
            total_spaces INT NOT NULL,
            hourly_rate DECIMAL(10, 2) NOT NULL,
            opening_hours JSON,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_parkings_owner_id (owner_id),
            INDEX idx_parkings_location (latitude, longitude),
            INDEX idx_parkings_created_at (created_at),
            FOREIGN KEY (owner_id) REFERENCES parking_owners(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $connection->exec($sql);
    }

    public function down(\PDO $connection): void
    {
        $connection->exec("DROP TABLE IF EXISTS parkings");
    }
}
