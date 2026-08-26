<?php
/**
 * Hospital Management System — Patient Portal Dashboard
 */

require_once __DIR__ . '/../includes/auth_middleware.php';
requireRole('patient');

$pageTitle = 'Patient Dashboard';
$breadcrumbs = [['label' => 'Dashboard']];

$db = getDB();
$patient = getPatientByUserId(getUserId());
$patientId = $patient['id'] ?? 0;

// Upcoming appointments
$upcomingAppts = $db->query("
    SELECT a.*, u_d.full_name as doctor_name, dep.name as dept_name
    FROM appointments a
    JOIN doctors d ON a.doctor_id = d.id
    JOIN users u_d ON d.user_id = u_d.id
    LEFT JOIN departments dep ON a.department_id = dep.id
    WHERE a.patient_id = {$patientId} AND (a.appointment_date >= DATE('now') OR a.appointment_date = DATE('now')) AND a.status IN ('scheduled','checked_in','pending_approval')
    ORDER BY a.appointment_date ASC, a.appointment_time ASC
")->fetchAll();

// Total visits
$totalVisits = $db->query("SELECT COUNT(*) as c FROM appointments WHERE patient_id = {$patientId} AND status = 'completed'")->fetch()['c'];

// Active prescriptions count
$rxCount = $db->query("SELECT COUNT(*) as c FROM prescriptions WHERE patient_id = {$patientId}")->fetch()['c'];

// Lab reports count
$labCount = $db->query("SELECT COUNT(*) as c FROM lab_orders WHERE patient_id = {$patientId}")->fetch()['c'];

// Total pending bill
$unpaidBills = $db->query("SELECT COALESCE(SUM(net_amount), 0) as total FROM billing WHERE patient_id = {$patientId} AND payment_status = 'unpaid'")->fetch()['total'];

include __DIR__ . '/../includes/header.php';
?>

<div class="alert alert-info mb-24" style="background: linear-gradient(135deg, var(--primary-50), #e0f2fe);">
    <div class="d-flex align-center gap-16" style="justify-content: space-between;">
        <div class="d-flex align-center gap-16">
            <div class="avatar avatar-lg" style="background: var(--primary);">
                <?= strtoupper(substr(getUserName(), 0, 1)) ?>
            </div>
            <div>
                <h2 class="mb-4">Welcome, <?= sanitize(getUserName()) ?>!</h2>
                <p class="text-sm text-muted mb-0">UHID: <strong><?= sanitize($patient['uhid'] ?? 'N/A') ?></strong> | Blood Group: <strong><?= $patient['blood_group'] ?: 'Not recorded' ?></strong></p>
            </div>
        </div>
        <div>
            <a href="/index.php" class="btn btn-primary" target="_blank">
                <i class="fas fa-globe"></i> Open Public Patient Website
            </a>
        </div>
    </div>
</div>

<div class="stats-grid mb-24">
    <div class="stat-card" style="--stat-color: #3b82f6;">
        <div class="stat-info">
            <h3>Upcoming Appointments</h3>
            <div class="stat-number"><?= count($upcomingAppts) ?></div>
        </div>
        <div class="stat-icon"><i class="fas fa-calendar-check"></i></div>
    </div>

    <div class="stat-card" style="--stat-color: #10b981;">
        <div class="stat-info">
            <h3>Completed Visits</h3>
            <div class="stat-number"><?= $totalVisits ?></div>
        </div>
        <div class="stat-icon"><i class="fas fa-file-medical"></i></div>
    </div>

    <div class="stat-card" style="--stat-color: #f59e0b;">
        <div class="stat-info">
            <h3>My Prescriptions</h3>
            <div class="stat-number"><?= $rxCount ?></div>
        </div>
        <div class="stat-icon"><i class="fas fa-prescription"></i></div>
    </div>

    <div class="stat-card" style="--stat-color: #06b6d4;">
        <div class="stat-info">
            <h3>Lab Test Reports</h3>
            <div class="stat-number"><?= $labCount ?></div>
        </div>
        <div class="stat-icon"><i class="fas fa-flask"></i></div>
    </div>
</div>

<div class="card mb-24">
    <div class="card-header">
        <h3><i class="fas fa-bolt text-warning"></i> Patient Portal Quick Actions</h3>
    </div>
    <div class="card-body">
        <div class="quick-actions">
            <a href="/patient/appointments.php" class="quick-action-btn">
                <i class="fas fa-calendar-plus"></i>
                <span>Book Appointment</span>
            </a>
            <a href="/patient/medical_records.php" class="quick-action-btn">
                <i class="fas fa-notes-medical"></i>
                <span>Medical History</span>
            </a>
            <a href="/patient/prescriptions.php" class="quick-action-btn">
                <i class="fas fa-pills"></i>
                <span>My Prescriptions</span>
            </a>
            <a href="/patient/lab_reports.php" class="quick-action-btn">
                <i class="fas fa-flask"></i>
                <span>Lab Reports</span>
            </a>
            <a href="/patient/bills.php" class="quick-action-btn">
                <i class="fas fa-receipt"></i>
                <span>My Bills</span>
            </a>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-calendar-alt text-primary"></i> My Booked OPD Appointments</h3>
        <a href="/patient/appointments.php" class="btn btn-sm btn-primary">Book New</a>
    </div>
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Doctor</th>
                    <th>Department</th>
                    <th>Date & Time</th>
                    <th>Token #</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($upcomingAppts)): ?>
                <tr><td colspan="6"><div class="empty-state"><p>No upcoming appointments scheduled</p></div></td></tr>
                <?php else: ?>
                <?php foreach ($upcomingAppts as $ua): ?>
                <tr>
                    <td><strong>Dr. <?= sanitize($ua['doctor_name']) ?></strong></td>
                    <td><?= sanitize($ua['dept_name'] ?? 'General OPD') ?></td>
                    <td><?= formatDate($ua['appointment_date']) ?> <?= formatTime($ua['appointment_time']) ?></td>
                    <td><span class="badge badge-primary">#<?= $ua['token_number'] ?></span></td>
                    <td><?= statusBadge($ua['status'], APPOINTMENT_STATUSES) ?></td>
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
