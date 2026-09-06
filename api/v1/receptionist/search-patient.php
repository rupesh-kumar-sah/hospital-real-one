<?php
/**
 * REST API — GET /api/v1/receptionist/search-patient
 * Fast search for patients by UHID, phone number, or patient name.
 */

require_once __DIR__ . '/../../../includes/api_middleware.php';

$auth = requireApiAuth(['receptionist', 'doctor', 'nurse', 'admin']);

$query = trim($_GET['q'] ?? '');
if (strlen($query) < 2) {
    jsonSuccess([], 'Query too short');
}

try {
    $db = getDB();
    $like = '%' . $query . '%';
    
    $stmt = $db->prepare("
        SELECT p.id as patient_id, p.uhid, p.date_of_birth, p.gender, p.blood_group,
               u.id as user_id, u.full_name, u.phone, u.email
        FROM patients p
        JOIN users u ON p.user_id = u.id
        WHERE p.uhid LIKE ? OR u.phone LIKE ? OR u.full_name LIKE ? OR u.email LIKE ?
        ORDER BY p.id DESC
        LIMIT 20
    ");
    $stmt->execute([$like, $like, $like, $like]);
    $results = $stmt->fetchAll();
    
    jsonSuccess($results, 'Patient search results');
} catch (\Throwable $e) {
    jsonServerError('Failed to search patients', $e);
}
