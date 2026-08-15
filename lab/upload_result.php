<?php
/**
 * Hospital Management System — Lab: Upload Test Result
 */

require_once __DIR__ . '/../includes/auth_middleware.php';
requireRole(['lab_technician', 'admin']);

$pageTitle = 'Upload Lab Result';
$breadcrumbs = [['label' => 'Dashboard', 'url' => '/lab/dashboard.php'], ['label' => 'Upload Result']];

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $orderId = (int)$_POST['lab_order_id'];
    $val = trim($_POST['result_value']);
    $range = trim($_POST['reference_range']);
    $interp = $_POST['interpretation'];
    $notes = trim($_POST['result_notes']);

    if ($orderId && $val) {
        $db->beginTransaction();

        $stmtR = $db->prepare("INSERT INTO lab_results (lab_order_id, result_value, reference_range, interpretation, result_notes, technician_id) VALUES (?, ?, ?, ?, ?, ?)");
        $stmtR->execute([$orderId, $val, $range, $interp, $notes, getUserId()]);

        $db->prepare("UPDATE lab_orders SET status = 'completed' WHERE id = ?")->execute([$orderId]);

        // Get lab order details for notification
        $orderInfo = $db->query("
            SELECT lo.*, lc.test_name, p.user_id as patient_user_id, d.user_id as doctor_user_id 
            FROM lab_orders lo 
            JOIN lab_test_catalog lc ON lo.test_id = lc.id 
            JOIN patients p ON lo.patient_id = p.id 
            JOIN doctors d ON lo.doctor_id = d.id 
            WHERE lo.id = {$orderId}
        ")->fetch();

        if ($orderInfo) {
            // Notify doctor
            createNotification($orderInfo['doctor_user_id'], 'Lab Result Completed', "Lab result for test '{$orderInfo['test_name']}' is ready.", 'lab');
            // Notify patient
            createNotification($orderInfo['patient_user_id'], 'Lab Report Ready', "Your lab report for '{$orderInfo['test_name']}' is ready to view.", 'lab');
        }

        $db->commit();
        logAudit('create', 'lab_results', $orderId, "Uploaded lab result for order #{$orderId}");

        setFlash('success', 'Lab test result saved and notifications dispatched to Doctor and Patient!');
        header('Location: /lab/dashboard.php');
        exit;
    }
}

$pendingResults = $db->query("
    SELECT lo.*, lc.test_name, lc.normal_range, p.uhid, u_p.full_name as patient_name
    FROM lab_orders lo
    JOIN lab_test_catalog lc ON lo.test_id = lc.id
    JOIN patients p ON lo.patient_id = p.id
    JOIN users u_p ON p.user_id = u_p.id
    WHERE lo.status IN ('ordered','sample_collected','processing')
")->fetchAll();

$selectedId = (int)($_GET['id'] ?? 0);

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Upload Test Result</h1>
        <p class="page-subtitle">Enter lab test values and interpretations</p>
    </div>
</div>

<div class="card" style="max-width: 650px;">
    <div class="card-body">
        <form method="POST">
            <div class="form-group">
                <label class="form-label">Select Pending Lab Order <span class="required">*</span></label>
                <select name="lab_order_id" class="form-control" required>
                    <option value="">Select Test Order</option>
                    <?php foreach ($pendingResults as $pr): ?>
                    <option value="<?= $pr['id'] ?>" <?= $selectedId === $pr['id'] ? 'selected' : '' ?>><?= sanitize($pr['patient_name']) ?> - <?= sanitize($pr['test_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Result Findings / Value <span class="required">*</span></label>
                <input type="text" name="result_value" class="form-control" required placeholder="e.g. Fasting Sugar: 95 mg/dL, WBC: 7,500/µL">
            </div>

            <div class="form-group">
                <label class="form-label">Reference Range</label>
                <input type="text" name="reference_range" class="form-control" placeholder="70-100 mg/dL">
            </div>

            <div class="form-group">
                <label class="form-label">Interpretation</label>
                <select name="interpretation" class="form-control">
                    <option value="normal">Normal Range</option>
                    <option value="abnormal">Abnormal / High</option>
                    <option value="critical">CRITICAL Alert</option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Technician Remarks</label>
                <textarea name="result_notes" class="form-control" placeholder="Additional notes from lab tech..."></textarea>
            </div>

            <button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> Publish Lab Result</button>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
