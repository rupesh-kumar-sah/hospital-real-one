<?php
/**
 * REST API — POST /api/v1/nurse/vitals
 * Records timed patient vital signs (BP, Pulse, Temperature, SpO2, Respiratory Rate).
 */

require_once __DIR__ . '/../../../includes/api_middleware.php';

$auth = requireApiAuth(['nurse', 'doctor', 'admin']);
$nurseUserId = (int)$auth['sub'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method Not Allowed. POST required.', 405);
}

$body = getJsonBody();
$patientId = (int)($body['patient_id'] ?? 0);
$admissionId = !empty($body['admission_id']) ? (int)$body['admission_id'] : null;
$bp = trim($body['blood_pressure'] ?? '');
$pulse = !empty($body['pulse_rate']) ? (int)$body['pulse_rate'] : null;
$temp = !empty($body['temperature']) ? (float)$body['temperature'] : null;
$rr = !empty($body['respiratory_rate']) ? (int)$body['respiratory_rate'] : null;
$spo2 = !empty($body['spo2']) ? (float)$body['spo2'] : null;
$weight = !empty($body['weight_kg']) ? (float)$body['weight_kg'] : null;
$height = !empty($body['height_cm']) ? (float)$body['height_cm'] : null;
$notes = trim($body['notes'] ?? '');

if ($patientId <= 0) {
    jsonError('patient_id is required.', 422);
}

try {
    $db = getDB();
    
    $stmt = $db->prepare("
        INSERT INTO vitals (patient_id, admission_id, recorded_by, blood_pressure, pulse_rate, temperature, respiratory_rate, spo2, weight_kg, height_cm, notes, recorded_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
    ");
    $stmt->execute([$patientId, $admissionId, $nurseUserId, $bp, $pulse, $temp, $rr, $spo2, $weight, $height, $notes]);
    
    jsonSuccess(['id' => (int)$db->lastInsertId()], 'Vitals logged successfully', 201);
} catch (\Throwable $e) {
    jsonServerError('Failed to record vitals', $e);
}
