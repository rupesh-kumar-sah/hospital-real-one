<?php
/**
 * Hospital Management System — Patient Bills View
 */

require_once __DIR__ . '/../includes/auth_middleware.php';
requireRole('patient');

$pageTitle = 'My Hospital Bills';
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
        <h1>My Hospital Invoices & Bills</h1>
        <p class="page-subtitle">View consultation fees, lab charges and payment transaction history</p>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Invoice #</th>
                    <th>Date</th>
                    <th>Subtotal</th>
                    <th>Discount</th>
                    <th>Total Net Amount</th>
                    <th>Payment Method</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($bills as $b): ?>
                <tr>
                    <td><strong><?= sanitize($b['invoice_number']) ?></strong></td>
                    <td><?= formatDate($b['created_at']) ?></td>
                    <td><?= formatCurrency($b['subtotal']) ?></td>
                    <td>Rs. <?= $b['discount'] ?></td>
                    <td class="font-bold text-success"><?= formatCurrency($b['net_amount']) ?></td>
                    <td><?= ucfirst($b['payment_method'] ?: 'Cash') ?></td>
                    <td><?= statusBadge($b['payment_status'], PAYMENT_STATUSES) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
