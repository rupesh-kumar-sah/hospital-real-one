-- =====================================================
-- Hospital Management System — Initial Production Seed
-- Clean setup with Main Admin Account & Initial Config
-- =====================================================

-- =====================================================
-- MAIN ADMIN ACCOUNT
-- Username & Email: sahkkr702@gmail.com
-- Password: kumar@9090
-- Hash: $2y$12$MF.oHNRTM1i9.o02PTIzq.eLGzvKAwvUv3nAKe9Ini8ILQrGsPD2W
-- =====================================================
INSERT INTO users (username, email, password_hash, full_name, phone, role, status)
VALUES ('sahkkr702@gmail.com', 'sahkkr702@gmail.com', '$2y$12$MF.oHNRTM1i9.o02PTIzq.eLGzvKAwvUv3nAKe9Ini8ILQrGsPD2W', 'System Administrator', '9800000000', 'admin', 'active');

-- =====================================================
-- HOSPITAL DEPARTMENTS
-- =====================================================
INSERT INTO departments (name, description, status) VALUES ('General Medicine', 'Internal medicine and general health care', 'active');
INSERT INTO departments (name, description, status) VALUES ('Cardiology', 'Heart and cardiovascular system', 'active');
INSERT INTO departments (name, description, status) VALUES ('Orthopedics', 'Bones, joints, and musculoskeletal system', 'active');
INSERT INTO departments (name, description, status) VALUES ('Pediatrics', 'Medical care for children and infants', 'active');
INSERT INTO departments (name, description, status) VALUES ('Gynecology & Obstetrics', 'Women health, pregnancy, and childbirth', 'active');
INSERT INTO departments (name, description, status) VALUES ('Dermatology', 'Skin, hair, and nail health', 'active');
INSERT INTO departments (name, description, status) VALUES ('Neurology', 'Brain, spinal cord, and nervous system', 'active');
INSERT INTO departments (name, description, status) VALUES ('Emergency & Trauma', '24/7 critical and emergency medical care', 'active');

-- =====================================================
-- WARDS
-- =====================================================
INSERT INTO wards (name, type, floor, capacity, status) VALUES ('General Male Ward', 'general', 1, 10, 'active');
INSERT INTO wards (name, type, floor, capacity, status) VALUES ('General Female Ward', 'general', 1, 10, 'active');
INSERT INTO wards (name, type, floor, capacity, status) VALUES ('Private Deluxe Ward', 'private', 2, 5, 'active');
INSERT INTO wards (name, type, floor, capacity, status) VALUES ('ICU (Intensive Care Unit)', 'icu', 3, 6, 'active');
INSERT INTO wards (name, type, floor, capacity, status) VALUES ('CCU (Cardiac Care Unit)', 'icu', 3, 4, 'active');

-- =====================================================
-- BEDS
-- =====================================================
INSERT INTO beds (ward_id, bed_number, status, daily_charge) VALUES (1, 'GM-01', 'available', 300.00);
INSERT INTO beds (ward_id, bed_number, status, daily_charge) VALUES (1, 'GM-02', 'available', 300.00);
INSERT INTO beds (ward_id, bed_number, status, daily_charge) VALUES (1, 'GM-03', 'available', 300.00);
INSERT INTO beds (ward_id, bed_number, status, daily_charge) VALUES (2, 'GF-01', 'available', 300.00);
INSERT INTO beds (ward_id, bed_number, status, daily_charge) VALUES (2, 'GF-02', 'available', 300.00);
INSERT INTO beds (ward_id, bed_number, status, daily_charge) VALUES (3, 'PV-101', 'available', 1500.00);
INSERT INTO beds (ward_id, bed_number, status, daily_charge) VALUES (3, 'PV-102', 'available', 1500.00);
INSERT INTO beds (ward_id, bed_number, status, daily_charge) VALUES (4, 'ICU-01', 'available', 3000.00);
INSERT INTO beds (ward_id, bed_number, status, daily_charge) VALUES (4, 'ICU-02', 'available', 3000.00);

-- =====================================================
-- LAB TEST CATALOG
-- =====================================================
INSERT INTO lab_test_catalog (test_name, category, price, sample_type, normal_range, status) VALUES ('Complete Blood Count (CBC)', 'Hematology', 450.00, 'Whole Blood', 'WBC: 4-11, Hb: 12-16 g/dL', 'active');
INSERT INTO lab_test_catalog (test_name, category, price, sample_type, normal_range, status) VALUES ('Fasting Blood Sugar (FBS)', 'Biochemistry', 200.00, 'Blood Serum', '70-100 mg/dL', 'active');
INSERT INTO lab_test_catalog (test_name, category, price, sample_type, normal_range, status) VALUES ('Lipid Profile', 'Biochemistry', 900.00, 'Blood Serum', 'Cholesterol < 200 mg/dL', 'active');
INSERT INTO lab_test_catalog (test_name, category, price, sample_type, normal_range, status) VALUES ('Kidney Function Test (KFT)', 'Biochemistry', 800.00, 'Blood Serum', 'Urea: 15-40, Creatinine: 0.6-1.2', 'active');
INSERT INTO lab_test_catalog (test_name, category, price, sample_type, normal_range, status) VALUES ('Liver Function Test (LFT)', 'Biochemistry', 850.00, 'Blood Serum', 'Bilirubin: 0.2-1.2, SGPT: 7-56', 'active');
INSERT INTO lab_test_catalog (test_name, category, price, sample_type, normal_range, status) VALUES ('Thyroid Panel (T3, T4, TSH)', 'Endocrinology', 1100.00, 'Blood Serum', 'TSH: 0.4-4.0 µIU/mL', 'active');
INSERT INTO lab_test_catalog (test_name, category, price, sample_type, normal_range, status) VALUES ('Chest X-Ray PA View', 'Radiology', 600.00, 'N/A', 'Clear lung fields', 'active');
INSERT INTO lab_test_catalog (test_name, category, price, sample_type, normal_range, status) VALUES ('Urine Routine Analysis', 'Microbiology', 250.00, 'Urine', 'pH: 5.0-8.0, Protein: Nil', 'active');

-- =====================================================
-- PHARMACY INVENTORY
-- =====================================================
INSERT INTO pharmacy_inventory (drug_name, generic_name, category, batch_number, stock_quantity, reorder_level, unit_price, selling_price, expiry_date, status)
VALUES ('Paracetamol 500mg', 'Acetaminophen', 'Tablet', 'BAT-101', 500, 50, 2.00, 4.00, '2027-12-31', 'active');

INSERT INTO pharmacy_inventory (drug_name, generic_name, category, batch_number, stock_quantity, reorder_level, unit_price, selling_price, expiry_date, status)
VALUES ('Amoxicillin 500mg', 'Amoxicillin', 'Capsule', 'BAT-102', 300, 30, 6.00, 10.00, '2026-11-30', 'active');

INSERT INTO pharmacy_inventory (drug_name, generic_name, category, batch_number, stock_quantity, reorder_level, unit_price, selling_price, expiry_date, status)
VALUES ('Azithromycin 500mg', 'Azithromycin', 'Tablet', 'BAT-103', 200, 20, 15.00, 25.00, '2027-06-30', 'active');

INSERT INTO pharmacy_inventory (drug_name, generic_name, category, batch_number, stock_quantity, reorder_level, unit_price, selling_price, expiry_date, status)
VALUES ('Omeprazole 20mg', 'Omeprazole', 'Capsule', 'BAT-104', 400, 40, 3.50, 7.00, '2027-08-31', 'active');

INSERT INTO pharmacy_inventory (drug_name, generic_name, category, batch_number, stock_quantity, reorder_level, unit_price, selling_price, expiry_date, status)
VALUES ('Pantoprazole 40mg IV', 'Pantoprazole', 'Injection', 'BAT-105', 100, 15, 35.00, 60.00, '2026-10-15', 'active');

-- =====================================================
-- SERVICE PRICING
-- =====================================================
INSERT INTO service_pricing (service_name, category, price, description, status) VALUES ('General OPD Consultation', 'Consultation', 500.00, 'Outpatient consultation with specialist doctor', 'active');
INSERT INTO service_pricing (service_name, category, price, description, status) VALUES ('Specialist Follow-up', 'Consultation', 300.00, 'Follow up consultation within 7 days', 'active');
INSERT INTO service_pricing (service_name, category, price, description, status) VALUES ('ECG Recording', 'Procedure', 350.00, '12-lead Electrocardiogram', 'active');
INSERT INTO service_pricing (service_name, category, price, description, status) VALUES ('Nebulization Charge', 'Procedure', 200.00, 'Single nebulization session', 'active');
INSERT INTO service_pricing (service_name, category, price, description, status) VALUES ('General Bed Daily Rate', 'Accommodation', 300.00, 'Per day general ward bed charge', 'active');
INSERT INTO service_pricing (service_name, category, price, description, status) VALUES ('Private Room Daily Rate', 'Accommodation', 1500.00, 'Per day private room charge', 'active');
INSERT INTO service_pricing (service_name, category, price, description, status) VALUES ('ICU Bed Daily Rate', 'Accommodation', 3000.00, 'Per day ICU bed charge with monitor', 'active');

-- =====================================================
-- DEFAULT PAYMENT METHODS (eSewa, Khalti, Fonepay, Cash)
-- =====================================================
INSERT INTO payment_methods (name, account_name, account_number, qr_image, instructions, status)
VALUES ('eSewa', 'MediCare Hospital & Research Centre', '9800000000', '', 'Scan the eSewa QR Code via your eSewa App. Mention Patient UHID in Remarks.', 'active');

INSERT INTO payment_methods (name, account_name, account_number, qr_image, instructions, status)
VALUES ('Khalti', 'MediCare Hospital & Research Centre', '9800000000', '', 'Scan Khalti QR Code to complete instant payment.', 'active');

INSERT INTO payment_methods (name, account_name, account_number, qr_image, instructions, status)
VALUES ('Fonepay / Mobile Banking', 'MediCare Hospital & Research Centre', '9800000000', '', 'Scan using any Mobile Banking App (Fonepay network).', 'active');

INSERT INTO payment_methods (name, account_name, account_number, qr_image, instructions, status)
VALUES ('Cash', 'Hospital Cash Counter', 'Counter 1 & 2', '', 'Pay directly at the hospital billing counter.', 'active');
