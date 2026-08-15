<?php
/**
 * Hospital Management System — Patient Self-Service Appointments
 */

require_once __DIR__ . '/../includes/auth_middleware.php';
requireRole('patient');

$pageTitle = 'My Appointments';
$breadcrumbs = [['label' => 'Dashboard', 'url' => '/patient/dashboard.php'], ['label' => 'Appointments']];

$db = getDB();
$patient = getPatientByUserId(getUserId());
$patientId = $patient['id'] ?? 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $doctorId = (int)$_POST['doctor_id'];
    $date = $_POST['appointment_date'];
    $time = $_POST['appointment_time'];
    $reason = trim($_POST['reason']);

    if ($doctorId && $date) {
        $token = generateToken($doctorId, $date);
        $doc = $db->query("SELECT department_id FROM doctors WHERE id = {$doctorId}")->fetch();

        $stmt = $db->prepare("INSERT INTO appointments (patient_id, doctor_id, department_id, appointment_date, appointment_time, token_number, status, reason, created_by) VALUES (?, ?, ?, ?, ?, ?, 'scheduled', ?, ?)");
        $stmt->execute([$patientId, $doctorId, $doc['department_id'] ?? null, $date, $time, $token, $reason, getUserId()]);

        setFlash('success', "Appointment booked successfully! OPD Token Number: #{$token}");
        header('Location: /patient/appointments.php');
        exit;
    }
}

$myAppts = $db->query("
    SELECT a.*, u_d.full_name as doctor_name, dep.name as dept_name
    FROM appointments a
    JOIN doctors d ON a.doctor_id = d.id
    JOIN users u_d ON d.user_id = u_d.id
    LEFT JOIN departments dep ON a.department_id = dep.id
    WHERE a.patient_id = {$patientId}
    ORDER BY a.appointment_date DESC
")->fetchAll();

$doctors = $db->query("SELECT d.id, u.full_name, dep.name as dept_name, d.consultation_fee FROM doctors d JOIN users u ON d.user_id = u.id LEFT JOIN departments dep ON d.department_id = dep.id WHERE u.status = 'active'")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>My Appointments</h1>
        <p class="page-subtitle">Book online doctor consultation appointments</p>
    </div>
    <button class="btn btn-primary" onclick="openModal('patientBookModal')">
        <i class="fas fa-calendar-plus"></i> Book New Appointment
    </button>
</div>

<div class="card">
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Token #</th>
                    <th>Doctor</th>
                    <th>Department</th>
                    <th>Date & Time</th>
                    <th>Reason</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($myAppts as $ma): ?>
                <tr>
                    <td><span class="badge badge-primary">#<?= $ma['token_number'] ?></span></td>
                    <td><strong>Dr. <?= sanitize($ma['doctor_name']) ?></strong></td>
                    <td><?= sanitize($ma['dept_name']) ?></td>
                    <td><?= formatDate($ma['appointment_date']) ?> <?= formatTime($ma['appointment_time']) ?></td>
                    <td><?= sanitize($ma['reason'] ?: '-') ?></td>
                    <td><?= statusBadge($ma['status'], APPOINTMENT_STATUSES) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal-overlay" id="patientBookModal">
    <div class="modal">
        <div class="modal-header">
            <h3>Book Appointment</h3>
            <button class="modal-close" onclick="closeModal('patientBookModal')">×</button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Select Specialist Doctor <span class="required">*</span></label>
                    <select name="doctor_id" class="form-control" required>
                        <option value="">Select Doctor</option>
                        <?php foreach ($doctors as $d): ?>
                        <option value="<?= $d['id'] ?>"><?= sanitize($d['full_name']) ?> (<?= $d['dept_name'] ?> - Rs. <?= $d['consultation_fee'] ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Preferred Date <span class="required">*</span></label>
                        <input type="date" name="appointment_date" class="form-control" required value="<?= date('Y-m-d') ?>" min="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Preferred Time Slot</label>
                        <input type="time" name="appointment_time" class="form-control" value="10:00">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Symptoms / Reason for Visit</label>
                    <textarea name="reason" class="form-control" placeholder="Describe symptoms or reason for visit..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('patientBookModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Confirm Booking</button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
