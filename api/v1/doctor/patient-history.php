<?php
/**
 * REST API — GET /api/v1/doctor/patient-history
 * Comprehensive EMR history for a specific patient ID.
 */

require_once __DIR__ . '/../../../includes/api_middleware.php';
require_once __DIR__ . '/../../../config/encryption.php';

$auth = requireApiAuth(['doctor', 'nurse', 'admin']);

$patientId = (int)($_GET['patient_id'] ?? 0);
if ($patientId <= 0) {
    jsonError('patient_id query parameter is required.', 422);
}

try {
    $db = getDB();
    
    // Patient Profile
    $stmtP = $db->prepare("
        SELECT p.*, u.full_name, u.email, u.phone, u.avatar
        FROM patients p
        JOIN users u ON p.user_id = u.id
        WHERE p.id = ?
    ");
    $stmtP->execute([$patientId]);
    $patient = $stmtP->fetch();
    
    if (!$patient) {
        jsonError('Patient not found.', 404);
    }
    
    // Medical Records
    $stmtMR = $db->prepare("
        SELECT mr.*, u_d.full_name as doctor_name, d.specialization
        FROM medical_records mr
        JOIN doctors d ON mr.doctor_id = d.id
        JOIN users u_d ON d.user_id = u_d.id
        WHERE mr.patient_id = ?
        ORDER BY mr.record_date DESC
        LIMIT 100
    ");
    $stmtMR->execute([$patientId]);
    $records = $stmtMR->fetchAll();
    foreach ($records as &$r) {
        $r['diagnosis'] = decryptData($r['diagnosis'] ?? '');
        $r['treatment_plan'] = decryptData($r['treatment_plan'] ?? '');
        $r['notes'] = decryptData($r['notes'] ?? '');
    }
    
    // Prescriptions
    $stmtRx = $db->prepare("
        SELECT pr.*, u_d.full_name as doctor_name
        FROM prescriptions pr
        JOIN doctors d ON pr.doctor_id = d.id
        JOIN users u_d ON d.user_id = u_d.id
        WHERE pr.patient_id = ?
        ORDER BY pr.created_at DESC
        LIMIT 100
    ");
    $stmtRx->execute([$patientId]);
    $prescriptions = $stmtRx->fetchAll();
    
    $itemsByPrescription = [];
    if ($prescriptions) {
        $placeholders = implode(',', array_fill(0, count($prescriptions), '?'));
        $itemStmt = $db->prepare(
            "SELECT * FROM prescription_items WHERE prescription_id IN ({$placeholders}) ORDER BY prescription_id, id"
        );
        $itemStmt->execute(array_column($prescriptions, 'id'));
        foreach ($itemStmt->fetchAll() as $item) {
            $itemsByPrescription[$item['prescription_id']][] = $item;
        }
    }
    foreach ($prescriptions as &$rx) {
        $rx['items'] = $itemsByPrescription[$rx['id']] ?? [];
    }
    
    // Lab Results
    $stmtLab = $db->prepare("
        SELECT lo.*, lc.test_name, lc.category, lr.result_value, lr.reference_range, lr.interpretation, lr.result_notes
        FROM lab_orders lo
        JOIN lab_test_catalog lc ON lo.test_id = lc.id
        LEFT JOIN lab_results lr ON lo.id = lr.lab_order_id
        WHERE lo.patient_id = ?
        ORDER BY lo.ordered_at DESC
        LIMIT 100
    ");
    $stmtLab->execute([$patientId]);
    $labResults = $stmtLab->fetchAll();
    
    // Vitals History
    $stmtVit = $db->prepare("
        SELECT v.*, u.full_name as recorded_by_name
        FROM vitals v
        LEFT JOIN users u ON v.recorded_by = u.id
        WHERE v.patient_id = ?
        ORDER BY v.recorded_at DESC
        LIMIT 20
    ");
    $stmtVit->execute([$patientId]);
    $vitals = $stmtVit->fetchAll();
    
    jsonSuccess([
        'patient' => $patient,
        'medical_records' => $records,
        'prescriptions' => $prescriptions,
        'lab_results' => $labResults,
        'vitals' => $vitals
    ], 'Patient medical history retrieved');
    
} catch (\Throwable $e) {
    jsonServerError('Failed to fetch patient history', $e);
}
