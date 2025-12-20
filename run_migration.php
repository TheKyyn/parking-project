<?php

require_once __DIR__ . '/vendor/autoload.php';

use ParkingSystem\Infrastructure\Repository\MySQL\MySQLConnection;

try {
    // Read .env file manually (no Dotenv dependency needed)
    $envFile = __DIR__ . '/.env';
    if (file_exists($envFile)) {
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) continue;
            if (strpos($line, '=') === false) continue;
            list($key, $value) = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value);
        }
    }

    // Create database connection (correct parameter order: host, database, username, password, port)
    $connection = new MySQLConnection(
        $_ENV['DB_HOST'] ?? 'localhost',
        $_ENV['DB_NAME'] ?? 'parking_system',
        $_ENV['DB_USERNAME'] ?? 'parking_dev',
        $_ENV['DB_PASSWORD'] ?? '',
        (int)($_ENV['DB_PORT'] ?? 3306)
    );

    $pdo = $connection->getConnection();

    echo "Running migration: add_available_spots.sql\n";

    // Read SQL file
    $sql = file_get_contents(__DIR__ . '/database/migrations/add_available_spots.sql');

    // Execute each statement
    $statements = array_filter(array_map('trim', explode(';', $sql)));

    foreach ($statements as $statement) {
        if (empty($statement) || strpos($statement, '--') === 0) {
            continue;
        }

        echo "Executing: " . substr($statement, 0, 50) . "...\n";
        $pdo->exec($statement);
    }

    echo "✅ Migration completed successfully!\n";

    // Verify
    $stmt = $pdo->query("DESCRIBE parkings");
    echo "\nParkings table structure:\n";
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "  {$row['Field']}: {$row['Type']}\n";
    }

} catch (Exception $e) {
    echo "❌ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
