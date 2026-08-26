<?php
/**
 * Hospital Management System — Receptionist: Appointments Management
 */

require_once __DIR__ . '/../includes/auth_middleware.php';
requireRole(['receptionist', 'admin', 'patient']);

$pageTitle = 'Manage Appointments';
$breadcrumbs = [['label' => 'Dashboard', 'url' => '/receptionist/dashboard.php'], ['label' => 'Appointments']];

$db = getDB();

// Handle New Appointment
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $patientId = (int)$_POST['patient_id'];
    $doctorId = (int)$_POST['doctor_id'];
    $date = $_POST['appointment_date'];
    $time = $_POST['appointment_time'];
    $reason = trim($_POST['reason']);

    if ($patientId && $doctorId && $date) {
        $token = generateToken($doctorId, $date);
        
        // Get doctor dept & user_id
        $doc = $db->query("SELECT user_id, department_id FROM doctors WHERE id = {$doctorId}")->fetch();
        $deptId = $doc['department_id'] ?? null;

        $stmt = $db->prepare("INSERT INTO appointments (patient_id, doctor_id, department_id, appointment_date, appointment_time, token_number, status, reason, created_by) VALUES (?, ?, ?, ?, ?, ?, 'scheduled', ?, ?)");
        $stmt->execute([$patientId, $doctorId, $deptId, $date, $time, $token, $reason, getUserId()]);
        $apptId = $db->lastInsertId();

        // Notify Doctor
        if ($doc && isset($doc['user_id'])) {
            createNotification($doc['user_id'], 'New Appointment Scheduled', "New patient appointment #{$token} booked for {$date} at {$time}", 'appointment');
        }

        // Notify Patient
        $patientUser = $db->query("SELECT user_id FROM patients WHERE id = {$patientId}")->fetch();
        if ($patientUser) {
            createNotification($patientUser['user_id'], 'Appointment Confirmed', "Your OPD appointment with Dr. " . getUserName() . " is confirmed for {$date}. Token: #{$token}", 'appointment');
        }

        logAudit('create', 'appointments', $apptId, "Booked appointment token #{$token} for patient #{$patientId}");

        setFlash('success', "Appointment booked successfully! Token Number: #{$token}");
        header('Location: /receptionist/appointments.php');
        exit;
    }
}

// Handle Accept & Confirm Date
if (isset($_GET['accept'])) {
    $acceptId = (int)$_GET['accept'];
    
    $appt = $db->query("
        SELECT a.*, p.user_id as patient_user_id, u_p.full_name as patient_name, u_d.full_name as doctor_name, d.user_id as doctor_user_id
        FROM appointments a
        JOIN patients p ON a.patient_id = p.id
        JOIN users u_p ON p.user_id = u_p.id
        JOIN doctors d ON a.doctor_id = d.id
        JOIN users u_d ON d.user_id = u_d.id
        WHERE a.id = {$acceptId}
    ")->fetch();

    if ($appt) {
        $stmt = $db->prepare("UPDATE appointments SET status = 'scheduled' WHERE id = ?");
        $stmt->execute([$acceptId]);

        // Notify Patient
        if ($appt['patient_user_id']) {
            createNotification($appt['patient_user_id'], 'Appointment Accepted & Confirmed!', "Your appointment for {$appt['appointment_date']} at {$appt['appointment_time']} with Dr. {$appt['doctor_name']} has been ACCEPTED & CONFIRMED by Reception. Token #{$appt['token_number']}", 'appointment');
        }

        // Notify Doctor
        if ($appt['doctor_user_id']) {
            createNotification($appt['doctor_user_id'], 'New Confirmed Patient Appointment', "Patient {$appt['patient_name']} confirmed for {$appt['appointment_date']} at {$appt['appointment_time']}. Token #{$appt['token_number']}", 'appointment');
        }

        logAudit('update', 'appointments', $acceptId, "Accepted & confirmed appointment #{$acceptId} date");
        setFlash('success', "Appointment date accepted & confirmed! Token #{$appt['token_number']}");
        header('Location: /receptionist/appointments.php');
        exit;
    }
}

// Handle Cancel
if (isset($_GET['cancel'])) {
    $cancelId = (int)$_GET['cancel'];
    $stmt = $db->prepare("UPDATE appointments SET status = 'cancelled' WHERE id = ?");
    $stmt->execute([$cancelId]);
    logAudit('update', 'appointments', $cancelId, "Cancelled appointment #{$cancelId}");
    setFlash('warning', 'Appointment cancelled.');
    header('Location: /receptionist/appointments.php');
    exit;
}

$appointments = $db->query("
    SELECT a.*, p.uhid, u_p.full_name as patient_name, u_d.full_name as doctor_name, dep.name as dept_name
    FROM appointments a
    JOIN patients p ON a.patient_id = p.id
    JOIN users u_p ON p.user_id = u_p.id
    JOIN doctors d ON a.doctor_id = d.id
    JOIN users u_d ON d.user_id = u_d.id
    LEFT JOIN departments dep ON a.department_id = dep.id
    ORDER BY CASE WHEN a.status = 'pending_approval' THEN 1 WHEN a.status = 'scheduled' THEN 2 ELSE 3 END, a.appointment_date DESC, a.appointment_time ASC
")->fetchAll();

$patients = $db->query("SELECT p.id, p.uhid, u.full_name FROM patients p JOIN users u ON p.user_id = u.id ORDER BY u.full_name")->fetchAll();
$doctors = $db->query("SELECT d.id, u.full_name, dep.name as dept_name FROM doctors d JOIN users u ON d.user_id = u.id LEFT JOIN departments dep ON d.department_id = dep.id ORDER BY u.full_name")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Appointments & Queue Management</h1>
        <p class="page-subtitle">Accept online patient applications, confirm dates, check in patients, and issue billing receipts</p>
    </div>
    <button class="btn btn-primary" onclick="openModal('bookApptModal')">
        <i class="fas fa-calendar-plus"></i> Book Appointment
    </button>
</div>

<div class="card">
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Token</th>
                    <th>Patient</th>
                    <th>Doctor</th>
                    <th>Department</th>
                    <th>Date & Time</th>
                    <th>Reason</th>
                    <th>Status</th>
                    <th>Actions & Workflow</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($appointments as $a): ?>
                <tr style="<?= $a['status'] === 'pending_approval' ? 'background: rgba(139, 92, 246, 0.05);' : '' ?>">
                    <td><span class="badge badge-primary">#<?= $a['token_number'] ?></span></td>
                    <td><strong><?= sanitize($a['patient_name']) ?></strong><br><code class="text-xs"><?= sanitize($a['uhid']) ?></code></td>
                    <td>Dr. <?= sanitize($a['doctor_name']) ?></td>
                    <td><?= sanitize($a['dept_name'] ?? '-') ?></td>
                    <td><?= formatDate($a['appointment_date']) ?> <?= formatTime($a['appointment_time']) ?></td>
                    <td><?= sanitize($a['reason'] ?: '-') ?></td>
                    <td><?= statusBadge($a['status'], APPOINTMENT_STATUSES) ?></td>
                    <td>
                        <?php if ($a['status'] === 'pending_approval'): ?>
                        <div class="d-flex gap-4">
                            <a href="/receptionist/appointments.php?accept=<?= $a['id'] ?>" class="btn btn-sm btn-success" style="display: inline-flex; align-items: center; gap: 4px;">
                                <i class="fas fa-check-circle"></i> Accept & Confirm Date
                            </a>
                            <a href="/receptionist/appointments.php?cancel=<?= $a['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Reject appointment application?')"><i class="fas fa-times"></i> Reject</a>
                        </div>
                        <?php elseif ($a['status'] === 'scheduled'): ?>
                        <div class="d-flex gap-4">
                            <a href="/receptionist/check_in.php?id=<?= $a['id'] ?>" class="btn btn-sm btn-primary"><i class="fas fa-clipboard-check"></i> Check In & Bill</a>
                            <a href="/receptionist/appointments.php?cancel=<?= $a['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Cancel appointment?')"><i class="fas fa-times"></i></a>
                        </div>
                        <?php elseif ($a['status'] === 'checked_in'): ?>
                        <span class="badge badge-warning"><i class="fas fa-paper-plane"></i> Sent to Doctor Queue</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal-overlay" id="bookApptModal">
    <div class="modal">
        <div class="modal-header">
            <h3>Book Appointment</h3>
            <button class="modal-close" onclick="closeModal('bookApptModal')">×</button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Select Patient <span class="required">*</span></label>
                    <select name="patient_id" class="form-control" required>
                        <option value="">Select Patient</option>
                        <?php foreach ($patients as $p): ?>
                        <option value="<?= $p['id'] ?>"><?= sanitize($p['full_name']) ?> (<?= $p['uhid'] ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Select Doctor <span class="required">*</span></label>
                    <select name="doctor_id" class="form-control" required>
                        <option value="">Select Doctor</option>
                        <?php foreach ($doctors as $d): ?>
                        <option value="<?= $d['id'] ?>"><?= sanitize($d['full_name']) ?> (<?= $d['dept_name'] ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Date <span class="required">*</span></label>
                        <input type="date" name="appointment_date" class="form-control" required value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Time Slot</label>
                        <input type="time" name="appointment_time" class="form-control" value="10:00">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Reason / Symptoms</label>
                    <textarea name="reason" class="form-control" placeholder="e.g. High fever for 2 days"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('bookApptModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Book Now</button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
