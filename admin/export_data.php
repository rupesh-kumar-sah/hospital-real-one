<?php
/**
 * Hospital Management System — Admin Data Exporter & Google Sheets Generator
 * Exports all database tables to CSV and creates interactive Hospital_Data_Sheets.html in E:\HM DATA\
 */

require_once __DIR__ . '/../includes/auth_middleware.php';
requireRole('admin');

$db = getDB();

// Destination directory
$hmDir = 'E:/HM DATA';
$csvDir = $hmDir . '/csv_exports';

if (!is_dir($hmDir)) {
    @mkdir($hmDir, 0777, true);
}
if (!is_dir($csvDir)) {
    @mkdir($csvDir, 0777, true);
}

// Get all table names
$tables = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'")->fetchAll(PDO::FETCH_COLUMN);

$exportedFiles = [];
$htmlTableSections = "";

foreach ($tables as $table) {
    $rows = $db->query("SELECT * FROM {$table}")->fetchAll();
    $csvFile = $csvDir . '/' . $table . '.csv';
    
    $fp = fopen($csvFile, 'w');
    if (!empty($rows)) {
        // Headers
        fputcsv($fp, array_keys($rows[0]), ',', '"', '\\');
        // Data
        foreach ($rows as $row) {
            fputcsv($fp, $row, ',', '"', '\\');
        }
    } else {
        // Get column names for empty table
        $cols = $db->query("PRAGMA table_info({$table})")->fetchAll(PDO::FETCH_COLUMN, 1);
        fputcsv($fp, $cols, ',', '"', '\\');
    }
    fclose($fp);
    $exportedFiles[] = $table . '.csv';

    // Build HTML table for Hospital_Data_Sheets.html
    $htmlTableSections .= "<div class='table-card' id='sec-{$table}'>";
    $htmlTableSections .= "<h2><i class='fas fa-table'></i> Table: " . ucfirst($table) . " (" . count($rows) . " rows)</h2>";
    $htmlTableSections .= "<div class='table-wrapper'><table><thead><tr>";
    if (!empty($rows)) {
        foreach (array_keys($rows[0]) as $h) {
            $htmlTableSections .= "<th>" . htmlspecialchars($h) . "</th>";
        }
        $htmlTableSections .= "</tr></thead><tbody>";
        foreach ($rows as $r) {
            $htmlTableSections .= "<tr>";
            foreach ($r as $val) {
                $htmlTableSections .= "<td>" . htmlspecialchars($val ?? '') . "</td>";
            }
            $htmlTableSections .= "</tr>";
        }
    } else {
        $cols = $db->query("PRAGMA table_info({$table})")->fetchAll(PDO::FETCH_COLUMN, 1);
        foreach ($cols as $h) {
            $htmlTableSections .= "<th>" . htmlspecialchars($h) . "</th>";
        }
        $htmlTableSections .= "</tr></thead><tbody><tr><td colspan='" . count($cols) . "'>No records in this table</td></tr>";
    }
    $htmlTableSections .= "</tbody></table></div></div>";
}

// Generate interactive Hospital_Data_Sheets.html
$htmlDashboardPath = $hmDir . '/Hospital_Data_Sheets.html';
$htmlContent = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hospital Data Sheets & Database Dashboard — MediCare HMS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body { font-family: 'Inter', sans-serif; background: #0f172a; color: #f8fafc; margin: 0; padding: 24px; }
        .container { max-width: 1400px; margin: 0 auto; }
        .header { display: flex; justify-content: space-between; align-items: center; background: #1e293b; padding: 20px 28px; border-radius: 16px; margin-bottom: 24px; border: 1px solid #334155; }
        .header h1 { margin: 0; font-size: 1.6rem; color: #38bdf8; }
        .nav-tabs { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 24px; }
        .nav-btn { background: #1e293b; border: 1px solid #334155; color: #94a3b8; padding: 8px 14px; border-radius: 8px; font-weight: 600; cursor: pointer; text-decoration: none; font-size: 0.85rem; }
        .nav-btn:hover, .nav-btn.active { background: #0284c7; color: #fff; border-color: #38bdf8; }
        .table-card { background: #1e293b; border-radius: 16px; padding: 24px; margin-bottom: 28px; border: 1px solid #334155; }
        .table-card h2 { margin: 0 0 16px 0; font-size: 1.2rem; color: #38bdf8; display: flex; align-items: center; gap: 8px; }
        .table-wrapper { overflow-x: auto; max-height: 500px; }
        table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
        th { background: #0f172a; padding: 10px 14px; text-align: left; color: #38bdf8; border-bottom: 2px solid #334155; position: sticky; top: 0; }
        td { padding: 10px 14px; border-bottom: 1px solid #334155; color: #cbd5e1; }
        tr:hover { background: rgba(56, 189, 248, 0.05); }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <div>
            <h1><i class="fas fa-hospital text-accent"></i> Hospital Data Sheets & Google Sheets Export</h1>
            <p style="margin: 4px 0 0 0; color: #94a3b8; font-size: 0.9rem;">
                Database Storage Path: <strong>E:\HM DATA\hms.db</strong> | Individual CSV Exports: <strong>E:\HM DATA\csv_exports\</strong>
            </p>
        </div>
        <div>
            <span style="background: #0284c7; color: #fff; padding: 6px 12px; border-radius: 20px; font-weight: 700; font-size: 0.85rem;">
                Total Tables: {$tableCount}
            </span>
        </div>
    </div>

    {$htmlTableSections}
</div>
</body>
</html>
HTML;

$tableCount = count($tables);
$htmlContent = str_replace('{$tableCount}', (string)$tableCount, $htmlContent);

file_put_contents($htmlDashboardPath, $htmlContent);

logAudit('export', 'database', 0, "Exported all " . count($tables) . " database tables to CSV and updated Hospital_Data_Sheets.html in E:\\HM DATA\\");

setFlash('success', "Data Export Complete! Exported " . count($tables) . " tables to E:\\HM DATA\\csv_exports\\ and generated Hospital_Data_Sheets.html inside E:\\HM DATA\\");

header('Location: /admin/audit_logs.php');
exit;
