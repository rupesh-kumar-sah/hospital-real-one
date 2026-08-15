<?php
/**
 * Hospital Management System — Pharmacy Dashboard
 */

require_once __DIR__ . '/../includes/auth_middleware.php';
requireRole(['pharmacist', 'admin']);

$pageTitle = 'Pharmacy Dashboard';
$breadcrumbs = [['label' => 'Dashboard']];

$db = getDB();

// Pending prescriptions count
$pendingRx = $db->query("SELECT COUNT(*) as c FROM prescriptions WHERE status = 'pending'")->fetch()['c'];
$dispensedToday = $db->query("SELECT COUNT(*) as c FROM prescriptions WHERE status = 'dispensed' AND DATE(created_at) = DATE('now')")->fetch()['c'];

// Stock stats
$totalDrugs = $db->query("SELECT COUNT(*) as c FROM pharmacy_inventory WHERE status = 'active'")->fetch()['c'];
$lowStockCount = $db->query("SELECT COUNT(*) as c FROM pharmacy_inventory WHERE stock_quantity <= reorder_level AND status = 'active'")->fetch()['c'];

// Pending RX list
$pendingList = $db->query("
    SELECT pr.*, p.uhid, u_p.full_name as patient_name, u_d.full_name as doctor_name
    FROM prescriptions pr
    JOIN patients p ON pr.patient_id = p.id
    JOIN users u_p ON p.user_id = u_p.id
    JOIN doctors d ON pr.doctor_id = d.id
    JOIN users u_d ON d.user_id = u_d.id
    WHERE pr.status = 'pending'
    ORDER BY pr.created_at DESC
")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="stats-grid">
    <div class="stat-card" style="--stat-color: #f59e0b;">
        <div class="stat-info">
            <h3>Pending Prescriptions Queue</h3>
            <div class="stat-number"><?= $pendingRx ?></div>
            <div class="stat-change text-success"><i class="fas fa-check"></i> <?= $dispensedToday ?> dispensed today</div>
        </div>
        <div class="stat-icon"><i class="fas fa-prescription"></i></div>
    </div>

    <div class="stat-card" style="--stat-color: #10b981;">
        <div class="stat-info">
            <h3>Total Inventory Drugs</h3>
            <div class="stat-number"><?= $totalDrugs ?></div>
        </div>
        <div class="stat-icon"><i class="fas fa-pills"></i></div>
    </div>

    <div class="stat-card" style="--stat-color: #ef4444;">
        <div class="stat-info">
            <h3>Low Stock Alerts</h3>
            <div class="stat-number"><?= $lowStockCount ?></div>
        </div>
        <div class="stat-icon"><i class="fas fa-triangle-exclamation"></i></div>
    </div>
</div>

<div class="card mb-24">
    <div class="card-header">
        <h3><i class="fas fa-bolt text-warning"></i> Quick Actions</h3>
    </div>
    <div class="card-body">
        <div class="quick-actions">
            <a href="/pharmacy/dispense.php" class="quick-action-btn">
                <i class="fas fa-hand-holding-medical"></i>
                <span>Dispense Medicine</span>
            </a>
            <a href="/pharmacy/inventory.php" class="quick-action-btn">
                <i class="fas fa-boxes-stacked"></i>
                <span>Drug Inventory</span>
            </a>
            <a href="/pharmacy/add_medicine.php" class="quick-action-btn">
                <i class="fas fa-plus-circle"></i>
                <span>Add New Medicine</span>
            </a>
            <a href="/pharmacy/stock_alerts.php" class="quick-action-btn">
                <i class="fas fa-triangle-exclamation"></i>
                <span>Stock Alerts</span>
            </a>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-clock text-primary"></i> Pending Prescriptions Queue</h3>
        <a href="/pharmacy/dispense.php" class="btn btn-sm btn-primary">Process All</a>
    </div>
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Rx ID</th>
                    <th>Patient</th>
                    <th>UHID</th>
                    <th>Prescribed By</th>
                    <th>Date</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($pendingList)): ?>
                <tr><td colspan="6"><div class="empty-state"><p>No pending prescriptions in queue</p></div></td></tr>
                <?php else: ?>
                <?php foreach ($pendingList as $pr): ?>
                <tr>
                    <td><strong>#Rx-<?= $pr['id'] ?></strong></td>
                    <td><strong><?= sanitize($pr['patient_name']) ?></strong></td>
                    <td><code><?= sanitize($pr['uhid']) ?></code></td>
                    <td>Dr. <?= sanitize($pr['doctor_name']) ?></td>
                    <td><?= formatDate($pr['created_at']) ?></td>
                    <td>
                        <a href="/pharmacy/dispense.php?id=<?= $pr['id'] ?>" class="btn btn-sm btn-success">
                            <i class="fas fa-pills"></i> Dispense
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
