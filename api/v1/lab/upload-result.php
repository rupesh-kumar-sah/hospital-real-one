<?php
/**
 * REST API — POST /api/v1/lab/upload-result
 * Uploads quantitative/qualitative lab findings and publishes final report.
 */

require_once __DIR__ . '/../../../includes/api_middleware.php';

$auth = requireApiAuth(['lab_technician', 'admin']);
$technicianUserId = (int)$auth['sub'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method Not Allowed. POST required.', 405);
}

$body = getJsonBody();
$orderId = (int)($body['order_id'] ?? 0);
$resultValue = trim($body['result_value'] ?? '');
$resultUnit = trim($body['result_unit'] ?? '');
$referenceRange = trim($body['reference_range'] ?? '');
$interpretation = trim($body['interpretation'] ?? 'normal'); // normal, high, low, abnormal
$notes = trim($body['result_notes'] ?? '');

if ($orderId <= 0 || empty($resultValue)) {
    jsonError('order_id and result_value are required.', 422);
}

try {
    $db = getDB();
    
    $stmtOrder = $db->prepare("SELECT id, patient_id, test_id FROM lab_orders WHERE id = ?");
    $stmtOrder->execute([$orderId]);
    $order = $stmtOrder->fetch();
    
    if (!$order) {
        jsonError('Lab order not found.', 404);
    }
    
    $db->beginTransaction();
    
    // Insert or update result
    $stmtRes = $db->prepare("
        INSERT INTO lab_results (lab_order_id, result_value, result_unit, reference_range, interpretation, result_notes, technician_id, verified_by, verified_at, uploaded_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ON DUPLICATE KEY UPDATE
            result_value = VALUES(result_value),
            result_unit = VALUES(result_unit),
            reference_range = VALUES(reference_range),
            interpretation = VALUES(interpretation),
            result_notes = VALUES(result_notes),
            technician_id = VALUES(technician_id),
            verified_by = VALUES(verified_by),
            verified_at = CURRENT_TIMESTAMP
    ");
    $stmtRes->execute([$orderId, $resultValue, $resultUnit, $referenceRange, $interpretation, $notes, $technicianUserId, $technicianUserId]);
    
    // Mark order completed
    $stmtUpd = $db->prepare("UPDATE lab_orders SET status = 'completed', completed_at = CURRENT_TIMESTAMP WHERE id = ?");
    $stmtUpd->execute([$orderId]);
    
    $db->commit();
    
    jsonSuccess([
        'order_id' => $orderId,
        'status' => 'completed',
        'interpretation' => $interpretation
    ], 'Lab result published successfully');
    
} catch (\Throwable $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    jsonServerError('Failed to upload lab result', $e);
}
