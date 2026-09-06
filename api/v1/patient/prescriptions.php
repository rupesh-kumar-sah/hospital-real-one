<?php
/**
 * REST API — GET /api/v1/patient/prescriptions
 * Returns digital prescriptions and nested medicines list.
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
        SELECT pr.*, u_d.full_name as doctor_name, d.specialization
        FROM prescriptions pr
        JOIN doctors d ON pr.doctor_id = d.id
        JOIN users u_d ON d.user_id = u_d.id
        WHERE pr.patient_id = ?
        ORDER BY pr.created_at DESC
        LIMIT 100
    ");
    $stmt->execute([$patientId]);
    $prescriptions = $stmt->fetchAll();
    
    // Fetch all line items in one query instead of one query per prescription.
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
    
    jsonSuccess($prescriptions, 'Prescriptions retrieved');
} catch (\Throwable $e) {
    jsonServerError('Failed to fetch prescriptions', $e);
}
