<?php
/**
 * REST API — GET /api/v1/doctors
 * Catalog of active hospital doctors with specialization and consultation fee.
 */

require_once __DIR__ . '/../../includes/api_middleware.php';

try {
    $db = getDB();
    $deptId = (int)($_GET['department_id'] ?? 0);
    
    $where = ["u.status = 'active'", "d.status = 'active'"];
    $params = [];
    
    if ($deptId > 0) {
        $where[] = "d.department_id = ?";
        $params[] = $deptId;
    }
    
    $whereSql = "WHERE " . implode(" AND ", $where);
    
    $stmt = $db->prepare("
        SELECT d.id as doctor_id, u.id as user_id, u.full_name, u.email, u.phone, u.avatar,
               d.specialization, d.qualification, d.experience_years, d.consultation_fee,
               dep.id as department_id, dep.name as department_name
        FROM doctors d
        JOIN users u ON d.user_id = u.id
        LEFT JOIN departments dep ON d.department_id = dep.id
        {$whereSql}
        ORDER BY u.full_name ASC
        LIMIT 100
    ");
    $stmt->execute($params);
    $doctors = $stmt->fetchAll();
    
    jsonSuccess($doctors, 'Doctors retrieved');
} catch (\Throwable $e) {
    jsonServerError('Failed to fetch doctors', $e);
}
