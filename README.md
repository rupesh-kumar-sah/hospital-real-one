# 🏥 MediCare Hospital Management System (HMS)

A complete, enterprise-grade, self-hosted **Hospital Management System (HMS)** built with **PHP 8.4** and **SQLite3 PDO**.

All patient data, medical records, digital prescriptions, lab reports, and financial billing records are stored locally on your laptop's **`E:\HM DATA\hms.db`** drive.

---

## 🚀 Production Deployment: Vercel + Render

This repository is configured for a split deployment:

- **Vercel** serves the public frontend URL and securely proxies requests to the backend.
- **Render** runs the PHP/Apache backend.
- **Render PostgreSQL** stores the application database on Render's private network.

Neon PostgreSQL can also be used as the production database. Set its rotated
connection string as Render's `DATABASE_URL` secret; never commit it or place
it in frontend code. The application parses `postgresql://` and `postgres://`
URLs and enforces `sslmode=require`.

### Deploy the backend first

1. Create a Render Blueprint from `render.yaml`.
2. Keep the generated `APP_ENCRYPTION_KEY` and `JWT_SECRET` values private. Never copy them into Git or Vercel.
3. Add every production Vercel origin as a comma-separated `FRONTEND_URL` value (for example, the production URL and any approved preview URL).
4. Confirm Render reports `/api/health.php` as healthy before deploying the frontend.

The free deployment uses SQLite inside the Render container. It is suitable only
for demonstrations because data can be lost when the free service restarts or
redeploys. Durable PostgreSQL storage requires billing on Render.

### Deploy the frontend

1. Import this repository into Vercel.
2. Use the repository root as the project root and leave the framework preset unset.
3. Deploy the production branch. `vercel.json` keeps the frontend URL stable while routing application requests to the Render backend.
4. Update Render's `FRONTEND_URL` if the Vercel production domain differs from `https://medicare-hms.vercel.app`.

### Production security checklist

- Use HTTPS-only custom domains for both services.
- Rotate generated secrets if they are ever exposed.
- Restrict Render access to the required Vercel origins; do not use `*` with credentialed requests.
- Configure database backups for the Render persistent disk and test restoration before storing live patient records.
- Set `APP_DEBUG=false` in Render and do not commit `.env`.

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

## 🔑 Initial administrator account

The initial account is created from the deployment seed data. Change its password
immediately after the first login and never store production credentials in this
repository.

Administrator-created accounts receive a cryptographically random temporary
password, which is shown once to the administrator and must be changed at first
sign-in. Administrators can enroll RFC 6238 TOTP MFA from
`/mgmt-x7k2p/mfa.php`; backup codes are displayed once and stored only as hashes.
For existing databases, apply the matching migration in
`sql/migrations/` before deploying the updated PHP files.

---

## 🧭 How to See & Test All 7 Role Modules

The system features 7 distinct, color-coded role portals:

### 1. 🧑‍💼 Administrator Portal (`http://localhost:9000/mgmt-x7k2p/dashboard.php`)
- **Dashboard**: Live analytics, revenue stats, bed occupancy rate, department bar chart, bed doughnut chart.
- **User Management** (`/mgmt-x7k2p/manage_users.php`): Create accounts for all 7 roles, toggle Active/Inactive, or Delete users.
- **Departments** (`/mgmt-x7k2p/manage_departments.php`): Create, edit, activate, or delete medical departments.
- **Wards & Beds** (`/mgmt-x7k2p/manage_wards.php`): Manage inpatient wards, change bed status, delete beds.
- **Service Pricing** (`/mgmt-x7k2p/manage_pricing.php`): Set standard OPD fees, procedures, bed daily charges.
- **Payment & QR Codes** (`/mgmt-x7k2p/manage_payment_methods.php`): Manage eSewa, Khalti, Fonepay, Cash & upload QR code images.
- **Analytics & Reports** (`/mgmt-x7k2p/reports.php`): Monthly revenue trends and top doctor performance metrics.
- **Audit Logs** (`/mgmt-x7k2p/audit_logs.php`): System audit trail tracking logins, deletions, and updates.
- **Settings** (`/mgmt-x7k2p/settings.php`): Hospital contact details and Neon database administration.

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

### Encrypted backup to Google Drive or OneDrive

The recommended free backup is an encrypted SQLite snapshot saved in a folder
that Google Drive or OneDrive synchronizes. The backup file is authenticated
with AES-256-GCM; the database is never uploaded in plaintext.

1. Add `BACKUP_ENCRYPTION_KEY` to `.env` using a new random 64-character
   hexadecimal value:
   `php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"`
2. Install Google Drive for Desktop or OneDrive and choose a private sync
   folder. Do not share this folder.
3. Run:
   `powershell -ExecutionPolicy Bypass -File tools\run_backup.ps1 -SyncDirectory "C:\Users\<you>\Google Drive\MediCare HMS Backups"`
4. Confirm the `.mchms` and `.sha256` files appear in the cloud folder before
   removing or replacing any local database.

The PowerShell wrapper keeps 30 days of encrypted backups by default. To test a
restore, use a separate output path:

`php tools\encrypted_backup.php restore "C:\path\hms-YYYYMMDD-HHMMSS.mchms" "C:\temp\hms-restore.db"`

Keep `BACKUP_ENCRYPTION_KEY` separately from the cloud folder. Without it, the
encrypted backups cannot be restored.

For recurring encrypted backups, keep the backup folder private and run:

`powershell -ExecutionPolicy Bypass -File tools\auto_backup.ps1 -BackupDirectory "C:\Users\<you>\Google Drive\MediCare HMS Backups"`

This creates a verified AES-256-GCM backup every six hours. Only the encrypted
`.mchms` files should be synchronized to cloud storage.

### Permanent deletion controls

Admin Settings includes a danger-zone action that requires the current
administrator password, CSRF token, an exact confirmation phrase, and a browser
confirmation. It permanently wipes all records from the connected Neon
PostgreSQL database and logs the administrator out. It cannot delete local or
cloud backup files because the website has no access to those storage accounts.

To permanently remove local encrypted backup files, run this separately on the
hospital computer and type the required confirmation:

`powershell -ExecutionPolicy Bypass -File tools\purge_backup_files.ps1`

Delete cloud copies separately from the private Google Drive/OneDrive folder
after confirming that no restoration is required.

### Automatic readable data export

To keep CSV files and the readable `Hospital_Data_Sheets.html` dashboard
updated without clicking the admin button, run:

`powershell -ExecutionPolicy Bypass -File tools\auto_export.ps1 -ExportDirectory "C:\Users\<you>\Google Drive\MediCare HMS Data"`

The watcher checks the SQLite database every 30 seconds and exports whenever
data changes. Google Drive or OneDrive then synchronizes those generated files.
The folder must remain private because CSV and HTML exports are unencrypted and
contain patient data. This is file synchronization, not direct Google Sheets
API synchronization; opening a CSV in Sheets is still a manual step.
