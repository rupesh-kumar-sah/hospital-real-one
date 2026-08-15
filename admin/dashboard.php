<?php
/**
 * Hospital Management System — Admin Dashboard
 * Analytics, stats, and hospital overview
 */

require_once __DIR__ . '/../includes/auth_middleware.php';
requireRole('admin');

$pageTitle = 'Admin Dashboard';
$breadcrumbs = [['label' => 'Dashboard']];

$db = getDB();

$todayDate = date('Y-m-d');

// === STATS ===
// Today's appointments
$todayAppts = $db->query("SELECT COUNT(*) as c FROM appointments WHERE appointment_date = '{$todayDate}' OR appointment_date = DATE('now')")->fetch()['c'];
$todayCompleted = $db->query("SELECT COUNT(*) as c FROM appointments WHERE (appointment_date = '{$todayDate}' OR appointment_date = DATE('now')) AND status = 'completed'")->fetch()['c'];

// Total patients
$totalPatients = $db->query("SELECT COUNT(*) as c FROM patients")->fetch()['c'];
$newPatientsToday = $db->query("SELECT COUNT(*) as c FROM patients WHERE DATE(created_at) = '{$todayDate}' OR DATE(created_at) = DATE('now')")->fetch()['c'];

// Active admissions
$activeAdmissions = $db->query("SELECT COUNT(*) as c FROM admissions WHERE status = 'admitted'")->fetch()['c'];

// Total doctors
$totalDoctors = $db->query("SELECT COUNT(*) as c FROM doctors d JOIN users u ON d.user_id = u.id WHERE u.status = 'active'")->fetch()['c'];

// Bed occupancy
$totalBeds = $db->query("SELECT COUNT(*) as c FROM beds WHERE status != 'maintenance'")->fetch()['c'];
$occupiedBeds = $db->query("SELECT COUNT(*) as c FROM beds WHERE status = 'occupied'")->fetch()['c'];
$bedOccupancyRate = $totalBeds > 0 ? round(($occupiedBeds / $totalBeds) * 100) : 0;

// Revenue today
$revenueToday = $db->query("SELECT COALESCE(SUM(net_amount), 0) as total FROM billing WHERE (DATE(created_at) = '{$todayDate}' OR DATE(created_at) = DATE('now')) AND payment_status = 'paid'")->fetch()['total'];

// Revenue this month
$revenueMonth = $db->query("SELECT COALESCE(SUM(net_amount), 0) as total FROM billing WHERE strftime('%Y-%m', created_at) = strftime('%Y-%m', 'now') AND payment_status = 'paid'")->fetch()['total'];

// Pending bills
$pendingBills = $db->query("SELECT COUNT(*) as c FROM billing WHERE payment_status = 'unpaid'")->fetch()['c'];

// Recent appointments
$recentAppts = $db->query("
    SELECT a.*, p.uhid, u_p.full_name as patient_name, u_d.full_name as doctor_name, dep.name as dept_name
    FROM appointments a
    JOIN patients p ON a.patient_id = p.id
    JOIN users u_p ON p.user_id = u_p.id
    JOIN doctors d ON a.doctor_id = d.id
    JOIN users u_d ON d.user_id = u_d.id
    LEFT JOIN departments dep ON a.department_id = dep.id
    ORDER BY a.appointment_date DESC, a.appointment_time DESC
    LIMIT 10
")->fetchAll();

// Department-wise appointments today
$deptAppts = $db->query("
    SELECT dep.name, COUNT(a.id) as count
    FROM departments dep
    LEFT JOIN appointments a ON a.department_id = dep.id AND a.appointment_date = DATE('now')
    WHERE dep.status = 'active'
    GROUP BY dep.id, dep.name
    ORDER BY count DESC
    LIMIT 8
")->fetchAll();

// Recent activity (audit logs)
$recentActivity = $db->query("
    SELECT * FROM audit_logs ORDER BY created_at DESC LIMIT 8
")->fetchAll();

// Pharmacy alerts
$lowStockCount = $db->query("SELECT COUNT(*) as c FROM pharmacy_inventory WHERE stock_quantity <= reorder_level AND status = 'active'")->fetch()['c'];

// Pending lab tests
$pendingLabTests = $db->query("SELECT COUNT(*) as c FROM lab_orders WHERE status IN ('ordered','sample_collected','processing')")->fetch()['c'];

// Pending prescriptions
$pendingRx = $db->query("SELECT COUNT(*) as c FROM prescriptions WHERE status = 'pending'")->fetch()['c'];

include __DIR__ . '/../includes/header.php';
?>

<!-- Stats Grid -->
<div class="stats-grid">
    <div class="stat-card" style="--stat-color: #3b82f6; --stat-color-light: #dbeafe;">
        <div class="stat-info">
            <h3>Today's Appointments</h3>
            <div class="stat-number"><?= $todayAppts ?></div>
            <div class="stat-change positive">
                <i class="fas fa-check-circle"></i> <?= $todayCompleted ?> completed
            </div>
        </div>
        <div class="stat-icon"><i class="fas fa-calendar-check"></i></div>
    </div>
    
    <div class="stat-card" style="--stat-color: #10b981; --stat-color-light: #d1fae5;">
        <div class="stat-info">
            <h3>Total Patients</h3>
            <div class="stat-number"><?= $totalPatients ?></div>
            <div class="stat-change positive">
                <i class="fas fa-arrow-up"></i> +<?= $newPatientsToday ?> today
            </div>
        </div>
        <div class="stat-icon"><i class="fas fa-users"></i></div>
    </div>
    
    <div class="stat-card" style="--stat-color: #f59e0b; --stat-color-light: #fef3c7;">
        <div class="stat-info">
            <h3>Active Admissions</h3>
            <div class="stat-number"><?= $activeAdmissions ?></div>
            <div class="stat-change">
                <i class="fas fa-bed"></i> <?= $occupiedBeds ?>/<?= $totalBeds ?> beds
            </div>
        </div>
        <div class="stat-icon"><i class="fas fa-procedures"></i></div>
    </div>
    
    <div class="stat-card" style="--stat-color: #8b5cf6; --stat-color-light: #ede9fe;">
        <div class="stat-info">
            <h3>Revenue (Today)</h3>
            <div class="stat-number"><?= formatCurrency($revenueToday) ?></div>
            <div class="stat-change">
                <i class="fas fa-chart-line"></i> This month: <?= formatCurrency($revenueMonth) ?>
            </div>
        </div>
        <div class="stat-icon"><i class="fas fa-indian-rupee-sign"></i></div>
    </div>
</div>

<!-- Second row stats -->
<div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));">
    <div class="stat-card" style="--stat-color: #06b6d4; --stat-color-light: #cffafe;">
        <div class="stat-info">
            <h3>Active Doctors</h3>
            <div class="stat-number"><?= $totalDoctors ?></div>
        </div>
        <div class="stat-icon"><i class="fas fa-user-doctor"></i></div>
    </div>
    
    <div class="stat-card" style="--stat-color: <?= $bedOccupancyRate > 80 ? '#ef4444' : '#10b981' ?>; --stat-color-light: <?= $bedOccupancyRate > 80 ? '#fee2e2' : '#d1fae5' ?>;">
        <div class="stat-info">
            <h3>Bed Occupancy</h3>
            <div class="stat-number"><?= $bedOccupancyRate ?>%</div>
        </div>
        <div class="stat-icon"><i class="fas fa-bed"></i></div>
    </div>
    
    <div class="stat-card" style="--stat-color: #ef4444; --stat-color-light: #fee2e2;">
        <div class="stat-info">
            <h3>Pending Bills</h3>
            <div class="stat-number"><?= $pendingBills ?></div>
        </div>
        <div class="stat-icon"><i class="fas fa-file-invoice-dollar"></i></div>
    </div>
    
    <div class="stat-card" style="--stat-color: #f59e0b; --stat-color-light: #fef3c7;">
        <div class="stat-info">
            <h3>Low Stock Drugs</h3>
            <div class="stat-number"><?= $lowStockCount ?></div>
        </div>
        <div class="stat-icon"><i class="fas fa-triangle-exclamation"></i></div>
    </div>
    
    <div class="stat-card" style="--stat-color: #06b6d4; --stat-color-light: #cffafe;">
        <div class="stat-info">
            <h3>Pending Lab Tests</h3>
            <div class="stat-number"><?= $pendingLabTests ?></div>
        </div>
        <div class="stat-icon"><i class="fas fa-flask"></i></div>
    </div>
</div>

<!-- Charts Row -->
<div class="grid-2" style="margin-bottom: 24px;">
    <!-- Department-wise Appointments Chart -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-chart-bar" style="color: var(--primary);"></i> Department-wise Appointments (Today)</h3>
        </div>
        <div class="card-body">
            <div class="chart-container">
                <canvas id="deptChart"></canvas>
            </div>
        </div>
    </div>
    
    <!-- Bed Occupancy Chart -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-chart-pie" style="color: var(--accent);"></i> Bed Occupancy Overview</h3>
        </div>
        <div class="card-body">
            <div class="chart-container">
                <canvas id="bedChart"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="card" style="margin-bottom: 24px;">
    <div class="card-header">
        <h3><i class="fas fa-bolt" style="color: var(--warning);"></i> Quick Actions</h3>
    </div>
    <div class="card-body">
        <div class="quick-actions">
            <a href="/admin/manage_users.php" class="quick-action-btn">
                <i class="fas fa-users"></i>
                <span>Manage Users</span>
            </a>
            <a href="/admin/manage_departments.php" class="quick-action-btn">
                <i class="fas fa-building"></i>
                <span>Departments</span>
            </a>
            <a href="/admin/manage_wards.php" class="quick-action-btn">
                <i class="fas fa-bed"></i>
                <span>Wards & Beds</span>
            </a>
            <a href="/admin/reports.php" class="quick-action-btn">
                <i class="fas fa-chart-bar"></i>
                <span>Reports</span>
            </a>
            <a href="/admin/audit_logs.php" class="quick-action-btn">
                <i class="fas fa-clipboard-list"></i>
                <span>Audit Logs</span>
            </a>
            <a href="/admin/manage_pricing.php" class="quick-action-btn">
                <i class="fas fa-tags"></i>
                <span>Service Pricing</span>
            </a>
        </div>
    </div>
</div>

<!-- Recent Appointments Table -->
<div class="card" style="margin-bottom: 24px;">
    <div class="card-header">
        <h3><i class="fas fa-calendar" style="color: var(--info);"></i> Recent Appointments</h3>
        <a href="/receptionist/appointments.php" class="btn btn-sm btn-secondary">View All</a>
    </div>
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Patient</th>
                    <th>UHID</th>
                    <th>Doctor</th>
                    <th>Department</th>
                    <th>Date & Time</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recentAppts)): ?>
                <tr>
                    <td colspan="6">
                        <div class="empty-state">
                            <i class="fas fa-calendar-xmark"></i>
                            <p>No appointments found</p>
                        </div>
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($recentAppts as $appt): ?>
                <tr>
                    <td>
                        <div class="info-card">
                            <div class="avatar avatar-sm" style="background: var(--primary);"><?= strtoupper(substr($appt['patient_name'], 0, 1)) ?></div>
                            <div class="info-details">
                                <h4><?= sanitize($appt['patient_name']) ?></h4>
                            </div>
                        </div>
                    </td>
                    <td><code style="font-size: 0.8125rem; color: var(--primary);"><?= sanitize($appt['uhid']) ?></code></td>
                    <td><?= sanitize($appt['doctor_name']) ?></td>
                    <td><?= sanitize($appt['dept_name'] ?? 'N/A') ?></td>
                    <td>
                        <div><?= formatDate($appt['appointment_date']) ?></div>
                        <div class="text-sm text-muted"><?= formatTime($appt['appointment_time']) ?></div>
                    </td>
                    <td><?= statusBadge($appt['status'], APPOINTMENT_STATUSES) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Recent Activity -->
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-history" style="color: var(--success);"></i> Recent Activity</h3>
        <a href="/admin/audit_logs.php" class="btn btn-sm btn-secondary">View All</a>
    </div>
    <div class="card-body">
        <?php if (empty($recentActivity)): ?>
        <div class="empty-state">
            <i class="fas fa-clipboard-list"></i>
            <p>No activity logged yet</p>
        </div>
        <?php else: ?>
        <div class="timeline">
            <?php foreach ($recentActivity as $activity): ?>
            <div class="timeline-item">
                <div class="timeline-time"><?= timeAgo($activity['created_at']) ?></div>
                <div class="timeline-content">
                    <strong><?= sanitize($activity['user_name'] ?? 'System') ?></strong>
                    <?= sanitize($activity['description'] ?? ($activity['action'] . ' on ' . $activity['table_name'])) ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Department Chart
const deptData = <?= json_encode($deptAppts) ?>;
const deptCtx = document.getElementById('deptChart').getContext('2d');
new Chart(deptCtx, {
    type: 'bar',
    data: {
        labels: deptData.map(d => d.name),
        datasets: [{
            label: 'Appointments',
            data: deptData.map(d => d.count),
            backgroundColor: [
                '#3b82f6', '#10b981', '#f59e0b', '#ef4444', 
                '#8b5cf6', '#06b6d4', '#ec4899', '#f97316'
            ],
            borderRadius: 6,
            barThickness: 28
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: { stepSize: 1, font: { family: 'Inter' } },
                grid: { color: '#f1f5f9' }
            },
            x: {
                ticks: { font: { family: 'Inter', size: 11 } },
                grid: { display: false }
            }
        }
    }
});

// Bed Occupancy Doughnut Chart
<?php
$availableBeds = $db->query("SELECT COUNT(*) as c FROM beds WHERE status = 'available'")->fetch()['c'];
$reservedBeds = $db->query("SELECT COUNT(*) as c FROM beds WHERE status = 'reserved'")->fetch()['c'];
$maintenanceBeds = $db->query("SELECT COUNT(*) as c FROM beds WHERE status = 'maintenance'")->fetch()['c'];
?>
const bedCtx = document.getElementById('bedChart').getContext('2d');
new Chart(bedCtx, {
    type: 'doughnut',
    data: {
        labels: ['Available', 'Occupied', 'Reserved', 'Maintenance'],
        datasets: [{
            data: [<?= $availableBeds ?>, <?= $occupiedBeds ?>, <?= $reservedBeds ?>, <?= $maintenanceBeds ?>],
            backgroundColor: ['#10b981', '#ef4444', '#f59e0b', '#6b7280'],
            borderWidth: 0,
            hoverOffset: 8
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '65%',
        plugins: {
            legend: {
                position: 'bottom',
                labels: { font: { family: 'Inter', size: 12 }, padding: 16, usePointStyle: true }
            }
        }
    }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
