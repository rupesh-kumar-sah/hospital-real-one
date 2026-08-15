<?php
/**
 * Hospital Management System — Nurse: Vitals Recording
 */

require_once __DIR__ . '/../includes/auth_middleware.php';
requireRole(['nurse', 'admin', 'doctor']);

$pageTitle = 'Record Vitals';
$breadcrumbs = [['label' => 'Dashboard', 'url' => '/nurse/dashboard.php'], ['label' => 'Record Vitals']];

$db = getDB();
$nurse = getNurseByUserId(getUserId());
$nurseId = $nurse['id'] ?? 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $patientId = (int)$_POST['patient_id'];
    $admissionId = (int)($_POST['admission_id'] ?? 0);
    $sys = (int)$_POST['bp_systolic'];
    $dia = (int)$_POST['bp_diastolic'];
    $temp = (float)$_POST['temperature'];
    $pulse = (int)$_POST['pulse'];
    $spo2 = (float)$_POST['spo2'];

    if ($patientId && $temp) {
        $stmt = $db->prepare("INSERT INTO vitals (patient_id, admission_id, nurse_id, blood_pressure_systolic, blood_pressure_diastolic, temperature, pulse, oxygen_saturation, recorded_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)");
        $stmt->execute([$patientId, $admissionId ?: null, $nurseId, $sys, $dia, $temp, $pulse, $spo2]);

        setFlash('success', 'Patient vital signs chart updated.');
        header('Location: /nurse/dashboard.php');
        exit;
    }
}

$patients = $db->query("SELECT p.id, p.uhid, u.full_name FROM patients p JOIN users u ON p.user_id = u.id ORDER BY u.full_name")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Patient Vitals Charting</h1>
        <p class="page-subtitle">Record Blood Pressure, Temperature, Pulse Rate and Oxygen Saturation (SpO2)</p>
    </div>
</div>

<div class="card" style="max-width: 650px;">
    <div class="card-body">
        <form method="POST">
            <div class="form-group">
                <label class="form-label">Select Patient <span class="required">*</span></label>
                <select name="patient_id" class="form-control" required>
                    <option value="">Select Patient</option>
                    <?php foreach ($patients as $p): ?>
                    <option value="<?= $p['id'] ?>"><?= sanitize($p['full_name']) ?> (<?= $p['uhid'] ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">BP Systolic (mmHg)</label>
                    <input type="number" name="bp_systolic" class="form-control" value="120">
                </div>
                <div class="form-group">
                    <label class="form-label">BP Diastolic (mmHg)</label>
                    <input type="number" name="bp_diastolic" class="form-control" value="80">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Temperature (°F) <span class="required">*</span></label>
                    <input type="number" name="temperature" class="form-control" step="0.1" value="98.6" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Pulse (bpm)</label>
                    <input type="number" name="pulse" class="form-control" value="72">
                </div>
                <div class="form-group">
                    <label class="form-label">SpO2 (%)</label>
                    <input type="number" name="spo2" class="form-control" step="0.1" value="98.0">
                </div>
            </div>

            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Vitals Entry</button>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
