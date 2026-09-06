<?php
/**
 * REST API — GET /api/v1/doctor/dashboard
 * Live queue, completed patient metrics, and lab order alerts for authenticated doctor.
 */

require_once __DIR__ . '/../../../includes/api_middleware.php';

$auth = requireApiAuth('doctor');
$userId = (int)$auth['sub'];

try {
    $db = getDB();
    
    // Find doctor record
    $stmtDoc = $db->prepare("SELECT id, department_id, consultation_fee, max_patients_per_day FROM doctors WHERE user_id = ?");
    $stmtDoc->execute([$userId]);
    $doctor = $stmtDoc->fetch();
    
    if (!$doctor) {
        jsonError('Doctor profile not found.', 404);
    }
    $doctorId = (int)$doctor['id'];
    $todayDate = date('Y-m-d');
    
    // Today's Queue
    $stmtQueue = $db->prepare("
        SELECT a.id, a.token_number, a.appointment_date, a.appointment_time, a.status, a.reason,
               p.id as patient_id, p.uhid, u.full_name as patient_name, p.gender, p.date_of_birth, p.blood_group, p.allergies
        FROM appointments a
        JOIN patients p ON a.patient_id = p.id
        JOIN users u ON p.user_id = u.id
        WHERE a.doctor_id = ? AND a.appointment_date = ? AND a.status IN ('scheduled', 'checked_in', 'in_progress')
        ORDER BY a.token_number ASC
        LIMIT 100
    ");
    $stmtQueue->execute([$doctorId, $todayDate]);
    $queue = $stmtQueue->fetchAll();
    
    // Completed Today
    $stmtComp = $db->prepare("SELECT COUNT(*) as c FROM appointments WHERE doctor_id = ? AND appointment_date = ? AND status = 'completed'");
    $stmtComp->execute([$doctorId, $todayDate]);
    $completedCount = (int)($stmtComp->fetch()['c'] ?? 0);
    
    // Total Unique Patients
    $stmtTotPts = $db->prepare("SELECT COUNT(DISTINCT patient_id) as c FROM appointments WHERE doctor_id = ? AND status = 'completed'");
    $stmtTotPts->execute([$doctorId]);
    $totalPatients = (int)($stmtTotPts->fetch()['c'] ?? 0);
    
    // Active Inpatient Admissions under Doctor
    $stmtAdm = $db->prepare("SELECT COUNT(*) as c FROM admissions WHERE doctor_id = ? AND status = 'admitted'");
    $stmtAdm->execute([$doctorId]);
    $activeAdmissions = (int)($stmtAdm->fetch()['c'] ?? 0);
    
    jsonSuccess([
        'doctor_id' => $doctorId,
        'stats' => [
            'queue_count' => count($queue),
            'completed_today' => $completedCount,
            'total_patients' => $totalPatients,
            'active_admissions' => $activeAdmissions
        ],
        'queue' => $queue
    ], 'Doctor dashboard retrieved');
    
} catch (\Throwable $e) {
    jsonServerError('Failed to fetch doctor dashboard', $e);
}
