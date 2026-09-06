<?php
/**
 * REST API — POST /api/v1/auth/register
 * Patient self-registration endpoint with automatic UHID generation.
 */

require_once __DIR__ . '/../../../includes/api_middleware.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../config/security.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method Not Allowed. POST required.', 405);
}

if (!checkRateLimit('api_register', 5, 900)) {
    jsonError('Too many registration attempts. Please wait and try again.', 429);
}

$body = getJsonBody();

$fullName = trim($body['full_name'] ?? '');
$username = trim($body['username'] ?? '');
$email = trim($body['email'] ?? '');
$phone = trim($body['phone'] ?? '');
$password = $body['password'] ?? '';
$dob = $body['date_of_birth'] ?? null;
$gender = $body['gender'] ?? 'other';
$bloodGroup = $body['blood_group'] ?? '';
$address = trim($body['address'] ?? '');

if (empty($fullName) || empty($username) || empty($password) || empty($email) || empty($phone)) {
    jsonError('Full name, username, email, phone number, and password are required.', 422);
}

if (($passwordError = passwordStrengthError($password)) !== null) {
    jsonError($passwordError, 422);
}

try {
    $db = getDB();
    
    // Check if username or email or phone exists
    $check = $db->prepare("SELECT id FROM users WHERE username = ? OR email = ? OR phone = ? LIMIT 1");
    $check->execute([$username, $email, $phone]);
    if ($check->fetch()) {
        jsonError('A user with this username, email, or phone number already exists.', 409);
    }
    
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    $uhid = generateUHID();
    
    $db->beginTransaction();
    
    $stmtUser = $db->prepare("
        INSERT INTO users (username, email, password_hash, full_name, phone, role, status, created_at)
        VALUES (?, ?, ?, ?, ?, 'patient', 'active', CURRENT_TIMESTAMP)
    ");
    $stmtUser->execute([$username, $email, $passwordHash, $fullName, $phone]);
    $userId = (int)$db->lastInsertId();
    
    $stmtPatient = $db->prepare("
        INSERT INTO patients (user_id, uhid, date_of_birth, gender, blood_group, address, created_at)
        VALUES (?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
    ");
    $stmtPatient->execute([$userId, $uhid, $dob ?: null, $gender, $bloodGroup, $address]);
    
    // Auto-generate Welcome Notification
    try {
        $stmtNotif = $db->prepare("INSERT INTO notifications (user_id, title, message, type, is_read, created_at) VALUES (?, 'Welcome to MediCare HMS', ?, 'info', 0, CURRENT_TIMESTAMP)");
        $stmtNotif->execute([$userId, 'Your patient registration is complete. Your UHID is ' . $uhid . '.']);
    } catch (\Throwable $e) {}
    
    $db->commit();
    
    // Automatically issue Access & Refresh tokens
    $userRecord = [
        'id' => $userId,
        'username' => $username,
        'email' => $email,
        'full_name' => $fullName,
        'role' => 'patient'
    ];
    $accessToken = generateAccessToken($userRecord);
    $refreshToken = generateRefreshToken();
    
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    storeRefreshToken($userId, $refreshToken, $ip, $ua);
    setRefreshTokenCookie($refreshToken);
    
    jsonSuccess([
        'access_token' => $accessToken,
        'token_type' => 'Bearer',
        'expires_in' => JWT_ACCESS_TOKEN_EXPIRY,
        'user' => [
            'id' => $userId,
            'username' => $username,
            'email' => $email,
            'full_name' => $fullName,
            'role' => 'patient',
            'uhid' => $uhid
        ]
    ], 'Registration successful', 201);
    
} catch (\Throwable $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    jsonServerError('Failed to register patient', $e);
}
