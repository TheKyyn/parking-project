<?php

require_once __DIR__ . '/vendor/autoload.php';

use ParkingSystem\Infrastructure\Service\UuidGenerator;
use ParkingSystem\Infrastructure\Service\BcryptPasswordHasher;
use ParkingSystem\Infrastructure\Service\FirebaseJwtTokenGenerator;
use ParkingSystem\Infrastructure\Http\Middleware\JwtAuthMiddleware;

echo "🧪 Test de l'Infrastructure d'Authentification\n\n";

// 1. Test UUID Generator
echo "1️⃣ UUID Generator:\n";
$uuidGen = new UuidGenerator();
$uuid = $uuidGen->generate();
echo "   ✅ UUID généré: $uuid\n\n";

// 2. Test Password Hasher
echo "2️⃣ Password Hasher:\n";
$hasher = new BcryptPasswordHasher();
$password = 'my_secure_password';
$hash = $hasher->hash($password);
echo "   ✅ Hash généré: " . substr($hash, 0, 30) . "...\n";
echo "   ✅ Vérification: " . ($hasher->verify($password, $hash) ? 'OK' : 'FAIL') . "\n\n";

// 3. Test JWT Token Generator
echo "3️⃣ JWT Token Generator:\n";
$jwtGen = new FirebaseJwtTokenGenerator('my_super_secret_key_at_least_32_characters');
$payload = [
    'userId' => 'user-123',
    'email' => 'user@test.com',
    'iat' => time(),
    'exp' => time() + 3600,
];
$token = $jwtGen->generate($payload, 3600);
echo "   ✅ Token généré: " . substr($token, 0, 50) . "...\n";
$decodedPayload = $jwtGen->verify($token);
echo "   ✅ Token validé - userId: {$decodedPayload['userId']}, email: {$decodedPayload['email']}\n\n";

// 4. Test Auth Middleware
echo "4️⃣ Auth Middleware:\n";
$middleware = new JwtAuthMiddleware($jwtGen);
$headers = ['Authorization' => 'Bearer ' . $token];
$user = $middleware->authenticate($headers);
echo "   ✅ Authentification réussie - userId: {$user['userId']}\n\n";

echo "🎉 Tous les tests manuels sont passés !\n";
