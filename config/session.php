<?php
/**
 * Hospital Management System — Session Management
 */

require_once __DIR__ . '/security.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Check if user is logged in
 */
function isLoggedIn(): bool {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Get current user's role
 */
function getUserRole(): string {
    return $_SESSION['role'] ?? '';
}

/**
 * Get current user's ID
 */
function getUserId(): int {
    return (int)($_SESSION['user_id'] ?? 0);
}

/**
 * Get current user's full name
 */
function getUserName(): string {
    return $_SESSION['full_name'] ?? '';
}

/**
 * Get current user's data
 */
function getCurrentUser(): array {
    return [
        'id' => getUserId(),
        'username' => $_SESSION['username'] ?? '',
        'email' => $_SESSION['email'] ?? '',
        'full_name' => getUserName(),
        'role' => getUserRole(),
        'avatar' => $_SESSION['avatar'] ?? '',
        'phone' => $_SESSION['phone'] ?? ''
    ];
}

/**
 * Set user session after login
 */
function setUserSession(array $user): void {
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['avatar'] = $user['avatar'] ?? '';
    $_SESSION['phone'] = $user['phone'] ?? '';
    $_SESSION['login_time'] = time();
}

/**
 * Destroy user session (logout)
 */
function destroySession(): void {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
}

/**
 * Set flash message
 */
function setFlash(string $type, string $message): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

/**
 * Get and clear flash message
 */
function getFlash(): ?array {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Generate CSRF token
 */
function generateCSRFToken(): string {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 */
function verifyCSRFToken(string $token): bool {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
