<?php
/**
 * Hospital Management System — Lab Technician Dashboard
 */

require_once __DIR__ . '/../includes/auth_middleware.php';
requireRole(['lab_technician', 'admin']);

$pageTitle = 'Laboratory Dashboard';
$breadcrumbs = [['label' => 'Dashboard']];

$db = getDB();

$pendingCount = $db->query("SELECT COUNT(*) as c FROM lab_orders WHERE status = 'ordered'")->fetch()['c'];
$processingCount = $db->query("SELECT COUNT(*) as c FROM lab_orders WHERE status IN ('sample_collected','processing')")->fetch()['c'];
$completedToday = $db->query("SELECT COUNT(*) as c FROM lab_orders WHERE status = 'completed' AND DATE(ordered_at) = DATE('now')")->fetch()['c'];

$orders = $db->query("
    SELECT lo.*, lc.test_name, lc.category, p.uhid, u_p.full_name as patient_name, u_d.full_name as doctor_name
    FROM lab_orders lo
    JOIN lab_test_catalog lc ON lo.test_id = lc.id
    JOIN patients p ON lo.patient_id = p.id
    JOIN users u_p ON p.user_id = u_p.id
    JOIN doctors d ON lo.doctor_id = d.id
    JOIN users u_d ON d.user_id = u_d.id
    WHERE lo.status != 'completed'
    ORDER BY lo.priority DESC, lo.ordered_at ASC
")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="stats-grid">
    <div class="stat-card" style="--stat-color: #06b6d4;">
        <div class="stat-info">
            <h3>New Test Orders</h3>
            <div class="stat-number"><?= $pendingCount ?></div>
        </div>
        <div class="stat-icon"><i class="fas fa-flask"></i></div>
    </div>

    <div class="stat-card" style="--stat-color: #f59e0b;">
        <div class="stat-info">
            <h3>Samples Processing</h3>
            <div class="stat-number"><?= $processingCount ?></div>
        </div>
        <div class="stat-icon"><i class="fas fa-vial"></i></div>
    </div>

    <div class="stat-card" style="--stat-color: #10b981;">
        <div class="stat-info">
            <h3>Completed Today</h3>
            <div class="stat-number"><?= $completedToday ?></div>
        </div>
        <div class="stat-icon"><i class="fas fa-check-double"></i></div>
    </div>
</div>

<div class="card mb-24">
    <div class="card-header">
        <h3><i class="fas fa-bolt text-warning"></i> Quick Actions</h3>
    </div>
    <div class="card-body">
        <div class="quick-actions">
            <a href="/lab/test_orders.php" class="quick-action-btn">
                <i class="fas fa-list-check"></i>
                <span>Test Orders</span>
            </a>
            <a href="/lab/collect_sample.php" class="quick-action-btn">
                <i class="fas fa-vial"></i>
                <span>Collect Sample</span>
            </a>
            <a href="/lab/upload_result.php" class="quick-action-btn">
                <i class="fas fa-upload"></i>
                <span>Upload Results</span>
            </a>
            <a href="/lab/test_catalog.php" class="quick-action-btn">
                <i class="fas fa-book-medical"></i>
                <span>Test Catalog</span>
            </a>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-flask text-primary"></i> Diagnostic Orders Worklist</h3>
    </div>
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Patient</th>
                    <th>Test Name</th>
                    <th>Ordered By</th>
                    <th>Priority</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($orders as $o): ?>
                <tr>
                    <td><strong><?= sanitize($o['patient_name']) ?></strong><br><code class="text-xs"><?= sanitize($o['uhid']) ?></code></td>
                    <td><strong><?= sanitize($o['test_name']) ?></strong> (<?= sanitize($o['category']) ?>)</td>
                    <td>Dr. <?= sanitize($o['doctor_name']) ?></td>
                    <td>
                        <span class="badge <?= $o['priority'] === 'stat' ? 'badge-danger' : ($o['priority'] === 'urgent' ? 'badge-warning' : 'badge-info') ?>">
                            <?= strtoupper($o['priority']) ?>
                        </span>
                    </td>
                    <td><span class="badge badge-warning"><?= ucfirst(str_replace('_', ' ', $o['status'])) ?></span></td>
                    <td>
                        <?php if ($o['status'] === 'ordered'): ?>
                        <a href="/lab/collect_sample.php?id=<?= $o['id'] ?>" class="btn btn-sm btn-primary"><i class="fas fa-vial"></i> Collect Sample</a>
                        <?php else: ?>
                        <a href="/lab/upload_result.php?id=<?= $o['id'] ?>" class="btn btn-sm btn-success"><i class="fas fa-upload"></i> Enter Result</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
