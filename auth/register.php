<?php
/**
 * Hospital Management System — Patient Self-Registration
 */

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';

if (isLoggedIn()) {
    header('Location: ' . (ROLE_DASHBOARDS[getUserRole()] ?? '/'));
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $dob = $_POST['date_of_birth'] ?? '';
    $gender = $_POST['gender'] ?? '';
    
    // Validation
    if (empty($fullName) || empty($email) || empty($username) || empty($password)) {
        $error = 'Please fill in all required fields.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match.';
    } else {
        $db = getDB();
        
        // Check if username or email already exists
        $stmt = $db->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        if ($stmt->fetch()) {
            $error = 'Username or email already exists.';
        } else {
            try {
                $db->beginTransaction();
                
                // Create user
                $stmt = $db->prepare("INSERT INTO users (username, email, password_hash, full_name, phone, role, status) VALUES (?, ?, ?, ?, ?, 'patient', 'active')");
                $stmt->execute([
                    $username,
                    $email,
                    password_hash($password, PASSWORD_DEFAULT),
                    $fullName,
                    $phone
                ]);
                $userId = $db->lastInsertId();
                
                // Create patient record
                $uhid = generateUHID();
                $stmt = $db->prepare("INSERT INTO patients (user_id, uhid, date_of_birth, gender) VALUES (?, ?, ?, ?)");
                $stmt->execute([$userId, $uhid, $dob ?: null, $gender ?: null]);
                
                $db->commit();
                
                setFlash('success', 'Registration successful! Your Hospital ID is ' . $uhid . '. You can now log in.');
                header('Location: /auth/login.php');
                exit;
            } catch (Exception $e) {
                $db->rollBack();
                $error = 'Registration failed. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register — <?= APP_NAME ?></title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<div class="login-page">
    <div class="login-card" style="max-width: 520px;">
        <div class="login-logo">
            <div class="logo-icon"><i class="fas fa-hospital"></i></div>
            <h1>Patient Registration</h1>
            <p>Create your account to book appointments</p>
        </div>
        
        <?php if ($error): ?>
        <div class="alert alert-error" style="background: rgba(239,68,68,0.15); color: #fca5a5; border-left-color: #ef4444;">
            <i class="fas fa-exclamation-circle"></i>
            <?= sanitize($error) ?>
        </div>
        <?php endif; ?>
        
        <form method="POST" action="" id="registerForm">
            <div class="form-group">
                <label class="form-label" for="full_name">Full Name <span class="required">*</span></label>
                <input type="text" class="form-control" id="full_name" name="full_name" 
                       placeholder="Enter your full name" required
                       value="<?= sanitize($_POST['full_name'] ?? '') ?>">
            </div>
            
            <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                <div class="form-group">
                    <label class="form-label" for="email">Email <span class="required">*</span></label>
                    <input type="email" class="form-control" id="email" name="email" 
                           placeholder="email@example.com" required
                           value="<?= sanitize($_POST['email'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label" for="phone">Phone</label>
                    <input type="tel" class="form-control" id="phone" name="phone" 
                           placeholder="98XXXXXXXX"
                           value="<?= sanitize($_POST['phone'] ?? '') ?>">
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label" for="username">Username <span class="required">*</span></label>
                <input type="text" class="form-control" id="username" name="username" 
                       placeholder="Choose a username" required
                       value="<?= sanitize($_POST['username'] ?? '') ?>">
            </div>
            
            <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                <div class="form-group">
                    <label class="form-label" for="date_of_birth">Date of Birth</label>
                    <input type="date" class="form-control" id="date_of_birth" name="date_of_birth"
                           value="<?= sanitize($_POST['date_of_birth'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label" for="gender">Gender</label>
                    <select class="form-control" id="gender" name="gender">
                        <option value="">Select</option>
                        <option value="male" <?= ($_POST['gender'] ?? '') === 'male' ? 'selected' : '' ?>>Male</option>
                        <option value="female" <?= ($_POST['gender'] ?? '') === 'female' ? 'selected' : '' ?>>Female</option>
                        <option value="other" <?= ($_POST['gender'] ?? '') === 'other' ? 'selected' : '' ?>>Other</option>
                    </select>
                </div>
            </div>
            
            <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                <div class="form-group">
                    <label class="form-label" for="password">Password <span class="required">*</span></label>
                    <input type="password" class="form-control" id="password" name="password" 
                           placeholder="Min 6 characters" required minlength="6">
                </div>
                <div class="form-group">
                    <label class="form-label" for="confirm_password">Confirm Password <span class="required">*</span></label>
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                           placeholder="Re-enter password" required>
                </div>
            </div>
            
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-user-plus"></i> Create Account
            </button>
        </form>
        
        <div class="login-footer">
            Already have an account? <a href="/auth/login.php">Sign in</a>
        </div>
    </div>
</div>
</body>
</html>
