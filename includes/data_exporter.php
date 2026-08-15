<?php
/**
 * Hospital Management System — Data Exporter & Live Spreadsheet Sync
 * Automatically exports database tables into clean CSV spreadsheets and an 
 * interactive Google-Sheets-like HTML dashboard inside E:\HM DATA\
 */

require_once __DIR__ . '/../config/database.php';

function exportAllTablesToHMData(): array {
    $db = getDB();
    $exportDir = 'E:\\HM DATA\\exports\\';
    if (!is_dir($exportDir)) {
        mkdir($exportDir, 0755, true);
    }

    $tables = [
        'users', 'doctors', 'nurses', 'patients', 'departments', 'wards', 'beds',
        'appointments', 'medical_records', 'prescriptions', 'prescription_items',
        'admissions', 'vitals', 'medication_administration', 'nursing_notes',
        'pharmacy_inventory', 'pharmacy_dispensing', 'lab_test_catalog', 'lab_orders',
        'lab_results', 'billing', 'billing_items', 'payment_methods', 'service_pricing', 'audit_logs'
    ];

    $exportedFiles = [];
    $allTableData = [];

    foreach ($tables as $table) {
        try {
            $stmt = $db->query("SELECT * FROM {$table}");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $allTableData[$table] = $rows;

            $csvFile = $exportDir . $table . '.csv';
            $fp = fopen($csvFile, 'w');
            
            // Add UTF-8 BOM for Excel / Google Sheets compatibility
            fputs($fp, "\xEF\xBB\xBF");

            if (!empty($rows)) {
                // Header row
                fputcsv($fp, array_keys($rows[0]), ',', '"', '\\');
                // Data rows
                foreach ($rows as $row) {
                    fputcsv($fp, $row, ',', '"', '\\');
                }
            } else {
                fputcsv($fp, ['No data in table'], ',', '"', '\\');
            }
            fclose($fp);
            $exportedFiles[] = $table . '.csv';
        } catch (Exception $e) {
            error_log("Export error for table {$table}: " . $e->getMessage());
        }
    }

    // Generate Interactive Google-Sheets-like HTML Spreadsheet Report
    generateInteractiveSpreadsheetHTML('E:\\HM DATA\\Hospital_Data_Sheets.html', $allTableData);

    return $exportedFiles;
}

/**
 * Generate a standalone Google-Sheets-like HTML file with tabs for each table
 */
function generateInteractiveSpreadsheetHTML(string $filePath, array $allTableData): void {
    ob_start();
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MediCare Hospital — Live Spreadsheet Database (E:\HM DATA)</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root {
            --primary: #0066CC;
            --gray-100: #f8fafc;
            --gray-200: #e2e8f0;
            --gray-700: #334155;
            --gray-900: #0f172a;
        }
        body { font-family: 'Inter', sans-serif; margin: 0; background: #f1f5f9; color: var(--gray-900); }
        .header { background: #1e293b; color: white; padding: 16px 24px; display: flex; align-items: center; justify-content: space-between; }
        .header h1 { margin: 0; font-size: 1.25rem; font-weight: 700; display: flex; align-items: center; gap: 10px; }
        .header .subtitle { color: #94a3b8; font-size: 0.85rem; }
        .tabs-bar { background: #0f172a; padding: 0 16px; display: flex; overflow-x: auto; border-bottom: 2px solid var(--primary); }
        .tab-btn { background: none; border: none; color: #94a3b8; padding: 12px 18px; font-size: 0.85rem; font-weight: 600; cursor: pointer; white-space: nowrap; border-bottom: 3px solid transparent; transition: all 0.2s; }
        .tab-btn:hover { color: white; background: rgba(255,255,255,0.05); }
        .tab-btn.active { color: white; border-bottom-color: #38bdf8; background: rgba(56,189,248,0.1); }
        .container { padding: 24px; }
        .sheet-card { background: white; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); border: 1px solid var(--gray-200); overflow: hidden; display: none; }
        .sheet-card.active { display: block; }
        .toolbar { padding: 16px 20px; border-bottom: 1px solid var(--gray-200); display: flex; justify-content: space-between; align-items: center; background: var(--gray-100); }
        .search-box { display: flex; align-items: center; background: white; border: 1px solid var(--gray-200); border-radius: 6px; padding: 6px 12px; width: 300px; }
        .search-box input { border: none; outline: none; width: 100%; margin-left: 8px; font-family: inherit; font-size: 0.85rem; }
        .table-responsive { overflow-x: auto; max-height: 650px; }
        table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
        th { background: #f8fafc; color: #475569; font-weight: 600; text-align: left; padding: 12px 16px; border-bottom: 2px solid var(--gray-200); position: sticky; top: 0; }
        td { padding: 10px 16px; border-bottom: 1px solid var(--gray-200); color: var(--gray-700); }
        tr:hover { background: #f1f5f9; }
        .badge { padding: 4px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: 600; }
        .badge-count { background: #38bdf8; color: #0f172a; padding: 2px 6px; border-radius: 10px; font-size: 0.75rem; margin-left: 6px; }
    </style>
</head>
<body>

<div class="header">
    <div>
        <h1><i class="fas fa-table text-primary"></i> MediCare Hospital — Tabular Database Viewer</h1>
        <div class="subtitle"><i class="fas fa-folder"></i> Location: E:\HM DATA\ (Synced at <?= date('Y-m-d H:i:s') ?>)</div>
    </div>
    <div>
        <span class="badge" style="background: #10b981; color: white;"><i class="fas fa-sync"></i> Real-time Sync Active</span>
    </div>
</div>

<div class="tabs-bar" id="tabsBar">
    <?php $first = true; foreach ($allTableData as $tableName => $rows): ?>
    <button class="tab-btn <?= $first ? 'active' : '' ?>" onclick="switchSheet('<?= $tableName ?>')">
        <i class="fas fa-table"></i> <?= ucfirst(str_replace('_', ' ', $tableName)) ?>
        <span class="badge-count"><?= count($rows) ?></span>
    </button>
    <?php $first = false; endforeach; ?>
</div>

<div class="container">
    <?php $first = true; foreach ($allTableData as $tableName => $rows): ?>
    <div class="sheet-card <?= $first ? 'active' : '' ?>" id="sheet_<?= $tableName ?>">
        <div class="toolbar">
            <div class="search-box">
                <i class="fas fa-search" style="color: #94a3b8;"></i>
                <input type="text" placeholder="Filter <?= $tableName ?> data..." onkeyup="filterSheet('<?= $tableName ?>', this.value)">
            </div>
            <div>
                <a href="exports/<?= $tableName ?>.csv" download class="badge" style="background: #0284c7; color: white; text-decoration: none; padding: 8px 12px;">
                    <i class="fas fa-file-csv"></i> Download CSV (Excel / Google Sheets)
                </a>
            </div>
        </div>
        <div class="table-responsive">
            <table id="table_<?= $tableName ?>">
                <thead>
                    <tr>
                        <?php if (!empty($rows)): ?>
                        <?php foreach (array_keys($rows[0]) as $col): ?>
                        <th><?= htmlspecialchars($col) ?></th>
                        <?php endforeach; ?>
                        <?php else: ?>
                        <th>Information</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)): ?>
                    <tr><td>No records in <?= $tableName ?> table.</td></tr>
                    <?php else: ?>
                    <?php foreach ($rows as $row): ?>
                    <tr>
                        <?php foreach ($row as $val): ?>
                        <td><?= htmlspecialchars((string)($val ?? 'NULL')) ?></td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php $first = false; endforeach; ?>
</div>

<script>
function switchSheet(tableName) {
    document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
    document.querySelectorAll('.sheet-card').forEach(card => card.classList.remove('active'));
    
    event.currentTarget.classList.add('active');
    document.getElementById('sheet_' + tableName).classList.add('active');
}

function filterSheet(tableName, query) {
    const q = query.toLowerCase();
    const rows = document.querySelectorAll('#table_' + tableName + ' tbody tr');
    rows.forEach(row => {
        row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
}
</script>

</body>
</html>
    <?php
    $htmlContent = ob_get_clean();
    file_put_contents($filePath, $htmlContent);
}
