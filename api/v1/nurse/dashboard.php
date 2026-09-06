<?php
/**
 * REST API — GET /api/v1/nurse/dashboard
 * Inpatient metrics, occupied beds, and ward patients list.
 */

require_once __DIR__ . '/../../../includes/api_middleware.php';

$auth = requireApiAuth(['nurse', 'admin']);

try {
    $db = getDB();
    
    // Admitted patients count
    $stmtAdm = $db->query("SELECT COUNT(*) as c FROM admissions WHERE status = 'admitted'")->fetch();
    $admittedCount = (int)($stmtAdm['c'] ?? 0);
    
    // Occupied vs Available beds
    $stmtBeds = $db->query("
        SELECT 
            SUM(CASE WHEN status = 'occupied' THEN 1 ELSE 0 END) as occupied_beds,
            SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END) as available_beds
        FROM beds
    ")->fetch() ?: [];
    
    // Current Inpatient Roster
    $stmtRoster = $db->query("
        SELECT a.id as admission_id, a.admission_date, a.reason,
               p.id as patient_id, p.uhid, u.full_name as patient_name, p.gender, p.blood_group,
               b.bed_number, w.name as ward_name, u_d.full_name as doctor_name
        FROM admissions a
        JOIN patients p ON a.patient_id = p.id
        JOIN users u ON p.user_id = u.id
        LEFT JOIN beds b ON a.bed_id = b.id
        LEFT JOIN wards w ON b.ward_id = w.id
        LEFT JOIN doctors d ON a.doctor_id = d.id
        LEFT JOIN users u_d ON d.user_id = u_d.id
        WHERE a.status = 'admitted'
        ORDER BY w.name ASC, b.bed_number ASC
    ");
    $roster = $stmtRoster->fetchAll();
    
    jsonSuccess([
        'stats' => [
            'admitted_patients' => $admittedCount,
            'occupied_beds' => (int)($stmtBeds['occupied_beds'] ?? 0),
            'available_beds' => (int)($stmtBeds['available_beds'] ?? 0)
        ],
        'patients' => $roster
    ], 'Nurse dashboard retrieved');
    
} catch (\Throwable $e) {
    jsonError('Failed to fetch nurse dashboard: ' . $e->getMessage(), 500);
}
