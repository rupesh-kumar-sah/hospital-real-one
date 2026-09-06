<?php
/**
 * REST API — GET /api/v1/patient/bills
 * Returns patient bills, line items, and active payment QR code.
 */

require_once __DIR__ . '/../../../includes/api_middleware.php';

$auth = requireApiAuth('patient');
$userId = (int)$auth['sub'];

try {
    $db = getDB();
    $stmtP = $db->prepare("SELECT id FROM patients WHERE user_id = ?");
    $stmtP->execute([$userId]);
    $patient = $stmtP->fetch();
    
    if (!$patient) {
        jsonError('Patient profile not found.', 404);
    }
    $patientId = (int)$patient['id'];
    
    $stmtBills = $db->prepare("SELECT * FROM billing WHERE patient_id = ? ORDER BY created_at DESC LIMIT 100");
    $stmtBills->execute([$patientId]);
    $bills = $stmtBills->fetchAll();
    
    // Fetch all line items in one query instead of one query per bill.
    $itemsByBill = [];
    if ($bills) {
        $placeholders = implode(',', array_fill(0, count($bills), '?'));
        $itemStmt = $db->prepare(
            "SELECT * FROM billing_items WHERE bill_id IN ({$placeholders}) ORDER BY bill_id, id"
        );
        $itemStmt->execute(array_column($bills, 'id'));
        foreach ($itemStmt->fetchAll() as $item) {
            $itemsByBill[$item['bill_id']][] = $item;
        }
    }
    
    foreach ($bills as &$b) {
        $b['items'] = $itemsByBill[$b['id']] ?? [];
    }
    
    // Active Payment Method QR Code
    $activePM = $db->query("SELECT id, name, account_name, account_number, qr_image, instructions FROM payment_methods WHERE status = 'active' AND qr_image IS NOT NULL AND qr_image != '' LIMIT 1")->fetch();
    
    jsonSuccess([
        'bills' => $bills,
        'payment_method' => $activePM ?: null
    ], 'Bills and payment methods retrieved');
} catch (\Throwable $e) {
    jsonServerError('Failed to fetch bills', $e);
}
