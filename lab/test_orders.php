<?php
/**
 * Hospital Management System — Lab: Test Orders List
 */

require_once __DIR__ . '/../includes/auth_middleware.php';
requireRole(['lab_technician', 'admin', 'doctor']);

$pageTitle = 'Lab Test Orders';
$breadcrumbs = [['label' => 'Dashboard', 'url' => '/lab/dashboard.php'], ['label' => 'Test Orders']];

$db = getDB();

$orders = $db->query("
    SELECT lo.*, lc.test_name, lc.category, p.uhid, u_p.full_name as patient_name, u_d.full_name as doctor_name
    FROM lab_orders lo
    JOIN lab_test_catalog lc ON lo.test_id = lc.id
    JOIN patients p ON lo.patient_id = p.id
    JOIN users u_p ON p.user_id = u_p.id
    JOIN doctors d ON lo.doctor_id = d.id
    JOIN users u_d ON d.user_id = u_d.id
    ORDER BY lo.ordered_at DESC
")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>All Laboratory Orders</h1>
        <p class="page-subtitle">View incoming diagnostic test requests from OPD and IPD doctors</p>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Patient</th>
                    <th>UHID</th>
                    <th>Test Name</th>
                    <th>Doctor</th>
                    <th>Priority</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($orders as $o): ?>
                <tr>
                    <td><strong><?= sanitize($o['patient_name']) ?></strong></td>
                    <td><code><?= sanitize($o['uhid']) ?></code></td>
                    <td><strong><?= sanitize($o['test_name']) ?></strong></td>
                    <td>Dr. <?= sanitize($o['doctor_name']) ?></td>
                    <td><span class="badge badge-info"><?= strtoupper($o['priority']) ?></span></td>
                    <td><span class="badge <?= $o['status'] === 'completed' ? 'badge-success' : 'badge-warning' ?>"><?= ucfirst($o['status']) ?></span></td>
                    <td>
                        <?php if ($o['status'] !== 'completed'): ?>
                        <a href="/lab/upload_result.php?id=<?= $o['id'] ?>" class="btn btn-sm btn-success"><i class="fas fa-edit"></i> Upload Result</a>
                        <?php else: ?>
                        <span class="text-xs text-muted">Completed</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
