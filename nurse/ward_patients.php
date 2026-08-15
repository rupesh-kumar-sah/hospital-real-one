<?php
/**
 * Hospital Management System — Nurse: Ward Patients List
 */

require_once __DIR__ . '/../includes/auth_middleware.php';
requireRole(['nurse', 'admin', 'doctor']);

$pageTitle = 'Ward Patients Directory';
$breadcrumbs = [['label' => 'Dashboard', 'url' => '/nurse/dashboard.php'], ['label' => 'Ward Patients']];

$db = getDB();

$patients = $db->query("
    SELECT a.*, p.uhid, u_p.full_name as patient_name, p.date_of_birth, p.gender, p.allergies,
           u_d.full_name as doctor_name, b.bed_number, w.name as ward_name
    FROM admissions a
    JOIN patients p ON a.patient_id = p.id
    JOIN users u_p ON p.user_id = u_p.id
    JOIN doctors d ON a.doctor_id = d.id
    JOIN users u_d ON d.user_id = u_d.id
    LEFT JOIN beds b ON a.bed_id = b.id
    LEFT JOIN wards w ON a.ward_id = w.id
    WHERE a.status = 'admitted'
    ORDER BY w.name, b.bed_number
")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Inpatient Ward Directory</h1>
        <p class="page-subtitle">List of currently admitted patients in all hospital wards</p>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Ward / Bed</th>
                    <th>Patient Name</th>
                    <th>UHID</th>
                    <th>Admitting Doctor</th>
                    <th>Admit Date</th>
                    <th>Allergies</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($patients as $pt): ?>
                <tr>
                    <td><strong><?= sanitize($pt['ward_name']) ?></strong><br><span class="badge badge-info">Bed <?= sanitize($pt['bed_number']) ?></span></td>
                    <td><strong><?= sanitize($pt['patient_name']) ?></strong></td>
                    <td><code><?= sanitize($pt['uhid']) ?></code></td>
                    <td>Dr. <?= sanitize($pt['doctor_name']) ?></td>
                    <td><?= formatDate($pt['admit_date']) ?></td>
                    <td><span class="text-danger"><?= sanitize($pt['allergies'] ?: 'None') ?></span></td>
                    <td>
                        <a href="/nurse/vitals.php?patient_id=<?= $pt['patient_id'] ?>&admission_id=<?= $pt['id'] ?>" class="btn btn-sm btn-primary"><i class="fas fa-heartbeat"></i> Vitals</a>
                        <a href="/nurse/medication.php?admission_id=<?= $pt['id'] ?>" class="btn btn-sm btn-warning"><i class="fas fa-pills"></i> MAR</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
