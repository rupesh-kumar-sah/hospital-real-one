<?php

declare(strict_types=1);

function deleteAllApplicationData(PDO $db): void
{
    $driver = strtolower((string)$db->getAttribute(PDO::ATTR_DRIVER_NAME));
    if ($driver !== 'pgsql') {
        throw new RuntimeException('Destructive deletion is available only for the Neon PostgreSQL database.');
    }

    $tables = [
        'refresh_tokens', 'password_resets', 'notifications', 'audit_logs',
        'billing_items', 'billing', 'lab_results', 'lab_orders',
        'pharmacy_dispensing', 'pharmacy_inventory', 'nursing_notes',
        'medication_administration', 'vitals', 'prescription_items',
        'prescriptions', 'medical_records', 'admissions', 'appointments',
        'beds', 'wards', 'nurses', 'doctors', 'patients', 'departments',
        'service_pricing', 'payment_methods', 'users'
    ];

    $db->beginTransaction();
    try {
        if ($driver === 'pgsql') {
            $quoted = array_map(static fn(string $table): string => '"' . $table . '"', $tables);
            $db->exec('TRUNCATE TABLE ' . implode(', ', $quoted) . ' RESTART IDENTITY CASCADE');
        }
        $db->commit();
    } catch (Throwable $exception) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $exception;
    }
}
