<?php

declare(strict_types=1);

namespace ParkingSystem\Infrastructure\Migration;

/**
 * Version20250101000001_CreateUsersTable
 * Creates the users table
 */
class Version20250101000001_CreateUsersTable implements MigrationInterface
{
    public function getVersion(): string
    {
        return '20250101000001';
    }

    public function getDescription(): string
    {
        return 'Create users table';
    }

    public function up(\PDO $connection): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS users (
            id VARCHAR(36) PRIMARY KEY,
            email VARCHAR(255) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            first_name VARCHAR(100) NOT NULL,
            last_name VARCHAR(100) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_users_email (email),
            INDEX idx_users_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $connection->exec($sql);
    }

    public function down(\PDO $connection): void
    {
        $connection->exec("DROP TABLE IF EXISTS users");
    }
}
