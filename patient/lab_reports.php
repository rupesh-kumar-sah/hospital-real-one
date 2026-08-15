<?php
/**
 * Hospital Management System — Patient Lab Reports View
 */

require_once __DIR__ . '/../includes/auth_middleware.php';
requireRole('patient');

$pageTitle = 'My Lab Reports';
$breadcrumbs = [['label' => 'Dashboard', 'url' => '/patient/dashboard.php'], ['label' => 'Lab Reports']];

$db = getDB();
$patient = getPatientByUserId(getUserId());
$patientId = $patient['id'] ?? 0;

$labs = $db->query("
    SELECT lo.*, lc.test_name, lc.category, lr.result_value, lr.reference_range, lr.interpretation, lr.uploaded_at
    FROM lab_orders lo
    JOIN lab_test_catalog lc ON lo.test_id = lc.id
    LEFT JOIN lab_results lr ON lo.id = lr.lab_order_id
    WHERE lo.patient_id = {$patientId}
    ORDER BY lo.ordered_at DESC
")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>My Diagnostic Lab Reports</h1>
        <p class="page-subtitle">View and download your laboratory test results and blood test reports</p>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Test Name</th>
                    <th>Category</th>
                    <th>Result</th>
                    <th>Normal Reference</th>
                    <th>Interpretation</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($labs as $l): ?>
                <tr>
                    <td><strong><?= sanitize($l['test_name']) ?></strong></td>
                    <td><?= sanitize($l['category']) ?></td>
                    <td><strong class="text-primary"><?= sanitize($l['result_value'] ?: 'Processing...') ?></strong></td>
                    <td><span class="text-xs text-muted"><?= sanitize($l['reference_range'] ?: '-') ?></span></td>
                    <td>
                        <?php if ($l['interpretation']): ?>
                        <span class="badge <?= $l['interpretation'] === 'normal' ? 'badge-success' : 'badge-danger' ?>"><?= ucfirst($l['interpretation']) ?></span>
                        <?php else: ?>
                        -
                        <?php endif; ?>
                    </td>
                    <td><span class="badge <?= $l['status'] === 'completed' ? 'badge-success' : 'badge-warning' ?>"><?= ucfirst($l['status']) ?></span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
