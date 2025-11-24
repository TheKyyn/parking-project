<?php

declare(strict_types=1);

/**
 * Entry point for the Shared Parking System
 * Following Clean Architecture principles
 */

require_once __DIR__ . '/../vendor/autoload.php';

// Basic router will be implemented in Infrastructure layer
// For now, show architecture status

header('Content-Type: application/json');
http_response_code(200);

echo json_encode([
    'message' => 'Shared Parking System - Clean Architecture',
    'status' => 'Project structure initialized',
    'timestamp' => date('Y-m-d H:i:s'),
    'architecture' => [
        'domain' => 'Pure business entities (no dependencies)',
        'usecases' => 'Business logic implementation',
        'infrastructure' => 'Controllers, repositories, external services'
    ]
]);
