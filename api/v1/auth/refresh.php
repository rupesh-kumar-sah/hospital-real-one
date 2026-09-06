<?php
/**
 * REST API — POST /api/v1/auth/refresh
 * Validates HttpOnly refresh token cookie, rotates token, and issues new 15-minute JWT access token.
 */

require_once __DIR__ . '/../../../includes/api_middleware.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method Not Allowed. POST required.', 405);
}

// Retrieve refresh token from Cookie or Request Body fallback
$refreshToken = $_COOKIE['hms_refresh_token'] ?? '';
if (empty($refreshToken)) {
    $body = getJsonBody();
    $refreshToken = $body['refresh_token'] ?? '';
}

if (empty($refreshToken)) {
    jsonError('No refresh token provided.', 401);
}

try {
    $userData = verifyRefreshToken($refreshToken);
    if (!$userData) {
        clearRefreshTokenCookie();
        jsonError('Invalid, revoked, or expired refresh token. Please log in again.', 401);
    }
    
    // Revoke old refresh token (Token Rotation Security)
    revokeRefreshToken($refreshToken);
    
    // Issue new Access Token and new Refresh Token
    $newAccessToken = generateAccessToken([
        'id' => $userData['user_id'],
        'username' => $userData['username'],
        'email' => $userData['email'],
        'full_name' => $userData['full_name'],
        'role' => $userData['role']
    ]);
    
    $newRefreshToken = generateRefreshToken();
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    storeRefreshToken((int)$userData['user_id'], $newRefreshToken, $ip, $ua);
    
    setRefreshTokenCookie($newRefreshToken);
    
    jsonSuccess([
        'access_token' => $newAccessToken,
        'token_type' => 'Bearer',
        'expires_in' => JWT_ACCESS_TOKEN_EXPIRY,
        'user' => [
            'id' => (int)$userData['user_id'],
            'username' => $userData['username'],
            'email' => $userData['email'],
            'full_name' => $userData['full_name'],
            'role' => $userData['role']
        ]
    ], 'Token refreshed successfully');
    
} catch (\Throwable $e) {
    jsonError('Server error during token refresh: ' . $e->getMessage(), 500);
}
