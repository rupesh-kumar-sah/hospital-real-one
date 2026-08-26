<?php
/**
 * Hospital Management System — Reset Password
 */

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth_middleware.php';

if (isLoggedIn()) {
    header('Location: ' . (ROLE_DASHBOARDS[getUserRole()] ?? '/'));
    exit;
}

$token = trim($_GET['token'] ?? $_POST['token'] ?? '');
$error = '';
$tokenValid = false;
$userInfo = null;

$db = getDB();

if ($token) {
    $stmt = $db->prepare("
        SELECT pr.*, u.username, u.email, u.full_name 
        FROM password_resets pr
        JOIN users u ON pr.user_id = u.id
        WHERE pr.token = ? AND pr.expires_at > DATETIME('now')
    ");
    $stmt->execute([$token]);
    $resetData = $stmt->fetch();
    
    if ($resetData) {
        $tokenValid = true;
        $userInfo = $resetData;
    } else {
        $error = 'This password reset link is invalid or has expired. Please request a new one.';
    }
} else {
    $error = 'No reset token provided. Please start from the forgot password page.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tokenValid) {
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    if (strlen($newPassword) < 6) {
        $error = 'New password must be at least 6 characters long.';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'Passwords do not match. Please verify and try again.';
    } else {
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        
        // Update user password
        $update = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        $update->execute([$hash, $userInfo['user_id']]);
        
        // Delete reset token
        $del = $db->prepare("DELETE FROM password_resets WHERE user_id = ?");
        $del->execute([$userInfo['user_id']]);
        
        // Audit log
        logAudit('update', 'users', $userInfo['user_id'], 'Password reset successfully for user: ' . $userInfo['username']);
        
        setFlash('success', 'Password reset successfully for ' . $userInfo['full_name'] . '! You can now log in.');
        header('Location: /auth/login.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set New Password — <?= APP_NAME ?></title>
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
            <div class="logo-icon"><i class="fas fa-lock"></i></div>
            <h1>Reset Password</h1>
            <?php if ($userInfo): ?>
                <p>Set a new password for account <strong><?= sanitize($userInfo['username']) ?></strong> (<?= sanitize($userInfo['email']) ?>)</p>
            <?php else: ?>
                <p>Set a new password for your account.</p>
            <?php endif; ?>
        </div>
        
        <?php if ($error): ?>
        <div class="alert alert-error" style="background: rgba(239,68,68,0.15); color: #fca5a5; border-left-color: #ef4444; margin-bottom: 20px;">
            <i class="fas fa-exclamation-circle"></i>
            <?= sanitize($error) ?>
        </div>
        <?php endif; ?>
        
        <?php if ($tokenValid): ?>
        <form method="POST" action="">
            <input type="hidden" name="token" value="<?= sanitize($token) ?>">
            
            <div class="form-group">
                <label class="form-label" for="new_password">New Password</label>
                <input type="password" class="form-control" id="new_password" name="new_password" 
                       placeholder="Enter new password (min. 6 chars)" required minlength="6"
                       autocomplete="new-password">
            </div>
            
            <div class="form-group">
                <label class="form-label" for="confirm_password">Confirm New Password</label>
                <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                       placeholder="Re-enter new password" required minlength="6"
                       autocomplete="new-password">
            </div>
            
            <button type="submit" class="btn btn-primary" style="margin-top: 10px;">
                <i class="fas fa-save"></i> Save New Password
            </button>
        </form>
        <?php else: ?>
        <div style="text-align: center; margin-top: 16px;">
            <a href="/auth/forgot_password.php" class="btn btn-primary">
                <i class="fas fa-arrow-left"></i> Go to Forgot Password Page
            </a>
        </div>
        <?php endif; ?>
        
        <div class="login-footer" style="margin-top: 24px;">
            Back to <a href="/auth/login.php">Login Page</a>
        </div>
    </div>
</div>
</body>
</html>
