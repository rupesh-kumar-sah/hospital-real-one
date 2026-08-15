<?php
/**
 * Hospital Management System — Lab: Collect Sample
 */

require_once __DIR__ . '/../includes/auth_middleware.php';
requireRole(['lab_technician', 'admin']);

$pageTitle = 'Collect Specimen / Sample';
$breadcrumbs = [['label' => 'Dashboard', 'url' => '/lab/dashboard.php'], ['label' => 'Collect Sample']];

$db = getDB();

if (isset($_GET['id'])) {
    $orderId = (int)$_GET['id'];
    $stmt = $db->prepare("UPDATE lab_orders SET status = 'sample_collected' WHERE id = ?");
    $stmt->execute([$orderId]);
    setFlash('success', 'Sample collected and barcode logged!');
    header('Location: /lab/dashboard.php');
    exit;
}

$pendingSamples = $db->query("
    SELECT lo.*, lc.test_name, lc.sample_type, p.uhid, u_p.full_name as patient_name
    FROM lab_orders lo
    JOIN lab_test_catalog lc ON lo.test_id = lc.id
    JOIN patients p ON lo.patient_id = p.id
    JOIN users u_p ON p.user_id = u_p.id
    WHERE lo.status = 'ordered'
")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Specimen / Sample Collection Desk</h1>
        <p class="page-subtitle">Collect blood, urine, or swab samples from patients and log barcodes</p>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Patient</th>
                    <th>UHID</th>
                    <th>Required Sample</th>
                    <th>Test Name</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pendingSamples as $ps): ?>
                <tr>
                    <td><strong><?= sanitize($ps['patient_name']) ?></strong></td>
                    <td><code><?= sanitize($ps['uhid']) ?></code></td>
                    <td><span class="badge badge-info"><i class="fas fa-vial"></i> <?= sanitize($ps['sample_type'] ?: 'Blood') ?></span></td>
                    <td><?= sanitize($ps['test_name']) ?></td>
                    <td>
                        <a href="/lab/collect_sample.php?id=<?= $ps['id'] ?>" class="btn btn-sm btn-primary">
                            <i class="fas fa-vial"></i> Mark Sample Collected
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
