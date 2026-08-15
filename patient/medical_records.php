<?php
/**
 * Hospital Management System — Patient Medical Records View
 */

require_once __DIR__ . '/../includes/auth_middleware.php';
requireRole('patient');

$pageTitle = 'My Medical Records';
$breadcrumbs = [['label' => 'Dashboard', 'url' => '/patient/dashboard.php'], ['label' => 'Medical Records']];

$db = getDB();
$patient = getPatientByUserId(getUserId());
$patientId = $patient['id'] ?? 0;

$records = $db->query("
    SELECT mr.*, u_d.full_name as doctor_name, d.specialization
    FROM medical_records mr
    JOIN doctors d ON mr.doctor_id = d.id
    JOIN users u_d ON d.user_id = u_d.id
    WHERE mr.patient_id = {$patientId}
    ORDER BY mr.created_at DESC
")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>My Electronic Health Records (EHR)</h1>
        <p class="page-subtitle">Read-only history of past consultation diagnoses and medical advice</p>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <?php if (empty($records)): ?>
        <div class="empty-state"><p>No medical records found</p></div>
        <?php else: ?>
        <div class="timeline">
            <?php foreach ($records as $r): ?>
            <div class="timeline-item">
                <div class="timeline-time"><?= formatDate($r['created_at']) ?> | Dr. <?= sanitize($r['doctor_name']) ?> (<?= sanitize($r['specialization']) ?>)</div>
                <div class="timeline-content">
                    <h3 class="text-primary mb-4">Diagnosis: <?= sanitize($r['diagnosis']) ?></h3>
                    <p class="text-sm"><strong>Symptoms:</strong> <?= sanitize($r['symptoms']) ?></p>
                    <p class="text-sm text-muted mt-4"><strong>Clinical Notes:</strong> <?= sanitize($r['clinical_notes'] ?: 'No notes') ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
