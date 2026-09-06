<?php
/**
 * REST API — POST /api/v1/receptionist/check-in
 * Checks in patient for today's appointment and generates OPD invoice.
 */

require_once __DIR__ . '/../../../includes/api_middleware.php';

$auth = requireApiAuth(['receptionist', 'admin']);
$receptionistUserId = (int)$auth['sub'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method Not Allowed. POST required.', 405);
}

$body = getJsonBody();
$apptId = (int)($body['appointment_id'] ?? 0);

if ($apptId <= 0) {
    jsonError('appointment_id is required.', 422);
}

try {
    $db = getDB();
    
    $stmtAppt = $db->prepare("
        SELECT a.*, d.consultation_fee, u_d.full_name as doctor_name, p.user_id as patient_user_id
        FROM appointments a
        JOIN doctors d ON a.doctor_id = d.id
        JOIN users u_d ON d.user_id = u_d.id
        JOIN patients p ON a.patient_id = p.id
        WHERE a.id = ?
    ");
    $stmtAppt->execute([$apptId]);
    $appt = $stmtAppt->fetch();
    
    if (!$appt) {
        jsonError('Appointment not found.', 404);
    }
    
    $db->beginTransaction();
    
    // Update appointment status to checked_in
    $stmtUpd = $db->prepare("UPDATE appointments SET status = 'checked_in' WHERE id = ?");
    $stmtUpd->execute([$apptId]);
    
    // Auto-generate OPD Consultation invoice if not already generated today
    $fee = (float)($appt['consultation_fee'] ?: 500);
    $patientId = (int)$appt['patient_id'];
    $todayDate = date('Y-m-d');
    $nextDate = date('Y-m-d', strtotime('+1 day'));
    
    $checkBill = $db->prepare("SELECT id FROM billing WHERE patient_id = ? AND created_at >= ? AND created_at < ? LIMIT 1");
    $checkBill->execute([$patientId, $todayDate, $nextDate]);
    $existingBill = $checkBill->fetch();
    
    $billId = null;
    $invNum = null;
    
    if (!$existingBill) {
        $invNum = generateInvoiceNumber();
        $stmtBill = $db->prepare("
            INSERT INTO billing (patient_id, appointment_id, invoice_number, subtotal, discount, tax, net_amount, payment_status, payment_method, payment_date, created_by, created_at)
            VALUES (?, ?, ?, ?, 0, 0, ?, 'paid', 'Cash', CURRENT_TIMESTAMP, ?, CURRENT_TIMESTAMP)
        ");
        $stmtBill->execute([$patientId, $apptId, $invNum, $fee, $fee, $receptionistUserId]);
        $billId = (int)$db->lastInsertId();
        
        $stmtItem = $db->prepare("
            INSERT INTO billing_items (bill_id, item_type, description, quantity, unit_price, total_price)
            VALUES (?, 'consultation', ?, 1, ?, ?)
        ");
        $stmtItem->execute([$billId, "OPD Consultation Fee - Dr. {$appt['doctor_name']} (Token #{$appt['token_number']})", $fee, $fee]);
    } else {
        $billId = (int)$existingBill['id'];
    }
    
    // Notify Doctor of patient arrival
    try {
        $stmtNotif = $db->prepare("
            INSERT INTO notifications (user_id, title, message, type, is_read, created_at)
            SELECT d.user_id, 'Patient Checked In', ?, 'appointment', 0, CURRENT_TIMESTAMP
            FROM doctors d WHERE d.id = ?
        ");
        $stmtNotif->execute(['Patient with Token #' . (int)$appt['token_number'] . ' is waiting for consultation.', $appt['doctor_id']]);
    } catch (\Throwable $e) {}
    
    $db->commit();
    
    jsonSuccess([
        'appointment_id' => $apptId,
        'status' => 'checked_in',
        'token_number' => $appt['token_number'],
        'bill_id' => $billId,
        'invoice_number' => $invNum
    ], 'Patient checked in successfully');
    
} catch (\Throwable $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    jsonServerError('Failed to check in patient', $e);
}
