<?php
require_once __DIR__ . '/../config/ip_allowlist.php';
checkIPAllowlist('admin');
require_once __DIR__ . '/../includes/auth_middleware.php';
require_once __DIR__ . '/../config/devices.php';
requireRole('admin');

$db = getDB();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCSRF();
    $deviceId = (int)($_POST['device_id'] ?? 0);
    $stmt = $db->prepare('UPDATE admin_devices SET revoked_at = CURRENT_TIMESTAMP WHERE id = ? AND user_id = ?');
    $stmt->execute([$deviceId, getUserId()]);
    logAudit('device_revoked', 'admin_devices', $deviceId, 'Administrator revoked a registered device');
    setFlash('success', 'Device revoked.');
    header('Location: ' . adminUrl('devices.php'));
    exit;
}
$stmt = $db->prepare('SELECT id, label, ip_address, created_at, last_used_at, expires_at, revoked_at FROM admin_devices WHERE user_id = ? ORDER BY created_at DESC');
$stmt->execute([getUserId()]);
$devices = $stmt->fetchAll();
$pageTitle = 'Registered Devices';
$breadcrumbs = [['label' => 'Dashboard', 'url' => adminUrl('dashboard.php')], ['label' => 'Registered Devices']];
include __DIR__ . '/../includes/header.php';
?>
<div class="page-header"><div><h1>Registered Devices</h1><p class="page-subtitle">Revoke browser access without changing your password.</p></div></div>
<div class="card"><div class="card-body">
<?php foreach ($devices as $device): ?>
<div style="display:flex;justify-content:space-between;gap:16px;padding:12px 0;border-bottom:1px solid var(--gray-200);">
    <div><strong><?= sanitize($device['label']) ?></strong><br><small><?= sanitize($device['ip_address']) ?> · Last used <?= sanitize($device['last_used_at'] ?? 'Never') ?></small></div>
    <?php if (!$device['revoked_at']): ?><form method="POST"><input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>"><input type="hidden" name="device_id" value="<?= (int)$device['id'] ?>"><button class="btn btn-danger" type="submit">Revoke</button></form><?php else: ?><span class="text-muted">Revoked</span><?php endif; ?>
</div>
<?php endforeach; ?>
</div></div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
