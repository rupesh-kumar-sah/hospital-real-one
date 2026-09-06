<?php
/**
 * REST API — GET /api/v1/auth/me
 * Returns authenticated user profile, permissions, and doctor/patient record IDs.
 */

require_once __DIR__ . '/../../../includes/api_middleware.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonError('Method Not Allowed. GET required.', 405);
}

$currentUser = requireApiAuth();
$userId = (int)$currentUser['sub'];

try {
    $db = getDB();
    $stmt = $db->prepare("SELECT id, username, email, full_name, phone, role, avatar, status, last_login, created_at FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if (!$user) {
        jsonError('User account not found.', 404);
    }
    
    // Attach patient or doctor specific foreign record ID if applicable
    $patientInfo = null;
    $doctorInfo = null;
    
    if ($user['role'] === 'patient') {
        $stmtP = $db->prepare("SELECT id, uhid, date_of_birth, gender, blood_group, city, state FROM patients WHERE user_id = ?");
        $stmtP->execute([$userId]);
        $patientInfo = $stmtP->fetch() ?: null;
    } elseif ($user['role'] === 'doctor') {
        $stmtD = $db->prepare("SELECT d.id, d.specialization, d.qualification, d.consultation_fee, dep.name as department_name FROM doctors d LEFT JOIN departments dep ON d.department_id = dep.id WHERE d.user_id = ?");
        $stmtD->execute([$userId]);
        $doctorInfo = $stmtD->fetch() ?: null;
    }
    
    jsonSuccess([
        'user' => $user,
        'patient' => $patientInfo,
        'doctor' => $doctorInfo
    ], 'User profile retrieved');
    
} catch (\Throwable $e) {
    jsonServerError('Failed to fetch user profile', $e);
}
