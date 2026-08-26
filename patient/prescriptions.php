<?php
/**
 * Hospital Management System — Patient: Prescriptions
 */

require_once __DIR__ . '/../includes/auth_middleware.php';
requireRole('patient');

$pageTitle = 'My Prescriptions';
$breadcrumbs = [['label' => 'Dashboard', 'url' => '/patient/dashboard.php'], ['label' => 'Prescriptions']];

$db = getDB();
$patient = getPatientByUserId(getUserId());
$patientId = $patient['id'] ?? 0;

$prescriptions = $db->query("
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
        <h1>My Medical Prescriptions</h1>
        <p class="page-subtitle">Digital prescriptions issued by treating doctors</p>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Rx ID</th>
                    <th>Doctor</th>
                    <th>Date Issued</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($prescriptions)): ?>
                <tr><td colspan="5"><div class="empty-state"><p>No prescriptions issued yet</p></div></td></tr>
                <?php else: ?>
                <?php foreach ($prescriptions as $rx): ?>
                <tr>
                    <td><strong>#Rx-<?= $rx['id'] ?></strong></td>
                    <td>Dr. <?= sanitize($rx['doctor_name']) ?></td>
                    <td><?= formatDate($rx['created_at']) ?></td>
                    <td><span class="badge badge-info"><?= ucfirst($rx['status']) ?></span></td>
                    <td>
                        <a href="/receptionist/view_invoice.php?patient_id=<?= $patientId ?>" class="btn btn-sm btn-primary">
                            <i class="fas fa-eye"></i> View Prescription
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
