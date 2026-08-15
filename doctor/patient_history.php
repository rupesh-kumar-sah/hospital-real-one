<?php
/**
 * Hospital Management System — Doctor: Patient Medical History
 */

require_once __DIR__ . '/../includes/auth_middleware.php';
requireRole(['doctor', 'nurse', 'admin', 'patient']);

$pageTitle = 'Patient Medical History';
$breadcrumbs = [['label' => 'Dashboard', 'url' => '/doctor/dashboard.php'], ['label' => 'Patient History']];

$db = getDB();
$patientId = (int)($_GET['patient_id'] ?? 0);

$records = [];
$selectedPatient = null;

if ($patientId) {
    $stmtP = $db->prepare("SELECT p.*, u.full_name, u.email, u.phone FROM patients p JOIN users u ON p.user_id = u.id WHERE p.id = ?");
    $stmtP->execute([$patientId]);
    $selectedPatient = $stmtP->fetch();

    $stmtR = $db->prepare("
        SELECT mr.*, u_d.full_name as doctor_name, d.specialization
        FROM medical_records mr
        JOIN doctors d ON mr.doctor_id = d.id
        JOIN users u_d ON d.user_id = u_d.id
        WHERE mr.patient_id = ?
        ORDER BY mr.created_at DESC
    ");
    $stmtR->execute([$patientId]);
    $records = $stmtR->fetchAll();
}

$patients = $db->query("SELECT p.id, p.uhid, u.full_name FROM patients p JOIN users u ON p.user_id = u.id ORDER BY u.full_name")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Patient Medical History & Timeline</h1>
        <p class="page-subtitle">View past consultations, diagnoses, clinical notes and treatments</p>
    </div>
</div>

<div class="card mb-24">
    <div class="card-body">
        <form method="GET" class="d-flex gap-12">
            <select name="patient_id" class="form-control" onchange="this.form.submit()">
                <option value="">Select Patient to view history...</option>
                <?php foreach ($patients as $p): ?>
                <option value="<?= $p['id'] ?>" <?= $patientId === $p['id'] ? 'selected' : '' ?>><?= sanitize($p['full_name']) ?> (<?= $p['uhid'] ?>)</option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>
</div>

<?php if ($selectedPatient): ?>
<div class="card mb-24">
    <div class="card-header">
        <h3><i class="fas fa-id-card text-primary"></i> Patient Profile: <?= sanitize($selectedPatient['full_name']) ?></h3>
        <code class="font-bold text-primary"><?= sanitize($selectedPatient['uhid']) ?></code>
    </div>
    <div class="card-body grid-3 text-sm">
        <div><strong>Gender / DOB:</strong> <?= calculateAge($selectedPatient['date_of_birth']) ?> (<?= ucfirst($selectedPatient['gender']) ?>)</div>
        <div><strong>Blood Group:</strong> <?= $selectedPatient['blood_group'] ?: 'N/A' ?></div>
        <div><strong>Known Allergies:</strong> <span class="text-danger"><?= sanitize($selectedPatient['allergies'] ?: 'None') ?></span></div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-history text-info"></i> Consultation History</h3>
    </div>
    <div class="card-body">
        <?php if (empty($records)): ?>
        <div class="empty-state"><p>No prior consultation records found for this patient.</p></div>
        <?php else: ?>
        <div class="timeline">
            <?php foreach ($records as $rec): ?>
            <div class="timeline-item">
                <div class="timeline-time"><?= formatDateTime($rec['created_at']) ?> | Consulted by <strong>Dr. <?= sanitize($rec['doctor_name']) ?></strong> (<?= sanitize($rec['specialization']) ?>)</div>
                <div class="timeline-content">
                    <h4 class="text-primary mb-4">Diagnosis: <?= sanitize($rec['diagnosis']) ?></h4>
                    <p class="text-sm"><strong>Symptoms:</strong> <?= sanitize($rec['symptoms']) ?></p>
                    <p class="text-sm text-muted mt-4"><strong>Notes:</strong> <?= sanitize($rec['clinical_notes'] ?: 'None') ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
