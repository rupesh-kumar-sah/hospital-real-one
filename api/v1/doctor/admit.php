<?php
/**
 * REST API — POST /api/v1/doctor/admit
 * Admits an outpatient into an inpatient ward and assigns an available bed.
 */

require_once __DIR__ . '/../../../includes/api_middleware.php';

$auth = requireApiAuth(['doctor', 'admin']);
$userId = (int)$auth['sub'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method Not Allowed. POST required.', 405);
}

$body = getJsonBody();
$patientId = (int)($body['patient_id'] ?? 0);
$wardId = (int)($body['ward_id'] ?? 0);
$bedId = (int)($body['bed_id'] ?? 0);
$reason = trim($body['reason'] ?? 'Inpatient Admission');

if ($patientId <= 0 || $wardId <= 0 || $bedId <= 0) {
    jsonError('patient_id, ward_id, and bed_id are required.', 422);
}

try {
    $db = getDB();
    
    // Look up doctor
    $stmtDoc = $db->prepare("SELECT id FROM doctors WHERE user_id = ?");
    $stmtDoc->execute([$userId]);
    $doctor = $stmtDoc->fetch();
    $doctorId = (int)($doctor['id'] ?? 0);
    
    $db->beginTransaction();
    
    // Verify bed is available
    $stmtBed = $db->prepare("SELECT id, status, bed_number FROM beds WHERE id = ? AND ward_id = ? FOR UPDATE");
    $stmtBed->execute([$bedId, $wardId]);
    $bed = $stmtBed->fetch();
    
    if (!$bed || $bed['status'] !== 'available') {
        $db->rollBack();
        jsonError('Selected bed is occupied or not available.', 409);
    }
    
    // Insert admission
    $stmtAdm = $db->prepare("
        INSERT INTO admissions (patient_id, doctor_id, bed_id, admission_date, status, reason, created_at)
        VALUES (?, ?, ?, CURRENT_TIMESTAMP, 'admitted', ?, CURRENT_TIMESTAMP)
    ");
    $stmtAdm->execute([$patientId, $doctorId, $bedId, $reason]);
    $admissionId = (int)$db->lastInsertId();
    
    // Mark bed occupied
    $stmtBedUpd = $db->prepare("UPDATE beds SET status = 'occupied' WHERE id = ?");
    $stmtBedUpd->execute([$bedId]);
    
    $db->commit();
    
    jsonSuccess([
        'admission_id' => $admissionId,
        'bed_id' => $bedId,
        'bed_number' => $bed['bed_number'],
        'status' => 'admitted'
    ], 'Patient admitted successfully', 201);
    
} catch (\Throwable $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    jsonServerError('Failed to admit patient', $e);
}
