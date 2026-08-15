<?php
/**
 * Hospital Management System — Doctor: Order Lab Test
 */

require_once __DIR__ . '/../includes/auth_middleware.php';
requireRole(['doctor', 'admin']);

$pageTitle = 'Order Lab Tests';
$breadcrumbs = [['label' => 'Dashboard', 'url' => '/doctor/dashboard.php'], ['label' => 'Order Lab Test']];

$db = getDB();
$doctor = getDoctorByUserId(getUserId());
$doctorId = $doctor['id'] ?? 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $patientId = (int)$_POST['patient_id'];
    $testIds = $_POST['test_ids'] ?? [];
    $priority = $_POST['priority'];
    $notes = trim($_POST['clinical_notes']);

    if ($patientId && !empty($testIds)) {
        foreach ($testIds as $tId) {
            $stmt = $db->prepare("INSERT INTO lab_orders (patient_id, doctor_id, test_id, priority, status, clinical_notes) VALUES (?, ?, ?, ?, 'ordered', ?)");
            $stmt->execute([$patientId, $doctorId, (int)$tId, $priority, $notes]);
        }
        setFlash('success', 'Lab test order(s) submitted to Laboratory module successfully.');
        header('Location: /doctor/dashboard.php');
        exit;
    }
}

$patients = $db->query("SELECT p.id, p.uhid, u.full_name FROM patients p JOIN users u ON p.user_id = u.id ORDER BY u.full_name")->fetchAll();
$tests = $db->query("SELECT * FROM lab_test_catalog WHERE status = 'active' ORDER BY category, test_name")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Order Laboratory & Imaging Tests</h1>
        <p class="page-subtitle">Send diagnostic test requests to the lab technician</p>
    </div>
</div>

<div class="card" style="max-width: 700px;">
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
                <label class="form-label">Select Tests to Order <span class="required">*</span></label>
                <div style="max-height: 250px; overflow-y: auto; border: 1px solid var(--gray-200); border-radius: var(--border-radius-xs); padding: 12px;">
                    <?php foreach ($tests as $t): ?>
                    <label class="form-check mb-8">
                        <input type="checkbox" name="test_ids[]" value="<?= $t['id'] ?>">
                        <span><strong><?= sanitize($t['test_name']) ?></strong> (<?= sanitize($t['category']) ?>) - Rs. <?= $t['price'] ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Priority</label>
                <select name="priority" class="form-control">
                    <option value="normal">Normal Routine</option>
                    <option value="urgent">Urgent</option>
                    <option value="stat">STAT / Emergency</option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Clinical Indications / Notes</label>
                <textarea name="clinical_notes" class="form-control" placeholder="Clinical reason for ordering test..."></textarea>
            </div>

            <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Send Test Order to Lab</button>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
