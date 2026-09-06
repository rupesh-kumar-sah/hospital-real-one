<?php
/**
 * REST API — POST /api/v1/pharmacy/dispense
 * Fulfills prescription, deducts drug inventory stock, and attaches charges to patient bill.
 */

require_once __DIR__ . '/../../../includes/api_middleware.php';

$auth = requireApiAuth(['pharmacist', 'admin']);
$pharmacistUserId = (int)$auth['sub'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method Not Allowed. POST required.', 405);
}

$body = getJsonBody();
$prescriptionId = (int)($body['prescription_id'] ?? 0);

if ($prescriptionId <= 0) {
    jsonError('prescription_id is required.', 422);
}

try {
    $db = getDB();
    
    $stmtRx = $db->prepare("
        SELECT pr.*, p.id as patient_id, p.user_id as patient_user_id
        FROM prescriptions pr
        JOIN patients p ON pr.patient_id = p.id
        WHERE pr.id = ?
    ");
    $stmtRx->execute([$prescriptionId]);
    $rx = $stmtRx->fetch();
    
    if (!$rx) {
        jsonError('Prescription not found.', 404);
    }
    
    if ($rx['status'] === 'dispensed') {
        jsonError('This prescription has already been dispensed.', 409);
    }
    
    $db->beginTransaction();
    
    // Fetch items
    $stmtItems = $db->prepare("SELECT * FROM prescription_items WHERE prescription_id = ?");
    $stmtItems->execute([$prescriptionId]);
    $items = $stmtItems->fetchAll();
    
    // Resolve all prescribed medicines with one inventory lookup instead of one
    // query per item. Matching remains equivalent to the previous LIKE lookup.
    $inventory = [];
    if ($items) {
        $inventoryWhere = implode(' OR ', array_fill(0, count($items), 'drug_name LIKE ?'));
        $inventoryStmt = $db->prepare(
            "SELECT * FROM pharmacy_inventory WHERE status = 'active' AND ({$inventoryWhere}) ORDER BY id"
        );
        $inventoryStmt->execute(array_map(
            static fn(array $item): string => '%' . $item['drug_name'] . '%',
            $items
        ));
        $inventory = $inventoryStmt->fetchAll();
    }
    $stockDeduct = $db->prepare("UPDATE pharmacy_inventory SET stock_quantity = MAX(0, stock_quantity - ?) WHERE id = ?");
    
    $totalMedCost = 0;
    
    foreach ($items as $it) {
        $inv = null;
        foreach ($inventory as $candidate) {
            if (stripos($candidate['drug_name'], $it['drug_name']) !== false) {
                $inv = $candidate;
                break;
            }
        }
        
        $qty = max(1, (int)($it['quantity'] ?? 10));
        $unitPrice = $inv ? (float)$inv['selling_price'] : 10.00;
        $subtotal = $qty * $unitPrice;
        $totalMedCost += $subtotal;
        
        if ($inv) {
            $stockDeduct->execute([$qty, $inv['id']]);
        }
    }
    
    // Mark prescription dispensed
    $stmtUpd = $db->prepare("UPDATE prescriptions SET status = 'dispensed', updated_at = CURRENT_TIMESTAMP WHERE id = ?");
    $stmtUpd->execute([$prescriptionId]);
    
    // Append to open bill or create pharmacy invoice
    $patientId = (int)$rx['patient_id'];
    $billStmt = $db->prepare("SELECT id, subtotal, net_amount FROM billing WHERE patient_id = ? AND payment_status = 'unpaid' ORDER BY id DESC LIMIT 1");
    $billStmt->execute([$patientId]);
    $openBill = $billStmt->fetch();
    
    if ($openBill) {
        $billId = (int)$openBill['id'];
        $newSub = (float)$openBill['subtotal'] + $totalMedCost;
        $newNet = (float)$openBill['net_amount'] + $totalMedCost;
        $updBill = $db->prepare("UPDATE billing SET subtotal = ?, net_amount = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $updBill->execute([$newSub, $newNet, $billId]);
    } else {
        $invNum = generateInvoiceNumber();
        $createBill = $db->prepare("
            INSERT INTO billing (patient_id, invoice_number, subtotal, discount, tax, net_amount, payment_status, payment_method, created_by, created_at)
            VALUES (?, ?, ?, 0, 0, ?, 'unpaid', 'cash', ?, CURRENT_TIMESTAMP)
        ");
        $createBill->execute([$patientId, $invNum, $totalMedCost, $totalMedCost, $pharmacistUserId]);
        $billId = (int)$db->lastInsertId();
    }
    
    $stmtBillItem = $db->prepare("
        INSERT INTO billing_items (bill_id, item_type, description, quantity, unit_price, total_price)
        VALUES (?, 'pharmacy', ?, 1, ?, ?)
    ");
    $stmtBillItem->execute([$billId, "Prescribed Medicines (#Rx-{$prescriptionId})", $totalMedCost, $totalMedCost]);
    
    $db->commit();
    
    jsonSuccess([
        'prescription_id' => $prescriptionId,
        'status' => 'dispensed',
        'bill_id' => $billId,
        'medication_total' => $totalMedCost
    ], 'Prescription dispensed and inventory deducted');
    
} catch (\Throwable $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    jsonServerError('Failed to dispense prescription', $e);
}
