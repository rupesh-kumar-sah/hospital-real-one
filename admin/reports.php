<?php
/**
 * Hospital Management System — Admin: Analytics & Reports
 */

require_once __DIR__ . '/../includes/auth_middleware.php';
requireRole('admin');

$pageTitle = 'Analytics & Reports';
$breadcrumbs = [['label' => 'Dashboard', 'url' => '/admin/dashboard.php'], ['label' => 'Reports']];

$db = getDB();

// Monthly revenue breakdown
$monthlyRevenue = $db->query("
    SELECT strftime('%Y-%m', created_at) as month, SUM(net_amount) as total
    FROM billing
    WHERE payment_status = 'paid'
    GROUP BY month
    ORDER BY month DESC
    LIMIT 6
")->fetchAll();

// Top doctors by patient count
$topDoctors = $db->query("
    SELECT u.full_name, dep.name as dept_name, COUNT(a.id) as appt_count
    FROM doctors d
    JOIN users u ON d.user_id = u.id
    LEFT JOIN departments dep ON d.department_id = dep.id
    LEFT JOIN appointments a ON a.doctor_id = d.id
    GROUP BY d.id
    ORDER BY appt_count DESC
    LIMIT 5
")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Hospital Analytics & Reports</h1>
        <p class="page-subtitle">Detailed reporting on revenue, doctor performance, and hospital usage</p>
    </div>
    <button class="btn btn-secondary" onclick="window.print()">
        <i class="fas fa-print"></i> Print Report
    </button>
</div>

<div class="grid-2 mb-24">
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-chart-line text-primary"></i> Monthly Revenue Trend</h3>
        </div>
        <div class="card-body">
            <div class="chart-container">
                <canvas id="monthlyRevChart"></canvas>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-trophy text-warning"></i> Top Performing Doctors</h3>
        </div>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Doctor</th>
                        <th>Department</th>
                        <th>Patients Consulted</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($topDoctors as $doc): ?>
                    <tr>
                        <td><strong><?= sanitize($doc['full_name']) ?></strong></td>
                        <td><?= sanitize($doc['dept_name'] ?? 'N/A') ?></td>
                        <td><span class="badge badge-success"><?= $doc['appt_count'] ?> patients</span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
const monthlyRev = <?= json_encode(array_reverse($monthlyRevenue)) ?>;
const revCtx = document.getElementById('monthlyRevChart').getContext('2d');
new Chart(revCtx, {
    type: 'line',
    data: {
        labels: monthlyRev.map(m => m.month),
        datasets: [{
            label: 'Revenue (Rs.)',
            data: monthlyRev.map(m => m.total),
            borderColor: '#0066CC',
            backgroundColor: 'rgba(0,102,204,0.1)',
            fill: true,
            tension: 0.3
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false
    }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
