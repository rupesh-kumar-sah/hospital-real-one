<?php
/**
 * Hospital Management System — Doctor: IPD Patient Admission Request
 */

require_once __DIR__ . '/../includes/auth_middleware.php';
requireRole(['doctor', 'admin']);

$pageTitle = 'Request Inpatient Admission (IPD)';
$breadcrumbs = [['label' => 'Dashboard', 'url' => '/doctor/dashboard.php'], ['label' => 'Admit Patient']];

$db = getDB();
$doctor = getDoctorByUserId(getUserId());
$doctorId = $doctor['id'] ?? 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $patientId = (int)$_POST['patient_id'];
    $bedId = (int)$_POST['bed_id'];
    $reason = trim($_POST['reason']);

    if ($patientId && $bedId) {
        $bed = $db->query("SELECT ward_id FROM beds WHERE id = {$bedId}")->fetch();
        $wardId = $bed['ward_id'];

        $db->beginTransaction();
        $stmt = $db->prepare("INSERT INTO admissions (patient_id, doctor_id, bed_id, ward_id, admit_date, status, reason) VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP, 'admitted', ?)");
        $stmt->execute([$patientId, $doctorId, $bedId, $wardId, $reason]);

        $db->prepare("UPDATE beds SET status = 'occupied' WHERE id = ?")->execute([$bedId]);
        $db->commit();

        setFlash('success', 'Patient admitted to inpatient ward successfully.');
        header('Location: /doctor/dashboard.php');
        exit;
    }
}

$patients = $db->query("SELECT p.id, p.uhid, u.full_name FROM patients p JOIN users u ON p.user_id = u.id ORDER BY u.full_name")->fetchAll();
$availableBeds = $db->query("SELECT b.*, w.name as ward_name FROM beds b JOIN wards w ON b.ward_id = w.id WHERE b.status = 'available' ORDER BY w.name, b.bed_number")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Inpatient (IPD) Admission Request</h1>
        <p class="page-subtitle">Assign ward bed and admit patient to hospital for inpatient care</p>
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

            <div class="form-group">
                <label class="form-label">Select Available Bed <span class="required">*</span></label>
                <select name="bed_id" class="form-control" required>
                    <option value="">Select Ward / Bed</option>
                    <?php foreach ($availableBeds as $b): ?>
                    <option value="<?= $b['id'] ?>"><?= sanitize($b['ward_name']) ?> - Bed <?= sanitize($b['bed_number']) ?> (Rs. <?= $b['daily_charge'] ?>/day)</option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Admission Reason / Diagnosis <span class="required">*</span></label>
                <textarea name="reason" class="form-control" required placeholder="Reason for inpatient admission..."></textarea>
            </div>

            <button type="submit" class="btn btn-primary"><i class="fas fa-procedures"></i> Confirm Patient Admission</button>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
