<?php
/**
 * Hospital Management System — Entry Point
 * Redirects to login or appropriate dashboard
 */

require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/constants.php';

// Initialize database (auto-creates on first run)
getDB();

if (isLoggedIn()) {
    $dashboard = ROLE_DASHBOARDS[getUserRole()] ?? '/auth/login.php';
    header('Location: ' . $dashboard);
} else {
    header('Location: /auth/login.php');
}
exit;
