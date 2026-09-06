<?php
/**
 * REST API — GET /api/v1/patient/lab-reports
 * Returns completed diagnostic test reports and test values.
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
    
    $stmt = $db->prepare("
        SELECT lo.id, lo.ordered_at, lo.status, lo.priority, lo.clinical_notes,
               lc.test_name, lc.category, lc.sample_type, lc.normal_range, lc.unit,
               u_d.full_name as doctor_name,
               lr.result_value, lr.result_unit, lr.reference_range, lr.interpretation, lr.result_notes, lr.verified_at
        FROM lab_orders lo
        JOIN lab_test_catalog lc ON lo.test_id = lc.id
        JOIN doctors d ON lo.doctor_id = d.id
        JOIN users u_d ON d.user_id = u_d.id
        LEFT JOIN lab_results lr ON lo.id = lr.lab_order_id
        WHERE lo.patient_id = ?
        ORDER BY lo.ordered_at DESC
        LIMIT 100
    ");
    $stmt->execute([$patientId]);
    $reports = $stmt->fetchAll();
    
    jsonSuccess($reports, 'Lab reports retrieved');
} catch (\Throwable $e) {
    jsonServerError('Failed to fetch lab reports', $e);
}
