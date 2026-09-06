<?php
/**
 * REST API — POST /api/v1/nurse/nursing-notes
 * Records shift clinical observation notes for admitted inpatients.
 */

require_once __DIR__ . '/../../../includes/api_middleware.php';

$auth = requireApiAuth(['nurse', 'admin']);
$nurseUserId = (int)$auth['sub'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method Not Allowed. POST required.', 405);
}

$body = getJsonBody();
$admissionId = (int)($body['admission_id'] ?? 0);
$note = trim($body['note'] ?? '');
$shift = trim($body['shift'] ?? 'morning'); // morning, evening, night

if ($admissionId <= 0 || empty($note)) {
    jsonError('admission_id and note text are required.', 422);
}

try {
    $db = getDB();
    
    // Look up nurse record
    $stmtN = $db->prepare("SELECT id FROM nurses WHERE user_id = ?");
    $stmtN->execute([$nurseUserId]);
    $nurse = $stmtN->fetch();
    $nurseId = (int)($nurse['id'] ?? 0);
    
    $stmt = $db->prepare("
        INSERT INTO nursing_notes (admission_id, nurse_id, note, shift, created_at)
        VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)
    ");
    $stmt->execute([$admissionId, $nurseId ?: null, $note, $shift]);
    
    jsonSuccess(['id' => (int)$db->lastInsertId()], 'Nursing note recorded', 201);
} catch (\Throwable $e) {
    jsonError('Failed to record nursing note: ' . $e->getMessage(), 500);
}
