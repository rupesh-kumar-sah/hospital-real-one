<?php
/**
 * Hospital Management System — Patient Public Portal & Online Appointment Booking
 * Dedicated exclusively for Patient Role Registration, Login, Profile & Appointment Booking
 */

require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth_middleware.php';

$db = getDB();

$flashMessage = getFlash();
$bookingSuccess = null;
$bookingError = null;

// Handle Patient Logout
if (isset($_GET['action']) && $_GET['action'] === 'patient_logout') {
    destroySession();
    header('Location: /');
    exit;
}

// -------------------------------------------------------------
// 1. HANDLE PATIENT REGISTRATION (Create Patient ID)
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'patient_register') {
    $fullName = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $gender = $_POST['gender'] ?? 'other';
    $bloodGroup = $_POST['blood_group'] ?? 'O+';

    if ($fullName && $phone && $password) {
        // Check if phone or email already registered
        $stmtCheck = $db->prepare("SELECT id FROM users WHERE phone = ? OR (email = ? AND email != '') LIMIT 1");
        $stmtCheck->execute([$phone, $email]);
        if ($stmtCheck->fetch()) {
            $bookingError = "An account with this phone number or email already exists. Please login instead.";
        } else {
            try {
                $db->beginTransaction();
                $uhid = generateUHID();
                $cleanUsername = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $fullName)) . rand(10, 99);
                $cleanEmail = $email ?: $cleanUsername . '@patient.com';
                $hashedPw = password_hash($password, PASSWORD_BCRYPT);

                $stmtUser = $db->prepare("INSERT INTO users (username, role, full_name, email, phone, password_hash, status) VALUES (?, 'patient', ?, ?, ?, ?, 'active')");
                $stmtUser->execute([$cleanUsername, $fullName, $cleanEmail, $phone, $hashedPw]);
                $userId = $db->lastInsertId();

                $stmtPatient = $db->prepare("INSERT INTO patients (user_id, uhid, gender, blood_group) VALUES (?, ?, ?, ?)");
                $stmtPatient->execute([$userId, $uhid, $gender, $bloodGroup]);
                $patientId = $db->lastInsertId();

                $db->commit();

                // Auto Login Patient
                $userRecord = $db->query("SELECT * FROM users WHERE id = {$userId}")->fetch();
                setUserSession($userRecord);
                logAudit('register', 'users', $userId, "New patient registered with UHID {$uhid}");
                setFlash('success', "Welcome {$fullName}! Your Patient UHID is {$uhid}. You can now book appointments easily.");
                header('Location: /#booking');
                exit;
            } catch (Exception $e) {
                $db->rollBack();
                $bookingError = "Registration failed: " . $e->getMessage();
            }
        }
    } else {
        $bookingError = "Please fill in all required registration fields.";
    }
}

// -------------------------------------------------------------
// 2. HANDLE PATIENT LOGIN
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'patient_login') {
    $loginInput = trim($_POST['login_input'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($loginInput && $password) {
        $stmt = $db->prepare("SELECT * FROM users WHERE (email = ? OR phone = ? OR username = ?) AND role = 'patient' AND status = 'active' LIMIT 1");
        $stmt->execute([$loginInput, $loginInput, $loginInput]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            setUserSession($user);
            logAudit('login', 'users', $user['id'], 'Patient logged into patient portal');
            setFlash('success', "Welcome back, {$user['full_name']}!");
            header('Location: /#booking');
            exit;
        } else {
            $bookingError = "Invalid patient credentials or account is not registered as a patient.";
        }
    } else {
        $bookingError = "Please enter your login email/phone and password.";
    }
}

// -------------------------------------------------------------
// 3. HANDLE APPOINTMENT BOOKING (Connected to Backend Database)
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'book_appointment') {
    $deptId = (int)($_POST['department_id'] ?? 0);
    $doctorId = (int)($_POST['doctor_id'] ?? 0);
    $date = $_POST['appointment_date'] ?? '';
    $time = $_POST['appointment_time'] ?? '10:00 AM';
    $reason = trim($_POST['reason'] ?? 'General Consultation');

    $patientId = 0;
    $userId = 0;
    $patientName = '';
    $patientPhone = '';

    // If patient is already logged in
    if (isLoggedIn() && getUserRole() === 'patient') {
        $userId = getUserId();
        $patientRecord = getPatientByUserId($userId);
        if ($patientRecord) {
            $patientId = $patientRecord['id'];
            $patientName = getUserName();
            $patientPhone = $_SESSION['phone'] ?? '';
            $uhid = $patientRecord['uhid'];
        }
    } else {
        // Public booking input
        $patientName = trim($_POST['patient_name'] ?? '');
        $patientPhone = trim($_POST['patient_phone'] ?? '');
        $patientEmail = trim($_POST['patient_email'] ?? '');

        if ($patientName && $patientPhone) {
            $stmtUser = $db->prepare("SELECT u.id as user_id, p.id as patient_id, p.uhid FROM users u LEFT JOIN patients p ON u.id = p.user_id WHERE u.phone = ? LIMIT 1");
            $stmtUser->execute([$patientPhone]);
            $existing = $stmtUser->fetch();

            if ($existing && !empty($existing['patient_id'])) {
                $patientId = $existing['patient_id'];
                $userId = $existing['user_id'];
                $uhid = $existing['uhid'];
            } else {
                // Auto create patient record
                $uhid = generateUHID();
                $cleanUsername = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $patientName)) . rand(10, 99);
                $cleanEmail = $patientEmail ?: $cleanUsername . '@patient.com';
                $hashedPw = password_hash('patient123', PASSWORD_BCRYPT);

                $stmtUserIns = $db->prepare("INSERT INTO users (username, role, full_name, email, phone, password_hash, status) VALUES (?, 'patient', ?, ?, ?, ?, 'active')");
                $stmtUserIns->execute([$cleanUsername, $patientName, $cleanEmail, $patientPhone, $hashedPw]);
                $userId = $db->lastInsertId();

                $stmtPatIns = $db->prepare("INSERT INTO patients (user_id, uhid, gender, blood_group) VALUES (?, ?, 'other', 'O+')");
                $stmtPatIns->execute([$userId, $uhid]);
                $patientId = $db->lastInsertId();
            }
        }
    }

    if ($doctorId && $date && $patientId) {
        try {
            $db->beginTransaction();

            $token = generateToken($doctorId, $date);
            $stmtAppt = $db->prepare("INSERT INTO appointments (patient_id, doctor_id, department_id, appointment_date, appointment_time, token_number, status, reason, created_by) VALUES (?, ?, ?, ?, ?, ?, 'pending_approval', ?, ?)");
            $stmtAppt->execute([$patientId, $doctorId, $deptId, $date, $time, $token, $reason, $userId]);
            $apptId = $db->lastInsertId();

            // Fetch Doctor Name
            $docStmt = $db->prepare("SELECT u.full_name as doctor_name, dep.name as dept_name FROM doctors d JOIN users u ON d.user_id = u.id LEFT JOIN departments dep ON d.department_id = dep.id WHERE d.id = ?");
            $docStmt->execute([$doctorId]);
            $docInfo = $docStmt->fetch();

            // Notify Receptionists for Date Confirmation
            $receptionists = $db->query("SELECT id FROM users WHERE role = 'receptionist' AND status = 'active'")->fetchAll();
            foreach ($receptionists as $rec) {
                createNotification($rec['id'], 'New Appointment Request Awaiting Approval', "New patient {$patientName} ({$uhid}) applied for appointment with Dr. {$docInfo['doctor_name']} on {$date} at {$time}. Please accept & confirm date.", 'appointment');
            }

            // Notify Patient
            createNotification($userId, 'Appointment Submitted', "Your OPD appointment application with Dr. {$docInfo['doctor_name']} for {$date} at {$time} is submitted! Pending Receptionist confirmation.", 'appointment');

            logAudit('create', 'appointments', $apptId, "Patient submitted appointment request #{$token} (Pending Receptionist Approval)");
            $db->commit();

            $bookingSuccess = [
                'token' => $token,
                'patient_name' => $patientName,
                'doctor_name' => $docInfo['doctor_name'] ?? 'Doctor',
                'dept_name' => $docInfo['dept_name'] ?? 'General OPD',
                'date' => $date,
                'time' => $time,
                'uhid' => $uhid ?? 'UHID-REGISTERED'
            ];
        } catch (Exception $e) {
            $db->rollBack();
            $bookingError = "Booking failed: " . $e->getMessage();
        }
    } else {
        $bookingError = "Please select a Doctor, Date, and ensure Patient information is complete.";
    }
}

// -------------------------------------------------------------
// 4. FETCH DATA FOR PATIENT PROFILE & FRONTEND
// -------------------------------------------------------------
$currentUser = getCurrentUser();
$patientProfile = null;
$myAppointments = [];
$myPrescriptions = [];
$myBills = [];

if (isLoggedIn() && getUserRole() === 'patient') {
    $patientProfile = getPatientByUserId(getUserId());
    if ($patientProfile) {
        $pId = $patientProfile['id'];

        // My Appointments
        $myAppointments = $db->query("
            SELECT a.*, u_d.full_name as doctor_name, dep.name as dept_name
            FROM appointments a
            JOIN doctors d ON a.doctor_id = d.id
            JOIN users u_d ON d.user_id = u_d.id
            LEFT JOIN departments dep ON a.department_id = dep.id
            WHERE a.patient_id = {$pId}
            ORDER BY a.appointment_date DESC, a.appointment_time DESC
        ")->fetchAll();

        // My Prescriptions
        $myPrescriptions = $db->query("
            SELECT pr.*, u_d.full_name as doctor_name
            FROM prescriptions pr
            JOIN doctors d ON pr.doctor_id = d.id
            JOIN users u_d ON d.user_id = u_d.id
            WHERE pr.patient_id = {$pId}
            ORDER BY pr.created_at DESC
        ")->fetchAll();

        // My Lab Reports
        $myLabReports = $db->query("
            SELECT lo.*, u_d.full_name as doctor_name
            FROM lab_orders lo
            LEFT JOIN doctors d ON lo.doctor_id = d.id
            LEFT JOIN users u_d ON d.user_id = u_d.id
            WHERE lo.patient_id = {$pId}
            ORDER BY lo.ordered_at DESC
        ")->fetchAll();

        // My Bills
        $myBills = $db->query("
            SELECT * FROM billing WHERE patient_id = {$pId} ORDER BY created_at DESC
        ")->fetchAll();
    }
}

// Fetch active departments & doctors
$departments = $db->query("SELECT * FROM departments WHERE status = 'active' ORDER BY name ASC")->fetchAll();
$doctors = $db->query("
    SELECT d.id as doctor_id, d.specialization, d.consultation_fee, u.full_name as doctor_name, dep.id as dept_id, dep.name as dept_name
    FROM doctors d
    JOIN users u ON d.user_id = u.id
    LEFT JOIN departments dep ON d.department_id = dep.id
    WHERE u.status = 'active'
    ORDER BY u.full_name ASC
")->fetchAll();

$activePM = $db->query("SELECT * FROM payment_methods WHERE status = 'active' AND qr_image != '' LIMIT 1")->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= APP_NAME ?> — Patient Portal & Online Appointment Booking</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/frontend.css">
</head>
<body>

<!-- Top Navigation Bar (Patient Dedicated Portal) -->
<nav class="top-nav">
    <div class="nav-container">
        <a href="/" class="brand-logo">
            <i class="fas fa-plus-square text-accent"></i>
            <span><?= APP_NAME ?></span>
            <span class="badge" style="background: #e0f2fe; color: #0284c7; font-size: 0.7rem; font-weight: 700; margin-left: 6px;">PATIENT PORTAL</span>
        </a>

        <ul class="nav-links">
            <li><a href="/" class="active">Home</a></li>
            <li><a href="#about">About Us</a></li>
            <li><a href="#services">Services</a></li>
            <li><a href="#doctors">Doctors</a></li>
            <li><a href="#departments">Departments</a></li>
            <li><a href="#booking">Book Appointment</a></li>
        </ul>

        <div class="nav-actions">
            <div class="helpline-pill">
                <i class="fas fa-phone-volume"></i>
                <span>+977 1 4000000</span>
            </div>

            <?php if (isLoggedIn() && getUserRole() === 'patient'): ?>
                <!-- Logged-in Patient Profile Dropdown/Button -->
                <button class="btn-outline" onclick="openPatientModal()" style="cursor: pointer; display: inline-flex; align-items: center; gap: 8px; border-color: var(--accent);">
                    <i class="fas fa-user-circle text-accent" style="font-size: 1.1rem;"></i>
                    <span><?= sanitize(getUserName()) ?> (<?= sanitize($patientProfile['uhid'] ?? 'PATIENT') ?>)</span>
                </button>
                <a href="/index.php?action=patient_logout" class="btn-outline" style="color: #ef4444; border-color: #fca5a5;">
                    <i class="fas fa-right-from-bracket"></i> Logout
                </a>
            <?php else: ?>
                <!-- Patient Register / Login Buttons -->
                <button onclick="openModal('patientLoginModal')" class="btn-outline">
                    <i class="fas fa-right-to-bracket"></i> Patient Login
                </button>
                <button onclick="openModal('patientRegisterModal')" class="btn-book" style="background: var(--primary);">
                    <i class="fas fa-user-plus"></i> Create Patient ID
                </button>
            <?php endif; ?>
        </div>
    </div>
</nav>

<!-- Flash Alerts -->
<?php if ($flashMessage): ?>
<div style="max-width: 1240px; margin: 16px auto 0 auto; padding: 0 24px;">
    <div style="background: <?= $flashMessage['type'] === 'success' ? '#f0fdf4' : '#fef2f2' ?>; border: 1px solid <?= $flashMessage['type'] === 'success' ? '#86efac' : '#fca5a5' ?>; color: <?= $flashMessage['type'] === 'success' ? '#166534' : '#991b1b' ?>; padding: 14px 20px; border-radius: 8px; font-weight: 600; display: flex; align-items: center; justify-content: space-between;">
        <span><i class="fas <?= $flashMessage['type'] === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i> <?= sanitize($flashMessage['message']) ?></span>
        <button onclick="this.parentElement.remove()" style="background: none; border: none; font-size: 1.2rem; cursor: pointer; color: inherit;">&times;</button>
    </div>
</div>
<?php endif; ?>

<!-- Hero Banner Section -->
<section class="hero-section">
    <div class="hero-container">
        <div class="hero-content">
            <h1>Your Health, <br><span>Our Priority</span></h1>
            <p>Compassionate care. Advanced medicine. Trusted by thousands of families for 24/7 emergency & specialist healthcare.</p>
            
            <div class="hero-features">
                <div class="hero-feature-item">
                    <i class="fas fa-user-doctor"></i>
                    <span>Senior Doctors</span>
                </div>
                <div class="hero-feature-item">
                    <i class="fas fa-microscope"></i>
                    <span>Modern Tech</span>
                </div>
                <div class="hero-feature-item">
                    <i class="fas fa-truck-medical"></i>
                    <span>24/7 Emergency</span>
                </div>
            </div>

            <div style="display: flex; gap: 16px;">
                <a href="#booking" class="btn-book" style="padding: 14px 28px; font-size: 1.05rem;">
                    <i class="fas fa-calendar-plus"></i> Book Appointment Now
                </a>
                <?php if (!isLoggedIn()): ?>
                <button onclick="openModal('patientRegisterModal')" class="btn-outline" style="padding: 14px 24px; font-size: 1.05rem;">
                    <i class="fas fa-user-plus text-accent"></i> Register Patient ID
                </button>
                <?php endif; ?>
            </div>
        </div>

        <div class="hero-image-wrapper">
            <img src="/assets/images/hospital_hero.jpg" alt="<?= APP_NAME ?> Patient Care">
            
            <div class="emergency-card-float">
                <div style="width: 44px; height: 44px; background: #fee2e2; color: #ef4444; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.25rem;">
                    <i class="fas fa-phone-volume"></i>
                </div>
                <div>
                    <div style="font-size: 0.75rem; font-weight: 700; color: #ef4444; text-transform: uppercase;">24/7 Emergency Helpline</div>
                    <div style="font-size: 1.1rem; font-weight: 800; color: #0f172a;">+977 1 4000000</div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Interactive Quick Appointment Booking Bar -->
<div class="booking-bar-wrapper" id="booking">
    <div class="booking-card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3 style="margin: 0;"><i class="fas fa-calendar-alt text-accent"></i> Find & Book Patient Appointment</h3>
            <?php if (isLoggedIn() && getUserRole() === 'patient'): ?>
                <span class="badge" style="background: #dcfce7; color: #15803d; font-weight: 700; padding: 6px 12px; font-size: 0.85rem;">
                    <i class="fas fa-check-circle"></i> Logged in as Patient: <?= sanitize(getUserName()) ?> (<?= sanitize($patientProfile['uhid'] ?? '') ?>)
                </span>
            <?php endif; ?>
        </div>

        <?php if ($bookingSuccess): ?>
        <!-- Booking Confirmation Success Card -->
        <div style="background: #f0fdf4; border: 2px solid #22c55e; border-radius: 12px; padding: 24px; margin-bottom: 24px;">
            <div style="display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid #bbf7d0; padding-bottom: 16px; margin-bottom: 16px;">
                <div>
                    <span class="badge" style="background: #16a34a; color: #ffffff; font-weight: 800; padding: 6px 12px; font-size: 0.85rem;">OPD APPOINTMENT CONFIRMED</span>
                    <h2 style="margin: 8px 0 0 0; color: #15803d; font-size: 1.6rem;">Token Number: #<?= $bookingSuccess['token'] ?></h2>
                </div>
                <div style="text-align: right;">
                    <code style="font-size: 0.95rem; background: #ffffff; padding: 6px 12px; border-radius: 6px; border: 1px solid #bbf7d0; font-weight: 800; color: #15803d;"><?= $bookingSuccess['uhid'] ?></code>
                </div>
            </div>
            
            <div class="grid-3 gap-16 mb-16" style="font-size: 0.95rem;">
                <div><strong>Patient Name:</strong> <?= sanitize($bookingSuccess['patient_name']) ?></div>
                <div><strong>Doctor:</strong> Dr. <?= sanitize($bookingSuccess['doctor_name']) ?></div>
                <div><strong>Department:</strong> <?= sanitize($bookingSuccess['dept_name']) ?></div>
                <div><strong>Date:</strong> <?= formatDate($bookingSuccess['date']) ?></div>
                <div><strong>Time Slot:</strong> <?= $bookingSuccess['time'] ?></div>
                <div><strong>Queue Status:</strong> Live in Doctor Queue</div>
            </div>

            <?php if ($activePM && !empty($activePM['qr_image'])): ?>
            <div style="background: #ffffff; border: 1px dashed #16a34a; border-radius: 8px; padding: 12px 16px; display: flex; align-items: center; justify-content: space-between;">
                <div style="display: flex; align-items: center; gap: 12px;">
                    <img src="<?= $activePM['qr_image'] ?>" alt="Hospital Payment QR" style="width: 70px; height: 70px; border-radius: 6px; border: 1px solid #e2e8f0;">
                    <div>
                        <div style="font-weight: 700; font-size: 0.85rem; color: #15803d;">Hospital Payment QR Code</div>
                        <div style="font-size: 0.75rem; color: #475569;">Scan with eSewa / Khalti / Mobile Banking to pay</div>
                    </div>
                </div>
                <button onclick="window.print()" class="btn-book" style="padding: 8px 16px; font-size: 0.875rem;"><i class="fas fa-print"></i> Print Confirmation Receipt</button>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ($bookingError): ?>
        <div style="background: #fef2f2; border: 1px solid #fca5a5; color: #991b1b; padding: 14px; border-radius: 8px; margin-bottom: 20px; font-weight: 600;">
            <i class="fas fa-exclamation-circle"></i> <?= $bookingError ?>
        </div>
        <?php endif; ?>

        <form method="POST" action="#booking">
            <input type="hidden" name="action" value="book_appointment">
            <div class="booking-grid">
                <!-- Select Department -->
                <div class="form-group-custom">
                    <label>Select Department</label>
                    <select name="department_id" id="deptSelect" class="form-control-custom" onchange="filterDoctorsByDept(this.value)">
                        <option value="">All Clinical Departments</option>
                        <?php foreach ($departments as $dept): ?>
                        <option value="<?= $dept['id'] ?>"><?= sanitize($dept['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Select Doctor -->
                <div class="form-group-custom">
                    <label>Select Doctor <span style="color:#ef4444;">*</span></label>
                    <select name="doctor_id" id="doctorSelect" class="form-control-custom" required>
                        <option value="">Select Doctor</option>
                        <?php foreach ($doctors as $doc): ?>
                        <option value="<?= $doc['doctor_id'] ?>" data-dept="<?= $doc['dept_id'] ?>">
                            Dr. <?= sanitize($doc['doctor_name']) ?> (<?= sanitize($doc['specialization'] ?: 'General') ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Date -->
                <div class="form-group-custom">
                    <label>Appointment Date <span style="color:#ef4444;">*</span></label>
                    <input type="date" name="appointment_date" class="form-control-custom" required value="<?= date('Y-m-d') ?>" min="<?= date('Y-m-d') ?>">
                </div>

                <!-- Time Slot -->
                <div class="form-group-custom">
                    <label>Preferred Time Slot</label>
                    <select name="appointment_time" class="form-control-custom">
                        <option value="09:00 AM">09:00 AM - 10:00 AM</option>
                        <option value="10:00 AM" selected>10:00 AM - 11:00 AM</option>
                        <option value="11:00 AM">11:00 AM - 12:00 PM</option>
                        <option value="02:00 PM">02:00 PM - 03:00 PM</option>
                        <option value="04:00 PM">04:00 PM - 05:00 PM</option>
                    </select>
                </div>

                <?php if (isLoggedIn() && getUserRole() === 'patient'): ?>
                <!-- Pre-filled Logged-in Patient Info -->
                <div class="form-group-custom">
                    <label>Patient Full Name</label>
                    <input type="text" class="form-control-custom" value="<?= sanitize(getUserName()) ?>" readonly style="background: #f8fafc;">
                </div>

                <div class="form-group-custom">
                    <label>Patient Contact Phone</label>
                    <input type="text" class="form-control-custom" value="<?= sanitize($_SESSION['phone'] ?? 'N/A') ?>" readonly style="background: #f8fafc;">
                </div>

                <div class="form-group-custom">
                    <label>Patient UHID</label>
                    <input type="text" class="form-control-custom" value="<?= sanitize($patientProfile['uhid'] ?? 'UHID-REGISTERED') ?>" readonly style="background: #f8fafc; font-weight: 700; color: var(--accent-dark);">
                </div>
                <?php else: ?>
                <!-- Public Patient Info -->
                <div class="form-group-custom">
                    <label>Full Name <span style="color:#ef4444;">*</span></label>
                    <input type="text" name="patient_name" class="form-control-custom" placeholder="e.g. Gita Shrestha" required>
                </div>

                <div class="form-group-custom">
                    <label>Phone Number <span style="color:#ef4444;">*</span></label>
                    <input type="tel" name="patient_phone" class="form-control-custom" placeholder="e.g. 9841000000" required>
                </div>

                <div class="form-group-custom">
                    <label>Email Address</label>
                    <input type="email" name="patient_email" class="form-control-custom" placeholder="your@email.com">
                </div>
                <?php endif; ?>

                <!-- Submit Button -->
                <div class="form-group-custom" style="justify-content: flex-end;">
                    <button type="submit" class="btn-submit-booking">
                        Book Appointment Now <i class="fas fa-arrow-right"></i>
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Trusted Care Section -->
<section class="section-wrapper" id="about" style="background: #ffffff;">
    <div class="hero-container">
        <div>
            <span class="section-tag">WHY CHOOSE US</span>
            <h2>Trusted Care Backed by Experience</h2>
            <p>At <?= APP_NAME ?>, we combine advanced medical technology with compassionate patient-centric care to ensure the best healthcare outcomes for your family.</p>
            
            <div class="grid-2 gap-24 mt-24">
                <div style="background: var(--light-bg); padding: 20px; border-radius: 12px; text-align: center; border: 1px solid var(--border);">
                    <div style="font-size: 2.2rem; font-weight: 800; color: var(--accent);"><?= count($doctors) ?>+</div>
                    <div style="font-weight: 600; color: var(--dark-muted);">Expert Doctors</div>
                </div>
                <div style="background: var(--light-bg); padding: 20px; border-radius: 12px; text-align: center; border: 1px solid var(--border);">
                    <div style="font-size: 2.2rem; font-weight: 800; color: var(--primary);">15K+</div>
                    <div style="font-weight: 600; color: var(--dark-muted);">Happy Patients</div>
                </div>
            </div>
        </div>

        <div style="border-radius: 20px; overflow: hidden; box-shadow: var(--shadow-lg);">
            <img src="/assets/images/hospital_building.jpg" alt="<?= APP_NAME ?> Building Facade" style="width: 100%; height: 400px; object-fit: cover;">
        </div>
    </div>
</section>

<!-- Departments -->
<section class="section-wrapper" id="departments" style="background: var(--light-bg);">
    <div class="section-header">
        <span class="section-tag">OUR DEPARTMENTS</span>
        <h2>Comprehensive Care Under One Roof</h2>
    </div>

    <div class="nav-container grid-6">
        <div class="dept-card" onclick="document.getElementById('booking').scrollIntoView({behavior: 'smooth'})">
            <div class="dept-icon"><i class="fas fa-heart-pulse"></i></div>
            <h4>Cardiology</h4>
        </div>
        <div class="dept-card" onclick="document.getElementById('booking').scrollIntoView({behavior: 'smooth'})">
            <div class="dept-icon" style="background: #eff6ff; color: #2563eb;"><i class="fas fa-brain"></i></div>
            <h4>Neurology</h4>
        </div>
        <div class="dept-card" onclick="document.getElementById('booking').scrollIntoView({behavior: 'smooth'})">
            <div class="dept-icon" style="background: #fef3c7; color: #d97706;"><i class="fas fa-bone"></i></div>
            <h4>Orthopedics</h4>
        </div>
        <div class="dept-card" onclick="document.getElementById('booking').scrollIntoView({behavior: 'smooth'})">
            <div class="dept-icon" style="background: #fce7f3; color: #db2777;"><i class="fas fa-baby"></i></div>
            <h4>Pediatrics</h4>
        </div>
        <div class="dept-card" onclick="document.getElementById('booking').scrollIntoView({behavior: 'smooth'})">
            <div class="dept-icon" style="background: #f3e8ff; color: #9333ea;"><i class="fas fa-person-breastfeeding"></i></div>
            <h4>Gynaecology</h4>
        </div>
        <div class="dept-card" onclick="document.getElementById('booking').scrollIntoView({behavior: 'smooth'})">
            <div class="dept-icon" style="background: #cffafe; color: #0891b2;"><i class="fas fa-x-ray"></i></div>
            <h4>Radiology</h4>
        </div>
    </div>
</section>

<!-- Doctors Showcase -->
<section class="section-wrapper" id="doctors">
    <div class="section-header">
        <span class="section-tag">MEET OUR DOCTORS</span>
        <h2>Expert Doctors, Compassionate Care</h2>
    </div>

    <div class="nav-container grid-4">
        <?php foreach ($doctors as $doc): ?>
        <div class="doctor-card">
            <div class="doctor-img">
                <i class="fas fa-user-md"></i>
            </div>
            <div class="doctor-info">
                <h4>Dr. <?= sanitize($doc['doctor_name']) ?></h4>
                <div class="doctor-spec"><?= sanitize($doc['specialization'] ?: 'General Medicine') ?></div>
                <button class="btn-book" style="width: 100%; justify-content: center;" onclick="selectDoctorForBooking(<?= $doc['doctor_id'] ?>, <?= $doc['dept_id'] ?: 'null' ?>)">
                    <i class="fas fa-calendar-check"></i> Book Consultation
                </button>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</section>

<!-- Footer -->
<footer>
    <div class="footer-container">
        <div>
            <a href="/" class="brand-logo" style="color: #ffffff; margin-bottom: 16px; display: inline-flex;">
                <i class="fas fa-plus-square text-accent"></i>
                <span><?= APP_NAME ?></span>
            </a>
            <p><?= APP_NAME ?> Patient Portal — Exclusively designed for patient registration, appointment tracking, digital prescriptions, and instant bills.</p>
        </div>
        <div>
            <h4>Hospital Staff Portal</h4>
            <p style="font-size: 0.85rem; color: #94a3b8;">Are you a doctor, administrator, nurse, or receptionist?</p>
            <a href="/auth/login.php" class="btn-outline" style="color: #ffffff; border-color: #475569; display: inline-flex; margin-top: 8px;">
                <i class="fas fa-lock"></i> Hospital Staff Login
            </a>
        </div>
    </div>
    <div style="text-align: center; font-size: 0.875rem;">
        &copy; <?= date('Y') ?> <?= APP_NAME ?> Patient Portal. All Rights Reserved.
    </div>
</footer>

<!-- ========================================================= -->
<!-- MODALS FOR PATIENT AUTHENTICATION & PATIENT PROFILE       -->
<!-- ========================================================= -->

<!-- 1. Patient Register Modal (Create Patient ID) -->
<div class="modal-overlay" id="patientRegisterModal" style="display: none; position: fixed; inset: 0; background: rgba(15,23,42,0.6); z-index: 2000; align-items: center; justify-content: center;">
    <div style="background: #ffffff; width: 100%; max-width: 480px; border-radius: 16px; padding: 28px; position: relative; box-shadow: var(--shadow-lg);">
        <button onclick="closeModal('patientRegisterModal')" style="position: absolute; top: 20px; right: 20px; background: none; border: none; font-size: 1.5rem; cursor: pointer;">&times;</button>
        
        <h2 style="margin: 0 0 6px 0; font-size: 1.4rem; color: var(--dark);"><i class="fas fa-user-plus text-primary"></i> Create Patient Account (Register ID)</h2>
        <p style="color: var(--dark-muted); font-size: 0.875rem; margin-bottom: 20px;">Register to manage appointments, view prescriptions, and pay bills online.</p>

        <form method="POST" action="/">
            <input type="hidden" name="action" value="patient_register">
            
            <div class="form-group-custom mb-12">
                <label>Full Name <span style="color:#ef4444;">*</span></label>
                <input type="text" name="full_name" class="form-control-custom" placeholder="e.g. Gita Shrestha" required>
            </div>

            <div class="grid-2 gap-12 mb-12">
                <div class="form-group-custom">
                    <label>Phone Number <span style="color:#ef4444;">*</span></label>
                    <input type="tel" name="phone" class="form-control-custom" placeholder="9841000000" required>
                </div>
                <div class="form-group-custom">
                    <label>Email Address</label>
                    <input type="email" name="email" class="form-control-custom" placeholder="email@domain.com">
                </div>
            </div>

            <div class="grid-2 gap-12 mb-12">
                <div class="form-group-custom">
                    <label>Gender</label>
                    <select name="gender" class="form-control-custom">
                        <option value="female">Female</option>
                        <option value="male">Male</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div class="form-group-custom">
                    <label>Blood Group</label>
                    <select name="blood_group" class="form-control-custom">
                        <option value="O+">O+</option>
                        <option value="A+">A+</option>
                        <option value="B+">B+</option>
                        <option value="AB+">AB+</option>
                        <option value="O-">O-</option>
                    </select>
                </div>
            </div>

            <div class="form-group-custom mb-20">
                <label>Password <span style="color:#ef4444;">*</span></label>
                <input type="password" name="password" class="form-control-custom" placeholder="Create secret password" required>
            </div>

            <button type="submit" class="btn-book" style="width: 100%; justify-content: center; padding: 12px;">
                <i class="fas fa-check"></i> Register Patient ID & Login
            </button>
        </form>
    </div>
</div>

<!-- 2. Patient Login Modal -->
<div class="modal-overlay" id="patientLoginModal" style="display: none; position: fixed; inset: 0; background: rgba(15,23,42,0.6); z-index: 2000; align-items: center; justify-content: center;">
    <div style="background: #ffffff; width: 100%; max-width: 420px; border-radius: 16px; padding: 28px; position: relative; box-shadow: var(--shadow-lg);">
        <button onclick="closeModal('patientLoginModal')" style="position: absolute; top: 20px; right: 20px; background: none; border: none; font-size: 1.5rem; cursor: pointer;">&times;</button>
        
        <h2 style="margin: 0 0 6px 0; font-size: 1.4rem; color: var(--dark);"><i class="fas fa-right-to-bracket text-accent"></i> Patient Login</h2>
        <p style="color: var(--dark-muted); font-size: 0.875rem; margin-bottom: 20px;">Access your medical profile, appointments, and receipts.</p>

        <form method="POST" action="/">
            <input type="hidden" name="action" value="patient_login">
            
            <div class="form-group-custom mb-16">
                <label>Phone Number or Email <span style="color:#ef4444;">*</span></label>
                <input type="text" name="login_input" class="form-control-custom" placeholder="e.g. gita@gmail.com or 9841000000" required>
            </div>

            <div class="form-group-custom mb-20">
                <label>Password <span style="color:#ef4444;">*</span></label>
                <input type="password" name="password" class="form-control-custom" placeholder="Enter password" required>
            </div>

            <button type="submit" class="btn-book" style="width: 100%; justify-content: center; padding: 12px;">
                <i class="fas fa-sign-in-alt"></i> Login to Patient Portal
            </button>
        </form>
    </div>
</div>

<!-- 3. Logged-In Patient Profile & Bookings Modal -->
<?php if (isLoggedIn() && getUserRole() === 'patient' && $patientProfile): ?>
<div class="modal-overlay" id="patientProfileModal" style="display: none; position: fixed; inset: 0; background: rgba(15,23,42,0.6); z-index: 2000; align-items: center; justify-content: center;">
    <div style="background: #ffffff; width: 100%; max-width: 800px; max-height: 90vh; overflow-y: auto; border-radius: 16px; padding: 28px; position: relative; box-shadow: var(--shadow-lg);">
        <button onclick="closeModal('patientProfileModal')" style="position: absolute; top: 20px; right: 20px; background: none; border: none; font-size: 1.5rem; cursor: pointer;">&times;</button>
        
        <div style="display: flex; align-items: center; gap: 16px; border-bottom: 2px solid var(--border); padding-bottom: 16px; margin-bottom: 20px;">
            <div style="width: 56px; height: 56px; background: var(--accent); color: #fff; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; font-weight: 800;">
                <?= strtoupper(substr(getUserName(), 0, 1)) ?>
            </div>
            <div>
                <h2 style="margin: 0; color: var(--dark); font-size: 1.3rem;"><?= sanitize(getUserName()) ?></h2>
                <div style="font-size: 0.875rem; color: var(--dark-muted);">
                    UHID: <code style="font-weight: 700; color: var(--accent-dark); background: #dcfce7; padding: 2px 6px; border-radius: 4px;"><?= sanitize($patientProfile['uhid']) ?></code>
                    • Phone: <?= sanitize($_SESSION['phone'] ?? 'N/A') ?> • Blood Group: <strong><?= $patientProfile['blood_group'] ?></strong>
                </div>
            </div>
        </div>

        <!-- Patient Medical Portal Tabs -->
        <div style="display: flex; gap: 8px; border-bottom: 2px solid var(--border); margin-bottom: 20px; overflow-x: auto;">
            <button onclick="switchPatientTab('tabAppts')" id="btnTabAppts" style="padding: 10px 16px; border: none; background: none; border-bottom: 3px solid var(--accent); color: var(--accent); font-weight: 700; cursor: pointer;">
                <i class="fas fa-calendar-check"></i> Appointments (<?= count($myAppointments) ?>)
            </button>
            <button onclick="switchPatientTab('tabRx')" id="btnTabRx" style="padding: 10px 16px; border: none; background: none; color: var(--dark-muted); font-weight: 600; cursor: pointer;">
                <i class="fas fa-pills"></i> Prescriptions (<?= count($myPrescriptions) ?>)
            </button>
            <button onclick="switchPatientTab('tabLab')" id="btnTabLab" style="padding: 10px 16px; border: none; background: none; color: var(--dark-muted); font-weight: 600; cursor: pointer;">
                <i class="fas fa-flask"></i> Lab Reports (<?= count($myLabReports) ?>)
            </button>
            <button onclick="switchPatientTab('tabBills')" id="btnTabBills" style="padding: 10px 16px; border: none; background: none; color: var(--dark-muted); font-weight: 600; cursor: pointer;">
                <i class="fas fa-file-invoice-dollar"></i> My Bills & Invoices (<?= count($myBills) ?>)
            </button>
        </div>

        <!-- 1. TAB: APPOINTMENTS -->
        <div id="tabAppts" class="patient-tab-content">
            <h3 style="font-size: 1.05rem; color: var(--dark); margin: 0 0 12px 0;"><i class="fas fa-calendar-check text-accent"></i> My Booked OPD Appointments</h3>
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; margin-bottom: 16px; font-size: 0.875rem;">
                    <thead>
                        <tr style="background: var(--light-bg); border-bottom: 2px solid var(--border); text-align: left;">
                            <th style="padding: 8px;">Token</th>
                            <th style="padding: 8px;">Doctor</th>
                            <th style="padding: 8px;">Department</th>
                            <th style="padding: 8px;">Date & Time</th>
                            <th style="padding: 8px;">Status</th>
                            <th style="padding: 8px;">Receipt</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($myAppointments)): ?>
                        <tr><td colspan="6" style="padding: 16px; text-align: center; color: var(--dark-muted);">No appointments booked yet. Use the booking bar above to schedule.</td></tr>
                        <?php else: ?>
                        <?php foreach ($myAppointments as $appt): ?>
                        <tr style="border-bottom: 1px solid var(--border);">
                            <td style="padding: 10px;"><strong>#<?= $appt['token_number'] ?></strong></td>
                            <td style="padding: 10px;">Dr. <?= sanitize($appt['doctor_name']) ?></td>
                            <td style="padding: 10px;"><?= sanitize($appt['dept_name'] ?? 'General OPD') ?></td>
                            <td style="padding: 10px;"><?= formatDate($appt['appointment_date']) ?> (<?= $appt['appointment_time'] ?>)</td>
                            <td style="padding: 10px;"><?= statusBadge($appt['status'], APPOINTMENT_STATUSES) ?></td>
                            <td style="padding: 10px;">
                                <a href="/patient_print_invoice.php?patient_id=<?= $pId ?>&autoprint=1" target="_blank" class="btn-book" style="padding: 4px 10px; font-size: 0.75rem;">
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

        <!-- 2. TAB: PRESCRIPTIONS -->
        <div id="tabRx" class="patient-tab-content" style="display: none;">
            <h3 style="font-size: 1.05rem; color: var(--dark); margin: 0 0 12px 0;"><i class="fas fa-pills text-primary"></i> Digital Medical Prescriptions</h3>
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; margin-bottom: 16px; font-size: 0.875rem;">
                    <thead>
                        <tr style="background: var(--light-bg); border-bottom: 2px solid var(--border); text-align: left;">
                            <th style="padding: 8px;">Prescription ID</th>
                            <th style="padding: 8px;">Prescribing Doctor</th>
                            <th style="padding: 8px;">Date Issued</th>
                            <th style="padding: 8px;">Status</th>
                            <th style="padding: 8px;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($myPrescriptions)): ?>
                        <tr><td colspan="5" style="padding: 16px; text-align: center; color: var(--dark-muted);">No prescriptions issued yet.</td></tr>
                        <?php else: ?>
                        <?php foreach ($myPrescriptions as $rx): ?>
                        <tr style="border-bottom: 1px solid var(--border);">
                            <td style="padding: 10px;"><strong>#Rx-<?= $rx['id'] ?></strong></td>
                            <td style="padding: 10px;">Dr. <?= sanitize($rx['doctor_name']) ?></td>
                            <td style="padding: 10px;"><?= formatDate($rx['created_at']) ?></td>
                            <td style="padding: 10px;"><span class="badge" style="background: #e0f2fe; color: #0284c7; font-weight: 700;"><?= ucfirst($rx['status']) ?></span></td>
                            <td style="padding: 10px;">
                                <a href="/patient_view_invoice.php?patient_id=<?= $pId ?>" class="btn-book" style="padding: 4px 10px; font-size: 0.75rem;">
                                    <i class="fas fa-eye"></i> View Prescription
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- 3. TAB: LAB REPORTS -->
        <div id="tabLab" class="patient-tab-content" style="display: none;">
            <h3 style="font-size: 1.05rem; color: var(--dark); margin: 0 0 12px 0;"><i class="fas fa-flask text-info"></i> Diagnostic & Lab Test Reports</h3>
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; margin-bottom: 16px; font-size: 0.875rem;">
                    <thead>
                        <tr style="background: var(--light-bg); border-bottom: 2px solid var(--border); text-align: left;">
                            <th style="padding: 8px;">Order #</th>
                            <th style="padding: 8px;">Ordering Doctor</th>
                            <th style="padding: 8px;">Date</th>
                            <th style="padding: 8px;">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($myLabReports)): ?>
                        <tr><td colspan="4" style="padding: 16px; text-align: center; color: var(--dark-muted);">No lab test reports found.</td></tr>
                        <?php else: ?>
                        <?php foreach ($myLabReports as $lab): ?>
                        <tr style="border-bottom: 1px solid var(--border);">
                            <td style="padding: 10px;"><strong>#LAB-<?= $lab['id'] ?></strong></td>
                            <td style="padding: 10px;">Dr. <?= sanitize($lab['doctor_name'] ?? 'Hospital Lab') ?></td>
                            <td style="padding: 10px;"><?= formatDate($lab['ordered_at'] ?? '') ?></td>
                            <td style="padding: 10px;"><span class="badge" style="background: #fef3c7; color: #d97706; font-weight: 700;"><?= ucfirst($lab['status']) ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- 4. TAB: BILLS & INVOICES -->
        <div id="tabBills" class="patient-tab-content" style="display: none;">
            <h3 style="font-size: 1.05rem; color: var(--dark); margin: 0 0 12px 0;"><i class="fas fa-file-invoice-dollar text-success"></i> Hospital Invoices & Payment QR</h3>
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; margin-bottom: 16px; font-size: 0.875rem;">
                    <thead>
                        <tr style="background: var(--light-bg); border-bottom: 2px solid var(--border); text-align: left;">
                            <th style="padding: 8px;">Invoice #</th>
                            <th style="padding: 8px;">Net Amount</th>
                            <th style="padding: 8px;">Payment Status</th>
                            <th style="padding: 8px;">Date</th>
                            <th style="padding: 8px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($myBills)): ?>
                        <tr><td colspan="5" style="padding: 16px; text-align: center; color: var(--dark-muted);">No billing receipts recorded yet.</td></tr>
                        <?php else: ?>
                        <?php foreach ($myBills as $b): ?>
                        <tr style="border-bottom: 1px solid var(--border);">
                            <td style="padding: 10px;"><strong><?= sanitize($b['invoice_number']) ?></strong></td>
                            <td style="padding: 10px; font-weight: 700; color: var(--accent);"><?= formatCurrency($b['net_amount']) ?></td>
                            <td style="padding: 10px;"><span class="badge" style="background: #dcfce7; color: #16a34a; font-weight: 700;"><?= strtoupper($b['payment_status']) ?></span></td>
                            <td style="padding: 10px;"><?= formatDate($b['created_at']) ?></td>
                            <td style="padding: 10px;">
                                <a href="/patient_view_invoice.php?bill_id=<?= $b['id'] ?>" class="btn-book" style="padding: 4px 10px; font-size: 0.75rem; background: var(--accent);">
                                    <i class="fas fa-receipt"></i> View Invoice
                                </a>
                                <a href="/patient_print_invoice.php?bill_id=<?= $b['id'] ?>&autoprint=1" target="_blank" class="btn-book" style="padding: 4px 10px; font-size: 0.75rem;">
                                    <i class="fas fa-print"></i> Print
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
function openModal(id) {
    document.getElementById(id).style.display = 'flex';
}
function closeModal(id) {
    document.getElementById(id).style.display = 'none';
}
function openPatientModal() {
    openModal('patientProfileModal');
}

function filterDoctorsByDept(deptId) {
    const doctorSelect = document.getElementById('doctorSelect');
    const options = doctorSelect.options;
    
    for (let i = 0; i < options.length; i++) {
        const opt = options[i];
        if (!opt.value) continue;
        const optDept = opt.getAttribute('data-dept');
        if (!deptId || optDept === deptId) {
            opt.style.display = 'block';
        } else {
            opt.style.display = 'none';
        }
    }
}

function switchPatientTab(tabId) {
    const tabs = ['tabAppts', 'tabRx', 'tabLab', 'tabBills'];
    const btns = ['btnTabAppts', 'btnTabRx', 'btnTabLab', 'btnTabBills'];
    
    tabs.forEach(t => {
        const el = document.getElementById(t);
        if (el) el.style.display = (t === tabId) ? 'block' : 'none';
    });

    btns.forEach(b => {
        const btn = document.getElementById(b);
        if (btn) {
            const isTarget = b.toLowerCase().includes(tabId.replace('tab', '').toLowerCase());
            btn.style.borderBottom = isTarget ? '3px solid var(--accent)' : 'none';
            btn.style.color = isTarget ? 'var(--accent)' : 'var(--dark-muted)';
            btn.style.fontWeight = isTarget ? '700' : '600';
        }
    });
}

function selectDoctorForBooking(doctorId, deptId) {
    if (deptId) {
        document.getElementById('deptSelect').value = deptId;
        filterDoctorsByDept(deptId);
    }
    document.getElementById('doctorSelect').value = doctorId;
    document.getElementById('booking').scrollIntoView({ behavior: 'smooth' });
}
</script>

</body>
</html>
