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

$search = trim($_GET['search'] ?? '');
$actionFilter = trim($_GET['action_filter'] ?? '');

$whereClauses = [];
$params = [];

if ($search !== '') {
    $whereClauses[] = "(user_name LIKE ? OR description LIKE ? OR table_name LIKE ? OR ip_address LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

if ($actionFilter !== '') {
    $whereClauses[] = "action = ?";
    $params[] = $actionFilter;
}

$whereSql = !empty($whereClauses) ? "WHERE " . implode(" AND ", $whereClauses) : "";

$pagination = paginate("SELECT * FROM audit_logs {$whereSql} ORDER BY created_at DESC", $params, $page, 20);
$logs = $pagination['data'];

$actionTypes = $db->query("SELECT DISTINCT action FROM audit_logs ORDER BY action ASC")->fetchAll(PDO::FETCH_COLUMN);

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>System Audit Trail</h1>
        <p class="page-subtitle">Track all system edits, logins, and operational changes for compliance</p>
    </div>
    <a href="/admin/export_data.php" class="btn btn-primary" style="background: #10b981; border: none; font-weight: 700;">
        <i class="fas fa-file-excel"></i> Export CSVs & Google Sheets Dashboard
    </a>
</div>

<!-- Database Storage & Exporter Card -->
<div class="card mb-24" style="background: linear-gradient(135deg, #0f172a, #1e293b); color: #fff; padding: 24px; border-radius: 12px; border: 1px solid #334155;">
    <div class="d-flex align-center gap-16 mb-16" style="justify-content: space-between; flex-wrap: wrap;">
        <div class="d-flex align-center gap-16">
            <div style="width: 52px; height: 52px; background: rgba(99, 102, 241, 0.2); color: #818cf8; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem;">
                <i class="fas fa-database"></i>
            </div>
            <div>
                <h3 style="margin: 0; color: #fff; font-size: 1.15rem;">Database Storage Path: <code style="color: #38bdf8; background: rgba(56, 189, 248, 0.1); padding: 3px 8px; border-radius: 6px;">E:\HM DATA\hms.db</code></h3>
                <p style="margin: 4px 0 0 0; color: #94a3b8; font-size: 0.875rem;">
                    All patient medical records, doctors, nurses, prescriptions, lab reports, and billing payments are saved on your laptop's <strong>E:\HM DATA</strong> folder.
                </p>
            </div>
        </div>
        <div>
            <a href="/admin/export_data.php" class="btn btn-success" style="padding: 10px 18px; font-weight: 700; font-size: 0.9rem;">
                <i class="fas fa-file-csv"></i> Generate CSVs & Google Sheets
            </a>
        </div>
    </div>
    <div style="border-top: 1px solid #334155; pt: 14px; margin-top: 14px; font-size: 0.8125rem; color: #cbd5e1; display: flex; align-items: center; gap: 8px;">
        <i class="fas fa-info-circle text-info"></i>
        <span>Generates individual CSV spreadsheets for all 25 tables + an interactive <strong>Hospital_Data_Sheets.html</strong> dashboard inside <strong>E:\HM DATA\</strong>.</span>
    </div>
</div>

<!-- Search & Action Filters -->
<div class="card mb-24">
    <div class="card-body">
        <form method="GET" action="" class="d-flex gap-16" style="flex-wrap: wrap; align-items: flex-end;">
            <div style="flex: 1; min-width: 200px;">
                <label class="form-label" style="font-size: 0.8125rem; font-weight: 600;">Search Logs</label>
                <input type="text" name="search" class="form-control" placeholder="Search by user, description, IP..." value="<?= sanitize($search) ?>">
            </div>
            <div style="width: 200px;">
                <label class="form-label" style="font-size: 0.8125rem; font-weight: 600;">Action Type</label>
                <select name="action_filter" class="form-control">
                    <option value="">All Actions</option>
                    <?php foreach ($actionTypes as $act): ?>
                    <option value="<?= sanitize($act) ?>" <?= $actionFilter === $act ? 'selected' : '' ?>><?= strtoupper(sanitize($act)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Filter</button>
                <a href="/admin/audit_logs.php" class="btn btn-secondary"><i class="fas fa-undo"></i> Reset</a>
            </div>
        </form>
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
