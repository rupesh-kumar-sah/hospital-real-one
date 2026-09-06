<?php
/**
 * REST API — /api/v1/patient/appointments
 * GET: Returns patient appointments list
 * POST: Books a new OPD appointment
 */

require_once __DIR__ . '/../../../includes/api_middleware.php';

$auth = requireApiAuth('patient');
$userId = (int)$auth['sub'];

$db = getDB();
$stmtP = $db->prepare("SELECT id, uhid FROM patients WHERE user_id = ?");
$stmtP->execute([$userId]);
$patient = $stmtP->fetch();

if (!$patient) {
    jsonError('Patient profile not found.', 404);
}
$patientId = (int)$patient['id'];

// =====================================================
// GET: Fetch Patient Appointments
// =====================================================
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $stmt = $db->prepare("
            SELECT a.id, a.appointment_date, a.appointment_time, a.status, a.token_number, a.reason, a.created_at,
                   u_d.full_name as doctor_name, d.specialization, d.consultation_fee, dep.name as department_name
            FROM appointments a
            JOIN doctors d ON a.doctor_id = d.id
            JOIN users u_d ON d.user_id = u_d.id
            LEFT JOIN departments dep ON a.department_id = dep.id
            WHERE a.patient_id = ?
            ORDER BY a.appointment_date DESC, a.appointment_time DESC
        ");
        $stmt->execute([$patientId]);
        $appointments = $stmt->fetchAll();
        
        jsonSuccess($appointments, 'Appointments fetched');
    } catch (\Throwable $e) {
        jsonError('Failed to fetch appointments: ' . $e->getMessage(), 500);
    }
}

// =====================================================
// POST: Book New Appointment
// =====================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = getJsonBody();
    
    $doctorId = (int)($body['doctor_id'] ?? 0);
    $departmentId = !empty($body['department_id']) ? (int)$body['department_id'] : null;
    $date = trim($body['appointment_date'] ?? '');
    $time = trim($body['appointment_time'] ?? '09:00:00');
    $reason = trim($body['reason'] ?? 'General Consultation');
    
    if ($doctorId <= 0 || empty($date)) {
        jsonError('Doctor and appointment date are required.', 422);
    }
    
    try {
        // Look up doctor & department
        $docStmt = $db->prepare("SELECT user_id, department_id, consultation_fee FROM doctors WHERE id = ?");
        $docStmt->execute([$doctorId]);
        $doctor = $docStmt->fetch();
        
        if (!$doctor) {
            jsonError('Selected doctor does not exist.', 404);
        }
        
        if (!$departmentId && !empty($doctor['department_id'])) {
            $departmentId = (int)$doctor['department_id'];
        }
        
        $token = generateToken($doctorId, $date);
        
        $insertStmt = $db->prepare("
            INSERT INTO appointments (patient_id, doctor_id, department_id, appointment_date, appointment_time, token_number, status, reason, created_at)
            VALUES (?, ?, ?, ?, ?, ?, 'pending_approval', ?, CURRENT_TIMESTAMP)
        ");
        $insertStmt->execute([$patientId, $doctorId, $departmentId, $date, $time, $token, $reason]);
        $appointmentId = (int)$db->lastInsertId();
        
        // Notify Receptionists and Patient
        try {
            $notifPatient = $db->prepare("INSERT INTO notifications (user_id, title, message, type, is_read, created_at) VALUES (?, 'Appointment Request Received', 'Your appointment request for " . $date . " is pending confirmation.', 'appointment', 0, CURRENT_TIMESTAMP)");
            $notifPatient->execute([$userId]);
        } catch (\Throwable $e) {}
        
        jsonSuccess([
            'appointment_id' => $appointmentId,
            'token_number' => $token,
            'status' => 'pending_approval',
            'appointment_date' => $date
        ], 'Appointment booked successfully. Awaiting receptionist approval.', 201);
        
    } catch (\Throwable $e) {
        jsonError('Failed to book appointment: ' . $e->getMessage(), 500);
    }
}

jsonError('Method Not Allowed', 405);
