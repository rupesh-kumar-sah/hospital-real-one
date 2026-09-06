<?php
/**
 * Hospital Management System — Security Hardening & Middleware
 * Implements HTTP Security Headers, Brute-Force Protection, CSP, and Anti-Exploit Rules.
 */

require_once __DIR__ . '/errors.php';

// =====================================================
// 1. HTTP SECURITY & CORS HEADERS
// =====================================================
if (!headers_sent()) {
    // Never reflect arbitrary origins when credentials are enabled.
    $requestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $configuredOrigins = preg_split('/\s*,\s*/', getenv('FRONTEND_URL') ?: '', -1, PREG_SPLIT_NO_EMPTY);
    if ($requestOrigin !== '' && in_array(rtrim($requestOrigin, '/'), array_map(
        static fn(string $origin): string => rtrim($origin, '/'),
        $configuredOrigins
    ), true)) {
        header("Access-Control-Allow-Origin: {$requestOrigin}");
        header('Vary: Origin');
        header("Access-Control-Allow-Credentials: true");
    }
    header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-CSRF-Token");
    
    // Handle preflight OPTIONS requests immediately
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
        http_response_code(200);
        exit;
    }
    
    // Prevent Clickjacking attacks
    header('X-Frame-Options: SAMEORIGIN');
    
    // Enable Cross-Site Scripting (XSS) filter
    header('X-XSS-Protection: 1; mode=block');
    
    // Prevent MIME-type sniffing
    header('X-Content-Type-Options: nosniff');
    
    // Control Referrer Information
    header('Referrer-Policy: strict-origin-when-cross-origin');
    
    // Restrict unused browser features
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
    if (getenv('APP_ENV') === 'production') {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

// =====================================================
// 2. SECURE SESSION COOKIE INI SETTINGS
// =====================================================
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    @ini_set('session.cookie_httponly', '1');
    @ini_set('session.use_only_cookies', '1');
    @ini_set('session.cookie_samesite', 'Lax');
}

// =====================================================
// 3. BRUTE-FORCE LOGIN RATE LIMITING
// =====================================================
function checkRateLimit(string $actionKey = 'login', int $maxAttempts = 5, int $decaySeconds = 900): bool {
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
    $key = hash('sha256', $actionKey . '|' . $ip);
    $directory = getenv('RATE_LIMIT_DIRECTORY') ?: __DIR__ . '/../data/rate_limits';

    if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
        // Do not silently disable brute-force protection if storage is unavailable.
        return false;
    }

    $handle = fopen($directory . DIRECTORY_SEPARATOR . $key . '.json', 'c+');
    if ($handle === false || !flock($handle, LOCK_EX)) {
        if (is_resource($handle)) {
            fclose($handle);
        }
        return false;
    }

    $contents = stream_get_contents($handle);
    $data = is_string($contents) ? json_decode($contents, true) : null;
    $now = time();
    if (
        !is_array($data)
        || !isset($data['attempts'], $data['first_attempt'])
        || ($now - (int)$data['first_attempt']) > $decaySeconds
    ) {
        $data = ['attempts' => 0, 'first_attempt' => $now];
    }

    if ((int)$data['attempts'] >= $maxAttempts) {
        flock($handle, LOCK_UN);
        fclose($handle);
        return false;
    }

    $data['attempts']++;
    ftruncate($handle, 0);
    rewind($handle);
    fwrite($handle, json_encode($data, JSON_THROW_ON_ERROR));
    fflush($handle);
    flock($handle, LOCK_UN);
    fclose($handle);
    return true;
}

function resetRateLimit(string $actionKey = 'login'): void {
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
    $key = hash('sha256', $actionKey . '|' . $ip);
    $directory = getenv('RATE_LIMIT_DIRECTORY') ?: __DIR__ . '/../data/rate_limits';
    $path = $directory . DIRECTORY_SEPARATOR . $key . '.json';
    if (is_file($path)) {
        @unlink($path);
    }
}
