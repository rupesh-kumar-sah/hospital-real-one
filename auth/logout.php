<?php
/**
 * Hospital Management System — Logout
 */

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_middleware.php';

if (isLoggedIn()) {
    logAudit('logout', 'users', getUserId(), 'User logged out: ' . getUserName());
}

destroySession();

header('Location: /auth/login.php');
exit;
