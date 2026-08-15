<?php
/**
 * Hospital Management System — Admin: Service Pricing
 */

require_once __DIR__ . '/../includes/auth_middleware.php';
requireRole('admin');

$pageTitle = 'Service Pricing';
$breadcrumbs = [['label' => 'Dashboard', 'url' => '/admin/dashboard.php'], ['label' => 'Service Pricing']];

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'add';
    if ($action === 'delete_service') {
        $id = (int)$_POST['service_id'];
        $stmt = $db->prepare("DELETE FROM service_pricing WHERE id = ?");
        $stmt->execute([$id]);
        logAudit('delete', 'service_pricing', $id, "Deleted service tariff #{$id}");
        setFlash('success', "Service item deleted.");
        header('Location: /admin/manage_pricing.php');
        exit;
    } else {
        $name = trim($_POST['service_name']);
        $cat = trim($_POST['category']);
        $price = (float)$_POST['price'];
        $desc = trim($_POST['description']);
        if ($name && $price) {
            $stmt = $db->prepare("INSERT INTO service_pricing (service_name, category, price, description) VALUES (?, ?, ?, ?)");
            $stmt->execute([$name, $cat, $price, $desc]);
            logAudit('create', 'service_pricing', $db->lastInsertId(), "Added service tariff {$name}");
            setFlash('success', "Service '{$name}' added.");
            header('Location: /admin/manage_pricing.php');
            exit;
        }
    }
}

$services = $db->query("SELECT * FROM service_pricing ORDER BY category, service_name")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Hospital Service Tariff / Pricing</h1>
        <p class="page-subtitle">Configure standard charges for procedures, consultations, and bed charges</p>
    </div>
    <button class="btn btn-primary" onclick="openModal('addServiceModal')">
        <i class="fas fa-plus"></i> Add Service Item
    </button>
</div>

<div class="card">
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Service Name</th>
                    <th>Category</th>
                    <th>Price (Rs.)</th>
                    <th>Description</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($services as $s): ?>
                <tr>
                    <td><strong><?= sanitize($s['service_name']) ?></strong></td>
                    <td><span class="badge badge-info"><?= sanitize($s['category']) ?></span></td>
                    <td class="font-bold text-success"><?= formatCurrency($s['price']) ?></td>
                    <td><?= sanitize($s['description'] ?: '-') ?></td>
                    <td>
                        <form method="POST" inline style="display:inline;" onsubmit="return confirm('Delete tariff item <?= sanitize($s['service_name']) ?>?');">
                            <input type="hidden" name="action" value="delete_service">
                            <input type="hidden" name="service_id" value="<?= $s['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-trash-can"></i> Delete</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal-overlay" id="addServiceModal">
    <div class="modal">
        <div class="modal-header">
            <h3>Add Service Tariff</h3>
            <button class="modal-close" onclick="closeModal('addServiceModal')">×</button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" value="add">
                <div class="form-group">
                    <label class="form-label">Service Name <span class="required">*</span></label>
                    <input type="text" name="service_name" class="form-control" required placeholder="e.g. ECG Test">
                </div>
                <div class="form-group">
                    <label class="form-label">Category</label>
                    <select name="category" class="form-control">
                        <option value="Consultation">Consultation</option>
                        <option value="Procedure">Procedure</option>
                        <option value="Accommodation">Accommodation</option>
                        <option value="Nursing">Nursing</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Price (Rs.) <span class="required">*</span></label>
                    <input type="number" name="price" class="form-control" required step="10">
                </div>
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addServiceModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Tariff</button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
