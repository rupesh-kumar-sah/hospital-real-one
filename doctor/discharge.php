<?php
/**
 * Hospital Management System — Doctor: Patient Discharge Summary
 */

require_once __DIR__ . '/../includes/auth_middleware.php';
requireRole(['doctor', 'admin']);

$pageTitle = 'Discharge Patient & Summary';
$breadcrumbs = [['label' => 'Dashboard', 'url' => '/doctor/dashboard.php'], ['label' => 'Discharge']];

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $admissionId = (int)$_POST['admission_id'];
    $summary = trim($_POST['discharge_summary']);

    if ($admissionId) {
        $adm = $db->query("SELECT bed_id FROM admissions WHERE id = {$admissionId}")->fetch();
        $bedId = $adm['bed_id'];

        $db->beginTransaction();
        $stmt = $db->prepare("UPDATE admissions SET status = 'discharged', discharge_date = CURRENT_TIMESTAMP, discharge_summary = ? WHERE id = ?");
        $stmt->execute([$summary, $admissionId]);

        if ($bedId) {
            $db->prepare("UPDATE beds SET status = 'available' WHERE id = ?")->execute([$bedId]);
        }
        $db->commit();

        setFlash('success', 'Patient discharged successfully and bed made available.');
        header('Location: /doctor/dashboard.php');
        exit;
    }
}

$admitted = $db->query("
    SELECT a.*, p.uhid, u_p.full_name as patient_name, b.bed_number, w.name as ward_name
    FROM admissions a
    JOIN patients p ON a.patient_id = p.id
    JOIN users u_p ON p.user_id = u_p.id
    LEFT JOIN beds b ON a.bed_id = b.id
    LEFT JOIN wards w ON a.ward_id = w.id
    WHERE a.status = 'admitted'
")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Patient Discharge Summary</h1>
        <p class="page-subtitle">Process IPD patient discharge and generate final summary</p>
    </div>
</div>

<div class="card" style="max-width: 650px;">
    <div class="card-body">
        <form method="POST">
            <div class="form-group">
                <label class="form-label">Select Admitted Patient <span class="required">*</span></label>
                <select name="admission_id" class="form-control" required>
                    <option value="">Select Patient</option>
                    <?php foreach ($admitted as $adm): ?>
                    <option value="<?= $adm['id'] ?>"><?= sanitize($adm['patient_name']) ?> (<?= $adm['ward_name'] ?> - Bed <?= $adm['bed_number'] ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Discharge Summary & Instructions <span class="required">*</span></label>
                <textarea name="discharge_summary" class="form-control" rows="5" required placeholder="Patient condition on discharge, follow-up advice, home medications..."></textarea>
            </div>

            <button type="submit" class="btn btn-primary"><i class="fas fa-door-open"></i> Complete Discharge</button>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
