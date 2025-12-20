<?php

declare(strict_types=1);

/**
 * Database Seeder
 * Populates the database with test data for development
 */

require_once __DIR__ . '/../vendor/autoload.php';

// Load environment variables from .env
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

try {
    // Connect to database
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        $_ENV['DB_HOST'] ?? 'localhost',
        $_ENV['DB_PORT'] ?? '3306',
        $_ENV['DB_NAME'] ?? 'parking_system'
    );

    $pdo = new PDO(
        $dsn,
        $_ENV['DB_USERNAME'] ?? 'parking_dev',
        $_ENV['DB_PASSWORD'] ?? '',
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );

    echo "🔄 Starting database seeding...\n\n";

    // 1. Create test parking owners
    echo "📝 Creating parking owners...\n";

    $owners = [
        [
            'id' => 'owner-001',
            'email' => 'owner1@parking.com',
            'password_hash' => password_hash('password123', PASSWORD_BCRYPT),
            'first_name' => 'Jean',
            'last_name' => 'Dupont'
        ],
        [
            'id' => 'owner-002',
            'email' => 'owner2@parking.com',
            'password_hash' => password_hash('password123', PASSWORD_BCRYPT),
            'first_name' => 'Marie',
            'last_name' => 'Martin'
        ],
        [
            'id' => 'owner-003',
            'email' => 'owner3@parking.com',
            'password_hash' => password_hash('password123', PASSWORD_BCRYPT),
            'first_name' => 'Pierre',
            'last_name' => 'Durand'
        ]
    ];

    $stmt = $pdo->prepare("
        INSERT INTO parking_owners (id, email, password_hash, first_name, last_name, created_at)
        VALUES (:id, :email, :password_hash, :first_name, :last_name, NOW())
        ON DUPLICATE KEY UPDATE email = VALUES(email)
    ");

    foreach ($owners as $owner) {
        $stmt->execute($owner);
    }

    echo "   ✅ Created " . count($owners) . " parking owners\n\n";

    // 2. Create test parkings
    echo "📝 Creating parkings...\n";

    $parkings = [
        [
            'id' => 'parking-paris-001',
            'owner_id' => 'owner-001',
            'name' => 'Parking Paris Centre',
            'address' => '1 Place de la Concorde, 75008 Paris',
            'latitude' => 48.8566,
            'longitude' => 2.3522,
            'total_spaces' => 100,
            'available_spots' => 100,
            'hourly_rate' => 3.50,
            'opening_hours' => json_encode([
                '1' => ['open' => '06:00', 'close' => '23:00'],
                '2' => ['open' => '06:00', 'close' => '23:00'],
                '3' => ['open' => '06:00', 'close' => '23:00'],
                '4' => ['open' => '06:00', 'close' => '23:00'],
                '5' => ['open' => '06:00', 'close' => '23:00'],
                '6' => ['open' => '08:00', 'close' => '20:00'],
                '0' => ['open' => '09:00', 'close' => '18:00']
            ])
        ],
        [
            'id' => 'parking-lyon-001',
            'owner_id' => 'owner-002',
            'name' => 'Parking Lyon Gare',
            'address' => '5 Place Charles Beraudier, 69003 Lyon',
            'latitude' => 45.7640,
            'longitude' => 4.8357,
            'total_spaces' => 50,
            'available_spots' => 50,
            'hourly_rate' => 2.80,
            'opening_hours' => json_encode([])
        ],
        [
            'id' => 'parking-marseille-001',
            'owner_id' => 'owner-002',
            'name' => 'Parking Marseille Vieux Port',
            'address' => '34 Quai du Port, 13002 Marseille',
            'latitude' => 43.2965,
            'longitude' => 5.3698,
            'total_spaces' => 75,
            'available_spots' => 75,
            'hourly_rate' => 2.50,
            'opening_hours' => json_encode([])
        ],
        [
            'id' => 'parking-toulouse-001',
            'owner_id' => 'owner-003',
            'name' => 'Parking Toulouse Centre',
            'address' => '12 Place du Capitole, 31000 Toulouse',
            'latitude' => 43.6047,
            'longitude' => 1.4442,
            'total_spaces' => 30,
            'available_spots' => 30,
            'hourly_rate' => 2.00,
            'opening_hours' => json_encode([])
        ],
        [
            'id' => 'parking-nice-001',
            'owner_id' => 'owner-003',
            'name' => 'Parking Nice Promenade',
            'address' => '15 Promenade des Anglais, 06000 Nice',
            'latitude' => 43.7102,
            'longitude' => 7.2620,
            'total_spaces' => 40,
            'available_spots' => 40,
            'hourly_rate' => 4.00,
            'opening_hours' => json_encode([])
        ]
    ];

    $stmt = $pdo->prepare("
        INSERT INTO parkings (id, owner_id, name, address, latitude, longitude, total_spaces, available_spots, hourly_rate, opening_hours, created_at)
        VALUES (:id, :owner_id, :name, :address, :latitude, :longitude, :total_spaces, :available_spots, :hourly_rate, :opening_hours, NOW())
        ON DUPLICATE KEY UPDATE name = VALUES(name), address = VALUES(address), total_spaces = VALUES(total_spaces), available_spots = VALUES(available_spots), hourly_rate = VALUES(hourly_rate)
    ");

    foreach ($parkings as $parking) {
        $stmt->execute($parking);
    }

    echo "   ✅ Created " . count($parkings) . " parkings\n\n";

    // 3. Create test users
    echo "📝 Creating users...\n";

    $users = [
        [
            'id' => 'user-001',
            'email' => 'alice@example.com',
            'password_hash' => password_hash('password123', PASSWORD_BCRYPT),
            'first_name' => 'Alice',
            'last_name' => 'Dubois'
        ],
        [
            'id' => 'user-002',
            'email' => 'bob@example.com',
            'password_hash' => password_hash('password123', PASSWORD_BCRYPT),
            'first_name' => 'Bob',
            'last_name' => 'Lefebvre'
        ],
        [
            'id' => 'user-003',
            'email' => 'charlie@example.com',
            'password_hash' => password_hash('password123', PASSWORD_BCRYPT),
            'first_name' => 'Charlie',
            'last_name' => 'Moreau'
        ]
    ];

    $stmt = $pdo->prepare("
        INSERT INTO users (id, email, password_hash, first_name, last_name, created_at)
        VALUES (:id, :email, :password_hash, :first_name, :last_name, NOW())
        ON DUPLICATE KEY UPDATE email = VALUES(email)
    ");

    foreach ($users as $user) {
        $stmt->execute($user);
    }

    echo "   ✅ Created " . count($users) . " users\n\n";

    // 4. Show summary
    echo "═══════════════════════════════════════════════════════\n";
    echo "✅ Database seeding completed successfully!\n";
    echo "═══════════════════════════════════════════════════════\n\n";

    echo "📊 Summary:\n";
    echo "   - " . count($owners) . " parking owners\n";
    echo "   - " . count($parkings) . " parkings\n";
    echo "   - " . count($users) . " users\n\n";

    echo "🔐 Test Credentials:\n";
    echo "   Users: alice@example.com / password123\n";
    echo "   Owners: owner1@parking.com / password123\n\n";

    echo "🗺️  Test Parkings:\n";
    echo "   - Paris Centre (100 spaces, €3.50/h)\n";
    echo "   - Lyon Gare (50 spaces, €2.80/h)\n";
    echo "   - Marseille Vieux Port (75 spaces, €2.50/h)\n";
    echo "   - Toulouse Centre (30 spaces, €2.00/h)\n";
    echo "   - Nice Promenade (40 spaces, €4.00/h)\n\n";

} catch (PDOException $e) {
    echo "❌ Database error: " . $e->getMessage() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
