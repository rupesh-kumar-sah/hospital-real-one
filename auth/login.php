<?php
/**
 * Hospital Management System — Multi-Role Login Page
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (!checkRateLimit('login', 10, 900)) {
        $error = 'Too many failed login attempts. Please wait 15 minutes before trying again.';
    } elseif (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } else {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM users WHERE (username = ? OR email = ?) AND status = 'active'");
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password_hash'])) {
            // Login successful
            resetRateLimit('login');
            setUserSession($user);
            
            // Update last login
            $update = $db->prepare("UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = ?");
            $update->execute([$user['id']]);
            
            // Audit log
            logAudit('login', 'users', $user['id'], 'User logged in as ' . $user['role'] . ': ' . $user['username']);
            
            setFlash('success', 'Welcome back, ' . $user['full_name'] . '!');
            header('Location: ' . (ROLE_DASHBOARDS[$user['role']] ?? '/'));
            exit;
        } else {
            $error = 'Invalid username or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Multi-Role Login — <?= APP_NAME ?></title>
    <meta name="description" content="Login to <?= APP_NAME ?> — <?= APP_TAGLINE ?>">
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        .role-btn-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
            gap: 8px;
            margin-top: 12px;
            margin-bottom: 12px;
        }
        .role-btn {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--gray-700);
            border-radius: 6px;
            padding: 8px 10px;
            color: var(--gray-200);
            font-size: 0.75rem;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s ease;
            text-align: left;
        }
        .role-btn:hover {
            background: var(--primary);
            border-color: var(--primary);
            color: #fff;
            transform: translateY(-1px);
        }
        .role-btn i {
            font-size: 0.875rem;
        }
    </style>
</head>
<body>
<div class="login-page">
    <div class="login-card" style="max-width: 520px;">
        <div class="login-logo">
            <div class="logo-icon"><i class="fas fa-hospital"></i></div>
            <h1><?= APP_NAME ?></h1>
            <p><?= APP_TAGLINE ?></p>
        </div>
        
        <?php if ($error): ?>
        <div class="alert alert-error" style="background: rgba(239,68,68,0.15); color: #fca5a5; border-left-color: #ef4444;">
            <i class="fas fa-exclamation-circle"></i>
            <?= sanitize($error) ?>
        </div>
        <?php endif; ?>
        
        <?php 
        $flash = getFlash();
        if ($flash): 
        ?>
        <div class="alert alert-<?= $flash['type'] ?>" style="background: rgba(16,185,129,0.15); color: #6ee7b7; border-left-color: #10b981;">
            <i class="fas fa-check-circle"></i>
            <?= sanitize($flash['message']) ?>
        </div>
        <?php endif; ?>

        <form method="POST" action="" id="loginForm">
            <div class="form-group">
                <label class="form-label" for="username">Username or Email</label>
                <input type="text" class="form-control" id="username" name="username" 
                       placeholder="Enter your username or email" required
                       value="<?= sanitize($_POST['username'] ?? '') ?>"
                       autocomplete="username">
            </div>
            
            <div class="form-group">
                <label class="form-label" for="password">Password</label>
                <input type="password" class="form-control" id="password" name="password" 
                       placeholder="Enter your password" required
                       autocomplete="current-password">
            </div>
            
            <div class="form-group" style="display: flex; align-items: center; justify-content: space-between;">
                <label class="form-check" style="margin: 0;">
                    <input type="checkbox" name="remember" style="accent-color: var(--primary);">
                    <span style="color: var(--gray-400); font-size: 0.8125rem;">Remember me</span>
                </label>
                <a href="/auth/forgot_password.php" style="color: var(--primary); font-size: 0.8125rem; text-decoration: none; font-weight: 500;">
                    <i class="fas fa-question-circle"></i> Forgot password?
                </a>
            </div>
            
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-sign-in-alt"></i> Sign In
            </button>
        </form>
        
        <div class="login-footer" style="margin-top: 16px;">
            New patient? <a href="/index.php">Book Appointment Online</a>
        </div>
    </div>
</div>
</body>
</html>
