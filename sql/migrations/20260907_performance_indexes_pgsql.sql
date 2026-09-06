-- Supporting indexes for high-volume API lookups and nested resource queries.
CREATE INDEX IF NOT EXISTS idx_users_phone ON users(phone);
CREATE INDEX IF NOT EXISTS idx_medical_records_patient_created ON medical_records(patient_id, created_at DESC);
CREATE INDEX IF NOT EXISTS idx_prescription_items_prescription ON prescription_items(prescription_id);
CREATE INDEX IF NOT EXISTS idx_vitals_patient_recorded ON vitals(patient_id, recorded_at DESC);
CREATE INDEX IF NOT EXISTS idx_lab_orders_status_ordered ON lab_orders(status, ordered_at);
CREATE INDEX IF NOT EXISTS idx_lab_results_order ON lab_results(lab_order_id);
CREATE INDEX IF NOT EXISTS idx_billing_items_bill ON billing_items(bill_id);
CREATE INDEX IF NOT EXISTS idx_billing_patient_status_id ON billing(patient_id, payment_status, id);
