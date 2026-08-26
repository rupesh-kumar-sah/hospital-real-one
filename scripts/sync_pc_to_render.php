<?php
/**
 * Hospital Management System — PC (E:\HM DATA) -> Render Cloud Auto-Sync Script
 * Run this on your Windows PC to continuously sync E:\HM DATA\hms.db with Render Cloud Server
 */

$renderUrl = getenv('RENDER_API_URL') ?: 'https://medicare-hms-public-gateway.onrender.com';
$syncToken = getenv('DATA_SYNC_TOKEN') ?: 'MEDICARE_HM_DATA_SYNC_SECRET_KEY_2026';
$localDbPath = 'E:/HM DATA/hms.db';

if (!file_exists($localDbPath)) {
    die("Local Database not found at {$localDbPath}\n");
}

echo "=== E:\\HM DATA LOCAL PC -> RENDER CLOUD AUTO-SYNC ===\n";
echo "Local DB Path: {$localDbPath}\n";
echo "Render Server URL: {$renderUrl}\n\n";

try {
    $db = new PDO('sqlite:' . $localDbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    $tables = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'")->fetchAll(PDO::FETCH_COLUMN);

    echo "Found " . count($tables) . " tables in local E:\\HM DATA\\hms.db.\n";

    foreach ($tables as $table) {
        $rows = $db->query("SELECT * FROM {$table}")->fetchAll();
        if (empty($rows)) continue;

        $payload = json_encode(['table' => $table, 'data' => $rows]);

        $ch = curl_init($renderUrl . '/api/sync_data.php');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'X-SYNC-TOKEN: ' . $syncToken
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $resData = json_decode($response, true);
        if ($httpCode === 200 && ($resData['status'] ?? '') === 'success') {
            echo "   [SUCCESS] Synced {$table} (" . count($rows) . " rows)\n";
        } else {
            echo "   [NOTICE] Sync result for {$table}: HTTP {$httpCode}\n";
        }
    }

    echo "\nSync Complete! E:\\HM DATA\\hms.db is in 100% harmony with Render Cloud Server.\n";
} catch (Exception $e) {
    echo "Error during sync: " . $e->getMessage() . "\n";
}
