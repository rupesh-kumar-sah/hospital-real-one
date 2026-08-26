<?php
/**
 * Hospital Management System — Patient: Medical Records
 */

require_once __DIR__ . '/../includes/auth_middleware.php';
requireRole('patient');

$pageTitle = 'Medical History & Records';
$breadcrumbs = [['label' => 'Dashboard', 'url' => '/patient/dashboard.php'], ['label' => 'Medical Records']];

$db = getDB();
$patient = getPatientByUserId(getUserId());
$patientId = $patient['id'] ?? 0;

$records = $db->query("
    SELECT a.*, u_d.full_name as doctor_name, dep.name as dept_name
    FROM appointments a
    JOIN doctors d ON a.doctor_id = d.id
    JOIN users u_d ON d.user_id = u_d.id
    LEFT JOIN departments dep ON a.department_id = dep.id
    WHERE a.patient_id = {$patientId} AND a.status = 'completed'
    ORDER BY a.appointment_date DESC
")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>My Medical History</h1>
        <p class="page-subtitle">Past OPD consultation notes and diagnoses</p>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Visit Date</th>
                    <th>Doctor</th>
                    <th>Department</th>
                    <th>Reason / Diagnosis</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($records)): ?>
                <tr><td colspan="5"><div class="empty-state"><p>No completed consultation records found</p></div></td></tr>
                <?php else: ?>
                <?php foreach ($records as $r): ?>
                <tr>
                    <td><?= formatDate($r['appointment_date']) ?></td>
                    <td>Dr. <?= sanitize($r['doctor_name']) ?></td>
                    <td><?= sanitize($r['dept_name'] ?? 'General OPD') ?></td>
                    <td><?= sanitize($r['reason'] ?: 'Routine Checkup') ?></td>
                    <td><span class="badge badge-success">Completed</span></td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
