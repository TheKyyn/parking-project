<?php

declare(strict_types=1);

namespace ParkingSystem\Infrastructure\Migration;

/**
 * Version20250101000004_CreateReservationsTable
 * Creates the reservations table
 */
class Version20250101000004_CreateReservationsTable implements MigrationInterface
{
    public function getVersion(): string
    {
        return '20250101000004';
    }

    public function getDescription(): string
    {
        return 'Create reservations table';
    }

    public function up(\PDO $connection): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS reservations (
            id VARCHAR(36) PRIMARY KEY,
            user_id VARCHAR(36) NOT NULL,
            parking_id VARCHAR(36) NOT NULL,
            start_time DATETIME NOT NULL,
            end_time DATETIME NOT NULL,
            total_amount DECIMAL(10, 2) NOT NULL,
            status ENUM('pending', 'confirmed', 'cancelled', 'completed') NOT NULL DEFAULT 'pending',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_reservations_user_id (user_id),
            INDEX idx_reservations_parking_id (parking_id),
            INDEX idx_reservations_status (status),
            INDEX idx_reservations_start_time (start_time),
            INDEX idx_reservations_time_range (parking_id, start_time, end_time),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (parking_id) REFERENCES parkings(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $connection->exec($sql);
    }

    public function down(\PDO $connection): void
    {
        $connection->exec("DROP TABLE IF EXISTS reservations");
    }
}
