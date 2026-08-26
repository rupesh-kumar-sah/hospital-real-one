<?php
/**
 * Hospital Management System — Detailed Patient Bill & Receipt Viewer
 */

require_once __DIR__ . '/../includes/auth_middleware.php';
requireRole(['receptionist', 'pharmacist', 'doctor', 'admin', 'patient']);

$db = getDB();

$billId = (int)($_GET['bill_id'] ?? 0);
$patientId = (int)($_GET['patient_id'] ?? 0);

$bill = null;
if ($billId) {
    $stmt = $db->prepare("
        SELECT b.*, p.uhid, p.blood_group, p.address, u_p.full_name as patient_name, u_p.phone as patient_phone, u_p.email as patient_email
        FROM billing b
        JOIN patients p ON b.patient_id = p.id
        JOIN users u_p ON p.user_id = u_p.id
        WHERE b.id = ?
    ");
    $stmt->execute([$billId]);
    $bill = $stmt->fetch();
} elseif ($patientId) {
    $stmt = $db->prepare("
        SELECT b.*, p.uhid, p.blood_group, p.address, u_p.full_name as patient_name, u_p.phone as patient_phone, u_p.email as patient_email
        FROM billing b
        JOIN patients p ON b.patient_id = p.id
        JOIN users u_p ON p.user_id = u_p.id
        WHERE b.patient_id = ?
        ORDER BY b.created_at DESC LIMIT 1
    ");
    $stmt->execute([$patientId]);
    $bill = $stmt->fetch();
}

if (!$bill && $patientId) {
    // If no bill exists yet, fetch patient data to display draft estimate
    $stmtP = $db->prepare("
        SELECT p.id as patient_id, p.uhid, p.blood_group, p.address, u_p.full_name as patient_name, u_p.phone as patient_phone
        FROM patients p
        JOIN users u_p ON p.user_id = u_p.id
        WHERE p.id = ?
    ");
    $stmtP->execute([$patientId]);
    $pData = $stmtP->fetch();
    if ($pData) {
        $bill = [
            'id' => 0,
            'invoice_number' => 'ESTIMATE-PAT-' . $pData['patient_id'],
            'patient_name' => $pData['patient_name'],
            'uhid' => $pData['uhid'],
            'patient_phone' => $pData['patient_phone'],
            'subtotal' => 0,
            'discount' => 0,
            'net_amount' => 0,
            'payment_status' => 'unpaid',
            'payment_method' => 'Cash',
            'created_at' => date('Y-m-d H:i:s')
        ];
    }
}

$pageTitle = 'Patient Invoice & Bill Details';
$breadcrumbs = [['label' => 'Dashboard', 'url' => '/receptionist/dashboard.php'], ['label' => 'Invoice Details']];

include __DIR__ . '/../includes/header.php';
?>

<?php if (!$bill): ?>
<div class="card p-24 text-center">
    <i class="fas fa-file-invoice-dollar text-danger" style="font-size: 3rem; margin-bottom: 16px;"></i>
    <h2>Invoice Not Found</h2>
    <p class="text-muted">The requested billing record could not be found.</p>
    <a href="/receptionist/billing.php" class="btn btn-primary mt-16"><i class="fas fa-arrow-left"></i> Back to Billing</a>
</div>
<?php else: ?>

<?php
// Fetch line items
$items = [];
if (!empty($bill['id'])) {
    $items = $db->query("SELECT * FROM billing_items WHERE bill_id = {$bill['id']}")->fetchAll();
}

// Fetch doctor prescriptions & medicine itemization for this patient
$prescriptions = $db->query("
    SELECT pr.*, u_d.full_name as doctor_name
    FROM prescriptions pr
    JOIN doctors d ON pr.doctor_id = d.id
    JOIN users u_d ON d.user_id = u_d.id
    WHERE pr.patient_id = " . ($bill['patient_id'] ?? $patientId) . "
    ORDER BY pr.created_at DESC
")->fetchAll();

$paymentMethods = $db->query("SELECT * FROM payment_methods WHERE status = 'active'")->fetchAll();
?>

<div style="max-width: 900px; margin: 0 auto;">
    <div class="d-flex justify-between align-center mb-16 no-print">
        <a href="javascript:history.back()" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back</a>
        <div class="d-flex gap-8">
            <button onclick="window.print()" class="btn btn-success"><i class="fas fa-print"></i> Print Official Receipt</button>
        </div>
    </div>

    <div class="card p-24" id="printableInvoice" style="background: #fff; color: #1e293b; border: 1px solid #cbd5e1; font-family: 'Inter', sans-serif;">
        <!-- Header -->
        <div class="d-flex justify-between align-center border-bottom pb-16 mb-16" style="border-bottom: 2px solid #334155;">
            <div>
                <h1 style="color: #0f172a; margin: 0; font-size: 1.5rem; font-weight: 800;">
                    <i class="fas fa-hospital text-primary"></i> <?= APP_NAME ?>
                </h1>
                <p style="margin: 4px 0 0 0; color: #64748b; font-size: 0.875rem;">Hospital & Research Centre • Kathmandu, Nepal</p>
                <p style="margin: 2px 0 0 0; color: #64748b; font-size: 0.8125rem;"><i class="fas fa-phone"></i> Emergency Hotline: +977 1 4000000</p>
            </div>
            <div class="text-right">
                <span class="badge" style="background: #0f172a; color: #fff; font-size: 0.875rem; padding: 6px 12px;">OFFICIAL RECEIPT</span>
                <h3 style="margin: 8px 0 0 0; color: #0284c7; font-size: 1.1rem; font-weight: 700;"><?= sanitize($bill['invoice_number']) ?></h3>
                <p style="margin: 2px 0 0 0; color: #64748b; font-size: 0.8125rem;">Date: <?= formatDate($bill['created_at']) ?></p>
            </div>
        </div>

        <!-- Patient & Billing Meta -->
        <div class="grid-2 gap-16 mb-24 p-16" style="background: #f8fafc; border-radius: 8px; border: 1px solid #e2e8f0;">
            <div>
                <h4 style="margin: 0 0 8px 0; color: #475569; font-size: 0.75rem; text-transform: uppercase;">Billed To (Patient Information)</h4>
                <div style="font-size: 1rem; font-weight: 700; color: #0f172a;"><?= sanitize($bill['patient_name']) ?></div>
                <div style="font-size: 0.8125rem; color: #475569;">UHID: <code style="background: #e2e8f0; padding: 2px 6px; border-radius: 4px; color: #0f172a; font-weight: 700;"><?= sanitize($bill['uhid']) ?></code></div>
                <div style="font-size: 0.8125rem; color: #475569;">Phone: <?= sanitize($bill['patient_phone'] ?: 'N/A') ?></div>
            </div>
            <div>
                <h4 style="margin: 0 0 8px 0; color: #475569; font-size: 0.75rem; text-transform: uppercase;">Payment Summary & Status</h4>
                <div style="font-size: 0.875rem; color: #334155;">Status: <?= statusBadge($bill['payment_status'], PAYMENT_STATUSES) ?></div>
                <div style="font-size: 0.875rem; color: #334155;">Payment Method: <strong><?= ucfirst(sanitize($bill['payment_method'] ?: 'Cash')) ?></strong></div>
            </div>
        </div>

        <!-- Itemized Services & Medicine Table -->
        <h3 style="margin: 0 0 12px 0; color: #0f172a; font-size: 1rem; font-weight: 700; border-bottom: 1px solid #e2e8f0; padding-bottom: 6px;">
            <i class="fas fa-list-check text-primary"></i> Itemized Billing Charges & Medicine Breakdown
        </h3>

        <table style="width: 100%; border-collapse: collapse; margin-bottom: 24px; font-size: 0.875rem;">
            <thead>
                <tr style="background: #f1f5f9; color: #334155; border-bottom: 2px solid #cbd5e1; text-align: left;">
                    <th style="padding: 10px; width: 40%;">Description / Service</th>
                    <th style="padding: 10px; text-align: right; width: 20%;">Unit Price (Rs.)</th>
                    <th style="padding: 10px; text-align: center; width: 15%;">Qty</th>
                    <th style="padding: 10px; text-align: right; width: 25%;">Total Amount (Rs.)</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($items)): ?>
                <?php foreach ($items as $it): ?>
                <tr style="border-bottom: 1px solid #e2e8f0;">
                    <td style="padding: 10px;">
                        <strong><?= sanitize($it['description']) ?></strong>
                        <span class="text-xs text-muted" style="display:block;"><?= ucfirst($it['item_type']) ?> Charge</span>
                    </td>
                    <td style="padding: 10px; text-align: right;"><?= formatCurrency($it['unit_price']) ?></td>
                    <td style="padding: 10px; text-align: center;"><?= $it['quantity'] ?></td>
                    <td style="padding: 10px; text-align: right; font-weight: 700; color: #0f172a;"><?= formatCurrency($it['total_price']) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>

                <!-- Detailed Doctor Prescribed Medicines Breakdown -->
                <?php foreach ($prescriptions as $rx): ?>
                <?php
                $rxItems = $db->query("SELECT * FROM prescription_items WHERE prescription_id = {$rx['id']}")->fetchAll();
                $rxGrand = 0;
                ?>
                <tr style="background: #f0f9ff; border-top: 2px solid #0284c7; border-bottom: 1px solid #bae6fd;">
                    <td colspan="4" style="padding: 8px 10px;">
                        <strong style="color: #0369a1;"><i class="fas fa-pills"></i> Doctor Prescribed Medicines (#Rx-<?= $rx['id'] ?> - Dr. <?= sanitize($rx['doctor_name']) ?>)</strong>
                    </td>
                </tr>
                <?php foreach ($rxItems as $m): ?>
                <?php
                $invStmt = $db->prepare("SELECT * FROM pharmacy_inventory WHERE drug_name LIKE ? AND status = 'active' LIMIT 1");
                $invStmt->execute(['%' . $m['drug_name'] . '%']);
                $inv = $invStmt->fetch();
                $unitP = $inv ? (float)$inv['selling_price'] : 10.00;
                $mQty = max(1, (int)($m['quantity'] ?: 10));
                $sub = $unitP * $mQty;
                $rxGrand += $sub;
                ?>
                <tr style="border-bottom: 1px dashed #cbd5e1; background: #fafafa;">
                    <td style="padding: 8px 10px 8px 24px;">
                        <strong><?= sanitize($m['drug_name']) ?></strong> (<?= sanitize($m['dosage']) ?>)
                        <div style="font-size: 0.75rem; color: #64748b;"><?= sanitize($m['frequency']) ?> x <?= sanitize($m['duration']) ?></div>
                    </td>
                    <td style="padding: 8px 10px; text-align: right;"><?= formatCurrency($unitP) ?></td>
                    <td style="padding: 8px 10px; text-align: center;"><?= $mQty ?></td>
                    <td style="padding: 8px 10px; text-align: right; font-weight: 600; color: #0284c7;"><?= formatCurrency($sub) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Hospital Official QR Code Payment Box -->
        <?php
        $activePM = $db->query("SELECT * FROM payment_methods WHERE status = 'active' AND qr_image != '' LIMIT 1")->fetch();
        ?>
        <?php if ($activePM && !empty($activePM['qr_image'])): ?>
        <div style="background: #f8fafc; border: 1px dashed #2563eb; border-radius: 8px; padding: 16px; margin-bottom: 24px; display: flex; align-items: center; justify-content: space-between;">
            <div style="display: flex; align-items: center; gap: 16px;">
                <img src="<?= $activePM['qr_image'] ?>" alt="Hospital Payment QR Code" style="width: 110px; height: 110px; border-radius: 8px; border: 1px solid #cbd5e1; background: #fff; padding: 4px;">
                <div>
                    <span class="badge" style="background: #16a34a; color: #fff; font-weight: 700; font-size: 0.75rem;">SCAN & PAY MOBILE QR</span>
                    <h4 style="margin: 6px 0 2px 0; color: #0f172a; font-size: 1rem; font-weight: 700;"><?= sanitize($activePM['name']) ?> Hospital QR Code</h4>
                    <p style="margin: 0; color: #475569; font-size: 0.8125rem;">Account: <strong><?= sanitize($activePM['account_name']) ?></strong> (<?= sanitize($activePM['account_number']) ?>)</p>
                    <p style="margin: 4px 0 0 0; color: #64748b; font-size: 0.75rem;"><i class="fas fa-qrcode text-primary"></i> Open eSewa, Khalti, or Mobile Banking app to scan & pay this bill instantly.</p>
                </div>
            </div>
            <div style="text-align: right;">
                <div style="font-size: 0.75rem; color: #64748b; font-weight: 600; text-transform: uppercase;">Amount to Pay</div>
                <div style="font-size: 1.4rem; font-weight: 800; color: #16a34a;"><?= formatCurrency($bill['net_amount'] > 0 ? $bill['net_amount'] : 600) ?></div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Totals Box -->
        <div class="d-flex justify-between align-center pt-16 border-top" style="border-top: 2px solid #334155;">
            <div>
                <p style="margin: 0; font-size: 0.8125rem; color: #64748b;">Thank you for choosing <?= APP_NAME ?>.</p>
                <p style="margin: 4px 0 0 0; font-size: 0.75rem; color: #94a3b8;">This is a computer-generated official billing summary receipt.</p>
            </div>
            <div style="min-width: 280px; background: #f8fafc; padding: 16px; border-radius: 8px; border: 1px solid #cbd5e1;">
                <div class="d-flex justify-between mb-8" style="font-size: 0.875rem;">
                    <span>Subtotal:</span>
                    <span><?= formatCurrency($bill['subtotal'] > 0 ? $bill['subtotal'] : 600) ?></span>
                </div>
                <div class="d-flex justify-between mb-8 text-danger" style="font-size: 0.875rem;">
                    <span>Discount:</span>
                    <span>- <?= formatCurrency($bill['discount']) ?></span>
                </div>
                <div class="d-flex justify-between pt-8 border-top" style="font-size: 1.25rem; font-weight: 800; color: #16a34a; border-top: 2px solid #cbd5e1;">
                    <span>Net Total Amount:</span>
                    <span><?= formatCurrency($bill['net_amount'] > 0 ? $bill['net_amount'] : 600) ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
@media print {
    .sidebar, .top-header, .no-print, .breadcrumb, .page-header { display: none !important; }
    .main-content { margin: 0 !important; padding: 0 !important; }
    .app-layout { display: block !important; }
    body { background: #fff !important; color: #000 !important; }
    #printableInvoice { border: none !important; box-shadow: none !important; }
}
</style>

<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
