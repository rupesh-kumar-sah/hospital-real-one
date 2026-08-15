<?php
/**
 * Hospital Management System — Receptionist Dashboard
 */

require_once __DIR__ . '/../includes/auth_middleware.php';
requireRole('receptionist');

$pageTitle = 'Receptionist Dashboard';
$breadcrumbs = [['label' => 'Dashboard']];

$db = getDB();
$todayDate = date('Y-m-d');

// Today's appointments
$todayAppts = $db->query("SELECT COUNT(*) as c FROM appointments WHERE appointment_date = '{$todayDate}' OR appointment_date = DATE('now')")->fetch()['c'];
$scheduledAppts = $db->query("SELECT COUNT(*) as c FROM appointments WHERE (appointment_date = '{$todayDate}' OR appointment_date = DATE('now')) AND status = 'scheduled'")->fetch()['c'];
$checkedIn = $db->query("SELECT COUNT(*) as c FROM appointments WHERE (appointment_date = '{$todayDate}' OR appointment_date = DATE('now')) AND status = 'checked_in'")->fetch()['c'];
$completedToday = $db->query("SELECT COUNT(*) as c FROM appointments WHERE (appointment_date = '{$todayDate}' OR appointment_date = DATE('now')) AND status = 'completed'")->fetch()['c'];

// New patients today
$newPatients = $db->query("SELECT COUNT(*) as c FROM patients WHERE DATE(created_at) = '{$todayDate}' OR DATE(created_at) = DATE('now')")->fetch()['c'];

// Total registered patients
$totalPatients = $db->query("SELECT COUNT(*) as c FROM patients")->fetch()['c'];

// Pending bills
$pendingBills = $db->query("SELECT COUNT(*) as c FROM billing WHERE payment_status = 'unpaid'")->fetch()['c'];

// Today's revenue
$todayRevenue = $db->query("SELECT COALESCE(SUM(net_amount), 0) as total FROM billing WHERE (DATE(created_at) = '{$todayDate}' OR DATE(created_at) = DATE('now')) AND payment_status = 'paid'")->fetch()['total'];

// Today's upcoming appointments
$upcomingAppts = $db->query("
    SELECT a.*, p.uhid, u_p.full_name as patient_name, u_p.phone as patient_phone, u_d.full_name as doctor_name, dep.name as dept_name
    FROM appointments a
    JOIN patients p ON a.patient_id = p.id
    JOIN users u_p ON p.user_id = u_p.id
    JOIN doctors d ON a.doctor_id = d.id
    JOIN users u_d ON d.user_id = u_d.id
    LEFT JOIN departments dep ON a.department_id = dep.id
    WHERE (a.appointment_date = '{$todayDate}' OR a.appointment_date = DATE('now')) AND a.status IN ('scheduled','checked_in')
    ORDER BY a.appointment_time ASC
    LIMIT 15
")->fetchAll();

// Available doctors today
$availableDoctors = $db->query("
    SELECT d.id, u.full_name, dep.name as dept_name, d.consultation_fee,
           (SELECT COUNT(*) FROM appointments WHERE doctor_id = d.id AND appointment_date = DATE('now') AND status IN ('scheduled','checked_in','in_progress')) as today_count,
           d.max_patients_per_day
    FROM doctors d
    JOIN users u ON d.user_id = u.id
    LEFT JOIN departments dep ON d.department_id = dep.id
    WHERE u.status = 'active'
    ORDER BY u.full_name
")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<!-- Stats -->
<div class="stats-grid">
    <div class="stat-card" style="--stat-color: #3b82f6; --stat-color-light: #dbeafe;">
        <div class="stat-info">
            <h3>Today's Appointments</h3>
            <div class="stat-number"><?= $todayAppts ?></div>
            <div class="stat-change"><i class="fas fa-clock"></i> <?= $scheduledAppts ?> waiting, <?= $checkedIn ?> checked in</div>
        </div>
        <div class="stat-icon"><i class="fas fa-calendar-check"></i></div>
    </div>
    
    <div class="stat-card" style="--stat-color: #10b981; --stat-color-light: #d1fae5;">
        <div class="stat-info">
            <h3>New Patients Today</h3>
            <div class="stat-number"><?= $newPatients ?></div>
            <div class="stat-change"><i class="fas fa-users"></i> Total: <?= $totalPatients ?></div>
        </div>
        <div class="stat-icon"><i class="fas fa-user-plus"></i></div>
    </div>
    
    <div class="stat-card" style="--stat-color: #f59e0b; --stat-color-light: #fef3c7;">
        <div class="stat-info">
            <h3>Pending Bills</h3>
            <div class="stat-number"><?= $pendingBills ?></div>
        </div>
        <div class="stat-icon"><i class="fas fa-file-invoice-dollar"></i></div>
    </div>
    
    <div class="stat-card" style="--stat-color: #8b5cf6; --stat-color-light: #ede9fe;">
        <div class="stat-info">
            <h3>Today's Revenue</h3>
            <div class="stat-number"><?= formatCurrency($todayRevenue) ?></div>
        </div>
        <div class="stat-icon"><i class="fas fa-indian-rupee-sign"></i></div>
    </div>
</div>

<!-- Quick Actions -->
<div class="card" style="margin-bottom: 24px;">
    <div class="card-header">
        <h3><i class="fas fa-bolt" style="color: var(--warning);"></i> Quick Actions</h3>
    </div>
    <div class="card-body">
        <div class="quick-actions">
            <a href="/receptionist/register_patient.php" class="quick-action-btn">
                <i class="fas fa-user-plus"></i>
                <span>New Patient</span>
            </a>
            <a href="/receptionist/appointments.php" class="quick-action-btn">
                <i class="fas fa-calendar-plus"></i>
                <span>Book Appointment</span>
            </a>
            <a href="/receptionist/check_in.php" class="quick-action-btn">
                <i class="fas fa-clipboard-check"></i>
                <span>Check-In</span>
            </a>
            <a href="/receptionist/search_patient.php" class="quick-action-btn">
                <i class="fas fa-search"></i>
                <span>Search Patient</span>
            </a>
            <a href="/receptionist/billing.php" class="quick-action-btn">
                <i class="fas fa-receipt"></i>
                <span>Generate Bill</span>
            </a>
        </div>
    </div>
</div>

<!-- Today's Upcoming Appointments -->
<div class="card" style="margin-bottom: 24px;">
    <div class="card-header">
        <h3><i class="fas fa-calendar" style="color: var(--info);"></i> Today's Appointments</h3>
        <a href="/receptionist/appointments.php" class="btn btn-sm btn-primary">Manage All</a>
    </div>
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Token</th>
                    <th>Patient</th>
                    <th>UHID</th>
                    <th>Phone</th>
                    <th>Doctor</th>
                    <th>Dept</th>
                    <th>Time</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($upcomingAppts)): ?>
                <tr><td colspan="9"><div class="empty-state"><i class="fas fa-calendar-xmark"></i><p>No upcoming appointments today</p></div></td></tr>
                <?php else: ?>
                <?php foreach ($upcomingAppts as $appt): ?>
                <tr>
                    <td><span class="badge badge-primary">#<?= $appt['token_number'] ?></span></td>
                    <td><?= sanitize($appt['patient_name']) ?></td>
                    <td><code><?= sanitize($appt['uhid']) ?></code></td>
                    <td><?= sanitize($appt['patient_phone'] ?? 'N/A') ?></td>
                    <td><?= sanitize($appt['doctor_name']) ?></td>
                    <td><?= sanitize($appt['dept_name'] ?? 'N/A') ?></td>
                    <td><?= formatTime($appt['appointment_time']) ?></td>
                    <td><?= statusBadge($appt['status'], APPOINTMENT_STATUSES) ?></td>
                    <td>
                        <div class="btn-group">
                            <?php if ($appt['status'] === 'scheduled'): ?>
                            <a href="/receptionist/check_in.php?id=<?= $appt['id'] ?>" class="btn btn-sm btn-success"><i class="fas fa-check"></i></a>
                            <?php endif; ?>
                            <a href="/receptionist/appointments.php?cancel=<?= $appt['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirmDelete('Cancel this appointment?')"><i class="fas fa-times"></i></a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Available Doctors -->
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-user-doctor" style="color: var(--success);"></i> Doctor Availability</h3>
    </div>
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Doctor</th>
                    <th>Department</th>
                    <th>Fee</th>
                    <th>Today's Patients</th>
                    <th>Availability</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($availableDoctors as $doc): ?>
                <?php $availableSlots = $doc['max_patients_per_day'] - $doc['today_count']; ?>
                <tr>
                    <td>
                        <div class="info-card">
                            <div class="avatar avatar-sm" style="background: var(--success);"><?= strtoupper(substr($doc['full_name'], 0, 1)) ?></div>
                            <div class="info-details"><h4><?= sanitize($doc['full_name']) ?></h4></div>
                        </div>
                    </td>
                    <td><?= sanitize($doc['dept_name'] ?? 'N/A') ?></td>
                    <td><?= formatCurrency($doc['consultation_fee']) ?></td>
                    <td><?= $doc['today_count'] ?> / <?= $doc['max_patients_per_day'] ?></td>
                    <td>
                        <?php if ($availableSlots > 0): ?>
                        <span class="badge badge-success"><?= $availableSlots ?> slots left</span>
                        <?php else: ?>
                        <span class="badge badge-danger">Full</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
