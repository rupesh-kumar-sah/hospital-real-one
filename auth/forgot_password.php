<?php
/**
 * Hospital Management System — Forgot Password
 */

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth_middleware.php';

// If already logged in, redirect to dashboard
if (isLoggedIn()) {
    header('Location: ' . (ROLE_DASHBOARDS[getUserRole()] ?? '/'));
    exit;
}

$error = '';
$successMsg = '';
$resetUrl = '';
$userFound = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = trim($_POST['identifier'] ?? '');
    
    if (empty($identifier)) {
        $error = 'Please enter your username or email address.';
    } else {
        $db = getDB();
        
        // Ensure password_resets table exists
        $db->exec("CREATE TABLE IF NOT EXISTS password_resets (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            token VARCHAR(64) UNIQUE NOT NULL,
            expires_at DATETIME NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
        
        $stmt = $db->prepare("SELECT id, username, email, full_name, role FROM users WHERE (username = ? OR email = ?) AND status = 'active'");
        $stmt->execute([$identifier, $identifier]);
        $userFound = $stmt->fetch();
        
        if ($userFound) {
            $token = bin2hex(random_bytes(32));
            $expiresAt = date('Y-m-d H:i:s', time() + 3600); // 1 hour validity
            
            // Delete old tokens for user
            $del = $db->prepare("DELETE FROM password_resets WHERE user_id = ?");
            $del->execute([$userFound['id']]);
            
            // Insert new token
            $ins = $db->prepare("INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)");
            $ins->execute([$userFound['id'], $token, $expiresAt]);
            
            $resetUrl = "/auth/reset_password.php?token=" . $token;
            $successMsg = "Account identified for <strong>" . sanitize($userFound['full_name']) . "</strong> (" . sanitize($userFound['email']) . "). You can now reset your password below.";
        } else {
            $error = 'No active account found with that username or email address.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password — <?= APP_NAME ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<div class="login-page">
    <div class="login-card">
        <div class="login-logo">
            <div class="logo-icon"><i class="fas fa-key"></i></div>
            <h1>Forgot Password?</h1>
            <p>Enter your username or registered email to reset your account password.</p>
        </div>
        
        <?php if ($error): ?>
        <div class="alert alert-error" style="background: rgba(239,68,68,0.15); color: #fca5a5; border-left-color: #ef4444; margin-bottom: 20px;">
            <i class="fas fa-exclamation-circle"></i>
            <?= sanitize($error) ?>
        </div>
        <?php endif; ?>
        
        <?php if ($successMsg): ?>
        <div class="alert alert-success" style="background: rgba(16,185,129,0.15); color: #6ee7b7; border-left-color: #10b981; margin-bottom: 20px;">
            <i class="fas fa-check-circle"></i>
            <?= $successMsg ?>
        </div>
        
        <div style="background: var(--gray-800); border: 1px solid var(--gray-700); border-radius: 8px; padding: 16px; margin-bottom: 20px; text-align: center;">
            <p style="color: var(--gray-300); font-size: 0.875rem; margin-bottom: 12px;">Click below to proceed to the Password Reset screen:</p>
            <a href="<?= $resetUrl ?>" class="btn btn-success" style="display: block; width: 100%; text-decoration: none;">
                <i class="fas fa-lock-open"></i> Proceed to Reset Password
            </a>
        </div>
        <?php else: ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label class="form-label" for="identifier">Username or Registered Email</label>
                <input type="text" class="form-control" id="identifier" name="identifier" 
                       placeholder="e.g. ram@gmail.com or ram" required
                       value="<?= sanitize($_POST['identifier'] ?? '') ?>"
                       autocomplete="username">
            </div>
            
            <button type="submit" class="btn btn-primary" style="margin-top: 10px;">
                <i class="fas fa-paper-plane"></i> Find Account & Reset Password
            </button>
        </form>
        
        <?php endif; ?>
        
        <div class="login-footer" style="margin-top: 24px;">
            Remember your password? <a href="/auth/login.php">Back to Login</a>
        </div>
    </div>
</div>
</body>
</html>
