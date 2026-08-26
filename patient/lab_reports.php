<?php
/**
 * Hospital Management System — Patient: Lab Reports
 */

require_once __DIR__ . '/../includes/auth_middleware.php';
requireRole('patient');

$pageTitle = 'Lab Test Reports';
$breadcrumbs = [['label' => 'Dashboard', 'url' => '/patient/dashboard.php'], ['label' => 'Lab Reports']];

$db = getDB();
$patient = getPatientByUserId(getUserId());
$patientId = $patient['id'] ?? 0;

$labReports = $db->query("
    SELECT lo.*, u_d.full_name as doctor_name
    FROM lab_orders lo
    LEFT JOIN doctors d ON lo.doctor_id = d.id
    LEFT JOIN users u_d ON d.user_id = u_d.id
    WHERE lo.patient_id = {$patientId}
    ORDER BY lo.ordered_at DESC
")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Diagnostic & Lab Reports</h1>
        <p class="page-subtitle">Track laboratory orders and test results</p>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Order #</th>
                    <th>Doctor</th>
                    <th>Date</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($labReports)): ?>
                <tr><td colspan="4"><div class="empty-state"><p>No lab test reports recorded yet</p></div></td></tr>
                <?php else: ?>
                <?php foreach ($labReports as $lab): ?>
                <tr>
                    <td><strong>#LAB-<?= $lab['id'] ?></strong></td>
                    <td>Dr. <?= sanitize($lab['doctor_name'] ?? 'Hospital Lab') ?></td>
                    <td><?= formatDate($lab['ordered_at'] ?? '') ?></td>
                    <td><span class="badge badge-warning"><?= ucfirst($lab['status']) ?></span></td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
