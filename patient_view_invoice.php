<?php
/**
 * Hospital Management System — Patient Frontend Invoice Viewer
 * Dedicated view module for patients on the frontend site (NO redirects to backend staff pages)
 */

require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/includes/functions.php';

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

if (!$bill) {
    die("Invoice not found.");
}

$items = [];
if ($bill['id'] > 0) {
    $stmtItems = $db->prepare("SELECT * FROM billing_items WHERE bill_id = ?");
    $stmtItems->execute([$bill['id']]);
    $items = $stmtItems->fetchAll();
}

// Fetch Doctor Consultation
$doctorInfo = $db->query("
    SELECT u_d.full_name as doctor_name, dep.name as dept_name
    FROM appointments a
    JOIN doctors d ON a.doctor_id = d.id
    JOIN users u_d ON d.user_id = u_d.id
    LEFT JOIN departments dep ON a.department_id = dep.id
    WHERE a.patient_id = {$bill['patient_id']}
    ORDER BY a.id DESC LIMIT 1
")->fetch();

$activePM = $db->query("SELECT * FROM payment_methods WHERE status = 'active' AND qr_image != '' LIMIT 1")->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Invoice <?= sanitize($bill['invoice_number']) ?> — <?= APP_NAME ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/frontend.css">
    <style>
        body { background: #f8fafc; font-family: 'Inter', sans-serif; padding: 24px; }
        .invoice-card { background: #fff; max-width: 800px; margin: 0 auto; border-radius: 16px; box-shadow: 0 10px 25px rgba(0,0,0,0.08); padding: 36px; border: 1px solid #e2e8f0; }
        .inv-header { display: flex; justify-content: space-between; border-bottom: 2px solid #e2e8f0; padding-bottom: 20px; margin-bottom: 24px; }
        .inv-table { width: 100%; border-collapse: collapse; margin: 24px 0; }
        .inv-table th { background: #f1f5f9; padding: 12px; text-align: left; font-size: 0.85rem; color: #475569; text-transform: uppercase; }
        .inv-table td { padding: 12px; border-bottom: 1px solid #e2e8f0; font-size: 0.9rem; }
    </style>
</head>
<body>

<div class="invoice-card">
    <div style="display: flex; justify-content: space-between; margin-bottom: 20px;">
        <a href="/index.php" class="btn-book" style="background: #64748b;"><i class="fas fa-arrow-left"></i> Back to Patient Portal</a>
        <a href="/patient_print_invoice.php?bill_id=<?= $bill['id'] ?>&autoprint=1" target="_blank" class="btn-book"><i class="fas fa-print"></i> Print Official Receipt</a>
    </div>

    <div class="inv-header">
        <div>
            <h1 style="margin:0 0 6px 0; color:#0f172a; font-size: 1.6rem;"><i class="fas fa-hospital text-accent"></i> <?= APP_NAME ?></h1>
            <p style="margin:0; color:#64748b; font-size: 0.875rem;">Kathmandu, Nepal • Phone: +977 1 4000000</p>
        </div>
        <div style="text-align: right;">
            <h3 style="margin:0 0 4px 0; color:#0ea5e9;"><?= sanitize($bill['invoice_number']) ?></h3>
            <span class="badge" style="background:#dcfce7; color:#16a34a; font-weight:700;"><?= strtoupper($bill['payment_status']) ?></span>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; background:#f8fafc; padding: 16px; border-radius: 8px; margin-bottom: 20px;">
        <div>
            <h4 style="margin:0 0 6px 0; color:#334155;">Patient Details</h4>
            <p style="margin:0; font-size:0.875rem; color:#475569;"><strong><?= sanitize($bill['patient_name']) ?></strong> (<?= sanitize($bill['uhid']) ?>)</p>
            <p style="margin:0; font-size:0.875rem; color:#475569;">Phone: <?= sanitize($bill['patient_phone']) ?></p>
        </div>
        <div>
            <h4 style="margin:0 0 6px 0; color:#334155;">Attending Doctor</h4>
            <p style="margin:0; font-size:0.875rem; color:#475569;"><strong>Dr. <?= sanitize($doctorInfo['doctor_name'] ?? 'General Practitioner') ?></strong></p>
            <p style="margin:0; font-size:0.875rem; color:#475569;"><?= sanitize($doctorInfo['dept_name'] ?? 'OPD Department') ?></p>
        </div>
    </div>

    <table class="inv-table">
        <thead>
            <tr>
                <th>Description / Service</th>
                <th>Qty</th>
                <th>Unit Price</th>
                <th>Total</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $item): ?>
            <tr>
                <td><strong><?= sanitize($item['description']) ?></strong></td>
                <td><?= $item['quantity'] ?></td>
                <td><?= formatCurrency($item['unit_price']) ?></td>
                <td><strong><?= formatCurrency($item['total_price']) ?></strong></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div style="display: flex; justify-content: space-between; align-items: center; border-top: 2px solid #e2e8f0; padding-top: 20px;">
        <div>
            <?php if ($activePM): ?>
            <div style="display: flex; align-items: center; gap: 12px;">
                <img src="<?= $activePM['qr_image'] ?>" style="width: 80px; height: 80px; border-radius: 6px; border: 1px solid #cbd5e1;">
                <div style="font-size: 0.8rem; color: #475569;">
                    <strong>Hospital Payment QR</strong><br>
                    Pay via eSewa / Khalti / Fonepay
                </div>
            </div>
            <?php endif; ?>
        </div>
        <div style="text-align: right;">
            <div style="font-size: 1.3rem; font-weight: 800; color: #0f172a;">Grand Total: <span style="color:#0ea5e9;"><?= formatCurrency($bill['net_amount']) ?></span></div>
            <p style="font-size:0.8rem; color:#16a34a; font-weight:700; margin:4px 0 0 0;">Status: Paid & Verified</p>
        </div>
    </div>
</div>

</body>
</html>
