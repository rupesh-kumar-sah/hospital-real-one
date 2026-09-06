<?php
/**
 * REST API — POST /api/v1/receptionist/billing
 * Creates a Point-of-Sale billing invoice with line items and payment recording.
 */

require_once __DIR__ . '/../../../includes/api_middleware.php';

$auth = requireApiAuth(['receptionist', 'admin']);
$receptionistUserId = (int)$auth['sub'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method Not Allowed. POST required.', 405);
}

$body = getJsonBody();

$patientId = (int)($body['patient_id'] ?? 0);
$items = $body['items'] ?? [];
$discount = (float)($body['discount'] ?? 0);
$tax = (float)($body['tax'] ?? 0);
$paymentStatus = $body['payment_status'] ?? 'paid';
$paymentMethod = $body['payment_method'] ?? 'cash';

if ($patientId <= 0 || empty($items) || !is_array($items)) {
    jsonError('patient_id and non-empty items array are required.', 422);
}

try {
    $db = getDB();
    
    // Verify patient
    $stmtP = $db->prepare("SELECT id, user_id FROM patients WHERE id = ?");
    $stmtP->execute([$patientId]);
    $patient = $stmtP->fetch();
    if (!$patient) {
        jsonError('Patient not found.', 404);
    }
    
    $subtotal = 0;
    foreach ($items as $it) {
        $qty = max(1, (int)($it['quantity'] ?? 1));
        $unitPrice = (float)($it['unit_price'] ?? 0);
        $subtotal += ($qty * $unitPrice);
    }
    
    $netAmount = max(0, ($subtotal - $discount) + $tax);
    $invoiceNumber = generateInvoiceNumber();
    
    $db->beginTransaction();
    
    $stmtBill = $db->prepare("
        INSERT INTO billing (patient_id, invoice_number, subtotal, discount, tax, net_amount, payment_status, payment_method, payment_date, created_by, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, CASE WHEN ? = 'paid' THEN CURRENT_TIMESTAMP ELSE NULL END, ?, CURRENT_TIMESTAMP)
    ");
    $stmtBill->execute([
        $patientId, $invoiceNumber, $subtotal, $discount, $tax, $netAmount,
        $paymentStatus, $paymentMethod, $paymentStatus, $receptionistUserId
    ]);
    $billId = (int)$db->lastInsertId();
    
    $stmtItem = $db->prepare("
        INSERT INTO billing_items (bill_id, item_type, description, quantity, unit_price, total_price)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    
    foreach ($items as $it) {
        $qty = max(1, (int)($it['quantity'] ?? 1));
        $unitPrice = (float)($it['unit_price'] ?? 0);
        $totalPrice = $qty * $unitPrice;
        $itemType = $it['item_type'] ?? 'service';
        $description = trim($it['description'] ?? 'Hospital Service');
        
        $stmtItem->execute([$billId, $itemType, $description, $qty, $unitPrice, $totalPrice]);
    }
    
    $db->commit();
    
    jsonSuccess([
        'bill_id' => $billId,
        'invoice_number' => $invoiceNumber,
        'subtotal' => $subtotal,
        'discount' => $discount,
        'tax' => $tax,
        'net_amount' => $netAmount,
        'payment_status' => $paymentStatus
    ], 'Invoice generated successfully', 201);
    
} catch (\Throwable $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    jsonError('Failed to create bill: ' . $e->getMessage(), 500);
}
