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
        
        $stmt->execute([
            getUserId(),
            getUserName(),
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
