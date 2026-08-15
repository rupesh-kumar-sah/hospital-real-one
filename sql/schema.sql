-- =====================================================
-- Hospital Management System (HMS) — Database Schema
-- Database: SQLite3 (via PHP PDO)
-- =====================================================

-- Enable foreign keys
PRAGMA foreign_keys = ON;

-- =====================================================
-- 1. USERS — Central user table for all roles
-- =====================================================
CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    role VARCHAR(20) NOT NULL CHECK (role IN ('admin','receptionist','doctor','nurse','patient','pharmacist','lab_technician')),
    avatar VARCHAR(255) DEFAULT NULL,
    status VARCHAR(10) DEFAULT 'active' CHECK (status IN ('active','inactive','suspended')),
    last_login DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- =====================================================
-- 2. DEPARTMENTS — Hospital departments
-- =====================================================
CREATE TABLE IF NOT EXISTS departments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    head_doctor_id INTEGER DEFAULT NULL,
    status VARCHAR(10) DEFAULT 'active' CHECK (status IN ('active','inactive')),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (head_doctor_id) REFERENCES doctors(id) ON DELETE SET NULL
);

-- =====================================================
-- 3. PATIENTS — Extended patient information
-- =====================================================
CREATE TABLE IF NOT EXISTS patients (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL UNIQUE,
    uhid VARCHAR(20) UNIQUE NOT NULL,  -- Unique Hospital ID
    date_of_birth DATE,
    gender VARCHAR(10) CHECK (gender IN ('male','female','other')),
    blood_group VARCHAR(5) CHECK (blood_group IN ('A+','A-','B+','B-','AB+','AB-','O+','O-','')),
    marital_status VARCHAR(15) DEFAULT '' CHECK (marital_status IN ('single','married','divorced','widowed','')),
    address TEXT,
    city VARCHAR(50),
    state VARCHAR(50),
    zip_code VARCHAR(10),
    emergency_contact_name VARCHAR(100),
    emergency_contact_phone VARCHAR(20),
    emergency_contact_relation VARCHAR(50),
    insurance_provider VARCHAR(100),
    insurance_number VARCHAR(50),
    allergies TEXT,
    chronic_conditions TEXT,
    notes TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- =====================================================
-- 4. DOCTORS — Extended doctor information
-- =====================================================
CREATE TABLE IF NOT EXISTS doctors (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL UNIQUE,
    department_id INTEGER,
    specialization VARCHAR(100),
    qualification VARCHAR(200),
    license_number VARCHAR(50),
    experience_years INTEGER DEFAULT 0,
    consultation_fee DECIMAL(10,2) DEFAULT 0.00,
    bio TEXT,
    schedule TEXT,  -- JSON: {"mon": "09:00-17:00", "tue": "09:00-17:00", ...}
    max_patients_per_day INTEGER DEFAULT 30,
    status VARCHAR(10) DEFAULT 'active',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL
);

-- =====================================================
-- 5. NURSES — Extended nurse information
-- =====================================================
CREATE TABLE IF NOT EXISTS nurses (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL UNIQUE,
    ward_id INTEGER,
    shift VARCHAR(10) DEFAULT 'morning' CHECK (shift IN ('morning','evening','night')),
    qualification VARCHAR(200),
    experience_years INTEGER DEFAULT 0,
    status VARCHAR(10) DEFAULT 'active',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (ward_id) REFERENCES wards(id) ON DELETE SET NULL
);

-- =====================================================
-- 6. WARDS — Hospital wards
-- =====================================================
CREATE TABLE IF NOT EXISTS wards (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name VARCHAR(100) NOT NULL,
    type VARCHAR(20) DEFAULT 'general' CHECK (type IN ('general','private','semi_private','icu','nicu','emergency','maternity','pediatric')),
    floor INTEGER DEFAULT 1,
    capacity INTEGER DEFAULT 10,
    description TEXT,
    status VARCHAR(10) DEFAULT 'active' CHECK (status IN ('active','inactive','maintenance')),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- =====================================================
-- 7. BEDS — Individual beds in wards
-- =====================================================
CREATE TABLE IF NOT EXISTS beds (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    ward_id INTEGER NOT NULL,
    bed_number VARCHAR(20) NOT NULL,
    status VARCHAR(15) DEFAULT 'available' CHECK (status IN ('available','occupied','reserved','maintenance')),
    daily_charge DECIMAL(10,2) DEFAULT 0.00,
    notes TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ward_id) REFERENCES wards(id) ON DELETE CASCADE,
    UNIQUE(ward_id, bed_number)
);

-- =====================================================
-- 8. APPOINTMENTS — OPD appointments
-- =====================================================
CREATE TABLE IF NOT EXISTS appointments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    patient_id INTEGER NOT NULL,
    doctor_id INTEGER NOT NULL,
    department_id INTEGER,
    appointment_date DATE NOT NULL,
    appointment_time TIME NOT NULL,
    status VARCHAR(20) DEFAULT 'scheduled' CHECK (status IN ('scheduled','checked_in','in_progress','completed','cancelled','no_show')),
    token_number INTEGER,
    type VARCHAR(15) DEFAULT 'opd' CHECK (type IN ('opd','follow_up','emergency','teleconsult')),
    reason TEXT,
    notes TEXT,
    created_by INTEGER,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

-- =====================================================
-- 9. MEDICAL RECORDS — EMR entries
-- =====================================================
CREATE TABLE IF NOT EXISTS medical_records (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    patient_id INTEGER NOT NULL,
    doctor_id INTEGER NOT NULL,
    appointment_id INTEGER,
    admission_id INTEGER,
    diagnosis TEXT,
    symptoms TEXT,
    clinical_notes TEXT,
    examination_findings TEXT,
    treatment_plan TEXT,
    follow_up_date DATE,
    record_type VARCHAR(15) DEFAULT 'opd' CHECK (record_type IN ('opd','ipd','emergency')),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE,
    FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE SET NULL,
    FOREIGN KEY (admission_id) REFERENCES admissions(id) ON DELETE SET NULL
);

-- =====================================================
-- 10. PRESCRIPTIONS — Linked to medical records
-- =====================================================
CREATE TABLE IF NOT EXISTS prescriptions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    medical_record_id INTEGER,
    patient_id INTEGER NOT NULL,
    doctor_id INTEGER NOT NULL,
    appointment_id INTEGER,
    notes TEXT,
    status VARCHAR(20) DEFAULT 'pending' CHECK (status IN ('pending','dispensed','partially_dispensed','cancelled')),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (medical_record_id) REFERENCES medical_records(id) ON DELETE SET NULL,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE,
    FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE SET NULL
);

-- =====================================================
-- 11. PRESCRIPTION ITEMS — Individual medicines
-- =====================================================
CREATE TABLE IF NOT EXISTS prescription_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    prescription_id INTEGER NOT NULL,
    drug_name VARCHAR(200) NOT NULL,
    dosage VARCHAR(100),
    frequency VARCHAR(100),  -- e.g., "1-0-1", "Twice daily"
    duration VARCHAR(50),    -- e.g., "7 days", "2 weeks"
    route VARCHAR(50) DEFAULT 'oral',  -- oral, IV, IM, topical, etc.
    instructions TEXT,
    quantity INTEGER DEFAULT 0,
    FOREIGN KEY (prescription_id) REFERENCES prescriptions(id) ON DELETE CASCADE
);

-- =====================================================
-- 12. ADMISSIONS — IPD admissions
-- =====================================================
CREATE TABLE IF NOT EXISTS admissions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    patient_id INTEGER NOT NULL,
    doctor_id INTEGER NOT NULL,
    bed_id INTEGER,
    ward_id INTEGER,
    admit_date DATETIME NOT NULL,
    expected_discharge DATE,
    discharge_date DATETIME,
    status VARCHAR(15) DEFAULT 'admitted' CHECK (status IN ('admitted','discharged','transferred','absconded')),
    reason TEXT,
    discharge_summary TEXT,
    discharge_instructions TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE,
    FOREIGN KEY (bed_id) REFERENCES beds(id) ON DELETE SET NULL,
    FOREIGN KEY (ward_id) REFERENCES wards(id) ON DELETE SET NULL
);

-- =====================================================
-- 13. VITALS — Patient vital recordings
-- =====================================================
CREATE TABLE IF NOT EXISTS vitals (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    patient_id INTEGER NOT NULL,
    admission_id INTEGER,
    appointment_id INTEGER,
    nurse_id INTEGER,
    blood_pressure_systolic INTEGER,
    blood_pressure_diastolic INTEGER,
    temperature DECIMAL(4,1),    -- in °F or °C
    pulse INTEGER,               -- beats per minute
    respiration_rate INTEGER,    -- breaths per minute
    oxygen_saturation DECIMAL(5,2),  -- SpO2 %
    blood_sugar DECIMAL(6,2),    -- mg/dL
    weight DECIMAL(5,2),         -- kg
    height DECIMAL(5,2),         -- cm
    bmi DECIMAL(5,2),
    notes TEXT,
    recorded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (admission_id) REFERENCES admissions(id) ON DELETE SET NULL,
    FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE SET NULL,
    FOREIGN KEY (nurse_id) REFERENCES nurses(id) ON DELETE SET NULL
);

-- =====================================================
-- 14. MEDICATION ADMINISTRATION — MAR records
-- =====================================================
CREATE TABLE IF NOT EXISTS medication_administration (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    admission_id INTEGER NOT NULL,
    patient_id INTEGER NOT NULL,
    nurse_id INTEGER NOT NULL,
    prescription_item_id INTEGER,
    drug_name VARCHAR(200) NOT NULL,
    dosage VARCHAR(100),
    scheduled_time DATETIME,
    administered_at DATETIME,
    status VARCHAR(15) DEFAULT 'pending' CHECK (status IN ('pending','administered','skipped','refused','held')),
    notes TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admission_id) REFERENCES admissions(id) ON DELETE CASCADE,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (nurse_id) REFERENCES nurses(id) ON DELETE CASCADE,
    FOREIGN KEY (prescription_item_id) REFERENCES prescription_items(id) ON DELETE SET NULL
);

-- =====================================================
-- 15. NURSING NOTES — Nurse observations
-- =====================================================
CREATE TABLE IF NOT EXISTS nursing_notes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    admission_id INTEGER NOT NULL,
    patient_id INTEGER NOT NULL,
    nurse_id INTEGER NOT NULL,
    note TEXT NOT NULL,
    priority VARCHAR(10) DEFAULT 'normal' CHECK (priority IN ('normal','urgent','critical')),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admission_id) REFERENCES admissions(id) ON DELETE CASCADE,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (nurse_id) REFERENCES nurses(id) ON DELETE CASCADE
);

-- =====================================================
-- 16. PHARMACY INVENTORY — Drug stock
-- =====================================================
CREATE TABLE IF NOT EXISTS pharmacy_inventory (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    drug_name VARCHAR(200) NOT NULL,
    generic_name VARCHAR(200),
    category VARCHAR(50),  -- tablet, capsule, syrup, injection, ointment, etc.
    manufacturer VARCHAR(100),
    batch_number VARCHAR(50),
    stock_quantity INTEGER DEFAULT 0,
    unit VARCHAR(20) DEFAULT 'pcs',  -- pcs, strips, bottles, vials, etc.
    unit_price DECIMAL(10,2) DEFAULT 0.00,
    selling_price DECIMAL(10,2) DEFAULT 0.00,
    expiry_date DATE,
    reorder_level INTEGER DEFAULT 10,
    storage_location VARCHAR(100),
    requires_prescription BOOLEAN DEFAULT 1,
    status VARCHAR(10) DEFAULT 'active' CHECK (status IN ('active','discontinued','expired')),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- =====================================================
-- 17. PHARMACY DISPENSING — Medicine dispensing log
-- =====================================================
CREATE TABLE IF NOT EXISTS pharmacy_dispensing (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    prescription_id INTEGER NOT NULL,
    prescription_item_id INTEGER,
    drug_id INTEGER,
    drug_name VARCHAR(200) NOT NULL,
    quantity_dispensed INTEGER NOT NULL,
    pharmacist_id INTEGER NOT NULL,
    notes TEXT,
    dispensed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (prescription_id) REFERENCES prescriptions(id) ON DELETE CASCADE,
    FOREIGN KEY (prescription_item_id) REFERENCES prescription_items(id) ON DELETE SET NULL,
    FOREIGN KEY (drug_id) REFERENCES pharmacy_inventory(id) ON DELETE SET NULL,
    FOREIGN KEY (pharmacist_id) REFERENCES users(id) ON DELETE CASCADE
);

-- =====================================================
-- 18. LAB TEST CATALOG — Available tests
-- =====================================================
CREATE TABLE IF NOT EXISTS lab_test_catalog (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    test_name VARCHAR(200) NOT NULL,
    test_code VARCHAR(20) UNIQUE,
    category VARCHAR(50),  -- hematology, biochemistry, microbiology, radiology, etc.
    description TEXT,
    sample_type VARCHAR(50),  -- blood, urine, stool, sputum, etc.
    price DECIMAL(10,2) DEFAULT 0.00,
    turnaround_time VARCHAR(50),  -- e.g., "2 hours", "24 hours", "3 days"
    normal_range TEXT,
    instructions TEXT,
    status VARCHAR(10) DEFAULT 'active' CHECK (status IN ('active','inactive')),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- =====================================================
-- 19. LAB ORDERS — Test orders from doctors
-- =====================================================
CREATE TABLE IF NOT EXISTS lab_orders (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    patient_id INTEGER NOT NULL,
    doctor_id INTEGER NOT NULL,
    appointment_id INTEGER,
    admission_id INTEGER,
    test_id INTEGER NOT NULL,
    priority VARCHAR(10) DEFAULT 'normal' CHECK (priority IN ('normal','urgent','stat')),
    status VARCHAR(20) DEFAULT 'ordered' CHECK (status IN ('ordered','sample_collected','processing','completed','cancelled')),
    clinical_notes TEXT,
    ordered_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE,
    FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE SET NULL,
    FOREIGN KEY (admission_id) REFERENCES admissions(id) ON DELETE SET NULL,
    FOREIGN KEY (test_id) REFERENCES lab_test_catalog(id) ON DELETE CASCADE
);

-- =====================================================
-- 20. LAB RESULTS — Test results
-- =====================================================
CREATE TABLE IF NOT EXISTS lab_results (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    lab_order_id INTEGER NOT NULL UNIQUE,
    result_value TEXT,
    result_unit VARCHAR(50),
    reference_range VARCHAR(100),
    interpretation VARCHAR(20) CHECK (interpretation IN ('normal','abnormal','critical','')),
    result_notes TEXT,
    attachments TEXT,  -- file paths, comma-separated
    technician_id INTEGER,
    verified_by INTEGER,
    verified_at DATETIME,
    uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (lab_order_id) REFERENCES lab_orders(id) ON DELETE CASCADE,
    FOREIGN KEY (technician_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL
);

-- =====================================================
-- 21. BILLING — Invoice header
-- =====================================================
CREATE TABLE IF NOT EXISTS billing (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    patient_id INTEGER NOT NULL,
    appointment_id INTEGER,
    admission_id INTEGER,
    invoice_number VARCHAR(30) UNIQUE NOT NULL,
    subtotal DECIMAL(12,2) DEFAULT 0.00,
    discount DECIMAL(12,2) DEFAULT 0.00,
    tax DECIMAL(12,2) DEFAULT 0.00,
    net_amount DECIMAL(12,2) DEFAULT 0.00,
    payment_status VARCHAR(10) DEFAULT 'unpaid' CHECK (payment_status IN ('unpaid','partial','paid','refunded')),
    payment_method VARCHAR(100) DEFAULT 'cash',
    payment_date DATETIME,
    insurance_claim_status VARCHAR(15) DEFAULT '' CHECK (insurance_claim_status IN ('not_applicable','submitted','approved','rejected','')),
    notes TEXT,
    created_by INTEGER,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE SET NULL,
    FOREIGN KEY (admission_id) REFERENCES admissions(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

-- =====================================================
-- 22. BILLING ITEMS — Invoice line items
-- =====================================================
CREATE TABLE IF NOT EXISTS billing_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    bill_id INTEGER NOT NULL,
    item_type VARCHAR(20) NOT NULL CHECK (item_type IN ('consultation','medicine','lab_test','bed_charge','procedure','nursing','other')),
    description VARCHAR(255) NOT NULL,
    quantity INTEGER DEFAULT 1,
    unit_price DECIMAL(10,2) DEFAULT 0.00,
    total_price DECIMAL(10,2) DEFAULT 0.00,
    reference_id INTEGER,  -- ID from related table (lab_order_id, prescription_id, etc.)
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (bill_id) REFERENCES billing(id) ON DELETE CASCADE
);

-- =====================================================
-- 23. AUDIT LOGS — System audit trail
-- =====================================================
CREATE TABLE IF NOT EXISTS audit_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER,
    user_name VARCHAR(100),
    action VARCHAR(20) NOT NULL CHECK (action IN ('create','read','update','delete','login','logout','export','print')),
    table_name VARCHAR(50),
    record_id INTEGER,
    description TEXT,
    old_values TEXT,  -- JSON
    new_values TEXT,  -- JSON
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- =====================================================
-- 24. NOTIFICATIONS — System notifications
-- =====================================================
CREATE TABLE IF NOT EXISTS notifications (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    title VARCHAR(200) NOT NULL,
    message TEXT,
    type VARCHAR(20) DEFAULT 'info' CHECK (type IN ('info','success','warning','error','appointment','lab','prescription','billing')),
    link VARCHAR(255),
    is_read BOOLEAN DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- =====================================================
-- 25. SERVICE PRICING — Hospital service prices
-- =====================================================
CREATE TABLE IF NOT EXISTS service_pricing (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    service_name VARCHAR(200) NOT NULL,
    category VARCHAR(50),
    price DECIMAL(10,2) NOT NULL,
    description TEXT,
    status VARCHAR(10) DEFAULT 'active',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- =====================================================
-- 26. PAYMENT METHODS — eSewa, Khalti, QR Payment Options
-- =====================================================
CREATE TABLE IF NOT EXISTS payment_methods (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name VARCHAR(100) NOT NULL,
    account_name VARCHAR(100),
    account_number VARCHAR(100),
    qr_image VARCHAR(255),
    instructions TEXT,
    status VARCHAR(20) DEFAULT 'active',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- =====================================================
-- INDEXES for performance
-- =====================================================
CREATE INDEX IF NOT EXISTS idx_users_role ON users(role);
CREATE INDEX IF NOT EXISTS idx_users_status ON users(status);
CREATE INDEX IF NOT EXISTS idx_users_username ON users(username);
CREATE INDEX IF NOT EXISTS idx_patients_uhid ON patients(uhid);
CREATE INDEX IF NOT EXISTS idx_patients_user_id ON patients(user_id);
CREATE INDEX IF NOT EXISTS idx_doctors_user_id ON doctors(user_id);
CREATE INDEX IF NOT EXISTS idx_doctors_department ON doctors(department_id);
CREATE INDEX IF NOT EXISTS idx_nurses_user_id ON nurses(user_id);
CREATE INDEX IF NOT EXISTS idx_nurses_ward ON nurses(ward_id);
CREATE INDEX IF NOT EXISTS idx_appointments_patient ON appointments(patient_id);
CREATE INDEX IF NOT EXISTS idx_appointments_doctor ON appointments(doctor_id);
CREATE INDEX IF NOT EXISTS idx_appointments_date ON appointments(appointment_date);
CREATE INDEX IF NOT EXISTS idx_appointments_status ON appointments(status);
CREATE INDEX IF NOT EXISTS idx_medical_records_patient ON medical_records(patient_id);
CREATE INDEX IF NOT EXISTS idx_medical_records_doctor ON medical_records(doctor_id);
CREATE INDEX IF NOT EXISTS idx_prescriptions_patient ON prescriptions(patient_id);
CREATE INDEX IF NOT EXISTS idx_prescriptions_status ON prescriptions(status);
CREATE INDEX IF NOT EXISTS idx_admissions_patient ON admissions(patient_id);
CREATE INDEX IF NOT EXISTS idx_admissions_status ON admissions(status);
CREATE INDEX IF NOT EXISTS idx_vitals_patient ON vitals(patient_id);
CREATE INDEX IF NOT EXISTS idx_lab_orders_patient ON lab_orders(patient_id);
CREATE INDEX IF NOT EXISTS idx_lab_orders_status ON lab_orders(status);
CREATE INDEX IF NOT EXISTS idx_billing_patient ON billing(patient_id);
CREATE INDEX IF NOT EXISTS idx_billing_status ON billing(payment_status);
CREATE INDEX IF NOT EXISTS idx_notifications_user ON notifications(user_id);
CREATE INDEX IF NOT EXISTS idx_notifications_read ON notifications(is_read);
CREATE INDEX IF NOT EXISTS idx_audit_logs_user ON audit_logs(user_id);
CREATE INDEX IF NOT EXISTS idx_audit_logs_action ON audit_logs(action);
CREATE INDEX IF NOT EXISTS idx_audit_logs_table ON audit_logs(table_name);
