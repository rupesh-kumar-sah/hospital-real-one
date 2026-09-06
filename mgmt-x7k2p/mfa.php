<?php
require_once __DIR__ . '/../config/ip_allowlist.php';
checkIPAllowlist('admin');
require_once __DIR__ . '/../includes/auth_middleware.php';
require_once __DIR__ . '/../config/mfa.php';
requireRole('admin');

$db = getDB();
$stmt = $db->prepare('SELECT id, username, email, mfa_enabled FROM users WHERE id = ? AND role = \'admin\'');
$stmt->execute([getUserId()]);
$admin = $stmt->fetch();
if (!$admin) {
    http_response_code(403);
    exit('Administrator account not found.');
}

$error = '';
$backupCodes = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCSRF();
    if ($admin['mfa_enabled']) {
        $error = 'MFA is already enabled. Contact another administrator to reset enrollment.';
    } else {
        $secret = $_SESSION['mfa_enrollment_secret'] ?? '';
        $code = trim((string)($_POST['code'] ?? ''));
        if (!$secret || !verifyTotpCode($secret, $code)) {
            $error = 'The authenticator code is invalid or expired.';
        } else {
            $backupCodes = generateBackupCodes();
            $encryptedSecret = encryptData($secret);
            if (!is_string($encryptedSecret) || !str_starts_with($encryptedSecret, 'ENC::')) {
                $backupCodes = [];
                $error = 'MFA could not be enabled because secure encryption is unavailable.';
            } else {
                $update = $db->prepare('UPDATE users SET mfa_enabled = TRUE, mfa_secret = ?, mfa_backup_codes = ?, mfa_enrolled_at = CURRENT_TIMESTAMP WHERE id = ?');
                $update->execute([$encryptedSecret, hashBackupCodes($backupCodes), getUserId()]);
                unset($_SESSION['mfa_enrollment_secret']);
                $admin['mfa_enabled'] = 1;
                logAudit('update', 'users', getUserId(), 'Administrator MFA enrolled');
            }
        }
    }
}

if (!$admin['mfa_enabled']) {
    if (empty($_SESSION['mfa_enrollment_secret'])) {
        $_SESSION['mfa_enrollment_secret'] = generateTotpSecret();
    }
    $enrollmentSecret = $_SESSION['mfa_enrollment_secret'];
    $otpauthUri = totpUri($enrollmentSecret, (string)$admin['email']);
}

$pageTitle = 'Admin MFA';
$breadcrumbs = [['label' => 'Dashboard', 'url' => adminUrl('dashboard.php')], ['label' => 'MFA Security']];
include __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
    <div>
        <h1>Administrator MFA</h1>
        <p class="page-subtitle">Protect administrator sign-in with a standard RFC 6238 authenticator.</p>
    </div>
</div>
<div class="card" style="max-width: 760px;">
    <div class="card-body">
        <?php if ($error): ?><div class="alert alert-error"><?= sanitize($error) ?></div><?php endif; ?>
        <?php if ($backupCodes): ?>
            <div class="alert alert-success">
                <strong>Save these backup codes now.</strong> They are shown once and cannot be recovered.
                <pre style="white-space: pre-wrap; user-select: all;"><?= sanitize(implode("\n", $backupCodes)) ?></pre>
            </div>
            <p>MFA is enabled. Future administrator logins require an authenticator code or one unused backup code.</p>
        <?php elseif ($admin['mfa_enabled']): ?>
            <div class="alert alert-success"><i class="fas fa-shield-halved"></i> MFA is enabled for this account.</div>
            <p>Backup codes and the authenticator secret are never displayed again.</p>
        <?php else: ?>
            <p>Scan the URI below with your authenticator app, or paste it into a QR-code generator. The secret is shown only during enrollment.</p>
            <div class="form-group">
                <label class="form-label" for="otpauth_uri">QR-friendly otpauth URI</label>
                <textarea id="otpauth_uri" class="form-control" rows="3" readonly><?= sanitize($otpauthUri) ?></textarea>
            </div>
            <div class="form-group">
                <label class="form-label">Manual secret</label>
                <input class="form-control" value="<?= sanitize($enrollmentSecret) ?>" readonly>
            </div>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                <div class="form-group">
                    <label class="form-label" for="code">Enter the 6-digit code from your app</label>
                    <input type="text" class="form-control" id="code" name="code" inputmode="numeric"
                           autocomplete="one-time-code" pattern="[0-9]{6}" required>
                </div>
                <button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> Enable MFA</button>
            </form>
        <?php endif; ?>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
