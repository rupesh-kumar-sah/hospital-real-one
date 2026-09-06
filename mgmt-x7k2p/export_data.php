<?php
require_once __DIR__ . '/../config/ip_allowlist.php';
checkIPAllowlist('admin');
/**
 * Local file exports are disabled.
 *
 * All application CRUD data is stored in Neon PostgreSQL. Backups are managed
 * outside the web request so the website cannot write patient data to a local
 * computer folder.
 */

require_once __DIR__ . '/../includes/auth_middleware.php';
requireRole('admin');

http_response_code(410);
include __DIR__ . '/../includes/header.php';
?>
<div class="card">
    <div class="card-body">
        <h1>Local exports disabled</h1>
        <p>CRUD data is stored in Neon PostgreSQL. Local HM DATA exports are no longer available.</p>
        <a class="btn btn-primary" href="<?= adminUrl('audit_logs.php') ?>">Return to audit logs</a>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
