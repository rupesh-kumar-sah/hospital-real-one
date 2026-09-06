<?php
/**
 * Shared configuration and helpers for the website-to-local sync channel.
 */

declare(strict_types=1);

/**
 * Tables that may be exported. Keeping this list fixed prevents an identifier
 * supplied by a request from becoming part of a SQL statement.
 */
function syncAllowedTables(): array
{
    return [
        'users', 'departments', 'patients', 'doctors', 'nurses', 'wards', 'beds',
        'appointments', 'medical_records', 'prescriptions', 'prescription_items',
        'admissions', 'vitals', 'medication_administration', 'nursing_notes',
        'pharmacy_inventory', 'pharmacy_dispensing', 'lab_test_catalog', 'lab_orders',
        'lab_results', 'billing', 'billing_items', 'audit_logs', 'notifications',
        'service_pricing', 'payment_methods', 'password_resets', 'refresh_tokens',
    ];
}

function syncEnvironmentValue(string $name): string
{
    $value = getenv($name);
    if ($value === false) {
        $value = $_ENV[$name] ?? $_SERVER[$name] ?? '';
    }
    return is_string($value) ? trim($value) : '';
}

/**
 * The API key is deliberately not logged or included in an error response.
 */
function syncApiKey(): string
{
    $key = syncEnvironmentValue('SYNC_API_KEY');
    if ($key === '' || strlen($key) < 32) {
        throw new RuntimeException('SYNC_API_KEY is not configured.');
    }
    return $key;
}

/**
 * SYNC_ENCRYPTION_KEY is represented as 32 bytes in a 64-character hex value.
 */
function syncEncryptionKey(): string
{
    $value = syncEnvironmentValue('SYNC_ENCRYPTION_KEY');
    if (!preg_match('/\A[0-9a-fA-F]{64}\z/', $value)) {
        throw new RuntimeException('SYNC_ENCRYPTION_KEY is not configured correctly.');
    }
    $key = hex2bin($value);
    if ($key === false || strlen($key) !== 32) {
        throw new RuntimeException('SYNC_ENCRYPTION_KEY is not configured correctly.');
    }
    return $key;
}

function syncQuoteIdentifier(PDO $pdo, string $identifier): string
{
    if (!in_array($identifier, syncAllowedTables(), true)) {
        throw new InvalidArgumentException('Table is not in the sync allowlist.');
    }

    $driver = strtolower((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME));
    $quote = $driver === 'mysql' ? '`' : '"';
    return $quote . str_replace($quote, $quote . $quote, $identifier) . $quote;
}

function syncIsHttpsRequest(): bool
{
    $https = strtolower((string)($_SERVER['HTTPS'] ?? ''));
    if ($https === 'on' || $https === '1') {
        return true;
    }
    return strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
}
