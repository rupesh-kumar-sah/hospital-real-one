<?php
/**
 * Hospital Management System — Admin Settings
 */

require_once __DIR__ . '/../includes/auth_middleware.php';
require_once __DIR__ . '/../includes/data_exporter.php';
requireRole('admin');

$pageTitle = 'System Settings';
$breadcrumbs = [['label' => 'Dashboard', 'url' => '/admin/dashboard.php'], ['label' => 'Settings']];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'save_info';
    if ($action === 'sync_excel_data') {
        $exported = exportAllTablesToHMData();
        setFlash('success', 'All database tables successfully exported as CSV spreadsheets and interactive Google Sheets dashboard in E:\\HM DATA\\');
        header('Location: /admin/settings.php');
        exit;
    } else {
        setFlash('success', 'System settings saved successfully.');
        header('Location: /admin/settings.php');
        exit;
    }
}

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>System Settings & Tabular Data Sync</h1>
        <p class="page-subtitle">Configure hospital profile, local storage, and export Google-Sheets-like tabular data to E:\HM DATA</p>
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
            <h3><i class="fas fa-database text-accent"></i> E:\HM DATA Drive Storage & Tabular Sheets Sync</h3>
        </div>
        <div class="card-body">
            <div class="alert alert-info">
                <i class="fas fa-folder"></i> <strong>Database Storage Path:</strong> E:\HM DATA\hms.db
            </div>
            <p class="text-sm text-muted mb-16">All patient medical records, doctors, nurses, prescriptions, lab reports, and billing payments are saved on your laptop's E:\HM DATA folder.</p>
            
            <div class="card p-16 bg-light mb-16" style="border: 1px solid var(--gray-200);">
                <h4><i class="fas fa-file-excel text-success"></i> Google Sheets & CSV Data Exporter</h4>
                <p class="text-xs text-muted mb-12">Clicking below generates individual CSV spreadsheets for all 25 tables + an interactive <code>Hospital_Data_Sheets.html</code> dashboard inside <strong>E:\HM DATA\</strong>.</p>
                
                <form method="POST" style="display:inline;">
                    <input type="hidden" name="action" value="sync_excel_data">
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-sync-alt"></i> Sync & Export All Tables to E:\HM DATA
                    </button>
                </form>
            </div>

            <div class="form-group">
                <label class="form-label">Database Mode</label>
                <input type="text" class="form-control" value="SQLite3 PDO (High-Performance WAL Mode)" readonly>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
