<?php
/**
 * REST API — GET /api/v1/patient/medical-records
 * Fetches patient EMR clinical records with decrypted notes.
 */

require_once __DIR__ . '/../../../includes/api_middleware.php';
require_once __DIR__ . '/../../../config/encryption.php';

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
        SELECT mr.*, u_d.full_name as doctor_name, d.specialization, dep.name as department_name
        FROM medical_records mr
        JOIN doctors d ON mr.doctor_id = d.id
        JOIN users u_d ON d.user_id = u_d.id
        LEFT JOIN departments dep ON d.department_id = dep.id
        WHERE mr.patient_id = ?
        ORDER BY mr.record_date DESC, mr.created_at DESC
        LIMIT 100
    ");
    $stmt->execute([$patientId]);
    $records = $stmt->fetchAll();
    
    // Decrypt notes & diagnosis if encrypted
    foreach ($records as &$rec) {
        $rec['diagnosis'] = decryptData($rec['diagnosis'] ?? '');
        $rec['treatment_plan'] = decryptData($rec['treatment_plan'] ?? '');
        $rec['notes'] = decryptData($rec['notes'] ?? '');
    }
    
    jsonSuccess($records, 'Medical records retrieved');
} catch (\Throwable $e) {
    jsonServerError('Failed to fetch medical records', $e);
}
