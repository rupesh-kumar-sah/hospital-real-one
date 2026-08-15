<?php
/**
 * Hospital Management System — Doctor: OPD Queue
 */

require_once __DIR__ . '/../includes/auth_middleware.php';
requireRole(['doctor', 'admin']);

$pageTitle = 'Patient Queue';
$breadcrumbs = [['label' => 'Dashboard', 'url' => '/doctor/dashboard.php'], ['label' => 'Patient Queue']];

$db = getDB();
$doctor = getDoctorByUserId(getUserId());
$doctorId = $doctor['id'] ?? 0;

$todayDate = date('Y-m-d');
$queue = $db->query("
    SELECT a.*, p.uhid, u_p.full_name as patient_name, p.date_of_birth, p.gender, p.blood_group, p.allergies
    FROM appointments a
    JOIN patients p ON a.patient_id = p.id
    JOIN users u_p ON p.user_id = u_p.id
    WHERE a.doctor_id = {$doctorId} AND (a.appointment_date = '{$todayDate}' OR a.appointment_date = DATE('now'))
    ORDER BY a.token_number ASC
")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Today's OPD Queue</h1>
        <p class="page-subtitle">List of scheduled and checked-in patients waiting for consultation</p>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Token</th>
                    <th>Patient Name</th>
                    <th>UHID</th>
                    <th>Age / Gender</th>
                    <th>Blood Group</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($queue)): ?>
                <tr><td colspan="7"><div class="empty-state"><p>No patients in queue today</p></div></td></tr>
                <?php else: ?>
                <?php foreach ($queue as $q): ?>
                <tr>
                    <td><span class="badge badge-primary" style="font-size: 1rem;">#<?= $q['token_number'] ?></span></td>
                    <td><strong><?= sanitize($q['patient_name']) ?></strong></td>
                    <td><code><?= sanitize($q['uhid']) ?></code></td>
                    <td><?= calculateAge($q['date_of_birth']) ?> / <?= ucfirst($q['gender'] ?: 'N/A') ?></td>
                    <td><?= $q['blood_group'] ?: '-' ?></td>
                    <td><?= statusBadge($q['status'], APPOINTMENT_STATUSES) ?></td>
                    <td>
                        <a href="/doctor/consultation.php?appointment_id=<?= $q['id'] ?>" class="btn btn-sm btn-success">
                            <i class="fas fa-stethoscope"></i> Start Consultation
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
