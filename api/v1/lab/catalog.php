<?php
/**
 * REST API — /api/v1/lab/catalog
 * GET: Lists master laboratory test catalog and pricing
 * POST: Adds new laboratory investigation
 */

require_once __DIR__ . '/../../../includes/api_middleware.php';

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $category = $_GET['category'] ?? '';
        $where = ["status = 'active'"];
        $params = [];
        
        if (!empty($category)) {
            $where[] = "category = ?";
            $params[] = $category;
        }
        
        $whereSql = "WHERE " . implode(" AND ", $where);
        $stmt = $db->prepare("SELECT * FROM lab_test_catalog {$whereSql} ORDER BY category ASC, test_name ASC LIMIT 100");
        $stmt->execute($params);
        $catalog = $stmt->fetchAll();
        
        jsonSuccess($catalog, 'Lab catalog retrieved');
    } catch (\Throwable $e) {
        jsonServerError('Failed to fetch lab catalog', $e);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $auth = requireApiAuth(['admin', 'lab_technician']);
    $body = getJsonBody();
    
    $testName = trim($body['test_name'] ?? '');
    $category = trim($body['category'] ?? 'General');
    $sampleType = trim($body['sample_type'] ?? 'Blood');
    $normalRange = trim($body['normal_range'] ?? '');
    $unit = trim($body['unit'] ?? '');
    $cost = (float)($body['cost'] ?? 0);
    $tatHours = (int)($body['turnaround_hours'] ?? 24);
    
    if (empty($testName) || $cost <= 0) {
        jsonError('test_name and valid cost are required.', 422);
    }
    
    try {
        $stmt = $db->prepare("
            INSERT INTO lab_test_catalog (test_name, category, sample_type, normal_range, unit, cost, turnaround_hours, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'active', CURRENT_TIMESTAMP)
        ");
        $stmt->execute([$testName, $category, $sampleType, $normalRange, $unit, $cost, $tatHours]);
        
        jsonSuccess(['id' => (int)$db->lastInsertId(), 'test_name' => $testName], 'Lab test added to catalog', 201);
    } catch (\Throwable $e) {
        jsonServerError('Failed to add lab test', $e);
    }
}

jsonError('Method Not Allowed', 405);
