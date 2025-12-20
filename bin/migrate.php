<?php

declare(strict_types=1);

/**
 * Database Migration CLI
 * Usage: php bin/migrate.php [command] [options]
 *
 * Commands:
 *   up        - Run all pending migrations
 *   down      - Rollback all migrations
 *   rollback  - Rollback the last migration
 *   status    - Show migration status
 */

require_once __DIR__ . '/../vendor/autoload.php';

if (file_exists(__DIR__ . '/../.env')) {
    $lines = file(__DIR__ . '/../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (empty($line) || strpos(trim($line), '#') === 0) {
            continue;
        }
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value);
            putenv(trim($key) . '=' . trim($value));
        }
    }
}

use ParkingSystem\Infrastructure\Migration\MigrationRunner;
use ParkingSystem\Infrastructure\Migration\Version20250101000001_CreateUsersTable;
use ParkingSystem\Infrastructure\Migration\Version20250101000002_CreateParkingOwnersTable;
use ParkingSystem\Infrastructure\Migration\Version20250101000003_CreateParkingsTable;
use ParkingSystem\Infrastructure\Migration\Version20250101000004_CreateReservationsTable;
use ParkingSystem\Infrastructure\Migration\Version20250101000005_CreateParkingSessionsTable;
use ParkingSystem\Infrastructure\Migration\Version20250101000006_CreateSubscriptionsTable;

// Configuration - modify these values for your environment
$config = [
    'host' => getenv('DB_HOST') ?: 'localhost',
    'port' => getenv('DB_PORT') ?: '3306',
    'database' => getenv('DB_DATABASE') ?: 'parking_system',
    'username' => getenv('DB_USERNAME') ?: 'root',
    'password' => getenv('DB_PASSWORD') ?: ''
];

$command = $argv[1] ?? 'status';
$option = $argv[2] ?? null;

try {
    // Create PDO connection
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        $config['host'],
        $config['port'],
        $config['database']
    );

    $pdo = new PDO(
        $dsn,
        $config['username'],
        $config['password'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );

    // Initialize migration runner
    $runner = new MigrationRunner($pdo);

    // Register all migrations
    $runner->registerMigrations([
        new Version20250101000001_CreateUsersTable(),
        new Version20250101000002_CreateParkingOwnersTable(),
        new Version20250101000003_CreateParkingsTable(),
        new Version20250101000004_CreateReservationsTable(),
        new Version20250101000005_CreateParkingSessionsTable(),
        new Version20250101000006_CreateSubscriptionsTable()
    ]);

    // Execute command
    switch ($command) {
        case 'up':
            echo "Running pending migrations...\n\n";
            $executed = $runner->migrateUp($option);

            if (empty($executed)) {
                echo "No pending migrations.\n";
            } else {
                foreach ($executed as $migration) {
                    echo "✓ {$migration['version']}: {$migration['description']}\n";
                }
                echo "\n" . count($executed) . " migration(s) executed.\n";
            }
            break;

        case 'down':
            echo "Rolling back all migrations...\n\n";
            $rolledBack = $runner->migrateDown($option);

            if (empty($rolledBack)) {
                echo "No migrations to rollback.\n";
            } else {
                foreach ($rolledBack as $migration) {
                    echo "✗ {$migration['version']}: {$migration['description']}\n";
                }
                echo "\n" . count($rolledBack) . " migration(s) rolled back.\n";
            }
            break;

        case 'rollback':
            $steps = $option ? (int)$option : 1;
            echo "Rolling back {$steps} migration(s)...\n\n";
            $rolledBack = $runner->rollback($steps);

            if (empty($rolledBack)) {
                echo "No migrations to rollback.\n";
            } else {
                foreach ($rolledBack as $migration) {
                    echo "✗ {$migration['version']}: {$migration['description']}\n";
                }
            }
            break;

        case 'status':
        default:
            echo "Migration Status\n";
            echo str_repeat('=', 60) . "\n\n";

            $status = $runner->getStatus();

            foreach ($status as $migration) {
                $mark = $migration['applied'] ? '✓' : '○';
                $state = $migration['applied'] ? 'Applied' : 'Pending';
                echo "{$mark} [{$state}] {$migration['version']}: {$migration['description']}\n";
            }

            $pending = $runner->getPendingMigrations();
            echo "\n" . count($pending) . " pending migration(s).\n";
            break;
    }

} catch (PDOException $e) {
    echo "Database connection error: " . $e->getMessage() . "\n";
    echo "\nMake sure your database configuration is correct:\n";
    echo "  Host: {$config['host']}\n";
    echo "  Port: {$config['port']}\n";
    echo "  Database: {$config['database']}\n";
    echo "  Username: {$config['username']}\n";
    exit(1);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
