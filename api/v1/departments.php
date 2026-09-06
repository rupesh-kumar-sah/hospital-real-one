<?php
/**
 * REST API — GET /api/v1/departments
 * Public/authenticated catalog of hospital clinical departments.
 */

require_once __DIR__ . '/../../includes/api_middleware.php';

try {
    $db = getDB();
    $stmt = $db->query("SELECT id, name, description FROM departments WHERE status = 'active' ORDER BY name ASC");
    jsonSuccess($stmt->fetchAll(), 'Departments retrieved');
} catch (\Throwable $e) {
    jsonError('Failed to fetch departments: ' . $e->getMessage(), 500);
}
