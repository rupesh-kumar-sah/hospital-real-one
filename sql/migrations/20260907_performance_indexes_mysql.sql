-- Supporting indexes for high-volume API lookups and nested resource queries.
CREATE INDEX idx_users_phone ON users(phone);
CREATE INDEX idx_medical_records_patient_created ON medical_records(patient_id, created_at);
CREATE INDEX idx_prescription_items_prescription ON prescription_items(prescription_id);
CREATE INDEX idx_vitals_patient_recorded ON vitals(patient_id, recorded_at);
CREATE INDEX idx_lab_orders_status_ordered ON lab_orders(status, ordered_at);
CREATE INDEX idx_lab_results_order ON lab_results(lab_order_id);
CREATE INDEX idx_billing_patient_status_id ON billing(patient_id, payment_status, id);
