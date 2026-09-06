<?php
/**
 * Regenerate the readable HMS CSV/HTML export without an admin session.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/data_exporter.php';

$files = exportAllTablesToHMData();
fwrite(STDOUT, sprintf(
    "[%s] Exported %d tables to %s\n",
    date('c'),
    count($files),
    getenv('EXPORT_DIRECTORY') ?: 'E:\\HM DATA'
));
