<?php
/**
 * Hospital Management System — Doctor: EMR Consultation Workspace
 */

require_once __DIR__ . '/../includes/auth_middleware.php';
requireRole(['doctor', 'admin']);

$pageTitle = 'EMR Consultation Workspace';
$breadcrumbs = [['label' => 'Dashboard', 'url' => '/doctor/dashboard.php'], ['label' => 'Consultation']];

$db = getDB();
$doctor = getDoctorByUserId(getUserId());
$doctorId = $doctor['id'] ?? 0;

$apptId = (int)($_GET['appointment_id'] ?? 0);
$patient = null;
$appointment = null;

if ($apptId) {
    $stmtAppt = $db->prepare("
        SELECT a.*, p.id as patient_id, p.uhid, u_p.full_name as patient_name, p.date_of_birth, p.gender, p.blood_group, p.allergies
        FROM appointments a
        JOIN patients p ON a.patient_id = p.id
        JOIN users u_p ON p.user_id = u_p.id
        WHERE a.id = ?
    ");
    $stmtAppt->execute([$apptId]);
    $appointment = $stmtAppt->fetch();
    if ($appointment) {
        $patient = $appointment;
    }
}

// Handle Form Submission (Save Medical Record + Prescription)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pId = (int)$_POST['patient_id'];
    $aId = (int)($_POST['appointment_id'] ?? 0);
    $diagnosis = trim($_POST['diagnosis']);
    $symptoms = trim($_POST['symptoms']);
    $notes = trim($_POST['clinical_notes']);

    if ($pId && $diagnosis) {
        $db->beginTransaction();

        // 1. Insert Medical Record
        $stmtMed = $db->prepare("INSERT INTO medical_records (patient_id, doctor_id, appointment_id, diagnosis, symptoms, clinical_notes) VALUES (?, ?, ?, ?, ?, ?)");
        $stmtMed->execute([$pId, $doctorId, $aId ?: null, $diagnosis, $symptoms, $notes]);
        $recordId = $db->lastInsertId();

        // 2. Insert Prescription if medicines added
        $medNames = $_POST['drug_name'] ?? [];
        $dosages = $_POST['dosage'] ?? [];
        $freqs = $_POST['frequency'] ?? [];
        $durations = $_POST['duration'] ?? [];

        if (!empty($medNames) && !empty($medNames[0])) {
            $stmtRx = $db->prepare("INSERT INTO prescriptions (medical_record_id, patient_id, doctor_id, appointment_id, status) VALUES (?, ?, ?, ?, 'pending')");
            $stmtRx->execute([$recordId, $pId, $doctorId, $aId ?: null]);
            $rxId = $db->lastInsertId();

            for ($i = 0; $i < count($medNames); $i++) {
                if (!empty($medNames[$i])) {
                    $stmtItem = $db->prepare("INSERT INTO prescription_items (prescription_id, drug_name, dosage, frequency, duration) VALUES (?, ?, ?, ?, ?)");
                    $stmtItem->execute([$rxId, $medNames[$i], $dosages[$i] ?? '', $freqs[$i] ?? '', $durations[$i] ?? '']);
                }
            }

            // Notify Pharmacist
            $pharmacists = $db->query("SELECT id FROM users WHERE role = 'pharmacist'")->fetchAll();
            foreach ($pharmacists as $ph) {
                createNotification($ph['id'], 'New Prescription Pending', "New prescription #Rx-{$rxId} issued by Dr. " . getUserName(), 'prescription');
            }
        }

        // 3. Mark appointment completed
        if ($aId) {
            $db->prepare("UPDATE appointments SET status = 'completed' WHERE id = ?")->execute([$aId]);
        }

        // Notify patient
        $patientUser = $db->query("SELECT user_id FROM patients WHERE id = {$pId}")->fetch();
        if ($patientUser) {
            createNotification($patientUser['user_id'], 'Consultation Completed', "Dr. " . getUserName() . " completed your consultation. Diagnosis: {$diagnosis}", 'info');
        }

        $db->commit();

        logAudit('create', 'medical_records', $recordId, "Recorded consultation for patient ID {$pId}");
        setFlash('success', 'Consultation notes and digital prescription saved successfully!');
        header('Location: /doctor/dashboard.php');
        exit;
    }
}

$patientsList = $db->query("SELECT p.id, p.uhid, u.full_name FROM patients p JOIN users u ON p.user_id = u.id ORDER BY u.full_name")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>EMR Consultation</h1>
        <p class="page-subtitle">Record clinical findings, diagnosis, and electronic prescriptions (e-Rx)</p>
    </div>
</div>

<?php if ($appointment): ?>
<div class="alert alert-info mb-24">
    <i class="fas fa-user-injured"></i> <strong>Consulting Patient:</strong> <?= sanitize($appointment['patient_name']) ?> (UHID: <?= $appointment['uhid'] ?>) | Token #<?= $appointment['token_number'] ?> | Age/Gender: <?= calculateAge($appointment['date_of_birth']) ?> / <?= ucfirst($appointment['gender'] ?? '') ?>
</div>
<?php endif; ?>

<form method="POST">
    <div class="grid-2 mb-24">
        <!-- Medical Notes -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-stethoscope text-primary"></i> Clinical Diagnosis & Findings</h3>
            </div>
            <div class="card-body">
                <?php if (!$appointment): ?>
                <div class="form-group">
                    <label class="form-label">Select Patient <span class="required">*</span></label>
                    <select name="patient_id" class="form-control" required>
                        <option value="">Select Patient</option>
                        <?php foreach ($patientsList as $pl): ?>
                        <option value="<?= $pl['id'] ?>"><?= sanitize($pl['full_name']) ?> (<?= $pl['uhid'] ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php else: ?>
                <input type="hidden" name="patient_id" value="<?= $appointment['patient_id'] ?>">
                <input type="hidden" name="appointment_id" value="<?= $appointment['id'] ?>">
                <?php endif; ?>

                <div class="form-group">
                    <label class="form-label">Chief Symptoms <span class="required">*</span></label>
                    <input type="text" name="symptoms" class="form-control" required placeholder="e.g. High fever, dry cough, body pain">
                </div>

                <div class="form-group">
                    <label class="form-label">Primary Diagnosis <span class="required">*</span></label>
                    <input type="text" name="diagnosis" class="form-control" required placeholder="e.g. Upper Respiratory Tract Infection (URTI)">
                </div>

                <div class="form-group">
                    <label class="form-label">Clinical Notes & Advice</label>
                    <textarea name="clinical_notes" class="form-control" rows="4" placeholder="Advice rest for 3 days, drink plenty of warm fluids..."></textarea>
                </div>
            </div>
        </div>

        <!-- Electronic Prescription (e-Rx) -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-prescription text-success"></i> Electronic Prescription (e-Rx)</h3>
            </div>
            <div class="card-body">
                <div id="medicineRows">
                    <div class="form-row mb-12">
                        <input type="text" name="drug_name[]" class="form-control" placeholder="Medicine (e.g. Paracetamol 500mg)">
                        <input type="text" name="dosage[]" class="form-control" placeholder="Dosage (500mg)">
                        <input type="text" name="frequency[]" class="form-control" placeholder="1-0-1">
                        <input type="text" name="duration[]" class="form-control" placeholder="5 Days">
                    </div>
                    <div class="form-row mb-12">
                        <input type="text" name="drug_name[]" class="form-control" placeholder="Medicine (e.g. Amoxicillin 500mg)">
                        <input type="text" name="dosage[]" class="form-control" placeholder="Dosage (500mg)">
                        <input type="text" name="frequency[]" class="form-control" placeholder="1-1-1">
                        <input type="text" name="duration[]" class="form-control" placeholder="7 Days">
                    </div>
                </div>
                <button type="button" class="btn btn-sm btn-secondary" onclick="addMedRow()"><i class="fas fa-plus"></i> Add More Medicines</button>
            </div>
        </div>
    </div>

    <div class="card p-20 d-flex justify-between align-center">
        <span class="text-muted">Saving will automatically send e-Prescription to Pharmacy</span>
        <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-check-circle"></i> Complete Consultation & Save EMR</button>
    </div>
</form>

<script>
function addMedRow() {
    let div = document.createElement('div');
    div.className = 'form-row mb-12';
    div.innerHTML = `
        <input type="text" name="drug_name[]" class="form-control" placeholder="Medicine Name">
        <input type="text" name="dosage[]" class="form-control" placeholder="Dosage">
        <input type="text" name="frequency[]" class="form-control" placeholder="Frequency">
        <input type="text" name="duration[]" class="form-control" placeholder="Duration">
    `;
    document.getElementById('medicineRows').appendChild(div);
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
