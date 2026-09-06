<?php
/**
 * REST API — POST /api/v1/doctor/consultation
 * Completes clinical consultation: records diagnosis, vitals, prescriptions, lab orders, and updates appointment status.
 */

require_once __DIR__ . '/../../../includes/api_middleware.php';
require_once __DIR__ . '/../../../config/encryption.php';

$auth = requireApiAuth('doctor');
$userId = (int)$auth['sub'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method Not Allowed. POST required.', 405);
}

$body = getJsonBody();

$appointmentId = (int)($body['appointment_id'] ?? 0);
$patientId = (int)($body['patient_id'] ?? 0);
$symptoms = trim($body['symptoms'] ?? '');
$diagnosis = trim($body['diagnosis'] ?? '');
$treatmentPlan = trim($body['treatment_plan'] ?? '');
$notes = trim($body['notes'] ?? '');
$followUpDate = $body['follow_up_date'] ?? null;

// Prescriptions array [{ drug_name, dosage, frequency, duration, instructions, quantity }]
$prescriptions = $body['prescriptions'] ?? [];

// Lab test IDs array [1, 5, 12]
$labTestIds = $body['lab_tests'] ?? [];

// Vitals { blood_pressure, pulse_rate, temperature, respiratory_rate, spo2 }
$vitals = $body['vitals'] ?? [];

if ($patientId <= 0) {
    jsonError('patient_id is required.', 422);
}

try {
    $db = getDB();
    
    // Look up doctor
    $stmtDoc = $db->prepare("SELECT id FROM doctors WHERE user_id = ?");
    $stmtDoc->execute([$userId]);
    $doctor = $stmtDoc->fetch();
    if (!$doctor) {
        jsonError('Doctor profile not found.', 404);
    }
    $doctorId = (int)$doctor['id'];
    
    $db->beginTransaction();
    
    // 1. Record Medical EMR Record (Encrypted)
    $encryptedDiagnosis = encryptData($diagnosis);
    $encryptedPlan = encryptData($treatmentPlan);
    $encryptedNotes = encryptData($notes);
    
    $stmtMR = $db->prepare("
        INSERT INTO medical_records (patient_id, doctor_id, appointment_id, record_date, symptoms, diagnosis, treatment_plan, notes, follow_up_date, created_at)
        VALUES (?, ?, ?, CURRENT_DATE, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
    ");
    $stmtMR->execute([
        $patientId, $doctorId, $appointmentId ?: null,
        $symptoms, $encryptedDiagnosis, $encryptedPlan, $encryptedNotes, $followUpDate ?: null
    ]);
    $recordId = (int)$db->lastInsertId();
    
    // 2. Record Vitals if provided
    if (!empty($vitals) && is_array($vitals)) {
        $stmtVit = $db->prepare("
            INSERT INTO vitals (patient_id, recorded_by, blood_pressure, pulse_rate, temperature, respiratory_rate, spo2, recorded_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
        ");
        $stmtVit->execute([
            $patientId, $userId,
            $vitals['blood_pressure'] ?? null,
            $vitals['pulse_rate'] ?? null,
            $vitals['temperature'] ?? null,
            $vitals['respiratory_rate'] ?? null,
            $vitals['spo2'] ?? null
        ]);
    }
    
    // 3. Record Prescriptions & Prescription Items
    $prescriptionId = null;
    if (!empty($prescriptions) && is_array($prescriptions)) {
        $stmtRx = $db->prepare("
            INSERT INTO prescriptions (patient_id, doctor_id, appointment_id, status, notes, created_at)
            VALUES (?, ?, ?, 'pending', ?, CURRENT_TIMESTAMP)
        ");
        $stmtRx->execute([$patientId, $doctorId, $appointmentId ?: null, $notes]);
        $prescriptionId = (int)$db->lastInsertId();
        
        $stmtItem = $db->prepare("
            INSERT INTO prescription_items (prescription_id, drug_name, dosage, frequency, duration, instructions, quantity)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        foreach ($prescriptions as $p) {
            if (!empty($p['drug_name'])) {
                $stmtItem->execute([
                    $prescriptionId,
                    trim($p['drug_name']),
                    trim($p['dosage'] ?? '1 tab'),
                    trim($p['frequency'] ?? '1-0-1'),
                    trim($p['duration'] ?? '5 days'),
                    trim($p['instructions'] ?? 'After meals'),
                    max(1, (int)($p['quantity'] ?? 10))
                ]);
            }
        }
    }
    
    // 4. Record Lab Orders
    $createdLabOrders = [];
    if (!empty($labTestIds) && is_array($labTestIds)) {
        $stmtLab = $db->prepare("
            INSERT INTO lab_orders (patient_id, doctor_id, test_id, appointment_id, status, priority, clinical_notes, ordered_at)
            VALUES (?, ?, ?, ?, 'ordered', 'routine', ?, CURRENT_TIMESTAMP)
        ");
        
        foreach ($labTestIds as $testId) {
            $stmtLab->execute([$patientId, $doctorId, (int)$testId, $appointmentId ?: null, $symptoms]);
            $createdLabOrders[] = (int)$db->lastInsertId();
        }
    }
    
    // 5. Update appointment status to completed
    if ($appointmentId > 0) {
        $stmtAppt = $db->prepare("UPDATE appointments SET status = 'completed', updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmtAppt->execute([$appointmentId]);
    }
    
    $db->commit();
    
    jsonSuccess([
        'medical_record_id' => $recordId,
        'prescription_id' => $prescriptionId,
        'lab_orders_count' => count($createdLabOrders),
        'appointment_id' => $appointmentId,
        'status' => 'completed'
    ], 'Consultation completed and clinical records saved', 201);
    
} catch (\Throwable $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    jsonServerError('Failed to record consultation', $e);
}
