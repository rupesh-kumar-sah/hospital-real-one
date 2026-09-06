<?php
require_once __DIR__ . '/../config/ip_allowlist.php';
checkIPAllowlist('admin');
/**
 * Hospital Management System — Admin Settings
 */

require_once __DIR__ . '/../includes/auth_middleware.php';
require_once __DIR__ . '/../config/destructive_actions.php';
requireRole('admin');

$pageTitle = 'System Settings';
$breadcrumbs = [['label' => 'Dashboard', 'url' => adminUrl('dashboard.php')], ['label' => 'Settings']];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'save_info';
    if ($action === 'delete_all_data') {
        requireCSRF();
        $confirmation = trim((string)($_POST['delete_confirmation'] ?? ''));
        $password = (string)($_POST['admin_password'] ?? '');
        if ($confirmation !== 'DELETE ALL HMS DATA') {
            setFlash('error', 'Deletion cancelled: type DELETE ALL HMS DATA exactly.');
        } else {
            $admin = getDB()->prepare('SELECT password_hash FROM users WHERE id = ? AND role = ? AND status = ?');
            $admin->execute([getUserId(), 'admin', 'active']);
            $adminHash = $admin->fetchColumn();
            if (!is_string($adminHash) || !password_verify($password, $adminHash)) {
                setFlash('error', 'Deletion cancelled: administrator password is incorrect.');
            } else {
                deleteAllApplicationData(getDB());
                destroySession();
                header('Location: /auth/login.php');
                exit;
            }
        }
        header('Location: ' . adminUrl('settings.php'));
        exit;
    } else {
        setFlash('success', 'System settings saved successfully.');
        header('Location: ' . adminUrl('settings.php'));
        exit;
    }
}

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>System Settings & Neon Database</h1>
        <p class="page-subtitle">Configure hospital profile and manage the connected Neon PostgreSQL database</p>
    </div>
</div>

<div class="grid-2">
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-hospital text-primary"></i> Hospital Profile Information</h3>
        </div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="action" value="save_info">
                <div class="form-group">
                    <label class="form-label">Hospital Name</label>
                    <input type="text" class="form-control" value="MediCare Hospital & Research Centre">
                </div>
                <div class="form-group">
                    <label class="form-label">Address</label>
                    <input type="text" class="form-control" value="Kathmandu, Nepal">
                </div>
                <div class="form-group">
                    <label class="form-label">Contact Phone</label>
                    <input type="text" class="form-control" value="+977 1 4000000">
                </div>
                <div class="form-group">
                    <label class="form-label">Emergency Hotline</label>
                    <input type="text" class="form-control" value="+977 1 4000001">
                </div>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Information</button>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-database text-accent"></i> Neon PostgreSQL Database</h3>
        </div>
        <div class="card-body">
            <div class="alert alert-info">
                <i class="fas fa-cloud"></i> <strong>Database Storage:</strong> Neon PostgreSQL over encrypted SSL
            </div>
            <p class="text-sm text-muted mb-16">All patient records, clinical data, prescriptions, lab reports, billing, and audit records are stored in Neon. CRUD operations in this application use the connected Neon database only.</p>

            <div class="card p-16 mb-16" style="border: 2px solid #dc2626; background: #fff7f7;">
                <h4 style="color: #b91c1c;"><i class="fas fa-triangle-exclamation"></i> Danger Zone: Delete All Application Data</h4>
                <p class="text-xs text-muted mb-12">This permanently deletes all records from the connected Neon PostgreSQL database. The action is refused if the app is not connected to Neon. It does not delete files stored in Google Drive, OneDrive, or your local computer.</p>
                <form method="POST" onsubmit="return confirm('This permanently deletes all application records. Continue only if you have a verified backup.');">
                    <input type="hidden" name="action" value="delete_all_data">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCSRFToken()) ?>">
                    <div class="form-group">
                        <label class="form-label">Administrator password</label>
                        <input type="password" name="admin_password" class="form-control" required autocomplete="current-password">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Type <code>DELETE ALL HMS DATA</code></label>
                        <input type="text" name="delete_confirmation" class="form-control" required pattern="DELETE ALL HMS DATA" autocomplete="off">
                    </div>
                    <button type="submit" class="btn btn-danger"><i class="fas fa-trash"></i> Permanently Delete All Application Data</button>
                </form>
            </div>

            <div class="form-group">
                <label class="form-label">Database Mode</label>
                <input type="text" class="form-control" value="Neon PostgreSQL (SSL required)" readonly>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
