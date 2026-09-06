<?php
/**
 * REST API — GET /api/v1/lab/dashboard
 * Laboratory queue counts and test order list.
 */

require_once __DIR__ . '/../../../includes/api_middleware.php';

$auth = requireApiAuth(['lab_technician', 'admin']);

try {
    $db = getDB();
    $todayDate = date('Y-m-d');
    $nextDate = date('Y-m-d', strtotime('+1 day'));
    
    // Aggregated stats with SARGable date filter
    $stmtStats = $db->prepare("
        SELECT 
            SUM(CASE WHEN status = 'ordered' THEN 1 ELSE 0 END) as pending_count,
            SUM(CASE WHEN status IN ('sample_collected','processing') THEN 1 ELSE 0 END) as processing_count,
            SUM(CASE WHEN status = 'completed' AND ordered_at >= ? AND ordered_at < ? THEN 1 ELSE 0 END) as completed_today
        FROM lab_orders
    ");
    $stmtStats->execute([$todayDate, $nextDate]);
    $stats = $stmtStats->fetch() ?: [];
    
    // Active orders list
    $stmtOrders = $db->query("
        SELECT lo.*, lc.test_name, lc.category, lc.sample_type,
               p.uhid, u_p.full_name as patient_name, u_d.full_name as doctor_name
        FROM lab_orders lo
        JOIN lab_test_catalog lc ON lo.test_id = lc.id
        JOIN patients p ON lo.patient_id = p.id
        JOIN users u_p ON p.user_id = u_p.id
        JOIN doctors d ON lo.doctor_id = d.id
        JOIN users u_d ON d.user_id = u_d.id
        WHERE lo.status != 'completed'
        ORDER BY lo.priority DESC, lo.ordered_at ASC
        LIMIT 25
    ");
    $orders = $stmtOrders->fetchAll();
    
    jsonSuccess([
        'stats' => [
            'pending_orders' => (int)($stats['pending_count'] ?? 0),
            'processing_orders' => (int)($stats['processing_count'] ?? 0),
            'completed_today' => (int)($stats['completed_today'] ?? 0)
        ],
        'orders' => $orders
    ], 'Lab dashboard retrieved');
    
} catch (\Throwable $e) {
    jsonServerError('Failed to fetch lab dashboard', $e);
}
