<?php
/**
 * Hospital Management System — Security Hardening & Middleware
 * Implements HTTP Security Headers, Brute-Force Protection, CSP, and Anti-Exploit Rules.
 */

// =====================================================
// 1. HTTP SECURITY HEADERS
// =====================================================
if (!headers_sent()) {
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
    
    // Content Security Policy (CSP)
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com; font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com; img-src 'self' data: blob:;");
}

// =====================================================
// 2. SECURE SESSION COOKIE INI SETTINGS
// =====================================================
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_samesite', 'Lax');

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
