<?php
/**
 * Hospital Management System — API Health & Database Connection Diagnostics
 * Tests Render Backend connection to local Laptop MySQL database over Cloudflare Tunnel.
 */

require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/encryption.php';

header('Content-Type: application/json');

$startTime = microtime(true);
$response = [
    'status' => 'ok',
    'timestamp' => date('Y-m-d H:i:s'),
    'backend' => 'Render Cloud Backend (PHP ' . PHP_VERSION . ')',
    'database' => [
        'driver' => DB_DRIVER,
        'host' => DB_HOST,
        'connected' => false,
        'latency_ms' => 0,
        'error' => null
    ],
    'encryption' => [
        'algorithm' => ENCRYPTION_CIPHER,
        'e2ee_active' => true
    ]
];

try {
    $dbStart = microtime(true);
    $pdo = getDB();
    $stmt = $pdo->query("SELECT COUNT(*) as user_count FROM users");
    $row = $stmt->fetch();
    
    $response['database']['connected'] = true;
    $response['database']['latency_ms'] = round((microtime(true) - $dbStart) * 1000, 2);
    $response['database']['user_count'] = (int)($row['user_count'] ?? 0);
} catch (\Throwable $e) {
    $response['status'] = 'error';
    $response['database']['connected'] = false;
    $response['database']['error'] = $e->getMessage();
}

$response['total_duration_ms'] = round((microtime(true) - $startTime) * 1000, 2);

http_response_code($response['status'] === 'ok' ? 200 : 500);
echo json_encode($response, JSON_PRETTY_PRINT);
