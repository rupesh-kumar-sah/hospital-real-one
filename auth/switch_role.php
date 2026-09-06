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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Method Not Allowed');
}
require_once __DIR__ . '/../includes/auth_middleware.php';
requireCSRF();

$targetRole = trim($_POST['role'] ?? $_GET['role'] ?? '');

/*
 * Never load another user's account here. The previous implementation selected
 * the first active account for the requested role, allowing any authenticated
 * user to impersonate staff. A role switch can only keep the current session's
 * role and is retained as a compatibility redirect for existing links.
 */
if ($targetRole === getUserRole() && array_key_exists($targetRole, ROLE_DASHBOARDS)) {
    header('Location: ' . ROLE_DASHBOARDS[$targetRole]);
    exit;
}

setFlash('error', 'Invalid role selection.');
header('Location: /');
exit;
