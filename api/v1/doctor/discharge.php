<?php
/**
 * REST API — POST /api/v1/doctor/discharge
 * Discharges an inpatient and marks assigned bed available.
 */

require_once __DIR__ . '/../../../includes/api_middleware.php';

$auth = requireApiAuth(['doctor', 'admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method Not Allowed. POST required.', 405);
}

$body = getJsonBody();
$admissionId = (int)($body['admission_id'] ?? 0);
$dischargeSummary = trim($body['discharge_summary'] ?? 'Discharged in stable condition');

if ($admissionId <= 0) {
    jsonError('admission_id is required.', 422);
}

try {
    $db = getDB();
    
    $stmtAdm = $db->prepare("SELECT id, bed_id, status FROM admissions WHERE id = ?");
    $stmtAdm->execute([$admissionId]);
    $adm = $stmtAdm->fetch();
    
    if (!$adm || $adm['status'] !== 'admitted') {
        jsonError('Active admission record not found.', 404);
    }
    
    $db->beginTransaction();
    
    // Update admission to discharged
    $stmtUpd = $db->prepare("
        UPDATE admissions SET 
            status = 'discharged',
            discharge_date = CURRENT_TIMESTAMP,
            discharge_summary = ?,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = ?
    ");
    $stmtUpd->execute([$dischargeSummary, $admissionId]);
    
    // Free bed
    if (!empty($adm['bed_id'])) {
        $stmtBed = $db->prepare("UPDATE beds SET status = 'available' WHERE id = ?");
        $stmtBed->execute([$adm['bed_id']]);
    }
    
    $db->commit();
    
    jsonSuccess([
        'admission_id' => $admissionId,
        'status' => 'discharged'
    ], 'Patient discharged successfully');
    
} catch (\Throwable $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    jsonError('Failed to discharge patient: ' . $e->getMessage(), 500);
}
