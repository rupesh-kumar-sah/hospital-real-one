<?php
/**
 * REST API — POST /api/v1/auth/reset-password
 * Resets user password given a valid unexpired reset token.
 */

require_once __DIR__ . '/../../../includes/api_middleware.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method Not Allowed. POST required.', 405);
}

$body = getJsonBody();
$token = trim($body['token'] ?? '');
$newPassword = $body['new_password'] ?? '';

if (empty($token) || empty($newPassword)) {
    jsonError('Token and new password are required.', 422);
}

if (strlen($newPassword) < 6) {
    jsonError('New password must be at least 6 characters.', 422);
}

try {
    $db = getDB();
    $now = date('Y-m-d H:i:s');
    
    $stmt = $db->prepare("
        SELECT pr.*, u.id as user_id, u.username
        FROM password_resets pr
        JOIN users u ON pr.user_id = u.id
        WHERE pr.token = ? AND pr.expires_at > ?
        LIMIT 1
    ");
    $stmt->execute([$token, $now]);
    $resetData = $stmt->fetch();
    
    if (!$resetData) {
        jsonError('This password reset link is invalid or has expired.', 400);
    }
    
    $userId = (int)$resetData['user_id'];
    $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
    
    $db->beginTransaction();
    
    // Update password
    $stmtUpd = $db->prepare("UPDATE users SET password_hash = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
    $stmtUpd->execute([$newHash, $userId]);
    
    // Delete consumed reset token
    $stmtDel = $db->prepare("DELETE FROM password_resets WHERE token = ?");
    $stmtDel->execute([$token]);
    
    // Revoke all existing refresh tokens for security
    $stmtRevoke = $db->prepare("UPDATE refresh_tokens SET revoked = 1 WHERE user_id = ?");
    $stmtRevoke->execute([$userId]);
    
    $db->commit();
    
    jsonSuccess(null, 'Password has been reset successfully. Please log in with your new password.');
    
} catch (\Throwable $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    jsonError('Failed to reset password: ' . $e->getMessage(), 500);
}
