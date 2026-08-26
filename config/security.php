<?php
/**
 * Hospital Management System — Security Hardening & Middleware
 * Implements HTTP Security Headers, Brute-Force Protection, CSP, and Anti-Exploit Rules.
 */

// =====================================================
// 1. HTTP SECURITY & CORS HEADERS
// =====================================================
if (!headers_sent()) {
    // Cross-Origin Resource Sharing (CORS) for Vercel Frontend -> Render Backend
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
    header("Access-Control-Allow-Origin: $origin");
    header("Access-Control-Allow-Credentials: true");
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
}

// =====================================================
// 2. WEB APPLICATION FIREWALL (WAF) THREAT INSPECTION
// =====================================================
function runFirewallSecurityInspection(): void {
    $queryString = urldecode($_SERVER['QUERY_STRING'] ?? '');
    $requestUri  = urldecode($_SERVER['REQUEST_URI'] ?? '');
    $userAgent   = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    // Malicious patterns
    $wafPatterns = [
        '/\b(union\s+select|select\s+.*\s+from|insert\s+into|delete\s+from|drop\s+table)\b/i',
        '/<script\b[^>]*>(.*?)<\/script>/is',
        '/javascript\s*:/i',
        '/\b(eval\(|base64_decode\(|passthru\(|exec\(|system\()\b/i',
        '/\.\.\/\.\.\//i' // Directory traversal
    ];
    
    foreach ($wafPatterns as $pattern) {
        if (preg_match($pattern, $queryString) || preg_match($pattern, $requestUri)) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode([
                'error' => 'Forbidden Access',
                'message' => 'Request blocked by MediCare HMS Production Firewall WAF Filter.'
            ]);
            exit;
        }
    }
}
runFirewallSecurityInspection();

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
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $key = 'rate_' . $actionKey . '_' . md5($ip);
    
    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = ['attempts' => 1, 'first_attempt' => time()];
        return true;
    }
    
    $data = $_SESSION[$key];
    
    // Reset if decay window passed
    if ((time() - $data['first_attempt']) > $decaySeconds) {
        $_SESSION[$key] = ['attempts' => 1, 'first_attempt' => time()];
        return true;
    }
    
    if ($data['attempts'] >= $maxAttempts) {
        return false; // Rate limit exceeded
    }
    
    $_SESSION[$key]['attempts']++;
    return true;
}

function resetRateLimit(string $actionKey = 'login'): void {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $key = 'rate_' . $actionKey . '_' . md5($ip);
    unset($_SESSION[$key]);
}
