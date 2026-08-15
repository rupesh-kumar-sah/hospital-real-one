<?php
/**
 * Hospital Management System — Nurse: Medication Administration Record (MAR)
 */

require_once __DIR__ . '/../includes/auth_middleware.php';
requireRole(['nurse', 'admin', 'doctor']);

$pageTitle = 'Medication Administration Record (MAR)';
$breadcrumbs = [['label' => 'Dashboard', 'url' => '/nurse/dashboard.php'], ['label' => 'MAR']];

$db = getDB();
$nurse = getNurseByUserId(getUserId());
$nurseId = $nurse['id'] ?? 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $admissionId = (int)$_POST['admission_id'];
    $drugName = trim($_POST['drug_name']);
    $dosage = trim($_POST['dosage']);

    if ($admissionId && $drugName) {
        $adm = $db->query("SELECT patient_id FROM admissions WHERE id = {$admissionId}")->fetch();
        $stmt = $db->prepare("INSERT INTO medication_administration (admission_id, patient_id, nurse_id, drug_name, dosage, administered_at, status) VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP, 'administered')");
        $stmt->execute([$admissionId, $adm['patient_id'], $nurseId, $drugName, $dosage]);

        setFlash('success', "Medication '{$drugName}' marked as administered.");
        header('Location: /nurse/medication.php');
        exit;
    }
}

$admissions = $db->query("
    SELECT a.id, p.uhid, u.full_name, b.bed_number 
    FROM admissions a 
    JOIN patients p ON a.patient_id = p.id 
    JOIN users u ON p.user_id = u.id 
    LEFT JOIN beds b ON a.bed_id = b.id 
    WHERE a.status = 'admitted'
")->fetchAll();

$mars = $db->query("
    SELECT ma.*, u_p.full_name as patient_name, u_n.full_name as nurse_name 
    FROM medication_administration ma
    JOIN patients p ON ma.patient_id = p.id
    JOIN users u_p ON p.user_id = u_p.id
    LEFT JOIN nurses n ON ma.nurse_id = n.id
    LEFT JOIN users u_n ON n.user_id = u_n.id
    ORDER BY ma.administered_at DESC
")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Medication Administration Record (MAR)</h1>
        <p class="page-subtitle">Track dosage schedules and mark administered medications</p>
    </div>
    <button class="btn btn-primary" onclick="openModal('addMarModal')">
        <i class="fas fa-pills"></i> Record Dose Given
    </button>
</div>

<div class="card">
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Patient</th>
                    <th>Medicine & Dosage</th>
                    <th>Administered By</th>
                    <th>Time</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($mars as $m): ?>
                <tr>
                    <td><strong><?= sanitize($m['patient_name']) ?></strong></td>
                    <td><strong><?= sanitize($m['drug_name']) ?></strong> (<?= sanitize($m['dosage']) ?>)</td>
                    <td>Nurse <?= sanitize($m['nurse_name'] ?: 'Staff') ?></td>
                    <td><?= formatDateTime($m['administered_at']) ?></td>
                    <td><span class="badge badge-success"><i class="fas fa-check"></i> Administered</span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal-overlay" id="addMarModal">
    <div class="modal">
        <div class="modal-header">
            <h3>Record Administered Medication</h3>
            <button class="modal-close" onclick="closeModal('addMarModal')">×</button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Admitted Patient <span class="required">*</span></label>
                    <select name="admission_id" class="form-control" required>
                        <?php foreach ($admissions as $a): ?>
                        <option value="<?= $a['id'] ?>"><?= sanitize($a['full_name']) ?> (Bed: <?= $a['bed_number'] ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Medicine Name <span class="required">*</span></label>
                    <input type="text" name="drug_name" class="form-control" required placeholder="e.g. Paracetamol 500mg IV">
                </div>
                <div class="form-group">
                    <label class="form-label">Dosage</label>
                    <input type="text" name="dosage" class="form-control" placeholder="1 Ampoule / 1 Tablet">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addMarModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Entry</button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
