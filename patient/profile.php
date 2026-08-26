<?php
/**
 * Hospital Management System — Patient: Profile
 */

require_once __DIR__ . '/../includes/auth_middleware.php';
requireRole('patient');

$pageTitle = 'My Profile';
$breadcrumbs = [['label' => 'Dashboard', 'url' => '/patient/dashboard.php'], ['label' => 'Profile']];

$db = getDB();
$patient = getPatientByUserId(getUserId());
$user = getCurrentUser();

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>My Personal Profile</h1>
        <p class="page-subtitle">Hospital Registration Details & Identification</p>
    </div>
</div>

<div class="card" style="max-width: 600px;">
    <div class="card-body">
        <div style="display: flex; align-items: center; gap: 20px; margin-bottom: 24px; padding-bottom: 20px; border-bottom: 1px solid var(--border);">
            <div class="avatar avatar-xl" style="background: var(--primary);">
                <?= strtoupper(substr(getUserName(), 0, 1)) ?>
            </div>
            <div>
                <h2><?= sanitize(getUserName()) ?></h2>
                <p class="text-sm text-muted">Hospital UHID: <code style="font-weight: 700; background: #e0f2fe; color: #0284c7; padding: 2px 8px; border-radius: 4px;"><?= sanitize($patient['uhid'] ?? 'N/A') ?></code></p>
            </div>
        </div>

        <div class="form-group mb-16">
            <label class="form-label">Phone Number</label>
            <input type="text" class="form-control" value="<?= sanitize($user['phone'] ?? 'N/A') ?>" readonly>
        </div>

        <div class="form-group mb-16">
            <label class="form-label">Email Address</label>
            <input type="text" class="form-control" value="<?= sanitize($user['email'] ?? 'N/A') ?>" readonly>
        </div>

        <div class="form-row mb-16">
            <div class="form-group">
                <label class="form-label">Gender</label>
                <input type="text" class="form-control" value="<?= ucfirst(sanitize($patient['gender'] ?? 'N/A')) ?>" readonly>
            </div>
            <div class="form-group">
                <label class="form-label">Blood Group</label>
                <input type="text" class="form-control" value="<?= sanitize($patient['blood_group'] ?? 'Not recorded') ?>" readonly>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
