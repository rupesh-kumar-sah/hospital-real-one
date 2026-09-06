<?php
/**
 * REST API — /api/v1/receptionist/appointments
 * GET: Lists all appointments with filters (date, status, doctor)
 * POST: Approves/reschedules/creates an appointment
 */

require_once __DIR__ . '/../../../includes/api_middleware.php';

$auth = requireApiAuth(['receptionist', 'admin']);
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $date = $_GET['date'] ?? '';
    $status = $_GET['status'] ?? '';
    $doctorId = (int)($_GET['doctor_id'] ?? 0);
    
    $where = [];
    $params = [];
    
    if (!empty($date)) {
        $where[] = "a.appointment_date = ?";
        $params[] = $date;
    }
    if (!empty($status)) {
        $where[] = "a.status = ?";
        $params[] = $status;
    }
    if ($doctorId > 0) {
        $where[] = "a.doctor_id = ?";
        $params[] = $doctorId;
    }
    
    $whereSql = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
    
    try {
        $stmt = $db->prepare("
            SELECT a.*, p.uhid, u_p.full_name as patient_name, u_p.phone as patient_phone,
                   u_d.full_name as doctor_name, dep.name as department_name, d.consultation_fee
            FROM appointments a
            JOIN patients p ON a.patient_id = p.id
            JOIN users u_p ON p.user_id = u_p.id
            JOIN doctors d ON a.doctor_id = d.id
            JOIN users u_d ON d.user_id = u_d.id
            LEFT JOIN departments dep ON a.department_id = dep.id
            {$whereSql}
            ORDER BY a.appointment_date DESC, a.token_number ASC
            LIMIT 100
        ");
        $stmt->execute($params);
        $appointments = $stmt->fetchAll();
        
        jsonSuccess($appointments, 'Appointments fetched');
    } catch (\Throwable $e) {
        jsonError('Failed to fetch appointments: ' . $e->getMessage(), 500);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = getJsonBody();
    $action = $body['action'] ?? 'create';
    
    if ($action === 'approve') {
        $apptId = (int)($body['appointment_id'] ?? 0);
        $stmt = $db->prepare("UPDATE appointments SET status = 'scheduled' WHERE id = ?");
        $stmt->execute([$apptId]);
        jsonSuccess(null, 'Appointment approved and scheduled');
    }
    
    if ($action === 'cancel') {
        $apptId = (int)($body['appointment_id'] ?? 0);
        $stmt = $db->prepare("UPDATE appointments SET status = 'cancelled' WHERE id = ?");
        $stmt->execute([$apptId]);
        jsonSuccess(null, 'Appointment cancelled');
    }
    
    jsonError('Invalid action', 400);
}

jsonError('Method Not Allowed', 405);
