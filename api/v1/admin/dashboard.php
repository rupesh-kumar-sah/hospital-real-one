<?php
/**
 * REST API — GET /api/v1/admin/dashboard
 * Hospital-wide metrics, financial summary, department workloads, and recent audit activity.
 */

require_once __DIR__ . '/../../../includes/api_middleware.php';

$auth = requireApiAuth('admin');

try {
    $db = getDB();
    $todayDate = date('Y-m-d');
    $nextDate = date('Y-m-d', strtotime('+1 day'));
    
    // Core Entity Counts
    $totPatients = (int)($db->query("SELECT COUNT(*) as c FROM patients")->fetch()['c'] ?? 0);
    $totDoctors = (int)($db->query("SELECT COUNT(*) as c FROM doctors WHERE status = 'active'")->fetch()['c'] ?? 0);
    $totAppts = (int)($db->query("SELECT COUNT(*) as c FROM appointments")->fetch()['c'] ?? 0);
    
    // Revenue totals
    $totRevenue = (float)($db->query("SELECT COALESCE(SUM(net_amount), 0) as total FROM billing WHERE payment_status = 'paid'")->fetch()['total'] ?? 0);
    
    $stmtRevToday = $db->prepare("SELECT COALESCE(SUM(net_amount), 0) as total FROM billing WHERE created_at >= ? AND created_at < ? AND payment_status = 'paid'");
    $stmtRevToday->execute([$todayDate, $nextDate]);
    $revenueToday = (float)($stmtRevToday->fetch()['total'] ?? 0);
    
    // Today's appointments
    $stmtApptToday = $db->prepare("SELECT COUNT(*) as c FROM appointments WHERE appointment_date = ?");
    $stmtApptToday->execute([$todayDate]);
    $todayAppts = (int)($stmtApptToday->fetch()['c'] ?? 0);
    
    // Department Appointment Workloads
    $stmtDepts = $db->prepare("
        SELECT dep.name, COUNT(a.id) as count
        FROM departments dep
        LEFT JOIN appointments a ON a.department_id = dep.id AND a.appointment_date = ?
        WHERE dep.status = 'active'
        GROUP BY dep.id, dep.name
        ORDER BY count DESC
    ");
    $stmtDepts->execute([$todayDate]);
    $deptWorkloads = $stmtDepts->fetchAll();
    
    // Recent Audit Logs
    $stmtAudit = $db->query("SELECT id, user_name, action, description, ip_address, created_at FROM audit_logs ORDER BY created_at DESC LIMIT 10");
    $recentLogs = $stmtAudit->fetchAll();
    
    jsonSuccess([
        'stats' => [
            'total_patients' => $totPatients,
            'total_doctors' => $totDoctors,
            'total_appointments' => $totAppts,
            'today_appointments' => $todayAppts,
            'total_revenue' => $totRevenue,
            'today_revenue' => $revenueToday
        ],
        'departments_load' => $deptWorkloads,
        'recent_logs' => $recentLogs
    ], 'Admin dashboard analytics retrieved');
    
} catch (\Throwable $e) {
    jsonError('Failed to fetch admin dashboard: ' . $e->getMessage(), 500);
}
