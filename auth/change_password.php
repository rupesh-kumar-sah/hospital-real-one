<?php
/**
 * Required first-login password change for administrator-created accounts.
 */
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth_middleware.php';

requireLogin();

$db = getDB();
$stmt = $db->prepare('SELECT id, username, must_change_password FROM users WHERE id = ? AND status = \'active\'');
$stmt->execute([getUserId()]);
$user = $stmt->fetch();
if (!$user || !(int)($user['must_change_password'] ?? 0)) {
    redirectToDashboard();
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCSRF();
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    if (($passwordError = passwordStrengthError($newPassword)) !== null) {
        $error = $passwordError;
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'Passwords do not match.';
    } else {
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        $update = $db->prepare('UPDATE users SET password_hash = ?, must_change_password = FALSE, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
        $update->execute([$hash, getUserId()]);
        $revoke = $db->prepare('UPDATE refresh_tokens SET revoked = 1 WHERE user_id = ?');
        $revoke->execute([getUserId()]);
        $_SESSION['must_change_password'] = false;
        logAudit('update', 'users', getUserId(), 'Required password change completed');
        setFlash('success', 'Your password has been changed successfully.');
        redirectToDashboard();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Required Password — <?= APP_NAME ?></title>
    <meta name="robots" content="noindex, nofollow, noarchive">
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<div class="login-page">
    <div class="login-card">
        <div class="login-logo">
            <div class="logo-icon"><i class="fas fa-key"></i></div>
            <h1>Change Your Password</h1>
            <p>Your administrator-issued password must be replaced before you can continue.</p>
        </div>
        <?php if ($error): ?>
        <div class="alert alert-error"><?= sanitize($error) ?></div>
        <?php endif; ?>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
            <div class="form-group">
                <label class="form-label" for="new_password">New Password</label>
                <input type="password" class="form-control" id="new_password" name="new_password"
                       minlength="12" autocomplete="new-password" required>
                <small>At least 12 characters with upper/lowercase letters, a number, and a symbol.</small>
            </div>
            <div class="form-group">
                <label class="form-label" for="confirm_password">Confirm New Password</label>
                <input type="password" class="form-control" id="confirm_password" name="confirm_password"
                       minlength="12" autocomplete="new-password" required>
            </div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Set Password</button>
        </form>
        <div class="login-footer"><a href="/auth/logout.php">Sign out</a></div>
    </div>
</div>
</body>
</html>
