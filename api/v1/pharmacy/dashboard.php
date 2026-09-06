<?php
/**
 * REST API — GET /api/v1/pharmacy/dashboard
 * Pharmacy stats, pending prescriptions queue, and stock threshold warnings.
 */

require_once __DIR__ . '/../../../includes/api_middleware.php';

$auth = requireApiAuth(['pharmacist', 'admin']);

try {
    $db = getDB();
    $todayDate = date('Y-m-d');
    $nextDate = date('Y-m-d', strtotime('+1 day'));
    
    $stmtPendingRx = $db->query("SELECT COUNT(*) as c FROM prescriptions WHERE status = 'pending'")->fetch();
    $pendingRx = (int)($stmtPendingRx['c'] ?? 0);
    
    $stmtDispToday = $db->prepare("SELECT COUNT(*) as c FROM prescriptions WHERE status = 'dispensed' AND created_at >= ? AND created_at < ?");
    $stmtDispToday->execute([$todayDate, $nextDate]);
    $dispensedToday = (int)($stmtDispToday->fetch()['c'] ?? 0);
    
    $stmtTotalDrugs = $db->query("SELECT COUNT(*) as c FROM pharmacy_inventory WHERE status = 'active'")->fetch();
    $totalDrugs = (int)($stmtTotalDrugs['c'] ?? 0);
    
    $stmtLowStock = $db->query("SELECT COUNT(*) as c FROM pharmacy_inventory WHERE stock_quantity <= reorder_level AND status = 'active'")->fetch();
    $lowStockCount = (int)($stmtLowStock['c'] ?? 0);
    
    // Pending Rx list
    $stmtQueue = $db->query("
        SELECT pr.*, p.uhid, u_p.full_name as patient_name, u_d.full_name as doctor_name
        FROM prescriptions pr
        JOIN patients p ON pr.patient_id = p.id
        JOIN users u_p ON p.user_id = u_p.id
        JOIN doctors d ON pr.doctor_id = d.id
        JOIN users u_d ON d.user_id = u_d.id
        WHERE pr.status = 'pending'
        ORDER BY pr.created_at DESC
        LIMIT 20
    ");
    $pendingQueue = $stmtQueue->fetchAll();
    
    $stmtItems = $db->prepare("SELECT * FROM prescription_items WHERE prescription_id = ?");
    foreach ($pendingQueue as &$rx) {
        $stmtItems->execute([$rx['id']]);
        $rx['items'] = $stmtItems->fetchAll();
    }
    
    jsonSuccess([
        'stats' => [
            'pending_prescriptions' => $pendingRx,
            'dispensed_today' => $dispensedToday,
            'total_medicines' => $totalDrugs,
            'low_stock_alerts' => $lowStockCount
        ],
        'queue' => $pendingQueue
    ], 'Pharmacy dashboard retrieved');
    
} catch (\Throwable $e) {
    jsonError('Failed to fetch pharmacy dashboard: ' . $e->getMessage(), 500);
}
