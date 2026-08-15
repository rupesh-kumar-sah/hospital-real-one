<?php
/**
 * Hospital Management System — Billing Module
 */

require_once __DIR__ . '/../includes/auth_middleware.php';
requireRole(['receptionist', 'admin']);

$pageTitle = 'Hospital Billing';
$breadcrumbs = [['label' => 'Dashboard', 'url' => '/receptionist/dashboard.php'], ['label' => 'Billing']];

$db = getDB();

// Handle Create Bill
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $patientId = (int)$_POST['patient_id'];
    $subtotal = (float)$_POST['subtotal'];
    $discount = (float)($_POST['discount'] ?? 0);
    $tax = (float)($_POST['tax'] ?? 0);
    $netAmount = $subtotal - $discount + $tax;
    $paymentMethod = $_POST['payment_method'];

    if ($patientId && $subtotal > 0) {
        $invNum = generateInvoiceNumber();
        $stmt = $db->prepare("INSERT INTO billing (patient_id, invoice_number, subtotal, discount, tax, net_amount, payment_status, payment_method, payment_date, created_by) VALUES (?, ?, ?, ?, ?, ?, 'paid', ?, CURRENT_TIMESTAMP, ?)");
        $stmt->execute([$patientId, $invNum, $subtotal, $discount, $tax, $netAmount, $paymentMethod, getUserId()]);
        $billId = $db->lastInsertId();

        // Items
        $desc = $_POST['item_description'] ?? [];
        $prices = $_POST['item_price'] ?? [];
        for ($i = 0; $i < count($desc); $i++) {
            if (!empty($desc[$i])) {
                $stmtItem = $db->prepare("INSERT INTO billing_items (bill_id, item_type, description, quantity, unit_price, total_price) VALUES (?, 'consultation', ?, 1, ?, ?)");
                $stmtItem->execute([$billId, $desc[$i], (float)$prices[$i], (float)$prices[$i]]);
            }
        }

        // Notify patient
        $patientUser = $db->query("SELECT user_id FROM patients WHERE id = {$patientId}")->fetch();
        if ($patientUser) {
            createNotification($patientUser['user_id'], 'Invoice Issued', "Receipt {$invNum} generated for Rs. {$netAmount} ({$paymentMethod}).", 'billing');
        }

        logAudit('create', 'billing', $billId, "Generated invoice {$invNum} for Rs. {$netAmount}");
        setFlash('success', "Invoice {$invNum} generated and payment recorded successfully.");
        header('Location: /receptionist/billing.php');
        exit;
    }
}

$bills = $db->query("
    SELECT b.*, p.uhid, u.full_name as patient_name 
    FROM billing b 
    JOIN patients p ON b.patient_id = p.id 
    JOIN users u ON p.user_id = u.id 
    ORDER BY b.created_at DESC
")->fetchAll();

$patients = $db->query("SELECT p.id, p.uhid, u.full_name FROM patients p JOIN users u ON p.user_id = u.id ORDER BY u.full_name")->fetchAll();
$paymentMethods = $db->query("SELECT * FROM payment_methods WHERE status = 'active' ORDER BY id ASC")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Invoicing & Payment Collection</h1>
        <p class="page-subtitle">Generate bills, accept eSewa / Khalti QR payments, and print receipts</p>
    </div>
    <button class="btn btn-primary" onclick="openModal('createBillModal')">
        <i class="fas fa-file-invoice-dollar"></i> Generate New Bill
    </button>
</div>

<div class="card">
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Invoice #</th>
                    <th>Patient</th>
                    <th>UHID</th>
                    <th>Amount</th>
                    <th>Payment Method</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($bills)): ?>
                <tr><td colspan="8"><div class="empty-state"><p>No billing records generated yet.</p></div></td></tr>
                <?php else: ?>
                <?php foreach ($bills as $b): ?>
                <tr>
                    <td><strong><?= sanitize($b['invoice_number']) ?></strong></td>
                    <td><?= sanitize($b['patient_name']) ?></td>
                    <td><code><?= sanitize($b['uhid']) ?></code></td>
                    <td class="font-bold text-success"><?= formatCurrency($b['net_amount']) ?></td>
                    <td><?= ucfirst(sanitize($b['payment_method'] ?: 'Cash')) ?></td>
                    <td><?= statusBadge($b['payment_status'], PAYMENT_STATUSES) ?></td>
                    <td><?= formatDate($b['created_at']) ?></td>
                    <td>
                        <button class="btn btn-sm btn-secondary" onclick="window.print()"><i class="fas fa-print"></i> Print Invoice</button>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal-overlay" id="createBillModal">
    <div class="modal" style="max-width: 700px;">
        <div class="modal-header">
            <h3>Generate Invoice & Select Payment Option</h3>
            <button class="modal-close" onclick="closeModal('createBillModal')">×</button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Patient <span class="required">*</span></label>
                    <select name="patient_id" class="form-control" required>
                        <option value="">Select Patient</option>
                        <?php foreach ($patients as $p): ?>
                        <option value="<?= $p['id'] ?>"><?= sanitize($p['full_name']) ?> (<?= $p['uhid'] ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Line Items</label>
                    <div class="form-row mb-8">
                        <input type="text" name="item_description[]" class="form-control" placeholder="Consultation Fee" value="OPD Consultation Fee">
                        <input type="number" name="item_price[]" class="form-control" placeholder="Amount" value="500" onchange="calcTotal()">
                    </div>
                    <div class="form-row mb-8">
                        <input type="text" name="item_description[]" class="form-control" placeholder="Lab Test / Procedure">
                        <input type="number" name="item_price[]" class="form-control" placeholder="Amount" value="0" onchange="calcTotal()">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Subtotal (Rs.)</label>
                        <input type="number" name="subtotal" id="billSubtotal" class="form-control" required value="500" readonly>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Discount (Rs.)</label>
                        <input type="number" name="discount" class="form-control" value="0">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Payment Method <span class="required">*</span></label>
                        <select name="payment_method" id="paymentMethodSelect" class="form-control" required onchange="showQRPreview(this.value)">
                            <?php foreach ($paymentMethods as $pm): ?>
                            <option value="<?= sanitize($pm['name']) ?>" data-qr="<?= sanitize($pm['qr_image']) ?>" data-acc="<?= sanitize($pm['account_name'] . ' - ' . $pm['account_number']) ?>" data-inst="<?= sanitize($pm['instructions']) ?>">
                                <?= sanitize($pm['name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Dynamic QR Preview Box -->
                <div id="qrPreviewBox" class="card p-16 mt-12 bg-light" style="display: none; border: 1px dashed var(--primary-light);">
                    <div class="d-flex gap-16 align-center">
                        <img id="qrImageDisplay" src="" alt="Payment QR" style="width: 110px; height: 110px; border-radius: 8px; border: 1px solid var(--gray-300); display: none;">
                        <div>
                            <h4 id="qrTitleDisplay" class="text-primary mb-4">Payment QR Code</h4>
                            <p id="qrAccDisplay" class="text-sm font-semibold mb-4"></p>
                            <p id="qrInstDisplay" class="text-xs text-muted mb-0"></p>
                        </div>
                    </div>
                </div>

            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('createBillModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> Collect Payment & Print</button>
            </div>
        </form>
    </div>
</div>

<script>
function calcTotal() {
    let inputs = document.getElementsByName('item_price[]');
    let total = 0;
    inputs.forEach(i => total += parseFloat(i.value || 0));
    document.getElementById('billSubtotal').value = total;
}

function showQRPreview(selectedName) {
    let select = document.getElementById('paymentMethodSelect');
    let option = select.options[select.selectedIndex];
    let qr = option.getAttribute('data-qr');
    let acc = option.getAttribute('data-acc');
    let inst = option.getAttribute('data-inst');
    let box = document.getElementById('qrPreviewBox');

    if (qr || acc || inst) {
        box.style.display = 'block';
        document.getElementById('qrTitleDisplay').textContent = selectedName + ' Payment Details';
        document.getElementById('qrAccDisplay').textContent = acc ? 'Account: ' + acc : '';
        document.getElementById('qrInstDisplay').textContent = inst ? inst : '';
        
        let img = document.getElementById('qrImageDisplay');
        if (qr) {
            img.src = qr;
            img.style.display = 'block';
        } else {
            img.style.display = 'none';
        }
    } else {
        box.style.display = 'none';
    }
}

// Trigger initial selection
document.addEventListener('DOMContentLoaded', () => {
    let select = document.getElementById('paymentMethodSelect');
    if (select && select.value) {
        showQRPreview(select.value);
    }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
