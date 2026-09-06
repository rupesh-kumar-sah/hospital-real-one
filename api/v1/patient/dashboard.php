<?php
/**
 * REST API — GET /api/v1/patient/dashboard
 * Aggregated summary for authenticated patient.
 */

require_once __DIR__ . '/../../../includes/api_middleware.php';

$auth = requireApiAuth('patient');
$userId = (int)$auth['sub'];

try {
    $db = getDB();
    
    // Get patient record
    $stmtP = $db->prepare("SELECT id, uhid FROM patients WHERE user_id = ?");
    $stmtP->execute([$userId]);
    $patient = $stmtP->fetch();
    
    if (!$patient) {
        jsonError('Patient profile not found for this account.', 404);
    }
    
    $patientId = (int)$patient['id'];
    
    // Total visits / completed appointments
    $stmtVisits = $db->prepare("SELECT COUNT(*) as c FROM appointments WHERE patient_id = ? AND status = 'completed'");
    $stmtVisits->execute([$patientId]);
    $totalVisits = (int)($stmtVisits->fetch()['c'] ?? 0);
    
    // Prescriptions count
    $stmtRx = $db->prepare("SELECT COUNT(*) as c FROM prescriptions WHERE patient_id = ?");
    $stmtRx->execute([$patientId]);
    $totalRx = (int)($stmtRx->fetch()['c'] ?? 0);
    
    // Lab reports count
    $stmtLab = $db->prepare("SELECT COUNT(*) as c FROM lab_orders WHERE patient_id = ?");
    $stmtLab->execute([$patientId]);
    $totalLab = (int)($stmtLab->fetch()['c'] ?? 0);
    
    // Unpaid billing balance
    $stmtUnpaid = $db->prepare("SELECT COALESCE(SUM(net_amount), 0) as total FROM billing WHERE patient_id = ? AND payment_status = 'unpaid'");
    $stmtUnpaid->execute([$patientId]);
    $unpaidTotal = (float)($stmtUnpaid->fetch()['total'] ?? 0);
    
    // Upcoming appointments
    $stmtUpcoming = $db->prepare("
        SELECT a.id, a.appointment_date, a.appointment_time, a.status, a.token_number, a.reason,
               u_d.full_name as doctor_name, d.specialization, dep.name as department_name
        FROM appointments a
        JOIN doctors d ON a.doctor_id = d.id
        JOIN users u_d ON d.user_id = u_d.id
        LEFT JOIN departments dep ON a.department_id = dep.id
        WHERE a.patient_id = ? AND a.status IN ('scheduled', 'pending_approval', 'checked_in')
        ORDER BY a.appointment_date ASC, a.appointment_time ASC
        LIMIT 5
    ");
    $stmtUpcoming->execute([$patientId]);
    $upcomingAppointments = $stmtUpcoming->fetchAll();
    
    // Recent completed prescriptions
    $stmtRecentRx = $db->prepare("
        SELECT pr.id, pr.created_at, pr.status, u_d.full_name as doctor_name
        FROM prescriptions pr
        JOIN doctors d ON pr.doctor_id = d.id
        JOIN users u_d ON d.user_id = u_d.id
        WHERE pr.patient_id = ?
        ORDER BY pr.created_at DESC
        LIMIT 5
    ");
    $stmtRecentRx->execute([$patientId]);
    $recentPrescriptions = $stmtRecentRx->fetchAll();
    
    jsonSuccess([
        'uhid' => $patient['uhid'],
        'patient_id' => $patientId,
        'stats' => [
            'total_visits' => $totalVisits,
            'total_prescriptions' => $totalRx,
            'total_lab_reports' => $totalLab,
            'unpaid_balance' => $unpaidTotal
        ],
        'upcoming_appointments' => $upcomingAppointments,
        'recent_prescriptions' => $recentPrescriptions
    ]);
    
} catch (\Throwable $e) {
    jsonServerError('Failed to fetch patient dashboard', $e);
}
