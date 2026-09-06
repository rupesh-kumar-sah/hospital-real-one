<?php
/**
 * REST API — /api/v1/admin/pricing
 * GET: Lists service pricing master catalog
 * POST: Adds/updates service pricing items
 */

require_once __DIR__ . '/../../../includes/api_middleware.php';

$auth = requireApiAuth(['admin', 'receptionist']);
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $stmt = $db->query("SELECT * FROM service_pricing WHERE status = 'active' ORDER BY category ASC, service_name ASC");
        jsonSuccess($stmt->fetchAll(), 'Service pricing catalog retrieved');
    } catch (\Throwable $e) {
        jsonError('Failed to fetch pricing: ' . $e->getMessage(), 500);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $auth = requireApiAuth('admin');
    $body = getJsonBody();
    
    $serviceName = trim($body['service_name'] ?? '');
    $category = trim($body['category'] ?? 'General');
    $price = (float)($body['price'] ?? 0);
    $description = trim($body['description'] ?? '');
    
    if (empty($serviceName) || $price <= 0) {
        jsonError('service_name and valid price are required.', 422);
    }
    
    try {
        $stmt = $db->prepare("INSERT INTO service_pricing (service_name, category, price, description, status, created_at) VALUES (?, ?, ?, ?, 'active', CURRENT_TIMESTAMP)");
        $stmt->execute([$serviceName, $category, $price, $description]);
        
        jsonSuccess(['id' => (int)$db->lastInsertId(), 'service_name' => $serviceName], 'Service pricing added', 201);
    } catch (\Throwable $e) {
        jsonError('Failed to add service pricing: ' . $e->getMessage(), 500);
    }
}

jsonError('Method Not Allowed', 405);
