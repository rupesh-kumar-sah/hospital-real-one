<?php
/**
 * REST API — GET /api/v1/admin/audit-logs
 * System-wide audit trail with user and action filters.
 */

require_once __DIR__ . '/../../../includes/api_middleware.php';

$auth = requireApiAuth('admin');

try {
    $db = getDB();
    $action = $_GET['action'] ?? '';
    $userId = (int)($_GET['user_id'] ?? 0);
    
    $where = [];
    $params = [];
    
    if (!empty($action)) {
        $where[] = "action = ?";
        $params[] = $action;
    }
    if ($userId > 0) {
        $where[] = "user_id = ?";
        $params[] = $userId;
    }
    
    $whereSql = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
    
    $stmt = $db->prepare("SELECT * FROM audit_logs {$whereSql} ORDER BY created_at DESC LIMIT 100");
    $stmt->execute($params);
    $logs = $stmt->fetchAll();
    
    jsonSuccess($logs, 'Audit logs retrieved');
} catch (\Throwable $e) {
    jsonError('Failed to fetch audit logs: ' . $e->getMessage(), 500);
}
