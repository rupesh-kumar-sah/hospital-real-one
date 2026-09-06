<?php
/**
 * REST API — /api/v1/receptionist/invoices
 * GET: Returns specific invoice details and line items with active payment QR
 * PATCH/POST: Updates payment status
 */

require_once __DIR__ . '/../../../includes/api_middleware.php';

$auth = requireApiAuth(['receptionist', 'admin', 'patient']);
$db = getDB();

$invoiceId = (int)($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if ($invoiceId <= 0) {
        // List recent invoices
        $stmtList = $db->query("
            SELECT b.*, p.uhid, u.full_name as patient_name, u.phone as patient_phone
            FROM billing b
            JOIN patients p ON b.patient_id = p.id
            JOIN users u ON p.user_id = u.id
            ORDER BY b.created_at DESC
            LIMIT 50
        ");
        jsonSuccess($stmtList->fetchAll(), 'Invoices list retrieved');
    }
    
    // Single invoice lookup
    $stmt = $db->prepare("
        SELECT b.*, p.uhid, u.full_name as patient_name, u.phone as patient_phone, u.email as patient_email
        FROM billing b
        JOIN patients p ON b.patient_id = p.id
        JOIN users u ON p.user_id = u.id
        WHERE b.id = ?
    ");
    $stmt->execute([$invoiceId]);
    $bill = $stmt->fetch();
    
    if (!$bill) {
        jsonError('Invoice not found', 404);
    }
    
    $stmtItems = $db->prepare("SELECT * FROM billing_items WHERE bill_id = ?");
    $stmtItems->execute([$invoiceId]);
    $bill['items'] = $stmtItems->fetchAll();
    
    $activePM = $db->query("SELECT id, name, account_name, account_number, qr_image, instructions FROM payment_methods WHERE status = 'active' AND qr_image IS NOT NULL AND qr_image != '' LIMIT 1")->fetch();
    $bill['payment_method'] = $activePM ?: null;
    
    jsonSuccess($bill, 'Invoice retrieved');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' || $_SERVER['REQUEST_METHOD'] === 'PATCH') {
    $auth = requireApiAuth(['receptionist', 'admin']);
    $body = getJsonBody();
    $id = (int)($body['id'] ?? $invoiceId);
    $status = $body['payment_status'] ?? 'paid';
    $method = $body['payment_method'] ?? 'cash';
    
    $stmtUpd = $db->prepare("
        UPDATE billing SET 
            payment_status = ?,
            payment_method = ?,
            payment_date = CASE WHEN ? = 'paid' THEN CURRENT_TIMESTAMP ELSE payment_date END,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = ?
    ");
    $stmtUpd->execute([$status, $method, $status, $id]);
    
    jsonSuccess(null, 'Invoice payment status updated');
}

jsonError('Method Not Allowed', 405);
