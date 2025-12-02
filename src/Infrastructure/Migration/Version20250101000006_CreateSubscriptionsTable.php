<?php

declare(strict_types=1);

namespace ParkingSystem\Infrastructure\Migration;

/**
 * Version20250101000006_CreateSubscriptionsTable
 * Creates the subscriptions table
 */
class Version20250101000006_CreateSubscriptionsTable implements MigrationInterface
{
    public function getVersion(): string
    {
        return '20250101000006';
    }

    public function getDescription(): string
    {
        return 'Create subscriptions table';
    }

    public function up(\PDO $connection): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS subscriptions (
            id VARCHAR(36) PRIMARY KEY,
            user_id VARCHAR(36) NOT NULL,
            parking_id VARCHAR(36) NOT NULL,
            weekly_time_slots JSON NOT NULL,
            duration_months INT NOT NULL,
            start_date DATE NOT NULL,
            end_date DATE NOT NULL,
            monthly_amount DECIMAL(10, 2) NOT NULL,
            status ENUM('active', 'expired', 'cancelled') NOT NULL DEFAULT 'active',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_subscriptions_user_id (user_id),
            INDEX idx_subscriptions_parking_id (parking_id),
            INDEX idx_subscriptions_status (status),
            INDEX idx_subscriptions_dates (start_date, end_date),
            INDEX idx_subscriptions_active (parking_id, status, start_date, end_date),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (parking_id) REFERENCES parkings(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $connection->exec($sql);
    }

    public function down(\PDO $connection): void
    {
        $connection->exec("DROP TABLE IF EXISTS subscriptions");
    }
}
