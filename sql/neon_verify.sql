-- Run this in the Neon SQL Editor after migration.
-- It reports the database identity and row counts for every HMS table.

SELECT current_database() AS database_name, current_schema() AS schema_name, version();

SELECT table_name
FROM information_schema.tables
WHERE table_schema = current_schema()
  AND table_type = 'BASE TABLE'
ORDER BY table_name;

SELECT 'users' AS table_name, COUNT(*) AS row_count FROM users
UNION ALL SELECT 'departments', COUNT(*) FROM departments
UNION ALL SELECT 'patients', COUNT(*) FROM patients
UNION ALL SELECT 'doctors', COUNT(*) FROM doctors
UNION ALL SELECT 'nurses', COUNT(*) FROM nurses
UNION ALL SELECT 'wards', COUNT(*) FROM wards
UNION ALL SELECT 'beds', COUNT(*) FROM beds
UNION ALL SELECT 'appointments', COUNT(*) FROM appointments
UNION ALL SELECT 'medical_records', COUNT(*) FROM medical_records
UNION ALL SELECT 'prescriptions', COUNT(*) FROM prescriptions
UNION ALL SELECT 'prescription_items', COUNT(*) FROM prescription_items
UNION ALL SELECT 'admissions', COUNT(*) FROM admissions
UNION ALL SELECT 'vitals', COUNT(*) FROM vitals
UNION ALL SELECT 'medication_administration', COUNT(*) FROM medication_administration
UNION ALL SELECT 'nursing_notes', COUNT(*) FROM nursing_notes
UNION ALL SELECT 'pharmacy_inventory', COUNT(*) FROM pharmacy_inventory
UNION ALL SELECT 'pharmacy_dispensing', COUNT(*) FROM pharmacy_dispensing
UNION ALL SELECT 'lab_test_catalog', COUNT(*) FROM lab_test_catalog
UNION ALL SELECT 'lab_orders', COUNT(*) FROM lab_orders
UNION ALL SELECT 'lab_results', COUNT(*) FROM lab_results
UNION ALL SELECT 'billing', COUNT(*) FROM billing
UNION ALL SELECT 'billing_items', COUNT(*) FROM billing_items
UNION ALL SELECT 'audit_logs', COUNT(*) FROM audit_logs
UNION ALL SELECT 'notifications', COUNT(*) FROM notifications
UNION ALL SELECT 'service_pricing', COUNT(*) FROM service_pricing
UNION ALL SELECT 'payment_methods', COUNT(*) FROM payment_methods
UNION ALL SELECT 'password_resets', COUNT(*) FROM password_resets
UNION ALL SELECT 'refresh_tokens', COUNT(*) FROM refresh_tokens
ORDER BY table_name;
