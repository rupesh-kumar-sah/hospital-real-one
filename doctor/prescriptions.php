<?php
/**
 * Hospital Management System — Doctor: Prescriptions View
 */

require_once __DIR__ . '/../includes/auth_middleware.php';
requireRole(['doctor', 'pharmacist', 'admin', 'patient']);

$pageTitle = 'Prescriptions Log';
$breadcrumbs = [['label' => 'Dashboard', 'url' => '/doctor/dashboard.php'], ['label' => 'Prescriptions']];

$db = getDB();

$prescriptions = $db->query("
    SELECT pr.*, p.uhid, u_p.full_name as patient_name, u_d.full_name as doctor_name
    FROM prescriptions pr
    JOIN patients p ON pr.patient_id = p.id
    JOIN users u_p ON p.user_id = u_p.id
    JOIN doctors d ON pr.doctor_id = d.id
    JOIN users u_d ON d.user_id = u_d.id
    ORDER BY pr.created_at DESC
")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Prescription Management (e-Rx)</h1>
        <p class="page-subtitle">View issued digital prescriptions and dispensing status</p>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Rx ID</th>
                    <th>Patient</th>
                    <th>Doctor</th>
                    <th>Medicines Prescribed</th>
                    <th>Status</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($prescriptions as $rx): ?>
                <?php
                $items = $db->query("SELECT * FROM prescription_items WHERE prescription_id = {$rx['id']}")->fetchAll();
                ?>
                <tr>
                    <td><strong>#Rx-<?= $rx['id'] ?></strong></td>
                    <td><strong><?= sanitize($rx['patient_name']) ?></strong><br><code class="text-xs"><?= sanitize($rx['uhid']) ?></code></td>
                    <td>Dr. <?= sanitize($rx['doctor_name']) ?></td>
                    <td>
                        <ul style="padding-left: 16px; margin: 0; font-size: 0.8125rem;">
                            <?php foreach ($items as $it): ?>
                            <li><strong><?= sanitize($it['drug_name']) ?></strong> (<?= sanitize($it['dosage']) ?>) - <?= sanitize($it['frequency']) ?> for <?= sanitize($it['duration']) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </td>
                    <td><span class="badge <?= $rx['status'] === 'dispensed' ? 'badge-success' : 'badge-warning' ?>"><?= ucfirst($rx['status']) ?></span></td>
                    <td><?= formatDate($rx['created_at']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
