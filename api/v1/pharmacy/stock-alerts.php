<?php
/**
 * REST API — GET /api/v1/pharmacy/stock-alerts
 * Lists low-stock and near-expiry medications.
 */

require_once __DIR__ . '/../../../includes/api_middleware.php';

$auth = requireApiAuth(['pharmacist', 'admin']);

try {
    $db = getDB();
    
    // Low stock items
    $stmtLow = $db->query("
        SELECT id, drug_name, generic_name, dosage_form, strength, stock_quantity, reorder_level, selling_price, expiry_date
        FROM pharmacy_inventory
        WHERE stock_quantity <= reorder_level AND status = 'active'
        ORDER BY stock_quantity ASC
    ");
    $lowStock = $stmtLow->fetchAll();
    
    // Near expiry items (within 60 days)
    $stmtExp = $db->query("
        SELECT id, drug_name, generic_name, dosage_form, strength, stock_quantity, expiry_date
        FROM pharmacy_inventory
        WHERE expiry_date IS NOT NULL AND expiry_date <= DATE_ADD(CURRENT_DATE, INTERVAL 60 DAY) AND status = 'active'
        ORDER BY expiry_date ASC
    ");
    $nearExpiry = $stmtExp->fetchAll();
    
    jsonSuccess([
        'low_stock' => $lowStock,
        'near_expiry' => $nearExpiry
    ], 'Stock alerts retrieved');
} catch (\Throwable $e) {
    jsonError('Failed to fetch stock alerts: ' . $e->getMessage(), 500);
}
