<?php
/**
 * Hospital Management System — Pharmacy: Add Medicine
 */

require_once __DIR__ . '/../includes/auth_middleware.php';
requireRole(['pharmacist', 'admin']);

$pageTitle = 'Add New Medicine';
$breadcrumbs = [['label' => 'Dashboard', 'url' => '/pharmacy/dashboard.php'], ['label' => 'Add Medicine']];

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['drug_name']);
    $generic = trim($_POST['generic_name']);
    $cat = $_POST['category'];
    $batch = trim($_POST['batch_number']);
    $qty = (int)$_POST['stock_quantity'];
    $unitPrice = (float)$_POST['unit_price'];
    $sellingPrice = (float)$_POST['selling_price'];
    $expiry = $_POST['expiry_date'];

    if ($name && $qty) {
        $stmt = $db->prepare("INSERT INTO pharmacy_inventory (drug_name, generic_name, category, batch_number, stock_quantity, unit_price, selling_price, expiry_date, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active')");
        $stmt->execute([$name, $generic, $cat, $batch, $qty, $unitPrice, $sellingPrice, $expiry]);

        setFlash('success', "Medicine '{$name}' added to inventory.");
        header('Location: /pharmacy/inventory.php');
        exit;
    }
}

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Add New Medicine / Stock Entry</h1>
        <p class="page-subtitle">Add new pharmaceutical drugs and supplies to hospital inventory</p>
    </div>
</div>

<div class="card" style="max-width: 650px;">
    <div class="card-body">
        <form method="POST">
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Brand / Drug Name <span class="required">*</span></label>
                    <input type="text" name="drug_name" class="form-control" required placeholder="e.g. Paracetamol 500mg">
                </div>
                <div class="form-group">
                    <label class="form-label">Generic Name</label>
                    <input type="text" name="generic_name" class="form-control" placeholder="Acetaminophen">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Form Category</label>
                    <select name="category" class="form-control">
                        <option value="Tablet">Tablet</option>
                        <option value="Capsule">Capsule</option>
                        <option value="Syrup">Syrup</option>
                        <option value="Injection">Injection</option>
                        <option value="Ointment">Ointment / Gel</option>
                        <option value="IV Fluid">IV Fluid</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Batch Number</label>
                    <input type="text" name="batch_number" class="form-control" placeholder="BAT-1002">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Stock Quantity <span class="required">*</span></label>
                    <input type="number" name="stock_quantity" class="form-control" required value="100">
                </div>
                <div class="form-group">
                    <label class="form-label">Unit Cost Price (Rs.)</label>
                    <input type="number" name="unit_price" class="form-control" step="0.5" value="5">
                </div>
                <div class="form-group">
                    <label class="form-label">Selling Price (Rs.)</label>
                    <input type="number" name="selling_price" class="form-control" step="0.5" value="8">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Expiry Date</label>
                <input type="date" name="expiry_date" class="form-control" value="2027-12-31">
            </div>

            <button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> Save to Inventory</button>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
