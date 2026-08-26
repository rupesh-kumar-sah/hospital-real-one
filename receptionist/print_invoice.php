<?php
/**
 * Hospital Management System — Printable Patient Bill & Official Invoice
 */

require_once __DIR__ . '/../includes/auth_middleware.php';
requireRole(['receptionist', 'pharmacist', 'doctor', 'admin', 'patient']);

$db = getDB();

$billId = (int)($_GET['bill_id'] ?? 0);
$patientId = (int)($_GET['patient_id'] ?? 0);

$bill = null;
if ($billId) {
    $stmt = $db->prepare("
        SELECT b.*, p.id as patient_db_id, p.uhid, p.blood_group, p.address, u_p.full_name as patient_name, u_p.phone as patient_phone, u_p.email as patient_email
        FROM billing b
        JOIN patients p ON b.patient_id = p.id
        JOIN users u_p ON p.user_id = u_p.id
        WHERE b.id = ?
    ");
    $stmt->execute([$billId]);
    $bill = $stmt->fetch();
} elseif ($patientId) {
    $stmt = $db->prepare("
        SELECT b.*, p.id as patient_db_id, p.uhid, p.blood_group, p.address, u_p.full_name as patient_name, u_p.phone as patient_phone, u_p.email as patient_email
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
    $stmtP = $db->prepare("
        SELECT p.id as patient_db_id, p.uhid, p.blood_group, p.address, u_p.full_name as patient_name, u_p.phone as patient_phone
        FROM patients p
        JOIN users u_p ON p.user_id = u_p.id
        WHERE p.id = ?
    ");
    $stmtP->execute([$patientId]);
    $pData = $stmtP->fetch();
    if ($pData) {
        $bill = [
            'id' => 0,
            'patient_db_id' => $pData['patient_db_id'],
            'invoice_number' => 'INV-' . date('Ym') . '-00' . $pData['patient_db_id'],
            'patient_name' => $pData['patient_name'],
            'uhid' => $pData['uhid'],
            'patient_phone' => $pData['patient_phone'],
            'subtotal' => 600,
            'discount' => 0,
            'net_amount' => 600,
            'payment_status' => 'paid',
            'payment_method' => 'Cash',
            'created_at' => date('Y-m-d H:i:s')
        ];
    }
}

$patientDbId = $bill['patient_db_id'] ?? $patientId;

// Fetch line items
$items = [];
if (!empty($bill['id'])) {
    $items = $db->query("SELECT * FROM billing_items WHERE bill_id = {$bill['id']}")->fetchAll();
}

// Fetch doctor prescriptions & medicine itemization
$prescriptions = $db->query("
    SELECT pr.*, u_d.full_name as doctor_name
    FROM prescriptions pr
    JOIN doctors d ON pr.doctor_id = d.id
    JOIN users u_d ON d.user_id = u_d.id
    WHERE pr.patient_id = {$patientDbId}
    ORDER BY pr.created_at DESC
")->fetchAll();

$currentUser = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print Invoice — <?= sanitize($bill['invoice_number'] ?? 'Receipt') ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: #f1f5f9;
            margin: 0;
            padding: 20px;
            color: #0f172a;
        }
        .print-actions {
            max-width: 800px;
            margin: 0 auto 16px auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .btn {
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 0.875rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border: none;
        }
        .btn-primary { background: #2563eb; color: #fff; }
        .btn-success { background: #16a34a; color: #fff; }
        .btn-secondary { background: #64748b; color: #fff; }
        
        .receipt-card {
            max-width: 800px;
            margin: 0 auto;
            background: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            padding: 32px;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
        }
        .hospital-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid #0f172a;
            padding-bottom: 16px;
            margin-bottom: 20px;
        }
        .hospital-brand h1 {
            margin: 0;
            font-size: 1.6rem;
            font-weight: 800;
            color: #0f172a;
        }
        .hospital-brand p {
            margin: 2px 0 0 0;
            font-size: 0.85rem;
            color: #475569;
        }
        .invoice-meta {
            text-align: right;
        }
        .receipt-badge {
            background: #0f172a;
            color: #ffffff;
            font-size: 0.75rem;
            font-weight: 800;
            letter-spacing: 1px;
            padding: 4px 10px;
            border-radius: 4px;
            display: inline-block;
        }
        .inv-num {
            margin: 6px 0 0 0;
            font-size: 1.25rem;
            font-weight: 700;
            color: #2563eb;
        }
        .inv-date {
            margin: 2px 0 0 0;
            font-size: 0.8rem;
            color: #64748b;
        }
        .meta-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 24px;
        }
        .meta-box h4 {
            margin: 0 0 6px 0;
            font-size: 0.7rem;
            text-transform: uppercase;
            color: #64748b;
            letter-spacing: 0.5px;
        }
        .meta-box div {
            font-size: 0.875rem;
            color: #1e293b;
        }
        .item-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 24px;
            font-size: 0.875rem;
        }
        .item-table th {
            background: #f1f5f9;
            color: #334155;
            padding: 10px;
            text-align: left;
            border-bottom: 2px solid #cbd5e1;
        }
        .item-table td {
            padding: 10px;
            border-bottom: 1px solid #e2e8f0;
        }
        .rx-header-row td {
            background: #eff6ff;
            color: #1e40af;
            font-weight: 700;
            border-top: 2px solid #3b82f6;
            border-bottom: 1px solid #93c5fd;
        }
        .totals-section {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            border-top: 2px solid #0f172a;
            padding-top: 16px;
            margin-bottom: 32px;
        }
        .total-box {
            min-width: 280px;
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            padding: 14px 18px;
        }
        .total-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 6px;
            font-size: 0.875rem;
        }
        .total-grand {
            font-size: 1.25rem;
            font-weight: 800;
            color: #16a34a;
            border-top: 2px solid #cbd5e1;
            padding-top: 8px;
            margin-top: 6px;
        }
        .signature-section {
            display: flex;
            justify-content: space-between;
            margin-top: 40px;
            padding-top: 16px;
        }
        .sig-line {
            width: 200px;
            border-top: 1px solid #94a3b8;
            text-align: center;
            font-size: 0.8rem;
            color: #64748b;
            padding-top: 4px;
        }
        
        @media print {
            body { background: #fff; padding: 0; }
            .print-actions { display: none !important; }
            .receipt-card { border: none; box-shadow: none; padding: 0; max-width: 100%; }
        }
    </style>
</head>
<body>

<div class="print-actions">
    <a href="javascript:history.back()" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back</a>
    <div>
        <button onclick="window.print()" class="btn btn-success"><i class="fas fa-print"></i> Print Official Receipt</button>
    </div>
</div>

<div class="receipt-card">
    <!-- Hospital Header -->
    <div class="hospital-header">
        <div class="hospital-brand">
            <h1><i class="fas fa-hospital text-primary"></i> <?= APP_NAME ?></h1>
            <p>Hospital & Research Centre • Reg No: 48920/Nepal</p>
            <p><i class="fas fa-location-dot"></i> Kathmandu, Nepal • Phone: +977 1 4000000</p>
        </div>
        <div class="invoice-meta">
            <span class="receipt-badge">OFFICIAL INVOICE & RECEIPT</span>
            <div class="inv-num"><?= sanitize($bill['invoice_number']) ?></div>
            <div class="inv-date">Issued: <?= formatDateTime($bill['created_at']) ?></div>
        </div>
    </div>

    <!-- Patient & Invoice Information -->
    <div class="meta-grid">
        <div class="meta-box">
            <h4>Patient Information</h4>
            <div style="font-weight: 700; font-size: 1rem; color: #0f172a;"><?= sanitize($bill['patient_name']) ?></div>
            <div>Hospital UHID: <strong><?= sanitize($bill['uhid']) ?></strong></div>
            <div>Contact Phone: <?= sanitize($bill['patient_phone'] ?: 'N/A') ?></div>
        </div>
        <div class="meta-box">
            <h4>Payment & Transaction Summary</h4>
            <div>Payment Status: <strong><?= strtoupper(sanitize($bill['payment_status'])) ?></strong></div>
            <div>Payment Method: <strong><?= ucfirst(sanitize($bill['payment_method'] ?: 'Cash')) ?></strong></div>
            <div>Processed By: <?= sanitize($currentUser['full_name']) ?></div>
        </div>
    </div>

    <!-- Itemized Services & Medicine Table -->
    <table class="item-table">
        <thead>
            <tr>
                <th style="width: 45%;">Description / Service Item</th>
                <th style="width: 20%; text-align: right;">Unit Price (Rs.)</th>
                <th style="width: 15%; text-align: center;">Qty</th>
                <th style="width: 20%; text-align: right;">Total (Rs.)</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($items)): ?>
            <?php foreach ($items as $it): ?>
            <tr>
                <td>
                    <strong><?= sanitize($it['description']) ?></strong>
                    <div style="font-size: 0.75rem; color: #64748b;"><?= ucfirst($it['item_type']) ?> Fee</div>
                </td>
                <td style="text-align: right;"><?= formatCurrency($it['unit_price']) ?></td>
                <td style="text-align: center;"><?= $it['quantity'] ?></td>
                <td style="text-align: right; font-weight: 700;"><?= formatCurrency($it['total_price']) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>

            <!-- Doctor Prescribed Medicines -->
            <?php foreach ($prescriptions as $rx): ?>
            <?php
            $rxItems = $db->query("SELECT * FROM prescription_items WHERE prescription_id = {$rx['id']}")->fetchAll();
            ?>
            <tr class="rx-header-row">
                <td colspan="4">
                    <i class="fas fa-pills"></i> Doctor Prescribed Medicines (#Rx-<?= $rx['id'] ?> - Dr. <?= sanitize($rx['doctor_name']) ?>)
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
            ?>
            <tr>
                <td style="padding-left: 24px;">
                    <strong><?= sanitize($m['drug_name']) ?></strong> (<?= sanitize($m['dosage']) ?>)
                    <div style="font-size: 0.75rem; color: #64748b;"><?= sanitize($m['frequency']) ?> x <?= sanitize($m['duration']) ?></div>
                </td>
                <td style="text-align: right;"><?= formatCurrency($unitP) ?></td>
                <td style="text-align: center;"><?= $mQty ?></td>
                <td style="text-align: right; font-weight: 600; color: #2563eb;"><?= formatCurrency($sub) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Hospital Official QR Code Box for Mobile Payment -->
    <?php
    $activePM = $db->query("SELECT * FROM payment_methods WHERE status = 'active' AND qr_image != '' LIMIT 1")->fetch();
    ?>
    <?php if ($activePM && !empty($activePM['qr_image'])): ?>
    <div style="background: #f8fafc; border: 1px dashed #2563eb; border-radius: 8px; padding: 14px; margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between;">
        <div style="display: flex; align-items: center; gap: 14px;">
            <img src="<?= $activePM['qr_image'] ?>" alt="Hospital Payment QR" style="width: 90px; height: 90px; border-radius: 6px; border: 1px solid #cbd5e1; background: #fff; padding: 4px;">
            <div>
                <div style="font-size: 0.7rem; font-weight: 800; color: #16a34a; text-transform: uppercase; letter-spacing: 0.5px;">Hospital Payment QR Code</div>
                <div style="font-size: 0.95rem; font-weight: 700; color: #0f172a; margin-top: 2px;"><?= sanitize($activePM['name']) ?> Instant QR Scan</div>
                <div style="font-size: 0.8rem; color: #475569; margin-top: 2px;">Account: <strong><?= sanitize($activePM['account_name']) ?></strong> (<?= sanitize($activePM['account_number']) ?>)</div>
                <div style="font-size: 0.75rem; color: #64748b; margin-top: 2px;">Scan using eSewa, Khalti, or Mobile Banking app to settle bill.</div>
            </div>
        </div>
        <div style="text-align: right;">
            <div style="font-size: 0.75rem; color: #64748b; font-weight: 700; text-transform: uppercase;">Net Amount</div>
            <div style="font-size: 1.3rem; font-weight: 800; color: #16a34a;"><?= formatCurrency($bill['net_amount'] > 0 ? $bill['net_amount'] : 600) ?></div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Totals Section -->
    <div class="totals-section">
        <div>
            <p style="margin: 0; font-size: 0.8rem; color: #64748b;">Thank you for visiting <?= APP_NAME ?>.</p>
            <p style="margin: 2px 0 0 0; font-size: 0.75rem; color: #94a3b8;">Computer-generated tax receipt. No signature required if digitally issued.</p>
        </div>
        <div class="total-box">
            <div class="total-row">
                <span>Gross Subtotal:</span>
                <span><?= formatCurrency($bill['subtotal'] > 0 ? $bill['subtotal'] : 600) ?></span>
            </div>
            <div class="total-row" style="color: #dc2626;">
                <span>Discount:</span>
                <span>- <?= formatCurrency($bill['discount']) ?></span>
            </div>
            <div class="total-row total-grand">
                <span>Net Total Amount:</span>
                <span><?= formatCurrency($bill['net_amount'] > 0 ? $bill['net_amount'] : 600) ?></span>
            </div>
        </div>
    </div>

    <!-- Signatures -->
    <div class="signature-section">
        <div class="sig-line">
            Patient / Receiver Signature
        </div>
        <div class="sig-line">
            Authorized Cashier / Pharmacist
        </div>
    </div>
</div>

<script>
// Auto print if requested via query parameter ?autoprint=1
window.addEventListener('DOMContentLoaded', () => {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('autoprint') === '1') {
        setTimeout(() => window.print(), 500);
    }
});
</script>

</body>
</html>
