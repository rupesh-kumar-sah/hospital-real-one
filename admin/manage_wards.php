<?php
/**
 * Hospital Management System — Admin: Wards & Beds Management
 */

require_once __DIR__ . '/../includes/auth_middleware.php';
requireRole(['admin', 'nurse']);

$pageTitle = 'Wards & Bed Management';
$breadcrumbs = [['label' => 'Dashboard', 'url' => '/admin/dashboard.php'], ['label' => 'Wards & Beds']];

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add_bed') {
        $wardId = (int)$_POST['ward_id'];
        $bedNumber = trim($_POST['bed_number']);
        $charge = (float)$_POST['daily_charge'];
        if ($wardId && $bedNumber) {
            $stmt = $db->prepare("INSERT INTO beds (ward_id, bed_number, status, daily_charge) VALUES (?, ?, 'available', ?)");
            $stmt->execute([$wardId, $bedNumber, $charge]);
            setFlash('success', "Bed {$bedNumber} added.");
            header('Location: /admin/manage_wards.php');
            exit;
        }
    } elseif ($action === 'update_bed_status') {
        $bedId = (int)$_POST['bed_id'];
        $status = $_POST['status'];
        $stmt = $db->prepare("UPDATE beds SET status = ? WHERE id = ?");
        $stmt->execute([$status, $bedId]);
        setFlash('success', "Bed status updated to {$status}.");
        header('Location: /admin/manage_wards.php');
        exit;
    } elseif ($action === 'delete_bed') {
        $bedId = (int)$_POST['bed_id'];
        $stmt = $db->prepare("DELETE FROM beds WHERE id = ?");
        $stmt->execute([$bedId]);
        logAudit('delete', 'beds', $bedId, "Deleted bed #{$bedId}");
        setFlash('success', "Bed deleted successfully.");
        header('Location: /admin/manage_wards.php');
        exit;
    }
}

$wards = $db->query("
    SELECT w.*, 
           (SELECT COUNT(*) FROM beds WHERE ward_id = w.id) as total_beds,
           (SELECT COUNT(*) FROM beds WHERE ward_id = w.id AND status = 'occupied') as occupied_beds
    FROM wards w
")->fetchAll();

$beds = $db->query("
    SELECT b.*, w.name as ward_name 
    FROM beds b 
    JOIN wards w ON b.ward_id = w.id 
    ORDER BY w.name, b.bed_number
")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Wards & Bed Inventory</h1>
        <p class="page-subtitle">Monitor bed availability, change status, or delete beds</p>
    </div>
    <button class="btn btn-primary" onclick="openModal('addBedModal')">
        <i class="fas fa-bed"></i> Add New Bed
    </button>
</div>

<!-- Wards summary grid -->
<div class="stats-grid mb-24">
    <?php foreach ($wards as $w): ?>
    <div class="stat-card" style="--stat-color: var(--primary);">
        <div class="stat-info">
            <h3><?= sanitize($w['name']) ?> (Floor <?= $w['floor'] ?>)</h3>
            <div class="stat-number"><?= $w['occupied_beds'] ?> / <?= $w['total_beds'] ?></div>
            <div class="stat-change text-muted">Beds Occupied</div>
        </div>
        <div class="stat-icon"><i class="fas fa-hospital-user"></i></div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Beds Table -->
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-bed text-primary"></i> Bed Details</h3>
    </div>
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Bed #</th>
                    <th>Ward</th>
                    <th>Daily Charge</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($beds as $b): ?>
                <tr>
                    <td><strong><?= sanitize($b['bed_number']) ?></strong></td>
                    <td><?= sanitize($b['ward_name']) ?></td>
                    <td><?= formatCurrency($b['daily_charge']) ?></td>
                    <td><?= statusBadge($b['status'], BED_STATUSES) ?></td>
                    <td>
                        <div class="d-flex gap-8 align-center">
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="action" value="update_bed_status">
                                <input type="hidden" name="bed_id" value="<?= $b['id'] ?>">
                                <select name="status" class="form-control form-control-sm" style="width: 140px; display: inline-block;" onchange="this.form.submit()">
                                    <option value="available" <?= $b['status'] === 'available' ? 'selected' : '' ?>>Available</option>
                                    <option value="occupied" <?= $b['status'] === 'occupied' ? 'selected' : '' ?>>Occupied</option>
                                    <option value="reserved" <?= $b['status'] === 'reserved' ? 'selected' : '' ?>>Reserved</option>
                                    <option value="maintenance" <?= $b['status'] === 'maintenance' ? 'selected' : '' ?>>Maintenance</option>
                                </select>
                            </form>

                            <form method="POST" style="display:inline;" onsubmit="return confirm('Delete Bed <?= sanitize($b['bed_number']) ?>?');">
                                <input type="hidden" name="action" value="delete_bed">
                                <input type="hidden" name="bed_id" value="<?= $b['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-trash-can"></i> Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal-overlay" id="addBedModal">
    <div class="modal">
        <div class="modal-header">
            <h3>Add New Bed</h3>
            <button class="modal-close" onclick="closeModal('addBedModal')">×</button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" value="add_bed">
                <div class="form-group">
                    <label class="form-label">Ward <span class="required">*</span></label>
                    <select name="ward_id" class="form-control" required>
                        <?php foreach ($wards as $w): ?>
                        <option value="<?= $w['id'] ?>"><?= sanitize($w['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Bed Number <span class="required">*</span></label>
                    <input type="text" name="bed_number" class="form-control" required placeholder="e.g. GA-06">
                </div>
                <div class="form-group">
                    <label class="form-label">Daily Charge (Rs.)</label>
                    <input type="number" name="daily_charge" class="form-control" value="200" step="50">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addBedModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Add Bed</button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
