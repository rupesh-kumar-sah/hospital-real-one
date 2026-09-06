<?php
/**
 * REST API — POST /api/v1/nurse/medication
 * Logs inpatient Medication Administration Record (MAR).
 */

require_once __DIR__ . '/../../../includes/api_middleware.php';

$auth = requireApiAuth(['nurse', 'doctor', 'admin']);
$nurseUserId = (int)$auth['sub'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method Not Allowed. POST required.', 405);
}

$body = getJsonBody();
$admissionId = (int)($body['admission_id'] ?? 0);
$medicineName = trim($body['medicine_name'] ?? '');
$dosage = trim($body['dosage'] ?? '');
$route = trim($body['route'] ?? 'Oral'); // Oral, IV, IM, SC, Topical
$status = trim($body['status'] ?? 'given'); // given, missed, refused
$notes = trim($body['notes'] ?? '');

if ($admissionId <= 0 || empty($medicineName)) {
    jsonError('admission_id and medicine_name are required.', 422);
}

try {
    $db = getDB();
    
    $stmt = $db->prepare("
        INSERT INTO medication_records (admission_id, medicine_name, dosage, route, administered_by, scheduled_time, administered_time, status, notes)
        VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, ?, ?)
    ");
    $stmt->execute([$admissionId, $medicineName, $dosage, $route, $nurseUserId, $status, $notes]);
    
    jsonSuccess(['id' => (int)$db->lastInsertId()], 'Medication administration logged', 201);
} catch (\Throwable $e) {
    jsonServerError('Failed to record medication', $e);
}
