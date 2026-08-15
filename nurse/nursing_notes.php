<?php
/**
 * Hospital Management System — Nurse: Nursing Observation Notes
 */

require_once __DIR__ . '/../includes/auth_middleware.php';
requireRole(['nurse', 'admin', 'doctor']);

$pageTitle = 'Nursing Notes';
$breadcrumbs = [['label' => 'Dashboard', 'url' => '/nurse/dashboard.php'], ['label' => 'Nursing Notes']];

$db = getDB();
$nurse = getNurseByUserId(getUserId());
$nurseId = $nurse['id'] ?? 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $admissionId = (int)$_POST['admission_id'];
    $note = trim($_POST['note']);
    $priority = $_POST['priority'];

    if ($admissionId && $note) {
        $adm = $db->query("SELECT patient_id FROM admissions WHERE id = {$admissionId}")->fetch();
        $stmt = $db->prepare("INSERT INTO nursing_notes (admission_id, patient_id, nurse_id, note, priority) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$admissionId, $adm['patient_id'], $nurseId, $note, $priority]);

        setFlash('success', 'Nursing observation note added.');
        header('Location: /nurse/nursing_notes.php');
        exit;
    }
}

$notesList = $db->query("
    SELECT nn.*, u_p.full_name as patient_name, b.bed_number, u_n.full_name as nurse_name
    FROM nursing_notes nn
    JOIN patients p ON nn.patient_id = p.id
    JOIN users u_p ON p.user_id = u_p.id
    LEFT JOIN admissions a ON nn.admission_id = a.id
    LEFT JOIN beds b ON a.bed_id = b.id
    LEFT JOIN nurses n ON nn.nurse_id = n.id
    LEFT JOIN users u_n ON n.user_id = u_n.id
    ORDER BY nn.created_at DESC
")->fetchAll();

$admissions = $db->query("SELECT a.id, p.uhid, u.full_name, b.bed_number FROM admissions a JOIN patients p ON a.patient_id = p.id JOIN users u ON p.user_id = u.id LEFT JOIN beds b ON a.bed_id = b.id WHERE a.status = 'admitted'")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Nursing Clinical Observation Notes</h1>
        <p class="page-subtitle">Log Shift Notes, Care Logs, and Emergency Flags for Doctors</p>
    </div>
    <button class="btn btn-primary" onclick="openModal('addNoteModal')">
        <i class="fas fa-plus"></i> Add Note
    </button>
</div>

<div class="card">
    <div class="card-body">
        <?php foreach ($notesList as $nt): ?>
        <div class="alert alert-<?= $nt['priority'] === 'critical' ? 'danger' : ($nt['priority'] === 'urgent' ? 'warning' : 'info') ?> mb-16">
            <div>
                <div class="d-flex justify-between align-center mb-4">
                    <strong><?= sanitize($nt['patient_name']) ?> (Bed: <?= $nt['bed_number'] ?>)</strong>
                    <span class="text-xs"><?= formatDateTime($nt['created_at']) ?> | By Nurse <?= sanitize($nt['nurse_name'] ?: 'Staff') ?></span>
                </div>
                <p class="text-sm mb-0"><?= sanitize($nt['note']) ?></p>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<div class="modal-overlay" id="addNoteModal">
    <div class="modal">
        <div class="modal-header">
            <h3>Add Nursing Note</h3>
            <button class="modal-close" onclick="closeModal('addNoteModal')">×</button>
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
                    <label class="form-label">Priority Flag</label>
                    <select name="priority" class="form-control">
                        <option value="normal">Normal Care Log</option>
                        <option value="urgent">Urgent Flag</option>
                        <option value="critical">CRITICAL Alert</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Observation Notes <span class="required">*</span></label>
                    <textarea name="note" class="form-control" required rows="4" placeholder="Patient condition, complaints, vitals trend..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addNoteModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Note</button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
