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
    $stmt = $db->prepare("UPDATE appointments SET status = 'checked_in' WHERE id = ?");
    $stmt->execute([$apptId]);
    setFlash('success', 'Patient checked in successfully! Added to doctor queue.');
    header('Location: /receptionist/check_in.php');
    exit;
}

$todayAppts = $db->query("
    SELECT a.*, p.uhid, u_p.full_name as patient_name, u_d.full_name as doctor_name, dep.name as dept_name
    FROM appointments a
    JOIN patients p ON a.patient_id = p.id
    JOIN users u_p ON p.user_id = u_p.id
    JOIN doctors d ON a.doctor_id = d.id
    JOIN users u_d ON d.user_id = u_d.id
    LEFT JOIN departments dep ON a.department_id = dep.id
    WHERE a.appointment_date = DATE('now')
    ORDER BY a.status DESC, a.token_number ASC
")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Patient Check-In & OPD Token Desk</h1>
        <p class="page-subtitle">Check in arriving patients and generate OPD token numbers for consultation</p>
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
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($todayAppts as $a): ?>
                <tr>
                    <td><span class="badge badge-primary" style="font-size: 1rem;">#<?= $a['token_number'] ?></span></td>
                    <td><strong><?= sanitize($a['patient_name']) ?></strong></td>
                    <td><code><?= sanitize($a['uhid']) ?></code></td>
                    <td><?= sanitize($a['doctor_name']) ?> (<?= sanitize($a['dept_name']) ?>)</td>
                    <td><?= statusBadge($a['status'], APPOINTMENT_STATUSES) ?></td>
                    <td>
                        <?php if ($a['status'] === 'scheduled'): ?>
                        <a href="/receptionist/check_in.php?id=<?= $a['id'] ?>" class="btn btn-sm btn-success">
                            <i class="fas fa-clipboard-check"></i> Check In Patient
                        </a>
                        <?php else: ?>
                        <span class="text-sm text-muted">Checked In</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
