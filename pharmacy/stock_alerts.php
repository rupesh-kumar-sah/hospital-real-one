<?php
/**
 * Hospital Management System — Pharmacy: Low Stock Alerts
 */

require_once __DIR__ . '/../includes/auth_middleware.php';
requireRole(['pharmacist', 'admin']);

$pageTitle = 'Stock & Expiry Alerts';
$breadcrumbs = [['label' => 'Dashboard', 'url' => '/pharmacy/dashboard.php'], ['label' => 'Stock Alerts']];

$db = getDB();

$alerts = $db->query("
    SELECT * FROM pharmacy_inventory 
    WHERE stock_quantity <= reorder_level OR expiry_date <= DATE('now', '+30 days')
    ORDER BY stock_quantity ASC
")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Low Stock & Near-Expiry Alerts</h1>
        <p class="page-subtitle">Pharmaceutical items requiring immediate reordering or disposal</p>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Drug Name</th>
                    <th>Category</th>
                    <th>Current Stock</th>
                    <th>Reorder Level</th>
                    <th>Expiry Date</th>
                    <th>Alert Type</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($alerts as $a): ?>
                <tr>
                    <td><strong><?= sanitize($a['drug_name']) ?></strong></td>
                    <td><?= sanitize($a['category']) ?></td>
                    <td><strong class="text-danger"><?= $a['stock_quantity'] ?> <?= $a['unit'] ?></strong></td>
                    <td><?= $a['reorder_level'] ?></td>
                    <td><?= formatDate($a['expiry_date']) ?></td>
                    <td>
                        <?php if ($a['stock_quantity'] <= $a['reorder_level']): ?>
                        <span class="badge badge-danger"><i class="fas fa-exclamation-triangle"></i> LOW STOCK</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
