<?php
/**
 * REST API — POST /api/v1/auth/login
 * Authenticates user credentials, issues 15-min JWT access token & database-backed HttpOnly refresh token.
 */

require_once __DIR__ . '/../../../includes/api_middleware.php';
require_once __DIR__ . '/../../../config/security.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method Not Allowed. POST required.', 405);
}

// Check IP-based brute-force rate limit
if (!checkRateLimit('api_login', 5, 900)) {
    jsonError('Too many login attempts. Please wait 15 minutes and try again.', 429);
}

$body = getJsonBody();
$username = trim($body['username'] ?? $body['email'] ?? '');
$password = $body['password'] ?? '';

if (empty($username) || empty($password)) {
    jsonError('Username/email and password are required.', 422);
}

try {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT id, username, email, password_hash, full_name, role, status, avatar
        FROM users
        WHERE (username = ? OR email = ?)
        LIMIT 1
    ");
    $stmt->execute([$username, $username]);
    $user = $stmt->fetch();
    
    if (!$user || !password_verify($password, $user['password_hash'])) {
        jsonError('Invalid username/email or password.', 401);
    }
    
    if ($user['status'] !== 'active') {
        jsonError('Account is ' . $user['status'] . '. Please contact the administrator.', 403);
    }
    
    // Update last login
    $updateStmt = $db->prepare("UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = ?");
    $updateStmt->execute([$user['id']]);
    
    // Generate JWT access token (15 mins) and refresh token (7 days)
    $accessToken = generateAccessToken($user);
    $refreshToken = generateRefreshToken();
    
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    storeRefreshToken((int)$user['id'], $refreshToken, $ip, $ua);
    
    // Set secure HttpOnly refresh token cookie
    setRefreshTokenCookie($refreshToken);
    
    // Audit Log
    try {
        $audit = $db->prepare("INSERT INTO audit_logs (user_id, user_name, action, description, ip_address, user_agent) VALUES (?, ?, 'login', 'API Login successful', ?, ?)");
        $audit->execute([$user['id'], $user['full_name'], $ip, substr($ua, 0, 500)]);
    } catch (\Throwable $e) {}
    
    jsonSuccess([
        'access_token' => $accessToken,
        'token_type' => 'Bearer',
        'expires_in' => JWT_ACCESS_TOKEN_EXPIRY,
        'user' => [
            'id' => (int)$user['id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'full_name' => $user['full_name'],
            'role' => $user['role'],
            'avatar' => $user['avatar']
        ]
    ], 'Login successful');
    
} catch (\Throwable $e) {
    jsonError('Server error during authentication: ' . $e->getMessage(), 500);
}
