<?php
/**
 * Hospital Management System — API Middleware & Security Gateway
 * Handles JSON response serialization, CORS preflight, JWT Bearer Token validation, and RBAC enforcement.
 */

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
    
    // Whitelist Vercel frontend domains and local dev origins
    $allowedOrigins = [
        'http://localhost:3000',
        'http://localhost:9000',
        'http://127.0.0.1:3000',
        'http://127.0.0.1:9000'
    ];
    
    $customOrigin = getenv('FRONTEND_URL') ?: ($_ENV['FRONTEND_URL'] ?? null);
    if ($customOrigin) {
        $allowedOrigins[] = rtrim($customOrigin, '/');
    }
    
    $requestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if (in_array($requestOrigin, $allowedOrigins) || preg_match('/^https:\/\/[a-zA-Z0-9-]+\.vercel\.app$/', $requestOrigin)) {
        header("Access-Control-Allow-Origin: {$requestOrigin}");
    } else {
        header("Access-Control-Allow-Origin: " . ($allowedOrigins[0] ?? '*'));
    }
    
    header("Access-Control-Allow-Credentials: true");
    header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, PATCH, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Accept");
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
    echo json_encode([
        'success' => false,
        'error' => $error,
        'details' => $details,
        'timestamp' => date('c')
    ], JSON_UNESCAPED_UNICODE);
    exit;
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
    
    if (!preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        jsonError('Missing or malformed Authorization header. Bearer token required.', 401);
    }
    
    $jwt = $matches[1];
    $payload = verifyAccessToken($jwt);
    
    if (!$payload) {
        jsonError('Invalid, tampered, or expired access token.', 401);
    }
    
    $userRole = $payload['role'] ?? '';
    
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
