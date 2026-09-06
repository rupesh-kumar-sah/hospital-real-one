<?php
/**
 * Hospital Management System — JWT & Refresh Token Authentication
 * Pure PHP lightweight HMAC-SHA256 (HS256) JWT engine with database-tracked revocable refresh tokens.
 */

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/constants.php';

define('JWT_ACCESS_TOKEN_EXPIRY', 900);       // 15 minutes in seconds
define('JWT_REFRESH_TOKEN_EXPIRY', 604800);   // 7 days in seconds

/**
 * Retrieve JWT signing secret key exclusively from environment.
 * Throws RuntimeException if not properly configured.
 */
function getJwtSecret(): string {
    $secret = getenv('JWT_SECRET') ?: ($_ENV['JWT_SECRET'] ?? null);
    if (!$secret || strlen($secret) < 32) {
        throw new RuntimeException('CRITICAL SECURITY ERROR: JWT_SECRET environment variable is missing or insufficiently secure (min 32 characters).');
    }
    return $secret;
}


/**
 * Base64Url encode helper
 */
function base64UrlEncode(string $data): string {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

/**
 * Base64Url decode helper
 */
function base64UrlDecode(string $data): string {
    return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', 3 - (3 + strlen($data)) % 4));
}

/**
 * Generate a signed JWT access token (15-minute lifespan)
 */
function generateAccessToken(array $user): string {
    $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
    
    $now = time();
    $payload = json_encode([
        'iss' => APP_NAME,
        'sub' => (int)$user['id'],
        'username' => $user['username'] ?? '',
        'email' => $user['email'] ?? '',
        'role' => $user['role'] ?? '',
        'full_name' => $user['full_name'] ?? '',
        'iat' => $now,
        'exp' => $now + JWT_ACCESS_TOKEN_EXPIRY
    ]);
    
    $base64Header = base64UrlEncode($header);
    $base64Payload = base64UrlEncode($payload);
    
    $signature = hash_hmac('sha256', $base64Header . '.' . $base64Payload, getJwtSecret(), true);
    $base64Signature = base64UrlEncode($signature);
    
    return $base64Header . '.' . $base64Payload . '.' . $base64Signature;
}

/**
 * Verify and decode a JWT access token
 * Returns payload array on success, or null on failure (expired, tampered, invalid alg)
 */
function verifyAccessToken(string $token): ?array {
    $parts = explode('.', $token);
    if (count($parts) !== 3) {
        return null;
    }
    
    list($base64Header, $base64Payload, $base64Signature) = $parts;
    
    $headerJson = base64UrlDecode($base64Header);
    $header = json_decode($headerJson, true);
    if (!$header || ($header['alg'] ?? '') !== 'HS256') {
        return null;
    }
    
    $expectedSignature = hash_hmac('sha256', $base64Header . '.' . $base64Payload, getJwtSecret(), true);
    $providedSignature = base64UrlDecode($base64Signature);
    
    if (!hash_equals($expectedSignature, $providedSignature)) {
        return null; // Tampered or invalid signature
    }
    
    $payloadJson = base64UrlDecode($base64Payload);
    $payload = json_decode($payloadJson, true);
    if (!$payload) {
        return null;
    }
    
    // Check expiration
    if (!isset($payload['exp']) || time() >= $payload['exp']) {
        return null; // Expired token
    }
    
    return $payload;
}

/**
 * Generate a cryptographically secure random refresh token string
 */
function generateRefreshToken(): string {
    return bin2hex(random_bytes(32)); // 64 hex characters
}

/**
 * Store refresh token in database (hashed for security)
 */
function storeRefreshToken(int $userId, string $rawRefreshToken, ?string $ip = null, ?string $userAgent = null): bool {
    try {
        $db = getDB();
        $tokenHash = hash('sha256', $rawRefreshToken);
        $expiresAt = date('Y-m-d H:i:s', time() + JWT_REFRESH_TOKEN_EXPIRY);
        
        $stmt = $db->prepare("
            INSERT INTO refresh_tokens (user_id, token_hash, expires_at, revoked, created_at, ip_address, user_agent)
            VALUES (?, ?, ?, 0, CURRENT_TIMESTAMP, ?, ?)
        ");
        return $stmt->execute([$userId, $tokenHash, $expiresAt, $ip, substr($userAgent ?? '', 0, 500)]);
    } catch (\Throwable $e) {
        error_log("storeRefreshToken error: " . $e->getMessage());
        return false;
    }
}

/**
 * Verify refresh token against database and retrieve associated user
 * Returns user array if valid and not revoked/expired, or null
 */
function verifyRefreshToken(string $rawRefreshToken): ?array {
    try {
        $db = getDB();
        $tokenHash = hash('sha256', $rawRefreshToken);
        $now = date('Y-m-d H:i:s');
        
        $stmt = $db->prepare("
            SELECT rt.*, u.id as user_id, u.username, u.email, u.full_name, u.role, u.status
            FROM refresh_tokens rt
            JOIN users u ON rt.user_id = u.id
            WHERE rt.token_hash = ? AND rt.revoked = 0 AND rt.expires_at > ? AND u.status = 'active'
            LIMIT 1
        ");
        $stmt->execute([$tokenHash, $now]);
        $row = $stmt->fetch();
        return $row ?: null;
    } catch (\Throwable $e) {
        error_log("verifyRefreshToken error: " . $e->getMessage());
        return null;
    }
}

/**
 * Revoke a refresh token (blacklist)
 */
function revokeRefreshToken(string $rawRefreshToken): bool {
    try {
        $db = getDB();
        $tokenHash = hash('sha256', $rawRefreshToken);
        $stmt = $db->prepare("UPDATE refresh_tokens SET revoked = 1 WHERE token_hash = ?");
        return $stmt->execute([$tokenHash]);
    } catch (\Throwable $e) {
        error_log("revokeRefreshToken error: " . $e->getMessage());
        return false;
    }
}

/**
 * Set Refresh Token in HttpOnly, Secure, SameSite=None cookie scoped strictly to the API domain
 */
function setRefreshTokenCookie(string $refreshToken): void {
    $isProd = (getenv('APP_ENV') === 'production') || (getenv('RENDER') === 'true');
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || 
               (($_SERVER['SERVER_PORT'] ?? 0) == 443) || 
               (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') ||
               $isProd;
    
    // Cross-origin requests between Vercel and Render require SameSite=None and Secure=true
    $sameSite = $isHttps ? 'None' : 'Lax';
    
    setcookie('hms_refresh_token', $refreshToken, [
        'expires' => time() + JWT_REFRESH_TOKEN_EXPIRY,
        'path' => '/api/v1/auth',
        'domain' => '', // Scoped to API domain only, no shared parent domain
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => $sameSite
    ]);
}

/**
 * Clear Refresh Token cookie
 */
function clearRefreshTokenCookie(): void {
    $isProd = (getenv('APP_ENV') === 'production') || (getenv('RENDER') === 'true');
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || 
               (($_SERVER['SERVER_PORT'] ?? 0) == 443) || 
               (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') ||
               $isProd;
    
    $sameSite = $isHttps ? 'None' : 'Lax';
    
    setcookie('hms_refresh_token', '', [
        'expires' => time() - 3600,
        'path' => '/api/v1/auth',
        'domain' => '',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => $sameSite
    ]);
}

