<?php
/**
 * Hospital Management System — Sidebar Component
 * Role-based navigation sidebar
 */

$currentUser = getCurrentUser();
$currentPage = basename($_SERVER['PHP_SELF']);
$currentDir = basename(dirname($_SERVER['PHP_SELF']));
$roleColor = ROLE_COLORS[$currentUser['role']] ?? '#6366f1';
$roleIcon = ROLE_ICONS[$currentUser['role']] ?? 'fa-user';
$roleLabel = ROLE_LABELS[$currentUser['role']] ?? ucfirst($currentUser['role']);

// Get pending counts for badges
$db = getDB();

// Define navigation items per role
$navItems = [];

$todayDate = date('Y-m-d');

switch ($currentUser['role']) {
    case 'admin':
        $navItems = [
            ['section' => 'Main'],
            ['label' => 'Dashboard', 'icon' => 'fa-chart-pie', 'url' => adminUrl('dashboard.php'), 'file' => 'dashboard.php', 'dir' => ADMIN_PATH],
            ['section' => 'Management'],
            ['label' => 'Users', 'icon' => 'fa-users', 'url' => adminUrl('manage_users.php'), 'file' => 'manage_users.php', 'dir' => ADMIN_PATH],
            ['label' => 'Departments', 'icon' => 'fa-building', 'url' => adminUrl('manage_departments.php'), 'file' => 'manage_departments.php', 'dir' => ADMIN_PATH],
            ['label' => 'Wards & Beds', 'icon' => 'fa-bed', 'url' => adminUrl('manage_wards.php'), 'file' => 'manage_wards.php', 'dir' => ADMIN_PATH],
            ['label' => 'Service Pricing', 'icon' => 'fa-tags', 'url' => adminUrl('manage_pricing.php'), 'file' => 'manage_pricing.php', 'dir' => ADMIN_PATH],
            ['label' => 'Payment & QR Codes', 'icon' => 'fa-qrcode', 'url' => adminUrl('manage_payment_methods.php'), 'file' => 'manage_payment_methods.php', 'dir' => ADMIN_PATH],
            ['section' => 'Reports'],
            ['label' => 'Analytics', 'icon' => 'fa-chart-bar', 'url' => adminUrl('reports.php'), 'file' => 'reports.php', 'dir' => ADMIN_PATH],
            ['label' => 'Audit Logs', 'icon' => 'fa-clipboard-list', 'url' => adminUrl('audit_logs.php'), 'file' => 'audit_logs.php', 'dir' => ADMIN_PATH],
            ['section' => 'System'],
            ['label' => 'MFA Security', 'icon' => 'fa-shield-halved', 'url' => adminUrl('mfa.php'), 'file' => 'mfa.php', 'dir' => ADMIN_PATH],
            ['label' => 'Registered Devices', 'icon' => 'fa-laptop', 'url' => adminUrl('devices.php'), 'file' => 'devices.php', 'dir' => ADMIN_PATH],
            ['label' => 'Settings', 'icon' => 'fa-cog', 'url' => adminUrl('settings.php'), 'file' => 'settings.php', 'dir' => ADMIN_PATH],
        ];
        break;

    case 'receptionist':
        $stmtPending = $db->prepare("SELECT COUNT(*) as c FROM appointments WHERE appointment_date = ? AND status = 'scheduled'");
        $stmtPending->execute([$todayDate]);
        $pendingAppts = (int)($stmtPending->fetch()['c'] ?? 0);

        $navItems = [
            ['section' => 'Main'],
            ['label' => 'Dashboard', 'icon' => 'fa-chart-pie', 'url' => '/receptionist/dashboard.php', 'file' => 'dashboard.php', 'dir' => 'receptionist'],
            ['section' => 'Patient Services'],
            ['label' => 'Register Patient', 'icon' => 'fa-user-plus', 'url' => '/receptionist/register_patient.php', 'file' => 'register_patient.php', 'dir' => 'receptionist'],
            ['label' => 'Appointments', 'icon' => 'fa-calendar-check', 'url' => '/receptionist/appointments.php', 'file' => 'appointments.php', 'dir' => 'receptionist', 'badge' => $pendingAppts > 0 ? $pendingAppts : null],
            ['label' => 'Check-In', 'icon' => 'fa-clipboard-check', 'url' => '/receptionist/check_in.php', 'file' => 'check_in.php', 'dir' => 'receptionist'],
            ['label' => 'Search Patient', 'icon' => 'fa-search', 'url' => '/receptionist/search_patient.php', 'file' => 'search_patient.php', 'dir' => 'receptionist'],
            ['section' => 'Billing'],
            ['label' => 'Billing', 'icon' => 'fa-file-invoice-dollar', 'url' => '/receptionist/billing.php', 'file' => 'billing.php', 'dir' => 'receptionist'],
        ];
        break;

    case 'doctor':
        $todayAppts = $db->prepare("SELECT COUNT(*) as c FROM appointments a JOIN doctors d ON a.doctor_id = d.id WHERE d.user_id = ? AND a.appointment_date = ? AND a.status IN ('scheduled','checked_in')");
        $todayAppts->execute([$currentUser['id'], $todayDate]);
        $queueCount = (int)($todayAppts->fetch()['c'] ?? 0);
        
        $navItems = [
            ['section' => 'Main'],
            ['label' => 'Dashboard', 'icon' => 'fa-chart-pie', 'url' => '/doctor/dashboard.php', 'file' => 'dashboard.php', 'dir' => 'doctor'],
            ['section' => 'Patient Care'],
            ['label' => 'Patient Queue', 'icon' => 'fa-list-ol', 'url' => '/doctor/patient_queue.php', 'file' => 'patient_queue.php', 'dir' => 'doctor', 'badge' => $queueCount > 0 ? $queueCount : null],
            ['label' => 'Consultation', 'icon' => 'fa-stethoscope', 'url' => '/doctor/consultation.php', 'file' => 'consultation.php', 'dir' => 'doctor'],
            ['label' => 'Patient History', 'icon' => 'fa-file-medical', 'url' => '/doctor/patient_history.php', 'file' => 'patient_history.php', 'dir' => 'doctor'],
            ['label' => 'Prescriptions', 'icon' => 'fa-prescription', 'url' => '/doctor/prescriptions.php', 'file' => 'prescriptions.php', 'dir' => 'doctor'],
            ['section' => 'Orders'],
            ['label' => 'Lab Orders', 'icon' => 'fa-flask', 'url' => '/doctor/order_lab_test.php', 'file' => 'order_lab_test.php', 'dir' => 'doctor'],
            ['label' => 'Admit Patient', 'icon' => 'fa-procedures', 'url' => '/doctor/admit_patient.php', 'file' => 'admit_patient.php', 'dir' => 'doctor'],
            ['label' => 'Discharge', 'icon' => 'fa-door-open', 'url' => '/doctor/discharge.php', 'file' => 'discharge.php', 'dir' => 'doctor'],
        ];
        break;

    case 'nurse':
        $navItems = [
            ['section' => 'Main'],
            ['label' => 'Dashboard', 'icon' => 'fa-chart-pie', 'url' => '/nurse/dashboard.php', 'file' => 'dashboard.php', 'dir' => 'nurse'],
            ['section' => 'Ward Management'],
            ['label' => 'Ward Patients', 'icon' => 'fa-bed', 'url' => '/nurse/ward_patients.php', 'file' => 'ward_patients.php', 'dir' => 'nurse'],
            ['label' => 'Record Vitals', 'icon' => 'fa-heartbeat', 'url' => '/nurse/vitals.php', 'file' => 'vitals.php', 'dir' => 'nurse'],
            ['label' => 'Medication', 'icon' => 'fa-pills', 'url' => '/nurse/medication.php', 'file' => 'medication.php', 'dir' => 'nurse'],
            ['label' => 'Nursing Notes', 'icon' => 'fa-notes-medical', 'url' => '/nurse/nursing_notes.php', 'file' => 'nursing_notes.php', 'dir' => 'nurse'],
            ['label' => 'Bed Management', 'icon' => 'fa-hospital', 'url' => '/nurse/bed_management.php', 'file' => 'bed_management.php', 'dir' => 'nurse'],
        ];
        break;

    case 'patient':
        $navItems = [
            ['section' => 'Main'],
            ['label' => 'Dashboard', 'icon' => 'fa-home', 'url' => '/patient/dashboard.php', 'file' => 'dashboard.php', 'dir' => 'patient'],
            ['section' => 'My Health'],
            ['label' => 'Appointments', 'icon' => 'fa-calendar-check', 'url' => '/patient/appointments.php', 'file' => 'appointments.php', 'dir' => 'patient'],
            ['label' => 'Medical Records', 'icon' => 'fa-file-medical', 'url' => '/patient/medical_records.php', 'file' => 'medical_records.php', 'dir' => 'patient'],
            ['label' => 'Prescriptions', 'icon' => 'fa-prescription', 'url' => '/patient/prescriptions.php', 'file' => 'prescriptions.php', 'dir' => 'patient'],
            ['label' => 'Lab Reports', 'icon' => 'fa-flask', 'url' => '/patient/lab_reports.php', 'file' => 'lab_reports.php', 'dir' => 'patient'],
            ['section' => 'Account'],
            ['label' => 'My Bills', 'icon' => 'fa-file-invoice-dollar', 'url' => '/patient/bills.php', 'file' => 'bills.php', 'dir' => 'patient'],
            ['label' => 'My Profile', 'icon' => 'fa-user', 'url' => '/patient/profile.php', 'file' => 'profile.php', 'dir' => 'patient'],
        ];
        break;

    case 'pharmacist':
        $rxFetch = $db->query("SELECT COUNT(*) as c FROM prescriptions WHERE status = 'pending'")->fetch();
        $pendingRx = (int)($rxFetch['c'] ?? 0);
        $stockFetch = $db->query("SELECT COUNT(*) as c FROM pharmacy_inventory WHERE stock_quantity <= reorder_level AND status = 'active'")->fetch();
        $lowStock = (int)($stockFetch['c'] ?? 0);
        
        $navItems = [
            ['section' => 'Main'],
            ['label' => 'Dashboard', 'icon' => 'fa-chart-pie', 'url' => '/pharmacy/dashboard.php', 'file' => 'dashboard.php', 'dir' => 'pharmacy'],
            ['section' => 'Pharmacy'],
            ['label' => 'Dispense', 'icon' => 'fa-hand-holding-medical', 'url' => '/pharmacy/dispense.php', 'file' => 'dispense.php', 'dir' => 'pharmacy', 'badge' => $pendingRx > 0 ? $pendingRx : null],
            ['label' => 'Inventory', 'icon' => 'fa-boxes-stacked', 'url' => '/pharmacy/inventory.php', 'file' => 'inventory.php', 'dir' => 'pharmacy'],
            ['label' => 'Add Medicine', 'icon' => 'fa-plus-circle', 'url' => '/pharmacy/add_medicine.php', 'file' => 'add_medicine.php', 'dir' => 'pharmacy'],
            ['label' => 'Stock Alerts', 'icon' => 'fa-triangle-exclamation', 'url' => '/pharmacy/stock_alerts.php', 'file' => 'stock_alerts.php', 'dir' => 'pharmacy', 'badge' => $lowStock > 0 ? $lowStock : null],
        ];
        break;

    case 'lab_technician':
        $labFetch = $db->query("SELECT COUNT(*) as c FROM lab_orders WHERE status IN ('ordered','sample_collected','processing')")->fetch();
        $pendingTests = (int)($labFetch['c'] ?? 0);
        
        $navItems = [
            ['section' => 'Main'],
            ['label' => 'Dashboard', 'icon' => 'fa-chart-pie', 'url' => '/lab/dashboard.php', 'file' => 'dashboard.php', 'dir' => 'lab'],
            ['section' => 'Lab Operations'],
            ['label' => 'Test Orders', 'icon' => 'fa-clipboard-list', 'url' => '/lab/test_orders.php', 'file' => 'test_orders.php', 'dir' => 'lab', 'badge' => $pendingTests > 0 ? $pendingTests : null],
            ['label' => 'Collect Sample', 'icon' => 'fa-vial', 'url' => '/lab/collect_sample.php', 'file' => 'collect_sample.php', 'dir' => 'lab'],
            ['label' => 'Upload Results', 'icon' => 'fa-upload', 'url' => '/lab/upload_result.php', 'file' => 'upload_result.php', 'dir' => 'lab'],
            ['label' => 'Test Catalog', 'icon' => 'fa-book-medical', 'url' => '/lab/test_catalog.php', 'file' => 'test_catalog.php', 'dir' => 'lab'],
        ];
        break;
}
?>

<!-- Sidebar -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <a href="/" class="sidebar-logo">
            <div class="logo-icon"><i class="fas fa-hospital"></i></div>
            <span><?= APP_NAME ?></span>
        </a>
    </div>
    
    <nav class="sidebar-nav">
        <?php foreach ($navItems as $item): ?>
            <?php if (isset($item['section'])): ?>
                <div class="nav-section">
                    <div class="nav-section-title"><?= $item['section'] ?></div>
                </div>
            <?php else: ?>
                <div class="nav-section">
                    <a href="<?= $item['url'] ?>" class="nav-link <?= ($currentPage === $item['file'] && $currentDir === $item['dir']) ? 'active' : '' ?>">
                        <i class="fas <?= $item['icon'] ?>"></i>
                        <span><?= $item['label'] ?></span>
                        <?php if (!empty($item['badge'])): ?>
                        <span class="badge-count"><?= $item['badge'] ?></span>
                        <?php endif; ?>
                    </a>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
    </nav>
    
    <div class="sidebar-footer">
        <div class="sidebar-user">
            <div class="user-avatar" style="background: <?= $roleColor ?>;">
                <?= strtoupper(substr($currentUser['full_name'], 0, 1)) ?>
            </div>
            <div class="user-info">
                <div class="user-name"><?= sanitize($currentUser['full_name']) ?></div>
                <div class="user-role"><i class="fas <?= $roleIcon ?>"></i> <?= $roleLabel ?></div>
            </div>
        </div>
    </div>
</aside>
