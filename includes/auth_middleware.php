<?php
/**
 * Hospital Management System — Auth Middleware
 * Role-based access control for all pages
 */

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/functions.php';

/**
 * Require user to be logged in
 */
function requireLogin(): void {
    if (!isLoggedIn()) {
        setFlash('error', 'Please log in to access this page.');
        header('Location: /auth/login.php');
        exit;
    }
    enforcePasswordChange();

    // Central CSRF enforcement for every authenticated state-changing request.
    requireCSRF();
}

/**
 * Keep administrator-created accounts out of every dashboard until the
 * temporary password has been replaced. The change page itself is allowed.
 */
function enforcePasswordChange(): void {
    $script = basename((string)($_SERVER['SCRIPT_FILENAME'] ?? ''));
    if ($script === 'change_password.php' && getUserRole() === 'admin' && empty($_SESSION['mfa_verified'])) {
        destroySession();
        setFlash('error', 'Administrator MFA verification is required.');
        header('Location: /auth/login.php');
        exit;
    }
    if ($script === 'change_password.php' || $script === 'mfa.php' || $script === 'logout.php' || $script === 'login.php') {
        return;
    }
    $mustChange = $_SESSION['must_change_password'] ?? null;
    if ($mustChange === null) {
        try {
            $stmt = getDB()->prepare('SELECT must_change_password FROM users WHERE id = ? LIMIT 1');
            $stmt->execute([getUserId()]);
            $mustChange = (bool)($stmt->fetchColumn() ?? false);
            $_SESSION['must_change_password'] = $mustChange;
        } catch (Throwable $e) {
            error_log('Password change enforcement error: ' . $e->getMessage());
            return;
        }
    }
    if (getUserRole() === 'admin' && empty($_SESSION['mfa_verified'])) {
        destroySession();
        setFlash('error', 'Administrator MFA verification is required.');
        header('Location: /auth/login.php');
        exit;
    }
    if ($mustChange) {
        setFlash('warning', 'Please change your temporary password before continuing.');
        header('Location: /auth/change_password.php');
        exit;
    }
}

/**
 * Require specific role(s) to access a page
 * @param string|array $roles Single role or array of allowed roles
 */
function requireRole(string|array $roles): void {
    requireLogin();
    
    if (is_string($roles)) {
        $roles = [$roles];
    }
    
    if (!in_array(getUserRole(), $roles)) {
        setFlash('error', 'You do not have permission to access this page.');
        $dashboard = ROLE_DASHBOARDS[getUserRole()] ?? '/auth/login.php';
        header('Location: ' . $dashboard);
        exit;
    }
}

/**
 * Redirect to role-specific dashboard
 */
function redirectToDashboard(): void {
    $role = getUserRole();
    $dashboard = ROLE_DASHBOARDS[$role] ?? '/auth/login.php';
    header('Location: ' . $dashboard);
    exit;
}

/**
 * Check if current user has a specific role
 */
function hasRole(string $role): bool {
    return getUserRole() === $role;
}

/**
 * Check if current user has any of the specified roles
 */
function hasAnyRole(array $roles): bool {
    return in_array(getUserRole(), $roles);
}

/**
 * Log an audit event
 */
function logAudit(string $action, string $tableName = '', int $recordId = 0, string $description = '', ?string $oldValues = null, ?string $newValues = null): void {
    try {
        $db = getDB();
        
        $stmt = $db->prepare('INSERT INTO audit_logs (user_id, user_name, action, table_name, record_id, description, old_values, new_values, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        
        $userId = getUserId();
        $userIdParam = ($userId > 0) ? $userId : null;
        $userNameParam = getUserName() ?: 'Guest/System';
        
        $stmt->execute([
            $userIdParam,
            $userNameParam,
            $action,
            $tableName,
            $recordId,
            $description,
            $oldValues,
            $newValues,
            $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
            $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
    } catch (Exception $e) {
        // Silently fail — audit logging should never break the app
        error_log('Audit log error: ' . $e->getMessage());
    }
}

/**
 * Require valid CSRF token on POST requests
 */
function requireCSRF(): void {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!verifyCSRFToken($token)) {
            http_response_code(403);
            setFlash('error', 'Invalid or expired security token. Please try again.');
            header('Location: ' . ($_SERVER['REQUEST_URI'] ?? '/'));
            exit;
        }
    }
}

/**
 * Enforce patient ownership for patient role (prevents IDOR)
 */
function requirePatientOwnership(int $patientId): void {
    if (getUserRole() === 'patient') {
        $patient = getPatientByUserId(getUserId());
        if (!$patient || (int)$patient['id'] !== $patientId) {
            http_response_code(403);
            setFlash('error', 'Access denied. You can only view your own records.');
            header('Location: /patient/dashboard.php');
            exit;
        }
    }
}

/**
 * Create a notification for a user
 */
function createNotification(int $userId, string $title, string $message, string $type = 'info', string $link = ''): void {
    try {
        $db = getDB();
        
        $stmt = $db->prepare('INSERT INTO notifications (user_id, title, message, type, link) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$userId, $title, $message, $type, $link]);
    } catch (Exception $e) {
        error_log('Notification error: ' . $e->getMessage());
    }
}
