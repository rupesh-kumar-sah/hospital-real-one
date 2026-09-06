<?php
/**
 * REST API — POST /api/v1/lab/collect-sample
 * Acknowledges biological specimen collection and updates order status.
 */

require_once __DIR__ . '/../../../includes/api_middleware.php';

$auth = requireApiAuth(['lab_technician', 'admin']);
$technicianUserId = (int)$auth['sub'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method Not Allowed. POST required.', 405);
}

$body = getJsonBody();
$orderId = (int)($body['order_id'] ?? 0);

if ($orderId <= 0) {
    jsonError('order_id is required.', 422);
}

try {
    $db = getDB();
    
    $stmt = $db->prepare("UPDATE lab_orders SET status = 'sample_collected', sample_collected_at = CURRENT_TIMESTAMP WHERE id = ?");
    $stmt->execute([$orderId]);
    
    jsonSuccess(['order_id' => $orderId, 'status' => 'sample_collected'], 'Sample collection recorded');
} catch (\Throwable $e) {
    jsonServerError('Failed to record sample collection', $e);
}
