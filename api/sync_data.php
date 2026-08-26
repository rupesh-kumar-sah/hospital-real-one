<?php
/**
 * Hospital Management System — PC Local (E:\HM DATA) <-> Render Cloud Secure Data Sync API
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/security.php';

header('Content-Type: application/json');

$syncToken = getenv('DATA_SYNC_TOKEN') ?: 'MEDICARE_HM_DATA_SYNC_SECRET_KEY_2026';
$providedToken = $_SERVER['HTTP_X_SYNC_TOKEN'] ?? $_GET['sync_token'] ?? '';

if (!hash_equals($syncToken, $providedToken)) {
    http_response_code(401);
    echo json_encode([
        'status' => 'error',
        'message' => 'Unauthorized Sync Request: Invalid X-SYNC-TOKEN provided.'
    ]);
    exit;
}

$db = getDB();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // Export cloud database summary for PC sync
    $tables = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'")->fetchAll(PDO::FETCH_COLUMN);
    $counts = [];
    foreach ($tables as $t) {
        $counts[$t] = $db->query("SELECT COUNT(*) FROM {$t}")->fetchColumn();
    }
    
    echo json_encode([
        'status' => 'success',
        'server' => 'Render Cloud Production Engine',
        'db_path' => DB_PATH,
        'timestamp' => date('Y-m-d H:i:s'),
        'table_counts' => $counts
    ]);
    exit;
}

if ($method === 'POST') {
    $rawInput = file_get_contents('php://input');
    $payload = json_decode($rawInput, true);
    
    if (!$payload || !isset($payload['table']) || !isset($payload['data'])) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid JSON payload structure.']);
        exit;
    }
    
    $table = preg_replace('/[^a-zA-Z0-9_]/', '', $payload['table']);
    $data = $payload['data'];
    
    if (empty($data)) {
        echo json_encode(['status' => 'success', 'message' => 'No rows to sync.']);
        exit;
    }
    
    $db->beginTransaction();
    try {
        $columns = array_keys($data[0]);
        $colSql = implode(', ', $columns);
        $valSql = implode(', ', array_fill(0, count($columns), '?'));
        
        $stmt = $db->prepare("INSERT OR REPLACE INTO {$table} ({$colSql}) VALUES ({$valSql})");
        
        $syncedRows = 0;
        foreach ($data as $row) {
            $stmt->execute(array_values($row));
            $syncedRows++;
        }
        
        $db->commit();
        echo json_encode([
            'status' => 'success',
            'message' => "Successfully synced {$syncedRows} rows for table '{$table}' from PC to Render Cloud.",
            'table' => $table
        ]);
    } catch (Exception $e) {
        $db->rollBack();
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => 'Database Sync Failed: ' . $e->getMessage()
        ]);
    }
    exit;
}
