<?php
/**
 * REST API — /api/v1/pharmacy/inventory
 * GET: Lists all inventory drugs with stock levels and pricing
 * POST: Adds new drug or updates stock quantity
 */

require_once __DIR__ . '/../../../includes/api_middleware.php';

$auth = requireApiAuth(['pharmacist', 'admin']);
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $search = trim($_GET['q'] ?? '');
    $category = trim($_GET['category'] ?? '');
    
    $where = ["status = 'active'"];
    $params = [];
    
    if (!empty($search)) {
        $where[] = "(drug_name LIKE ? OR generic_name LIKE ? OR manufacturer LIKE ?)";
        $like = '%' . $search . '%';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }
    if (!empty($category)) {
        $where[] = "category = ?";
        $params[] = $category;
    }
    
    $whereSql = "WHERE " . implode(" AND ", $where);
    
    try {
        $stmt = $db->prepare("SELECT * FROM pharmacy_inventory {$whereSql} ORDER BY drug_name ASC");
        $stmt->execute($params);
        $drugs = $stmt->fetchAll();
        
        jsonSuccess($drugs, 'Inventory retrieved');
    } catch (\Throwable $e) {
        jsonError('Failed to fetch inventory: ' . $e->getMessage(), 500);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = getJsonBody();
    
    $drugName = trim($body['drug_name'] ?? '');
    $genericName = trim($body['generic_name'] ?? '');
    $category = trim($body['category'] ?? 'General');
    $dosageForm = trim($body['dosage_form'] ?? 'Tablet');
    $strength = trim($body['strength'] ?? '');
    $manufacturer = trim($body['manufacturer'] ?? '');
    $batchNumber = trim($body['batch_number'] ?? '');
    $stockQty = max(0, (int)($body['stock_quantity'] ?? 0));
    $reorderLevel = max(1, (int)($body['reorder_level'] ?? 10));
    $purchasePrice = (float)($body['purchase_price'] ?? 0);
    $sellingPrice = (float)($body['selling_price'] ?? 0);
    $expiryDate = $body['expiry_date'] ?? null;
    
    if (empty($drugName) || $sellingPrice <= 0) {
        jsonError('Drug name and selling price are required.', 422);
    }
    
    try {
        $stmt = $db->prepare("
            INSERT INTO pharmacy_inventory (
                drug_name, generic_name, category, dosage_form, strength, manufacturer,
                batch_number, expiry_date, stock_quantity, reorder_level, purchase_price,
                selling_price, status, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', CURRENT_TIMESTAMP)
        ");
        $stmt->execute([
            $drugName, $genericName, $category, $dosageForm, $strength, $manufacturer,
            $batchNumber, $expiryDate ?: null, $stockQty, $reorderLevel, $purchasePrice, $sellingPrice
        ]);
        
        jsonSuccess([
            'id' => (int)$db->lastInsertId(),
            'drug_name' => $drugName
        ], 'Medicine added to inventory', 201);
    } catch (\Throwable $e) {
        jsonError('Failed to add medicine: ' . $e->getMessage(), 500);
    }
}

jsonError('Method Not Allowed', 405);
