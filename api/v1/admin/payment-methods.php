<?php
/**
 * REST API — /api/v1/admin/payment-methods
 * GET: Lists active payment methods with Base64 QR code image URIs
 * POST: Adds/updates payment gateway (eSewa, Khalti, Fonepay) and stores Base64 QR
 */

require_once __DIR__ . '/../../../includes/api_middleware.php';

$auth = requireApiAuth(['admin', 'receptionist']);
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $stmt = $db->query("SELECT id, name, account_name, account_number, qr_image, instructions, status FROM payment_methods ORDER BY id ASC LIMIT 100");
        $methods = $stmt->fetchAll();
        jsonSuccess($methods, 'Payment methods retrieved');
    } catch (\Throwable $e) {
        jsonServerError('Failed to fetch payment methods', $e);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $auth = requireApiAuth('admin');
    $body = getJsonBody();
    
    $name = trim($body['name'] ?? '');
    $accountName = trim($body['account_name'] ?? '');
    $accountNumber = trim($body['account_number'] ?? '');
    $qrImage = trim($body['qr_image'] ?? ''); // Base64 data URI (e.g. data:image/png;base64,...)
    $instructions = trim($body['instructions'] ?? '');
    $status = $body['status'] ?? 'active';
    
    if (empty($name)) {
        jsonError('Payment method name is required.', 422);
    }
    
    try {
        $stmt = $db->prepare("
            INSERT INTO payment_methods (name, account_name, account_number, qr_image, instructions, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
        ");
        $stmt->execute([$name, $accountName, $accountNumber, $qrImage ?: null, $instructions, $status]);
        
        jsonSuccess(['id' => (int)$db->lastInsertId(), 'name' => $name], 'Payment method saved', 201);
    } catch (\Throwable $e) {
        jsonServerError('Failed to save payment method', $e);
    }
}

jsonError('Method Not Allowed', 405);
