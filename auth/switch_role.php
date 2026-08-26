<?php
/**
 * Hospital Management System — Instant Role Switcher
 */

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isLoggedIn()) {
    header('Location: /auth/login.php');
    exit;
}

$targetRole = trim($_GET['role'] ?? '');

if (array_key_exists($targetRole, ROLE_DASHBOARDS)) {
    $db = getDB();
    
    // Find active account for target role
    $stmt = $db->prepare("SELECT * FROM users WHERE role = ? AND status = 'active' ORDER BY id ASC LIMIT 1");
    $stmt->execute([$targetRole]);
    $user = $stmt->fetch();
    
    if ($user) {
        setUserSession($user);
        logAudit('login', 'users', $user['id'], 'Switched role workspace to: ' . $user['role']);
        setFlash('success', 'Switched workspace to ' . (ROLE_LABELS[$user['role']] ?? ucfirst($user['role'])) . ' Role!');
        header('Location: ' . ROLE_DASHBOARDS[$user['role']]);
        exit;
    }
}

setFlash('error', 'Invalid role selection.');
header('Location: /');
exit;
