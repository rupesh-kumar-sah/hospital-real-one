<?php
/**
 * Authenticated website-to-local database export.
 *
 * The response is an AES-256-GCM envelope. It contains no plaintext database
 * data and is never cached. The API key and encryption key are independent.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/sync.php';

function syncJsonValue(mixed $value): mixed
{
    if (is_string($value) && preg_match('//u', $value) !== 1) {
        return ['__hms_binary_base64' => base64_encode($value)];
    }
    if (is_resource($value)) {
        $contents = stream_get_contents($value);
        return ['__hms_binary_base64' => base64_encode($contents === false ? '' : $contents)];
    }
    return $value;
}

function syncDescribeTable(PDO $db, string $table): array
{
    $driver = strtolower((string)$db->getAttribute(PDO::ATTR_DRIVER_NAME));
    $quoted = syncQuoteIdentifier($db, $table);
    $rows = [];

    if ($driver === 'sqlite') {
        $rows = $db->query("PRAGMA table_info({$quoted})")->fetchAll(PDO::FETCH_ASSOC);
    } elseif ($driver === 'mysql') {
        $rows = $db->query("DESCRIBE {$quoted}")->fetchAll(PDO::FETCH_ASSOC);
    } elseif ($driver === 'pgsql') {
        $statement = $db->prepare(
            'SELECT column_name, data_type, is_nullable, column_default '
            . 'FROM information_schema.columns '
            . 'WHERE table_schema = current_schema() AND table_name = :table '
            . 'ORDER BY ordinal_position'
        );
        $statement->execute(['table' => $table]);
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
    } else {
        throw new RuntimeException('Unsupported database driver.');
    }

    if ($rows === []) {
        throw new RuntimeException('A required sync table is unavailable.');
    }

    $columns = [];
    foreach ($rows as $row) {
        $name = (string)($row['name'] ?? $row['Field'] ?? $row['column_name'] ?? '');
        if ($name === '') {
            throw new RuntimeException('A sync table has invalid metadata.');
        }
        $columns[] = [
            'name' => $name,
            'type' => (string)($row['type'] ?? $row['Type'] ?? $row['data_type'] ?? ''),
            'nullable' => !((string)($row['notnull'] ?? '') === '1'
                || strtoupper((string)($row['Null'] ?? '')) === 'NO'
                || strtoupper((string)($row['is_nullable'] ?? '')) === 'NO'),
            'default' => $row['dflt_value'] ?? $row['Default'] ?? $row['column_default'] ?? null,
            'primary_key' => ((int)($row['pk'] ?? 0) > 0)
                || strtoupper((string)($row['Key'] ?? '')) === 'PRI',
        ];
    }
    return $columns;
}

function syncExportPayload(PDO $db): string
{
    $tables = [];
    foreach (syncAllowedTables() as $table) {
        $columns = syncDescribeTable($db, $table);
        $columnNames = array_column($columns, 'name');
        $statement = $db->query('SELECT * FROM ' . syncQuoteIdentifier($db, $table));
        $rows = [];
        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            $normalized = [];
            foreach ($columnNames as $columnName) {
                $normalized[$columnName] = syncJsonValue($row[$columnName] ?? null);
            }
            $rows[] = $normalized;
        }
        $tables[] = [
            'name' => $table,
            'columns' => $columns,
            'rows' => $rows,
        ];
    }

    return json_encode([
        'format' => 'MCHMS-SYNC-1',
        'version' => 1,
        'created_at' => gmdate('c'),
        'source_driver' => strtolower((string)$db->getAttribute(PDO::ATTR_DRIVER_NAME)),
        'tables' => $tables,
    ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
}

function syncSendError(int $status, string $message): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => $message], JSON_UNESCAPED_SLASHES);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    header('Allow: GET');
    syncSendError(405, 'Method not allowed.');
}

if (
    getenv('APP_ENV') === 'production'
    && !syncIsHttpsRequest()
    && filter_var(syncEnvironmentValue('SYNC_ALLOW_INSECURE_LOCAL'), FILTER_VALIDATE_BOOLEAN) !== true
) {
    syncSendError(400, 'HTTPS is required.');
}

$providedKey = (string)($_SERVER['HTTP_X_SYNC_API_KEY'] ?? '');
if ($providedKey === '' && preg_match('/\ABearer[ \t]+(.+)\z/i', (string)($_SERVER['HTTP_AUTHORIZATION'] ?? ''), $matches)) {
    $providedKey = trim($matches[1]);
}

try {
    $expectedKey = syncApiKey();
} catch (Throwable) {
    syncSendError(503, 'Sync is not configured.');
}

if ($providedKey === '' || !hash_equals($expectedKey, $providedKey)) {
    syncSendError(401, 'Unauthorized.');
}

try {
    $key = syncEncryptionKey();
    $db = getDB();
    $plaintext = syncExportPayload($db);
    $iv = random_bytes(12);
    $tag = '';
    $aad = 'MCHMS-SYNC-1';
    $ciphertext = openssl_encrypt(
        $plaintext,
        'aes-256-gcm',
        $key,
        OPENSSL_RAW_DATA,
        $iv,
        $tag,
        $aad
    );
    if ($ciphertext === false || strlen($tag) !== 16) {
        throw new RuntimeException('Encryption failed.');
    }

    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');
    header('X-Content-Type-Options: nosniff');
    echo json_encode([
        'format' => 'MCHMS-SYNC-1',
        'algorithm' => 'aes-256-gcm',
        'aad' => base64_encode($aad),
        'iv' => base64_encode($iv),
        'tag' => base64_encode($tag),
        'ciphertext' => base64_encode($ciphertext),
    ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
} catch (Throwable) {
    // Do not write exception messages: they may contain connection details or
    // data values. The client receives only a generic failure.
    syncSendError(500, 'Unable to create sync export.');
}
