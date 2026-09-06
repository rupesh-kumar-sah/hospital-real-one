<?php
/**
 * REST API — POST /api/v1/auth/forgot-password
 * Generates a password recovery token for valid email/username.
 */

require_once __DIR__ . '/../../../includes/api_middleware.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method Not Allowed. POST required.', 405);
}

$body = getJsonBody();
$emailOrUser = trim($body['email'] ?? $body['username'] ?? '');

if (empty($emailOrUser)) {
    jsonError('Email or username is required.', 422);
}

try {
    $db = getDB();
    $stmt = $db->prepare("SELECT id, username, email, full_name FROM users WHERE (email = ? OR username = ?) AND status = 'active' LIMIT 1");
    $stmt->execute([$emailOrUser, $emailOrUser]);
    $user = $stmt->fetch();
    
    if (!$user) {
        // Obfuscated generic message to prevent email enumeration
        jsonSuccess(null, 'If an active account exists with that identifier, password reset instructions have been generated.');
    }
    
    $token = bin2hex(random_bytes(32));
    $expiresAt = date('Y-m-d H:i:s', time() + 3600); // 1 hour validity
    
    $stmtReset = $db->prepare("INSERT INTO password_resets (user_id, token, expires_at, created_at) VALUES (?, ?, ?, CURRENT_TIMESTAMP)");
    $stmtReset->execute([$user['id'], $token, $expiresAt]);
    
    jsonSuccess([
        'token' => $token,
        'expires_at' => $expiresAt,
        'reset_url' => APP_BASE_URL . '/auth/reset_password.php?token=' . $token
    ], 'Password reset token generated successfully');
    
} catch (\Throwable $e) {
    jsonServerError('Failed to process password reset', $e);
}
