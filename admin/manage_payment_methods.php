<?php
/**
 * Hospital Management System — Admin: Payment Gateways & QR Codes Management
 */

require_once __DIR__ . '/../includes/auth_middleware.php';
requireRole('admin');

$pageTitle = 'Payment Gateways & QR Codes';
$breadcrumbs = [['label' => 'Dashboard', 'url' => '/admin/dashboard.php'], ['label' => 'Payment Methods']];

$db = getDB();

// Handle Create / Edit / Delete Payment Method
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'save_payment_method') {
        $id = (int)($_POST['method_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $accName = trim($_POST['account_name'] ?? '');
        $accNum = trim($_POST['account_number'] ?? '');
        $instructions = trim($_POST['instructions'] ?? '');
        $status = $_POST['status'] ?? 'active';
        $qrImagePath = $_POST['existing_qr'] ?? '';

        // Handle File Upload (QR Image)
        if (isset($_FILES['qr_image']) && $_FILES['qr_image']['error'] === UPLOAD_ERR_OK) {
            $fileTmp = $_FILES['qr_image']['tmp_name'];
            $fileName = $_FILES['qr_image']['name'];
            $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            $allowedExts = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

            if (in_array($fileExt, $allowedExts)) {
                $uploadDir = __DIR__ . '/../uploads/qr_codes/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                $newFileName = 'qr_' . time() . '_' . rand(1000, 9999) . '.' . $fileExt;
                $targetPath = $uploadDir . $newFileName;

                if (move_uploaded_file($fileTmp, $targetPath)) {
                    $qrImagePath = '/uploads/qr_codes/' . $newFileName;
                }
            } else {
                setFlash('error', 'Invalid image format. Allowed formats: JPG, PNG, WEBP.');
            }
        }

        if ($name) {
            if ($id > 0) {
                // Update
                $stmt = $db->prepare("UPDATE payment_methods SET name = ?, account_name = ?, account_number = ?, qr_image = ?, instructions = ?, status = ? WHERE id = ?");
                $stmt->execute([$name, $accName, $accNum, $qrImagePath, $instructions, $status, $id]);
                logAudit('update', 'payment_methods', $id, "Updated payment method {$name}");
                setFlash('success', "Payment method '{$name}' updated successfully.");
            } else {
                // Create
                $stmt = $db->prepare("INSERT INTO payment_methods (name, account_name, account_number, qr_image, instructions, status) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$name, $accName, $accNum, $qrImagePath, $instructions, $status]);
                logAudit('create', 'payment_methods', $db->lastInsertId(), "Added payment method {$name}");
                setFlash('success', "Payment method '{$name}' added successfully.");
            }
        } else {
            setFlash('error', 'Payment method name is required.');
        }

        header('Location: /admin/manage_payment_methods.php');
        exit;
    } elseif ($action === 'toggle_status') {
        $id = (int)$_POST['method_id'];
        $newStatus = $_POST['status'] === 'active' ? 'inactive' : 'active';
        $stmt = $db->prepare("UPDATE payment_methods SET status = ? WHERE id = ?");
        $stmt->execute([$newStatus, $id]);
        setFlash('success', "Payment method status updated to {$newStatus}.");
        header('Location: /admin/manage_payment_methods.php');
        exit;
    } elseif ($action === 'delete_method') {
        $id = (int)$_POST['method_id'];
        $stmt = $db->prepare("DELETE FROM payment_methods WHERE id = ?");
        $stmt->execute([$id]);
        logAudit('delete', 'payment_methods', $id, "Deleted payment method #{$id}");
        setFlash('success', "Payment method deleted.");
        header('Location: /admin/manage_payment_methods.php');
        exit;
    }
}

$methods = $db->query("SELECT * FROM payment_methods ORDER BY id ASC")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Payment Gateways & Digital QR Codes</h1>
        <p class="page-subtitle">Configure eSewa, Khalti, Fonepay, Cash, and Bank Transfer details for patient/receptionist billing</p>
    </div>
    <button class="btn btn-primary" onclick="openPaymentModal()">
        <i class="fas fa-plus-circle"></i> Add Payment Option / QR
    </button>
</div>

<div class="grid-2">
    <?php foreach ($methods as $m): ?>
    <div class="card mb-24">
        <div class="card-header">
            <h3>
                <i class="fas <?= str_contains(strtolower($m['name']), 'esewa') ? 'fa-wallet text-success' : (str_contains(strtolower($m['name']), 'khalti') ? 'fa-mobile-screen text-accent' : (str_contains(strtolower($m['name']), 'cash') ? 'fa-money-bill-wave text-warning' : 'fa-qrcode text-primary')) ?>"></i> 
                <?= sanitize($m['name']) ?>
            </h3>
            <span class="badge <?= $m['status'] === 'active' ? 'badge-success' : 'badge-danger' ?>"><?= ucfirst($m['status']) ?></span>
        </div>
        <div class="card-body">
            <div class="d-flex gap-16 align-start">
                <?php if ($m['qr_image']): ?>
                <div style="text-align: center;">
                    <img src="<?= sanitize($m['qr_image']) ?>" alt="QR Code" style="width: 130px; height: 130px; border-radius: 8px; border: 2px solid var(--gray-200); object-fit: cover;">
                    <div class="text-xs text-muted mt-4">Scannable QR</div>
                </div>
                <?php else: ?>
                <div style="width: 130px; height: 130px; border-radius: 8px; background: var(--gray-100); display: flex; flex-direction: column; align-items: center; justify-content: center; color: var(--gray-400);">
                    <i class="fas fa-qrcode fa-2x mb-4"></i>
                    <span class="text-xs">No QR Code Image</span>
                </div>
                <?php endif; ?>

                <div class="flex-1">
                    <p class="mb-4"><strong>Account Name:</strong> <?= sanitize($m['account_name'] ?: 'N/A') ?></p>
                    <p class="mb-4"><strong>Account / Mobile #:</strong> <code><?= sanitize($m['account_number'] ?: 'N/A') ?></code></p>
                    <p class="text-sm text-muted mb-12"><strong>Instructions:</strong> <?= sanitize($m['instructions'] ?: 'No special instructions') ?></p>

                    <div class="d-flex gap-8">
                        <button type="button" class="btn btn-sm btn-secondary" onclick='editPaymentModal(<?= json_encode($m) ?>)'>
                            <i class="fas fa-edit"></i> Edit
                        </button>

                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="action" value="toggle_status">
                            <input type="hidden" name="method_id" value="<?= $m['id'] ?>">
                            <input type="hidden" name="status" value="<?= $m['status'] ?>">
                            <button type="submit" class="btn btn-sm <?= $m['status'] === 'active' ? 'btn-warning' : 'btn-success' ?>">
                                <?= $m['status'] === 'active' ? 'Deactivate' : 'Activate' ?>
                            </button>
                        </form>

                        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete payment option <?= sanitize($m['name']) ?>?');">
                            <input type="hidden" name="action" value="delete_method">
                            <input type="hidden" name="method_id" value="<?= $m['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-trash-can"></i> Delete</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Modal Form for Add/Edit -->
<div class="modal-overlay" id="paymentModal">
    <div class="modal" style="max-width: 600px;">
        <div class="modal-header">
            <h3 id="paymentModalTitle"><i class="fas fa-qrcode text-primary"></i> Add Payment Option / QR</h3>
            <button class="modal-close" onclick="closeModal('paymentModal')">×</button>
        </div>
        <form method="POST" enctype="multipart/form-data">
            <div class="modal-body">
                <input type="hidden" name="action" value="save_payment_method">
                <input type="hidden" name="method_id" id="method_id" value="0">
                <input type="hidden" name="existing_qr" id="existing_qr" value="">

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Payment Gateway / Name <span class="required">*</span></label>
                        <input type="text" name="name" id="name" class="form-control" required placeholder="e.g. eSewa / Khalti / Fonepay">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select name="status" id="status" class="form-control">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Account Holder Name</label>
                        <input type="text" name="account_name" id="account_name" class="form-control" placeholder="MediCare Hospital Pvt. Ltd.">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Account / ID / Mobile #</label>
                        <input type="text" name="account_number" id="account_number" class="form-control" placeholder="9800000000">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Upload Scannable QR Code Photo (eSewa / Khalti / Fonepay)</label>
                    <input type="file" name="qr_image" class="form-control" accept="image/*">
                    <span class="text-xs text-muted">Upload PNG or JPG image of your official QR code</span>
                </div>

                <div class="form-group">
                    <label class="form-label">Payment Instructions for Patients / Staff</label>
                    <textarea name="instructions" id="instructions" class="form-control" rows="3" placeholder="e.g. Scan QR using eSewa App, enter Patient UHID in Remarks."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('paymentModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Payment Gateway</button>
            </div>
        </form>
    </div>
</div>

<script>
function openPaymentModal() {
    document.getElementById('method_id').value = '0';
    document.getElementById('name').value = '';
    document.getElementById('account_name').value = '';
    document.getElementById('account_number').value = '';
    document.getElementById('instructions').value = '';
    document.getElementById('existing_qr').value = '';
    document.getElementById('status').value = 'active';
    document.getElementById('paymentModalTitle').innerHTML = '<i class="fas fa-qrcode text-primary"></i> Add Payment Option / QR';
    openModal('paymentModal');
}

function editPaymentModal(data) {
    document.getElementById('method_id').value = data.id;
    document.getElementById('name').value = data.name;
    document.getElementById('account_name').value = data.account_name || '';
    document.getElementById('account_number').value = data.account_number || '';
    document.getElementById('instructions').value = data.instructions || '';
    document.getElementById('existing_qr').value = data.qr_image || '';
    document.getElementById('status').value = data.status || 'active';
    document.getElementById('paymentModalTitle').innerHTML = '<i class="fas fa-edit text-primary"></i> Edit ' + data.name;
    openModal('paymentModal');
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
