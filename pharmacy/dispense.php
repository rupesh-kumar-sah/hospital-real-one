<?php
/**
 * Hospital Management System — Pharmacy: Dispense Medicine
 */

require_once __DIR__ . '/../includes/auth_middleware.php';
requireRole(['pharmacist', 'admin']);

$pageTitle = 'Dispense Prescriptions';
$breadcrumbs = [['label' => 'Dashboard', 'url' => '/pharmacy/dashboard.php'], ['label' => 'Dispense']];

$db = getDB();

if (isset($_GET['dispense_id'])) {
    $rxId = (int)$_GET['dispense_id'];
    
    $db->beginTransaction();
    try {
        // Mark prescription dispensed
        $stmt = $db->prepare("UPDATE prescriptions SET status = 'dispensed' WHERE id = ?");
        $stmt->execute([$rxId]);

        // Get prescription items
        $items = $db->query("SELECT * FROM prescription_items WHERE prescription_id = {$rxId}")->fetchAll();
        $rx = $db->query("SELECT patient_id, doctor_id FROM prescriptions WHERE id = {$rxId}")->fetch();

        foreach ($items as $item) {
            // Log dispensing
            $logStmt = $db->prepare("INSERT INTO pharmacy_dispensing (prescription_id, prescription_item_id, drug_name, quantity_dispensed, pharmacist_id, dispensed_at) VALUES (?, ?, ?, 1, ?, CURRENT_TIMESTAMP)");
            $logStmt->execute([$rxId, $item['id'], $item['drug_name'], getUserId()]);

            // Deduct inventory if matching drug found by name
            $deductStmt = $db->prepare("UPDATE pharmacy_inventory SET stock_quantity = MAX(0, stock_quantity - 1) WHERE drug_name LIKE ?");
            $deductStmt->execute(['%' . $item['drug_name'] . '%']);
        }

        // Notify patient user
        if ($rx && isset($rx['patient_id'])) {
            $patientUser = $db->query("SELECT user_id FROM patients WHERE id = {$rx['patient_id']}")->fetch();
            if ($patientUser) {
                createNotification($patientUser['user_id'], 'Prescription Dispensed', "Your prescription #Rx-{$rxId} has been dispensed by the pharmacy.", 'prescription');
            }
        }

        $db->commit();
        logAudit('update', 'prescriptions', $rxId, "Dispensed prescription #Rx-{$rxId}");
        setFlash('success', "Prescription #Rx-{$rxId} successfully dispensed! Inventory stock updated.");
    } catch (Exception $e) {
        $db->rollBack();
        setFlash('error', "Dispensing failed: " . $e->getMessage());
    }

    header('Location: /pharmacy/dispense.php');
    exit;
}

$prescriptions = $db->query("
    SELECT pr.*, p.uhid, u_p.full_name as patient_name, u_d.full_name as doctor_name
    FROM prescriptions pr
    JOIN patients p ON pr.patient_id = p.id
    JOIN users u_p ON p.user_id = u_p.id
    JOIN doctors d ON pr.doctor_id = d.id
    JOIN users u_d ON d.user_id = u_d.id
    ORDER BY pr.status DESC, pr.created_at DESC
")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Prescription Dispensing Desk</h1>
        <p class="page-subtitle">Verify prescriptions, check inventory stock, and dispense medicines to patients</p>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Rx ID</th>
                    <th>Patient</th>
                    <th>Doctor</th>
                    <th>Prescribed Medicines</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($prescriptions as $rx): ?>
                <?php $items = $db->query("SELECT * FROM prescription_items WHERE prescription_id = {$rx['id']}")->fetchAll(); ?>
                <tr>
                    <td><strong>#Rx-<?= $rx['id'] ?></strong></td>
                    <td><strong><?= sanitize($rx['patient_name']) ?></strong><br><code class="text-xs"><?= sanitize($rx['uhid']) ?></code></td>
                    <td>Dr. <?= sanitize($rx['doctor_name']) ?></td>
                    <td>
                        <ul style="padding-left: 16px; margin: 0; font-size: 0.8125rem;">
                            <?php foreach ($items as $it): ?>
                            <li><strong><?= sanitize($it['drug_name']) ?></strong> - <?= sanitize($it['dosage']) ?> (<?= sanitize($it['frequency']) ?> x <?= sanitize($it['duration']) ?>)</li>
                            <?php endforeach; ?>
                        </ul>
                    </td>
                    <td><span class="badge <?= $rx['status'] === 'dispensed' ? 'badge-success' : 'badge-warning' ?>"><?= ucfirst($rx['status']) ?></span></td>
                    <td>
                        <?php if ($rx['status'] !== 'dispensed'): ?>
                        <a href="/pharmacy/dispense.php?dispense_id=<?= $rx['id'] ?>" class="btn btn-sm btn-success">
                            <i class="fas fa-check"></i> Mark Dispensed
                        </a>
                        <?php else: ?>
                        <span class="text-xs text-muted">Dispensed</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
