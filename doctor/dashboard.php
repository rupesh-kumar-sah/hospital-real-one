<?php
/**
 * Hospital Management System — Doctor Dashboard
 */

require_once __DIR__ . '/../includes/auth_middleware.php';
requireRole('doctor');

$pageTitle = 'Doctor Dashboard';
$breadcrumbs = [['label' => 'Dashboard']];

$db = getDB();
$doctor = getDoctorByUserId(getUserId());
$doctorId = $doctor['id'] ?? 0;

$todayDate = date('Y-m-d');

// Today's queue
$todayQueue = $db->prepare("
    SELECT a.*, p.uhid, u_p.full_name as patient_name, p.date_of_birth, p.gender, p.blood_group, p.allergies
    FROM appointments a
    JOIN patients p ON a.patient_id = p.id
    JOIN users u_p ON p.user_id = u_p.id
    WHERE a.doctor_id = ? AND (a.appointment_date = ? OR a.appointment_date = DATE('now')) AND a.status IN ('scheduled','checked_in','in_progress')
    ORDER BY a.token_number ASC
");
$todayQueue->execute([$doctorId, $todayDate]);
$queue = $todayQueue->fetchAll();

// Today's completed
$completedToday = $db->prepare("SELECT COUNT(*) as c FROM appointments WHERE doctor_id = ? AND (appointment_date = ? OR appointment_date = DATE('now')) AND status = 'completed'");
$completedToday->execute([$doctorId, $todayDate]);
$completed = $completedToday->fetch()['c'];

// Total patients seen (all time)
$totalPatients = $db->prepare("SELECT COUNT(DISTINCT patient_id) as c FROM appointments WHERE doctor_id = ? AND status = 'completed'");
$totalPatients->execute([$doctorId]);
$totalPts = $totalPatients->fetch()['c'];

// Active admissions under this doctor
$activeAdmissions = $db->prepare("SELECT COUNT(*) as c FROM admissions WHERE doctor_id = ? AND status = 'admitted'");
$activeAdmissions->execute([$doctorId]);
$admissions = $activeAdmissions->fetch()['c'];

// Pending lab results
$pendingLabs = $db->prepare("SELECT COUNT(*) as c FROM lab_orders WHERE doctor_id = ? AND status IN ('ordered','sample_collected','processing')");
$pendingLabs->execute([$doctorId]);
$pendingLabCount = $pendingLabs->fetch()['c'];

// Recent completed lab results
$recentResults = $db->prepare("
    SELECT lo.*, lc.test_name, lr.result_value, lr.interpretation, u_p.full_name as patient_name
    FROM lab_orders lo
    JOIN lab_test_catalog lc ON lo.test_id = lc.id
    LEFT JOIN lab_results lr ON lo.id = lr.lab_order_id
    JOIN patients p ON lo.patient_id = p.id
    JOIN users u_p ON p.user_id = u_p.id
    WHERE lo.doctor_id = ? AND lo.status = 'completed'
    ORDER BY lr.uploaded_at DESC
    LIMIT 5
");
$recentResults->execute([$doctorId]);
$labResults = $recentResults->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<!-- Stats -->
<div class="stats-grid">
    <div class="stat-card" style="--stat-color: #3b82f6; --stat-color-light: #dbeafe;">
        <div class="stat-info">
            <h3>Today's Queue</h3>
            <div class="stat-number"><?= count($queue) ?></div>
            <div class="stat-change positive"><i class="fas fa-check"></i> <?= $completed ?> completed</div>
        </div>
        <div class="stat-icon"><i class="fas fa-list-ol"></i></div>
    </div>
    
    <div class="stat-card" style="--stat-color: #10b981; --stat-color-light: #d1fae5;">
        <div class="stat-info">
            <h3>Total Patients Seen</h3>
            <div class="stat-number"><?= $totalPts ?></div>
        </div>
        <div class="stat-icon"><i class="fas fa-users"></i></div>
    </div>
    
    <div class="stat-card" style="--stat-color: #f59e0b; --stat-color-light: #fef3c7;">
        <div class="stat-info">
            <h3>Active Admissions</h3>
            <div class="stat-number"><?= $admissions ?></div>
        </div>
        <div class="stat-icon"><i class="fas fa-procedures"></i></div>
    </div>
    
    <div class="stat-card" style="--stat-color: #8b5cf6; --stat-color-light: #ede9fe;">
        <div class="stat-info">
            <h3>Pending Lab Results</h3>
            <div class="stat-number"><?= $pendingLabCount ?></div>
        </div>
        <div class="stat-icon"><i class="fas fa-flask"></i></div>
    </div>
</div>

<!-- Quick Actions -->
<div class="card" style="margin-bottom: 24px;">
    <div class="card-header">
        <h3><i class="fas fa-bolt" style="color: var(--warning);"></i> Quick Actions</h3>
    </div>
    <div class="card-body">
        <div class="quick-actions">
            <a href="/doctor/patient_queue.php" class="quick-action-btn">
                <i class="fas fa-list-ol"></i>
                <span>Patient Queue</span>
            </a>
            <a href="/doctor/consultation.php" class="quick-action-btn">
                <i class="fas fa-stethoscope"></i>
                <span>New Consultation</span>
            </a>
            <a href="/doctor/order_lab_test.php" class="quick-action-btn">
                <i class="fas fa-flask"></i>
                <span>Order Lab Test</span>
            </a>
            <a href="/doctor/admit_patient.php" class="quick-action-btn">
                <i class="fas fa-procedures"></i>
                <span>Admit Patient</span>
            </a>
            <a href="/doctor/prescriptions.php" class="quick-action-btn">
                <i class="fas fa-prescription"></i>
                <span>Prescriptions</span>
            </a>
        </div>
    </div>
</div>

<!-- Today's Patient Queue -->
<div class="card" style="margin-bottom: 24px;">
    <div class="card-header">
        <h3><i class="fas fa-users" style="color: var(--primary);"></i> Today's Patient Queue</h3>
        <a href="/doctor/patient_queue.php" class="btn btn-sm btn-primary">View Full Queue</a>
    </div>
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Token</th>
                    <th>Patient</th>
                    <th>UHID</th>
                    <th>Age/Gender</th>
                    <th>Blood Group</th>
                    <th>Time</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($queue)): ?>
                <tr>
                    <td colspan="8">
                        <div class="empty-state">
                            <i class="fas fa-check-circle" style="color: var(--success);"></i>
                            <h3>Queue Empty!</h3>
                            <p>No pending patients. All consultations for today are completed.</p>
                        </div>
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($queue as $patient): ?>
                <tr>
                    <td>
                        <span class="badge badge-primary" style="font-size: 0.9rem; min-width: 32px; justify-content: center;">
                            #<?= $patient['token_number'] ?>
                        </span>
                    </td>
                    <td>
                        <div class="info-card">
                            <div class="avatar avatar-sm" style="background: var(--primary);"><?= strtoupper(substr($patient['patient_name'], 0, 1)) ?></div>
                            <div class="info-details">
                                <h4><?= sanitize($patient['patient_name']) ?></h4>
                                <?php if ($patient['allergies']): ?>
                                <p class="text-danger"><i class="fas fa-exclamation-triangle"></i> <?= sanitize($patient['allergies']) ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </td>
                    <td><code><?= sanitize($patient['uhid']) ?></code></td>
                    <td><?= calculateAge($patient['date_of_birth']) ?> / <?= ucfirst($patient['gender'] ?? 'N/A') ?></td>
                    <td><?= $patient['blood_group'] ?: 'N/A' ?></td>
                    <td><?= formatTime($patient['appointment_time']) ?></td>
                    <td><?= statusBadge($patient['status'], APPOINTMENT_STATUSES) ?></td>
                    <td>
                        <a href="/doctor/consultation.php?appointment_id=<?= $patient['id'] ?>" class="btn btn-sm btn-success">
                            <i class="fas fa-stethoscope"></i> Consult
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Recent Lab Results -->
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-flask" style="color: var(--accent);"></i> Recent Lab Results</h3>
    </div>
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Patient</th>
                    <th>Test</th>
                    <th>Result</th>
                    <th>Interpretation</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($labResults)): ?>
                <tr><td colspan="4"><div class="empty-state"><p>No lab results yet</p></div></td></tr>
                <?php else: ?>
                <?php foreach ($labResults as $result): ?>
                <tr>
                    <td><?= sanitize($result['patient_name']) ?></td>
                    <td><?= sanitize($result['test_name']) ?></td>
                    <td><?= sanitize($result['result_value'] ?? 'Pending') ?></td>
                    <td>
                        <?php if ($result['interpretation']): ?>
                        <span class="badge <?= $result['interpretation'] === 'normal' ? 'badge-success' : ($result['interpretation'] === 'critical' ? 'badge-danger' : 'badge-warning') ?>">
                            <?= ucfirst($result['interpretation']) ?>
                        </span>
                        <?php else: ?>
                        <span class="badge">Pending</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
