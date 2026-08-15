# 🏥 MediCare Hospital Management System (HMS)

A complete, enterprise-grade, self-hosted **Hospital Management System (HMS)** built with **PHP 8.4** and **SQLite3 PDO**.

All patient data, medical records, digital prescriptions, lab reports, and financial billing records are stored locally on your laptop's **`E:\HM DATA\hms.db`** drive.

---

## 🚀 Quick Start Guide: How to Run the Server

### 1. Prerequisites
- **PHP 8.4** (or PHP 8.x with `pdo_sqlite` extension enabled).
- **SQLite3** enabled in `php.ini` (`extension=pdo_sqlite`).

### 2. Start the Local Server
Open PowerShell or Command Prompt in `E:\hospital real one\` and run:

```powershell
& "C:\Users\rsah0\AppData\Local\Microsoft\WinGet\Packages\PHP.PHP.8.4_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe" -S localhost:9000 -t "E:\hospital real one"
```

Or using standard PHP in PATH:
```bash
php -S localhost:9000 -t "E:\hospital real one"
```

### 3. Access in Browser
Open your browser and navigate to:
👉 **`http://localhost:9000/auth/login.php`**

---

## 🔑 Main Super Admin Credentials

| Parameter | Credential |
|---|---|
| **Login URL** | `http://localhost:9000/auth/login.php` |
| **Username / Email** | `sahkkr702@gmail.com` |
| **Password** | `kumar@9090` |
| **Role** | `admin` (Super Administrator) |

> **Note**: Logged in as Admin, you can create, activate, deactivate, or delete Doctors, Nurses, Receptionists, Pharmacists, Lab Techs, and Patients via **User Management** (`/admin/manage_users.php`).

---

## 🧭 How to See & Test All 7 Role Modules

The system features 7 distinct, color-coded role portals:

### 1. 🧑‍💼 Administrator Portal (`http://localhost:9000/admin/dashboard.php`)
- **Dashboard**: Live analytics, revenue stats, bed occupancy rate, department bar chart, bed doughnut chart.
- **User Management** (`/admin/manage_users.php`): Create accounts for all 7 roles, toggle Active/Inactive, or Delete users.
- **Departments** (`/admin/manage_departments.php`): Create, edit, activate, or delete medical departments.
- **Wards & Beds** (`/admin/manage_wards.php`): Manage inpatient wards, change bed status, delete beds.
- **Service Pricing** (`/admin/manage_pricing.php`): Set standard OPD fees, procedures, bed daily charges.
- **Payment & QR Codes** (`/admin/manage_payment_methods.php`): Manage eSewa, Khalti, Fonepay, Cash & upload QR code images.
- **Analytics & Reports** (`/admin/reports.php`): Monthly revenue trends and top doctor performance metrics.
- **Audit Logs** (`/admin/audit_logs.php`): System audit trail tracking logins, deletions, and updates.
- **Settings** (`/admin/settings.php`): Hospital contact details and local database backup.

### 2. 🧑‍💼 Receptionist Desk (`http://localhost:9000/receptionist/dashboard.php`)
- **Register Patient** (`/receptionist/register_patient.php`): Create new patient files with auto-generated UHID.
- **Book Appointments** (`/receptionist/appointments.php`): Schedule consultations, assign token numbers.
- **OPD Check-In Desk** (`/receptionist/check_in.php`): Check in arriving patients into the doctor queue.
- **Search Patient** (`/receptionist/search_patient.php`): Lookup directory by UHID, Name, or Mobile number.
- **Billing & Checkout** (`/receptionist/billing.php`): Generate bills, collect payments, and render scannable **eSewa / Khalti QR Codes**.

### 3. 👩‍⚕️ Doctor EMR Workspace (`http://localhost:9000/doctor/dashboard.php`)
- **Patient Queue** (`/doctor/patient_queue.php`): Real-time OPD token queue for today.
- **EMR Consultation** (`/doctor/consultation.php`): Record symptoms, diagnosis, clinical notes, and digital e-Prescriptions.
- **Patient History** (`/doctor/patient_history.php`): Timeline of past consultations and treatments.
- **Order Lab Tests** (`/doctor/order_lab_test.php`): Request CBC, X-Rays, LFT/KFT tests to the lab.
- **Inpatient Admission** (`/doctor/admit_patient.php`): Admit patients to IPD wards/beds.
- **Discharge Summary** (`/doctor/discharge.php`): Write discharge summaries and release beds.

### 4. 👩‍⚕️ Nurse Ward Station (`http://localhost:9000/nurse/dashboard.php`)
- **Ward Directory** (`/nurse/ward_patients.php`): Admitted patients list across all hospital wards.
- **Vitals Charting** (`/nurse/vitals.php`): Log Blood Pressure, Temperature, Pulse, and SpO2.
- **Medication Administration (MAR)** (`/nurse/medication.php`): Log given medicine doses.
- **Nursing Notes** (`/nurse/nursing_notes.php`): Shift observations and critical priority alerts for doctors.

### 5. 🔬 Laboratory Module (`http://localhost:9000/lab/dashboard.php`)
- **Test Worklist** (`/lab/test_orders.php`): Pending diagnostic orders from OPD/IPD doctors.
- **Specimen Collection** (`/lab/collect_sample.php`): Mark blood/urine samples collected with barcode tracking.
- **Upload Results** (`/lab/upload_result.php`): Publish test values and interpretations (notifies Doctor & Patient).
- **Test Catalog** (`/lab/test_catalog.php`): Configure available lab tests and reference ranges.

### 6. 💊 Pharmacy Module (`http://localhost:9000/pharmacy/dashboard.php`)
- **Prescription Dispensing** (`/pharmacy/dispense.php`): Verify e-Prescriptions and dispense medicines (**automatically deducts stock quantity**).
- **Drug Inventory** (`/pharmacy/inventory.php`): Track stock quantities, expiry dates, unit cost, and selling prices.
- **Add Medicine** (`/pharmacy/add_medicine.php`): Add new pharmaceutical stock.
- **Stock Alerts** (`/pharmacy/stock_alerts.php`): Low stock and near-expiry warning alerts.

### 7. 🧑‍🦽 Patient Portal (`http://localhost:9000/patient/dashboard.php`)
- **My Health Portal**: View UHID, blood group, upcoming appointments.
- **Book Appointments** (`/patient/appointments.php`): Online appointment booking with specialist doctors.
- **Medical Records** (`/patient/medical_records.php`): EHR consultation diagnoses and clinical advice.
- **My Prescriptions** (`/patient/prescriptions.php`): Electronic prescriptions issued by doctors.
- **Lab Reports** (`/patient/lab_reports.php`): View published blood test and diagnostic reports.
- **My Bills** (`/patient/bills.php`): Payment receipts and invoice history.

---

## 🔒 Full Application Security & Firewall Protection

The application includes multi-layered enterprise security controls:

### 1. Application-Level Security
- **HTTP Security Headers** (`config/security.php`):
  - `X-Frame-Options: SAMEORIGIN` (prevents Clickjacking)
  - `X-XSS-Protection: 1; mode=block` (Cross-Site Scripting protection)
  - `X-Content-Type-Options: nosniff` (MIME sniffing defense)
  - `Content-Security-Policy`: Restricts resource execution to trusted sources.
  - `Referrer-Policy: strict-origin-when-cross-origin`
- **Brute-Force Rate Limiting**: Max 5 failed login attempts per 15-minute window per IP.
- **SQL Injection Immunity**: 100% database interactions use PDO Prepared Statements with bound parameters.
- **XSS Output Encoding**: All user-supplied input rendered via `sanitize()` (HTML Entity Encoding).
- **Session Security**: Cookies set with `HttpOnly`, `SameSite=Lax`, and session IDs regenerated upon login.
- **Direct File Protection** (`.htaccess`): Blocks direct web access to `.db`, `.sql`, `.env`, and config files.

---

### 2. Windows Firewall Setup Commands (Server Security)

To secure the local server on Windows Defender Firewall:

#### Option A: Allow Local Access Only (Recommended for local host)
Run PowerShell as Administrator to restrict port 9000 to `localhost` (127.0.0.1):

```powershell
New-NetFirewallRule -DisplayName "HMS Local Server (Port 9000)" -Direction Inbound -Action Allow -Protocol TCP -LocalPort 9000 -RemoteAddress 127.0.0.1
```

#### Option B: Allow Local Network / LAN Access (For hospital computers on same Wi-Fi/LAN)
```powershell
New-NetFirewallRule -DisplayName "HMS Hospital LAN Access (Port 9000)" -Direction Inbound -Action Allow -Protocol TCP -LocalPort 9000 -Profile Private
```

---

## 🗄️ Database Backup & File Location

- **Database Path**: `E:\HM DATA\hms.db`
- **Automated Backup**: Copy the `E:\HM DATA\hms.db` file to a USB drive or cloud backup anytime to save a complete backup of all hospital records.
