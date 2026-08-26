<?php
/**
 * Hospital Management System — Pharmacy: Dispense Medicine Desk
 */

require_once __DIR__ . '/../includes/auth_middleware.php';
requireRole(['pharmacist', 'admin']);

$pageTitle = 'Pharmacy Dispensing Desk';
$breadcrumbs = [['label' => 'Dashboard', 'url' => '/pharmacy/dashboard.php'], ['label' => 'Dispense']];

$db = getDB();

if (isset($_GET['dispense_id'])) {
    $rxId = (int)$_GET['dispense_id'];
    
    $db->beginTransaction();
    try {
        // Fetch prescription with patient and doctor details
        $stmtRx = $db->prepare("SELECT pr.*, p.user_id as patient_user_id FROM prescriptions pr JOIN patients p ON pr.patient_id = p.id WHERE pr.id = ?");
        $stmtRx->execute([$rxId]);
        $rx = $stmtRx->fetch();

        if ($rx) {
            // Mark prescription as dispensed
            $upd = $db->prepare("UPDATE prescriptions SET status = 'dispensed' WHERE id = ?");
            $upd->execute([$rxId]);

            // Get prescription items
            $items = $db->query("SELECT * FROM prescription_items WHERE prescription_id = {$rxId}")->fetchAll();
            
            $rxGrandTotal = 0;

            foreach ($items as $item) {
                // Find matching drug in inventory
                $invStmt = $db->prepare("SELECT * FROM pharmacy_inventory WHERE drug_name LIKE ? AND status = 'active' LIMIT 1");
                $invStmt->execute(['%' . $item['drug_name'] . '%']);
                $inv = $invStmt->fetch();

                $unitPrice = $inv ? (float)$inv['selling_price'] : 10.00;
                $qty = max(1, (int)($item['quantity'] ?: 10));
                $itemSubtotal = $unitPrice * $qty;
                $rxGrandTotal += $itemSubtotal;

                // Log dispensing
                $logStmt = $db->prepare("INSERT INTO pharmacy_dispensing (prescription_id, prescription_item_id, drug_id, drug_name, quantity_dispensed, pharmacist_id, dispensed_at) VALUES (?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)");
                $logStmt->execute([$rxId, $item['id'], $inv['id'] ?? null, $item['drug_name'], $qty, getUserId()]);

                // Deduct inventory stock
                if ($inv) {
                    $deductStmt = $db->prepare("UPDATE pharmacy_inventory SET stock_quantity = MAX(0, stock_quantity - ?) WHERE id = ?");
                    $deductStmt->execute([$qty, $inv['id']]);
                }
            }

            // Sync with Billing invoice
            if ($rxGrandTotal > 0) {
                $billStmt = $db->prepare("SELECT id, subtotal, net_amount FROM billing WHERE patient_id = ? AND payment_status = 'unpaid' ORDER BY id DESC LIMIT 1");
                $billStmt->execute([$rx['patient_id']]);
                $bill = $billStmt->fetch();

                if (!$bill) {
                    $invNum = generateInvoiceNumber();
                    $insBill = $db->prepare("INSERT INTO billing (patient_id, appointment_id, invoice_number, subtotal, net_amount, payment_status, created_by) VALUES (?, ?, ?, ?, ?, 'unpaid', ?)");
                    $insBill->execute([$rx['patient_id'], $rx['appointment_id'], $invNum, $rxGrandTotal, $rxGrandTotal, getUserId()]);
                    $billId = $db->lastInsertId();
                } else {
                    $billId = $bill['id'];
                    $newSubtotal = $bill['subtotal'] + $rxGrandTotal;
                    $updBill = $db->prepare("UPDATE billing SET subtotal = ?, net_amount = ? WHERE id = ?");
                    $updBill->execute([$newSubtotal, $newSubtotal, $billId]);
                }

                // Insert medicine billing line item
                $insItem = $db->prepare("INSERT INTO billing_items (bill_id, item_type, description, quantity, unit_price, total_price, reference_id) VALUES (?, 'medicine', ?, 1, ?, ?, ?)");
                $insItem->execute([$billId, "Pharmacy Medicines (#Rx-{$rxId})", $rxGrandTotal, $rxGrandTotal, $rxId]);
            }

            // Notify patient
            if (isset($rx['patient_user_id'])) {
                createNotification($rx['patient_user_id'], 'Prescription Dispensed', "Your prescription #Rx-{$rxId} has been dispensed by the pharmacy (Total: Rs. " . number_format($rxGrandTotal, 2) . ").", 'prescription');
            }

            $db->commit();
            logAudit('update', 'prescriptions', $rxId, "Dispensed prescription #Rx-{$rxId} (Total: Rs. {$rxGrandTotal})");
            setFlash('success', "Prescription #Rx-{$rxId} dispensed successfully! Bill generated: " . formatCurrency($rxGrandTotal));
        } else {
            $db->rollBack();
            setFlash('error', "Prescription not found.");
        }
    } catch (Exception $e) {
        $db->rollBack();
        setFlash('error', "Dispensing failed: " . $e->getMessage());
    }

    header('Location: /pharmacy/dispense.php');
    exit;
}

$prescriptions = $db->query("
    SELECT pr.*, p.uhid, p.blood_group, u_p.full_name as patient_name, u_p.phone as patient_phone, u_d.full_name as doctor_name
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
        <h1><i class="fas fa-pills text-warning"></i> Pharmacy Medicine Dispensing Desk</h1>
        <p class="page-subtitle">Verify doctor prescriptions, check unit prices, inventory stock, and calculate total medicine bill</p>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Rx ID</th>
                    <th>Patient Information (Receptionist)</th>
                    <th>Prescribing Doctor</th>
                    <th>Prescribed Medicines & Price Breakdown</th>
                    <th>Total Medicine Bill</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($prescriptions)): ?>
                <tr>
                    <td colspan="7">
                        <div class="empty-state">
                            <i class="fas fa-prescription-bottle-alt text-muted"></i>
                            <h3>No Prescriptions Available</h3>
                            <p>Doctor prescriptions will automatically appear here once issued.</p>
                        </div>
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($prescriptions as $rx): ?>
                <?php
                $items = $db->query("SELECT * FROM prescription_items WHERE prescription_id = {$rx['id']}")->fetchAll();
                $rxTotal = 0;
                ?>
                <tr>
                    <td>
                        <span class="badge badge-primary" style="font-size: 0.875rem;">
                            #Rx-<?= $rx['id'] ?>
                        </span>
                        <div class="text-xs text-muted mt-4"><?= formatDate($rx['created_at']) ?></div>
                    </td>
                    <td>
                        <strong><?= sanitize($rx['patient_name']) ?></strong><br>
                        <code class="text-xs"><?= sanitize($rx['uhid']) ?></code><br>
                        <span class="text-xs text-muted"><i class="fas fa-phone"></i> <?= sanitize($rx['patient_phone'] ?: 'N/A') ?></span>
                    </td>
                    <td>
                        <strong>Dr. <?= sanitize($rx['doctor_name']) ?></strong>
                    </td>
                    <td>
                        <table class="table-borderless text-xs" style="width: 100%; margin: 0;">
                            <thead>
                                <tr style="background: rgba(0,0,0,0.03); border-bottom: 1px solid var(--gray-200);">
                                    <th style="padding: 4px 6px;">Drug Name</th>
                                    <th style="padding: 4px 6px;">Dosage/Duration</th>
                                    <th style="padding: 4px 6px;">Unit Price</th>
                                    <th style="padding: 4px 6px;">Qty</th>
                                    <th style="padding: 4px 6px;">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($items as $it): ?>
                                <?php
                                $invStmt = $db->prepare("SELECT * FROM pharmacy_inventory WHERE drug_name LIKE ? AND status = 'active' LIMIT 1");
                                $invStmt->execute(['%' . $it['drug_name'] . '%']);
                                $inv = $invStmt->fetch();
                                $unitPrice = $inv ? (float)$inv['selling_price'] : 10.00;
                                $qty = max(1, (int)($it['quantity'] ?: 10));
                                $itemSubtotal = $unitPrice * $qty;
                                $rxTotal += $itemSubtotal;
                                ?>
                                <tr style="border-bottom: 1px dashed var(--gray-200);">
                                    <td style="padding: 4px 6px;">
                                        <strong><?= sanitize($it['drug_name']) ?></strong>
                                        <?php if ($inv): ?>
                                            <span class="text-xs text-success" style="display:block;">(In Stock: <?= $inv['stock_quantity'] ?> <?= $inv['unit'] ?>)</span>
                                        <?php else: ?>
                                            <span class="text-xs text-warning" style="display:block;">(Standard Price)</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding: 4px 6px;"><?= sanitize($it['dosage']) ?> (<?= sanitize($it['frequency']) ?> x <?= sanitize($it['duration']) ?>)</td>
                                    <td style="padding: 4px 6px;"><?= formatCurrency($unitPrice) ?></td>
                                    <td style="padding: 4px 6px;"><?= $qty ?></td>
                                    <td style="padding: 4px 6px;"><strong><?= formatCurrency($itemSubtotal) ?></strong></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </td>
                    <td>
                        <span style="font-size: 1.1rem; font-weight: 700; color: var(--success);">
                            <?= formatCurrency($rxTotal) ?>
                        </span>
                    </td>
                    <td>
                        <span class="badge <?= $rx['status'] === 'dispensed' ? 'badge-success' : 'badge-warning' ?>">
                            <?= ucfirst($rx['status']) ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($rx['status'] !== 'dispensed'): ?>
                        <a href="/pharmacy/dispense.php?dispense_id=<?= $rx['id'] ?>" class="btn btn-sm btn-success mb-4" style="display: inline-flex; align-items: center; gap: 4px;" onclick="return confirm('Dispense medicines for #Rx-<?= $rx['id'] ?> and add <?= formatCurrency($rxTotal) ?> to patient invoice?');">
                            <i class="fas fa-check-circle"></i> Dispense & Bill
                        </a>
                        <?php else: ?>
                        <span class="text-xs text-muted mb-4" style="display:block;"><i class="fas fa-check-double text-success"></i> Dispensed</span>
                        <?php endif; ?>
                        
                        <div class="d-flex flex-column gap-4">
                            <a href="/receptionist/view_invoice.php?patient_id=<?= $rx['patient_id'] ?>" class="btn btn-sm btn-primary" style="display: inline-flex; align-items: center; gap: 4px;">
                                <i class="fas fa-receipt"></i> View Patient Bill
                            </a>
                            <a href="/receptionist/print_invoice.php?patient_id=<?= $rx['patient_id'] ?>&autoprint=1" target="_blank" class="btn btn-sm btn-success" style="display: inline-flex; align-items: center; gap: 4px;">
                                <i class="fas fa-print"></i> Print Bill / Receipt
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
