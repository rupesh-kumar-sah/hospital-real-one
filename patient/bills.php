<?php
/**
 * Hospital Management System — Patient: My Bills
 */

require_once __DIR__ . '/../includes/auth_middleware.php';
requireRole('patient');

$pageTitle = 'My Invoices & Bills';
$breadcrumbs = [['label' => 'Dashboard', 'url' => '/patient/dashboard.php'], ['label' => 'My Bills']];

$db = getDB();
$patient = getPatientByUserId(getUserId());
$patientId = $patient['id'] ?? 0;

$bills = $db->query("
    SELECT * FROM billing WHERE patient_id = {$patientId} ORDER BY created_at DESC
")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>My Billing Invoices</h1>
        <p class="page-subtitle">View consultation charges, medicine bills, and payment status</p>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Invoice #</th>
                    <th>Subtotal</th>
                    <th>Discount</th>
                    <th>Net Amount</th>
                    <th>Payment Status</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($bills)): ?>
                <tr><td colspan="7"><div class="empty-state"><p>No billing records found</p></div></td></tr>
                <?php else: ?>
                <?php foreach ($bills as $b): ?>
                <tr>
                    <td><strong><?= sanitize($b['invoice_number']) ?></strong></td>
                    <td><?= formatCurrency($b['subtotal']) ?></td>
                    <td><?= formatCurrency($b['discount']) ?></td>
                    <td><strong class="text-accent"><?= formatCurrency($b['net_amount']) ?></strong></td>
                    <td><?= statusBadge($b['payment_status'], PAYMENT_STATUSES) ?></td>
                    <td><?= formatDate($b['created_at']) ?></td>
                    <td>
                        <a href="/receptionist/view_invoice.php?patient_id=<?= $patientId ?>" class="btn btn-sm btn-primary">
                            <i class="fas fa-receipt"></i> View Invoice
                        </a>
                        <a href="/receptionist/print_invoice.php?patient_id=<?= $patientId ?>&autoprint=1" target="_blank" class="btn btn-sm btn-success">
                            <i class="fas fa-print"></i> Print
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
