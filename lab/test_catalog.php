<?php
/**
 * Hospital Management System — Lab: Test Catalog
 */

require_once __DIR__ . '/../includes/auth_middleware.php';
requireRole(['lab_technician', 'admin']);

$pageTitle = 'Lab Test Catalog';
$breadcrumbs = [['label' => 'Dashboard', 'url' => '/lab/dashboard.php'], ['label' => 'Test Catalog']];

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'add';
    if ($action === 'delete_test') {
        $testId = (int)$_POST['test_id'];
        $stmt = $db->prepare("DELETE FROM lab_test_catalog WHERE id = ?");
        $stmt->execute([$testId]);
        logAudit('delete', 'lab_test_catalog', $testId, "Deleted lab test #{$testId}");
        setFlash('success', "Lab test removed from catalog.");
        header('Location: /lab/test_catalog.php');
        exit;
    } else {
        $name = trim($_POST['test_name']);
        $cat = trim($_POST['category']);
        $price = (float)$_POST['price'];
        $sample = trim($_POST['sample_type']);
        $range = trim($_POST['normal_range']);

        if ($name && $price) {
            $stmt = $db->prepare("INSERT INTO lab_test_catalog (test_name, category, price, sample_type, normal_range, status) VALUES (?, ?, ?, ?, ?, 'active')");
            $stmt->execute([$name, $cat, $price, $sample, $range]);
            logAudit('create', 'lab_test_catalog', $db->lastInsertId(), "Added lab test {$name}");
            setFlash('success', "Lab test '{$name}' added to catalog.");
            header('Location: /lab/test_catalog.php');
            exit;
        }
    }
}

$tests = $db->query("SELECT * FROM lab_test_catalog ORDER BY category, test_name")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Laboratory Test Catalog & Tariff</h1>
        <p class="page-subtitle">Configure available diagnostic tests, normal reference ranges and fees</p>
    </div>
    <button class="btn btn-primary" onclick="openModal('addTestModal')">
        <i class="fas fa-plus"></i> Add Test Type
    </button>
</div>

<div class="card">
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Test Name</th>
                    <th>Category</th>
                    <th>Sample Type</th>
                    <th>Normal Reference Range</th>
                    <th>Price (Rs.)</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tests as $t): ?>
                <tr>
                    <td><strong><?= sanitize($t['test_name']) ?></strong></td>
                    <td><span class="badge badge-info"><?= sanitize($t['category']) ?></span></td>
                    <td><?= sanitize($t['sample_type'] ?: 'Blood') ?></td>
                    <td><span class="text-xs text-muted"><?= sanitize($t['normal_range'] ?: '-') ?></span></td>
                    <td class="font-bold text-success">Rs. <?= $t['price'] ?></td>
                    <td>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete lab test <?= sanitize($t['test_name']) ?>?');">
                            <input type="hidden" name="action" value="delete_test">
                            <input type="hidden" name="test_id" value="<?= $t['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-trash-can"></i> Delete</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal-overlay" id="addTestModal">
    <div class="modal">
        <div class="modal-header">
            <h3>Add New Lab Test</h3>
            <button class="modal-close" onclick="closeModal('addTestModal')">×</button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" value="add">
                <div class="form-group">
                    <label class="form-label">Test Name <span class="required">*</span></label>
                    <input type="text" name="test_name" class="form-control" required placeholder="e.g. Vitamin D3 Total">
                </div>
                <div class="form-group">
                    <label class="form-label">Category</label>
                    <select name="category" class="form-control">
                        <option value="Hematology">Hematology</option>
                        <option value="Biochemistry">Biochemistry</option>
                        <option value="Microbiology">Microbiology</option>
                        <option value="Radiology">Radiology / Imaging</option>
                        <option value="Endocrinology">Endocrinology</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Sample Type</label>
                    <input type="text" name="sample_type" class="form-control" placeholder="Blood / Urine / Swab">
                </div>
                <div class="form-group">
                    <label class="form-label">Normal Reference Range</label>
                    <input type="text" name="normal_range" class="form-control" placeholder="30-100 ng/mL">
                </div>
                <div class="form-group">
                    <label class="form-label">Price (Rs.) <span class="required">*</span></label>
                    <input type="number" name="price" class="form-control" required value="500">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addTestModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Test</button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
