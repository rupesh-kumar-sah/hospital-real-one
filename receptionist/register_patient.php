<?php
/**
 * Hospital Management System — Receptionist: Register Patient
 */

require_once __DIR__ . '/../includes/auth_middleware.php';
requireRole(['receptionist', 'admin']);

$pageTitle = 'Register Patient';
$breadcrumbs = [['label' => 'Dashboard', 'url' => '/receptionist/dashboard.php'], ['label' => 'Register Patient']];

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $gender = $_POST['gender'];
    $dob = $_POST['date_of_birth'];
    $bloodGroup = $_POST['blood_group'];
    $address = trim($_POST['address']);
    $emergencyName = trim($_POST['emergency_contact_name']);
    $emergencyPhone = trim($_POST['emergency_contact_phone']);

    if ($fullName) {
        $username = 'pat_' . time();
        $dummyPassword = password_hash('password123', PASSWORD_DEFAULT);
        
        $db->beginTransaction();
        $stmtUser = $db->prepare("INSERT INTO users (username, email, password_hash, full_name, phone, role, status) VALUES (?, ?, ?, ?, ?, 'patient', 'active')");
        $stmtUser->execute([$username, $email ?: $username.'@hospital.local', $dummyPassword, $fullName, $phone]);
        $userId = $db->lastInsertId();

        $uhid = generateUHID();
        $stmtPat = $db->prepare("INSERT INTO patients (user_id, uhid, date_of_birth, gender, blood_group, address, emergency_contact_name, emergency_contact_phone) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmtPat->execute([$userId, $uhid, $dob ?: null, $gender, $bloodGroup, $address, $emergencyName, $emergencyPhone]);
        $db->commit();

        logAudit('create', 'patients', $userId, "Registered patient {$fullName} ({$uhid})");
        setFlash('success', "Patient {$fullName} registered successfully! Assigned UHID: {$uhid}");
        header('Location: /receptionist/appointments.php?patient_id=' . $userId);
        exit;
    }
}

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Register New Patient</h1>
        <p class="page-subtitle">Create a new patient record and assign a unique hospital ID (UHID)</p>
    </div>
</div>

<div class="card" style="max-width: 800px;">
    <div class="card-body">
        <form method="POST">
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Full Name <span class="required">*</span></label>
                    <input type="text" name="full_name" class="form-control" required placeholder="John Doe">
                </div>
                <div class="form-group">
                    <label class="form-label">Phone Number</label>
                    <input type="tel" name="phone" class="form-control" placeholder="98XXXXXXXX">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Date of Birth</label>
                    <input type="date" name="date_of_birth" class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-label">Gender</label>
                    <select name="gender" class="form-control">
                        <option value="male">Male</option>
                        <option value="female">Female</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Blood Group</label>
                    <select name="blood_group" class="form-control">
                        <option value="">Unknown</option>
                        <option value="A+">A+</option><option value="A-">A-</option>
                        <option value="B+">B+</option><option value="B-">B-</option>
                        <option value="O+">O+</option><option value="O-">O-</option>
                        <option value="AB+">AB+</option><option value="AB-">AB-</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Address</label>
                <input type="text" name="address" class="form-control" placeholder="City, Location">
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Emergency Contact Name</label>
                    <input type="text" name="emergency_contact_name" class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-label">Emergency Contact Phone</label>
                    <input type="tel" name="emergency_contact_phone" class="form-control">
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> Save & Proceed to Appointment</button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
