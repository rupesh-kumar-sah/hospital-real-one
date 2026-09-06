<?php
/**
 * Hospital Management System — API Middleware & Security Gateway
 * Handles JSON response serialization, CORS preflight, JWT Bearer Token validation, and RBAC enforcement.
 */

require_once __DIR__ . '/../config/errors.php';
require_once __DIR__ . '/../config/jwt.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/functions.php';

// =====================================================
// 1. CORS & HTTP API SECURITY HEADERS
// =====================================================
function initApiHeaders(): void {
    if (headers_sent()) {
        return;
    }
    
    $allowedOrigins = preg_split(
        '/\s*,\s*/',
        getenv('FRONTEND_URL') ?: ($_ENV['FRONTEND_URL'] ?? ''),
        -1,
        PREG_SPLIT_NO_EMPTY
    );
    if (getenv('APP_ENV') !== 'production') {
        $allowedOrigins = array_merge($allowedOrigins, [
            'http://localhost:3000',
            'http://localhost:9000',
            'http://127.0.0.1:3000',
            'http://127.0.0.1:9000'
        ]);
    }
    
    $requestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if (in_array(rtrim($requestOrigin, '/'), array_map(
        static fn(string $origin): string => rtrim($origin, '/'),
        $allowedOrigins
    ), true)) {
        header("Access-Control-Allow-Origin: {$requestOrigin}");
        header('Vary: Origin');
        header("Access-Control-Allow-Credentials: true");
    }

    header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, PATCH, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Accept, X-CSRF-Token");
    header("Content-Type: application/json; charset=UTF-8");
    
    // Strict API Security Headers
    header("X-Content-Type-Options: nosniff");
    header("X-Frame-Options: DENY");
    header("Referrer-Policy: strict-origin-when-cross-origin");
    header("Content-Security-Policy: default-src 'none'");
    
    // Handle OPTIONS Preflight requests immediately
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
        http_response_code(200);
        echo json_encode(['status' => 'preflight_ok']);
        exit;
    }
}

// Automatically apply API headers
initApiHeaders();

/**
 * Reject cookie-authenticated requests from untrusted browser origins.
 */
function requireApiCookieOrigin(): void {
    if (empty($_COOKIE['hms_refresh_token'])) {
        return;
    }

    $origin = rtrim((string)($_SERVER['HTTP_ORIGIN'] ?? ''), '/');
    if ($origin === '') {
        return;
    }

    $allowedOrigins = preg_split(
        '/\s*,\s*/',
        getenv('FRONTEND_URL') ?: ($_ENV['FRONTEND_URL'] ?? ''),
        -1,
        PREG_SPLIT_NO_EMPTY
    );
    if (getenv('APP_ENV') !== 'production') {
        $allowedOrigins = array_merge($allowedOrigins, [
            'http://localhost:3000',
            'http://localhost:9000',
            'http://127.0.0.1:3000',
            'http://127.0.0.1:9000'
        ]);
    }
    $allowedOrigins = array_map(static fn(string $value): string => rtrim($value, '/'), $allowedOrigins);
    if (!in_array($origin, $allowedOrigins, true)) {
        jsonError('Cross-site request rejected.', 403);
    }
}

// =====================================================
// 2. UNIFIED JSON RESPONSE HELPERS
// =====================================================
function jsonSuccess(mixed $data = null, string $message = 'Success', int $statusCode = 200): void {
    http_response_code($statusCode);
    echo json_encode([
        'success' => true,
        'message' => $message,
        'data' => $data,
        'timestamp' => date('c')
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function jsonError(string $error = 'An error occurred', int $statusCode = 400, ?array $details = null): void {
    http_response_code($statusCode);
    if ($statusCode >= 500) {
        $error = 'Internal server error.';
        $details = null;
    }
    echo json_encode([
        'success' => false,
        'error' => $error,
        'details' => $details,
        'timestamp' => date('c')
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Return a safe server error without exposing database/implementation details.
 */
function jsonServerError(string $context, Throwable $exception): void {
    error_log($context . ': ' . $exception->getMessage());
    jsonError($context, 500);
}

// =====================================================
// 3. JSON REQUEST BODY PARSER
// =====================================================
function getJsonBody(): array {
    $raw = file_get_contents('php://input');
    if (empty($raw)) {
        return $_POST ?: [];
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

// =====================================================
// 4. JWT BEARER AUTHENTICATION & RBAC ENFORCEMENT
// =====================================================
function requireApiAuth(string|array|null $allowedRoles = null): array {
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    
    if (!preg_match('/\\ABearer[ \\t]+(\\S+)\\z/i', $authHeader, $matches)) {
        jsonError('Missing or malformed Authorization header. Bearer token required.', 401);
    }
    
    $jwt = $matches[1];
    $payload = verifyAccessToken($jwt);
    
    if (!$payload) {
        jsonError('Invalid, tampered, or expired access token.', 401);
    }
    
    $userRole = $payload['role'] ?? '';

    // A forced password change also applies to already-issued short-lived JWTs.
    try {
        $userStmt = getDB()->prepare('SELECT role, status, must_change_password FROM users WHERE id = ? LIMIT 1');
        $userStmt->execute([(int)($payload['sub'] ?? 0)]);
        $user = $userStmt->fetch(PDO::FETCH_ASSOC);
        if (!$user || $user['status'] !== 'active' || !hash_equals((string)$user['role'], (string)($payload['role'] ?? ''))) {
            jsonError('Invalid or inactive access token.', 401);
        }
        if ((bool)$user['must_change_password']) {
            jsonError('Password change required before API access.', 403, ['password_change_required' => true]);
        }
    } catch (\Throwable $e) {
        error_log('API token subject validation error: ' . $e->getMessage());
        jsonError('Authentication service unavailable.', 503);
    }
    
    // Check role authorization if role restrictions are defined
    if ($allowedRoles !== null) {
        $roles = is_array($allowedRoles) ? $allowedRoles : [$allowedRoles];
        if (!in_array($userRole, $roles, true)) {
            jsonError('Forbidden. Insufficient permissions for this resource.', 403, [
                'user_role' => $userRole,
                'required_roles' => $roles
            ]);
        }
    }
    
    return $payload;
}
