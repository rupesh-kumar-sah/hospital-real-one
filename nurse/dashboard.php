<?php
/**
 * Hospital Management System — Nurse Dashboard
 */

require_once __DIR__ . '/../includes/auth_middleware.php';
requireRole('nurse');

$pageTitle = 'Nurse Dashboard';
$breadcrumbs = [['label' => 'Dashboard']];

$db = getDB();
$nurse = getNurseByUserId(getUserId());
$nurseId = $nurse['id'] ?? 0;
$wardId = $nurse['ward_id'] ?? 0;

// Patients in assigned ward
$wardPatients = $db->prepare("
    SELECT a.*, p.uhid, u_p.full_name as patient_name, p.date_of_birth, p.gender, p.allergies,
           u_d.full_name as doctor_name, b.bed_number, w.name as ward_name
    FROM admissions a
    JOIN patients p ON a.patient_id = p.id
    JOIN users u_p ON p.user_id = u_p.id
    JOIN doctors d ON a.doctor_id = d.id
    JOIN users u_d ON d.user_id = u_d.id
    LEFT JOIN beds b ON a.bed_id = b.id
    LEFT JOIN wards w ON a.ward_id = w.id
    WHERE a.status = 'admitted' AND (a.ward_id = ? OR ? = 0)
    ORDER BY a.admit_date DESC
");
$wardPatients->execute([$wardId, $wardId]);
$patients = $wardPatients->fetchAll();

// Pending medication
$pendingMeds = $db->prepare("
    SELECT COUNT(*) as c FROM medication_administration 
    WHERE nurse_id = ? AND status = 'pending'
");
$pendingMeds->execute([$nurseId]);
$pendingMedCount = $pendingMeds->fetch()['c'];

// Vitals due (simplified: patients without vitals in last 4 hours)
$vitalsDue = count($patients); // simplified

// Urgent nursing notes
$urgentNotes = $db->prepare("
    SELECT nn.*, u_p.full_name as patient_name, b.bed_number
    FROM nursing_notes nn
    JOIN patients p ON nn.patient_id = p.id
    JOIN users u_p ON p.user_id = u_p.id
    LEFT JOIN admissions a ON nn.admission_id = a.id
    LEFT JOIN beds b ON a.bed_id = b.id
    WHERE nn.priority IN ('urgent','critical')
    ORDER BY nn.created_at DESC
    LIMIT 5
");
$urgentNotes->execute();
$urgentNotesList = $urgentNotes->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="stats-grid">
    <div class="stat-card" style="--stat-color: #3b82f6; --stat-color-light: #dbeafe;">
        <div class="stat-info">
            <h3>Ward Patients</h3>
            <div class="stat-number"><?= count($patients) ?></div>
            <div class="stat-change"><i class="fas fa-hospital"></i> <?= sanitize($nurse['ward_name'] ?? 'All Wards') ?></div>
        </div>
        <div class="stat-icon"><i class="fas fa-bed"></i></div>
    </div>
    
    <div class="stat-card" style="--stat-color: #f59e0b; --stat-color-light: #fef3c7;">
        <div class="stat-info">
            <h3>Pending Medication</h3>
            <div class="stat-number"><?= $pendingMedCount ?></div>
        </div>
        <div class="stat-icon"><i class="fas fa-pills"></i></div>
    </div>
    
    <div class="stat-card" style="--stat-color: #ef4444; --stat-color-light: #fee2e2;">
        <div class="stat-info">
            <h3>Vitals Due</h3>
            <div class="stat-number"><?= $vitalsDue ?></div>
        </div>
        <div class="stat-icon"><i class="fas fa-heartbeat"></i></div>
    </div>
    
    <div class="stat-card" style="--stat-color: #8b5cf6; --stat-color-light: #ede9fe;">
        <div class="stat-info">
            <h3>Shift</h3>
            <div class="stat-number"><?= ucfirst($nurse['shift'] ?? 'N/A') ?></div>
        </div>
        <div class="stat-icon"><i class="fas fa-clock"></i></div>
    </div>
</div>

<!-- Quick Actions -->
<div class="card" style="margin-bottom: 24px;">
    <div class="card-header">
        <h3><i class="fas fa-bolt" style="color: var(--warning);"></i> Quick Actions</h3>
    </div>
    <div class="card-body">
        <div class="quick-actions">
            <a href="/nurse/vitals.php" class="quick-action-btn">
                <i class="fas fa-heartbeat"></i>
                <span>Record Vitals</span>
            </a>
            <a href="/nurse/medication.php" class="quick-action-btn">
                <i class="fas fa-pills"></i>
                <span>Administer Med</span>
            </a>
            <a href="/nurse/nursing_notes.php" class="quick-action-btn">
                <i class="fas fa-notes-medical"></i>
                <span>Add Notes</span>
            </a>
            <a href="/nurse/bed_management.php" class="quick-action-btn">
                <i class="fas fa-bed"></i>
                <span>Bed Status</span>
            </a>
        </div>
    </div>
</div>

<!-- Ward Patients -->
<div class="card" style="margin-bottom: 24px;">
    <div class="card-header">
        <h3><i class="fas fa-bed" style="color: var(--primary);"></i> Ward Patients</h3>
    </div>
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Bed</th>
                    <th>Patient</th>
                    <th>UHID</th>
                    <th>Age/Gender</th>
                    <th>Doctor</th>
                    <th>Admitted</th>
                    <th>Allergies</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($patients)): ?>
                <tr><td colspan="8"><div class="empty-state"><i class="fas fa-bed"></i><p>No patients in ward</p></div></td></tr>
                <?php else: ?>
                <?php foreach ($patients as $pt): ?>
                <tr>
                    <td><span class="badge badge-info"><?= sanitize($pt['bed_number'] ?? 'N/A') ?></span></td>
                    <td>
                        <div class="info-card">
                            <div class="avatar avatar-sm" style="background: var(--danger);"><?= strtoupper(substr($pt['patient_name'], 0, 1)) ?></div>
                            <div class="info-details"><h4><?= sanitize($pt['patient_name']) ?></h4></div>
                        </div>
                    </td>
                    <td><code><?= sanitize($pt['uhid']) ?></code></td>
                    <td><?= calculateAge($pt['date_of_birth']) ?> / <?= ucfirst($pt['gender'] ?? '') ?></td>
                    <td><?= sanitize($pt['doctor_name']) ?></td>
                    <td><?= formatDate($pt['admit_date']) ?></td>
                    <td>
                        <?php if ($pt['allergies']): ?>
                        <span class="badge badge-danger"><i class="fas fa-exclamation-triangle"></i> <?= sanitize($pt['allergies']) ?></span>
                        <?php else: ?>
                        <span class="text-muted">None</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="btn-group">
                            <a href="/nurse/vitals.php?patient_id=<?= $pt['patient_id'] ?>&admission_id=<?= $pt['id'] ?>" class="btn btn-sm btn-primary" data-tooltip="Record Vitals"><i class="fas fa-heartbeat"></i></a>
                            <a href="/nurse/medication.php?admission_id=<?= $pt['id'] ?>" class="btn btn-sm btn-warning" data-tooltip="Medication"><i class="fas fa-pills"></i></a>
                            <a href="/nurse/nursing_notes.php?admission_id=<?= $pt['id'] ?>" class="btn btn-sm btn-secondary" data-tooltip="Notes"><i class="fas fa-note-sticky"></i></a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Urgent Notes -->
<?php if (!empty($urgentNotesList)): ?>
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-exclamation-triangle" style="color: var(--danger);"></i> Urgent/Critical Notes</h3>
    </div>
    <div class="card-body">
        <?php foreach ($urgentNotesList as $note): ?>
        <div class="alert alert-<?= $note['priority'] === 'critical' ? 'danger' : 'warning' ?>">
            <i class="fas fa-<?= $note['priority'] === 'critical' ? 'exclamation-circle' : 'exclamation-triangle' ?>"></i>
            <div>
                <strong><?= sanitize($note['patient_name']) ?> (Bed: <?= sanitize($note['bed_number'] ?? 'N/A') ?>)</strong><br>
                <?= sanitize($note['note']) ?>
                <div class="text-xs mt-4"><?= timeAgo($note['created_at']) ?></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
