<?php
/**
 * Hospital Management System — Patient Prescriptions View
 */

require_once __DIR__ . '/../includes/auth_middleware.php';
requireRole('patient');

$pageTitle = 'My Prescriptions';
$breadcrumbs = [['label' => 'Dashboard', 'url' => '/patient/dashboard.php'], ['label' => 'Prescriptions']];

$db = getDB();
$patient = getPatientByUserId(getUserId());
$patientId = $patient['id'] ?? 0;

$rxs = $db->query("
    SELECT pr.*, u_d.full_name as doctor_name
    FROM prescriptions pr
    JOIN doctors d ON pr.doctor_id = d.id
    JOIN users u_d ON d.user_id = u_d.id
    WHERE pr.patient_id = {$patientId}
    ORDER BY pr.created_at DESC
")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>My Digital Prescriptions (e-Rx)</h1>
        <p class="page-subtitle">View active electronic prescriptions issued by your doctors</p>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Rx ID</th>
                    <th>Prescribed By Doctor</th>
                    <th>Prescribed Medicines</th>
                    <th>Status</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rxs as $rx): ?>
                <?php $items = $db->query("SELECT * FROM prescription_items WHERE prescription_id = {$rx['id']}")->fetchAll(); ?>
                <tr>
                    <td><strong>#Rx-<?= $rx['id'] ?></strong></td>
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
