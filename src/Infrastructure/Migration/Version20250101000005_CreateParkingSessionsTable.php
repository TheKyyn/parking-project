<?php

declare(strict_types=1);

namespace ParkingSystem\Infrastructure\Migration;

/**
 * Version20250101000005_CreateParkingSessionsTable
 * Creates the parking_sessions table
 */
class Version20250101000005_CreateParkingSessionsTable implements MigrationInterface
{
    public function getVersion(): string
    {
        return '20250101000005';
    }

    public function getDescription(): string
    {
        return 'Create parking_sessions table';
    }

    public function up(\PDO $connection): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS parking_sessions (
            id VARCHAR(36) PRIMARY KEY,
            user_id VARCHAR(36) NOT NULL,
            parking_id VARCHAR(36) NOT NULL,
            reservation_id VARCHAR(36) NULL,
            start_time DATETIME NOT NULL,
            end_time DATETIME NULL,
            total_amount DECIMAL(10, 2) NULL,
            status ENUM('active', 'completed', 'overstayed') NOT NULL DEFAULT 'active',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_sessions_user_id (user_id),
            INDEX idx_sessions_parking_id (parking_id),
            INDEX idx_sessions_reservation_id (reservation_id),
            INDEX idx_sessions_status (status),
            INDEX idx_sessions_start_time (start_time),
            INDEX idx_sessions_active (parking_id, status),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (parking_id) REFERENCES parkings(id) ON DELETE CASCADE,
            FOREIGN KEY (reservation_id) REFERENCES reservations(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $connection->exec($sql);
    }

    public function down(\PDO $connection): void
    {
        $connection->exec("DROP TABLE IF EXISTS parking_sessions");
    }
}
