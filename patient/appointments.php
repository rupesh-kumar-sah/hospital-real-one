<?php
/**
 * Hospital Management System — Patient: Appointments
 */

require_once __DIR__ . '/../includes/auth_middleware.php';
requireRole('patient');

$pageTitle = 'My Appointments';
$breadcrumbs = [['label' => 'Dashboard', 'url' => '/patient/dashboard.php'], ['label' => 'Appointments']];

$db = getDB();
$patient = getPatientByUserId(getUserId());
$patientId = $patient['id'] ?? 0;

$appointments = $db->query("
    SELECT a.*, u_d.full_name as doctor_name, dep.name as dept_name
    FROM appointments a
    JOIN doctors d ON a.doctor_id = d.id
    JOIN users u_d ON d.user_id = u_d.id
    LEFT JOIN departments dep ON a.department_id = dep.id
    WHERE a.patient_id = {$patientId}
    ORDER BY a.appointment_date DESC, a.appointment_time DESC
")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>My Booked Appointments</h1>
        <p class="page-subtitle">Track OPD consultations, token numbers, and appointment status</p>
    </div>
    <a href="/index.php#booking" class="btn btn-primary">
        <i class="fas fa-calendar-plus"></i> Book New Appointment
    </a>
</div>

<div class="card">
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Token</th>
                    <th>Doctor</th>
                    <th>Department</th>
                    <th>Date & Time</th>
                    <th>Reason</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($appointments)): ?>
                <tr><td colspan="7"><div class="empty-state"><p>No appointments booked yet</p></div></td></tr>
                <?php else: ?>
                <?php foreach ($appointments as $a): ?>
                <tr>
                    <td><span class="badge badge-primary">#<?= $a['token_number'] ?></span></td>
                    <td><strong>Dr. <?= sanitize($a['doctor_name']) ?></strong></td>
                    <td><?= sanitize($a['dept_name'] ?? 'General OPD') ?></td>
                    <td><?= formatDate($a['appointment_date']) ?> <?= formatTime($a['appointment_time']) ?></td>
                    <td><?= sanitize($a['reason'] ?: '-') ?></td>
                    <td><?= statusBadge($a['status'], APPOINTMENT_STATUSES) ?></td>
                    <td>
                        <a href="/receptionist/print_invoice.php?patient_id=<?= $patientId ?>&autoprint=1" target="_blank" class="btn btn-sm btn-success">
                            <i class="fas fa-print"></i> Print Receipt
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
