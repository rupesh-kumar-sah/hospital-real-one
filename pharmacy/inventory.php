<?php
/**
 * Hospital Management System — Pharmacy: Drug Inventory
 */

require_once __DIR__ . '/../includes/auth_middleware.php';
requireRole(['pharmacist', 'admin']);

$pageTitle = 'Drug Inventory';
$breadcrumbs = [['label' => 'Dashboard', 'url' => '/pharmacy/dashboard.php'], ['label' => 'Inventory']];

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'delete_drug') {
        $drugId = (int)$_POST['drug_id'];
        $stmt = $db->prepare("DELETE FROM pharmacy_inventory WHERE id = ?");
        $stmt->execute([$drugId]);
        logAudit('delete', 'pharmacy_inventory', $drugId, "Deleted drug #{$drugId}");
        setFlash('success', "Drug removed from inventory.");
        header('Location: /pharmacy/inventory.php');
        exit;
    }
}

$drugs = $db->query("SELECT * FROM pharmacy_inventory ORDER BY drug_name")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Pharmacy Drug Inventory</h1>
        <p class="page-subtitle">Track drug stock quantities, expiry dates, batch numbers and prices</p>
    </div>
    <a href="/pharmacy/add_medicine.php" class="btn btn-primary">
        <i class="fas fa-plus"></i> Add New Medicine
    </a>
</div>

<div class="card">
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Drug Name</th>
                    <th>Generic / Category</th>
                    <th>Batch #</th>
                    <th>Stock Qty</th>
                    <th>Unit Price</th>
                    <th>Selling Price</th>
                    <th>Expiry Date</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($drugs as $d): ?>
                <tr>
                    <td><strong><?= sanitize($d['drug_name']) ?></strong></td>
                    <td><?= sanitize($d['generic_name']) ?><br><span class="badge badge-info"><?= sanitize($d['category']) ?></span></td>
                    <td><code><?= sanitize($d['batch_number']) ?></code></td>
                    <td>
                        <strong class="<?= $d['stock_quantity'] <= $d['reorder_level'] ? 'text-danger' : 'text-success' ?>">
                            <?= $d['stock_quantity'] ?> <?= $d['unit'] ?>
                        </strong>
                    </td>
                    <td>Rs. <?= $d['unit_price'] ?></td>
                    <td class="font-bold text-success">Rs. <?= $d['selling_price'] ?></td>
                    <td><?= formatDate($d['expiry_date']) ?></td>
                    <td>
                        <?php if ($d['stock_quantity'] <= $d['reorder_level']): ?>
                        <span class="badge badge-danger">Low Stock</span>
                        <?php else: ?>
                        <span class="badge badge-success">In Stock</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete <?= sanitize($d['drug_name']) ?> from inventory?');">
                            <input type="hidden" name="action" value="delete_drug">
                            <input type="hidden" name="drug_id" value="<?= $d['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-trash-can"></i> Delete</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
