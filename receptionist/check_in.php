<?php
/**
 * Hospital Management System — Receptionist: Patient Check-In & Token
 */

require_once __DIR__ . '/../includes/auth_middleware.php';
requireRole(['receptionist', 'admin']);

$pageTitle = 'Patient Check-In';
$breadcrumbs = [['label' => 'Dashboard', 'url' => '/receptionist/dashboard.php'], ['label' => 'Check-In']];

$db = getDB();

if (isset($_GET['id'])) {
    $apptId = (int)$_GET['id'];
    
    $appt = $db->query("
        SELECT a.*, d.consultation_fee, u_d.full_name as doctor_name
        FROM appointments a
        JOIN doctors d ON a.doctor_id = d.id
        JOIN users u_d ON d.user_id = u_d.id
        WHERE a.id = {$apptId}
    ")->fetch();

    if ($appt) {
        $stmt = $db->prepare("UPDATE appointments SET status = 'checked_in' WHERE id = ?");
        $stmt->execute([$apptId]);

        // Auto-generate / verify OPD Consultation Invoice
        $fee = (float)($appt['consultation_fee'] ?: 500);
        $patientId = $appt['patient_id'];

        $existingBill = $db->query("SELECT id FROM billing WHERE patient_id = {$patientId} AND DATE(created_at) = DATE('now') LIMIT 1")->fetch();

        if (!$existingBill) {
            $invNum = generateInvoiceNumber();
            $stmtBill = $db->prepare("INSERT INTO billing (patient_id, invoice_number, subtotal, discount, tax, net_amount, payment_status, payment_method, payment_date, created_by) VALUES (?, ?, ?, 0, 0, ?, 'paid', 'Cash', CURRENT_TIMESTAMP, ?)");
            $stmtBill->execute([$patientId, $invNum, $fee, $fee, getUserId()]);
            $billId = $db->lastInsertId();

            $stmtItem = $db->prepare("INSERT INTO billing_items (bill_id, item_type, description, quantity, unit_price, total_price) VALUES (?, 'consultation', ?, 1, ?, ?)");
            $stmtItem->execute([$billId, "OPD Consultation Fee - Dr. {$appt['doctor_name']} (Token #{$appt['token_number']})", $fee, $fee]);

            logAudit('create', 'billing', $billId, "Generated OPD check-in invoice {$invNum} for Rs. {$fee}");
        }

        setFlash('success', "Patient checked in & OPD consultation fee verified successfully! Token #{$appt['token_number']}");
        header('Location: /receptionist/check_in.php');
        exit;
    }
}

$todayAppts = $db->query("
    SELECT a.*, p.uhid, u_p.full_name as patient_name, u_d.full_name as doctor_name, dep.name as dept_name
    FROM appointments a
    JOIN patients p ON a.patient_id = p.id
    JOIN users u_p ON p.user_id = u_p.id
    JOIN doctors d ON a.doctor_id = d.id
    JOIN users u_d ON d.user_id = u_d.id
    LEFT JOIN departments dep ON a.department_id = dep.id
    WHERE a.appointment_date = DATE('now') OR a.appointment_date = DATE('now', 'localtime')
    ORDER BY a.status DESC, a.token_number ASC
")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Patient Check-In & OPD Token Desk</h1>
        <p class="page-subtitle">Check in arriving patients, verify patient UHID, collect OPD consultation fee, and issue checked receipts</p>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Token #</th>
                    <th>Patient Name</th>
                    <th>UHID</th>
                    <th>Doctor</th>
                    <th>Status</th>
                    <th>Actions & Checked Receipts</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($todayAppts as $a): ?>
                <tr>
                    <td><span class="badge badge-primary" style="font-size: 1rem;">#<?= $a['token_number'] ?></span></td>
                    <td><strong><?= sanitize($a['patient_name']) ?></strong></td>
                    <td><code><?= sanitize($a['uhid']) ?></code></td>
                    <td>Dr. <?= sanitize($a['doctor_name']) ?> (<?= sanitize($a['dept_name']) ?>)</td>
                    <td><?= statusBadge($a['status'], APPOINTMENT_STATUSES) ?></td>
                    <td>
                        <?php if ($a['status'] === 'scheduled'): ?>
                        <a href="/receptionist/check_in.php?id=<?= $a['id'] ?>" class="btn btn-sm btn-success">
                            <i class="fas fa-clipboard-check"></i> Check In & Verify Fee
                        </a>
                        <?php else: ?>
                        <div class="d-flex gap-4">
                            <a href="/receptionist/view_invoice.php?patient_id=<?= $a['patient_id'] ?>" class="btn btn-sm btn-primary">
                                <i class="fas fa-receipt"></i> Checked Receipt
                            </a>
                            <a href="/receptionist/print_invoice.php?patient_id=<?= $a['patient_id'] ?>&autoprint=1" target="_blank" class="btn btn-sm btn-success">
                                <i class="fas fa-print"></i> Print
                            </a>
                        </div>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
