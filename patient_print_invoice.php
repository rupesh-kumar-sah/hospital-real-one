<?php
/**
 * Hospital Management System — Patient Printable Paper Receipt
 * Dedicated receipt module for patients on the frontend site
 */

require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth_middleware.php';

requireLogin();

$db = getDB();

$billId = (int)($_GET['bill_id'] ?? 0);
$patientId = (int)($_GET['patient_id'] ?? 0);

$bill = null;
if ($billId) {
    $stmt = $db->prepare("
        SELECT b.*, p.uhid, p.blood_group, p.address, u_p.full_name as patient_name, u_p.phone as patient_phone
        FROM billing b
        JOIN patients p ON b.patient_id = p.id
        JOIN users u_p ON p.user_id = u_p.id
        WHERE b.id = ?
    ");
    $stmt->execute([$billId]);
    $bill = $stmt->fetch();
} elseif ($patientId) {
    $stmt = $db->prepare("
        SELECT b.*, p.uhid, p.blood_group, p.address, u_p.full_name as patient_name, u_p.phone as patient_phone
        FROM billing b
        JOIN patients p ON b.patient_id = p.id
        JOIN users u_p ON p.user_id = u_p.id
        WHERE b.patient_id = ?
        ORDER BY b.created_at DESC LIMIT 1
    ");
    $stmt->execute([$patientId]);
    $bill = $stmt->fetch();
}

if (!$bill) {
    die("Receipt not found.");
}

requirePatientOwnership((int)$bill['patient_id']);

$items = [];
if ($bill['id'] > 0) {
    $stmtItems = $db->prepare("SELECT * FROM billing_items WHERE bill_id = ?");
    $stmtItems->execute([$bill['id']]);
    $items = $stmtItems->fetchAll();
}

$activePM = $db->query("SELECT * FROM payment_methods WHERE status = 'active' AND qr_image IS NOT NULL AND qr_image != '' LIMIT 1")->fetch();
$autoPrint = isset($_GET['autoprint']) && $_GET['autoprint'] == '1';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Print Receipt <?= sanitize($bill['invoice_number']) ?></title>
    <style>
        body { font-family: 'Courier New', Courier, monospace; width: 800px; margin: 0 auto; padding: 20px; color: #000; }
        .receipt-header { text-align: center; border-bottom: 2px dashed #000; padding-bottom: 10px; margin-bottom: 15px; }
        .receipt-table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        .receipt-table th, .receipt-table td { padding: 6px; text-align: left; font-size: 13px; }
        .receipt-table th { border-bottom: 1px solid #000; }
        .no-print { margin-bottom: 15px; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>

<div class="no-print">
    <button onclick="window.print()" style="padding: 8px 16px; background: #0284c7; color: #fff; border: none; border-radius: 4px; cursor: pointer; font-weight: bold;">
        🖨️ Print Receipt / Save as PDF
    </button>
</div>

<div class="receipt-header">
    <h2><?= APP_NAME ?></h2>
    <p>Official Patient Consultation Receipt</p>
    <p>Invoice #: <strong><?= sanitize($bill['invoice_number']) ?></strong> | Date: <?= formatDate($bill['created_at']) ?></p>
</div>

<div>
    <p>Patient Name: <strong><?= sanitize($bill['patient_name']) ?></strong> (UHID: <?= sanitize($bill['uhid']) ?>)</p>
    <p>Phone: <?= sanitize($bill['patient_phone']) ?></p>
</div>

<table class="receipt-table">
    <thead>
        <tr>
            <th>Item Description</th>
            <th>Qty</th>
            <th>Rate</th>
            <th>Amount</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($items as $item): ?>
        <tr>
            <td><?= sanitize($item['description']) ?></td>
            <td><?= $item['quantity'] ?></td>
            <td><?= formatCurrency($item['unit_price']) ?></td>
            <td><?= formatCurrency($item['total_price']) ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<div style="text-align: right; border-top: 1px solid #000; padding-top: 10px;">
    <h3>Total Amount Paid: <?= formatCurrency($bill['net_amount']) ?></h3>
    <p>Status: PAID</p>
</div>

<?php if ($activePM && !empty($activePM['qr_image'])): ?>
<div style="border: 1px dashed #333; padding: 10px; margin-top: 15px; display: flex; align-items: center; gap: 12px;">
    <img src="<?= htmlspecialchars($activePM['qr_image'], ENT_QUOTES, 'UTF-8') ?>" alt="Hospital Payment QR" style="width: 70px; height: 70px; border: 1px solid #999;">
    <div style="font-size: 12px;">
        <strong>Hospital Payment QR Code</strong><br>
        <?= sanitize($activePM['name']) ?> — <?= sanitize($activePM['account_name']) ?> (<?= sanitize($activePM['account_number']) ?>)<br>
        <em>Scan using eSewa / Khalti / Mobile Banking to pay.</em>
    </div>
</div>
<?php endif; ?>

<?php if ($autoPrint): ?>
<script>
window.onload = function() { window.print(); }
</script>
<?php endif; ?>

</body>
</html>
