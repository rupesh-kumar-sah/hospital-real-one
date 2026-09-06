<?php
/**
 * REST API — GET /api/v1/receptionist/dashboard
 * Live OPD counts, waiting queue, revenue, and doctor availability.
 */

require_once __DIR__ . '/../../../includes/api_middleware.php';

$auth = requireApiAuth(['receptionist', 'admin']);

try {
    $db = getDB();
    $todayDate = date('Y-m-d');
    $nextDate = date('Y-m-d', strtotime('+1 day'));
    
    // Pending online appointment approvals
    $stmtPending = $db->query("SELECT COUNT(*) as c FROM appointments WHERE status = 'pending_approval'")->fetch();
    $pendingApproval = (int)($stmtPending['c'] ?? 0);
    
    // Today's consolidated stats
    $stmtStats = $db->prepare("
        SELECT 
            COUNT(*) as total_today,
            SUM(CASE WHEN status IN ('scheduled', 'pending_approval') THEN 1 ELSE 0 END) as scheduled_count,
            SUM(CASE WHEN status = 'checked_in' THEN 1 ELSE 0 END) as checked_in_count,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_count
        FROM appointments 
        WHERE appointment_date = ?
    ");
    $stmtStats->execute([$todayDate]);
    $stats = $stmtStats->fetch() ?: [];
    
    // New patients today (SARGable range query)
    $stmtNewPts = $db->prepare("SELECT COUNT(*) as c FROM patients WHERE created_at >= ? AND created_at < ?");
    $stmtNewPts->execute([$todayDate, $nextDate]);
    $newPatients = (int)($stmtNewPts->fetch()['c'] ?? 0);
    
    // Revenue today
    $stmtRev = $db->prepare("SELECT COALESCE(SUM(net_amount), 0) as total FROM billing WHERE created_at >= ? AND created_at < ? AND payment_status = 'paid'");
    $stmtRev->execute([$todayDate, $nextDate]);
    $todayRevenue = (float)($stmtRev->fetch()['total'] ?? 0);
    
    // Recent appointments queue
    $stmtQueue = $db->query("
        SELECT a.id, a.token_number, a.appointment_date, a.appointment_time, a.status, a.reason,
               p.uhid, u_p.full_name as patient_name, u_p.phone as patient_phone,
               u_d.full_name as doctor_name, dep.name as department_name
        FROM appointments a
        JOIN patients p ON a.patient_id = p.id
        JOIN users u_p ON p.user_id = u_p.id
        JOIN doctors d ON a.doctor_id = d.id
        JOIN users u_d ON d.user_id = u_d.id
        LEFT JOIN departments dep ON a.department_id = dep.id
        WHERE a.status IN ('pending_approval', 'scheduled', 'checked_in')
        ORDER BY CASE WHEN a.status = 'pending_approval' THEN 1 WHEN a.status = 'scheduled' THEN 2 ELSE 3 END, a.appointment_date DESC, a.appointment_time ASC
        LIMIT 20
    ");
    $upcomingAppointments = $stmtQueue->fetchAll();
    
    jsonSuccess([
        'stats' => [
            'pending_approvals' => $pendingApproval,
            'today_appointments' => (int)($stats['total_today'] ?? 0),
            'scheduled' => (int)($stats['scheduled_count'] ?? 0),
            'checked_in' => (int)($stats['checked_in_count'] ?? 0),
            'completed' => (int)($stats['completed_count'] ?? 0),
            'new_patients_today' => $newPatients,
            'today_revenue' => $todayRevenue
        ],
        'queue' => $upcomingAppointments
    ], 'Receptionist dashboard data retrieved');
    
} catch (\Throwable $e) {
    jsonError('Failed to fetch receptionist dashboard: ' . $e->getMessage(), 500);
}
