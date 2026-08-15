<?php
/**
 * Hospital Management System — Admin: Audit Logs
 */

require_once __DIR__ . '/../includes/auth_middleware.php';
requireRole('admin');

$pageTitle = 'Audit Logs';
$breadcrumbs = [['label' => 'Dashboard', 'url' => '/admin/dashboard.php'], ['label' => 'Audit Logs']];

$db = getDB();
$page = max(1, (int)($_GET['page'] ?? 1));

$pagination = paginate("SELECT * FROM audit_logs ORDER BY created_at DESC", [], $page, 20);
$logs = $pagination['data'];

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>System Audit Trail</h1>
        <p class="page-subtitle">Track all system edits, logins, and operational changes for compliance</p>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Timestamp</th>
                    <th>User</th>
                    <th>Action</th>
                    <th>Table</th>
                    <th>Description</th>
                    <th>IP Address</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)): ?>
                <tr><td colspan="6"><div class="empty-state"><p>No audit logs available</p></div></td></tr>
                <?php else: ?>
                <?php foreach ($logs as $log): ?>
                <tr>
                    <td><span class="text-xs text-muted"><?= formatDateTime($log['created_at']) ?></span></td>
                    <td><strong><?= sanitize($log['user_name'] ?? 'System') ?></strong></td>
                    <td><span class="badge badge-info"><?= strtoupper(sanitize($log['action'])) ?></span></td>
                    <td><code><?= sanitize($log['table_name'] ?: '-') ?></code></td>
                    <td><?= sanitize($log['description'] ?: '-') ?></td>
                    <td><span class="text-xs font-mono"><?= sanitize($log['ip_address'] ?: '127.0.0.1') ?></span></td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="card-footer">
        <?= renderPagination($pagination, '/admin/audit_logs.php') ?>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
