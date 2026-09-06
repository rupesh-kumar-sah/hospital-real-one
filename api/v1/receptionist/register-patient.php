<?php
/**
 * REST API — POST /api/v1/receptionist/register-patient
 * In-person patient intake with automatic UHID generation.
 */

require_once __DIR__ . '/../../../includes/api_middleware.php';

$auth = requireApiAuth(['receptionist', 'admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method Not Allowed. POST required.', 405);
}

$body = getJsonBody();

$fullName = trim($body['full_name'] ?? '');
$phone = trim($body['phone'] ?? '');
$email = trim($body['email'] ?? '');
$gender = $body['gender'] ?? 'male';
$dob = $body['date_of_birth'] ?? null;
$bloodGroup = $body['blood_group'] ?? '';
$address = trim($body['address'] ?? '');
$emergencyContact = trim($body['emergency_contact_name'] ?? '');
$emergencyPhone = trim($body['emergency_contact_phone'] ?? '');

if (empty($fullName) || empty($phone)) {
    jsonError('Full name and phone number are required.', 422);
}

try {
    $db = getDB();
    
    // Check if phone or email already registered
    if (!empty($email)) {
        $check = $db->prepare("SELECT id FROM users WHERE phone = ? OR email = ? LIMIT 1");
        $check->execute([$phone, $email]);
    } else {
        $check = $db->prepare("SELECT id FROM users WHERE phone = ? LIMIT 1");
        $check->execute([$phone]);
    }
    
    if ($check->fetch()) {
        jsonError('A patient with this phone number or email is already registered.', 409);
    }
    
    $username = 'pt_' . preg_replace('/[^0-9]/', '', $phone);
    if (empty($email)) {
        $email = $username . '@hospital.local';
    }
    
    $randomPass = bin2hex(random_bytes(4));
    $passHash = password_hash($randomPass, PASSWORD_DEFAULT);
    $uhid = generateUHID();
    
    $db->beginTransaction();
    
    $stmtUser = $db->prepare("
        INSERT INTO users (username, email, password_hash, full_name, phone, role, status, created_at)
        VALUES (?, ?, ?, ?, ?, 'patient', 'active', CURRENT_TIMESTAMP)
    ");
    $stmtUser->execute([$username, $email, $passHash, $fullName, $phone]);
    $userId = (int)$db->lastInsertId();
    
    $stmtPatient = $db->prepare("
        INSERT INTO patients (user_id, uhid, date_of_birth, gender, blood_group, address, emergency_contact_name, emergency_contact_phone, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
    ");
    $stmtPatient->execute([$userId, $uhid, $dob ?: null, $gender, $bloodGroup, $address, $emergencyContact, $emergencyPhone]);
    $patientId = (int)$db->lastInsertId();
    
    $db->commit();
    
    jsonSuccess([
        'patient_id' => $patientId,
        'user_id' => $userId,
        'uhid' => $uhid,
        'username' => $username,
        'temporary_password' => $randomPass,
        'full_name' => $fullName
    ], 'Patient registered successfully', 201);
    
} catch (\Throwable $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    jsonError('Failed to register patient: ' . $e->getMessage(), 500);
}
